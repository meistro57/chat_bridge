<?php

namespace App\Console\Commands;

use App\Jobs\RunOrchestration;
use App\Models\Orchestration;
use App\Models\OrchestratorRun;
use App\Services\Orchestrator\OrchestratorService;
use Illuminate\Console\Command;

class OrchestrationSchedule extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orchestration:schedule';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch due scheduled orchestrations';

    /**
     * Execute the console command.
     */
    public function handle(OrchestratorService $service): int
    {
        $due = Orchestration::query()
            ->where('is_scheduled', true)
            ->where('status', 'idle')
            ->where('next_run_at', '<=', now())
            ->get();

        foreach ($due as $orchestration) {
            /** @var Orchestration $orchestration */
            $run = OrchestratorRun::create([
                'orchestration_id' => $orchestration->id,
                'user_id' => $orchestration->user_id,
                'status' => 'queued',
                'triggered_by' => 'schedule',
            ]);

            RunOrchestration::dispatch($run->id);

            $nextRunAt = $service->computeNextRunAt(
                $orchestration->cron_expression,
                $orchestration->timezone
            );

            $orchestration->update([
                'last_run_at' => now(),
                'next_run_at' => $nextRunAt,
            ]);

            $this->info("Dispatched run [{$run->id}] for [{$orchestration->name}]. Next run: {$nextRunAt}.");
        }

        if ($due->isEmpty()) {
            $this->line('No due orchestrations.');
        }

        return self::SUCCESS;
    }
}
