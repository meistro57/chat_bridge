<?php

namespace App\Services\AI\Drivers;

class LMStudioDriver extends OpenAIDriver
{
    public function __construct(
        string $model = 'local-model',
        string $baseUrl = 'http://localhost:1234/v1',
        string $apiKey = 'not-needed'
    ) {
        parent::__construct($apiKey, $model, $baseUrl);
    }
}
