<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AI\Data\MessageData;
use App\Services\AI\Drivers\OpenAIDriver;
use App\Services\System\EnvFileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\Process\Process;

class SystemController extends Controller
{
    public function __construct(
        private readonly EnvFileService $envService
    ) {}

    public function index(): InertiaResponse
    {
        return Inertia::render('Admin/System', [
            'systemInfo' => $this->getSystemInfo(),
        ]);
    }

    public function runDiagnostic(Request $request): JsonResponse
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

                case 'invoke_codex':
                    $output = $this->invokeCodex($request->input('prompt', ''));
                    break;

                default:
                    $success = false;
                    $output = "Unknown action: {$action}";
            }
        } catch (\Exception $e) {
            $success = false;
            $output = 'Error: '.$e->getMessage();
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

    public function updateOpenAiKey(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'openai_key' => 'required|string|min:20',
        ]);

        $this->envService->updateKey('OPENAI_API_KEY', $validated['openai_key']);
        Artisan::call('config:clear');

        Log::info('Service OpenAI key updated by admin', [
            'user_id' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'openai_key_set' => true,
            'openai_key_last4' => substr($validated['openai_key'], -4),
        ]);
    }

    public function testOpenAiKey(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'openai_key' => 'nullable|string|min:20',
        ]);

        $key = $validated['openai_key'] ?? (string) config('services.openai.key', '');

        if ($key === '') {
            return response()->json([
                'success' => false,
                'message' => 'No OpenAI key configured.',
            ], 422);
        }

        $driver = new OpenAIDriver(
            apiKey: $key,
            model: config('services.openai.model', 'gpt-4o-mini')
        );

        $messages = collect([
            new MessageData('user', 'Respond with only the word "OK".'),
        ]);

        try {
            $result = trim($driver->chat($messages, 0));
        } catch (\Throwable $e) {
            Log::warning('OpenAI key test failed', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'OpenAI key test failed: '.$e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $result === 'OK' ? 'OpenAI key is valid.' : 'OpenAI responded, but content was unexpected.',
            'result' => $result,
        ]);
    }

    public function clearOpenAiKey(): JsonResponse
    {
        $this->envService->updateKey('OPENAI_API_KEY', '');
        Artisan::call('config:clear');

        Log::info('Service OpenAI key cleared by admin', [
            'user_id' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'openai_key_set' => false,
        ]);
    }

    private function getSystemInfo(): array
    {
        $openaiKey = (string) config('services.openai.key', '');
        $boostConfig = $this->getBoostConfig();
        $mcpHealth = $this->getMcpHealth();

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
            'openai_key_set' => $openaiKey !== '',
            'openai_key_last4' => $openaiKey !== '' ? substr($openaiKey, -4) : null,
            'boost' => $boostConfig,
            'mcp' => $mcpHealth,
        ];
    }

    private function getDiskSpace(): array
    {
        $free = disk_free_space('/');
        $total = disk_total_space('/');

        return [
            'free' => $this->formatBytes($free),
            'total' => $this->formatBytes($total),
            'percent' => round(($free / $total) * 100, 2),
        ];
    }

    private function formatBytes(float|int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2).' '.$units[$pow];
    }

    private function runHealthCheck(): string
    {
        $checks = [];

        // PHP Version
        $checks[] = '✓ PHP Version: '.PHP_VERSION;

        // Laravel Version
        $checks[] = '✓ Laravel Version: '.app()->version();

        // Environment
        $checks[] = '✓ Environment: '.app()->environment();

        // Composer Dependencies
        $checks[] = File::exists(base_path('vendor'))
            ? '✓ Composer Dependencies: Installed'
            : '✗ Composer Dependencies: Missing';

        // Environment File
        $checks[] = File::exists(base_path('.env'))
            ? '✓ Environment File: Found'
            : '✗ Environment File: Missing';

        // App Key
        $checks[] = config('app.key')
            ? '✓ Application Key: Set'
            : '✗ Application Key: Missing';

        // Database Connection
        try {
            \DB::connection()->getPdo();
            $checks[] = '✓ Database: Connected';
        } catch (\Exception $e) {
            $checks[] = '✗ Database: Connection Failed';
        }

        // Storage Permissions
        $checks[] = is_writable(storage_path())
            ? '✓ Storage: Writable'
            : '✗ Storage: Not Writable';

        // Bootstrap Cache
        $checks[] = is_writable(base_path('bootstrap/cache'))
            ? '✓ Bootstrap Cache: Writable'
            : '✗ Bootstrap Cache: Not Writable';

        // Queue Status
        $checks[] = '→ Queue Driver: '.config('queue.default');

        // Cache Status
        $checks[] = '→ Cache Driver: '.config('cache.default');

        // AI Services
        $aiDrivers = config('ai.drivers', []);
        $enabledCount = count(array_filter($aiDrivers, fn ($d) => $d['enabled'] ?? false));
        $checks[] = "→ AI Drivers: {$enabledCount} enabled";

        // Personas Count
        $personaCount = \App\Models\Persona::count();
        $checks[] = "→ Personas: {$personaCount} registered";

        // Users Count
        $userCount = \App\Models\User::count();
        $checks[] = "→ Users: {$userCount} registered";

        return implode("\n", $checks);
    }

    private function fixPermissions(): string
    {
        $output = [];

        $output[] = 'Setting permissions on storage and bootstrap/cache...';

        $storagePath = storage_path();
        $cachePath = base_path('bootstrap/cache');
        $useOpenPermissions = app()->environment(['local', 'testing']) || $this->isRunningInDocker();
        $dirMode = $useOpenPermissions ? 0777 : 0755;
        $fileMode = $useOpenPermissions ? 0666 : 0644;

        try {
            $storageRoot = chmod($storagePath, $dirMode);
            $cacheRoot = chmod($cachePath, $dirMode);

            $storageResults = $this->setPermissionsRecursive($storagePath, $dirMode, $fileMode);
            $cacheResults = $this->setPermissionsRecursive($cachePath, $dirMode, $fileMode);

            $failedDirs = $storageResults['dir_failed'] + $cacheResults['dir_failed'];
            $failedFiles = $storageResults['file_failed'] + $cacheResults['file_failed'];

            if ($storageRoot && $cacheRoot && $failedDirs === 0 && $failedFiles === 0) {
                $output[] = '✓ Permissions set successfully';
                $output[] = '✓ Directories: '.decoct($dirMode);
                $output[] = '✓ Files: '.decoct($fileMode);
            } else {
                $output[] = '✗ Failed to update some permissions';
                $output[] = '→ Directories failed: '.$failedDirs;
                $output[] = '→ Files failed: '.$failedFiles;
            }
        } catch (\Exception $e) {
            $output[] = '✗ Failed to set permissions: '.$e->getMessage();
        }

        if ($this->isRunningInDocker() && (! is_writable($storagePath) || ! is_writable($cachePath))) {
            $output[] = '→ Docker hint: run';
            $output[] = '  docker compose exec -T app sh -lc "chmod -R 777 /var/www/html/storage /var/www/html/bootstrap/cache"';
        }

        return implode("\n", $output);
    }

    /**
     * @return array{dir_failed:int, file_failed:int}
     */
    private function setPermissionsRecursive(string $path, int $dirMode, int $fileMode): array
    {
        $dirFailed = 0;
        $fileFailed = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                if (! chmod($item->getPathname(), $dirMode)) {
                    $dirFailed++;
                }
            } else {
                if (! chmod($item->getPathname(), $fileMode)) {
                    $fileFailed++;
                }
            }
        }

        return [
            'dir_failed' => $dirFailed,
            'file_failed' => $fileFailed,
        ];
    }

    private function isRunningInDocker(): bool
    {
        if (file_exists('/.dockerenv')) {
            return true;
        }

        $cgroup = @file_get_contents('/proc/1/cgroup');
        if ($cgroup === false) {
            return false;
        }

        return str_contains($cgroup, 'docker') || str_contains($cgroup, 'kubepods');
    }

    private function clearAllCache(): string
    {
        $output = [];

        $output[] = 'Clearing all caches...';

        Artisan::call('config:clear');
        $output[] = '✓ Config cache cleared';

        Artisan::call('cache:clear');
        $output[] = '✓ Application cache cleared';

        Artisan::call('route:clear');
        $output[] = '✓ Route cache cleared';

        Artisan::call('view:clear');
        $output[] = '✓ View cache cleared';

        Artisan::call('event:clear');
        $output[] = '✓ Event cache cleared';

        $output[] = "\n✓ All caches cleared successfully!";

        return implode("\n", $output);
    }

    private function optimizeApplication(): string
    {
        $output = [];

        $output[] = 'Optimizing application...';

        if (app()->environment('production')) {
            Artisan::call('config:cache');
            $output[] = '✓ Config cached';

            Artisan::call('route:cache');
            $output[] = '✓ Routes cached';

            Artisan::call('view:cache');
            $output[] = '✓ Views cached';

            Artisan::call('event:cache');
            $output[] = '✓ Events cached';
        } else {
            $output[] = '→ Skipping optimization (not in production)';
            $output[] = "→ Run 'php artisan optimize' manually if needed";
        }

        $output[] = "\n✓ Optimization complete!";

        return implode("\n", $output);
    }

    private function validateAIServices(): string
    {
        $output = [];

        $output[] = 'Validating AI services...';

        $drivers = config('ai.drivers', []);

        foreach ($drivers as $name => $config) {
            if ($config['enabled'] ?? false) {
                try {
                    // Check if driver can be instantiated
                    $driver = app('ai')->driver($name);
                    $output[] = "✓ {$name}: Available";
                } catch (\Exception $e) {
                    $output[] = "✗ {$name}: ".$e->getMessage();
                }
            } else {
                $output[] = "→ {$name}: Disabled";
            }
        }

        $output[] = "\n✓ AI service validation complete!";

        return implode("\n", $output);
    }

    private function checkDatabase(): string
    {
        $output = [];

        $output[] = 'Checking database...';

        try {
            $connection = \DB::connection();
            $pdo = $connection->getPdo();

            $output[] = '✓ Database: Connected';
            $output[] = '→ Driver: '.$connection->getDriverName();
            $output[] = '→ Database: '.$connection->getDatabaseName();

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
            $output[] = '✗ Database error: '.$e->getMessage();
        }

        $output[] = "\n✓ Database check complete!";

        return implode("\n", $output);
    }

    private function runTests(): string
    {
        $output = [];

        $output[] = 'Running tests...';
        $output[] = "This may take a minute...\n";

        try {
            Artisan::call('test', ['--stop-on-failure' => true]);
            $output[] = Artisan::output();
        } catch (\Exception $e) {
            $output[] = '✗ Tests failed: '.$e->getMessage();
        }

        return implode("\n", $output);
    }

    private function fixCodeStyle(): string
    {
        $output = [];

        $output[] = 'Fixing code style with Laravel Pint...';

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
                $output[] = '✓ Code style fixed!';
            } catch (\Exception $e) {
                $output[] = '✗ Failed: '.$e->getMessage();
            }
        } else {
            $output[] = '✗ Laravel Pint not found';
            $output[] = '→ Install with: composer require laravel/pint --dev';
        }

        return implode("\n", $output);
    }

    private function invokeCodex(string $prompt): string
    {
        $output = [];

        $output[] = 'Invoking Codex AI Agent...';
        $output[] = '';

        // Check if OpenAI key is set
        $openaiKey = (string) config('services.openai.key', '');
        if ($openaiKey === '') {
            $output[] = '✗ Error: No OpenAI service key configured.';
            $output[] = '→ Please set an OpenAI key in the Service Key section above.';

            return implode("\n", $output);
        }

        // Get boost config to verify Codex is available
        $boostConfig = $this->getBoostConfig();
        if (! $boostConfig['present']) {
            $output[] = '✗ Error: Boost configuration not found (boost.json missing).';

            return implode("\n", $output);
        }

        if (! in_array('codex', $boostConfig['agents'])) {
            $output[] = '✗ Error: Codex agent not registered in boost.json.';
            $output[] = '→ Available agents: '.implode(', ', $boostConfig['agents']);

            return implode("\n", $output);
        }

        // Default prompt for diagnostics if none provided
        if (empty($prompt)) {
            $prompt = 'Analyze the current system state and provide a brief health summary. List any potential issues or recommendations.';
        }

        $context = $this->buildCodexContext();

        $output[] = '✓ Codex agent verified';
        $output[] = '✓ OpenAI key configured';
        $output[] = '';
        $output[] = '→ Prompt: '.$prompt;
        $output[] = '→ Context: '.$context['summary'];
        $output[] = '';

        try {
            $response = $this->runCodexCli($openaiKey, $context['details'], $prompt);

            $output[] = '─────────────────────────────────────────';
            $output[] = 'CODEX RESPONSE:';
            $output[] = '─────────────────────────────────────────';
            $output[] = '';
            $output[] = trim($response);
            $output[] = '';
            $output[] = '─────────────────────────────────────────';
            $output[] = '✓ Codex invocation complete';

        } catch (\Throwable $e) {
            $output[] = '✗ Codex invocation failed: '.$e->getMessage();
            Log::error('Codex invocation failed', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);
        }

        return implode("\n", $output);
    }

    private function runCodexCli(string $openaiKey, string $context, string $prompt): string
    {
        $fullPrompt = <<<PROMPT
CONTEXT:
{$context}

TASK:
{$prompt}

RULES:
- Use the provided context and repository information.
- If context is missing or insufficient, say so explicitly.
- Avoid generic advice; be specific to the errors and files referenced.
PROMPT;

        $outputPath = storage_path('app/codex/last-message-'.Str::uuid().'.txt');
        File::ensureDirectoryExists(dirname($outputPath));

        $process = new Process(
            $this->buildCodexCommand($fullPrompt, $outputPath),
            base_path(),
            $this->buildCodexEnvironment($openaiKey)
        );
        $process->setTimeout(300);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException(trim($process->getErrorOutput()) ?: 'Codex CLI failed.');
        }

        if (File::exists($outputPath)) {
            return trim((string) File::get($outputPath));
        }

        return trim($process->getOutput());
    }

    /**
     * @return array<int,string>
     */
    protected function buildCodexCommand(string $fullPrompt, string $outputPath): array
    {
        return [
            'npx',
            '--no-install',
            'codex',
            'exec',
            '--skip-git-repo-check',
            '--color',
            'never',
            '--output-last-message',
            $outputPath,
            $fullPrompt,
        ];
    }

    /**
     * @return array<string,string>
     */
    protected function buildCodexEnvironment(string $openaiKey): array
    {
        return [
            'CODEX_API_KEY' => $openaiKey,
            'OPENAI_API_KEY' => $openaiKey,
            'CODEX_HOME' => config('services.codex.home'),
            'TERM' => 'dumb',
            'NO_COLOR' => '1',
            'COLUMNS' => '120',
            'LINES' => '40',
        ];
    }

    /**
     * @return array{summary:string,details:string}
     */
    private function buildCodexContext(): array
    {
        $systemInfo = $this->getSystemInfo();
        $recentErrors = $this->getRecentErrors();
        $logTail = $this->getLogTail();

        $summary = 'System info + extracted errors + recent log tail attached.';
        $details = "SYSTEM INFO\n".json_encode($systemInfo, JSON_PRETTY_PRINT);
        $details .= "\n\nRECENT ERRORS (EXTRACTED)\n".$recentErrors;
        $details .= "\n\nRECENT LOG TAIL\n".$logTail;

        return [
            'summary' => $summary,
            'details' => $details,
        ];
    }

    private function getRecentErrors(int $limit = 20): string
    {
        $path = storage_path('logs/laravel.log');

        if (! File::exists($path)) {
            return 'laravel.log not found.';
        }

        $lines = collect(explode("\n", File::get($path)));
        $errors = $lines->filter(function (string $line) {
            return str_contains($line, '.ERROR:') || str_contains($line, 'ERROR:');
        })->take(-$limit)->implode("\n");

        if ($errors === '') {
            return 'No recent error entries found.';
        }

        return $this->redactSecrets($errors);
    }

    private function getLogTail(int $lines = 200): string
    {
        $path = storage_path('logs/laravel.log');

        if (! File::exists($path)) {
            return 'laravel.log not found.';
        }

        $contents = File::get($path);
        $tail = collect(explode("\n", $contents))
            ->take(-$lines)
            ->implode("\n");

        return $this->redactSecrets($tail);
    }

    private function redactSecrets(string $text): string
    {
        $patterns = [
            '/sk-[A-Za-z0-9]{20,}/',
            '/(OPENAI_API_KEY=)[^\s]+/',
        ];

        foreach ($patterns as $pattern) {
            $text = preg_replace($pattern, '[redacted]', $text) ?? $text;
        }

        return Str::limit($text, 12000, "\n[truncated]");
    }

    private function getBoostConfig(): array
    {
        $path = base_path('boost.json');

        if (! File::exists($path)) {
            return [
                'present' => false,
                'agents' => [],
                'editors' => [],
                'error' => null,
            ];
        }

        try {
            $decoded = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);

            return [
                'present' => true,
                'agents' => $decoded['agents'] ?? [],
                'editors' => $decoded['editors'] ?? [],
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return [
                'present' => false,
                'agents' => [],
                'editors' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    private function getMcpHealth(): array
    {
        try {
            $response = app(\App\Http\Controllers\Api\McpController::class)->health();
            $data = method_exists($response, 'getData') ? $response->getData(true) : [];

            return [
                'ok' => ($data['status'] ?? null) === 'ok',
                'details' => $data,
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'details' => [
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ],
            ];
        }
    }
}
