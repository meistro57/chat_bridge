<?php

namespace App\Services\AI\Drivers;

class DeepSeekDriver extends OpenAIDriver
{
    public function __construct(
        string $apiKey,
        string $model = 'deepseek-chat',
        string $baseUrl = 'https://api.deepseek.com/v1'
    ) {
        parent::__construct($apiKey, $model, $baseUrl);
    }
}
