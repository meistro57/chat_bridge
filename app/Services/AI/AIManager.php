<?php

namespace App\Services\AI;

use App\Services\AI\Drivers\AnthropicDriver;
use App\Services\AI\Drivers\DeepSeekDriver;
use App\Services\AI\Drivers\GeminiDriver;
use App\Services\AI\Drivers\LMStudioDriver;
use App\Services\AI\Drivers\OllamaDriver;
use App\Services\AI\Drivers\OpenAIDriver;
use App\Services\AI\Drivers\OpenRouterDriver;
use Illuminate\Support\Manager;

class AIManager extends Manager
{
    public function getDefaultDriver()
    {
        return $this->config->get('ai.default', 'openai');
    }

    public function createOpenAIDriver()
    {
        return new OpenAIDriver(
            apiKey: config('services.openai.key'),
            model: config('services.openai.model', 'gpt-4o-mini')
        );
    }

    public function createAnthropicDriver()
    {
        return new AnthropicDriver(
            apiKey: config('services.anthropic.key'),
            model: config('services.anthropic.model', 'claude-3-5-sonnet-20241022')
        );
    }

    public function createDeepSeekDriver()
    {
        return new DeepSeekDriver(
            apiKey: config('services.deepseek.key'),
            model: config('services.deepseek.model', 'deepseek-chat')
        );
    }

    public function createOpenRouterDriver()
    {
        return new OpenRouterDriver(
            apiKey: config('services.openrouter.key'),
            model: config('services.openrouter.model', 'openai/gpt-4o-mini'),
            appName: config('services.openrouter.app_name'),
            referer: config('services.openrouter.referer')
        );
    }

    public function createGeminiDriver()
    {
        return new GeminiDriver(
            apiKey: config('services.gemini.key'),
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
