<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrchestratorRequest;
use App\Http\Requests\UpdateOrchestratorRequest;
use App\Jobs\RunOrchestration;
use App\Models\Orchestration;
use App\Models\OrchestratorRun;
use App\Models\OrchestratorStep;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class OrchestratorController extends Controller
{
    /**
     * Display a listing of the user's orchestrations.
     */
    public function index(Request $request): InertiaResponse
    {
        $orchestrations = Orchestration::query()
            ->where('user_id', $request->user()->id)
            ->with('latestRun')
            ->withCount('runs')
            ->latest()
            ->paginate(20);

        return Inertia::render('Orchestrator/Index', [
            'orchestrations' => $orchestrations,
        ]);
    }

    /**
     * Display the specified orchestration with run history.
     */
    public function show(Request $request, Orchestration $orchestration): InertiaResponse
    {
        if ($orchestration->user_id !== $request->user()->id) {
            abort(403);
        }

        $orchestration->load(['steps', 'latestRun']);

        $runs = $orchestration->runs()
            ->with('stepRuns.step')
            ->latest()
            ->paginate(10);

        return Inertia::render('Orchestrator/Show', [
            'orchestration' => $orchestration,
            'runs' => $runs,
        ]);
    }

    /**
     * Store a new orchestration (from wizard output).
     */
    public function store(StoreOrchestratorRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $orchestration = Orchestration::create([
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'goal' => $validated['goal'] ?? null,
            'is_scheduled' => $validated['is_scheduled'] ?? false,
            'cron_expression' => $validated['cron_expression'] ?? null,
            'timezone' => $validated['timezone'] ?? 'UTC',
            'status' => 'idle',
        ]);

        foreach ($validated['steps'] as $stepData) {
            OrchestratorStep::create([
                'orchestration_id' => $orchestration->id,
                'step_number' => $stepData['step_number'],
                'label' => $stepData['label'] ?? null,
                'template_id' => $stepData['template_id'] ?? null,
                'persona_a_id' => $stepData['persona_a_id'] ?? null,
                'persona_b_id' => $stepData['persona_b_id'] ?? null,
                'provider_a' => $stepData['provider_a'] ?? null,
                'model_a' => $stepData['model_a'] ?? null,
                'provider_b' => $stepData['provider_b'] ?? null,
                'model_b' => $stepData['model_b'] ?? null,
                'input_source' => $stepData['input_source'] ?? 'static',
                'input_value' => $stepData['input_value'] ?? null,
                'input_variable_name' => $stepData['input_variable_name'] ?? null,
                'output_action' => $stepData['output_action'] ?? 'log',
                'output_variable_name' => $stepData['output_variable_name'] ?? null,
                'output_webhook_url' => $stepData['output_webhook_url'] ?? null,
                'condition' => $stepData['condition'] ?? null,
                'pause_before_run' => $stepData['pause_before_run'] ?? false,
            ]);
        }

        return redirect()->route('orchestrator.show', $orchestration->id);
    }

    /**
     * Update an existing orchestration.
     */
    public function update(UpdateOrchestratorRequest $request, Orchestration $orchestration): RedirectResponse
    {
        if ($orchestration->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validated();

        $orchestration->update([
            'name' => $validated['name'] ?? $orchestration->name,
            'description' => $validated['description'] ?? $orchestration->description,
            'goal' => $validated['goal'] ?? $orchestration->goal,
            'is_scheduled' => $validated['is_scheduled'] ?? $orchestration->is_scheduled,
            'cron_expression' => $validated['cron_expression'] ?? $orchestration->cron_expression,
            'timezone' => $validated['timezone'] ?? $orchestration->timezone,
        ]);

        if (isset($validated['steps'])) {
            $orchestration->steps()->delete();

            foreach ($validated['steps'] as $stepData) {
                OrchestratorStep::create([
                    'orchestration_id' => $orchestration->id,
                    'step_number' => $stepData['step_number'],
                    'label' => $stepData['label'] ?? null,
                    'template_id' => $stepData['template_id'] ?? null,
                    'persona_a_id' => $stepData['persona_a_id'] ?? null,
                    'persona_b_id' => $stepData['persona_b_id'] ?? null,
                    'provider_a' => $stepData['provider_a'] ?? null,
                    'model_a' => $stepData['model_a'] ?? null,
                    'provider_b' => $stepData['provider_b'] ?? null,
                    'model_b' => $stepData['model_b'] ?? null,
                    'input_source' => $stepData['input_source'] ?? 'static',
                    'input_value' => $stepData['input_value'] ?? null,
                    'input_variable_name' => $stepData['input_variable_name'] ?? null,
                    'output_action' => $stepData['output_action'] ?? 'log',
                    'output_variable_name' => $stepData['output_variable_name'] ?? null,
                    'output_webhook_url' => $stepData['output_webhook_url'] ?? null,
                    'condition' => $stepData['condition'] ?? null,
                    'pause_before_run' => $stepData['pause_before_run'] ?? false,
                ]);
            }
        }

        return redirect()->route('orchestrator.show', $orchestration->id);
    }

    /**
     * Soft delete the orchestration.
     */
    public function destroy(Request $request, Orchestration $orchestration): RedirectResponse
    {
        if ($orchestration->user_id !== $request->user()->id) {
            abort(403);
        }

        $orchestration->delete();

        return redirect()->route('orchestrator.index');
    }

    /**
     * Manually trigger a run for the orchestration.
     */
    public function run(Request $request, Orchestration $orchestration): RedirectResponse
    {
        if ($orchestration->user_id !== $request->user()->id) {
            abort(403);
        }

        $run = OrchestratorRun::create([
            'orchestration_id' => $orchestration->id,
            'user_id' => $request->user()->id,
            'status' => 'queued',
            'triggered_by' => 'manual',
        ]);

        RunOrchestration::dispatch($run->id);

        return redirect()->route('orchestrator.show', $orchestration->id);
    }

    /**
     * Pause the active run for an orchestration.
     */
    public function pause(Request $request, Orchestration $orchestration): RedirectResponse
    {
        if ($orchestration->user_id !== $request->user()->id) {
            abort(403);
        }

        $activeRun = $orchestration->runs()
            ->whereIn('status', ['queued', 'running'])
            ->latest()
            ->first();

        if ($activeRun) {
            $activeRun->update(['status' => 'paused']);
        }

        return redirect()->route('orchestrator.show', $orchestration->id);
    }

    /**
     * Resume a paused orchestration run.
     */
    public function resume(Request $request, OrchestratorRun $run): RedirectResponse
    {
        if ($run->orchestration->user_id !== $request->user()->id) {
            abort(403);
        }

        \App\Jobs\ResumeOrchestratorRun::dispatch($run->id);

        return redirect()->route('orchestrator.show', $run->orchestration_id);
    }
}
