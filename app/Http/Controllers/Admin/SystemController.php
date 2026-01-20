<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class SystemController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/System', [
            'systemInfo' => $this->getSystemInfo(),
        ]);
    }

    public function runDiagnostic(Request $request)
    {
        $action = $request->input('action');

        Log::info('System diagnostic action triggered', [
            'action' => $action,
            'user_id' => auth()->id(),
        ]);

        $output = '';
        $success = true;

        try {
            switch ($action) {
                case 'health_check':
                    $output = $this->runHealthCheck();
                    break;

                case 'fix_permissions':
                    $output = $this->fixPermissions();
                    break;

                case 'clear_cache':
                    $output = $this->clearAllCache();
                    break;

                case 'optimize':
                    $output = $this->optimizeApplication();
                    break;

                case 'validate_ai':
                    $output = $this->validateAIServices();
                    break;

                case 'check_database':
                    $output = $this->checkDatabase();
                    break;

                case 'run_tests':
                    $output = $this->runTests();
                    break;

                case 'fix_code_style':
                    $output = $this->fixCodeStyle();
                    break;

                default:
                    $success = false;
                    $output = "Unknown action: {$action}";
            }
        } catch (\Exception $e) {
            $success = false;
            $output = "Error: " . $e->getMessage();
            Log::error('System diagnostic failed', [
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'success' => $success,
            'output' => $output,
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    private function getSystemInfo()
    {
        return [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'environment' => app()->environment(),
            'debug_mode' => config('app.debug'),
            'cache_driver' => config('cache.default'),
            'queue_driver' => config('queue.default'),
            'database' => config('database.default'),
            'storage_writable' => is_writable(storage_path()),
            'cache_writable' => is_writable(base_path('bootstrap/cache')),
            'disk_space' => $this->getDiskSpace(),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
        ];
    }

    private function getDiskSpace()
    {
        $free = disk_free_space('/');
        $total = disk_total_space('/');

        return [
            'free' => $this->formatBytes($free),
            'total' => $this->formatBytes($total),
            'percent' => round(($free / $total) * 100, 2),
        ];
    }

    private function formatBytes($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    private function runHealthCheck()
    {
        $checks = [];

        // PHP Version
        $checks[] = "✓ PHP Version: " . PHP_VERSION;

        // Laravel Version
        $checks[] = "✓ Laravel Version: " . app()->version();

        // Environment
        $checks[] = "✓ Environment: " . app()->environment();

        // Composer Dependencies
        $checks[] = File::exists(base_path('vendor'))
            ? "✓ Composer Dependencies: Installed"
            : "✗ Composer Dependencies: Missing";

        // Environment File
        $checks[] = File::exists(base_path('.env'))
            ? "✓ Environment File: Found"
            : "✗ Environment File: Missing";

        // App Key
        $checks[] = config('app.key')
            ? "✓ Application Key: Set"
            : "✗ Application Key: Missing";

        // Database Connection
        try {
            \DB::connection()->getPdo();
            $checks[] = "✓ Database: Connected";
        } catch (\Exception $e) {
            $checks[] = "✗ Database: Connection Failed";
        }

        // Storage Permissions
        $checks[] = is_writable(storage_path())
            ? "✓ Storage: Writable"
            : "✗ Storage: Not Writable";

        // Bootstrap Cache
        $checks[] = is_writable(base_path('bootstrap/cache'))
            ? "✓ Bootstrap Cache: Writable"
            : "✗ Bootstrap Cache: Not Writable";

        // Queue Status
        $checks[] = "→ Queue Driver: " . config('queue.default');

        // Cache Status
        $checks[] = "→ Cache Driver: " . config('cache.default');

        // AI Services
        $aiDrivers = config('ai.drivers', []);
        $enabledCount = count(array_filter($aiDrivers, fn($d) => $d['enabled'] ?? false));
        $checks[] = "→ AI Drivers: {$enabledCount} enabled";

        // Personas Count
        $personaCount = \App\Models\Persona::count();
        $checks[] = "→ Personas: {$personaCount} registered";

        // Users Count
        $userCount = \App\Models\User::count();
        $checks[] = "→ Users: {$userCount} registered";

        return implode("\n", $checks);
    }

    private function fixPermissions()
    {
        $output = [];

        $output[] = "Setting permissions on storage and bootstrap/cache...";

        try {
            chmod(storage_path(), 0755);
            chmod(base_path('bootstrap/cache'), 0755);

            // Recursively set permissions
            $this->setPermissionsRecursive(storage_path(), 0755, 0644);
            $this->setPermissionsRecursive(base_path('bootstrap/cache'), 0755, 0644);

            $output[] = "✓ Permissions set successfully";
            $output[] = "✓ Directories: 755";
            $output[] = "✓ Files: 644";
        } catch (\Exception $e) {
            $output[] = "✗ Failed to set permissions: " . $e->getMessage();
        }

        return implode("\n", $output);
    }

    private function setPermissionsRecursive($path, $dirMode, $fileMode)
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @chmod($item->getPathname(), $dirMode);
            } else {
                @chmod($item->getPathname(), $fileMode);
            }
        }
    }

    private function clearAllCache()
    {
        $output = [];

        $output[] = "Clearing all caches...";

        Artisan::call('config:clear');
        $output[] = "✓ Config cache cleared";

        Artisan::call('cache:clear');
        $output[] = "✓ Application cache cleared";

        Artisan::call('route:clear');
        $output[] = "✓ Route cache cleared";

        Artisan::call('view:clear');
        $output[] = "✓ View cache cleared";

        Artisan::call('event:clear');
        $output[] = "✓ Event cache cleared";

        $output[] = "\n✓ All caches cleared successfully!";

        return implode("\n", $output);
    }

    private function optimizeApplication()
    {
        $output = [];

        $output[] = "Optimizing application...";

        if (app()->environment('production')) {
            Artisan::call('config:cache');
            $output[] = "✓ Config cached";

            Artisan::call('route:cache');
            $output[] = "✓ Routes cached";

            Artisan::call('view:cache');
            $output[] = "✓ Views cached";

            Artisan::call('event:cache');
            $output[] = "✓ Events cached";
        } else {
            $output[] = "→ Skipping optimization (not in production)";
            $output[] = "→ Run 'php artisan optimize' manually if needed";
        }

        $output[] = "\n✓ Optimization complete!";

        return implode("\n", $output);
    }

    private function validateAIServices()
    {
        $output = [];

        $output[] = "Validating AI services...";

        $drivers = config('ai.drivers', []);

        foreach ($drivers as $name => $config) {
            if ($config['enabled'] ?? false) {
                try {
                    // Check if driver can be instantiated
                    $driver = app('ai')->driver($name);
                    $output[] = "✓ {$name}: Available";
                } catch (\Exception $e) {
                    $output[] = "✗ {$name}: " . $e->getMessage();
                }
            } else {
                $output[] = "→ {$name}: Disabled";
            }
        }

        $output[] = "\n✓ AI service validation complete!";

        return implode("\n", $output);
    }

    private function checkDatabase()
    {
        $output = [];

        $output[] = "Checking database...";

        try {
            $connection = \DB::connection();
            $pdo = $connection->getPdo();

            $output[] = "✓ Database: Connected";
            $output[] = "→ Driver: " . $connection->getDriverName();
            $output[] = "→ Database: " . $connection->getDatabaseName();

            // Check migrations
            $migrationsRun = \DB::table('migrations')->count();
            $output[] = "→ Migrations run: {$migrationsRun}";

            // Check table counts
            $tables = [
                'users' => \App\Models\User::count(),
                'personas' => \App\Models\Persona::count(),
                'conversations' => \App\Models\Conversation::count(),
                'messages' => \App\Models\Message::count(),
                'api_keys' => \App\Models\ApiKey::count(),
            ];

            foreach ($tables as $table => $count) {
                $output[] = "→ {$table}: {$count} records";
            }

        } catch (\Exception $e) {
            $output[] = "✗ Database error: " . $e->getMessage();
        }

        $output[] = "\n✓ Database check complete!";

        return implode("\n", $output);
    }

    private function runTests()
    {
        $output = [];

        $output[] = "Running tests...";
        $output[] = "This may take a minute...\n";

        try {
            Artisan::call('test', ['--stop-on-failure' => true]);
            $output[] = Artisan::output();
        } catch (\Exception $e) {
            $output[] = "✗ Tests failed: " . $e->getMessage();
        }

        return implode("\n", $output);
    }

    private function fixCodeStyle()
    {
        $output = [];

        $output[] = "Fixing code style with Laravel Pint...";

        if (File::exists(base_path('vendor/bin/pint'))) {
            $process = new \Symfony\Component\Process\Process(
                ['./vendor/bin/pint'],
                base_path(),
                null,
                null,
                120
            );

            try {
                $process->run();
                $output[] = $process->getOutput();
                $output[] = "✓ Code style fixed!";
            } catch (\Exception $e) {
                $output[] = "✗ Failed: " . $e->getMessage();
            }
        } else {
            $output[] = "✗ Laravel Pint not found";
            $output[] = "→ Install with: composer require laravel/pint --dev";
        }

        return implode("\n", $output);
    }
}
