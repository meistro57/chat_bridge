<?php

namespace App\Services\AI;

use App\Models\Message;
use Illuminate\Support\Carbon;

class MessageEmbeddingPopulator
{
    /**
     * @return array{status:string,attempts_used:int,error:?string,next_retry_at:?Carbon,retriable:bool}
     */
    public function populate(Message $message, EmbeddingService $embeddingService): array
    {
        $attemptedAt = now();
        $existingAttempts = (int) ($message->embedding_attempts ?? 0);

        $preparedContent = $this->prepareContent($message->content);

        if ($preparedContent === null) {
            $message->update([
                'embedding_status' => 'skipped',
                'embedding_attempts' => $existingAttempts + 1,
                'embedding_last_error' => null,
                'embedding_skip_reason' => 'empty_or_invalid_content',
                'embedding_last_attempt_at' => $attemptedAt,
                'embedding_next_retry_at' => null,
            ]);

            return [
                'status' => 'skipped',
                'attempts_used' => 1,
                'error' => null,
                'next_retry_at' => null,
                'retriable' => false,
            ];
        }

        try {
            $embedding = $embeddingService->getEmbedding($preparedContent);

            $message->update([
                'embedding' => $embedding,
                'embedding_status' => 'embedded',
                'embedding_attempts' => $existingAttempts + 1,
                'embedding_last_error' => null,
                'embedding_skip_reason' => null,
                'embedding_last_attempt_at' => $attemptedAt,
                'embedding_next_retry_at' => null,
            ]);

            return [
                'status' => 'embedded',
                'attempts_used' => 1,
                'error' => null,
                'next_retry_at' => null,
                'retriable' => false,
            ];
        } catch (\Throwable $exception) {
            $newAttempts = $existingAttempts + 1;
            $maxAttempts = $this->maxAttempts();
            $nextRetryAt = $newAttempts < $maxAttempts
                ? $attemptedAt->copy()->addSeconds($this->backoffSeconds($newAttempts))
                : null;

            $message->update([
                'embedding_status' => 'failed',
                'embedding_attempts' => $newAttempts,
                'embedding_last_error' => mb_substr($exception->getMessage(), 0, 1500),
                'embedding_skip_reason' => null,
                'embedding_last_attempt_at' => $attemptedAt,
                'embedding_next_retry_at' => $nextRetryAt,
            ]);

            return [
                'status' => 'failed',
                'attempts_used' => 1,
                'error' => $exception->getMessage(),
                'next_retry_at' => $nextRetryAt,
                'retriable' => $nextRetryAt !== null,
            ];
        }
    }

    public function maxAttempts(): int
    {
        return max(1, (int) config('ai.embedding_population_max_attempts', 5));
    }

    private function backoffSeconds(int $attemptNumber): int
    {
        return match ($attemptNumber) {
            1 => 60,
            2 => 300,
            3 => 900,
            default => 1800,
        };
    }

    private function prepareContent(?string $content): ?string
    {
        if (! is_string($content)) {
            return null;
        }

        $normalized = trim($content);

        if ($normalized === '') {
            return null;
        }

        if (! mb_check_encoding($normalized, 'UTF-8')) {
            $normalized = mb_convert_encoding($normalized, 'UTF-8');
        }

        $maxChars = max(200, (int) config('ai.embedding_input_max_chars', 8000));

        if (mb_strlen($normalized) > $maxChars) {
            $normalized = mb_substr($normalized, 0, $maxChars);
        }

        return trim($normalized) === '' ? null : $normalized;
    }
}
