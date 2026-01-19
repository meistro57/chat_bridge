<?php

namespace App\Jobs;

use App\Events\MessageChunkSent;
use App\Events\MessageCompleted;
use App\Models\Conversation;
use App\Services\AI\AIManager;
use App\Services\AI\Data\MessageData;
use App\Services\AI\StopWordService;
use App\Services\AI\TranscriptService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessConversationTurn implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $conversationId,
        public int $round = 1,
        public int $maxRounds = 10
    ) {}

    public function handle(AIManager $ai, StopWordService $stopWords, TranscriptService $transcripts)
    {
        $conversation = Conversation::with(['messages.persona', 'personaA', 'personaB'])->findOrFail($this->conversationId);

        if ($conversation->status !== 'active' || $this->round > $this->maxRounds) {
            $conversation->update(['status' => 'completed']);
            $transcripts->generate($conversation);

            return;
        }

        $personaA = $conversation->personaA;
        $personaB = $conversation->personaB;

        if (! $personaA || ! $personaB) {
            // Log error or fallback
            return;
        }

        $lastMessage = $conversation->messages()->whereNotNull('persona_id')->latest()->first();
        $currentPersona = (! $lastMessage || $lastMessage->persona_id === $personaB->id) ? $personaA : $personaB;

        $history = $conversation->messages->map(fn ($m) => new MessageData($m->role, $m->content)
        )->take(-10);

        $driver = $ai->driver($currentPersona->provider);
        $messages = collect();
        $messages->push(new MessageData('system', $currentPersona->system_prompt));
        foreach ($currentPersona->guidelines ?? [] as $guideline) {
            $messages->push(new MessageData('system', "Guideline: $guideline"));
        }
        $messages = $messages->concat($history);

        $fullResponse = '';
        foreach ($driver->streamChat($messages, $currentPersona->temperature) as $chunk) {
            $fullResponse .= $chunk;
            broadcast(new MessageChunkSent($conversation->id, $chunk, 'assistant', $currentPersona->name));
        }

        $message = $conversation->messages()->create([
            'persona_id' => $currentPersona->id,
            'role' => 'assistant',
            'content' => $fullResponse,
        ]);

        broadcast(new MessageCompleted($message));

        if ($stopWords->shouldStop($fullResponse)) {
            $conversation->update(['status' => 'completed']);
            $transcripts->generate($conversation);

            return;
        }

        // Schedule next turn
        dispatch(new self($this->conversationId, $this->round + 1, $this->maxRounds))
            ->delay(now()->addSeconds(2));
    }
}
