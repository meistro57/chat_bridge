<?php

namespace Tests\Feature;

use App\Jobs\RunChatSession;
use App\Models\Conversation;
use App\Models\Persona;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
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
            'starter_message' => 'Test kickoff prompt.',
            'max_rounds' => 7,
            'stop_word_detection' => true,
            'stop_words' => ['goodbye', 'halt'],
            'stop_word_threshold' => 0.5,
            'notifications_enabled' => false,
        ];

        $response = $this->actingAs($user)->post(route('chat.store'), $payload);

        $response->assertRedirect();

        $conversation = Conversation::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();
        $this->assertNotNull($conversation);
        $this->assertSame($user->id, $conversation->user_id);
        $this->assertSame($payload['provider_a'], $conversation->provider_a);
        $this->assertSame($payload['model_b'], $conversation->model_b);
        $this->assertEquals($personaA->temperature, $conversation->temp_a);
        $this->assertEquals($personaB->temperature, $conversation->temp_b);
        $this->assertSame($payload['max_rounds'], $conversation->max_rounds);
        $this->assertSame($payload['stop_words'], $conversation->stop_words);
        $this->assertTrue($conversation->stop_word_detection);
        $this->assertEquals($payload['stop_word_threshold'], $conversation->stop_word_threshold);
        $this->assertSame(false, $conversation->metadata['notifications_enabled'] ?? null);

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

    public function test_store_defaults_notifications_to_off_when_omitted(): void
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
            'starter_message' => 'Test default notifications setting.',
            'max_rounds' => 7,
            'stop_word_detection' => false,
        ];

        $response = $this->actingAs($user)->post(route('chat.store'), $payload);

        $response->assertRedirect();

        $conversation = Conversation::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($conversation);
        $this->assertSame(false, $conversation->metadata['notifications_enabled'] ?? null);
    }

    public function test_transcript_downloads_markdown_from_private_storage(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $personaA = Persona::factory()->create();
        $personaB = Persona::factory()->create();

        $conversation = Conversation::create([
            'user_id' => $user->id,
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
            'provider_a' => 'openai',
            'provider_b' => 'openai',
            'model_a' => 'gpt-4o-mini',
            'model_b' => 'gpt-4o-mini',
            'temp_a' => 0.7,
            'temp_b' => 0.7,
            'starter_message' => 'Start the test.',
            'status' => 'completed',
            'max_rounds' => 1,
            'stop_word_detection' => false,
            'stop_words' => null,
            'stop_word_threshold' => 0.8,
        ]);

        $conversation->messages()->create([
            'persona_id' => $personaA->id,
            'role' => 'assistant',
            'content' => 'Transcript content.',
        ]);

        $response = $this->actingAs($user)->get(route('chat.transcript', $conversation));

        $expectedFilename = Str::slug($conversation->id).'.md';

        $response->assertOk();
        $response->assertDownload($expectedFilename);
        Storage::disk('local')->assertExists('transcripts/'.$expectedFilename);
    }

    public function test_show_includes_markdown_message_content(): void
    {
        $user = User::factory()->create();
        $personaA = Persona::factory()->create();
        $personaB = Persona::factory()->create();

        $conversation = Conversation::create([
            'user_id' => $user->id,
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
            'provider_a' => 'openai',
            'provider_b' => 'openai',
            'model_a' => 'gpt-4o-mini',
            'model_b' => 'gpt-4o-mini',
            'temp_a' => 0.7,
            'temp_b' => 0.7,
            'starter_message' => 'Start the test.',
            'status' => 'completed',
            'max_rounds' => 1,
            'stop_word_detection' => false,
            'stop_words' => null,
            'stop_word_threshold' => 0.8,
        ]);

        $markdown = "**Bold** and `code` with a list:\n- One\n- Two";

        $conversation->messages()->create([
            'persona_id' => $personaA->id,
            'role' => 'assistant',
            'content' => $markdown,
        ]);

        $response = $this->actingAs($user)->get(route('chat.show', $conversation));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Chat/Show')
            ->has('conversation.messages', 1)
            ->where('conversation.messages.0.content', $markdown)
        );
    }
}
