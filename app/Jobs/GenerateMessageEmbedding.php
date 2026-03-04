<?php

namespace App\Jobs;

use App\Models\Message;
use App\Services\AI\EmbeddingService;
use App\Services\RagService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateMessageEmbedding implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Message $message) {}

    public function handle(EmbeddingService $embeddings, RagService $rag): void
    {
        try {
            $vector = $embeddings->getEmbedding($this->message->content);
            $this->message->update(['embedding' => $vector]);

            if (config('services.qdrant.enabled', false) && $rag->isAvailable()) {
                $this->message->refresh();
                $rag->storeMessage($this->message);
            }
        } catch (\Exception $e) {
            Log::warning("Embedding generation failed for message {$this->message->id}: ".$e->getMessage());
        }
    }
}
