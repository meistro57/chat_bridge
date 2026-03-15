<?php

namespace App\Services\Orchestrator;

use App\Models\Conversation;
use App\Models\OrchestratorRun;
use App\Models\OrchestratorStep;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OrchestratorService
{
    /**
     * Resolve the input string for a step given the current run's variable bag.
     */
    public function resolveStepInput(OrchestratorStep $step, OrchestratorRun $run): string
    {
        return match ($step->input_source) {
            'variable' => $this->resolveVariable($step->input_variable_name ?? '', $run->variables ?? []),
            'previous_step_output' => $this->resolveVariable('_previous_output', $run->variables ?? []),
            default => $step->input_value ?? '',
        };
    }

    /**
     * Evaluate a condition against a previous step's output.
     *
     * @param  array<string, mixed>|null  $condition
     */
    public function evaluateCondition(?array $condition, ?string $previousOutput): bool
    {
        if ($condition === null || $condition === []) {
            return true;
        }

        $output = $previousOutput ?? '';

        if (isset($condition['contains'])) {
            return str_contains($output, $condition['contains']);
        }

        if (isset($condition['not_contains'])) {
            return ! str_contains($output, $condition['not_contains']);
        }

        if (isset($condition['equals'])) {
            return $output === $condition['equals'];
        }

        if (isset($condition['regex'])) {
            return (bool) preg_match('/' . $condition['regex'] . '/i', $output);
        }

        return true;
    }

    /**
     * Compute the next scheduled run time for a cron expression.
     */
    public function computeNextRunAt(string $cronExpression, string $timezone): Carbon
    {
        return \Cron\CronExpression::factory($cronExpression)
            ->getNextRunDate('now', 0, false, $timezone);
    }

    /**
     * Capture the last assistant output from a completed conversation.
     */
    public function captureConversationOutput(Conversation $conversation): string
    {
        $message = $conversation->messages()
            ->where('role', 'assistant')
            ->latest('id')
            ->first();

        return $message?->content ?? '';
    }

    /**
     * Apply the step's output action to the run's variable bag.
     */
    public function applyOutputAction(OrchestratorStep $step, OrchestratorRun $run, string $output): void
    {
        $variables = $run->variables ?? [];

        $variables['_previous_output'] = $output;

        if ($step->output_action === 'store_as_variable' && $step->output_variable_name) {
            $variables[$step->output_variable_name] = $output;
        }

        if ($step->output_action === 'webhook' && $step->output_webhook_url) {
            $this->postToWebhook($step->output_webhook_url, $output, $step, $run);
        }

        $run->update(['variables' => $variables]);
    }

    /**
     * Resolve a dot-notation variable from the variable bag.
     *
     * @param  array<string, mixed>  $variables
     */
    protected function resolveVariable(string $key, array $variables): string
    {
        return data_get($variables, $key, '') ?? '';
    }

    protected function postToWebhook(string $url, string $output, OrchestratorStep $step, OrchestratorRun $run): void
    {
        try {
            Http::timeout(10)->post($url, [
                'output' => $output,
                'step_id' => $step->id,
                'run_id' => $run->id,
                'orchestration_id' => $run->orchestration_id,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Orchestrator webhook POST failed', [
                'url' => $url,
                'error' => $e->getMessage(),
                'run_id' => $run->id,
            ]);
        }
    }
}
