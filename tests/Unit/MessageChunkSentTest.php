<?php

namespace Tests\Unit;

use App\Events\MessageChunkSent;
use Tests\TestCase;

class MessageChunkSentTest extends TestCase
{
    public function test_broadcast_payload_shape(): void
    {
        $event = new MessageChunkSent(
            conversationId: 'conv-123',
            chunk: 'hello',
            role: 'assistant',
            personaName: 'Agent A'
        );

        $payload = $event->broadcastWith();

        $this->assertSame('conv-123', $payload['conversationId']);
        $this->assertSame('hello', $payload['chunk']);
        $this->assertSame('assistant', $payload['role']);
        $this->assertSame('Agent A', $payload['personaName']);
    }
}
