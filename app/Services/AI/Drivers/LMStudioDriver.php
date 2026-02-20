<?php

namespace App\Services\AI\Drivers;

use Illuminate\Support\Collection;

class LMStudioDriver extends OpenAIDriver
{
    public function __construct(
        string $model = 'local-model',
        string $baseUrl = 'http://localhost:1234/v1',
        string $apiKey = 'not-needed'
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
