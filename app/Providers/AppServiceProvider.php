<?php

namespace App\Providers;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register AI Manager
        $this->app->singleton('ai', function ($app) {
            return new \App\Services\AI\AIManager($app);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->ensureSqliteDatabaseFileExists();
        $this->registerReadOnlyDatabaseGuard();

        Vite::prefetch(concurrency: 3);
    }

    private function ensureSqliteDatabaseFileExists(): void
    {
        if (config('database.default') !== 'sqlite') {
            return;
        }

        $configuredPath = (string) config('database.connections.sqlite.database', '');

        if ($configuredPath === '' || $configuredPath === ':memory:' || str_starts_with($configuredPath, 'file:')) {
            return;
        }

        $databasePath = str_starts_with($configuredPath, DIRECTORY_SEPARATOR)
            ? $configuredPath
            : database_path($configuredPath);

        try {
            $directory = dirname($databasePath);

            if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
                return;
            }

            if (! file_exists($databasePath)) {
                touch($databasePath);
            }
        } catch (\Throwable) {
            return;
        }
    }

    private function registerReadOnlyDatabaseGuard(): void
    {
        $connectionNames = array_keys(config('database.connections', []));

        foreach ($connectionNames as $connectionName) {
            try {
                DB::connection($connectionName)->beforeExecuting(function (string $query, array $bindings, ConnectionInterface $connection): void {
                    if (! config('safety.read_only_mode', false)) {
                        return;
                    }

                    $normalized = strtolower(ltrim($query));
                    $readPrefixes = [
                        'select',
                        'with',
                        'show',
                        'pragma',
                        'explain',
                        'describe',
                        'begin',
                        'commit',
                        'rollback',
                        'savepoint',
                        'release',
                    ];

                    foreach ($readPrefixes as $prefix) {
                        if ($normalized === $prefix || str_starts_with($normalized, $prefix.' ')) {
                            return;
                        }
                    }

                    if (config('safety.allow_infrastructure_writes', true)) {
                        $infraTables = ['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'];
                        $isInfraWrite = false;

                        foreach ($infraTables as $table) {
                            if (str_contains($normalized, '"'.$table.'"') || str_contains($normalized, ' '.$table.' ')) {
                                $isInfraWrite = true;
                                break;
                            }
                        }

                        if ($isInfraWrite) {
                            return;
                        }
                    }

                    throw new RuntimeException(
                        sprintf('Read-only mode is enabled; blocked SQL on connection "%s".', $connection->getName())
                    );
                });
            } catch (\Throwable) {
                continue;
            }
        }
    }
}
