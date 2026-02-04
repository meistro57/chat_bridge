<?php

namespace Tests\Unit;

use App\Services\AI\Data\MessageData;
use App\Services\AI\Drivers\AnthropicDriver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class AnthropicDriverTest extends TestCase
{
    public function test_chat_returns_text_content(): void
    {
        Http::fake([
            'https://api.anthropic.com/v1/messages' => Http::response([
                'content' => [
                    ['type' => 'text', 'text' => 'Hello!'],
                ],
            ], 200),
        ]);

        $driver = new AnthropicDriver('test-key');
        $messages = new Collection([new MessageData('user', 'Hi')]);

        $response = $driver->chat($messages, 0.7);

        $this->assertSame('Hello!', $response);
    }

    public function test_chat_returns_empty_string_when_content_empty(): void
    {
        Http::fake([
            'https://api.anthropic.com/v1/messages' => Http::response([
                'id' => 'msg_123',
                'model' => 'claude-test',
                'content' => [],
                'stop_reason' => 'end_turn',
            ], 200),
        ]);

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'Anthropic returned empty content.'
                    && $context['id'] === 'msg_123'
                    && $context['model'] === 'claude-test'
                    && $context['stop_reason'] === 'end_turn';
            });

        $driver = new AnthropicDriver('test-key');
        $messages = new Collection([new MessageData('user', 'Hi')]);

        $response = $driver->chat($messages, 0.7);

        $this->assertSame('', $response);
    }
}
