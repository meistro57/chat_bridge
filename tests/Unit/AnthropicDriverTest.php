<?php

namespace Tests\Unit;

use App\Services\AI\Data\AIResponse;
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

        $this->assertInstanceOf(AIResponse::class, $response);
        $this->assertSame('Hello!', $response->content);
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

        $this->assertInstanceOf(AIResponse::class, $response);
        $this->assertSame('', $response->content);
    }

    public function test_chat_trims_trailing_whitespace_from_outbound_messages(): void
    {
        Http::fake([
            'https://api.anthropic.com/v1/messages' => Http::response([
                'content' => [
                    ['type' => 'text', 'text' => 'OK'],
                ],
            ], 200),
        ]);

        $driver = new AnthropicDriver('test-key');
        $messages = new Collection([
            new MessageData('system', 'System guidance with trailing spaces   '),
            new MessageData('assistant', 'Assistant says hello   ', 'Persona A'),
            new MessageData('user', 'User asks next question   '),
        ]);

        $driver->chat($messages, 0.7);

        Http::assertSent(function ($request): bool {
            $payload = $request->data();
            $system = $payload['system'] ?? '';
            $outboundMessages = $payload['messages'] ?? [];

            if ($system !== rtrim($system)) {
                return false;
            }

            foreach ($outboundMessages as $message) {
                if (($message['content'] ?? '') !== rtrim((string) ($message['content'] ?? ''))) {
                    return false;
                }
            }

            return true;
        });
    }
}
