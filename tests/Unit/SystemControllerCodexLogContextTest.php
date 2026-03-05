<?php

namespace Tests\Unit;

use App\Http\Controllers\Admin\SystemController;
use App\Services\System\EnvFileService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SystemControllerCodexLogContextTest extends TestCase
{
    public function test_codex_log_helpers_use_newest_laravel_log_file(): void
    {
        $logDir = storage_path('logs');
        File::ensureDirectoryExists($logDir);

        $olderPath = $logDir.'/laravel-old-test.log';
        $newerPath = $logDir.'/laravel-new-test.log';

        file_put_contents($olderPath, "[2026-03-05 10:00:00] local.ERROR: old-log-error\n");
        file_put_contents($newerPath, "[2026-03-05 10:00:00] local.ERROR: newest-log-error\n");

        touch($olderPath, time() - 120);
        touch($newerPath, time());

        config()->set('services.codex.log_recent_minutes', 100000);

        $controller = new SystemController($this->mock(EnvFileService::class));
        $errors = $this->invokePrivate($controller, 'getRecentErrors', 20);
        $tail = $this->invokePrivate($controller, 'getLogTail', 50);

        $this->assertStringContainsString('Source: laravel-new-test.log', $errors);
        $this->assertStringContainsString('newest-log-error', $errors);
        $this->assertStringNotContainsString('old-log-error', $errors);

        $this->assertStringContainsString('Source: laravel-new-test.log', $tail);
        $this->assertStringContainsString('newest-log-error', $tail);

        @unlink($olderPath);
        @unlink($newerPath);
    }

    public function test_codex_recent_errors_filter_to_recent_window(): void
    {
        $logDir = storage_path('logs');
        File::ensureDirectoryExists($logDir);
        $path = $logDir.'/laravel-window-test.log';

        $oldTimestamp = now()->subHours(4)->format('Y-m-d H:i:s');
        $recentTimestamp = now()->subMinutes(5)->format('Y-m-d H:i:s');

        file_put_contents($path, implode("\n", [
            "[{$oldTimestamp}] local.ERROR: stale error should not be used",
            "[{$recentTimestamp}] local.ERROR: fresh error should be used",
        ]));
        touch($path, time());

        config()->set('services.codex.log_recent_minutes', 60);

        $controller = new SystemController($this->mock(EnvFileService::class));
        $errors = $this->invokePrivate($controller, 'getRecentErrors', 20);

        $this->assertStringContainsString('fresh error should be used', $errors);
        $this->assertStringNotContainsString('stale error should not be used', $errors);

        @unlink($path);
    }

    private function invokePrivate(object $target, string $method, mixed ...$arguments): mixed
    {
        $reflection = new \ReflectionMethod($target, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($target, $arguments);
    }
}
