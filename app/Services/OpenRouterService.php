<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenRouterService
{
    protected string $apiKey;

    protected string $baseUrl = 'https://openrouter.ai/api/v1';

    public function __construct()
    {
        $this->apiKey = (string) (config('services.openrouter.key') ?? '');
    }

    protected function headers(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->apiKey,
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * Get total credits purchased and used.
     */
    public function getCredits(): ?array
    {
        return Cache::remember('openrouter_credits', 60, function () {
            try {
                $response = Http::withHeaders($this->headers())
                    ->timeout(10)
                    ->get("{$this->baseUrl}/credits");

                if ($response->successful()) {
                    $data = $response->json('data', []);
                    $total = $data['total_credits'] ?? 0;
                    $used = $data['total_usage'] ?? 0;

                    return [
                        'total_credits' => round($total, 4),
                        'total_usage' => round($used, 4),
                        'balance' => round($total - $used, 4),
                    ];
                }

                Log::warning('OpenRouter credits fetch failed', ['status' => $response->status()]);

                return null;
            } catch (\Exception $e) {
                Log::error('OpenRouter credits exception', ['error' => $e->getMessage()]);

                return null;
            }
        });
    }

    /**
     * Get activity for the last 30 days, optionally filtered by date.
     */
    public function getActivity(?string $date = null): ?array
    {
        $cacheKey = 'openrouter_activity_'.($date ?? 'all');

        return Cache::remember($cacheKey, 120, function () use ($date) {
            try {
                $params = $date ? ['date' => $date] : [];
                $response = Http::withHeaders($this->headers())
                    ->timeout(10)
                    ->get("{$this->baseUrl}/activity", $params);

                if ($response->successful()) {
                    return $response->json('data', []);
                }

                $status = $response->status();
                $body = $this->truncateResponseBody($response->body());
                Log::warning('OpenRouter activity fetch failed', [
                    'status' => $status,
                    'date' => $date,
                    'body' => $body,
                ]);

                if ($date !== null && in_array($status, [400, 422], true)) {
                    $fallbackResponse = Http::withHeaders($this->headers())
                        ->timeout(10)
                        ->get("{$this->baseUrl}/activity");

                    if ($fallbackResponse->successful()) {
                        Log::info('OpenRouter activity date-filter request failed; recovered via unfiltered fallback', [
                            'status' => $status,
                            'date' => $date,
                        ]);

                        return $this->filterActivityByDate($fallbackResponse->json('data', []), $date);
                    }
                }

                return null;
            } catch (\Exception $e) {
                Log::error('OpenRouter activity exception', ['error' => $e->getMessage()]);

                return null;
            }
        });
    }

    /**
     * Get key info (rate limits, credits remaining).
     */
    public function getKeyInfo(): ?array
    {
        return Cache::remember('openrouter_key_info', 60, function () {
            try {
                $response = Http::withHeaders($this->headers())
                    ->timeout(10)
                    ->get("{$this->baseUrl}/key");

                if ($response->successful()) {
                    return $response->json('data', $response->json());
                }

                Log::warning('OpenRouter key info fetch failed', ['status' => $response->status()]);

                return null;
            } catch (\Exception $e) {
                Log::error('OpenRouter key info exception', ['error' => $e->getMessage()]);

                return null;
            }
        });
    }

    /**
     * Summarized stats for dashboard widget.
     */
    public function getDashboardStats(): array
    {
        $credits = $this->getCredits();
        $activity = $this->getActivity();
        $keyInfo = $this->getKeyInfo();
        $activitySummary = $this->summarizeActivity($activity);

        // Aggregate spend by model from activity
        $byModel = [];
        if (is_array($activity)) {
            foreach ($activity as $item) {
                $model = (string) ($item['model'] ?? 'unknown');
                $cost = $this->toFloat($item['cost'] ?? 0);
                $byModel[$model] = ($byModel[$model] ?? 0) + $cost;
            }
            arsort($byModel);
            $byModel = array_slice($byModel, 0, 5, true); // top 5
        }

        // Today's spend
        $today = now()->toDateString();
        $todayActivity = $this->getActivity($today);
        $todaySpend = 0;
        if (is_array($todayActivity)) {
            foreach ($todayActivity as $item) {
                $todaySpend += $this->toFloat($item['cost'] ?? 0);
            }
        }

        $topModelName = array_key_first($byModel);
        $topModelCost = is_string($topModelName) ? (float) ($byModel[$topModelName] ?? 0) : 0.0;
        $topModelShare = $activitySummary['spend_30d'] > 0
            ? round(($topModelCost / $activitySummary['spend_30d']) * 100, 2)
            : 0.0;

        return [
            'credits' => $credits,
            'today_spend' => round($todaySpend, 6),
            'top_models' => $byModel,
            'activity_days' => is_array($activity) ? count($activity) : 0,
            'activity_summary' => $activitySummary,
            'top_model' => [
                'name' => $topModelName,
                'cost' => round($topModelCost, 6),
                'share_percent' => $topModelShare,
            ],
            'key_limits' => [
                'limit' => $this->toFloat($keyInfo['limit'] ?? 0),
                'usage' => $this->toFloat($keyInfo['usage'] ?? 0),
                'is_free_tier' => (bool) ($keyInfo['is_free_tier'] ?? false),
            ],
            'last_updated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $activity
     * @return array{requests_30d:int,requests_7d:int,spend_30d:float,spend_7d:float,active_days:int,avg_daily_spend:float,avg_cost_per_request:float}
     */
    protected function summarizeActivity(?array $activity): array
    {
        if (! is_array($activity)) {
            return [
                'requests_30d' => 0,
                'requests_7d' => 0,
                'spend_30d' => 0.0,
                'spend_7d' => 0.0,
                'active_days' => 0,
                'avg_daily_spend' => 0.0,
                'avg_cost_per_request' => 0.0,
            ];
        }

        $requests30d = 0;
        $requests7d = 0;
        $spend30d = 0.0;
        $spend7d = 0.0;
        $activeDays = [];
        $windowStart = CarbonImmutable::now()->subDays(6)->startOfDay();

        foreach ($activity as $item) {
            if (! is_array($item)) {
                continue;
            }

            $cost = $this->toFloat($item['cost'] ?? 0);
            $requestCount = (int) ($item['request_count'] ?? $item['requests'] ?? 1);
            if ($requestCount < 1) {
                $requestCount = 1;
            }

            $requests30d += $requestCount;
            $spend30d += $cost;

            $entryDate = $this->extractActivityDate($item);
            if ($entryDate !== null) {
                $activeDays[$entryDate->toDateString()] = true;
                if ($entryDate->greaterThanOrEqualTo($windowStart)) {
                    $requests7d += $requestCount;
                    $spend7d += $cost;
                }
            }
        }

        $activeDaysCount = count($activeDays);

        return [
            'requests_30d' => $requests30d,
            'requests_7d' => $requests7d,
            'spend_30d' => round($spend30d, 6),
            'spend_7d' => round($spend7d, 6),
            'active_days' => $activeDaysCount,
            'avg_daily_spend' => $activeDaysCount > 0 ? round($spend30d / $activeDaysCount, 6) : 0.0,
            'avg_cost_per_request' => $requests30d > 0 ? round($spend30d / $requests30d, 6) : 0.0,
        ];
    }

    /**
     * @param  array<string, mixed>  $activityItem
     */
    protected function extractActivityDate(array $activityItem): ?CarbonImmutable
    {
        $dateValue = $activityItem['created_at']
            ?? $activityItem['date']
            ?? $activityItem['timestamp']
            ?? null;

        if (! is_string($dateValue) || trim($dateValue) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($dateValue);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function toFloat(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        return 0.0;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function filterActivityByDate(mixed $activity, string $date): array
    {
        if (! is_array($activity)) {
            return [];
        }

        return collect($activity)
            ->filter(function (mixed $item) use ($date): bool {
                if (! is_array($item)) {
                    return false;
                }

                $entryDate = $this->extractActivityDate($item);

                return $entryDate?->toDateString() === $date;
            })
            ->values()
            ->all();
    }

    protected function truncateResponseBody(string $body): string
    {
        $trimmed = trim($body);
        if ($trimmed === '') {
            return '';
        }

        if (strlen($trimmed) <= 500) {
            return $trimmed;
        }

        return substr($trimmed, 0, 500).'... [truncated]';
    }
}
