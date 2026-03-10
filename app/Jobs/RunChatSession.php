<?php

namespace App\Jobs;

use App\Events\MessageChunkSent;
use App\Events\MessageCompleted;
use App\Models\Conversation;
use App\Models\Persona;
use App\Notifications\ConversationCompletedNotification;
use App\Notifications\ConversationFailedNotification;
use App\Services\AI\Data\MessageData;
use App\Services\AI\StopWordService;
use App\Services\AI\StreamingChunker;
use App\Services\Broadcast\SafeBroadcaster;
use App\Services\ConversationService;
use App\Services\Discord\DiscordStreamer;
use App\Services\Discourse\DiscourseStreamer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RunChatSession implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     * Set to 20 minutes for long conversations.
     *
     * @var int
     */
    public $timeout = 1200;

    public function __construct(
        public string $conversationId,
        public int $maxRounds = 20
    ) {}

    /**
     * Prevent overlapping workers from processing the same conversation.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("run-chat-session:{$this->conversationId}"))
                ->expireAfter($this->timeout + 60)
                ->dontRelease(),
        ];
    }

    public function handle(
        ConversationService $service,
        StopWordService $stopWords,
        DiscordStreamer $discordStreamer
    ): void {
        if (config('safety.read_only_mode', false)) {
            Log::warning('Skipping chat session in read-only mode', [
                'conversation_id' => $this->conversationId,
            ]);

            return;
        }

        $conversation = Conversation::with(['messages.persona', 'personaA', 'personaB'])->findOrFail($this->conversationId);

        if ($conversation->status !== 'active') {
            Log::info('Skipping conversation - not active', [
                'conversation_id' => $this->conversationId,
                'status' => $conversation->status,
            ]);

            return;
        }

        $maxRounds = $this->maxRounds > 0 ? $this->maxRounds : $conversation->max_rounds;

        Log::info('Starting chat session', [
            'conversation_id' => $this->conversationId,
            'max_rounds' => $maxRounds,
            'persona_a' => $conversation->personaA->name,
            'persona_b' => $conversation->personaB->name,
        ]);

        $discourseStreamer = app(DiscourseStreamer::class);

        if ($conversation->discord_thread_id === null) {
            $discordStreamer->startConversation($conversation);
        }
        if ($conversation->discourse_topic_id === null) {
            $discourseStreamer->startConversation($conversation);
        }

        $round = 0;
        $startTime = microtime(true);

        try {
            // Loop until max rounds or stopped
            while ($round < $maxRounds) {
                // 1. Check for manual stop signal
                if (Cache::get("conversation.stop.{$this->conversationId}")) {
                    Log::info("Conversation {$this->conversationId} stopped by user signal.");
                    $service->completeConversation($conversation);
                    break;
                }

                // 2. Identify Current Speaker
                $lastMessage = $conversation->messages()
                    ->where('role', 'assistant')
                    ->latest()
                    ->first();

                $currentPersona = (! $lastMessage || $lastMessage->persona_id === $conversation->personaB->id)
                    ? $conversation->personaA
                    : $conversation->personaB;
                $historyLimit = $this->historyLimitForConversation($conversation);

                // 3. Prepare History with persona names
                $history = $conversation->messages()
                    ->with('persona')
                    ->latest()
                    ->take($historyLimit)
                    ->get()
                    ->sortBy('id')
                    ->map(fn ($m) => new MessageData(
                        $m->role,
                        $m->content,
                        $m->role === 'assistant' ? $m->persona?->name : null
                    ));

                // 4. Generate & Stream
                $fullResponse = '';
                // Yield chunks from service
                $chunker = app(StreamingChunker::class);
                $broadcaster = app(SafeBroadcaster::class);
                $maxChunkSize = (int) config('ai.stream_chunk_size', 1500);
                $initialStreamEnabled = (bool) config('ai.initial_stream_enabled', true);
                $initialStreamChunk = (string) config('ai.initial_stream_chunk', '');
                $interTurnDelayMs = max(0, (int) config('ai.inter_turn_delay_ms', 250));
                $maxEmptyTurnRetries = max(0, (int) config('ai.empty_turn_retry_attempts', 1));
                $emptyTurnRetryDelayMs = max(0, (int) config('ai.empty_turn_retry_delay_ms', 350));
                $maxTurnExceptionRetries = max(0, (int) config('ai.turn_exception_retry_attempts', 2));
                $turnExceptionRetryDelayMs = max(0, (int) config('ai.turn_exception_retry_delay_ms', 1000));
                $maxTurnRescueAttempts = max(0, (int) config('ai.turn_rescue_attempts', 1));
                $chunkCount = 0;
                $driver = null;
                $emptyRetryAttempt = 0;
                $exceptionRetryAttempt = 0;
                $retryableExceptionAfterRetries = null;

                while (true) {
                    $fullResponse = '';
                    $chunkCount = 0;
                    $pendingChunkBuffer = '';
                    try {
                        $generation = $service->generateTurn($conversation, $currentPersona, $history);
                        $driver = $generation['driver'];

                        if ($initialStreamEnabled) {
                            $chunkCount++;
                            $broadcaster->broadcast(
                                new MessageChunkSent(
                                    conversationId: $conversation->id,
                                    chunk: $initialStreamChunk,
                                    role: 'assistant',
                                    personaName: $currentPersona->name
                                ),
                                [
                                    'conversation_id' => $conversation->id,
                                    'phase' => 'chunk',
                                ]
                            );
                        }

                        foreach ($generation['content'] as $chunk) {
                            $fullResponse .= $chunk;
                            $pendingChunkBuffer .= $chunk;

                            $pendingPieces = $chunker->split($pendingChunkBuffer, $maxChunkSize);
                            $pendingChunkBuffer = (string) array_pop($pendingPieces);

                            foreach ($pendingPieces as $piece) {
                                $chunkCount++;
                                $broadcaster->broadcast(
                                    new MessageChunkSent(
                                        conversationId: $conversation->id,
                                        chunk: $piece,
                                        role: 'assistant',
                                        personaName: $currentPersona->name
                                    ),
                                    [
                                        'conversation_id' => $conversation->id,
                                        'phase' => 'chunk',
                                    ]
                                );
                            }

                            if (Cache::get("conversation.stop.{$this->conversationId}")) {
                                break;
                            }
                        }

                        if ($pendingChunkBuffer !== '') {
                            $chunkCount++;
                            $broadcaster->broadcast(
                                new MessageChunkSent(
                                    conversationId: $conversation->id,
                                    chunk: $pendingChunkBuffer,
                                    role: 'assistant',
                                    personaName: $currentPersona->name
                                ),
                                [
                                    'conversation_id' => $conversation->id,
                                    'phase' => 'chunk',
                                ]
                            );
                        }
                    } catch (\Throwable $exception) {
                        if ($this->isRetryableTurnException($exception)) {
                            if ($exceptionRetryAttempt < $maxTurnExceptionRetries) {
                                $exceptionRetryAttempt++;
                                Log::warning('Turn failed with retryable exception, retrying', [
                                    'conversation_id' => $this->conversationId,
                                    'round' => $round + 1,
                                    'persona' => $currentPersona->name,
                                    'retry_attempt' => $exceptionRetryAttempt,
                                    'max_retries' => $maxTurnExceptionRetries,
                                    'error' => $exception->getMessage(),
                                ]);

                                usleep($turnExceptionRetryDelayMs * 1000);

                                continue;
                            }

                            $retryableExceptionAfterRetries = $exception;

                            Log::warning('Turn failed with retryable exception after retries; attempting rescue turn', [
                                'conversation_id' => $this->conversationId,
                                'round' => $round + 1,
                                'persona' => $currentPersona->name,
                                'retry_attempts' => $exceptionRetryAttempt,
                                'max_retries' => $maxTurnExceptionRetries,
                                'error' => $exception->getMessage(),
                            ]);

                            break;
                        }

                        throw $exception;
                    }

                    if (trim($fullResponse) !== '') {
                        break;
                    }

                    if ($emptyRetryAttempt < $maxEmptyTurnRetries) {
                        $emptyRetryAttempt++;
                        Log::warning('Turn produced empty response, retrying', [
                            'conversation_id' => $this->conversationId,
                            'round' => $round + 1,
                            'persona' => $currentPersona->name,
                            'retry_attempt' => $emptyRetryAttempt,
                            'max_retries' => $maxEmptyTurnRetries,
                        ]);

                        usleep($emptyTurnRetryDelayMs * 1000);

                        continue;
                    }

                    break;
                }

                if (trim($fullResponse) === '' && $maxTurnRescueAttempts > 0) {
                    for ($rescueAttempt = 1; $rescueAttempt <= $maxTurnRescueAttempts; $rescueAttempt++) {
                        $rescueResult = $this->attemptRescueTurn(
                            $service,
                            $conversation,
                            $currentPersona,
                            $history,
                            $round + 1,
                            $rescueAttempt,
                            $maxTurnRescueAttempts
                        );

                        if (trim($rescueResult['content']) !== '') {
                            $fullResponse = $rescueResult['content'];
                            $driver = $rescueResult['driver'] ?? $driver;

                            break;
                        }
                    }
                }

                if (trim($fullResponse) === '') {
                    $failureContext = [
                        'code' => 'empty_turn_exhausted',
                        'conversation_id' => (string) $this->conversationId,
                        'round' => $round + 1,
                        'persona' => $currentPersona->name,
                        'provider' => $this->providerForPersona($conversation, $currentPersona),
                        'model' => $this->modelForPersona($conversation, $currentPersona),
                        'chunk_count' => $chunkCount,
                        'empty_retry_attempts' => $emptyRetryAttempt,
                        'max_empty_retries' => $maxEmptyTurnRetries,
                        'exception_retry_attempts' => $exceptionRetryAttempt,
                        'max_exception_retries' => $maxTurnExceptionRetries,
                        'retryable_exception' => $retryableExceptionAfterRetries?->getMessage(),
                        'rescue_attempts' => $maxTurnRescueAttempts,
                    ];

                    Log::error('Turn produced empty response after retries; failing conversation', $failureContext);

                    $errorMessage = sprintf(
                        'Turn failed after retries: empty response from %s/%s (round %d, persona %s).',
                        (string) ($failureContext['provider'] ?? 'unknown'),
                        (string) ($failureContext['model'] ?? 'unknown'),
                        $round + 1,
                        $currentPersona->name
                    );

                    throw $this->buildGenerationFailure($errorMessage, $failureContext);
                }

                // 5. Save & Finalize Turn
                $tokensUsed = $driver?->getLastTokenUsage();
                $message = $service->saveTurn($conversation, $currentPersona, $fullResponse, $tokensUsed);
                // Touch the conversation so the watchdog sees it as recently active
                $conversation->touch();
                $broadcaster->broadcast(
                    new MessageCompleted($message),
                    [
                        'conversation_id' => $conversation->id,
                        'phase' => 'completed',
                    ]
                );

                Log::info('Turn completed', [
                    'conversation_id' => $this->conversationId,
                    'round' => $round + 1,
                    'persona' => $currentPersona->name,
                    'message_length' => strlen($fullResponse),
                    'tokens_used' => $message->tokens_used ?? 0,
                    'chunk_count' => $chunkCount,
                ]);

                $discordStreamer->postMessage($conversation, $message, $round + 1);
                $discourseStreamer->postMessage($conversation, $message, $round + 1);

                // 6. Check Stop Words
                if ($conversation->stop_word_detection && $stopWords->shouldStopWithThreshold(
                    $fullResponse,
                    $conversation->stop_words ?? [],
                    (float) $conversation->stop_word_threshold
                )) {
                    Log::info("Conversation {$this->conversationId} stopped by stop word.");
                    $service->completeConversation($conversation);
                    break;
                }

                $round++;
                usleep($interTurnDelayMs * 1000);
            }

            if ($round >= $maxRounds && $conversation->fresh()->status === 'active') {
                Log::info('Conversation reached max rounds', [
                    'conversation_id' => $this->conversationId,
                    'rounds' => $round,
                ]);
                $service->completeConversation($conversation);
            }

            $duration = microtime(true) - $startTime;
            $totalMessages = $conversation->messages()->count();

            Log::info('Chat session completed', [
                'conversation_id' => $this->conversationId,
                'total_rounds' => $round,
                'duration_seconds' => round($duration, 2),
                'total_messages' => $totalMessages,
            ]);

            $discordStreamer->conversationCompleted($conversation, $totalMessages, $round, $duration);
            $discourseStreamer->conversationCompleted($conversation, $totalMessages, $round, $duration);
            $this->notifyCompletion($conversation, $totalMessages, $round);
        } catch (\Throwable $e) {
            Log::error("Job failed for conversation {$this->conversationId}", [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'round' => $round,
            ]);
            $this->markConversationFailed($conversation, $e->getMessage(), $this->extractFailureContext($e));
            app(SafeBroadcaster::class)->broadcast(
                new \App\Events\ConversationStatusUpdated($conversation),
                [
                    'conversation_id' => $conversation->id,
                    'phase' => 'status',
                ]
            );

            $this->notifyFailure($conversation, $e->getMessage());
            $discordStreamer->conversationFailed($conversation, $e->getMessage());
            $discourseStreamer->conversationFailed($conversation, $e->getMessage());

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $conversation = Conversation::find($this->conversationId);
        if ($conversation) {
            $this->markConversationFailed($conversation, $exception->getMessage(), $this->extractFailureContext($exception));
            app(SafeBroadcaster::class)->broadcast(
                new \App\Events\ConversationStatusUpdated($conversation),
                [
                    'conversation_id' => $conversation->id,
                    'phase' => 'status',
                ]
            );

            $this->notifyFailure($conversation, $exception->getMessage());
            app(DiscordStreamer::class)->conversationFailed($conversation, $exception->getMessage());
            app(DiscourseStreamer::class)->conversationFailed($conversation, $exception->getMessage());
        }
    }

    /**
     * Send a completion notification to the conversation owner if they opted in.
     */
    protected function notifyCompletion(Conversation $conversation, int $totalMessages, int $totalRounds): void
    {
        $user = $conversation->user;

        if ($user && $this->notificationsEnabled($conversation) && $user->wantsNotification('conversation_completed')) {
            $user->notify(new ConversationCompletedNotification(
                $conversation,
                $totalMessages,
                $totalRounds
            ));
        }
    }

    /**
     * Send a failure notification to the conversation owner if they opted in.
     */
    protected function notifyFailure(Conversation $conversation, string $errorMessage): void
    {
        $user = $conversation->user;

        if ($user && $this->notificationsEnabled($conversation) && $user->wantsNotification('conversation_failed')) {
            $user->notify(new ConversationFailedNotification(
                $conversation,
                $errorMessage
            ));
        }
    }

    protected function notificationsEnabled(Conversation $conversation): bool
    {
        $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];

        if (! array_key_exists('notifications_enabled', $metadata)) {
            return true;
        }

        return (bool) $metadata['notifications_enabled'];
    }

    protected function markConversationFailed(Conversation $conversation, string $errorMessage, ?array $errorContext = null): void
    {
        $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
        $metadata['last_error_message'] = mb_substr($errorMessage, 0, 2000);
        $metadata['last_error_at'] = now()->toIso8601String();
        if (is_array($errorContext) && $errorContext !== []) {
            $metadata['last_error_context'] = $errorContext;
        }

        $conversation->update([
            'status' => 'failed',
            'metadata' => $metadata,
        ]);
    }

    private function isRetryableTurnException(\Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());

        $retryableSnippets = [
            'curl error 28',
            'timed out',
            'timeout',
            'connection reset',
            'connection refused',
            'temporarily unavailable',
            'server error',
            'service unavailable',
            'too many requests',
            'rate limit',
            'no content returned',
            'empty response',
            'unexpected response format',
        ];

        foreach ($retryableSnippets as $snippet) {
            if (str_contains($message, $snippet)) {
                return true;
            }
        }

        return false;
    }

    private function historyLimitForConversation(Conversation $conversation): int
    {
        $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
        $configured = (int) data_get($metadata, 'memory.history_limit', 10);

        return max(1, min($configured, 50));
    }

    /**
     * @return array{content: string, driver: mixed}
     */
    private function attemptRescueTurn(
        ConversationService $service,
        Conversation $conversation,
        Persona $currentPersona,
        \Illuminate\Support\Collection $history,
        int $roundNumber,
        int $attempt,
        int $maxAttempts
    ): array {
        $rescueHistory = $history->values();
        $rescueHistory->push(new MessageData(
            'system',
            'Your previous attempt returned no content. Respond now to the latest message with a complete 2-4 sentence reply.'
        ));

        try {
            $generation = $service->generateTurn($conversation, $currentPersona, $rescueHistory);
            $driver = $generation['driver'] ?? null;
            $content = '';

            foreach ($generation['content'] as $chunk) {
                $content .= $chunk;
            }

            if (trim($content) !== '') {
                Log::info('Recovered empty turn via rescue generation', [
                    'conversation_id' => $this->conversationId,
                    'round' => $roundNumber,
                    'persona' => $currentPersona->name,
                    'attempt' => $attempt,
                    'max_attempts' => $maxAttempts,
                ]);

                return [
                    'content' => $content,
                    'driver' => $driver,
                ];
            }

            Log::warning('Rescue generation remained empty', [
                'conversation_id' => $this->conversationId,
                'round' => $roundNumber,
                'persona' => $currentPersona->name,
                'attempt' => $attempt,
                'max_attempts' => $maxAttempts,
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Rescue generation failed', [
                'conversation_id' => $this->conversationId,
                'round' => $roundNumber,
                'persona' => $currentPersona->name,
                'attempt' => $attempt,
                'max_attempts' => $maxAttempts,
                'error' => $exception->getMessage(),
            ]);
        }

        return [
            'content' => '',
            'driver' => null,
        ];
    }

    private function providerForPersona(Conversation $conversation, Persona $persona): string
    {
        return $conversation->personaA && $persona->is($conversation->personaA)
            ? (string) $conversation->provider_a
            : (string) $conversation->provider_b;
    }

    private function modelForPersona(Conversation $conversation, Persona $persona): ?string
    {
        return $conversation->personaA && $persona->is($conversation->personaA)
            ? ($conversation->model_a ? (string) $conversation->model_a : null)
            : ($conversation->model_b ? (string) $conversation->model_b : null);
    }

    private function buildGenerationFailure(string $message, array $context): \RuntimeException
    {
        return new \RuntimeException($message, 0, new \RuntimeException(json_encode($context, JSON_UNESCAPED_SLASHES)));
    }

    private function extractFailureContext(\Throwable $exception): ?array
    {
        $previous = $exception->getPrevious();
        if (! $previous instanceof \RuntimeException) {
            return null;
        }

        $decoded = json_decode($previous->getMessage(), true);
        if (! is_array($decoded)) {
            return null;
        }

        return $decoded;
    }
}
