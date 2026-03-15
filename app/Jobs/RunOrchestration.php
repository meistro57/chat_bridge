<?php

namespace App\Jobs;

use App\Events\OrchestratorRunCompleted;
use App\Events\OrchestratorStepPaused;
use App\Events\OrchestratorStepStarted;
use App\Models\Conversation;
use App\Models\Orchestration;
use App\Models\OrchestratorRun;
use App\Models\OrchestratorStep;
use App\Models\OrchestratorStepRun;
use App\Services\Orchestrator\OrchestratorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunOrchestration implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public int $timeout = 3600;

    public function __construct(
        public string $runId,
        public int $startFromStep = 1
    ) {}

    public function handle(OrchestratorService $service): void
    {
        $run = OrchestratorRun::with(['orchestration.steps'])->findOrFail($this->runId);
        $orchestration = $run->orchestration;

        $run->update(['status' => 'running', 'started_at' => now()]);
        $orchestration->update(['status' => 'running']);

        try {
            $this->executeSteps($run, $orchestration, $service);
        } catch (\Throwable $e) {
            Log::error('RunOrchestration job failed', [
                'run_id' => $run->id,
                'error' => $e->getMessage(),
            ]);

            $run->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            $orchestration->update(['status' => 'idle']);

            return;
        }

        $run->refresh();

        if ($run->status === 'paused') {
            return;
        }

        $run->update(['status' => 'completed', 'completed_at' => now()]);

        $orchestration->update([
            'status' => 'idle',
            'last_run_at' => now(),
        ]);

        if ($orchestration->is_scheduled && $orchestration->cron_expression) {
            $nextRunAt = $service->computeNextRunAt(
                $orchestration->cron_expression,
                $orchestration->timezone
            );
            $orchestration->update(['next_run_at' => $nextRunAt]);
        }

        OrchestratorRunCompleted::dispatch($run->refresh());
    }

    protected function executeSteps(OrchestratorRun $run, Orchestration $orchestration, OrchestratorService $service): void
    {
        $steps = $orchestration->steps->where('step_number', '>=', $this->startFromStep);
        $previousOutput = null;

        foreach ($steps as $step) {
            /** @var OrchestratorStep $step */

            $conditionPassed = $service->evaluateCondition($step->condition, $previousOutput);

            $stepRun = OrchestratorStepRun::create([
                'run_id' => $run->id,
                'step_id' => $step->id,
                'status' => 'pending',
                'condition_passed' => $conditionPassed,
            ]);

            if (! $conditionPassed) {
                $stepRun->update(['status' => 'skipped']);
                continue;
            }

            if ($step->pause_before_run) {
                $stepRun->update(['status' => 'paused']);
                $run->update(['status' => 'paused']);
                $stepRun->load('run.orchestration');
                OrchestratorStepPaused::dispatch($stepRun);

                return;
            }

            $stepRun->update(['status' => 'running', 'started_at' => now()]);
            $stepRun->load('run.orchestration');
            OrchestratorStepStarted::dispatch($stepRun);

            $resolvedInput = $service->resolveStepInput($step, $run);
            $conversation = $this->createAndRunConversation($step, $run, $resolvedInput);

            $output = $service->captureConversationOutput($conversation);
            $service->applyOutputAction($step, $run, $output);

            $stepRun->update([
                'status' => 'completed',
                'conversation_id' => $conversation->id,
                'output_summary' => mb_substr($output, 0, 5000),
                'completed_at' => now(),
            ]);

            $previousOutput = $output;
        }
    }

    protected function createAndRunConversation(OrchestratorStep $step, OrchestratorRun $run, string $input): Conversation
    {
        $template = $step->template;

        $personaAId = $step->persona_a_id ?? $template?->persona_a_id;
        $personaBId = $step->persona_b_id ?? $template?->persona_b_id;
        $providerA = $step->provider_a ?? 'openai';
        $providerB = $step->provider_b ?? 'openai';
        $modelA = $step->model_a ?? null;
        $modelB = $step->model_b ?? null;
        $maxRounds = $template?->max_rounds ?? 10;

        if (! $personaAId || ! $personaBId) {
            $userPersonas = \App\Models\Persona::where('user_id', $run->user_id)->pluck('id');

            if ($userPersonas->count() < 2) {
                throw new \RuntimeException("Step {$step->step_number} is missing persona configuration and no fallback personas are available.");
            }

            $personaAId = $personaAId ?? $userPersonas->get(0);
            $personaBId = $personaBId ?? $userPersonas->get(1);
        }

        $conversation = Conversation::create([
            'user_id' => $run->user_id,
            'persona_a_id' => $personaAId,
            'persona_b_id' => $personaBId,
            'provider_a' => $providerA,
            'provider_b' => $providerB,
            'model_a' => $modelA,
            'model_b' => $modelB,
            'temp_a' => 1.0,
            'temp_b' => 1.0,
            'starter_message' => $input,
            'status' => 'active',
            'max_rounds' => $maxRounds,
            'stop_word_detection' => false,
            'stop_words' => [],
            'stop_word_threshold' => 0.8,
            'metadata' => [
                'persona_a_name' => $step->personaA?->name ?? '',
                'persona_b_name' => $step->personaB?->name ?? '',
                'orchestration_run_id' => $run->id,
                'orchestration_id' => $run->orchestration_id,
            ],
        ]);

        $conversation->messages()->create([
            'user_id' => $run->user_id,
            'role' => 'user',
            'content' => $input,
        ]);

        RunChatSession::dispatchSync($conversation->id, $maxRounds);

        return $conversation->refresh();
    }
}
