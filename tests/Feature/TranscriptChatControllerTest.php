<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Persona;
use App\Models\User;
use App\Services\AI\EmbeddingService;
use App\Services\RagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class TranscriptChatControllerTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // index
    // -------------------------------------------------------------------------

    public function test_index_requires_authentication(): void
    {
        $response = $this->get(route('transcript-chat.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_index_renders_inertia_page_with_conversations(): void
    {
        $user = User::factory()->create();
        Conversation::factory()->count(3)->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('transcript-chat.index'));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Chat/TranscriptChat')
            ->has('conversations', 3)
        );
    }

    public function test_index_only_returns_the_authenticated_users_conversations(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Conversation::factory()->count(2)->create(['user_id' => $user->id]);
        Conversation::factory()->count(5)->create(['user_id' => $other->id]);

        $response = $this->actingAs($user)->get(route('transcript-chat.index'));

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Chat/TranscriptChat')
            ->has('conversations', 2)
        );
    }

    // -------------------------------------------------------------------------
    // ask – authentication & validation
    // -------------------------------------------------------------------------

    public function test_ask_requires_authentication(): void
    {
        $response = $this->postJson(route('transcript-chat.ask'), ['question' => 'Hello?']);

        $response->assertUnauthorized();
    }

    public function test_ask_requires_a_question(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('transcript-chat.ask'), []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['question']);
    }

    public function test_ask_rejects_short_questions(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('transcript-chat.ask'), ['question' => 'Hi']);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['question']);
    }

    public function test_ask_rejects_invalid_conversation_id(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('transcript-chat.ask'), [
            'question' => 'What happened in this chat?',
            'conversation_id' => 'not-a-uuid',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['conversation_id']);
    }

    public function test_ask_rejects_nonexistent_conversation_id(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('transcript-chat.ask'), [
            'question' => 'What happened in this chat?',
            'conversation_id' => '00000000-0000-0000-0000-000000000000',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['conversation_id']);
    }

    // -------------------------------------------------------------------------
    // ask – no matching context
    // -------------------------------------------------------------------------

    public function test_ask_returns_fallback_message_when_no_context_found(): void
    {
        $user = User::factory()->create();

        $this->mock(RagService::class, function ($mock) {
            $mock->shouldReceive('searchSimilarMessages')
                ->once()
                ->andReturn(collect());
        });

        $this->mock(EmbeddingService::class);

        $response = $this->actingAs($user)->postJson(route('transcript-chat.ask'), [
            'question' => 'Tell me about the deployment process?',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['answer', 'sources']);
        $response->assertJsonPath('sources', []);
        $this->assertStringContainsString(
            'could not find',
            strtolower($response->json('answer'))
        );
    }

    // -------------------------------------------------------------------------
    // ask – happy path with OpenAI mock
    // -------------------------------------------------------------------------

    public function test_ask_returns_ai_answer_with_sources(): void
    {
        $user = User::factory()->create();
        $persona = Persona::factory()->create(['user_id' => $user->id]);
        $conversation = Conversation::factory()->create(['user_id' => $user->id]);

        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'persona_id' => $persona->id,
            'role' => 'assistant',
            'content' => 'We deployed using Docker Compose on the production server.',
            'embedding' => array_fill(0, 1536, 0.1),
        ]);
        $message->similarity_score = 0.92;

        $mockMessages = collect([$message]);

        $this->mock(RagService::class, function ($mock) use ($mockMessages) {
            $mock->shouldReceive('searchSimilarMessages')
                ->once()
                ->andReturn($mockMessages);
        });

        $this->mock(EmbeddingService::class);

        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'The team used Docker Compose for deployment.']],
                ],
            ], 200),
        ]);

        config(['services.openai.key' => 'sk-test-key']);

        $response = $this->actingAs($user)->postJson(route('transcript-chat.ask'), [
            'question' => 'How did we deploy to production?',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['answer', 'sources']);
        $this->assertSame('The team used Docker Compose for deployment.', $response->json('answer'));
        $this->assertCount(1, $response->json('sources'));
        $this->assertSame('assistant', $response->json('sources.0.role'));
    }

    public function test_ask_accepts_valid_conversation_id_for_scoped_search(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create(['user_id' => $user->id]);

        $this->mock(RagService::class, function ($mock) use ($conversation) {
            $mock->shouldReceive('searchSimilarMessages')
                ->once()
                ->withArgs(function ($query, $limit, $filter) use ($conversation) {
                    return isset($filter['conversation_id'])
                        && $filter['conversation_id'] === $conversation->id;
                })
                ->andReturn(collect());
        });

        $this->mock(EmbeddingService::class);

        $this->actingAs($user)->postJson(route('transcript-chat.ask'), [
            'question' => 'What was discussed here?',
            'conversation_id' => $conversation->id,
        ]);
    }

    public function test_ask_ignores_conversation_id_belonging_to_another_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $otherConversation = Conversation::factory()->create(['user_id' => $other->id]);

        $this->mock(RagService::class, function ($mock) {
            $mock->shouldReceive('searchSimilarMessages')
                ->once()
                ->withArgs(function ($query, $limit, $filter) {
                    // conversation_id should NOT be present since the conversation
                    // belongs to a different user
                    return ! isset($filter['conversation_id']);
                })
                ->andReturn(collect());
        });

        $this->mock(EmbeddingService::class);

        $this->actingAs($user)->postJson(route('transcript-chat.ask'), [
            'question' => 'What was discussed here?',
            'conversation_id' => $otherConversation->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // ask – OpenAI error handling
    // -------------------------------------------------------------------------

    public function test_ask_returns_error_message_when_openai_fails(): void
    {
        $user = User::factory()->create();
        $persona = Persona::factory()->create(['user_id' => $user->id]);
        $conversation = Conversation::factory()->create(['user_id' => $user->id]);

        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'persona_id' => $persona->id,
            'role' => 'assistant',
            'content' => 'Some transcript content here.',
            'embedding' => array_fill(0, 1536, 0.1),
        ]);
        $message->similarity_score = 0.85;

        $this->mock(RagService::class, function ($mock) use ($message) {
            $mock->shouldReceive('searchSimilarMessages')
                ->once()
                ->andReturn(collect([$message]));
        });

        $this->mock(EmbeddingService::class);

        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response(['error' => 'Unauthorized'], 401),
        ]);

        config(['services.openai.key' => 'sk-test-key']);

        $response = $this->actingAs($user)->postJson(route('transcript-chat.ask'), [
            'question' => 'Tell me about the conversation?',
        ]);

        $response->assertOk();
        $this->assertStringContainsString('error', strtolower($response->json('answer')));
    }

    public function test_ask_returns_notice_when_openai_key_is_missing(): void
    {
        $user = User::factory()->create();
        $persona = Persona::factory()->create(['user_id' => $user->id]);
        $conversation = Conversation::factory()->create(['user_id' => $user->id]);

        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'persona_id' => $persona->id,
            'role' => 'assistant',
            'content' => 'Some transcript content.',
            'embedding' => array_fill(0, 1536, 0.1),
        ]);
        $message->similarity_score = 0.88;

        $this->mock(RagService::class, function ($mock) use ($message) {
            $mock->shouldReceive('searchSimilarMessages')
                ->once()
                ->andReturn(collect([$message]));
        });

        $this->mock(EmbeddingService::class);

        config(['services.openai.key' => null]);

        $response = $this->actingAs($user)->postJson(route('transcript-chat.ask'), [
            'question' => 'Tell me about the conversation?',
        ]);

        $response->assertOk();
        $this->assertStringContainsString('api key', strtolower($response->json('answer')));
    }
}
