<?php

namespace App\Services\AI;

use App\Models\ApiKey;
use App\Services\AI\Drivers\AnthropicDriver;
use App\Services\AI\Drivers\DeepSeekDriver;
use App\Services\AI\Drivers\GeminiDriver;
use App\Services\AI\Drivers\LMStudioDriver;
use App\Services\AI\Drivers\MockDriver;
use App\Services\AI\Drivers\OllamaDriver;
use App\Services\AI\Drivers\OpenAIDriver;
use App\Services\AI\Drivers\OpenRouterDriver;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Manager;

class AIManager extends Manager
{
    public function getDefaultDriver()
    {
        return $this->config->get('ai.default', 'openai');
    }

    /**
     * Retrieve API Key from DB or Config
     */
    private function decryptKey($encryptedKey)
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
        // 1. Try Config (.env) FIRST - for system-wide/admin keys
        $configKey = config("services.{$provider}.key");
        if (! empty($configKey)) {
            \Log::info("Using config key for {$provider}");
            return $configKey;
        }

        try {
            // 2. Try to get current user's key from Database
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
                    $decryptedKey = $this->decryptKey($dbEntry->key);
                    return $decryptedKey;
                }
            }

            // 3. Fallback to any active key (for backward compatibility)
            $dbEntry = ApiKey::where('provider', $provider)
                ->where('is_active', true)
                ->latest()
                ->first();

            if ($dbEntry && ! empty($dbEntry->key)) {
                \Log::info("Found fallback API key for {$provider}", [
                    'user_id' => $dbEntry->user_id,
                ]);
                $decryptedKey = $this->decryptKey($dbEntry->key);
                return $decryptedKey;
            }
        } catch (\Exception $e) {
            \Log::warning("Failed to fetch API key from DB for {$provider}: ".$e->getMessage(), [
                'exception_trace' => $e->getTraceAsString(),
            ]);
        }

        \Log::warning("No API key found for {$provider}");
        return null;
    }

    public function createOpenAIDriver()
    {
        $key = $this->getKey('openai');

        if (empty($key)) {
            return new MockDriver;
        }

        return new OpenAIDriver(
            apiKey: $key,
            model: config('services.openai.model', 'gpt-4o-mini')
        );
    }

    public function createAnthropicDriver()
    {
        $key = $this->getKey('anthropic');

        if (empty($key)) {
            return new MockDriver;
        }

        return new AnthropicDriver(
            apiKey: $key,
            model: config('services.anthropic.model', 'claude-sonnet-4-5-20250929')
        );
    }

    public function createDeepSeekDriver()
    {
        $key = $this->getKey('deepseek');

        if (empty($key)) {
            return new MockDriver;
        }

        return new DeepSeekDriver(
            apiKey: $key,
            model: config('services.deepseek.model', 'deepseek-chat')
        );
    }

    public function createOpenRouterDriver()
    {
        $key = $this->getKey('openrouter');

        if (empty($key)) {
            return new MockDriver;
        }

        return new OpenRouterDriver(
            apiKey: $key,
            model: config('services.openrouter.model', 'openai/gpt-4o-mini'),
            appName: config('services.openrouter.app_name'),
            referer: config('services.openrouter.referer')
        );
    }

    public function createGeminiDriver()
    {
        $key = $this->getKey('gemini');

        if (empty($key)) {
            return new MockDriver;
        }

        return new GeminiDriver(
            apiKey: $key,
            model: config('services.gemini.model', 'gemini-1.5-flash')
        );
    }

    public function createOllamaDriver()
    {
        return new OllamaDriver(
            model: config('services.ollama.model', 'llama3.1'),
            baseUrl: config('services.ollama.host', 'http://localhost:11434')
        );
    }

    public function createLMStudioDriver()
    {
        return new LMStudioDriver(
            model: config('services.lmstudio.model', 'local-model'),
            baseUrl: config('services.lmstudio.base_url', 'http://localhost:1234/v1')
        );
    }

    public function createMockDriver()
    {
        return new MockDriver;
    }
}
