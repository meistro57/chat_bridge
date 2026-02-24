<?php

namespace Tests\Unit;

use App\Services\AI\Data\AIResponse;
use App\Services\AI\Data\MessageData;
use App\Services\AI\Drivers\OpenAIDriver;
use App\Services\AI\Tools\ToolDefinition;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAIDriverTest extends TestCase
{
    public function test_chat_does_not_send_temperature(): void
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'OK']],
                ],
            ], 200),
        ]);

        $driver = new OpenAIDriver('test-key', 'gpt-4o-mini');
        $messages = collect([
            new MessageData('user', 'Hello'),
        ]);

        $result = $driver->chat($messages, 0.7);

        $this->assertInstanceOf(AIResponse::class, $result);
        $this->assertSame('OK', $result->content);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request): bool => ! array_key_exists('temperature', $request->data()));
    }

    public function test_stream_chat_does_not_send_temperature(): void
    {
        $streamBody = implode("\n", [
            'data: {"choices":[{"delta":{"content":"Hello "}}]}',
            '',
            'data: {"choices":[{"delta":{"content":"World"}}]}',
            '',
            'data: [DONE]',
            '',
        ]);

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response($streamBody, 200, ['Content-Type' => 'text/event-stream']),
        ]);

        $driver = new OpenAIDriver('test-key', 'gpt-4o-mini');
        $messages = collect([
            new MessageData('user', 'Hello'),
        ]);

        $chunks = iterator_to_array($driver->streamChat($messages, 0.7));

        $this->assertSame(['Hello ', 'World'], $chunks);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request): bool => ! array_key_exists('temperature', $request->data()));
    }

    public function test_chat_with_tools_does_not_send_temperature(): void
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'Toolless response']],
                ],
            ], 200),
        ]);

        $driver = new OpenAIDriver('test-key', 'gpt-4o-mini');
        $messages = collect([
            new MessageData('user', 'Hello'),
        ]);
        $tools = collect([
            new ToolDefinition(
                'search_history',
                'Search historical messages',
                [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                        ],
                    ],
                    'required' => ['query'],
                ],
                fn (array $args) => $args,
            ),
        ]);

        $result = $driver->chatWithTools($messages, $tools, 0.7);

        $this->assertArrayHasKey('response', $result);
        $this->assertInstanceOf(AIResponse::class, $result['response']);
        $this->assertSame('Toolless response', $result['response']->content);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request): bool => ! array_key_exists('temperature', $request->data()));
    }
}
