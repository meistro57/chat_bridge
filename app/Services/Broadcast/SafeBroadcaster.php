<?php

namespace App\Services\Broadcast;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Log;

class SafeBroadcaster
{
    private const DEFAULT_PAYLOAD_LIMIT = 9000;

    public function __construct(
        protected Dispatcher $events
    ) {}

    public function broadcast(object $event, array $context = []): bool
    {
        $payloadSize = $this->estimatePayloadSize($event);
        $payloadLimit = (int) config('ai.broadcast_payload_limit', self::DEFAULT_PAYLOAD_LIMIT);

        if ($payloadSize !== null && $payloadLimit > 0 && $payloadSize > $payloadLimit) {
            Log::warning('Broadcast skipped (payload too large)', [
                'event' => $event::class,
                'payload_bytes' => $payloadSize,
                'payload_limit' => $payloadLimit,
                'context' => $context,
            ]);

            return false;
        }

        try {
            $this->events->dispatch($event);

            return true;
        } catch (\Throwable $exception) {
            Log::warning('Broadcast failed', [
                'event' => $event::class,
                'error' => $exception->getMessage(),
                'payload_bytes' => $payloadSize,
                'context' => $context,
            ]);

            return false;
        }
    }

    private function estimatePayloadSize(object $event): ?int
    {
        try {
            if (method_exists($event, 'broadcastWith')) {
                $payload = $event->broadcastWith();

                return strlen((string) json_encode($payload));
            }

            $payload = get_object_vars($event);

            if ($payload !== []) {
                return strlen((string) json_encode($payload));
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }
}
