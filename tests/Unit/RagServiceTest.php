<?php

namespace Tests\Unit;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Persona;
use App\Models\User;
use App\Services\AI\EmbeddingService;
use App\Services\RagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class RagServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_rag_search_respects_user_filter_and_similarity_order(): void
    {
        config([
            'ai.rag_driver' => 'database',
            'services.qdrant.enabled' => false,
        ]);

        $embeddingService = Mockery::mock(EmbeddingService::class);
        $embeddingService->shouldReceive('getEmbedding')
            ->once()
            ->with('How did we deploy?')
            ->andReturn([1.0, 0.0, 0.0]);
        $this->app->instance(EmbeddingService::class, $embeddingService);

        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $ownerPersona = Persona::factory()->create(['user_id' => $owner->id]);
        $otherPersona = Persona::factory()->create(['user_id' => $otherUser->id]);

        $ownerConversation = Conversation::factory()->create([
            'user_id' => $owner->id,
            'persona_a_id' => $ownerPersona->id,
            'persona_b_id' => $ownerPersona->id,
        ]);
        $otherConversation = Conversation::factory()->create([
            'user_id' => $otherUser->id,
            'persona_a_id' => $otherPersona->id,
            'persona_b_id' => $otherPersona->id,
        ]);

        $bestMatch = Message::factory()->create([
            'conversation_id' => $ownerConversation->id,
            'persona_id' => $ownerPersona->id,
            'role' => 'assistant',
            'content' => 'We deployed with Docker Compose.',
            'embedding' => [1.0, 0.0, 0.0],
        ]);

        $lowerMatch = Message::factory()->create([
            'conversation_id' => $ownerConversation->id,
            'persona_id' => $ownerPersona->id,
            'role' => 'assistant',
            'content' => 'Deployment used blue-green cutover.',
            'embedding' => [0.6, 0.4, 0.0],
        ]);

        Message::factory()->create([
            'conversation_id' => $otherConversation->id,
            'persona_id' => $otherPersona->id,
            'role' => 'assistant',
            'content' => 'This should never leak across users.',
            'embedding' => [1.0, 0.0, 0.0],
        ]);

        $results = app(RagService::class)->searchSimilarMessages(
            query: 'How did we deploy?',
            limit: 5,
            filter: ['user_id' => $owner->id],
            scoreThreshold: 0.3
        );

        $this->assertCount(2, $results);
        $this->assertSame($bestMatch->id, $results->first()->id);
        $this->assertSame($lowerMatch->id, $results->last()->id);
        $this->assertGreaterThan(
            $results->last()->similarity_score,
            $results->first()->similarity_score
        );
        $this->assertTrue(
            $results->every(fn (Message $message) => $message->conversation->user_id === $owner->id)
        );
    }

    public function test_auto_driver_falls_back_to_database_when_qdrant_is_disabled(): void
    {
        config([
            'ai.rag_driver' => 'auto',
            'services.qdrant.enabled' => false,
        ]);

        $embeddingService = Mockery::mock(EmbeddingService::class);
        $embeddingService->shouldReceive('getEmbedding')
            ->once()
            ->with('What is next?')
            ->andReturn([0.0, 1.0, 0.0]);
        $this->app->instance(EmbeddingService::class, $embeddingService);

        $user = User::factory()->create();
        $persona = Persona::factory()->create(['user_id' => $user->id]);
        $conversation = Conversation::factory()->create([
            'user_id' => $user->id,
            'persona_a_id' => $persona->id,
            'persona_b_id' => $persona->id,
        ]);

        $matchingMessage = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'persona_id' => $persona->id,
            'role' => 'assistant',
            'content' => 'Next step is to ship the release.',
            'embedding' => [0.0, 1.0, 0.0],
        ]);

        $results = app(RagService::class)->searchSimilarMessages(
            query: 'What is next?',
            limit: 3,
            filter: ['conversation_id' => $conversation->id, 'role' => 'assistant'],
            scoreThreshold: 0.5
        );

        $this->assertCount(1, $results);
        $this->assertSame($matchingMessage->id, $results->first()->id);
    }
}
