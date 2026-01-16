<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Persona;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_view_chat_dashboard(): void
    {
        $response = $this->get('/chat');
        $response->assertStatus(200);
    }

    public function test_can_search_messages(): void
    {
        $response = $this->get('/chat/search?q=test');
        $response->assertStatus(200);
    }

    public function test_can_create_new_conversation(): void
    {
        $this->withoutMiddleware();
        $personaA = Persona::factory()->create();
        $personaB = Persona::factory()->create();

        $response = $this->post('/chat/store', [
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
            'starter_message' => 'Hello agents',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('conversations', [
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
        ]);
    }
}
