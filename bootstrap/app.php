<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(\App\Http\Middleware\PreventWritesInReadOnlyMode::class);
        $middleware->append(\App\Http\Middleware\RecordPerformanceMetrics::class);

        $middleware->web(append: [
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        if (class_exists(\Barryvdh\Debugbar\Middleware\InjectDebugbar::class)) {
            $middleware->web(append: [
                \Barryvdh\Debugbar\Middleware\InjectDebugbar::class,
            ]);
        }

        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
