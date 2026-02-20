<?php

namespace App\Services\AI\Drivers;

use Illuminate\Support\Collection;

class DeepSeekDriver extends OpenAIDriver
{
    public function __construct(
        string $apiKey,
        string $model = 'deepseek-chat',
        string $baseUrl = 'https://api.deepseek.com/v1'
    ) {
        parent::__construct($apiKey, $model, $baseUrl);
    }

    public function chatWithTools(Collection $messages, Collection $tools, float $temperature = 0.7): array
    {
        throw new \Exception(get_class($this).' does not support tool calling yet');
    }

    public function supportsTools(): bool
    {
        return false;
    }
}
