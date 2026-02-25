<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Discord Streaming Master Switch
    |--------------------------------------------------------------------------
    |
    | Enable or disable Discord streaming globally. When disabled, no
    | conversations will stream to Discord regardless of per-conversation
    | or per-user settings.
    |
    */

    'enabled' => env('DISCORD_STREAMING_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | System-Wide Default Webhook URL
    |--------------------------------------------------------------------------
    |
    | Fallback webhook URL used when neither the conversation nor the user
    | has a webhook configured. Leave empty to require per-user setup.
    |
    */

    'webhook_url' => env('DISCORD_WEBHOOK_URL'),

    /*
    |--------------------------------------------------------------------------
    | Thread Auto-Creation
    |--------------------------------------------------------------------------
    |
    | Automatically create a new Discord thread for each conversation.
    | When disabled, messages post directly to the webhook's channel.
    |
    */

    'thread_auto_create' => env('DISCORD_THREAD_AUTO_CREATE', true),

    /*
    |--------------------------------------------------------------------------
    | Embed Colors
    |--------------------------------------------------------------------------
    |
    | Colors used for Discord embeds. Values are decimal integers.
    | Agent A/B get distinct colors so readers can follow the conversation.
    |
    */

    'embed_colors' => [
        'agent_a' => (int) env('DISCORD_EMBED_COLOR_A', 0x3B82F6),  // Blue
        'agent_b' => (int) env('DISCORD_EMBED_COLOR_B', 0x8B5CF6),  // Purple
        'system' => 0x10B981,  // Emerald
        'error' => 0xEF4444,  // Red
        'topic' => 0x06B6D4,  // Cyan
    ],

    /*
    |--------------------------------------------------------------------------
    | Message Constraints
    |--------------------------------------------------------------------------
    |
    | Discord enforces a 2000 character limit on message content and a
    | 4096 character limit on embed descriptions. We stay under these
    | with a safety buffer.
    |
    */

    'max_embed_description' => (int) env('DISCORD_MAX_EMBED_DESCRIPTION', 3900),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Safety limits to avoid hitting Discord's rate limits.
    | Discord allows ~30 webhook executions per 60 seconds per channel.
    |
    */

    'rate_limit' => [
        'max_messages_per_minute' => 25,
        'retry_after_ms' => 1000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Circuit Breaker
    |--------------------------------------------------------------------------
    |
    | After this many consecutive failures, Discord streaming is disabled
    | for the remainder of the conversation.
    |
    */

    'circuit_breaker_threshold' => (int) env('DISCORD_CIRCUIT_BREAKER_THRESHOLD', 5),

    /*
    |--------------------------------------------------------------------------
    | Provider Icons
    |--------------------------------------------------------------------------
    |
    | Icon URLs used in embed author fields to identify providers visually.
    |
    */

    'provider_icons' => [
        'openai' => 'https://cdn.simpleicons.org/openai/white',
        'anthropic' => 'https://cdn.simpleicons.org/anthropic/white',
        'gemini' => 'https://cdn.simpleicons.org/google/white',
        'deepseek' => 'https://cdn.simpleicons.org/deepseek/white',
        'openrouter' => 'https://openrouter.ai/favicon.ico',
        'ollama' => 'https://ollama.ai/public/ollama.png',
        'lmstudio' => 'https://cdn.simpleicons.org/lmstudio/white',
    ],
];
