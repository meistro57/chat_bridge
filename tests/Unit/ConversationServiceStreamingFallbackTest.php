<?php

namespace Tests\Unit;

use App\Models\Conversation;
use App\Models\Persona;
use App\Models\User;
use App\Services\AI\AIManager;
use App\Services\AI\Contracts\AIDriverInterface;
use App\Services\AI\Data\AIResponse;
use App\Services\AI\Data\MessageData;
use App\Services\AI\EmbeddingService;
use App\Services\AI\StreamingChunker;
use App\Services\AI\Tools\ToolExecutor;
use App\Services\AI\TranscriptService;
use App\Services\ConversationService;
use App\Services\RagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class ConversationServiceStreamingFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_turn_applies_template_rag_settings_and_file_context(): void
    {
        Storage::fake('local');
        config()->set('ai.tools_enabled', false);
        config()->set('ai.rag_template_max_files', 3);
        config()->set('ai.rag_template_max_chars', 800);

        $user = User::factory()->create();
        $personaA = Persona::factory()->create();
        $personaB = Persona::factory()->create();
        $conversationId = (string) Str::uuid();
        $filePath = "template-rag/{$user->id}/template-123/facts.txt";
        Storage::disk('local')->put($filePath, 'Mars has two moons: Phobos and Deimos.');

        $conversation = Conversation::create([
            'id' => $conversationId,
            'user_id' => $user->id,
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
            'provider_a' => 'mock',
            'provider_b' => 'mock',
            'model_a' => null,
            'model_b' => null,
            'temp_a' => 0.7,
            'temp_b' => 0.7,
            'starter_message' => 'Use the attached references.',
            'status' => 'active',
            'metadata' => [
                'rag' => [
                    'enabled' => true,
                    'source_limit' => 8,
                    'score_threshold' => 0.31,
                    'system_prompt' => 'Ground responses in retrieved context.',
                    'files' => [$filePath],
                ],
            ],
            'max_rounds' => 3,
            'stop_word_detection' => false,
            'stop_words' => [],
            'stop_word_threshold' => 0.8,
        ]);

        $driver = Mockery::mock(AIDriverInterface::class);
        $driver->shouldReceive('supportsTools')
            ->once()
            ->andReturn(false);
        $driver->shouldReceive('streamChat')
            ->once()
            ->withArgs(function (Collection $messages, float $temperature): bool {
                $this->assertSame(1.0, $temperature);

                $contents = $messages
                    ->map(fn (MessageData $message) => $message->content)
                    ->implode("\n\n");

                $this->assertStringContainsString('RAG instruction: Ground responses in retrieved context.', $contents);
                $this->assertStringContainsString('Relevant template file excerpts:', $contents);
                $this->assertStringContainsString('Mars has two moons: Phobos and Deimos.', $contents);

                return true;
            })
            ->andReturn(new \ArrayIterator(['ok']));
        $driver->shouldReceive('chat')->never();

        $ai = Mockery::mock(AIManager::class);
        $ai->shouldReceive('driverForProvider')
            ->once()
            ->andReturn($driver);

        $rag = Mockery::mock(RagService::class);
        $rag->shouldReceive('searchSimilarMessages')
            ->once()
            ->withArgs(function (string $query, int $limit, array $filter, float $scoreThreshold) use ($personaA, $user): bool {
                $this->assertSame('latest prompt for retrieval', $query);
                $this->assertSame(8, $limit);
                $this->assertSame($personaA->id, $filter['persona_id'] ?? null);
                $this->assertSame($user->id, $filter['user_id'] ?? null);
                $this->assertEqualsWithDelta(0.31, $scoreThreshold, 0.0001);

                return true;
            })
            ->andReturn(collect([
                (object) [
                    'id' => 99,
                    'conversation_id' => $conversation->id,
                    'created_at' => now()->subMinute(),
                    'content' => 'Should be excluded because this is current conversation.',
                ],
            ]));

        $service = new ConversationService(
            ai: $ai,
            transcripts: Mockery::mock(TranscriptService::class),
            embeddings: Mockery::mock(EmbeddingService::class),
            rag: $rag,
            toolExecutor: Mockery::mock(ToolExecutor::class),
            streamingChunker: new StreamingChunker
        );

        $history = collect([
            new MessageData('assistant', 'latest prompt for retrieval', $personaB->name),
        ]);

        $result = $service->generateTurn($conversation, $personaA, $history);
        $chunks = iterator_to_array($result['content']);

        $this->assertSame(['ok'], $chunks);
    }

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

    public function test_generate_turn_falls_back_to_chat_when_stream_is_whitespace_only(): void
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
            ->andReturn(new \ArrayIterator(['   ', "\n"]));
        $driver->shouldReceive('chat')
            ->once()
            ->andReturn(new AIResponse('Recovered after whitespace stream'));
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

        $this->assertSame("   \nRecovered after whitespace stream", implode('', $chunks));
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
        $driver->shouldReceive('chat')
            ->once()
            ->andReturn(new AIResponse(''));
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

    public function test_generate_turn_uses_plain_chat_fallback_for_empty_tool_response(): void
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
            ->once()
            ->andReturn(['response' => new AIResponse(''), 'tool_calls' => []]);
        $driver->shouldReceive('chat')
            ->once()
            ->andReturn(new AIResponse('Fallback recovery'));

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

        $this->assertSame(['Fallback recovery'], $chunks);
    }

    public function test_generate_turn_throws_when_all_tool_attempts_are_empty(): void
    {
        config()->set('services.qdrant.enabled', false);
        config()->set('ai.tools_enabled', true);
        config()->set('ai.max_tool_iterations', 1);

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
            'temp_a' => 1.0,
            'temp_b' => 1.0,
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
            ->andReturn(['response' => new AIResponse(''), 'tool_calls' => []]);
        $driver->shouldReceive('chat')
            ->twice()
            ->andReturn(new AIResponse(''), new AIResponse(''));

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

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Tool-enabled generation remained empty');
        $service->generateTurn($conversation, $personaA, new Collection);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
