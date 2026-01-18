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
use Illuminate\Support\Manager;
use Illuminate\Support\Facades\Log;

class AIManager extends Manager
{
    public function getDefaultDriver()
    {
        return $this->config->get('ai.default', 'openai');
    }

    /**
     * Retrieve API Key from DB or Config
     */
    private function getKey(string $provider): ?string
    {
        try {
            // 1. Try Database
            $dbEntry = ApiKey::where('provider', $provider)
                ->where('is_active', true)
                ->latest()
                ->first();

            if ($dbEntry && !empty($dbEntry->key)) {
                return $dbEntry->key;
            }
        } catch (\Exception $e) {
            // Fallback if DB fails/missing
            Log::warning("Failed to fetch API key from DB for {$provider}: " . $e->getMessage());
        }

        // 2. Fallback to Config
        return config("services.{$provider}.key");
    }

    public function createOpenAIDriver()
    {
        $key = $this->getKey('openai');
        
        if (empty($key)) {
            return new MockDriver();
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
            return new MockDriver();
        }

        return new AnthropicDriver(
            apiKey: $key,
            model: config('services.anthropic.model', 'claude-3-5-sonnet-20241022')
        );
    }

    public function createDeepSeekDriver()
    {
        $key = $this->getKey('deepseek');
        
        if (empty($key)) {
            return new MockDriver();
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
            return new MockDriver();
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
            return new MockDriver();
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
}
