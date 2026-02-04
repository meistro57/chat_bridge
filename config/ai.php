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
