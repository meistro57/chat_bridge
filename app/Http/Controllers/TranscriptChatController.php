<?php

namespace App\Http\Controllers;

use App\Http\Requests\TranscriptChatRequest;
use App\Models\Conversation;
use App\Services\AI\EmbeddingService;
use App\Services\RagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class TranscriptChatController extends Controller
{
    public function __construct(
        protected RagService $ragService,
        protected EmbeddingService $embeddingService
    ) {}

    public function index(): InertiaResponse
    {
        $conversations = auth()->user()
            ->conversations()
            ->with(['personaA:id,name', 'personaB:id,name'])
            ->latest()
            ->limit(50)
            ->get(['id', 'persona_a_id', 'persona_b_id', 'created_at', 'status']);

        return Inertia::render('Chat/TranscriptChat', [
            'conversations' => $conversations,
        ]);
    }

    public function ask(TranscriptChatRequest $request): JsonResponse
    {
        $question = $request->validated('question');
        $conversationId = $request->validated('conversation_id');

        $context = $this->retrieveRelevantContext($question, $conversationId);

        if ($context->isEmpty()) {
            return response()->json([
                'answer' => 'I could not find any relevant transcript content to answer your question. Try asking something more specific to your chat history.',
                'sources' => [],
            ]);
        }

        $answer = $this->generateAnswer($question, $context);

        $sources = $context->map(fn ($message) => [
            'id' => $message->id,
            'content' => $this->truncate($message->content, 200),
            'role' => $message->role,
            'score' => round($message->similarity_score ?? 0, 3),
            'created_at' => $message->created_at->diffForHumans(),
            'conversation_id' => $message->conversation_id,
            'persona_name' => $message->persona?->name,
        ])->values();

        return response()->json([
            'answer' => $answer,
            'sources' => $sources,
        ]);
    }

    /**
     * Retrieve semantically similar messages from transcripts.
     */
    protected function retrieveRelevantContext(string $question, ?string $conversationId): \Illuminate\Support\Collection
    {
        $filter = ['user_id' => auth()->id()];

        if ($conversationId) {
            $conversation = Conversation::where('id', $conversationId)
                ->where('user_id', auth()->id())
                ->first();

            if ($conversation) {
                $filter['conversation_id'] = $conversationId;
            }
        }

        return $this->ragService->searchSimilarMessages(
            query: $question,
            limit: 6,
            filter: $filter,
            scoreThreshold: 0.65
        );
    }

    /**
     * Generate an AI answer using OpenAI with the retrieved context.
     */
    protected function generateAnswer(string $question, \Illuminate\Support\Collection $context): string
    {
        $apiKey = config('services.openai.key');

        if (empty($apiKey)) {
            return 'OpenAI API key is not configured. Please add your key in Admin → System Settings.';
        }

        $contextText = $context->map(function ($message) {
            $speaker = $message->persona?->name ?? ucfirst($message->role);
            $time = $message->created_at->diffForHumans();

            return "[{$time}] {$speaker}: {$message->content}";
        })->implode("\n\n");

        $systemPrompt = <<<'PROMPT'
You are a helpful assistant that answers questions about AI chat transcript archives.
You have been given relevant excerpts from past conversations retrieved via semantic search.
Use ONLY the provided transcript context to answer the question.
If the context does not contain enough information, say so clearly.
Be concise and accurate. Quote specific parts of the transcript when helpful.
PROMPT;

        $userMessage = <<<MSG
Relevant transcript excerpts:
{$contextText}

---
Question: {$question}
MSG;

        try {
            $response = Http::withToken($apiKey)
                ->timeout(30)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => config('services.openai.model', 'gpt-4o-mini'),
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userMessage],
                    ],
                    'temperature' => 0.3,
                    'max_tokens' => 1024,
                ]);

            if ($response->failed()) {
                Log::error('TranscriptChat OpenAI error', ['response' => $response->body()]);

                return 'An error occurred while generating the answer. Please try again.';
            }

            return $response->json('choices.0.message.content', 'No answer could be generated.');
        } catch (\Exception $e) {
            Log::error('TranscriptChat exception', ['error' => $e->getMessage()]);

            return 'An unexpected error occurred. Please try again.';
        }
    }

    protected function truncate(string $text, int $limit): string
    {
        return mb_strlen($text) > $limit
            ? mb_substr($text, 0, $limit).'…'
            : $text;
    }
}
