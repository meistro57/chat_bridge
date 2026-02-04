<?php

namespace Tests\Unit;

use App\Events\ConversationStatusUpdated;
use App\Models\Conversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationStatusUpdatedTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_broadcasts_minimal_payload(): void
    {
        $conversation = Conversation::factory()->create([
            'status' => 'failed',
        ]);

        $event = new ConversationStatusUpdated($conversation);
        $payload = $event->broadcastWith();

        $this->assertSame($conversation->id, $payload['conversation']['id']);
        $this->assertSame('failed', $payload['conversation']['status']);
    }
}
