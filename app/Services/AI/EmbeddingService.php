<?php

namespace App\Services\AI;

use App\Models\ApiKey;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmbeddingService
{
    protected string $openAiBaseUrl = 'https://api.openai.com/v1';

    protected string $openRouterBaseUrl = 'https://openrouter.ai/api/v1';

    /**
     * Generate embedding using text-embedding-3-small
     */
    public function getEmbedding(string $text): array
    {
        $openAiKey = $this->resolveApiKey('openai');
        if (empty($openAiKey)) {
            // Return dummy vector for demo/development if key is missing
            return array_fill(0, 1536, 0.0);
        }

        $response = Http::withToken($openAiKey)
            ->post("{$this->openAiBaseUrl}/embeddings", $this->embeddingPayload('text-embedding-3-small', $text));

        if ($response->successful()) {
            $embedding = $this->extractEmbedding($response);
            if ($embedding !== null) {
                return $embedding;
            }

            Log::warning('OpenAI embedding response missing valid vector', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \Exception('OpenAI Embedding Error: response did not include a valid embedding vector.');
        }

        if ($this->shouldFallbackToOpenRouter($response->status(), $response->body())) {
            $openRouterKey = $this->resolveApiKey('openrouter');
            if (! empty($openRouterKey)) {
                $fallbackResponse = Http::withHeaders($this->openRouterHeaders($openRouterKey))
                    ->post(
                        "{$this->openRouterBaseUrl}/embeddings",
                        $this->embeddingPayload($this->openRouterEmbeddingModel(), $text),
                    );

                if ($fallbackResponse->successful()) {
                    $embedding = $this->extractEmbedding($fallbackResponse);
                    if ($embedding !== null) {
                        return $embedding;
                    }

                    Log::warning('OpenRouter embedding response missing valid vector', [
                        'status' => $fallbackResponse->status(),
                        'body' => $fallbackResponse->body(),
                    ]);

                    throw new \Exception('OpenRouter Embedding Error: response did not include a valid embedding vector.');
                }

                throw new \Exception('OpenRouter Embedding Error: '.$fallbackResponse->body());
            }
        }

        throw new \Exception('OpenAI Embedding Error: '.$response->body());
    }

    private function resolveApiKey(string $provider): ?string
    {
        try {
            if (auth()->check()) {
                $userEntry = ApiKey::query()
                    ->where('provider', $provider)
                    ->where('user_id', auth()->id())
                    ->where('is_active', true)
                    ->latest()
                    ->first();

                if ($userEntry && ! empty($userEntry->key)) {
                    return $userEntry->key;
                }
            }

            $fallbackEntry = ApiKey::query()
                ->where('provider', $provider)
                ->where('is_active', true)
                ->latest()
                ->first();

            if ($fallbackEntry && ! empty($fallbackEntry->key)) {
                return $fallbackEntry->key;
            }
        } catch (\Throwable) {
            // Intentionally swallow DB lookup failures and continue to config fallback.
        }

        $configKey = config("services.{$provider}.key");

        return is_string($configKey) && $configKey !== '' && $configKey !== 'sk-sample-key'
            ? $configKey
            : null;
    }

    private function embeddingPayload(string $model, string $text): array
    {
        return [
            'input' => $text,
            'model' => $model,
        ];
    }

    private function shouldFallbackToOpenRouter(int $status, string $body): bool
    {
        if ($status !== 429) {
            return false;
        }

        $normalizedBody = strtolower($body);

        return str_contains($normalizedBody, 'insufficient_quota')
            || str_contains($normalizedBody, 'quota')
            || str_contains($normalizedBody, 'credit')
            || str_contains($normalizedBody, 'billing_hard_limit_reached');
    }

    private function openRouterHeaders(string $apiKey): array
    {
        return array_filter([
            'Authorization' => "Bearer {$apiKey}",
            'HTTP-Referer' => config('services.openrouter.referer'),
            'X-Title' => config('services.openrouter.app_name'),
            'Content-Type' => 'application/json',
        ]);
    }

    private function openRouterEmbeddingModel(): string
    {
        return (string) config('services.openrouter.embedding_model', 'openai/text-embedding-3-small');
    }

    private function extractEmbedding(\Illuminate\Http\Client\Response $response): ?array
    {
        $embedding = $response->json('data.0.embedding');

        if (! is_array($embedding) || $embedding === []) {
            return null;
        }

        foreach ($embedding as $value) {
            if (! is_numeric($value)) {
                return null;
            }
        }

        return array_map(static fn ($value): float => (float) $value, $embedding);
    }
}
