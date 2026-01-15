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

        // Loop until max rounds or stopped
        while ($round < $this->maxRounds) {
            // 1. Check for manual stop signal
            if (Cache::get("conversation.stop.{$this->conversationId}")) {
                Log::info("Conversation {$this->conversationId} stopped by user signal.");
                $service->completeConversation($conversation);
                break;
            }

            // 2. Identify Current Speaker
            // We look at the last assistant message to determine who spoke last.
            // If no assistant message, Persona A starts.
            $lastMessage = $conversation->messages()
                ->where('role', 'assistant')
                ->latest()
                ->first();
                
            $currentPersona = (!$lastMessage || $lastMessage->persona_id === $conversation->personaB->id) 
                ? $conversation->personaA 
                : $conversation->personaB;

            // 3. Prepare History
            $history = $conversation->messages()
                ->latest() // Get latest first
                ->take(10) // Limit to 10
                ->get()
                ->sortBy('id') // Reorder chronologically
                ->map(fn($m) => new MessageData($m->role, $m->content));

            // 4. Generate & Stream
            $fullResponse = '';
            try {
                // Yield chunks from service
                foreach ($service->generateTurn($currentPersona, $history) as $chunk) {
                    $fullResponse .= $chunk;
                    
                    // Broadcast chunk immediately
                    broadcast(new MessageChunkSent(
                        conversationId: $conversation->id,
                        chunk: $chunk,
                        role: 'assistant',
                        personaName: $currentPersona->name
                    ));
                    
                    // Check cache signal even during generation?
                    if (Cache::get("conversation.stop.{$this->conversationId}")) {
                        break; 
                    }
                }
            } catch (\Exception $e) {
                Log::error("Error generating turn in conversation {$this->conversationId}: " . $e->getMessage());
                // Optionally broadcast error
                break;
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
            
            // tiny sleep to let systems breathe
            sleep(1);
        }

        if ($round >= $this->maxRounds) {
            $service->completeConversation($conversation);
        }
    }
}
