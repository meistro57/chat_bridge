<?php

namespace App\Jobs;

use App\Events\MessageChunkSent;
use App\Events\MessageCompleted;
use App\Models\Conversation;
use App\Notifications\ConversationCompletedNotification;
use App\Notifications\ConversationFailedNotification;
use App\Services\AI\Data\MessageData;
use App\Services\AI\StopWordService;
use App\Services\AI\StreamingChunker;
use App\Services\Broadcast\SafeBroadcaster;
use App\Services\ConversationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
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

    public function handle(
        ConversationService $service,
        StopWordService $stopWords
    ): void {
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

                // 3. Prepare History with persona names
                $history = $conversation->messages()
                    ->with('persona')
                    ->latest()
                    ->take(10)
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
                $chunkCount = 0;
                $driver = null;
                $emptyRetryAttempt = 0;
                $exceptionRetryAttempt = 0;

                while (true) {
                    $fullResponse = '';
                    $chunkCount = 0;
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

                            foreach ($chunker->split($chunk, $maxChunkSize) as $piece) {
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
                    } catch (\Throwable $exception) {
                        if (
                            $this->isRetryableTurnException($exception)
                            && $exceptionRetryAttempt < $maxTurnExceptionRetries
                        ) {
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

                if (trim($fullResponse) === '') {
                    $fullResponse = trim((string) config(
                        'ai.empty_turn_fallback_message',
                        'I need to regroup for a moment. Please continue with your strongest next point.'
                    ));

                    Log::warning('Turn produced empty response after retries; using fallback message', [
                        'conversation_id' => $this->conversationId,
                        'round' => $round + 1,
                        'persona' => $currentPersona->name,
                        'chunk_count' => $chunkCount,
                        'empty_retry_attempts' => $emptyRetryAttempt,
                        'max_empty_retries' => $maxEmptyTurnRetries,
                        'exception_retry_attempts' => $exceptionRetryAttempt,
                        'max_exception_retries' => $maxTurnExceptionRetries,
                        'fallback_length' => strlen($fullResponse),
                    ]);
                }

                // 5. Save & Finalize Turn
                $tokensUsed = $driver?->getLastTokenUsage();
                $message = $service->saveTurn($conversation, $currentPersona, $fullResponse, $tokensUsed);
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

            $this->notifyCompletion($conversation, $totalMessages, $round);
        } catch (\Throwable $e) {
            Log::error("Job failed for conversation {$this->conversationId}", [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'round' => $round,
            ]);
            $conversation->update(['status' => 'failed']);
            app(SafeBroadcaster::class)->broadcast(
                new \App\Events\ConversationStatusUpdated($conversation),
                [
                    'conversation_id' => $conversation->id,
                    'phase' => 'status',
                ]
            );

            $this->notifyFailure($conversation, $e->getMessage());

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
            $conversation->update(['status' => 'failed']);
            app(SafeBroadcaster::class)->broadcast(
                new \App\Events\ConversationStatusUpdated($conversation),
                [
                    'conversation_id' => $conversation->id,
                    'phase' => 'status',
                ]
            );

            $this->notifyFailure($conversation, $exception->getMessage());
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
        ];

        foreach ($retryableSnippets as $snippet) {
            if (str_contains($message, $snippet)) {
                return true;
            }
        }

        return false;
    }
}
