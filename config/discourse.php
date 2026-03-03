<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Discourse Streaming Master Switch
    |--------------------------------------------------------------------------
    */
    'enabled' => env('DISCOURSE_STREAMING_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Discourse Endpoint + Credentials
    |--------------------------------------------------------------------------
    */
    'base_url' => env('DISCOURSE_BASE_URL'),
    'api_key' => env('DISCOURSE_API_KEY'),
    'api_username' => env('DISCOURSE_API_USERNAME'),

    /*
    |--------------------------------------------------------------------------
    | Discourse Chat Plugin Delivery
    |--------------------------------------------------------------------------
    */
    'chat_enabled' => env('DISCOURSE_CHAT_ENABLED', false),
    'chat_webhook_url' => env('DISCOURSE_CHAT_WEBHOOK_URL'),

    /*
    |--------------------------------------------------------------------------
    | Topic Defaults
    |--------------------------------------------------------------------------
    */
    'default_category_id' => env('DISCOURSE_DEFAULT_CATEGORY_ID'),
    'default_tags' => array_values(array_filter(array_map(
        static fn (string $tag): string => trim($tag),
        explode(',', (string) env('DISCOURSE_DEFAULT_TAGS', 'chat-bridge'))
    ))),

    /*
    |--------------------------------------------------------------------------
    | HTTP + Circuit Breaker
    |--------------------------------------------------------------------------
    */
    'timeout' => (int) env('DISCOURSE_TIMEOUT_SECONDS', 15),
    'connect_timeout' => (int) env('DISCOURSE_CONNECT_TIMEOUT_SECONDS', 5),
    'circuit_breaker_threshold' => (int) env('DISCOURSE_CIRCUIT_BREAKER_THRESHOLD', 5),
];
