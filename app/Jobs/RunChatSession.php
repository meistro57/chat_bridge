<?php

namespace App\Jobs;

use App\Events\MessageChunkSent;
use App\Events\MessageCompleted;
use App\Models\Conversation;
use App\Services\AI\Data\MessageData;
use App\Services\AI\StopWordService;
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

                // 3. Prepare History
                $history = $conversation->messages()
                    ->latest()
                    ->take(10)
                    ->get()
                    ->sortBy('id')
                    ->map(fn ($m) => new MessageData($m->role, $m->content));

                // 4. Generate & Stream
                $fullResponse = '';
                // Yield chunks from service
                foreach ($service->generateTurn($conversation, $currentPersona, $history) as $chunk) {
                    $fullResponse .= $chunk;

                    broadcast(new MessageChunkSent(
                        conversationId: $conversation->id,
                        chunk: $chunk,
                        role: 'assistant',
                        personaName: $currentPersona->name
                    ));

                    if (Cache::get("conversation.stop.{$this->conversationId}")) {
                        break;
                    }
                }

                // 5. Save & Finalize Turn
                $message = $service->saveTurn($conversation, $currentPersona, $fullResponse);
                broadcast(new MessageCompleted($message));

                Log::info('Turn completed', [
                    'conversation_id' => $this->conversationId,
                    'round' => $round + 1,
                    'persona' => $currentPersona->name,
                    'message_length' => strlen($fullResponse),
                    'tokens_used' => $message->tokens_used ?? 0,
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
                sleep(1);
            }

            if ($round >= $maxRounds && $conversation->fresh()->status === 'active') {
                Log::info('Conversation reached max rounds', [
                    'conversation_id' => $this->conversationId,
                    'rounds' => $round,
                ]);
                $service->completeConversation($conversation);
            }

            $duration = microtime(true) - $startTime;
            Log::info('Chat session completed', [
                'conversation_id' => $this->conversationId,
                'total_rounds' => $round,
                'duration_seconds' => round($duration, 2),
                'total_messages' => $conversation->messages()->count(),
            ]);
        } catch (\Throwable $e) {
            Log::error("Job failed for conversation {$this->conversationId}", [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'round' => $round,
            ]);
            $conversation->update(['status' => 'failed']);
            broadcast(new \App\Events\ConversationStatusUpdated($conversation));
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
            broadcast(new \App\Events\ConversationStatusUpdated($conversation));
        }
    }
}
