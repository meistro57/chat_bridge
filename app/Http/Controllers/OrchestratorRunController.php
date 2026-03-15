<?php

namespace App\Http\Controllers;

use App\Models\Orchestration;
use App\Models\OrchestratorRun;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class OrchestratorRunController extends Controller
{
    /**
     * List all runs for an orchestration.
     */
    public function index(Request $request, Orchestration $orchestration): InertiaResponse
    {
        if ($orchestration->user_id !== $request->user()->id) {
            abort(403);
        }

        $runs = $orchestration->runs()
            ->with('stepRuns.step')
            ->latest()
            ->paginate(20);

        return Inertia::render('Orchestrator/Runs/Index', [
            'orchestration' => $orchestration,
            'runs' => $runs,
        ]);
    }

    /**
     * Show a single run with all step run details.
     */
    public function show(Request $request, OrchestratorRun $run): InertiaResponse
    {
        if ($run->orchestration->user_id !== $request->user()->id) {
            abort(403);
        }

        $run->load([
            'orchestration',
            'stepRuns' => fn ($query) => $query->with(['step', 'conversation']),
        ]);

        return Inertia::render('Orchestrator/Runs/Show', [
            'run' => $run,
        ]);
    }
}
