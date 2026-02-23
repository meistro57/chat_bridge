<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\ModelPrice;
use App\Models\Persona;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    private const CACHE_TTL = 300;

    private const CACHE_VERSION_KEY = 'analytics:%d:version';

    /**
     * @return array{average_length:float, completion_rate:float}
     */
    public function getConversationMetrics(User $user): array
    {
        return Cache::remember($this->cacheKey($user, 'conversation-metrics'), self::CACHE_TTL, function () use ($user) {
            $totalConversations = $user->conversations()->count();
            $completedConversations = $user->conversations()->where('status', 'completed')->count();

            $averageLength = $this->averageConversationLength($user);
            $completionRate = $totalConversations > 0
                ? round($completedConversations / $totalConversations, 4)
                : 0.0;

            return [
                'average_length' => $averageLength,
                'completion_rate' => $completionRate,
            ];
        });
    }

    /**
     * @return array<int, array{provider:string, tokens:int}>
     */
    public function getTokenUsageByProvider(User $user): array
    {
        return Cache::remember($this->cacheKey($user, 'token-usage-provider'), self::CACHE_TTL, function () use ($user) {
            $stats = Message::query()
                ->join('conversations', 'messages.conversation_id', '=', 'conversations.id')
                ->where('conversations.user_id', $user->id)
                ->whereNotNull('messages.persona_id')
                ->selectRaw('CASE WHEN messages.persona_id = conversations.persona_a_id THEN conversations.provider_a ELSE conversations.provider_b END as provider')
                ->selectRaw('SUM(COALESCE(messages.tokens_used, 0)) as tokens')
                ->groupBy('provider')
                ->orderByDesc('tokens')
                ->get();

            return $stats->map(fn ($row) => [
                'provider' => (string) $row->provider,
                'tokens' => (int) $row->tokens,
            ])->all();
        });
    }

    /**
     * @return array{total_cost:float, by_provider:array<int, array{provider:string, cost:float}>}
     */
    public function getCostEstimation(User $user): array
    {
        return Cache::remember($this->cacheKey($user, 'cost-estimation'), self::CACHE_TTL, function () use ($user) {
            $byProvider = [];
            $totalCost = 0.0;
            $costLookup = [];

            $query = Message::query()
                ->join('conversations', 'messages.conversation_id', '=', 'conversations.id')
                ->where('conversations.user_id', $user->id)
                ->whereNotNull('messages.persona_id')
                ->select([
                    'messages.id',
                    'messages.tokens_used',
                    'messages.persona_id',
                    'conversations.persona_a_id',
                    'conversations.persona_b_id',
                    'conversations.provider_a',
                    'conversations.provider_b',
                    'conversations.model_a',
                    'conversations.model_b',
                ])
                ->orderBy('messages.id');

            $query->chunk(500, function (Collection $chunk) use (&$byProvider, &$totalCost, &$costLookup) {
                foreach ($chunk as $row) {
                    $tokens = (int) ($row->tokens_used ?? 0);
                    if ($tokens <= 0) {
                        continue;
                    }

                    $isPersonaA = $row->persona_id && $row->persona_a_id && $row->persona_id === $row->persona_a_id;
                    $provider = $isPersonaA ? $row->provider_a : $row->provider_b;
                    $model = $isPersonaA ? $row->model_a : $row->model_b;

                    $costPerToken = $this->estimateCostPerToken($model, $provider, $costLookup);
                    $cost = $tokens * $costPerToken;

                    $totalCost += $cost;
                    $byProvider[$provider] = ($byProvider[$provider] ?? 0) + $cost;
                }
            });

            $providerBreakdown = collect($byProvider)
                ->map(fn ($cost, $provider) => [
                    'provider' => (string) $provider,
                    'cost' => round($cost, 4),
                ])
                ->sortByDesc('cost')
                ->values()
                ->all();

            return [
                'total_cost' => round($totalCost, 4),
                'by_provider' => $providerBreakdown,
            ];
        });
    }

    /**
     * @return array<int, array{persona_name:string, count:int}>
     */
    public function getPersonaPopularity(User $user): array
    {
        return Cache::remember($this->cacheKey($user, 'persona-popularity'), self::CACHE_TTL, function () use ($user) {
            return Message::query()
                ->whereHas('conversation', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->whereNotNull('persona_id')
                ->select('persona_id', DB::raw('COUNT(*) as message_count'))
                ->groupBy('persona_id')
                ->with('persona:id,name')
                ->orderByDesc('message_count')
                ->limit(10)
                ->get()
                ->map(function ($stat) {
                    return [
                        'persona_name' => $stat->persona->name ?? 'Unknown',
                        'count' => (int) $stat->message_count,
                    ];
                })
                ->all();
        });
    }

    /**
     * @return array<int, array{date:string, count:int}>
     */
    public function getTrendData(User $user, int $days = 30): array
    {
        return Cache::remember($this->cacheKey($user, 'trend-data-'.$days), self::CACHE_TTL, function () use ($user, $days) {
            $startDate = CarbonImmutable::now()->subDays($days - 1)->startOfDay();

            $results = Conversation::query()
                ->where('user_id', $user->id)
                ->where('created_at', '>=', $startDate)
                ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->map(fn ($row) => [
                    'date' => $row->date,
                    'count' => (int) $row->count,
                ])
                ->all();

            return $this->fillDateSeries($startDate, $days, $results);
        });
    }

    /**
     * @return array{total_conversations:int, total_messages:int, total_tokens:int, total_cost:float}
     */
    public function getOverviewStats(User $user): array
    {
        return Cache::remember($this->cacheKey($user, 'overview-stats'), self::CACHE_TTL, function () use ($user) {
            $conversationIds = $user->conversations()->pluck('id');

            $totalMessages = Message::whereIn('conversation_id', $conversationIds)->count();
            $totalTokens = (int) Message::whereIn('conversation_id', $conversationIds)->sum('tokens_used');
            $cost = $this->getCostEstimation($user);

            return [
                'total_conversations' => $conversationIds->count(),
                'total_messages' => $totalMessages,
                'total_tokens' => $totalTokens,
                'total_cost' => $cost['total_cost'],
            ];
        });
    }

    /**
     * @return array<int, array{provider:string, count:int}>
     */
    public function getProviderUsage(User $user): array
    {
        return Cache::remember($this->cacheKey($user, 'provider-usage'), self::CACHE_TTL, function () use ($user) {
            $stats = Conversation::query()
                ->where('user_id', $user->id)
                ->selectRaw('provider_a as provider')
                ->selectRaw('COUNT(*) as count')
                ->groupBy('provider_a')
                ->get();

            $providerCounts = collect($stats)->mapWithKeys(fn ($row) => [
                $row->provider => (int) $row->count,
            ]);

            $secondaryStats = Conversation::query()
                ->where('user_id', $user->id)
                ->selectRaw('provider_b as provider')
                ->selectRaw('COUNT(*) as count')
                ->groupBy('provider_b')
                ->get();

            foreach ($secondaryStats as $row) {
                $providerCounts[$row->provider] = ($providerCounts[$row->provider] ?? 0) + (int) $row->count;
            }

            return $providerCounts
                ->map(fn ($count, $provider) => [
                    'provider' => (string) $provider,
                    'count' => (int) $count,
                ])
                ->sortByDesc('count')
                ->values()
                ->all();
        });
    }

    /**
     * @return array<int, array{id:string,status:string,created_at:string,updated_at:string,provider_a:string,provider_b:string,model_a:?string,model_b:?string,message_count:int,total_tokens:int}>
     */
    public function getRecentConversations(User $user, int $limit = 8): array
    {
        return Cache::remember($this->cacheKey($user, 'recent-conversations-'.$limit), self::CACHE_TTL, function () use ($user, $limit) {
            return Conversation::query()
                ->where('user_id', $user->id)
                ->latest()
                ->withCount('messages')
                ->withSum('messages', 'tokens_used')
                ->limit($limit)
                ->get([
                    'id',
                    'status',
                    'created_at',
                    'updated_at',
                    'provider_a',
                    'provider_b',
                    'model_a',
                    'model_b',
                ])
                ->map(fn (Conversation $conversation) => [
                    'id' => (string) $conversation->id,
                    'status' => $conversation->status,
                    'created_at' => $conversation->created_at->toDateTimeString(),
                    'updated_at' => $conversation->updated_at->toDateTimeString(),
                    'provider_a' => $conversation->provider_a,
                    'provider_b' => $conversation->provider_b,
                    'model_a' => $conversation->model_a,
                    'model_b' => $conversation->model_b,
                    'message_count' => (int) $conversation->messages_count,
                    'total_tokens' => (int) ($conversation->messages_sum_tokens_used ?? 0),
                ])
                ->all();
        });
    }

    public function getPersonas(User $user): Collection
    {
        return Persona::query()
            ->where(function ($query) use ($user) {
                $query->whereNull('user_id')
                    ->orWhere('user_id', $user->id);
            })
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function averageConversationLength(User $user): float
    {
        $subQuery = Message::query()
            ->select('conversation_id', DB::raw('COUNT(*) as message_count'))
            ->whereHas('conversation', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->groupBy('conversation_id');

        $average = DB::query()->fromSub($subQuery, 'conversation_counts')->avg('message_count');

        return $average ? round((float) $average, 2) : 0.0;
    }

    /**
     * @param  array<int, array{date:string, count:int}>  $results
     * @return array<int, array{date:string, count:int}>
     */
    private function fillDateSeries(CarbonImmutable $startDate, int $days, array $results): array
    {
        $lookup = collect($results)->keyBy('date');
        $series = [];

        for ($i = 0; $i < $days; $i++) {
            $date = $startDate->addDays($i)->toDateString();
            $series[] = [
                'date' => $date,
                'count' => (int) ($lookup[$date]['count'] ?? 0),
            ];
        }

        return $series;
    }

    /**
     * @param  array<string, float>  $costLookup
     */
    private function estimateCostPerToken(?string $model, ?string $provider, array &$costLookup): float
    {
        $lookupKey = ($provider ?? 'unknown').'|'.($model ?? 'unknown');

        if (array_key_exists($lookupKey, $costLookup)) {
            return $costLookup[$lookupKey];
        }

        $dynamicCostPerToken = $this->estimateDynamicCostPerToken($model, $provider);
        if ($dynamicCostPerToken !== null) {
            $costLookup[$lookupKey] = $dynamicCostPerToken;

            return $dynamicCostPerToken;
        }

        $pricing = config('ai.pricing', []);
        $modelPricing = $pricing['models'] ?? [];

        if ($model && array_key_exists($model, $modelPricing)) {
            $modelCosts = $modelPricing[$model];
            $prompt = (float) ($modelCosts['prompt_per_million'] ?? 0);
            $completion = (float) ($modelCosts['completion_per_million'] ?? 0);

            $costLookup[$lookupKey] = (($prompt + $completion) / 2) / 1_000_000;

            return $costLookup[$lookupKey];
        }

        $providerPricing = $pricing['providers'] ?? [];

        if ($provider && array_key_exists($provider, $providerPricing)) {
            $costLookup[$lookupKey] = (float) $providerPricing[$provider];

            return $costLookup[$lookupKey];
        }

        $costLookup[$lookupKey] = (float) ($pricing['per_token_default'] ?? 0);

        return $costLookup[$lookupKey];
    }

    private function estimateDynamicCostPerToken(?string $model, ?string $provider): ?float
    {
        if (! is_string($model) || $model === '') {
            return null;
        }

        $candidateModels = $this->candidateModelKeys($model, $provider);

        $records = ModelPrice::query()
            ->whereIn('model', $candidateModels)
            ->orderByDesc('updated_at')
            ->get(['provider', 'model', 'prompt_per_million', 'completion_per_million']);

        if ($records->isEmpty()) {
            return null;
        }

        if (is_string($provider) && $provider !== '') {
            foreach ($candidateModels as $candidateModel) {
                $record = $records->first(function (ModelPrice $price) use ($provider, $candidateModel) {
                    return $price->provider === $provider && $price->model === $candidateModel;
                });

                if ($record !== null) {
                    return (($record->prompt_per_million + $record->completion_per_million) / 2) / 1_000_000;
                }
            }
        }

        foreach ($candidateModels as $candidateModel) {
            $record = $records->first(fn (ModelPrice $price) => $price->model === $candidateModel);

            if ($record !== null) {
                return (($record->prompt_per_million + $record->completion_per_million) / 2) / 1_000_000;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function candidateModelKeys(string $model, ?string $provider): array
    {
        $candidates = [$model];

        if (is_string($provider) && $provider !== '' && ! str_contains($model, '/')) {
            $candidates[] = "{$provider}/{$model}";
        }

        if (str_contains($model, '/')) {
            $candidates[] = substr($model, strpos($model, '/') + 1);
        }

        return array_values(array_unique($candidates));
    }

    private function cacheKey(User $user, string $suffix): string
    {
        return 'analytics:'.$user->id.':v'.$this->cacheVersion($user).':'.$suffix;
    }

    public function invalidateUserCache(User $user): void
    {
        $versionKey = sprintf(self::CACHE_VERSION_KEY, $user->id);
        $currentVersion = (int) Cache::get($versionKey, 1);

        Cache::forever($versionKey, $currentVersion + 1);
    }

    private function cacheVersion(User $user): int
    {
        return (int) Cache::get(sprintf(self::CACHE_VERSION_KEY, $user->id), 1);
    }
}
