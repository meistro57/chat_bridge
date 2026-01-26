<?php

namespace App\Services\AI;

use App\Models\ApiKey;
use App\Services\AI\Contracts\AIDriverInterface;
use App\Services\AI\Drivers\AnthropicDriver;
use App\Services\AI\Drivers\DeepSeekDriver;
use App\Services\AI\Drivers\GeminiDriver;
use App\Services\AI\Drivers\LMStudioDriver;
use App\Services\AI\Drivers\MockDriver;
use App\Services\AI\Drivers\OllamaDriver;
use App\Services\AI\Drivers\OpenAIDriver;
use App\Services\AI\Drivers\OpenRouterDriver;
use Illuminate\Support\Manager;

class AIManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return $this->config->get('ai.default', 'openai');
    }

    /**
     * Retrieve API Key from DB or Config
     */
    private function decryptKey($encryptedKey): ?string
    {
        \Log::info('Decrypting API Key', [
            'encrypted_key' => substr($encryptedKey, 0, 20).'...',
        ]);

        try {
            $decrypted = decrypt($encryptedKey);

            return $decrypted;
        } catch (\Exception $e) {
            \Log::error('API Key Decryption Failed', [
                'error' => $e->getMessage(),
                'encrypted_length' => strlen($encryptedKey),
            ]);

            return null;
        }
    }

    private function getKey(string $provider): ?string
    {
        try {
            // 1. Try to get current user's key from Database FIRST
            if (auth()->check()) {
                $dbEntry = ApiKey::where('provider', $provider)
                    ->where('user_id', auth()->id())
                    ->where('is_active', true)
                    ->latest()
                    ->first();

                if ($dbEntry && ! empty($dbEntry->key)) {
                    \Log::info("Found API key for {$provider} in database", [
                        'user_id' => auth()->id(),
                    ]);

                    // The ApiKey model has 'encrypted' cast, so $dbEntry->key is already decrypted
                    return $dbEntry->key;
                }
            }

            // 2. Fallback to any active key from database
            $dbEntry = ApiKey::where('provider', $provider)
                ->where('is_active', true)
                ->latest()
                ->first();

            if ($dbEntry && ! empty($dbEntry->key)) {
                \Log::info("Found fallback API key for {$provider} in database", [
                    'user_id' => $dbEntry->user_id,
                ]);

                // The ApiKey model has 'encrypted' cast, so $dbEntry->key is already decrypted
                return $dbEntry->key;
            }
        } catch (\Exception $e) {
            \Log::warning("Failed to fetch API key from DB for {$provider}: ".$e->getMessage(), [
                'exception_trace' => $e->getTraceAsString(),
            ]);
        }

        // 3. Final fallback to Config (.env) - for system-wide/admin keys when no user keys exist
        $configKey = config("services.{$provider}.key");
        if (! empty($configKey) && $configKey !== 'sk-sample-key') {
            \Log::info("Using config key for {$provider}");

            return $configKey;
        }

        \Log::warning("No API key found for {$provider}");

        return null;
    }

    public function createOpenAIDriver(?string $model = null): AIDriverInterface
    {
        $key = $this->getKey('openai');

        if (empty($key)) {
            return new MockDriver;
        }

        return new OpenAIDriver(
            apiKey: $key,
            model: $model ?: config('services.openai.model', 'gpt-4o-mini')
        );
    }

    public function createAnthropicDriver(?string $model = null): AIDriverInterface
    {
        $key = $this->getKey('anthropic');

        if (empty($key)) {
            return new MockDriver;
        }

        return new AnthropicDriver(
            apiKey: $key,
            model: $model ?: config('services.anthropic.model', 'claude-sonnet-4-5-20250929')
        );
    }

    public function createDeepSeekDriver(?string $model = null): AIDriverInterface
    {
        $key = $this->getKey('deepseek');

        if (empty($key)) {
            return new MockDriver;
        }

        return new DeepSeekDriver(
            apiKey: $key,
            model: $model ?: config('services.deepseek.model', 'deepseek-chat')
        );
    }

    public function createOpenRouterDriver(?string $model = null): AIDriverInterface
    {
        $key = $this->getKey('openrouter');

        if (empty($key)) {
            return new MockDriver;
        }

        return new OpenRouterDriver(
            apiKey: $key,
            model: $model ?: config('services.openrouter.model', 'openai/gpt-4o-mini'),
            appName: config('services.openrouter.app_name'),
            referer: config('services.openrouter.referer')
        );
    }

    public function createGeminiDriver(?string $model = null): AIDriverInterface
    {
        $key = $this->getKey('gemini');

        if (empty($key)) {
            return new MockDriver;
        }

        return new GeminiDriver(
            apiKey: $key,
            model: $model ?: config('services.gemini.model', 'gemini-1.5-flash')
        );
    }

    public function createOllamaDriver(?string $model = null): AIDriverInterface
    {
        return new OllamaDriver(
            model: $model ?: config('services.ollama.model', 'llama3.1'),
            baseUrl: config('services.ollama.host', 'http://localhost:11434')
        );
    }

    public function createLMStudioDriver(?string $model = null): AIDriverInterface
    {
        return new LMStudioDriver(
            model: $model ?: config('services.lmstudio.model', 'local-model'),
            baseUrl: config('services.lmstudio.base_url', 'http://localhost:1234/v1')
        );
    }

    public function createMockDriver(): AIDriverInterface
    {
        return new MockDriver;
    }

    public function driverForProvider(?string $provider, ?string $model = null): AIDriverInterface
    {
        if ($provider === null || $provider === '') {
            return $this->driver();
        }

        return match ($provider) {
            'openai' => $this->createOpenAIDriver($model),
            'anthropic' => $this->createAnthropicDriver($model),
            'deepseek' => $this->createDeepSeekDriver($model),
            'openrouter' => $this->createOpenRouterDriver($model),
            'gemini' => $this->createGeminiDriver($model),
            'ollama' => $this->createOllamaDriver($model),
            'lmstudio' => $this->createLMStudioDriver($model),
            'mock' => $this->createMockDriver(),
            default => $this->driver($provider),
        };
    }
}
