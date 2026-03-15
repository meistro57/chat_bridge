<?php

namespace App\Console\Commands;

use App\Jobs\RunOrchestration;
use App\Models\Orchestration;
use App\Models\OrchestratorRun;
use Illuminate\Console\Command;

class OrchestrationRun extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orchestration:run {orchestration : The UUID of the orchestration to run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manually trigger a run for a given orchestration';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $orchestration = Orchestration::findOrFail($this->argument('orchestration'));

        $run = OrchestratorRun::create([
            'orchestration_id' => $orchestration->id,
            'user_id' => $orchestration->user_id,
            'status' => 'queued',
            'triggered_by' => 'manual',
        ]);

        RunOrchestration::dispatch($run->id);

        $this->info("Dispatched run [{$run->id}] for orchestration [{$orchestration->name}].");

        return self::SUCCESS;
    }
}
