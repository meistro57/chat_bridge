<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreChatRequest;
use App\Jobs\RunChatSession;
use App\Models\Conversation;
use App\Models\Persona;
use App\Services\AI\TranscriptService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ChatController extends Controller
{
    public function index(): InertiaResponse
    {
        Log::info('ChatController::index loading personas', [
            'user_id' => auth()->id(),
            'persona_count' => Persona::count(),
        ]);

        return Inertia::render('Chat', [
            'personas' => Persona::orderBy('name')->get(),
            'conversations' => auth()->user()->conversations()->latest()->limit(50)->get(),
        ]);
    }

    public function search(Request $request): InertiaResponse
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

    public function create(): InertiaResponse
    {
        return Inertia::render('Chat/Create', [
            'personas' => Persona::orderBy('name')->get(),
        ]);
    }

    public function store(StoreChatRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $personaA = Persona::findOrFail($validated['persona_a_id']);
        $personaB = Persona::findOrFail($validated['persona_b_id']);

        Log::info('Creating new conversation', [
            'user_id' => auth()->id(),
            'persona_a' => $personaA->name,
            'persona_b' => $personaB->name,
            'provider_a' => $validated['provider_a'],
            'provider_b' => $validated['provider_b'],
        ]);

        $conversation = auth()->user()->conversations()->create([
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
            'provider_a' => $validated['provider_a'],
            'provider_b' => $validated['provider_b'],
            'model_a' => $validated['model_a'],
            'model_b' => $validated['model_b'],
            'temp_a' => $validated['temp_a'],
            'temp_b' => $validated['temp_b'],
            'starter_message' => $validated['starter_message'],
            'status' => 'active',
            'max_rounds' => $validated['max_rounds'],
            'stop_word_detection' => $validated['stop_word_detection'] ?? false,
            'stop_words' => $validated['stop_words'] ?? [],
            'stop_word_threshold' => $validated['stop_word_threshold'] ?? 0.8,
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

        dispatch(new RunChatSession($conversation->id, $conversation->max_rounds));

        return redirect()->route('chat.show', $conversation->id);
    }

    public function show(Conversation $conversation): InertiaResponse
    {
        if ($conversation->user_id !== auth()->id()) {
            abort(403);
        }

        return Inertia::render('Chat/Show', [
            'conversation' => $conversation->load([
                'messages' => fn ($query) => $query->orderBy('id'),
                'messages.persona',
            ]),
            'stopSignal' => (bool) Cache::get("conversation.stop.{$conversation->id}"),
        ]);
    }

    public function stop(Conversation $conversation): RedirectResponse
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

    public function destroy(Conversation $conversation): RedirectResponse
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

    public function transcript(Conversation $conversation, TranscriptService $transcripts): BinaryFileResponse
    {
        if ($conversation->user_id !== auth()->id()) {
            abort(403);
        }

        $path = $transcripts->generate($conversation);

        return response()->download(storage_path('app/'.$path));
    }
}
