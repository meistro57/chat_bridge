<?php

namespace App\Http\Controllers;

use App\Http\Requests\WizardChatRequest;
use App\Services\Orchestrator\OrchestratorWizardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class OrchestratorWizardController extends Controller
{
    public function __construct(
        protected OrchestratorWizardService $wizardService
    ) {}

    /**
     * Show the wizard UI.
     */
    public function show(): InertiaResponse
    {
        return Inertia::render('Orchestrator/Wizard');
    }

    /**
     * Send a wizard message and receive an AI reply.
     */
    public function chat(WizardChatRequest $request): JsonResponse
    {
        $result = $this->wizardService->chat(
            user: $request->user(),
            history: $request->input('history', []),
            userMessage: $request->input('message')
        );

        return response()->json($result);
    }

    /**
     * Materialize a wizard draft into an orchestration.
     */
    public function materialize(Request $request): RedirectResponse
    {
        $request->validate([
            'draft' => ['required', 'array'],
            'draft.name' => ['required', 'string'],
            'draft.steps' => ['required', 'array', 'min:1'],
        ]);

        $orchestration = $this->wizardService->materialize(
            user: $request->user(),
            draft: $request->input('draft')
        );

        return redirect()->route('orchestrator.show', $orchestration->id);
    }
}
