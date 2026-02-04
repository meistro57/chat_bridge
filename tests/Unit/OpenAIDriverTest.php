<?php

namespace Tests\Unit;

use App\Services\AI\Data\MessageData;
use App\Services\AI\Drivers\OpenAIDriver;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAIDriverTest extends TestCase
{
    public function test_chat_retries_without_temperature_when_unsupported(): void
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::sequence()
                ->push([
                    'error' => [
                        'message' => "Unsupported value: 'temperature' does not support 0.7 with this model. Only the default (1) value is supported.",
                        'param' => 'temperature',
                    ],
                ], 400)
                ->push([
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

        $this->assertSame('OK', $result);
        Http::assertSentCount(2);
        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return isset($data['temperature']) && $data['temperature'] === 0.7;
        });
        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return ! array_key_exists('temperature', $data);
        });
    }

    public function test_stream_chat_retries_without_temperature_when_unsupported(): void
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
            'https://api.openai.com/v1/chat/completions' => Http::sequence()
                ->push([
                    'error' => [
                        'message' => "Unsupported value: 'temperature' does not support 0.7 with this model. Only the default (1) value is supported.",
                        'param' => 'temperature',
                    ],
                ], 400)
                ->push($streamBody, 200, ['Content-Type' => 'text/event-stream']),
        ]);

        $driver = new OpenAIDriver('test-key', 'gpt-4o-mini');
        $messages = collect([
            new MessageData('user', 'Hello'),
        ]);

        $chunks = iterator_to_array($driver->streamChat($messages, 0.7));

        $this->assertSame(['Hello ', 'World'], $chunks);
        Http::assertSentCount(2);
        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return isset($data['temperature']) && $data['temperature'] === 0.7;
        });
        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return ! array_key_exists('temperature', $data);
        });
    }
}
