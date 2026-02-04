<?php

namespace Tests\Unit;

use App\Services\Broadcast\SafeBroadcaster;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SafeBroadcasterTest extends TestCase
{
    public function test_it_swallows_broadcast_errors_and_logs(): void
    {
        $dispatcher = new class implements Dispatcher
        {
            public function dispatch($event, $payload = [], $halt = false)
            {
                throw new \RuntimeException('boom');
            }

            public function listen($events, $listener = null) {}

            public function hasListeners($eventName)
            {
                return false;
            }

            public function subscribe($subscriber) {}

            public function until($event, $payload = []) {}

            public function flush($event) {}

            public function forget($event) {}

            public function forgetPushed() {}

            public function push($event, $payload = []) {}
        };

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'Broadcast failed'
                    && $context['event'] === DummyEvent::class
                    && $context['error'] === 'boom';
            });

        $broadcaster = new SafeBroadcaster($dispatcher);

        $result = $broadcaster->broadcast(new DummyEvent);

        $this->assertFalse($result);
    }

    public function test_it_skips_payloads_over_limit(): void
    {
        $wasDispatched = false;

        $dispatcher = new class($wasDispatched) implements Dispatcher
        {
            public function __construct(private bool &$wasDispatched) {}

            public function dispatch($event, $payload = [], $halt = false)
            {
                $this->wasDispatched = true;
            }

            public function listen($events, $listener = null) {}

            public function hasListeners($eventName)
            {
                return false;
            }

            public function subscribe($subscriber) {}

            public function until($event, $payload = []) {}

            public function flush($event) {}

            public function forget($event) {}

            public function forgetPushed() {}

            public function push($event, $payload = []) {}
        };

        config()->set('ai.broadcast_payload_limit', 10);

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'Broadcast skipped (payload too large)'
                    && $context['event'] === DummyLargeEvent::class
                    && $context['payload_limit'] === 10;
            });

        $broadcaster = new SafeBroadcaster($dispatcher);

        $result = $broadcaster->broadcast(new DummyLargeEvent);

        $this->assertFalse($result);
        $this->assertFalse($wasDispatched);
    }
}

class DummyEvent
{
    public function broadcastWith(): array
    {
        return ['foo' => 'bar'];
    }
}

class DummyLargeEvent
{
    public function broadcastWith(): array
    {
        return ['chunk' => str_repeat('x', 100)];
    }
}
