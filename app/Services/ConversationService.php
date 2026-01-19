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
        protected EmbeddingService $embeddings,
        protected RagService $rag
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

        // Add RAG context if available and enabled
        if (config('services.qdrant.enabled', false) && $this->rag->isAvailable()) {
            $relevantContext = $this->getRelevantContext($persona, $history);

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
    protected function getRelevantContext(Persona $persona, Collection $history): Collection
    {
        // Get the most recent user/assistant message to use as search query
        $lastMessage = $history->last();

        if (! $lastMessage || empty($lastMessage->content)) {
            return collect();
        }

        // Search for similar messages, excluding current conversation
        $relevantMessages = $this->rag->searchSimilarMessages(
            query: $lastMessage->content,
            limit: 3,
            filter: ['persona_id' => $persona->id],
            scoreThreshold: 0.75
        );

        // Filter out messages from the current history
        $currentMessageIds = $history->pluck('id')->filter()->all();

        return $relevantMessages->filter(function ($message) use ($currentMessageIds) {
            return ! in_array($message->id, $currentMessageIds);
        });
    }

    /**
     * Finalize the conversation
     */
    public function completeConversation(Conversation $conversation): void
    {
        $conversation->update(['status' => 'completed']);
        $this->transcripts->generate($conversation);

        broadcast(new \App\Events\ConversationStatusUpdated($conversation));
    }
}
