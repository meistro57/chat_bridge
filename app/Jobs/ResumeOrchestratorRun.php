<?php

namespace App\Jobs;

use App\Models\OrchestratorRun;
use App\Models\OrchestratorStep;
use App\Models\OrchestratorStepRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ResumeOrchestratorRun implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $runId
    ) {}

    public function handle(): void
    {
        $run = OrchestratorRun::with('orchestration.steps')->findOrFail($this->runId);

        if ($run->status !== 'paused') {
            return;
        }

        /** @var OrchestratorStepRun|null $pausedStepRun */
        $pausedStepRun = $run->stepRuns()
            ->where('status', 'paused')
            ->with('step')
            ->first();

        if (! $pausedStepRun) {
            return;
        }

        /** @var OrchestratorStep $pausedStep */
        $pausedStep = $pausedStepRun->step;

        $pausedStepRun->update(['status' => 'skipped']);
        $run->update(['status' => 'queued']);

        RunOrchestration::dispatch($run->id, $pausedStep->step_number);
    }
}
