<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\ModelPrice;
use App\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProviderController extends Controller
{
    public function __construct(private readonly AnalyticsService $analyticsService) {}

    public function modelsForProvider(string $provider): array
    {
        return $this->fetchModelsForProvider($provider);
    }

    public function getModels(Request $request): JsonResponse
    {
        $provider = $request->input('provider');

        if (empty($provider)) {
            return response()->json(['error' => 'Provider is required'], 400);
        }

        try {
            $models = $this->modelsForProvider($provider);
            try {
                $this->persistModelPricing($provider, $models);
            } catch (\Throwable $exception) {
                Log::warning('Failed to persist provider model pricing', [
                    'provider' => $provider,
                    'error' => $exception->getMessage(),
                ]);
            }

            return response()->json([
                'provider' => $provider,
                'models' => $models,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch models: '.$e->getMessage(),
            ], 500);
        }
    }

    private function fetchModelsForProvider(string $provider): array
    {
        return match ($provider) {
            'anthropic' => $this->fetchAnthropicModels(),
            'openai' => $this->fetchOpenAIModels(),
            'openrouter' => $this->fetchOpenRouterModels(),
            'gemini' => $this->fetchGeminiModels(),
            'deepseek' => $this->fetchDeepSeekModels(),
            'ollama' => $this->fetchOllamaModels(),
            'lmstudio' => $this->fetchLMStudioModels(),
            default => throw new \Exception("Unsupported provider: {$provider}"),
        };
    }

    private function fetchAnthropicModels(): array
    {
        $apiKey = $this->getApiKey('anthropic');
        if (! $apiKey) {
            return $this->getDefaultAnthropicModels();
        }

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
        ])->get('https://api.anthropic.com/v1/models');

        if ($response->successful()) {
            return collect($response->json('data'))->map(fn ($model) => [
                'id' => $model['id'],
                'name' => $model['display_name'],
            ])->toArray();
        }

        return $this->getDefaultAnthropicModels();
    }

    private function fetchOpenAIModels(): array
    {
        $apiKey = $this->getApiKey('openai');
        if (! $apiKey) {
            return $this->getDefaultOpenAIModels();
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
        ])->get('https://api.openai.com/v1/models');

        if ($response->successful()) {
            return collect($response->json('data'))
                ->filter(fn ($model) => str_starts_with($model['id'], 'gpt-'))
                ->sortByDesc('created')
                ->map(fn ($model) => [
                    'id' => $model['id'],
                    'name' => $model['id'],
                ])
                ->values()
                ->toArray();
        }

        return $this->getDefaultOpenAIModels();
    }

    private function fetchOpenRouterModels(): array
    {
        try {
            $response = Http::timeout(5)->get('https://openrouter.ai/api/v1/models');

            if ($response->successful()) {
                return collect($response->json('data'))
                    ->map(function ($model) {
                        $promptPrice = $model['pricing']['prompt'] ?? 0;
                        $completionPrice = $model['pricing']['completion'] ?? 0;

                        // Convert from price per token to price per 1M tokens and format
                        $promptPerMillion = $promptPrice * 1000000;
                        $completionPerMillion = $completionPrice * 1000000;

                        $cost = null;
                        if ($promptPerMillion == 0 && $completionPerMillion == 0) {
                            $cost = 'FREE';
                        } else {
                            $cost = sprintf('$%.2f/$%.2f', $promptPerMillion, $completionPerMillion);
                        }

                        return [
                            'id' => $model['id'],
                            'name' => $model['name'] ?? $model['id'],
                            'context' => $model['context_length'] ?? null,
                            'cost' => $cost,
                            'prompt_per_million' => $promptPerMillion,
                            'completion_per_million' => $completionPerMillion,
                        ];
                    })
                    ->sortBy('name')
                    ->values()
                    ->toArray();
            }
        } catch (\Exception $e) {
            // Fall back to curated list if API fails
        }

        // Fallback curated list
        return [
            ['id' => 'openai/gpt-4o', 'name' => 'GPT-4o', 'context' => 128000, 'cost' => '$2.50/$10.00'],
            ['id' => 'openai/gpt-4o-mini', 'name' => 'GPT-4o Mini', 'context' => 128000, 'cost' => '$0.15/$0.60'],
            ['id' => 'openai/gpt-4-turbo', 'name' => 'GPT-4 Turbo', 'context' => 128000, 'cost' => '$10/$30'],
            ['id' => 'anthropic/claude-sonnet-4-5-20250929', 'name' => 'Claude Sonnet 4.5', 'context' => 200000, 'cost' => '$3/$15'],
            ['id' => 'anthropic/claude-opus-4-5-20251101', 'name' => 'Claude Opus 4.5', 'context' => 200000, 'cost' => '$15/$75'],
            ['id' => 'anthropic/claude-haiku-4-5-20251001', 'name' => 'Claude Haiku 4.5', 'context' => 200000, 'cost' => '$0.25/$1.25'],
            ['id' => 'google/gemini-2.0-flash-exp', 'name' => 'Gemini 2.0 Flash', 'context' => 1000000, 'cost' => 'FREE'],
            ['id' => 'google/gemini-1.5-pro', 'name' => 'Gemini 1.5 Pro', 'context' => 2000000, 'cost' => '$1.25/$5.00'],
            ['id' => 'meta-llama/llama-3.3-70b-instruct', 'name' => 'Llama 3.3 70B', 'context' => 128000, 'cost' => '$0.35/$0.40'],
            ['id' => 'deepseek/deepseek-chat', 'name' => 'DeepSeek Chat', 'context' => 64000, 'cost' => '$0.14/$0.28'],
            ['id' => 'deepseek/deepseek-r1', 'name' => 'DeepSeek R1', 'context' => 64000, 'cost' => '$0.55/$2.19'],
            ['id' => 'qwen/qwen-2.5-72b-instruct', 'name' => 'Qwen 2.5 72B', 'context' => 128000, 'cost' => '$0.35/$0.40'],
            ['id' => 'mistralai/mistral-large', 'name' => 'Mistral Large', 'context' => 128000, 'cost' => '$2/$6'],
        ];
    }

    private function fetchGeminiModels(): array
    {
        $apiKey = $this->getApiKey('gemini');

        if ($apiKey) {
            try {
                $response = Http::timeout(5)->get(
                    "https://generativelanguage.googleapis.com/v1beta/models?key={$apiKey}"
                );

                if ($response->successful()) {
                    $pricingMap = $this->getGeminiPricingMap();

                    $models = collect($response->json('models', []))
                        ->filter(fn ($model) => in_array('generateContent', $model['supportedGenerationMethods'] ?? [], true))
                        ->map(function ($model) use ($pricingMap) {
                            $id = str_replace('models/', '', $model['name'] ?? '');
                            $displayName = $model['displayName'] ?? $id;

                            return [
                                'id' => $id,
                                'name' => $displayName,
                                'cost' => $pricingMap[$id] ?? null,
                            ];
                        })
                        ->filter(fn ($model) => str_starts_with($model['id'], 'gemini-'))
                        ->sortBy('name')
                        ->values()
                        ->toArray();

                    if (! empty($models)) {
                        return $models;
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Failed to fetch Gemini models from API', ['error' => $e->getMessage()]);
            }
        }

        return $this->getDefaultGeminiModels();
    }

    /**
     * @return array<string, string>
     */
    private function getGeminiPricingMap(): array
    {
        return [
            'gemini-2.5-pro-preview' => '$1.25/$10.00',
            'gemini-2.0-pro-exp' => 'FREE (exp)',
            'gemini-2.0-flash' => '$0.10/$0.40',
            'gemini-2.0-flash-lite' => '$0.075/$0.30',
            'gemini-2.0-flash-exp' => 'FREE (exp)',
            'gemini-1.5-pro' => '$1.25/$5.00',
            'gemini-1.5-pro-latest' => '$1.25/$5.00',
            'gemini-1.5-flash' => '$0.075/$0.30',
            'gemini-1.5-flash-latest' => '$0.075/$0.30',
            'gemini-1.5-flash-8b' => '$0.037/$0.15',
        ];
    }

    private function getDefaultGeminiModels(): array
    {
        return [
            ['id' => 'gemini-2.0-flash', 'name' => 'Gemini 2.0 Flash', 'cost' => '$0.10/$0.40'],
            ['id' => 'gemini-2.0-flash-lite', 'name' => 'Gemini 2.0 Flash Lite', 'cost' => '$0.075/$0.30'],
            ['id' => 'gemini-1.5-pro', 'name' => 'Gemini 1.5 Pro', 'cost' => '$1.25/$5.00'],
            ['id' => 'gemini-1.5-flash-8b', 'name' => 'Gemini 1.5 Flash 8B', 'cost' => '$0.037/$0.15'],
        ];
    }

    private function fetchDeepSeekModels(): array
    {
        return [
            ['id' => 'deepseek-chat', 'name' => 'DeepSeek Chat', 'cost' => '$0.14/$0.28'],
            ['id' => 'deepseek-reasoner', 'name' => 'DeepSeek Reasoner', 'cost' => '$0.55/$2.19'],
        ];
    }

    private function fetchOllamaModels(): array
    {
        $baseUrl = config('services.ollama.host', 'http://localhost:11434');

        try {
            $response = Http::timeout(3)->get("{$baseUrl}/api/tags");

            if ($response->successful()) {
                return collect($response->json('models'))->map(fn ($model) => [
                    'id' => $model['name'],
                    'name' => $model['name'],
                    'cost' => 'FREE (local)',
                ])->toArray();
            }
        } catch (\Exception $e) {
            // Ollama not available
        }

        return [
            ['id' => 'llama3.1', 'name' => 'Llama 3.1', 'cost' => 'FREE (local)'],
            ['id' => 'llama3.2', 'name' => 'Llama 3.2', 'cost' => 'FREE (local)'],
            ['id' => 'mistral', 'name' => 'Mistral', 'cost' => 'FREE (local)'],
        ];
    }

    private function fetchLMStudioModels(): array
    {
        $baseUrl = config('services.lmstudio.base_url', 'http://localhost:1234/v1');

        try {
            $response = Http::timeout(3)->get("{$baseUrl}/models");

            if ($response->successful()) {
                return collect($response->json('data'))->map(fn ($model) => [
                    'id' => $model['id'],
                    'name' => $model['id'],
                    'cost' => 'FREE (local)',
                ])->toArray();
            }
        } catch (\Exception $e) {
            // LMStudio not available
        }

        return [
            ['id' => 'local-model', 'name' => 'Local Model', 'cost' => 'FREE (local)'],
        ];
    }

    private function getApiKey(string $provider): ?string
    {
        // Try config first
        $configKey = config("services.{$provider}.key");
        if (! empty($configKey)) {
            return $configKey;
        }

        // Try user's database key
        if (auth()->check()) {
            $dbKey = ApiKey::where('provider', $provider)
                ->where('user_id', auth()->id())
                ->where('is_active', true)
                ->latest()
                ->first();

            if ($dbKey) {
                return $dbKey->key;
            }
        }

        return null;
    }

    private function getDefaultAnthropicModels(): array
    {
        return [
            ['id' => 'claude-sonnet-4-5-20250929', 'name' => 'Claude Sonnet 4.5', 'cost' => '$3/$15'],
            ['id' => 'claude-opus-4-5-20251101', 'name' => 'Claude Opus 4.5', 'cost' => '$15/$75'],
            ['id' => 'claude-haiku-4-5-20251001', 'name' => 'Claude Haiku 4.5', 'cost' => '$0.25/$1.25'],
            ['id' => 'claude-3-7-sonnet-20250219', 'name' => 'Claude Sonnet 3.7', 'cost' => '$3/$15'],
        ];
    }

    private function getDefaultOpenAIModels(): array
    {
        return [
            ['id' => 'gpt-4o', 'name' => 'GPT-4o', 'cost' => '$2.50/$10.00'],
            ['id' => 'gpt-4o-mini', 'name' => 'GPT-4o Mini', 'cost' => '$0.15/$0.60'],
            ['id' => 'gpt-4-turbo', 'name' => 'GPT-4 Turbo', 'cost' => '$10/$30'],
            ['id' => 'gpt-3.5-turbo', 'name' => 'GPT-3.5 Turbo', 'cost' => '$0.50/$1.50'],
        ];
    }

    /**
     * @param  array<int, array{id:string, cost?:string, prompt_per_million?:float, completion_per_million?:float}>  $models
     */
    private function persistModelPricing(string $provider, array $models): void
    {
        $timestamp = now();
        $rows = [];

        foreach ($models as $model) {
            $modelId = $model['id'] ?? null;
            if (! is_string($modelId) || $modelId === '') {
                continue;
            }

            $pricing = $this->resolvePricing($provider, $model);
            if ($pricing === null) {
                continue;
            }

            $rows[] = [
                'provider' => $provider,
                'model' => $modelId,
                'prompt_per_million' => $pricing['prompt_per_million'],
                'completion_per_million' => $pricing['completion_per_million'],
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        if ($rows === []) {
            return;
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            ModelPrice::query()->upsert(
                $chunk,
                ['provider', 'model'],
                ['prompt_per_million', 'completion_per_million', 'updated_at']
            );
        }

        $this->analyticsService->invalidatePricingCache();
    }

    /**
     * @param  array{id:string, cost?:string, prompt_per_million?:float, completion_per_million?:float}  $model
     * @return array{prompt_per_million:float, completion_per_million:float}|null
     */
    private function resolvePricing(string $provider, array $model): ?array
    {
        $prompt = $model['prompt_per_million'] ?? null;
        $completion = $model['completion_per_million'] ?? null;

        if (is_numeric($prompt) && is_numeric($completion)) {
            return [
                'prompt_per_million' => (float) $prompt,
                'completion_per_million' => (float) $completion,
            ];
        }

        $cost = $model['cost'] ?? null;
        if (is_string($cost)) {
            $normalizedCost = strtoupper(trim($cost));

            if (str_contains($normalizedCost, 'FREE')) {
                return [
                    'prompt_per_million' => 0.0,
                    'completion_per_million' => 0.0,
                ];
            }

            if (preg_match('/\\$([0-9]+(?:\\.[0-9]+)?)\\s*\\/\\s*\\$([0-9]+(?:\\.[0-9]+)?)/', $cost, $matches) === 1) {
                return [
                    'prompt_per_million' => (float) $matches[1],
                    'completion_per_million' => (float) $matches[2],
                ];
            }
        }

        return $this->resolvePricingFromConfig($provider, $model['id'] ?? null);
    }

    /**
     * @return array{prompt_per_million:float, completion_per_million:float}|null
     */
    private function resolvePricingFromConfig(string $provider, mixed $modelId): ?array
    {
        if (! is_string($modelId) || $modelId === '') {
            return null;
        }

        $modelPricing = config('ai.pricing.models', []);

        $candidates = [
            $modelId,
            "{$provider}/{$modelId}",
        ];

        if (str_contains($modelId, '/')) {
            $candidates[] = substr($modelId, strpos($modelId, '/') + 1);
        }

        foreach (array_values(array_unique($candidates)) as $candidate) {
            $entry = $modelPricing[$candidate] ?? null;
            if (! is_array($entry)) {
                continue;
            }

            $prompt = $entry['prompt_per_million'] ?? null;
            $completion = $entry['completion_per_million'] ?? null;

            if (is_numeric($prompt) && is_numeric($completion)) {
                return [
                    'prompt_per_million' => (float) $prompt,
                    'completion_per_million' => (float) $completion,
                ];
            }
        }

        $providerPerToken = config("ai.pricing.providers.{$provider}");
        if (is_numeric($providerPerToken)) {
            $providerPerMillion = (float) $providerPerToken * 1_000_000;

            return [
                'prompt_per_million' => $providerPerMillion,
                'completion_per_million' => $providerPerMillion,
            ];
        }

        return null;
    }
}
