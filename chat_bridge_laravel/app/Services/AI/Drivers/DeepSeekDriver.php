<?php

namespace App\Services\AI\Drivers;

class DeepSeekDriver extends OpenAIDriver
{
    public function __construct(
        protected string $apiKey,
        protected string $model = 'deepseek-chat',
        protected string $baseUrl = 'https://api.deepseek.com/v1'
    ) {}
}
