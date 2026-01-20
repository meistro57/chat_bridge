<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ProviderController extends Controller
{
    public function getModels(Request $request)
    {
        $provider = $request->input('provider');

        if (empty($provider)) {
            return response()->json(['error' => 'Provider is required'], 400);
        }

        try {
            $models = $this->fetchModelsForProvider($provider);

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
        return [
            ['id' => 'gemini-2.0-flash-exp', 'name' => 'Gemini 2.0 Flash', 'cost' => 'FREE (exp)'],
            ['id' => 'gemini-1.5-flash', 'name' => 'Gemini 1.5 Flash', 'cost' => '$0.075/$0.30'],
            ['id' => 'gemini-1.5-pro', 'name' => 'Gemini 1.5 Pro', 'cost' => '$1.25/$5.00'],
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
}
