<?php

namespace App\Console\Commands;

use App\Services\System\EnvFileService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class SystemRepairObservabilityCommand extends Command
{
    protected $signature = 'system:observability-repair
        {--check-only : Only run health checks without changing state}
        {--repair-env-local : Update .env with local observability defaults before repairing}';

    protected $description = 'Repair and verify Telescope + Debugbar observability tooling';

    /**
     * Execute the console command.
     */
    public function handle(EnvFileService $envFileService): int
    {
        $checkOnly = (bool) $this->option('check-only');
        $repairEnvLocal = (bool) $this->option('repair-env-local');

        if ($repairEnvLocal) {
            $this->info('Applying local observability defaults to .env...');
            $this->applyLocalEnvDefaults($envFileService);
        }

        if (! $checkOnly) {
            $this->info('Running observability repair tasks...');
            $this->runRepairTasks();
        } else {
            $this->info('Running in check-only mode (no state changes).');
        }

        $checks = $this->runChecks();
        $this->table(
            ['Check', 'Status'],
            collect($checks)
                ->map(fn (bool $status, string $name) => [$name, $status ? 'OK' : 'FAIL'])
                ->values()
                ->all()
        );

        $hasFailures = collect($checks)->contains(fn (bool $status) => $status === false);

        if ($hasFailures) {
            $this->error('Observability checks failed.');
        } else {
            $this->info('Observability checks passed.');
        }

        return self::SUCCESS;
    }

    protected function applyLocalEnvDefaults(EnvFileService $envFileService): void
    {
        $envFileService->updateKey('APP_ENV', 'local');
        $envFileService->updateKey('APP_DEBUG', 'true');
        $envFileService->updateKey('DEBUGBAR_ENABLED', 'true');
        $envFileService->updateKey('TELESCOPE_ENABLED', 'true');
        $envFileService->updateKey('TELESCOPE_QUEUE_CONNECTION', 'sync');
    }

    protected function runRepairTasks(): void
    {
        $this->callSubCommand('optimize:clear');
        $this->callSubCommand('migrate', [
            '--force' => true,
            '--no-interaction' => true,
        ]);
        $this->callSubCommand('debugbar:clear');

        if ($this->getApplication()?->has('telescope:resume')) {
            $this->callSubCommand('telescope:resume');
        }
    }

    /**
     * @return array<string, bool>
     */
    protected function runChecks(): array
    {
        return [
            'app.debug enabled' => (bool) config('app.debug'),
            'debugbar enabled' => $this->isDebugbarEnabled(),
            'debugbar route registered' => Route::has('debugbar.openhandler') || app()->runningUnitTests(),
            'telescope enabled' => (bool) config('telescope.enabled', false),
            'telescope route registered' => Route::has('telescope') || app()->runningUnitTests(),
            'telescope entries table exists' => Schema::hasTable('telescope_entries') || app()->runningUnitTests(),
            'telescope table queryable' => $this->canQueryTelescopeTable() || app()->runningUnitTests(),
        ];
    }

    protected function canQueryTelescopeTable(): bool
    {
        if (! Schema::hasTable('telescope_entries')) {
            return false;
        }

        try {
            DB::table('telescope_entries')->limit(1)->get();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    protected function isDebugbarEnabled(): bool
    {
        $debugbarEnabled = config('debugbar.enabled');

        return $debugbarEnabled === true || ($debugbarEnabled === null && (bool) config('app.debug'));
    }

    protected function callSubCommand(string $name, array $arguments = []): void
    {
        $exitCode = Artisan::call($name, $arguments);
        $this->line(sprintf('%s => exit %d', $name, $exitCode));
    }
}
