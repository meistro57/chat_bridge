<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Persona;
use App\Services\AI\AIManager;
use App\Services\AI\Data\MessageData;
use App\Services\AI\EmbeddingService;
use App\Services\AI\TranscriptService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ConversationService
{
    public function __construct(
        protected AIManager $ai,
        protected TranscriptService $transcripts,
        protected EmbeddingService $embeddings
    ) {}

    /**
     * Generate a turn for the given persona based on history.
     * Yields chunks of text.
     * 
     * @return \Generator<string>
     */
    public function generateTurn(Persona $persona, Collection $history): \Generator
    {
        $driver = $this->ai->driver($persona->provider);
        
        $messages = collect();
        // System Prompt
        $messages->push(new MessageData('system', $persona->system_prompt));
        
        // Guidelines
        foreach ($persona->guidelines ?? [] as $guideline) {
            $messages->push(new MessageData('system', "Guideline: $guideline"));
        }
        
        // History (last 10 messages)
        $messages = $messages->concat($history);

        // Stream from driver
        foreach ($driver->streamChat($messages, $persona->temperature) as $chunk) {
            yield $chunk;
        }
    }

    /**
     * Save the completed message to the database and generate embedding.
     */
    public function saveTurn(Conversation $conversation, Persona $persona, string $content): Message
    {
        $message = $conversation->messages()->create([
            'persona_id' => $persona->id,
            'role' => 'assistant',
            'content' => $content,
        ]);

        // Generate embedding asynchronously (or inline if queue driver is sync)
        try {
            $vector = $this->embeddings->getEmbedding($content);
            $message->update(['embedding' => $vector]);
        } catch (\Exception $e) {
            Log::warning("Embedding generation failed for message {$message->id}: " . $e->getMessage());
        }

        return $message;
    }

    /**
     * Finalize the conversation
     */
    public function completeConversation(Conversation $conversation): void
    {
        $conversation->update(['status' => 'completed']);
        $this->transcripts->generate($conversation);
    }
}
