<?php

namespace App\Services\AI\Drivers;

class LMStudioDriver extends OpenAIDriver
{
    public function __construct(
        protected string $model = 'local-model',
        protected string $baseUrl = 'http://localhost:1234/v1',
        protected string $apiKey = 'not-needed'
    ) {}
}
