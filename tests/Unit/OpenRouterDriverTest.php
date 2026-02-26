<?php

namespace Tests\Unit;

use App\Services\AI\Data\MessageData;
use App\Services\AI\Drivers\OpenRouterDriver;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenRouterDriverTest extends TestCase
{
    public function test_it_handles_empty_response_without_throwing(): void
    {
        Http::fake([
            'openrouter.ai/api/v1/chat/completions' => Http::response([
                'id' => 'gen-123',
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => '',
                        ],
                        'finish_reason' => 'stop',
                    ],
                ],
                'usage' => [
                    'total_tokens' => 10,
                ],
            ], 200),
        ]);

        $driver = new OpenRouterDriver('test-key', 'google/gemini-2.5-flash-lite');
        $response = $driver->chat(collect([
            new MessageData('user', 'hello'),
        ]));

        $this->assertEquals('', $response->content);
        $this->assertEquals(10, $response->totalTokens);
    }

    public function test_it_throws_on_api_failure(): void
    {
        Http::fake([
            'openrouter.ai/api/v1/chat/completions' => Http::response([
                'error' => [
                    'message' => 'Invalid API Key',
                ],
            ], 401),
        ]);

        $driver = new OpenRouterDriver('test-key', 'google/gemini-2.5-flash-lite');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('OpenRouter API Error: Invalid API Key');

        $driver->chat(collect([
            new MessageData('user', 'hello'),
        ]));
    }
}
