<?php

namespace Tests\Unit;

use App\Events\MessageCompleted;
use App\Models\Conversation;
use App\Models\Persona;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageCompletedTest extends TestCase
{
    use RefreshDatabase;

    public function test_broadcast_payload_includes_content(): void
    {
        $user = User::factory()->create();
        $persona = Persona::factory()->create(['user_id' => $user->id]);
        $conversation = Conversation::factory()->create([
            'user_id' => $user->id,
            'persona_a_id' => $persona->id,
            'persona_b_id' => $persona->id,
        ]);

        $message = $conversation->messages()->create([
            'persona_id' => $persona->id,
            'role' => 'assistant',
            'content' => 'Hello world.',
        ]);

        $event = new MessageCompleted($message->load('persona'));
        $payload = $event->broadcastWith();

        $this->assertSame('Hello world.', $payload['message']['content']);
        $this->assertSame($message->id, $payload['message']['id']);
        $this->assertSame($persona->name, $payload['personaName']);
    }
}
