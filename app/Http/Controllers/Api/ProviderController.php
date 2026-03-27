<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\ModelPrice;
use App\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
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

    public function getConfiguredProviders(): JsonResponse
    {
        $userId = auth()->id();

        $result = collect($this->defaultProviders())
            ->keyBy('id');

        // Fetch all active keys for the current user, ordered oldest-first so
        // single-key providers keep their plain id.
        $userKeys = ApiKey::query()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->orderBy('created_at')
            ->get(['id', 'provider', 'label']);

        $byProvider = $userKeys->groupBy('provider');
        $scopedProviderEntries = collect();

        foreach ($byProvider as $provider => $keys) {
            if (! $result->has($provider)) {
                $result->put($provider, [
                    'id' => $provider,
                    'name' => $this->providerDisplayName($provider),
                    'supports_tools' => $this->providerSupportsTools($provider),
                ]);
            }

            if ($keys->count() === 1) {
                continue;
            }

            foreach ($keys as $key) {
                $displayName = $key->label
                    ? $key->label
                    : $this->providerDisplayName($provider);
                $scopedProviderEntries->push([
                    'id' => "{$provider}:{$key->id}",
                    'name' => $displayName,
                    'supports_tools' => $this->providerSupportsTools($provider),
                ]);
            }
        }

        return response()->json([
            'providers' => $result->values()->merge($scopedProviderEntries)->values(),
        ]);
    }

    /**
     * @return array<int, array{id:string, name:string, supports_tools:bool}>
     */
    private function defaultProviders(): array
    {
        return [
            ['id' => 'openai', 'name' => 'OpenAI', 'supports_tools' => true],
            ['id' => 'anthropic', 'name' => 'Anthropic', 'supports_tools' => true],
            ['id' => 'gemini', 'name' => 'Gemini', 'supports_tools' => true],
            ['id' => 'openrouter', 'name' => 'OpenRouter', 'supports_tools' => false],
            ['id' => 'deepseek', 'name' => 'DeepSeek', 'supports_tools' => false],
            ['id' => 'bedrock', 'name' => 'Bedrock', 'supports_tools' => false],
            ['id' => 'ollama', 'name' => 'Ollama', 'supports_tools' => true],
            ['id' => 'lmstudio', 'name' => 'LM Studio', 'supports_tools' => false],
            ['id' => 'mock', 'name' => 'Mock', 'supports_tools' => false],
        ];
    }

    private function providerSupportsTools(string $provider): bool
    {
        return match ($provider) {
            'openai', 'anthropic', 'gemini', 'ollama' => true,
            default => false,
        };
    }

    private function providerDisplayName(string $provider): string
    {
        return match ($provider) {
            'openai' => 'OpenAI',
            'anthropic' => 'Anthropic',
            'deepseek' => 'DeepSeek',
            'openrouter' => 'OpenRouter',
            'gemini' => 'Gemini',
            'bedrock' => 'Bedrock',
            'ollama' => 'Ollama',
            'lmstudio' => 'LM Studio',
            'mock' => 'Mock',
            default => ucfirst($provider),
        };
    }

    private function fetchModelsForProvider(string $provider): array
    {
        // Strip optional ":keyId" suffix
        if (str_contains($provider, ':')) {
            $provider = explode(':', $provider, 2)[0];
        }

        return match ($provider) {
            'anthropic' => $this->fetchAnthropicModels(),
            'openai' => $this->fetchOpenAIModels(),
            'openrouter' => $this->fetchOpenRouterModels(),
            'gemini' => $this->fetchGeminiModels(),
            'bedrock' => $this->fetchBedrockModels(),
            'deepseek' => $this->fetchDeepSeekModels(),
            'ollama' => $this->fetchOllamaModels(),
            'lmstudio' => $this->fetchLMStudioModels(),
            'mock' => $this->getDefaultMockModels(),
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
                'supports_tools' => true,
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
                ->filter(function ($model) {
                    $id = (string) ($model['id'] ?? '');

                    return str_starts_with($id, 'gpt-')
                        || str_starts_with($id, 'o1')
                        || str_starts_with($id, 'o3')
                        || str_starts_with($id, 'o4');
                })
                ->sortByDesc('created')
                ->map(fn ($model) => [
                    'id' => $model['id'],
                    'name' => $model['id'],
                    'supports_tools' => true,
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

                        $supportedParams = $model['supported_parameters'] ?? [];
                        $supportsTools = is_array($supportedParams) && in_array('tools', $supportedParams, true);

                        return [
                            'id' => $model['id'],
                            'name' => $model['name'] ?? $model['id'],
                            'context' => $model['context_length'] ?? null,
                            'cost' => $cost,
                            'prompt_per_million' => $promptPerMillion,
                            'completion_per_million' => $completionPerMillion,
                            'supports_tools' => $supportsTools,
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
            ['id' => 'openai/gpt-4o', 'name' => 'GPT-4o', 'context' => 128000, 'cost' => '$2.50/$10.00', 'supports_tools' => true],
            ['id' => 'openai/gpt-4o-mini', 'name' => 'GPT-4o Mini', 'context' => 128000, 'cost' => '$0.15/$0.60', 'supports_tools' => true],
            ['id' => 'openai/gpt-4-turbo', 'name' => 'GPT-4 Turbo', 'context' => 128000, 'cost' => '$10/$30', 'supports_tools' => true],
            ['id' => 'anthropic/claude-3-sonnet', 'name' => 'Claude 3 Sonnet', 'context' => 200000, 'cost' => '$3/$15', 'supports_tools' => true],
            ['id' => 'anthropic/claude-sonnet-4-5-20250929', 'name' => 'Claude Sonnet 4.5', 'context' => 200000, 'cost' => '$3/$15', 'supports_tools' => true],
            ['id' => 'anthropic/claude-opus-4-5-20251101', 'name' => 'Claude Opus 4.5', 'context' => 200000, 'cost' => '$15/$75', 'supports_tools' => true],
            ['id' => 'anthropic/claude-haiku-4-5-20251001', 'name' => 'Claude Haiku 4.5', 'context' => 200000, 'cost' => '$0.25/$1.25', 'supports_tools' => true],
            ['id' => 'google/gemini-2.0-flash-exp', 'name' => 'Gemini 2.0 Flash', 'context' => 1000000, 'cost' => 'FREE', 'supports_tools' => true],
            ['id' => 'google/gemini-1.5-pro', 'name' => 'Gemini 1.5 Pro', 'context' => 2000000, 'cost' => '$1.25/$5.00', 'supports_tools' => true],
            ['id' => 'meta-llama/llama-3.3-70b-instruct', 'name' => 'Llama 3.3 70B', 'context' => 128000, 'cost' => '$0.35/$0.40', 'supports_tools' => true],
            ['id' => 'deepseek/deepseek-chat', 'name' => 'DeepSeek Chat', 'context' => 64000, 'cost' => '$0.14/$0.28', 'supports_tools' => true],
            ['id' => 'deepseek/deepseek-r1', 'name' => 'DeepSeek R1', 'context' => 64000, 'cost' => '$0.55/$2.19', 'supports_tools' => false],
            ['id' => 'qwen/qwen-2.5-72b-instruct', 'name' => 'Qwen 2.5 72B', 'context' => 128000, 'cost' => '$0.35/$0.40', 'supports_tools' => true],
            ['id' => 'mistralai/mistral-large', 'name' => 'Mistral Large', 'context' => 128000, 'cost' => '$2/$6', 'supports_tools' => true],
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
                                'supports_tools' => true,
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
            'gemini-2.5-flash' => '$0.15/$0.60',
            'gemini-2.5-pro' => '$1.25/$10.00',
            'gemini-2.5-pro-preview' => '$1.25/$10.00',
            'gemini-2.0-pro-exp' => 'FREE (exp)',
            'gemini-2.0-flash' => '$0.10/$0.40',
            'gemini-2.0-flash-lite' => '$0.075/$0.30',
            'gemini-2.0-flash-exp' => 'FREE (exp)',
        ];
    }

    private function getDefaultGeminiModels(): array
    {
        return [
            ['id' => 'gemini-2.5-flash', 'name' => 'Gemini 2.5 Flash', 'cost' => '$0.15/$0.60', 'supports_tools' => true],
            ['id' => 'gemini-2.5-pro', 'name' => 'Gemini 2.5 Pro', 'cost' => '$1.25/$10.00', 'supports_tools' => true],
            ['id' => 'gemini-2.0-flash-lite', 'name' => 'Gemini 2.0 Flash Lite', 'cost' => '$0.075/$0.30', 'supports_tools' => true],
            ['id' => 'gemini-2.0-flash', 'name' => 'Gemini 2.0 Flash', 'cost' => '$0.10/$0.40', 'supports_tools' => true],
        ];
    }

    private function fetchDeepSeekModels(): array
    {
        $apiKey = $this->getApiKey('deepseek');

        if ($apiKey) {
            try {
                $response = Http::withHeaders(['Authorization' => "Bearer {$apiKey}"])
                    ->timeout(5)
                    ->get('https://api.deepseek.com/v1/models');

                if ($response->successful()) {
                    $models = collect($response->json('data', []))
                        ->filter(fn ($model) => isset($model['id']))
                        ->map(function ($model) {
                            $id = $model['id'];
                            $isReasoner = str_contains(strtolower($id), 'reasoner');

                            return [
                                'id' => $id,
                                'name' => $model['id'],
                                'supports_tools' => ! $isReasoner,
                            ];
                        })
                        ->sortBy('name')
                        ->values()
                        ->all();

                    if (! empty($models)) {
                        return $models;
                    }
                }
            } catch (\Exception $e) {
                Log::info('DeepSeek model discovery failed', ['error' => $e->getMessage()]);
            }
        }

        return [
            ['id' => 'deepseek-chat', 'name' => 'DeepSeek Chat', 'cost' => '$0.14/$0.28', 'supports_tools' => true],
            ['id' => 'deepseek-reasoner', 'name' => 'DeepSeek Reasoner', 'cost' => '$0.55/$2.19', 'supports_tools' => false],
        ];
    }

    private function fetchBedrockModels(): array
    {
        $accessKeyId = config('services.bedrock.access_key_id');
        $secretAccessKey = config('services.bedrock.secret_access_key');
        $sessionToken = config('services.bedrock.session_token');
        $region = config('services.bedrock.region', 'us-east-1');

        if (! empty($accessKeyId) && ! empty($secretAccessKey)) {
            try {
                $response = $this->sendBedrockGetRequest(
                    $accessKeyId,
                    $secretAccessKey,
                    $sessionToken,
                    $region,
                    'bedrock',
                    "bedrock.{$region}.amazonaws.com",
                    '/foundation-models'
                );

                if ($response->successful()) {
                    $models = collect($response->json('modelSummaries', []))
                        ->filter(function ($model) {
                            $inferenceTypes = $model['inferenceTypesSupported'] ?? $model['supportedInferenceTypes'] ?? [];
                            $outputModalities = $model['outputModalities'] ?? [];

                            return in_array('ON_DEMAND', $inferenceTypes, true)
                                && in_array('TEXT', $outputModalities, true);
                        })
                        ->map(function ($model) {
                            $id = $model['modelId'];
                            $name = $model['modelName'] ?? $id;
                            $provider = $model['providerName'] ?? '';

                            return [
                                'id' => $id,
                                'name' => "{$name} ({$provider})",
                                'supports_tools' => str_contains(strtolower($id), 'claude'),
                            ];
                        })
                        ->sortBy('name')
                        ->values()
                        ->all();

                    if (! empty($models)) {
                        return $models;
                    }
                }
            } catch (\Exception $e) {
                Log::info('Bedrock model discovery failed', ['error' => $e->getMessage()]);
            }
        }

        return [
            ['id' => 'anthropic.claude-3-5-sonnet-20241022-v2:0', 'name' => 'Claude 3.5 Sonnet (Bedrock)', 'cost' => '$3.00/$15.00', 'supports_tools' => true],
            ['id' => 'anthropic.claude-3-7-sonnet-20250219-v1:0', 'name' => 'Claude 3.7 Sonnet (Bedrock)', 'cost' => '$3.00/$15.00', 'supports_tools' => true],
            ['id' => 'anthropic.claude-sonnet-4-20250514-v1:0', 'name' => 'Claude Sonnet 4 (Bedrock)', 'cost' => '$3.00/$15.00', 'supports_tools' => true],
            ['id' => 'anthropic.claude-3-5-haiku-20241022-v1:0', 'name' => 'Claude 3.5 Haiku (Bedrock)', 'cost' => '$0.80/$4.00', 'supports_tools' => true],
        ];
    }

    private function sendBedrockGetRequest(
        string $accessKeyId,
        string $secretAccessKey,
        ?string $sessionToken,
        string $region,
        string $service,
        string $host,
        string $uri
    ): \Illuminate\Http\Client\Response {
        $algorithm = 'AWS4-HMAC-SHA256';
        $amzDate = gmdate('Ymd\THis\Z');
        $dateStamp = gmdate('Ymd');
        $emptyPayloadHash = hash('sha256', '');
        $credentialScope = "{$dateStamp}/{$region}/{$service}/aws4_request";

        $headers = ['host' => $host, 'x-amz-date' => $amzDate, 'x-amz-content-sha256' => $emptyPayloadHash];
        if (! empty($sessionToken)) {
            $headers['x-amz-security-token'] = $sessionToken;
        }
        ksort($headers);

        $canonicalHeaders = '';
        foreach ($headers as $name => $value) {
            $canonicalHeaders .= strtolower($name).':'.trim((string) $value)."\n";
        }
        $signedHeaders = implode(';', array_keys($headers));

        $canonicalRequest = implode("\n", ['GET', $uri, '', $canonicalHeaders, $signedHeaders, $emptyPayloadHash]);
        $stringToSign = implode("\n", [$algorithm, $amzDate, $credentialScope, hash('sha256', $canonicalRequest)]);

        $kDate = hash_hmac('sha256', $dateStamp, 'AWS4'.$secretAccessKey, true);
        $kRegion = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);
        $signingKey = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $signingKey);

        $authorization = "{$algorithm} Credential={$accessKeyId}/{$credentialScope}, SignedHeaders={$signedHeaders}, Signature={$signature}";

        $outboundHeaders = [
            'Authorization' => $authorization,
            'Host' => $host,
            'X-Amz-Date' => $amzDate,
            'X-Amz-Content-Sha256' => $emptyPayloadHash,
        ];
        if (! empty($sessionToken)) {
            $outboundHeaders['X-Amz-Security-Token'] = $sessionToken;
        }

        return Http::withHeaders($outboundHeaders)
            ->timeout(5)
            ->get("https://{$host}{$uri}");
    }

    private function fetchOllamaModels(): array
    {
        $configuredBaseUrl = rtrim((string) config('services.ollama.host', 'http://localhost:11434'), '/');
        $nativeBaseUrl = $this->normalizeOllamaNativeBaseUrl($configuredBaseUrl);

        try {
            $response = Http::timeout(3)->get("{$nativeBaseUrl}/api/tags");

            if ($response->successful()) {
                $models = collect($response->json('models', []))
                    ->map(function ($model) {
                        $name = is_array($model) ? ($model['name'] ?? null) : null;
                        if (! is_string($name) || trim($name) === '') {
                            return null;
                        }

                        return [
                            'id' => $name,
                            'name' => $name,
                            'cost' => 'FREE (local)',
                        ];
                    })
                    ->filter()
                    ->values()
                    ->all();

                if (! empty($models)) {
                    return $models;
                }
            }
        } catch (\Exception $e) {
            Log::info('Ollama /api/tags model discovery failed', ['error' => $e->getMessage()]);
        }

        $openAiCompatibleBaseUrls = collect([
            $configuredBaseUrl,
            "{$nativeBaseUrl}/v1",
        ])->unique()->values();

        foreach ($openAiCompatibleBaseUrls as $openAiCompatibleBaseUrl) {
            try {
                $response = Http::timeout(3)->get("{$openAiCompatibleBaseUrl}/models");

                if (! $response->successful()) {
                    continue;
                }

                $models = collect($response->json('data', []))
                    ->map(function ($model) {
                        $id = is_array($model) ? ($model['id'] ?? null) : null;
                        if (! is_string($id) || trim($id) === '') {
                            return null;
                        }

                        $name = Arr::get($model, 'name', $id);
                        if (! is_string($name) || trim($name) === '') {
                            $name = $id;
                        }

                        return [
                            'id' => $id,
                            'name' => $name,
                            'cost' => 'FREE (local)',
                        ];
                    })
                    ->filter()
                    ->unique('id')
                    ->values()
                    ->all();

                if (! empty($models)) {
                    return $models;
                }
            } catch (\Exception $e) {
                Log::info('Ollama OpenAI-compatible model discovery failed', [
                    'base_url' => $openAiCompatibleBaseUrl,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            ['id' => 'llama3.1', 'name' => 'Llama 3.1', 'cost' => 'FREE (local)'],
            ['id' => 'llama3.2', 'name' => 'Llama 3.2', 'cost' => 'FREE (local)'],
            ['id' => 'mistral', 'name' => 'Mistral', 'cost' => 'FREE (local)'],
        ];
    }

    private function normalizeOllamaNativeBaseUrl(string $configuredBaseUrl): string
    {
        $trimmedBaseUrl = rtrim($configuredBaseUrl, '/');
        foreach (['/v1', '/api'] as $suffix) {
            if (str_ends_with($trimmedBaseUrl, $suffix)) {
                return substr($trimmedBaseUrl, 0, -strlen($suffix));
            }
        }

        return $trimmedBaseUrl;
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

        // Try the authenticated user's key
        if (auth()->check()) {
            $userKey = ApiKey::where('provider', $provider)
                ->where('user_id', auth()->id())
                ->where('is_active', true)
                ->latest()
                ->value('key');

            if (! empty($userKey)) {
                return $userKey;
            }
        }

        // Fall back to any active key for this provider
        $globalKey = ApiKey::where('provider', $provider)
            ->where('is_active', true)
            ->latest()
            ->value('key');

        return ! empty($globalKey) ? $globalKey : null;
    }

    private function getDefaultAnthropicModels(): array
    {
        return [
            ['id' => 'claude-sonnet-4-5-20250929', 'name' => 'Claude Sonnet 4.5', 'cost' => '$3/$15', 'supports_tools' => true],
            ['id' => 'claude-opus-4-5-20251101', 'name' => 'Claude Opus 4.5', 'cost' => '$15/$75', 'supports_tools' => true],
            ['id' => 'claude-haiku-4-5-20251001', 'name' => 'Claude Haiku 4.5', 'cost' => '$0.25/$1.25', 'supports_tools' => true],
            ['id' => 'claude-3-7-sonnet-20250219', 'name' => 'Claude Sonnet 3.7', 'cost' => '$3/$15', 'supports_tools' => true],
        ];
    }

    private function getDefaultOpenAIModels(): array
    {
        return [
            ['id' => 'gpt-5', 'name' => 'GPT-5', 'cost' => '$1.25/$10.00', 'supports_tools' => true],
            ['id' => 'gpt-5-mini', 'name' => 'GPT-5 Mini', 'cost' => '$0.25/$2.00', 'supports_tools' => true],
            ['id' => 'gpt-5-nano', 'name' => 'GPT-5 Nano', 'cost' => '$0.05/$0.40', 'supports_tools' => true],
            ['id' => 'gpt-4.1', 'name' => 'GPT-4.1', 'cost' => '$2.00/$8.00', 'supports_tools' => true],
            ['id' => 'gpt-4.1-mini', 'name' => 'GPT-4.1 Mini', 'cost' => '$0.40/$1.60', 'supports_tools' => true],
            ['id' => 'gpt-4.1-nano', 'name' => 'GPT-4.1 Nano', 'cost' => '$0.10/$0.40', 'supports_tools' => true],
            ['id' => 'gpt-4o', 'name' => 'GPT-4o', 'cost' => '$2.50/$10.00', 'supports_tools' => true],
            ['id' => 'gpt-4o-mini', 'name' => 'GPT-4o Mini', 'cost' => '$0.15/$0.60', 'supports_tools' => true],
            ['id' => 'o1', 'name' => 'o1', 'cost' => '$15.00/$60.00', 'supports_tools' => true],
            ['id' => 'o3-mini', 'name' => 'o3-mini', 'cost' => '$1.10/$4.40', 'supports_tools' => true],
        ];
    }

    private function getDefaultMockModels(): array
    {
        return [
            ['id' => 'mock-default', 'name' => 'Mock Default', 'cost' => 'FREE', 'supports_tools' => false],
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
