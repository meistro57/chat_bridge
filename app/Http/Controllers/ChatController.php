<?php

namespace App\Http\Controllers;

use App\Jobs\RunChatSession;
use App\Models\Conversation;
use App\Models\Persona;
use App\Services\AI\TranscriptService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class ChatController extends Controller
{
    public function index()
    {
        Log::info('ChatController::index loading personas', [
            'user_id' => auth()->id(),
            'persona_count' => Persona::count()
        ]);
        return Inertia::render('Chat', [
            'personas' => Persona::orderBy('name')->get(),
            'conversations' => auth()->user()->conversations()->latest()->limit(50)->get(),
        ]);
    }

    public function search(Request $request)
    {
        $query = $request->query('q');
        $messages = [];

        if ($query) {
            $messages = \App\Models\Message::whereHas('conversation', function ($q) {
                $q->where('user_id', auth()->id());
            })
                ->where('content', 'like', "%{$query}%")
                ->with(['conversation', 'persona'])
                ->latest()
                ->limit(20)
                ->get();
        }

        return Inertia::render('Chat/Search', [
            'results' => $messages,
            'query' => $query,
        ]);
    }

    public function create()
    {
        return Inertia::render('Chat/Create', [
            'personas' => Persona::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'persona_a_id' => 'required|exists:personas,id',
            'persona_b_id' => 'required|exists:personas,id',
            'starter_message' => 'required|string',
        ]);

        $personaA = Persona::findOrFail($validated['persona_a_id']);
        $personaB = Persona::findOrFail($validated['persona_b_id']);

        Log::info('Creating new conversation', [
            'user_id' => auth()->id(),
            'persona_a' => $personaA->name,
            'persona_b' => $personaB->name,
            'provider_a' => $personaA->provider,
            'provider_b' => $personaB->provider,
        ]);

        $conversation = auth()->user()->conversations()->create([
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
            'user_id' => auth()->id(),
            'role' => 'user',
            'content' => $validated['starter_message'],
        ]);

        Log::info('Conversation created successfully', [
            'conversation_id' => $conversation->id,
            'starter_message_length' => strlen($validated['starter_message']),
        ]);

        dispatch(new RunChatSession($conversation->id));

        return redirect()->route('chat.show', $conversation->id);
    }

    public function show(Conversation $conversation)
    {
        if ($conversation->user_id !== auth()->id()) {
            abort(403);
        }

        return Inertia::render('Chat/Show', [
            'conversation' => $conversation->load('messages.persona'),
            'stopSignal' => (bool) Cache::get("conversation.stop.{$conversation->id}"),
        ]);
    }

    public function stop(Conversation $conversation)
    {
        if ($conversation->user_id !== auth()->id()) {
            abort(403);
        }

        Log::info('User requested conversation stop', [
            'conversation_id' => $conversation->id,
            'user_id' => auth()->id(),
            'message_count' => $conversation->messages()->count(),
        ]);

        Cache::put("conversation.stop.{$conversation->id}", true, now()->addHour());

        return back()->with('success', 'Stop signal sent.');
    }

    public function destroy(Conversation $conversation)
    {
        if ($conversation->user_id !== auth()->id()) {
            abort(403);
        }

        Log::info('Deleting conversation', [
            'conversation_id' => $conversation->id,
            'user_id' => auth()->id(),
            'message_count' => $conversation->messages()->count(),
            'status' => $conversation->status,
        ]);

        $conversation->delete();

        return redirect()->route('chat.index')->with('success', 'Conversation deleted.');
    }

    public function transcript(Conversation $conversation, TranscriptService $transcripts)
    {
        if ($conversation->user_id !== auth()->id()) {
            abort(403);
        }

        $path = $transcripts->generate($conversation);

        return response()->download(storage_path('app/'.$path));
    }
}
