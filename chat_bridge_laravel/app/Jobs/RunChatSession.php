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
    ) {
        $conversation = Conversation::with(['messages.persona', 'personaA', 'personaB'])->findOrFail($this->conversationId);

        if ($conversation->status !== 'active') {
            return;
        }

        $round = 0;

        try {
            // Loop until max rounds or stopped
            while ($round < $this->maxRounds) {
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
                    
                $currentPersona = (!$lastMessage || $lastMessage->persona_id === $conversation->personaB->id) 
                    ? $conversation->personaA 
                    : $conversation->personaB;

                // 3. Prepare History
                $history = $conversation->messages()
                    ->latest()
                    ->take(10)
                    ->get()
                    ->sortBy('id')
                    ->map(fn($m) => new MessageData($m->role, $m->content));

                // 4. Generate & Stream
                $fullResponse = '';
                // Yield chunks from service
                foreach ($service->generateTurn($currentPersona, $history) as $chunk) {
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

                // 6. Check Stop Words
                if ($stopWords->shouldStop($fullResponse)) {
                    Log::info("Conversation {$this->conversationId} stopped by stop word.");
                    $service->completeConversation($conversation);
                    break;
                }

                $round++;
                sleep(1);
            }

            if ($round >= $this->maxRounds && $conversation->fresh()->status === 'active') {
                $service->completeConversation($conversation);
            }
        } catch (\Throwable $e) {
            Log::error("Job failed for conversation {$this->conversationId}: " . $e->getMessage());
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
