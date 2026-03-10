<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\ProviderController;
use App\Http\Requests\StoreChatRequest;
use App\Jobs\RunChatSession;
use App\Models\Conversation;
use App\Models\ConversationTemplate;
use App\Models\Persona;
use App\Services\AI\TranscriptService;
use App\Services\Conversations\ActiveConversationRecoveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatController extends Controller
{
    public function liveStatus(Request $request): JsonResponse
    {
        $activeConversations = Conversation::query()
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->with(['personaA:id,name', 'personaB:id,name'])
            ->withCount([
                'messages as assistant_turns_count' => fn ($query) => $query->where('role', 'assistant'),
                'messages as messages_count',
            ])
            ->latest('updated_at')
            ->limit(6)
            ->get([
                'id',
                'persona_a_id',
                'persona_b_id',
                'max_rounds',
                'updated_at',
            ]);

        $items = $activeConversations->map(function (Conversation $conversation): array {
            $assistantTurns = (int) ($conversation->assistant_turns_count ?? 0);
            $maxRounds = max(1, (int) ($conversation->max_rounds ?? 1));
            $currentTurn = min($assistantTurns + 1, $maxRounds);
            $stopRequested = $this->resolveStopRequested($conversation);
            $this->maybeKickstartStaleConversation($conversation, $assistantTurns, $stopRequested);
            $personaAName = $conversation->personaA?->name ?? 'Agent A';
            $personaBName = $conversation->personaB?->name ?? 'Agent B';

            return [
                'id' => (string) $conversation->id,
                'label' => "{$personaAName} vs {$personaBName}",
                'current_turn' => $currentTurn,
                'max_rounds' => $maxRounds,
                'assistant_turns' => $assistantTurns,
                'messages_count' => (int) ($conversation->messages_count ?? 0),
                'stop_requested' => $stopRequested,
                'updated_at' => $conversation->updated_at?->toIso8601String(),
                'updated_at_human' => $conversation->updated_at?->diffForHumans(),
            ];
        })->values();

        return response()->json([
            'active_count' => $items->count(),
            'items' => $items,
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    protected function resolveStopRequested(Conversation $conversation): bool
    {
        return app(ActiveConversationRecoveryService::class)->resolveStopRequested($conversation);
    }

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

    public function create(Request $request): InertiaResponse
    {
        $template = null;
        $openRouterModels = [];

        if ($request->filled('template')) {
            $template = ConversationTemplate::query()
                ->where('id', $request->input('template'))
                ->where(function ($query) use ($request) {
                    $query->where('is_public', true)
                        ->orWhere('user_id', $request->user()->id);
                })
                ->with(['personaA:id,name', 'personaB:id,name'])
                ->firstOrFail();
        }

        if (! app()->environment('testing')) {
            $cacheKey = 'provider_models.openrouter';
            $cachedModels = Cache::get($cacheKey);

            if (is_array($cachedModels) && $cachedModels !== []) {
                $openRouterModels = $cachedModels;
            } else {
                try {
                    $fetchedModels = app(ProviderController::class)->modelsForProvider('openrouter');
                    if ($fetchedModels !== []) {
                        Cache::put($cacheKey, $fetchedModels, now()->addMinutes(10));
                    }
                    $openRouterModels = $fetchedModels;
                } catch (\Throwable $exception) {
                    Log::warning('Failed to preload OpenRouter models for create page', [
                        'error' => $exception->getMessage(),
                    ]);

                    Cache::forget($cacheKey);
                    $openRouterModels = [];
                }
            }
        }

        return Inertia::render('Chat/Create', [
            'personas' => Persona::orderBy('name')->get(),
            'template' => $template,
            'openRouterModels' => $openRouterModels,
            'mcpEnabled' => (bool) config('ai.tools_enabled', true),
            'discordDefaults' => [
                'enabled' => (bool) $request->user()->discord_streaming_default,
                'webhook_url' => $request->user()->discord_webhook_url,
            ],
            'discourseDefaults' => [
                'enabled' => (bool) $request->user()->discourse_streaming_default,
            ],
        ]);
    }

    public function store(StoreChatRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $selectedTemplate = null;

        if (! empty($validated['template_id'])) {
            $selectedTemplate = ConversationTemplate::query()
                ->where('id', $validated['template_id'])
                ->where(function ($query) use ($request) {
                    $query->where('is_public', true)
                        ->orWhere('user_id', $request->user()->id);
                })
                ->first();
        }

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
            'temp_a' => 1.0,
            'temp_b' => 1.0,
            'starter_message' => $validated['starter_message'],
            'status' => 'active',
            'max_rounds' => $validated['max_rounds'],
            'stop_word_detection' => $validated['stop_word_detection'] ?? false,
            'stop_words' => $validated['stop_words'] ?? [],
            'stop_word_threshold' => $validated['stop_word_threshold'] ?? 0.8,
            'metadata' => [
                'persona_a_name' => $personaA->name,
                'persona_b_name' => $personaB->name,
                'notifications_enabled' => $request->boolean('notifications_enabled', false),
                'template_id' => $selectedTemplate?->id,
                'memory' => [
                    'history_limit' => (int) ($validated['memory_history_limit'] ?? 10),
                ],
                'rag' => [
                    'enabled' => (bool) ($validated['memory_rag_enabled'] ?? ($selectedTemplate?->rag_enabled ?? true)),
                    'source_limit' => (int) ($validated['memory_rag_source_limit'] ?? ($selectedTemplate?->rag_source_limit ?? 6)),
                    'score_threshold' => (float) ($validated['memory_rag_score_threshold'] ?? ($selectedTemplate?->rag_score_threshold ?? 0.3)),
                    'system_prompt' => (string) ($selectedTemplate?->rag_system_prompt ?? ''),
                    'files' => $selectedTemplate?->rag_files ?? [],
                ],
            ],
            'discord_streaming_enabled' => $request->has('discord_streaming_enabled')
                ? $request->boolean('discord_streaming_enabled')
                : (bool) auth()->user()->discord_streaming_default,
            'discord_webhook_url' => $validated['discord_webhook_url'] ?? null,
            'discourse_streaming_enabled' => $request->has('discourse_streaming_enabled')
                ? $request->boolean('discourse_streaming_enabled')
                : (bool) auth()->user()->discourse_streaming_default,
            'discourse_topic_id' => $validated['discourse_topic_id'] ?? null,
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

        $assistantTurns = (int) $conversation->messages()
            ->where('role', 'assistant')
            ->count();
        $stopSignal = $this->resolveStopRequested($conversation);
        $this->maybeKickstartStaleConversation($conversation, $assistantTurns, $stopSignal);

        return Inertia::render('Chat/Show', [
            'conversation' => $conversation->load([
                'messages' => fn ($query) => $query->orderBy('id'),
                'messages.persona',
                'personaA',
                'personaB',
            ]),
            'stopSignal' => $stopSignal,
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

    public function resume(Conversation $conversation): RedirectResponse
    {
        if ($conversation->user_id !== auth()->id()) {
            abort(403);
        }

        if ($conversation->status !== 'failed') {
            return back()->with('error', 'Only failed conversations can be resumed.');
        }

        $assistantTurns = (int) $conversation->messages()
            ->where('role', 'assistant')
            ->count();
        $remainingRounds = $conversation->max_rounds - $assistantTurns;

        if ($remainingRounds <= 0) {
            return back()->with('error', 'No remaining rounds available to resume.');
        }

        $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
        $metadata['resumed_at'] = now()->toIso8601String();
        $metadata['resume_attempts'] = (int) ($metadata['resume_attempts'] ?? 0) + 1;

        $conversation->update([
            'status' => 'active',
            'metadata' => $metadata,
        ]);

        Cache::forget("conversation.stop.{$conversation->id}");
        dispatch(new RunChatSession($conversation->id, $remainingRounds));

        Log::info('Conversation resumed', [
            'conversation_id' => $conversation->id,
            'user_id' => auth()->id(),
            'assistant_turns' => $assistantTurns,
            'remaining_rounds' => $remainingRounds,
        ]);

        return back()->with('success', 'Conversation resumed.');
    }

    public function retryWith(Request $request, Conversation $conversation): RedirectResponse
    {
        if ($conversation->user_id !== auth()->id()) {
            abort(403);
        }

        if ($conversation->status !== 'failed') {
            return back()->with('error', 'Only failed conversations can be retried.');
        }

        $validated = $request->validate([
            'provider_a' => 'nullable|string|max:50',
            'model_a' => 'nullable|string|max:200',
            'provider_b' => 'nullable|string|max:50',
            'model_b' => 'nullable|string|max:200',
        ]);

        $updates = array_filter([
            'provider_a' => $validated['provider_a'] ?? null,
            'model_a' => $validated['model_a'] ?? null,
            'provider_b' => $validated['provider_b'] ?? null,
            'model_b' => $validated['model_b'] ?? null,
        ]);

        $assistantTurns = (int) $conversation->messages()
            ->where('role', 'assistant')
            ->count();
        $remainingRounds = $conversation->max_rounds - $assistantTurns;

        if ($remainingRounds <= 0) {
            return back()->with('error', 'No remaining rounds available to retry.');
        }

        $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
        $metadata['resumed_at'] = now()->toIso8601String();
        $metadata['resume_attempts'] = (int) ($metadata['resume_attempts'] ?? 0) + 1;
        $metadata['retry_model_change'] = $updates;

        $conversation->update(array_merge($updates, [
            'status' => 'active',
            'metadata' => $metadata,
        ]));

        Cache::forget("conversation.stop.{$conversation->id}");
        dispatch(new RunChatSession($conversation->id, $remainingRounds));

        Log::info('Conversation retried with updated models', [
            'conversation_id' => $conversation->id,
            'user_id' => auth()->id(),
            'updates' => $updates,
            'remaining_rounds' => $remainingRounds,
        ]);

        return back()->with('success', 'Conversation restarted with updated settings.');
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

    public function transcript(Conversation $conversation, TranscriptService $transcripts): StreamedResponse
    {
        if ($conversation->user_id !== auth()->id()) {
            abort(403);
        }

        $path = $transcripts->generate($conversation);

        return Storage::disk('local')->download($path, basename($path), [
            'Content-Type' => 'text/markdown; charset=utf-8',
        ]);
    }

    protected function maybeKickstartStaleConversation(Conversation $conversation, int $assistantTurns, bool $stopRequested): void
    {
        app(ActiveConversationRecoveryService::class)->maybeKickstartConversation(
            $conversation,
            $assistantTurns,
            $stopRequested,
            'chat-controller'
        );
    }
}
