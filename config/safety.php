<?php

return [
    'read_only_mode' => (bool) env('APP_READ_ONLY_MODE', false),
    'allow_infrastructure_writes' => (bool) env('APP_READ_ONLY_ALLOW_INFRA_WRITES', true),

    /*
    |--------------------------------------------------------------------------
    | Allowed Mutating Routes in Read-Only Mode
    |--------------------------------------------------------------------------
    |
    | These routes use POST but are still read-only with respect to persisted
    | application data.
    |
    */
    'allowed_mutating_routes' => [
        'analytics.query.run-sql',
        'analytics.export',
        'api-keys.test',
        'personas.generate',
    ],

    'allowed_mutating_paths' => [
        'analytics/query/run-sql',
        'analytics/export',
        'api-keys/*/test',
        'personas/generate',
    ],
];
