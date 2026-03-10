<?php

namespace Tests\Unit;

use App\Providers\AppServiceProvider;
use Tests\TestCase;

class AppServiceProviderTest extends TestCase
{
    public function test_it_creates_missing_sqlite_file_for_sqlite_default_connection(): void
    {
        $databasePath = sys_get_temp_dir().'/chat-bridge-sqlite-'.uniqid('', true).'/missing-database.sqlite';
        $databaseDirectory = dirname($databasePath);

        if (file_exists($databasePath)) {
            unlink($databasePath);
        }

        if (is_dir($databaseDirectory) && count(scandir($databaseDirectory)) === 2) {
            rmdir($databaseDirectory);
        }

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $databasePath,
        ]);

        $provider = new AppServiceProvider($this->app);
        $method = new \ReflectionMethod($provider, 'ensureSqliteDatabaseFileExists');
        $method->setAccessible(true);
        $method->invoke($provider);

        $this->assertFileExists($databasePath);

        unlink($databasePath);
        rmdir($databaseDirectory);
    }

    public function test_it_resolves_project_relative_sqlite_paths_without_duplicating_database_directory(): void
    {
        $databasePath = base_path('database/test-relative-sqlite.sqlite');
        $incorrectNestedPath = database_path('database/test-relative-sqlite.sqlite');

        if (file_exists($databasePath)) {
            unlink($databasePath);
        }
        if (file_exists($incorrectNestedPath)) {
            unlink($incorrectNestedPath);
        }

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => 'database/test-relative-sqlite.sqlite',
        ]);

        $provider = new AppServiceProvider($this->app);
        $method = new \ReflectionMethod($provider, 'ensureSqliteDatabaseFileExists');
        $method->setAccessible(true);
        $method->invoke($provider);

        $this->assertFileExists($databasePath);
        $this->assertFileDoesNotExist($incorrectNestedPath);

        unlink($databasePath);
    }
}
