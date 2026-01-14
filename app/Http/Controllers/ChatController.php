<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Persona;
use App\Jobs\ProcessConversationTurn;
use Illuminate\Http\Request;
use App\Services\AI\TranscriptService;
use Inertia\Inertia;

class ChatController extends Controller
{
    public function index()
    {
        return Inertia::render('Chat', [
            'personas' => Persona::all(),
            'conversations' => Conversation::latest()->limit(10)->get(),
        ]);
    }

    public function create()
    {
        return Inertia::render('Chat/Create', [
            'personas' => Persona::all(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'persona_a_id' => 'required|exists:personas,id',
            'persona_b_id' => 'required|exists:personas,id',
            'starter_message' => 'required|string',
        ]);

        $personaA = Persona::find($validated['persona_a_id']);
        $personaB = Persona::find($validated['persona_b_id']);

        $conversation = Conversation::create([
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
            'provider_a' => $personaA->provider,
            'provider_b' => $personaB->provider,
            'model_a' => $personaA->model,
            'model_b' => $personaB->model,
            'temp_a' => $personaA->temperature,
            'temp_b' => $personaB->temperature,
            'starter_message' => $validated['starter_message'],
            'status' => 'active',
            'metadata' => [
                'persona_a_name' => $personaA->name,
                'persona_b_name' => $personaB->name,
            ],
        ]);

        $conversation->messages()->create([
            'role' => 'user',
            'content' => $validated['starter_message'],
        ]);

        dispatch(new ProcessConversationTurn($conversation->id));

        return redirect()->route('chat.show', $conversation->id);
    }

    public function show(Conversation $conversation)
    {
        return Inertia::render('Chat/Show', [
            'conversation' => $conversation->load('messages.persona'),
        ]);
    }

    public function transcript(Conversation $conversation, TranscriptService $transcripts)
    {
        $path = $transcripts->generate($conversation);
        return response()->download(storage_path('app/' . $path));
    }
}
