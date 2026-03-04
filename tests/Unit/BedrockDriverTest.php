<?php

namespace Tests\Unit;

use App\Services\AI\Data\AIResponse;
use App\Services\AI\Data\MessageData;
use App\Services\AI\Drivers\BedrockDriver;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BedrockDriverTest extends TestCase
{
    public function test_chat_returns_text_content_and_sends_aws_signature_headers(): void
    {
        Http::fake([
            'https://bedrock-runtime.us-east-1.amazonaws.com/model/*/invoke' => Http::response([
                'content' => [
                    ['type' => 'text', 'text' => 'Hello from Bedrock'],
                ],
                'usage' => [
                    'input_tokens' => 9,
                    'output_tokens' => 6,
                ],
            ], 200),
        ]);

        $driver = new BedrockDriver(
            accessKeyId: 'AKIATESTKEY123',
            secretAccessKey: 'secret-test-key',
            sessionToken: null,
            region: 'us-east-1',
            model: 'anthropic.claude-3-7-sonnet-20250219-v1:0'
        );

        $messages = collect([
            new MessageData('system', 'System guidance'),
            new MessageData('user', 'Hello'),
        ]);

        $response = $driver->chat($messages, 0.3);

        $this->assertInstanceOf(AIResponse::class, $response);
        $this->assertSame('Hello from Bedrock', $response->content);
        $this->assertSame(15, $response->totalTokens);

        Http::assertSent(function ($request): bool {
            $authorization = $request->header('Authorization')[0] ?? '';
            $amzDate = $request->header('X-Amz-Date')[0] ?? '';

            return str_starts_with($authorization, 'AWS4-HMAC-SHA256 ')
                && $amzDate !== ''
                && str_contains($request->url(), '/model/anthropic.claude-3-7-sonnet-20250219-v1%3A0/invoke');
        });
    }
}
