<?php

namespace Tests\Unit;

use App\Services\AI\Drivers\GeminiDriver;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiDriverTest extends TestCase
{
    public function test_chat_returns_actionable_message_for_unsupported_model_errors(): void
    {
        $driver = new GeminiDriver(
            apiKey: 'test-key',
            model: 'gemini-1.5-flash',
            baseUrl: 'https://generativelanguage.googleapis.com/v1beta'
        );

        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=test-key' => Http::response(
                '{"error":{"code":404,"message":"models/gemini-1.5-flash is not found for API version v1beta, or is not supported for generateContent."}}',
                404
            ),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('not supported by endpoint');

        $driver->chat(collect([
            (object) ['role' => 'user', 'content' => 'Say OK'],
        ]));
    }
}
