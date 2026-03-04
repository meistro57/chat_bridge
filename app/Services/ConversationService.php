<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Persona;
use App\Services\AI\AIManager;
use App\Services\AI\Data\MessageData;
use App\Services\AI\EmbeddingService;
use App\Services\AI\StreamingChunker;
use App\Services\AI\Tools\ToolExecutor;
use App\Services\AI\TranscriptService;
use App\Services\Broadcast\SafeBroadcaster;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ConversationService
{
    public function __construct(
        protected AIManager $ai,
        protected TranscriptService $transcripts,
        protected EmbeddingService $embeddings,
        protected RagService $rag,
        protected ToolExecutor $toolExecutor,
        protected StreamingChunker $streamingChunker
    ) {}

    /**
     * Generate a turn for the given persona based on history.
     * Yields chunks of text.
     * Returns an array with 'content' generator and 'driver' instance.
     *
     * @return array{content: \Generator<string>, driver: \App\Services\AI\Contracts\AIDriverInterface}
     */
    public function generateTurn(Conversation $conversation, Persona $persona, Collection $history): array
    {
        $settings = $conversation->settingsForPersona($persona);
        $driver = $this->ai->driverForProvider($settings['provider'], $settings['model']);

        $messages = $this->buildMessages($conversation, $persona, $history);

        // Check if driver supports tools and tools are enabled
        if ($driver->supportsTools() && config('ai.tools_enabled', true)) {
            // Use agentic loop with tools (non-streaming)
            $result = $this->generateWithTools($driver, $messages, $settings['temperature']);

            // Stream the final tools response in smaller chunks for smoother UI updates.
            $generator = function () use ($result) {
                foreach ($this->streamingChunker->split($result, $this->toolResponseChunkSize()) as $chunk) {
                    yield $chunk;
                }
            };

            return [
                'content' => $generator(),
                'driver' => $driver,
            ];
        }

        // Standard streaming response without tools
        $generator = function () use ($driver, $messages, $settings, $conversation, $persona) {
            $streamYieldedContent = false;
            $streamedContent = '';
            foreach ($driver->streamChat($messages, $settings['temperature']) as $chunk) {
                $streamYieldedContent = true;
                $streamedContent .= $chunk;
                yield $chunk;
            }

            if (! $streamYieldedContent || trim($streamedContent) === '') {
                if ($streamYieldedContent) {
                    Log::warning('Streaming turn content was whitespace-only; using non-stream fallback', [
                        'conversation_id' => $conversation->id,
                        'persona_id' => $persona->id,
                    ]);
                }

                $response = $driver->chat($messages, $settings['temperature']);
                if (trim($response->content) !== '') {
                    yield $response->content;
                }
            }
        };

        return [
            'content' => $generator(),
            'driver' => $driver,
        ];
    }

    protected function toolResponseChunkSize(): int
    {
        $configuredLimit = (int) config('ai.stream_chunk_size', 1500);

        return max(1, min($configuredLimit, 120));
    }

    /**
     * Build message collection with system prompts, guidelines, and history
     */
    protected function buildMessages(Conversation $conversation, Persona $persona, Collection $history): Collection
    {
        $messages = collect();

        // System Prompt
        $messages->push(new MessageData('system', $persona->system_prompt));

        // Add conversation context for multi-turn awareness
        $conversationContext = "IMPORTANT: This is an ongoing multi-turn conversation simulation. You MUST respond to each message.\n\n".
            "Your task:\n".
            "1. Read the most recent message from the other participant\n".
            "2. Provide a substantive response from YOUR professional perspective\n".
            "3. Engage with their points - agree, disagree, add context, or raise new concerns\n".
            "4. Keep the dialogue active and interesting\n\n".
            'Even if the previous message seems complete, find an angle to respond from your expertise. '.
            'Share your thoughts, concerns, alternative approaches, or practical considerations. '.
            "NEVER leave a message unanswered.\n\n".
            'You have access to tools that can search past conversations and retrieve contextual information. '.
            'Use these tools when relevant to provide informed responses based on conversation history.';
        $messages->push(new MessageData('system', $conversationContext));

        // Guidelines
        foreach ($persona->guidelines ?? [] as $guideline) {
            $messages->push(new MessageData('system', "Guideline: $guideline"));
        }

        $ragConfig = $this->conversationRagConfig($conversation);
        $ragEnabled = (bool) ($ragConfig['enabled'] ?? true);

        if ($ragEnabled) {
            $ragSystemPrompt = trim((string) ($ragConfig['system_prompt'] ?? ''));
            if ($ragSystemPrompt !== '') {
                $messages->push(new MessageData('system', "RAG instruction: {$ragSystemPrompt}"));
            }

            $fileContext = $this->templateFileContextMessage($ragConfig);
            if ($fileContext !== null) {
                $messages->push(new MessageData('system', $fileContext));
            }

            $relevantContext = $this->getRelevantContext($persona, $history, $ragConfig);
            if ($relevantContext->isNotEmpty()) {
                $contextMessage = "Relevant context from previous conversations:\n\n";

                foreach ($relevantContext as $contextMsg) {
                    $contextMessage .= "- [{$contextMsg->created_at->diffForHumans()}] {$contextMsg->content}\n";
                }

                $messages->push(new MessageData('system', $contextMessage));

                Log::info('Added RAG context to conversation', [
                    'persona_id' => $persona->id,
                    'context_messages' => $relevantContext->count(),
                ]);
            }
        }

        // History (last 10 messages)
        return $messages->concat($history);
    }

    /**
     * Generate response using agentic tool calling loop
     */
    protected function generateWithTools($driver, Collection $messages, float $temperature): string
    {
        $tools = $this->toolExecutor->getAllTools();
        $maxIterations = config('ai.max_tool_iterations', 5);
        $iteration = 0;

        while ($iteration < $maxIterations) {
            $iteration++;

            Log::info('Tool iteration', [
                'iteration' => $iteration,
                'max' => $maxIterations,
            ]);

            $result = $driver->chatWithTools($messages, $tools, $temperature);

            // If AI returned a text response (no tool calls), we're done
            if ($result['response'] !== null) {
                if (trim($result['response']->content) !== '') {
                    return $result['response']->content;
                }

                Log::warning('Tool-enabled response was empty; requesting retry', [
                    'iteration' => $iteration,
                    'max' => $maxIterations,
                ]);

                $fallbackResponse = $driver->chat($messages, $temperature);
                if (trim($fallbackResponse->content) !== '') {
                    Log::info('Recovered empty tool response via plain chat fallback', [
                        'iteration' => $iteration,
                    ]);

                    return $fallbackResponse->content;
                }

                $messages->push(new MessageData(
                    'system',
                    'Your previous answer was empty. Respond with a complete, substantive reply to the latest message.'
                ));

                continue;
            }

            // AI wants to call tools
            $toolCalls = $result['tool_calls'];
            if (empty($toolCalls)) {
                throw new \Exception('AI returned neither response nor tool calls');
            }

            Log::info('AI requested tool calls', [
                'tool_calls' => array_map(fn ($tc) => $tc['name'], $toolCalls),
            ]);

            // Execute all tool calls
            $toolResults = [];
            foreach ($toolCalls as $toolCall) {
                $toolResult = $this->toolExecutor->execute($toolCall['name'], $toolCall['arguments']);
                $toolResults[] = [
                    'tool_call_id' => $toolCall['id'],
                    'tool_name' => $toolCall['name'],
                    'result' => $toolResult['result'],
                    'error' => $toolResult['error'],
                ];
            }

            // Add tool results back to messages for next iteration
            $toolResultsText = "Tool execution results:\n\n";
            foreach ($toolResults as $tr) {
                $toolResultsText .= "Tool: {$tr['tool_name']}\n";
                if ($tr['error']) {
                    $toolResultsText .= "Error: {$tr['error']}\n";
                } else {
                    $toolResultsText .= 'Result: '.json_encode($tr['result'], JSON_PRETTY_PRINT)."\n";
                }
                $toolResultsText .= "\n";
            }

            $messages->push(new MessageData('system', $toolResultsText));
        }

        // Max iterations reached - ask AI to respond without more tools
        Log::warning('Max tool iterations reached', ['max' => $maxIterations]);
        $finalResponse = trim($driver->chat($messages, $temperature)->content);
        if ($finalResponse !== '') {
            return $finalResponse;
        }

        $fallbackMessage = (string) config(
            'ai.empty_turn_fallback_message',
            'I need to regroup for a moment. Please continue with your strongest next point.'
        );

        Log::warning('Tool-enabled generation remained empty after retries; using fallback message', [
            'max_iterations' => $maxIterations,
        ]);

        return $fallbackMessage;
    }

    /**
     * Save the completed message to the database and generate embedding.
     */
    public function saveTurn(Conversation $conversation, Persona $persona, string $content, ?int $tokensUsed = null): Message
    {
        $message = $conversation->messages()->create([
            'persona_id' => $persona->id,
            'role' => 'assistant',
            'content' => $content,
            'tokens_used' => $tokensUsed,
        ]);

        // Generate embedding asynchronously (or inline if queue driver is sync)
        try {
            $vector = $this->embeddings->getEmbedding($content);
            $message->update(['embedding' => $vector]);

            // Store in Qdrant for RAG if available and enabled
            if (config('services.qdrant.enabled', false) && $this->rag->isAvailable()) {
                $message->refresh(); // Ensure we have the latest embedding
                $this->rag->storeMessage($message);
            }
        } catch (\Exception $e) {
            Log::warning("Embedding generation failed for message {$message->id}: ".$e->getMessage());
        }

        return $message;
    }

    /**
     * Get relevant context from previous conversations using RAG
     */
    protected function getRelevantContext(Persona $persona, Collection $history, array $ragConfig): Collection
    {
        if ((bool) ($ragConfig['enabled'] ?? true) === false) {
            return collect();
        }

        $conversationId = (string) ($ragConfig['conversation_id'] ?? '');
        if ($conversationId === '') {
            return collect();
        }

        $ragSourceLimit = (int) ($ragConfig['source_limit'] ?? 6);
        $ragSourceLimit = max(1, min(20, $ragSourceLimit));
        $ragScoreThreshold = (float) ($ragConfig['score_threshold'] ?? 0.3);
        $ragScoreThreshold = max(0.0, min(1.0, $ragScoreThreshold));
        $ragUserId = (int) ($ragConfig['user_id'] ?? 0);

        $lastMessage = $history->last();

        if (! $lastMessage || empty($lastMessage->content)) {
            return collect();
        }

        $relevantMessages = $this->rag->searchSimilarMessages(
            query: $lastMessage->content,
            limit: $ragSourceLimit,
            filter: array_filter([
                'persona_id' => $persona->id,
                'user_id' => $ragUserId > 0 ? $ragUserId : null,
            ], fn ($value) => $value !== null),
            scoreThreshold: $ragScoreThreshold
        );

        return $relevantMessages
            ->reject(fn ($message) => (string) $message->conversation_id === $conversationId)
            ->values();
    }

    protected function conversationRagConfig(Conversation $conversation): array
    {
        $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
        $ragConfig = is_array($metadata['rag'] ?? null) ? $metadata['rag'] : [];

        return [
            ...$ragConfig,
            'conversation_id' => $conversation->id,
            'user_id' => $conversation->user_id,
        ];
    }

    protected function templateFileContextMessage(array $ragConfig): ?string
    {
        $paths = collect($ragConfig['files'] ?? [])
            ->filter(fn ($path) => is_string($path) && trim($path) !== '')
            ->values();

        if ($paths->isEmpty()) {
            return null;
        }

        $userId = (int) ($ragConfig['user_id'] ?? 0);
        if ($userId <= 0) {
            return null;
        }

        $maxFiles = max(1, min(10, (int) config('ai.rag_template_max_files', 3)));
        $paths = $paths->take($maxFiles);
        $maxChars = max(600, (int) config('ai.rag_template_max_chars', 3000));
        $charsPerFile = max(200, (int) floor($maxChars / max(1, $paths->count())));
        $prefix = "template-rag/{$userId}/";
        $snippets = [];

        foreach ($paths as $path) {
            if (! str_starts_with($path, $prefix) || ! Storage::disk('local')->exists($path)) {
                continue;
            }

            $snippet = $this->readTemplateFileSnippet($path, $charsPerFile);
            if ($snippet === null) {
                continue;
            }

            $snippets[] = 'File: '.basename($path)."\n".$snippet;
        }

        if ($snippets === []) {
            return null;
        }

        return "Relevant template file excerpts:\n\n".implode("\n\n---\n\n", $snippets);
    }

    protected function readTemplateFileSnippet(string $path, int $maxChars): ?string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (! in_array($extension, ['txt', 'md', 'csv', 'json'], true)) {
            return null;
        }

        $content = trim((string) Storage::disk('local')->get($path));
        if ($content === '') {
            return null;
        }

        return Str::limit($content, $maxChars, '...');
    }

    /**
     * Finalize the conversation
     */
    public function completeConversation(Conversation $conversation): void
    {
        Log::info('Completing conversation', [
            'conversation_id' => $conversation->id,
            'total_messages' => $conversation->messages()->count(),
            'previous_status' => $conversation->status,
        ]);

        $conversation->update(['status' => 'completed']);
        $this->transcripts->generate($conversation);

        app(SafeBroadcaster::class)->broadcast(
            new \App\Events\ConversationStatusUpdated($conversation),
            [
                'conversation_id' => $conversation->id,
                'phase' => 'status',
            ]
        );
    }
}
