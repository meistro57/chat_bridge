<?php

namespace App\Jobs;

use App\Models\Message;
use App\Services\AI\EmbeddingService;
use App\Services\AI\MessageEmbeddingPopulator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PopulateMessageEmbeddingJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $messageId)
    {
    }

    public function handle(EmbeddingService $embeddingService, MessageEmbeddingPopulator $populator): void
    {
        $message = Message::query()->find($this->messageId);

        if (! $message instanceof Message || $message->embedding !== null) {
            return;
        }

        if ($message->embedding_status === 'skipped') {
            return;
        }

        if ($message->embedding_next_retry_at !== null && $message->embedding_next_retry_at->isFuture()) {
            return;
        }

        $populator->populate($message, $embeddingService);
    }
}
