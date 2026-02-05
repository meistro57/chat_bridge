<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default AI Driver
    |--------------------------------------------------------------------------
    |
    | This option controls the default AI driver that will be used when
    | no specific driver is requested. You can change this to any of the
    | supported drivers: openai, anthropic, gemini, deepseek, openrouter,
    | ollama, lmstudio, mock
    |
    */

    'default' => env('AI_DEFAULT_DRIVER', 'openai'),

    /*
    |--------------------------------------------------------------------------
    | AI Driver Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure the AI drivers and their settings. Each driver
    | can have its own specific configuration options.
    |
    */

    'drivers' => [
        'openai' => [
            'enabled' => true,
        ],
        'anthropic' => [
            'enabled' => true,
        ],
        'gemini' => [
            'enabled' => true,
        ],
        'deepseek' => [
            'enabled' => true,
        ],
        'openrouter' => [
            'enabled' => true,
        ],
        'ollama' => [
            'enabled' => true,
        ],
        'lmstudio' => [
            'enabled' => true,
        ],
        'mock' => [
            'enabled' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pricing Estimates
    |--------------------------------------------------------------------------
    |
    | These values are used for analytics estimates only and are intentionally
    | conservative defaults. Adjust as needed to reflect your billing rates.
    | Prices are in USD per 1M tokens unless otherwise specified.
    |
    */

    'pricing' => [
        'per_token_default' => 0.0,
        'providers' => [
            'ollama' => 0.0,
            'lmstudio' => 0.0,
            'mock' => 0.0,
        ],
        'models' => [
            'gpt-4o-mini' => ['prompt_per_million' => 0.15, 'completion_per_million' => 0.60],
            'openai/gpt-4o-mini' => ['prompt_per_million' => 0.15, 'completion_per_million' => 0.60],
            'gpt-4o' => ['prompt_per_million' => 2.50, 'completion_per_million' => 10.00],
            'openai/gpt-4o' => ['prompt_per_million' => 2.50, 'completion_per_million' => 10.00],
            'claude-sonnet-4-5-20250929' => ['prompt_per_million' => 3.00, 'completion_per_million' => 15.00],
            'anthropic/claude-sonnet-4-5-20250929' => ['prompt_per_million' => 3.00, 'completion_per_million' => 15.00],
            'deepseek-chat' => ['prompt_per_million' => 0.14, 'completion_per_million' => 0.28],
            'deepseek/deepseek-chat' => ['prompt_per_million' => 0.14, 'completion_per_million' => 0.28],
            'gemini-1.5-flash' => ['prompt_per_million' => 0.075, 'completion_per_million' => 0.30],
            'google/gemini-1.5-flash' => ['prompt_per_million' => 0.075, 'completion_per_million' => 0.30],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Streaming Chunk Size
    |--------------------------------------------------------------------------
    |
    | Limits the size of each broadcast chunk to avoid oversized payloads
    | when streaming responses. Measured in UTF-8 characters.
    |
    */

    'stream_chunk_size' => (int) env('AI_STREAM_CHUNK_SIZE', 1500),

    /*
    |--------------------------------------------------------------------------
    | Broadcast Payload Limit
    |--------------------------------------------------------------------------
    |
    | Maximum payload size (in bytes) allowed for broadcasting events.
    | Payloads exceeding this limit are skipped to avoid Pusher/Reverb errors.
    |
    */

    'broadcast_payload_limit' => (int) env('AI_BROADCAST_PAYLOAD_LIMIT', 20000),
];
