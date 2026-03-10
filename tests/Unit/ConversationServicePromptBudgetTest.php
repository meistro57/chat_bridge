<?php

namespace Tests\Unit;

use App\Services\AI\Data\MessageData;
use App\Services\AI\EmbeddingService;
use App\Services\AI\StreamingChunker;
use App\Services\AI\Tools\ToolExecutor;
use App\Services\AI\TranscriptService;
use App\Services\ConversationService;
use App\Services\RagService;
use Illuminate\Support\Collection;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

class ConversationServicePromptBudgetTest extends TestCase
{
    public function test_fit_messages_within_prompt_budget_discards_oldest_non_system_messages(): void
    {
        config()->set('ai.prompt_char_budgets.anthropic', 120);

        $service = $this->makeService();
        $method = new ReflectionMethod($service, 'fitMessagesWithinPromptBudget');
        $method->setAccessible(true);

        $messages = new Collection([
            new MessageData('system', 'System prompt'),
            new MessageData('user', str_repeat('A', 1100)),
            new MessageData('assistant', 'Newest response', 'Persona'),
        ]);

        $result = $method->invoke($service, $messages, 'anthropic');

        $this->assertCount(2, $result);
        $this->assertSame('system', $result[0]->role);
        $this->assertSame('assistant', $result[1]->role);
        $this->assertSame('Newest response', $result[1]->content);
    }

    public function test_format_tool_results_for_prompt_truncates_large_results(): void
    {
        config()->set('ai.tool_result_max_entries', 1);
        config()->set('ai.tool_result_entry_max_chars', 80);
        config()->set('ai.tool_result_max_chars', 120);

        $service = $this->makeService();
        $method = new ReflectionMethod($service, 'formatToolResultsForPrompt');
        $method->setAccessible(true);

        $result = $method->invoke($service, [[
            'tool_call_id' => 'call-1',
            'tool_name' => 'get_contextual_memory',
            'result' => ['payload' => str_repeat('x', 500)],
            'error' => null,
        ]]);

        $this->assertStringStartsWith('Tool execution results:', $result);
        $this->assertStringContainsString('Tool: get_contextual_memory', $result);
        $this->assertLessThanOrEqual(123, strlen($result));
        $this->assertStringContainsString('...', $result);
    }

    private function makeService(): ConversationService
    {
        return new ConversationService(
            ai: Mockery::mock(\App\Services\AI\AIManager::class),
            transcripts: Mockery::mock(TranscriptService::class),
            embeddings: Mockery::mock(EmbeddingService::class),
            rag: Mockery::mock(RagService::class),
            toolExecutor: Mockery::mock(ToolExecutor::class),
            streamingChunker: new StreamingChunker
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
