<?php

namespace Tests\Feature;

use App\Jobs\RunChatSession;
use App\Models\Conversation;
use App\Models\Persona;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class ChatControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_persists_ui_settings_and_dispatches_job(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $personaA = Persona::factory()->create();
        $personaB = Persona::factory()->create();

        $payload = [
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
            'provider_a' => 'openai',
            'provider_b' => 'openai',
            'model_a' => 'gpt-4o-mini',
            'model_b' => 'gpt-4o-mini',
            'temp_a' => 0.6,
            'temp_b' => 0.8,
            'starter_message' => 'Test kickoff prompt.',
            'max_rounds' => 7,
            'stop_word_detection' => true,
            'stop_words' => ['goodbye', 'halt'],
            'stop_word_threshold' => 0.5,
        ];

        $response = $this->actingAs($user)->post(route('chat.store'), $payload);

        $response->assertRedirect();

        $conversation = Conversation::first();
        $this->assertNotNull($conversation);
        $this->assertSame($user->id, $conversation->user_id);
        $this->assertSame($payload['provider_a'], $conversation->provider_a);
        $this->assertSame($payload['model_b'], $conversation->model_b);
        $this->assertSame($payload['max_rounds'], $conversation->max_rounds);
        $this->assertSame($payload['stop_words'], $conversation->stop_words);
        $this->assertTrue($conversation->stop_word_detection);
        $this->assertEquals($payload['stop_word_threshold'], $conversation->stop_word_threshold);

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $payload['starter_message'],
        ]);

        Bus::assertDispatched(RunChatSession::class, function (RunChatSession $job) use ($conversation, $payload) {
            return $job->conversationId === $conversation->id
                && $job->maxRounds === $payload['max_rounds'];
        });
    }
}
