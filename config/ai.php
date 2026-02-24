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
    | Tool Calling (MCP Integration)
    |--------------------------------------------------------------------------
    |
    | Enable AI personas to call MCP tools during conversations. When enabled,
    | AI models with tool support (OpenAI, Anthropic, Gemini) can search past
    | conversations, retrieve contextual memory, and access conversation history.
    |
    */

    'tools_enabled' => env('AI_TOOLS_ENABLED', true),
    'max_tool_iterations' => env('AI_MAX_TOOL_ITERATIONS', 5),

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
            'gpt-4o-2024-11-20' => ['prompt_per_million' => 2.50, 'completion_per_million' => 10.00],
            'openai/gpt-4o-2024-11-20' => ['prompt_per_million' => 2.50, 'completion_per_million' => 10.00],
            'claude-sonnet-4-5-20250929' => ['prompt_per_million' => 3.00, 'completion_per_million' => 15.00],
            'anthropic/claude-sonnet-4-5-20250929' => ['prompt_per_million' => 3.00, 'completion_per_million' => 15.00],
            'claude-haiku-4-5-20251001' => ['prompt_per_million' => 0.80, 'completion_per_million' => 4.00],
            'anthropic/claude-haiku-4-5-20251001' => ['prompt_per_million' => 0.80, 'completion_per_million' => 4.00],
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
    | Initial Stream Chunk
    |--------------------------------------------------------------------------
    |
    | Optional small chunk broadcast immediately when a turn starts so the UI
    | can render a live bubble before provider tokens arrive.
    |
    */

    'initial_stream_enabled' => env('AI_INITIAL_STREAM_ENABLED', true),
    'initial_stream_chunk' => env('AI_INITIAL_STREAM_CHUNK', ''),

    /*
    |--------------------------------------------------------------------------
    | Inter-Turn Delay
    |--------------------------------------------------------------------------
    |
    | Delay between conversation turns in milliseconds.
    |
    */

    'inter_turn_delay_ms' => (int) env('AI_INTER_TURN_DELAY_MS', 250),

    /*
    |--------------------------------------------------------------------------
    | Empty Turn Retry
    |--------------------------------------------------------------------------
    |
    | If a provider returns an empty turn, retry generation this many times
    | before failing the conversation.
    |
    */

    'empty_turn_retry_attempts' => (int) env('AI_EMPTY_TURN_RETRY_ATTEMPTS', 1),
    'empty_turn_retry_delay_ms' => (int) env('AI_EMPTY_TURN_RETRY_DELAY_MS', 350),

    /*
    |--------------------------------------------------------------------------
    | Turn Exception Retry
    |--------------------------------------------------------------------------
    |
    | Retry a turn when transient provider/network exceptions happen.
    |
    */

    'turn_exception_retry_attempts' => (int) env('AI_TURN_EXCEPTION_RETRY_ATTEMPTS', 2),
    'turn_exception_retry_delay_ms' => (int) env('AI_TURN_EXCEPTION_RETRY_DELAY_MS', 1000),

    /*
    |--------------------------------------------------------------------------
    | Provider HTTP Client Resilience
    |--------------------------------------------------------------------------
    |
    | Timeout and retry controls for outbound AI provider HTTP requests.
    | These protect long-running model calls from transient network stalls.
    |
    */

    'http_timeout_seconds' => (int) env('AI_HTTP_TIMEOUT_SECONDS', 90),
    'http_connect_timeout_seconds' => (int) env('AI_HTTP_CONNECT_TIMEOUT_SECONDS', 15),
    'http_retry_attempts' => (int) env('AI_HTTP_RETRY_ATTEMPTS', 2),
    'http_retry_delay_ms' => (int) env('AI_HTTP_RETRY_DELAY_MS', 500),

    /*
    |--------------------------------------------------------------------------
    | Empty Turn Fallback Message
    |--------------------------------------------------------------------------
    |
    | Safety net text used when a provider repeatedly returns empty content.
    |
    */

    'empty_turn_fallback_message' => env(
        'AI_EMPTY_TURN_FALLBACK_MESSAGE',
        'I need to regroup for a moment. Please continue with your strongest next point.'
    ),

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
