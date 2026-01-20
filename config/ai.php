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
];
