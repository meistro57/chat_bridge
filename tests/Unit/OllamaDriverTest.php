<?php

namespace Tests\Unit;

use App\Services\AI\Data\AIResponse;
use App\Services\AI\Data\MessageData;
use App\Services\AI\Drivers\OllamaDriver;
use App\Services\AI\Tools\ToolDefinition;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OllamaDriverTest extends TestCase
{
    public function test_chat_with_tools_returns_tool_calls(): void
    {
        Http::fake([
            'http://localhost:11434/api/chat' => Http::response([
                'message' => [
                    'role' => 'assistant',
                    'content' => '',
                    'tool_calls' => [
                        [
                            'function' => [
                                'name' => 'get_mcp_stats',
                                'arguments' => '{"limit":5}',
                            ],
                        ],
                    ],
                ],
                'prompt_eval_count' => 10,
                'eval_count' => 20,
            ], 200),
        ]);

        $driver = new OllamaDriver(model: 'gpt-oss:20b', baseUrl: 'http://localhost:11434');
        $messages = collect([
            new MessageData('user', 'Show me stats'),
        ]);
        $tools = collect([
            new ToolDefinition(
                name: 'get_mcp_stats',
                description: 'Get MCP stats',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer'],
                    ],
                    'required' => [],
                ],
                executor: fn (array $args) => $args,
            ),
        ]);

        $result = $driver->chatWithTools($messages, $tools);

        $this->assertNull($result['response']);
        $this->assertCount(1, $result['tool_calls']);
        $this->assertSame('get_mcp_stats', $result['tool_calls'][0]['name']);
        $this->assertSame(['limit' => 5], $result['tool_calls'][0]['arguments']);
        $this->assertNotEmpty($result['tool_calls'][0]['id']);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request): bool => array_key_exists('tools', $request->data()));
    }

    public function test_chat_with_tools_returns_text_response_without_tool_calls(): void
    {
        Http::fake([
            'http://localhost:11434/api/chat' => Http::response([
                'message' => [
                    'role' => 'assistant',
                    'content' => 'Tool summary response',
                ],
                'prompt_eval_count' => 8,
                'eval_count' => 12,
            ], 200),
        ]);

        $driver = new OllamaDriver(model: 'gpt-oss:20b', baseUrl: 'http://localhost:11434');
        $messages = collect([
            new MessageData('user', 'Summarize tool results'),
        ]);
        $tools = collect();

        $result = $driver->chatWithTools($messages, $tools);

        $this->assertInstanceOf(AIResponse::class, $result['response']);
        $this->assertSame('Tool summary response', $result['response']->content);
        $this->assertSame([], $result['tool_calls']);
    }

    public function test_supports_tools_returns_true(): void
    {
        $driver = new OllamaDriver;

        $this->assertTrue($driver->supportsTools());
    }
}
