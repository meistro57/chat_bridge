<?php

namespace Tests\Unit;

use App\Models\Conversation;
use App\Models\Persona;
use App\Models\User;
use App\Services\AI\AIManager;
use App\Services\AI\Contracts\AIDriverInterface;
use App\Services\AI\Data\AIResponse;
use App\Services\AI\EmbeddingService;
use App\Services\AI\StreamingChunker;
use App\Services\AI\Tools\ToolExecutor;
use App\Services\AI\TranscriptService;
use App\Services\ConversationService;
use App\Services\RagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class ConversationServiceStreamingFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_turn_falls_back_to_chat_when_stream_is_empty(): void
    {
        config()->set('services.qdrant.enabled', false);

        $user = User::factory()->create();
        $personaA = Persona::factory()->create();
        $personaB = Persona::factory()->create();

        $conversation = Conversation::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
            'provider_a' => 'mock',
            'provider_b' => 'mock',
            'model_a' => null,
            'model_b' => null,
            'temp_a' => 0.7,
            'temp_b' => 0.7,
            'starter_message' => 'Hello',
            'status' => 'active',
            'metadata' => [],
            'max_rounds' => 3,
            'stop_word_detection' => false,
            'stop_words' => [],
            'stop_word_threshold' => 0.8,
        ]);

        $driver = Mockery::mock(AIDriverInterface::class);
        $driver->shouldReceive('streamChat')
            ->once()
            ->andReturn(new \ArrayIterator([]));
        $driver->shouldReceive('chat')
            ->once()
            ->andReturn(new AIResponse('fallback response'));
        $driver->shouldReceive('supportsTools')
            ->once()
            ->andReturn(false);

        $ai = Mockery::mock(AIManager::class);
        $ai->shouldReceive('driverForProvider')
            ->once()
            ->andReturn($driver);

        $service = new ConversationService(
            ai: $ai,
            transcripts: Mockery::mock(TranscriptService::class),
            embeddings: Mockery::mock(EmbeddingService::class),
            rag: Mockery::mock(RagService::class),
            toolExecutor: Mockery::mock(ToolExecutor::class),
            streamingChunker: new StreamingChunker
        );

        $result = $service->generateTurn($conversation, $personaA, new Collection);
        $chunks = iterator_to_array($result['content']);

        $this->assertSame(['fallback response'], $chunks);
    }

    public function test_generate_turn_streams_tools_response_in_smaller_chunks(): void
    {
        config()->set('services.qdrant.enabled', false);
        config()->set('ai.tools_enabled', true);
        config()->set('ai.stream_chunk_size', 1000);

        $user = User::factory()->create();
        $personaA = Persona::factory()->create();
        $personaB = Persona::factory()->create();

        $conversation = Conversation::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
            'provider_a' => 'openai',
            'provider_b' => 'openai',
            'model_a' => null,
            'model_b' => null,
            'temp_a' => 0.7,
            'temp_b' => 0.7,
            'starter_message' => 'Hello',
            'status' => 'active',
            'metadata' => [],
            'max_rounds' => 3,
            'stop_word_detection' => false,
            'stop_words' => [],
            'stop_word_threshold' => 0.8,
        ]);

        $driver = Mockery::mock(AIDriverInterface::class);
        $driver->shouldReceive('supportsTools')
            ->once()
            ->andReturn(true);
        $driver->shouldReceive('chatWithTools')
            ->once()
            ->andReturn([
                'response' => new AIResponse(str_repeat('a', 240)),
                'tool_calls' => [],
            ]);

        $ai = Mockery::mock(AIManager::class);
        $ai->shouldReceive('driverForProvider')
            ->once()
            ->andReturn($driver);

        $toolExecutor = Mockery::mock(ToolExecutor::class);
        $toolExecutor->shouldReceive('getAllTools')
            ->once()
            ->andReturn(collect());

        $service = new ConversationService(
            ai: $ai,
            transcripts: Mockery::mock(TranscriptService::class),
            embeddings: Mockery::mock(EmbeddingService::class),
            rag: Mockery::mock(RagService::class),
            toolExecutor: $toolExecutor,
            streamingChunker: new StreamingChunker
        );

        $result = $service->generateTurn($conversation, $personaA, new Collection);
        $chunks = iterator_to_array($result['content']);

        $this->assertCount(2, $chunks);
        $this->assertSame(120, strlen($chunks[0]));
        $this->assertSame(120, strlen($chunks[1]));
        $this->assertSame(str_repeat('a', 240), implode('', $chunks));
    }

    public function test_generate_turn_retries_tool_response_when_first_response_is_empty(): void
    {
        config()->set('services.qdrant.enabled', false);
        config()->set('ai.tools_enabled', true);

        $user = User::factory()->create();
        $personaA = Persona::factory()->create();
        $personaB = Persona::factory()->create();

        $conversation = Conversation::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
            'provider_a' => 'openai',
            'provider_b' => 'openai',
            'model_a' => null,
            'model_b' => null,
            'temp_a' => 0.7,
            'temp_b' => 0.7,
            'starter_message' => 'Hello',
            'status' => 'active',
            'metadata' => [],
            'max_rounds' => 3,
            'stop_word_detection' => false,
            'stop_words' => [],
            'stop_word_threshold' => 0.8,
        ]);

        $driver = Mockery::mock(AIDriverInterface::class);
        $driver->shouldReceive('supportsTools')
            ->once()
            ->andReturn(true);
        $driver->shouldReceive('chatWithTools')
            ->twice()
            ->andReturn(
                ['response' => new AIResponse(''), 'tool_calls' => []],
                ['response' => new AIResponse('Recovered response'), 'tool_calls' => []]
            );

        $ai = Mockery::mock(AIManager::class);
        $ai->shouldReceive('driverForProvider')
            ->once()
            ->andReturn($driver);

        $toolExecutor = Mockery::mock(ToolExecutor::class);
        $toolExecutor->shouldReceive('getAllTools')
            ->once()
            ->andReturn(collect());

        $service = new ConversationService(
            ai: $ai,
            transcripts: Mockery::mock(TranscriptService::class),
            embeddings: Mockery::mock(EmbeddingService::class),
            rag: Mockery::mock(RagService::class),
            toolExecutor: $toolExecutor,
            streamingChunker: new StreamingChunker
        );

        $result = $service->generateTurn($conversation, $personaA, new Collection);
        $chunks = iterator_to_array($result['content']);

        $this->assertSame(['Recovered response'], $chunks);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
