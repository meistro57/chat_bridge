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

    public function test_generate_turn_includes_attached_file_context_when_cross_chat_memory_is_disabled(): void
    {
        Storage::fake('local');
        config()->set('ai.tools_enabled', false);

        $user = User::factory()->create();
        $personaA = Persona::factory()->create();
        $personaB = Persona::factory()->create();
        $conversationId = (string) Str::uuid();
        $filePath = "session-rag/{$user->id}/{$conversationId}/briefing.md";
        Storage::disk('local')->put($filePath, 'Attached context: prioritize incident containment over feature work.');

        $conversation = Conversation::create([
            'id' => $conversationId,
            'user_id' => $user->id,
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
            'provider_a' => 'mock',
            'provider_b' => 'mock',
            'model_a' => null,
            'model_b' => null,
            'temp_a' => 1.0,
            'temp_b' => 1.0,
            'starter_message' => 'Use the attached references.',
            'status' => 'active',
            'metadata' => [
                'rag' => [
                    'enabled' => false,
                    'source_limit' => 6,
                    'score_threshold' => 0.3,
                    'system_prompt' => 'Rely on attached evidence when present.',
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

                $this->assertStringContainsString('RAG instruction: Rely on attached evidence when present.', $contents);
                $this->assertStringContainsString('Relevant template file excerpts:', $contents);
                $this->assertStringContainsString('Attached context: prioritize incident containment over feature work.', $contents);
                $this->assertStringNotContainsString('Relevant context from previous conversations:', $contents);

                return true;
            })
            ->andReturn(new \ArrayIterator(['ok']));
        $driver->shouldReceive('chat')->never();

        $ai = Mockery::mock(AIManager::class);
        $ai->shouldReceive('driverForProvider')
            ->once()
            ->andReturn($driver);

        $rag = Mockery::mock(RagService::class);
        $rag->shouldReceive('searchSimilarMessages')->never();

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

    public function test_generate_turn_includes_docx_template_rag_content_in_prompt(): void
    {
        if (! class_exists(\ZipArchive::class)) {
            $this->markTestSkipped('ZipArchive extension is required for DOCX extraction test.');
        }

        Storage::fake('local');
        config()->set('ai.tools_enabled', false);

        $user = User::factory()->create();
        $personaA = Persona::factory()->create();
        $personaB = Persona::factory()->create();
        $conversationId = (string) Str::uuid();
        $filePath = "template-rag/{$user->id}/template-123/brief.docx";
        Storage::disk('local')->put($filePath, $this->buildDocxBinary('Deployment runbook confirms blue-green rollback.'));

        $conversation = Conversation::create([
            'id' => $conversationId,
            'user_id' => $user->id,
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
            'provider_a' => 'mock',
            'provider_b' => 'mock',
            'model_a' => null,
            'model_b' => null,
            'temp_a' => 1.0,
            'temp_b' => 1.0,
            'starter_message' => 'Use the attached references.',
            'status' => 'active',
            'metadata' => [
                'rag' => [
                    'enabled' => true,
                    'source_limit' => 6,
                    'score_threshold' => 0.3,
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

                $this->assertStringContainsString('Relevant template file excerpts:', $contents);
                $this->assertStringContainsString('Deployment runbook confirms blue-green rollback.', $contents);

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
            ->andReturn(collect());

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
            'metadata' => ['rag' => ['enabled' => false]],
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
            'metadata' => ['rag' => ['enabled' => false]],
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
            'metadata' => ['rag' => ['enabled' => false]],
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
            'metadata' => ['rag' => ['enabled' => false]],
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
            'metadata' => ['rag' => ['enabled' => false]],
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

    public function test_generate_turn_falls_back_to_standard_generation_when_tool_mode_exhausts_empty_attempts(): void
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
            'metadata' => ['rag' => ['enabled' => false]],
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
        $driver->shouldReceive('streamChat')
            ->once()
            ->andReturn(new \ArrayIterator(['Recovered after tool failure']));

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

        $this->assertSame(['Recovered after tool failure'], $chunks);
    }

    public function test_generate_turn_truncates_tool_results_and_uses_clean_messages_for_standard_fallback(): void
    {
        config()->set('services.qdrant.enabled', false);
        config()->set('ai.tools_enabled', true);
        config()->set('ai.tool_result_max_chars', 220);
        config()->set('ai.tool_result_entry_max_chars', 180);

        $user = User::factory()->create();
        $personaA = Persona::factory()->create();
        $personaB = Persona::factory()->create();

        $conversation = Conversation::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
            'provider_a' => 'anthropic',
            'provider_b' => 'anthropic',
            'model_a' => null,
            'model_b' => null,
            'temp_a' => 0.7,
            'temp_b' => 0.7,
            'starter_message' => 'Hello',
            'status' => 'active',
            'metadata' => ['rag' => ['enabled' => false]],
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
            ->andReturnUsing(function (Collection $messages, Collection $tools, float $temperature) {
                $this->assertSame(0.7, $temperature);
                $this->assertCount(0, $tools);
                $contents = $messages->pluck('content')->implode("\n");

                $this->assertStringNotContainsString('Tool execution results:', $contents);

                return [
                    'response' => null,
                    'tool_calls' => [
                        ['id' => 'call-1', 'name' => 'get_contextual_memory', 'arguments' => ['topic' => 'abc']],
                    ],
                ];
            });
        $driver->shouldReceive('streamChat')
            ->once()
            ->withArgs(function (Collection $messages): bool {
                $contents = $messages->pluck('content')->implode("\n");

                $this->assertStringNotContainsString('Tool execution results:', $contents);

                return true;
            })
            ->andReturn(new \ArrayIterator(['Recovered via clean fallback']));
        $driver->shouldReceive('chat')->never();

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

        $result = $service->generateTurn($conversation, $personaA, new Collection([
            new MessageData('user', 'Please answer this.'),
        ]));

        $this->assertSame(['Recovered via clean fallback'], iterator_to_array($result['content']));
    }

    public function test_generate_turn_drops_oldest_history_when_prompt_budget_is_exceeded(): void
    {
        config()->set('services.qdrant.enabled', false);
        config()->set('ai.tools_enabled', false);
        config()->set('ai.prompt_char_budgets.anthropic', 1300);

        $user = User::factory()->create();
        $personaA = Persona::factory()->create();
        $personaB = Persona::factory()->create();

        $conversation = Conversation::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
            'provider_a' => 'anthropic',
            'provider_b' => 'anthropic',
            'model_a' => null,
            'model_b' => null,
            'temp_a' => 0.7,
            'temp_b' => 0.7,
            'starter_message' => 'Hello',
            'status' => 'active',
            'metadata' => ['rag' => ['enabled' => false]],
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
            ->withArgs(function (Collection $messages): bool {
                $contents = $messages->pluck('content')->implode("\n");

                $this->assertStringContainsString('Newest message should remain', $contents);
                $this->assertStringNotContainsString('Oldest message should be removed', $contents);

                return true;
            })
            ->andReturn(new \ArrayIterator(['ok']));
        $driver->shouldReceive('chat')->never();

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

        $history = new Collection([
            new MessageData('user', 'Oldest message should be removed '.str_repeat('a', 1100)),
            new MessageData('assistant', 'Newest message should remain '.str_repeat('b', 80), $personaB->name),
        ]);

        $result = $service->generateTurn($conversation, $personaA, $history);

        $this->assertSame(['ok'], iterator_to_array($result['content']));
    }

    private function buildDocxBinary(string $text): string
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'rag-docx-test-');

        if ($tempPath === false) {
            throw new \RuntimeException('Unable to allocate temporary DOCX path.');
        }

        $zip = new \ZipArchive;
        $opened = $zip->open($tempPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        if ($opened !== true) {
            @unlink($tempPath);

            throw new \RuntimeException('Unable to create temporary DOCX archive.');
        }

        $zip->addFromString('word/document.xml', sprintf(
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>%s</w:t></w:r></w:p></w:body></w:document>',
            htmlspecialchars($text, ENT_XML1)
        ));
        $zip->close();

        $binary = file_get_contents($tempPath);
        @unlink($tempPath);

        if (! is_string($binary) || $binary === '') {
            throw new \RuntimeException('Failed to read generated DOCX binary content.');
        }

        return $binary;
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
