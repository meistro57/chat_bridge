<?php

namespace Tests\Feature;

use App\Jobs\RunChatSession;
use App\Models\Conversation;
use App\Models\Persona;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_view_chat_dashboard(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get('/chat');
        $response->assertStatus(200);
    }

    public function test_can_search_messages(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get('/chat/search?q=test');
        $response->assertStatus(200);
    }

    public function test_can_create_new_conversation(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $personaA = Persona::factory()->create(['user_id' => $user->id]);
        $personaB = Persona::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post('/chat', [
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
            'provider_a' => 'openai',
            'provider_b' => 'openai',
            'model_a' => 'gpt-4o-mini',
            'model_b' => 'gpt-4o-mini',
            'temp_a' => 0.7,
            'temp_b' => 0.7,
            'starter_message' => 'Hello agents',
            'max_rounds' => 10,
            'stop_word_detection' => true,
            'stop_words' => ['goodbye', 'end'],
            'stop_word_threshold' => 0.8,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('conversations', [
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
        ]);

        Queue::assertPushed(RunChatSession::class);
    }

    public function test_can_view_conversation_show_page(): void
    {
        $user = User::factory()->create();
        $personaA = Persona::factory()->create(['user_id' => $user->id]);
        $personaB = Persona::factory()->create(['user_id' => $user->id]);
        $conversation = Conversation::factory()->create([
            'user_id' => $user->id,
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
        ]);

        $response = $this->actingAs($user)->get("/chat/{$conversation->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Chat/Show')
            ->has('conversation')
        );
    }

    public function test_can_stop_active_conversation(): void
    {
        $user = User::factory()->create();
        $personaA = Persona::factory()->create(['user_id' => $user->id]);
        $personaB = Persona::factory()->create(['user_id' => $user->id]);
        $conversation = Conversation::factory()->create([
            'user_id' => $user->id,
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->post("/chat/{$conversation->id}/stop");

        $response->assertRedirect();
        $this->assertTrue(Cache::has("conversation.stop.{$conversation->id}"));
    }
}
