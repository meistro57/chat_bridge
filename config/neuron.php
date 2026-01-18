<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Neuron AI Provider Configuration
    |--------------------------------------------------------------------------
    |
    | Supported providers: 'openai', 'anthropic', 'guzzle' (generic)
    |
    */
    'default' => env('NEURON_PROVIDER', 'openai'),

    'providers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL', 'gpt-3.5-turbo'),
        ],
        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'model' => env('ANTHROPIC_MODEL', 'claude-3-opus-20240229'),
        ],
    ],
];
