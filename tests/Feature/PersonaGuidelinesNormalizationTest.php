<?php

namespace Tests\Feature;

use App\Jobs\ProcessConversationTurn;
use App\Models\Conversation;
use App\Models\Persona;
use App\Services\AI\AIManager;
use App\Services\AI\Contracts\AIDriverInterface;
use App\Services\AI\Data\AIResponse;
use App\Services\AI\Data\MessageData;
use App\Services\AI\EmbeddingService;
use App\Services\AI\StopWordService;
use App\Services\AI\StreamingChunker;
use App\Services\AI\Tools\ToolExecutor;
use App\Services\AI\TranscriptService;
use App\Services\ConversationService;
use App\Services\RagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class PersonaGuidelinesNormalizationTest extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function mockDriver(iterable $chunks = []): AIDriverInterface
    {
        $driver = Mockery::mock(AIDriverInterface::class);
        $driver->shouldReceive('supportsTools')->andReturn(false);
        $driver->shouldReceive('streamChat')->andReturn($chunks);
        $driver->shouldReceive('chat')->andReturn(new AIResponse('fallback response', 0));
        $driver->shouldReceive('getLastTokenUsage')->andReturn(null);

        return $driver;
    }

    private function mockManager(AIDriverInterface $driver): AIManager
    {
        $manager = Mockery::mock(AIManager::class);
        $manager->shouldReceive('driverForProvider')->andReturn($driver);

        return $manager;
    }

    /**
     * Bypass the Eloquent cast and write a raw value directly to the DB.
     * This simulates the production state where legacy data contains JSON strings.
     */
    private function forceRawGuidelines(string $personaId, string $rawValue): void
    {
        DB::table('personas')->where('id', $personaId)->update(['guidelines' => $rawValue]);
    }

    private function makeConversationService(AIManager $manager): ConversationService
    {
        return new ConversationService(
            $manager,
            app(TranscriptService::class),
            app(EmbeddingService::class),
            app(RagService::class),
            app(ToolExecutor::class),
            app(StreamingChunker::class),
        );
    }

    // ──────────────────────────────────────────────────────────────────────────
    // ProcessConversationTurn — guidelines normalization
    // ──────────────────────────────────────────────────────────────────────────

    public function test_process_turn_does_not_crash_with_array_guidelines(): void
    {
        $persona = Persona::factory()->create(['guidelines' => ['Be concise', 'Stay professional']]);
        $conversation = Conversation::factory()->create(['persona_a_id' => $persona->id, 'status' => 'active']);
        $conversation->messages()->create(['role' => 'user', 'content' => 'Hello']);

        $job = new ProcessConversationTurn($conversation->id, 1, 1);
        $job->handle($this->mockManager($this->mockDriver(['great answer'])), app(StopWordService::class), app(TranscriptService::class));

        $this->assertDatabaseHas('messages', ['conversation_id' => $conversation->id, 'role' => 'assistant']);
    }

    public function test_process_turn_does_not_crash_with_null_guidelines(): void
    {
        $persona = Persona::factory()->create(['guidelines' => null]);
        $conversation = Conversation::factory()->create(['persona_a_id' => $persona->id, 'status' => 'active']);
        $conversation->messages()->create(['role' => 'user', 'content' => 'Hello']);

        $job = new ProcessConversationTurn($conversation->id, 1, 1);
        $job->handle($this->mockManager($this->mockDriver(['great answer'])), app(StopWordService::class), app(TranscriptService::class));

        $this->assertDatabaseHas('messages', ['conversation_id' => $conversation->id, 'role' => 'assistant']);
    }

    public function test_process_turn_does_not_crash_with_raw_json_string_guidelines(): void
    {
        // Simulate the production bug: JSON string in the column instead of a JSON array.
        $persona = Persona::factory()->create();
        $this->forceRawGuidelines($persona->id, '"Be concise and professional."');
        $persona->refresh();

        $conversation = Conversation::factory()->create(['persona_a_id' => $persona->id, 'status' => 'active']);
        $conversation->messages()->create(['role' => 'user', 'content' => 'Hello']);

        $job = new ProcessConversationTurn($conversation->id, 1, 1);

        // Must not throw — was crashing with "foreach argument must be type array|object, string given".
        $job->handle($this->mockManager($this->mockDriver(['great answer'])), app(StopWordService::class), app(TranscriptService::class));

        $this->assertTrue(true);
    }

    public function test_process_turn_does_not_crash_with_multi_line_string_guidelines(): void
    {
        // Another production variant: newline-delimited bullet points stored as a JSON string.
        $persona = Persona::factory()->create();
        $this->forceRawGuidelines($persona->id, '"- Be concise\n- Stay professional\n- Use examples"');
        $persona->refresh();

        $conversation = Conversation::factory()->create(['persona_a_id' => $persona->id, 'status' => 'active']);
        $conversation->messages()->create(['role' => 'user', 'content' => 'Hello']);

        $job = new ProcessConversationTurn($conversation->id, 1, 1);
        $job->handle($this->mockManager($this->mockDriver(['great answer'])), app(StopWordService::class), app(TranscriptService::class));

        $this->assertTrue(true);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // ConversationService — normalizeToArray via generateTurn
    // ──────────────────────────────────────────────────────────────────────────

    public function test_conversation_service_does_not_crash_with_array_guidelines(): void
    {
        $persona = Persona::factory()->create(['guidelines' => ['Be thorough']]);
        // Disable RAG so no embedding calls are made.
        $conversation = Conversation::factory()->create([
            'persona_a_id' => $persona->id,
            'metadata' => ['rag' => ['enabled' => false]],
        ]);

        $driver = $this->mockDriver((function () { yield 'hello'; })());
        $service = $this->makeConversationService($this->mockManager($driver));

        $history = collect([new MessageData('user', 'Hello')]);
        $result = $service->generateTurn($conversation, $persona, $history);

        $content = '';
        foreach ($result['content'] as $chunk) {
            $content .= $chunk;
        }

        $this->assertSame('hello', $content);
    }

    public function test_conversation_service_does_not_crash_with_raw_json_string_guidelines(): void
    {
        // Simulate the exact production failure: JSON string in the column.
        $persona = Persona::factory()->create();
        $this->forceRawGuidelines($persona->id, '"Evaluate each idea fairly."');
        $persona->refresh();

        $conversation = Conversation::factory()->create([
            'persona_a_id' => $persona->id,
            'metadata' => ['rag' => ['enabled' => false]],
        ]);

        $driver = $this->mockDriver((function () { yield 'hello'; })());
        $service = $this->makeConversationService($this->mockManager($driver));

        $history = collect([new MessageData('user', 'Hello')]);
        $result = $service->generateTurn($conversation, $persona, $history);

        $content = '';
        foreach ($result['content'] as $chunk) {
            $content .= $chunk;
        }

        $this->assertNotEmpty($content);
    }

    public function test_conversation_service_does_not_crash_with_null_guidelines(): void
    {
        $persona = Persona::factory()->create(['guidelines' => null]);
        $conversation = Conversation::factory()->create([
            'persona_a_id' => $persona->id,
            'metadata' => ['rag' => ['enabled' => false]],
        ]);

        $driver = $this->mockDriver((function () { yield 'hello'; })());
        $service = $this->makeConversationService($this->mockManager($driver));

        $history = collect([new MessageData('user', 'Hello')]);
        $result = $service->generateTurn($conversation, $persona, $history);

        $content = '';
        foreach ($result['content'] as $chunk) {
            $content .= $chunk;
        }

        $this->assertSame('hello', $content);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // RAG file context — string vs array files payload
    // ──────────────────────────────────────────────────────────────────────────

    public function test_conversation_service_does_not_crash_when_rag_files_is_a_string(): void
    {
        $persona = Persona::factory()->create(['guidelines' => []]);
        $conversation = Conversation::factory()->create([
            'persona_a_id' => $persona->id,
            'metadata' => [
                'rag' => [
                    'enabled' => false,
                    // Malformed: single string instead of an array of paths.
                    'files' => 'session-rag/1/document.pdf',
                ],
            ],
        ]);

        $driver = $this->mockDriver((function () { yield 'hello'; })());
        $service = $this->makeConversationService($this->mockManager($driver));

        $history = collect([new MessageData('user', 'Hello')]);
        $result = $service->generateTurn($conversation, $persona, $history);

        $content = '';
        foreach ($result['content'] as $chunk) {
            $content .= $chunk;
        }

        $this->assertNotEmpty($content);
    }

    public function test_conversation_service_does_not_crash_when_rag_files_is_an_array(): void
    {
        $persona = Persona::factory()->create(['guidelines' => []]);
        $conversation = Conversation::factory()->create([
            'persona_a_id' => $persona->id,
            'metadata' => [
                'rag' => [
                    'enabled' => false,
                    'files' => ['session-rag/1/document.pdf', 'session-rag/1/notes.txt'],
                ],
            ],
        ]);

        $driver = $this->mockDriver((function () { yield 'hello'; })());
        $service = $this->makeConversationService($this->mockManager($driver));

        $history = collect([new MessageData('user', 'Hello')]);
        $result = $service->generateTurn($conversation, $persona, $history);

        $content = '';
        foreach ($result['content'] as $chunk) {
            $content .= $chunk;
        }

        $this->assertNotEmpty($content);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Data migration — existing string guidelines are normalised
    // ──────────────────────────────────────────────────────────────────────────

    public function test_migration_converts_json_string_guidelines_to_single_element_array(): void
    {
        $persona = Persona::factory()->create();
        $this->forceRawGuidelines($persona->id, '"Be concise and professional."');

        // Run the migration logic inline.
        DB::table('personas')
            ->whereNotNull('guidelines')
            ->get(['id', 'guidelines'])
            ->each(function (object $row): void {
                $decoded = json_decode($row->guidelines, true);
                if (! is_string($decoded)) {
                    return;
                }
                DB::table('personas')
                    ->where('id', $row->id)
                    ->update(['guidelines' => json_encode([$decoded])]);
            });

        $persona->refresh();

        $this->assertIsArray($persona->guidelines);
        $this->assertCount(1, $persona->guidelines);
        $this->assertSame('Be concise and professional.', $persona->guidelines[0]);
    }

    public function test_migration_leaves_array_guidelines_untouched(): void
    {
        $persona = Persona::factory()->create(['guidelines' => ['Be concise', 'Stay professional']]);

        // Run the migration logic inline.
        DB::table('personas')
            ->whereNotNull('guidelines')
            ->get(['id', 'guidelines'])
            ->each(function (object $row): void {
                $decoded = json_decode($row->guidelines, true);
                if (! is_string($decoded)) {
                    return;
                }
                DB::table('personas')
                    ->where('id', $row->id)
                    ->update(['guidelines' => json_encode([$decoded])]);
            });

        $persona->refresh();

        $this->assertIsArray($persona->guidelines);
        $this->assertCount(2, $persona->guidelines);
        $this->assertSame(['Be concise', 'Stay professional'], $persona->guidelines);
    }
}
