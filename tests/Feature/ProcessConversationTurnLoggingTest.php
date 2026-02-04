<?php

namespace Tests\Feature;

use App\Jobs\ProcessConversationTurn;
use App\Models\Conversation;
use App\Services\AI\AIManager;
use App\Services\AI\Contracts\AIDriverInterface;
use App\Services\AI\StopWordService;
use App\Services\AI\TranscriptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class ProcessConversationTurnLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_logs_when_a_turn_returns_empty_response(): void
    {
        Log::shouldReceive('info')->andReturnNull();
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'Conversation turn empty response'
                    && isset($context['conversation_id'])
                    && isset($context['round']);
            });

        $conversation = Conversation::factory()->create();
        $conversation->messages()->create([
            'role' => 'user',
            'content' => 'Hello there',
        ]);

        $driver = Mockery::mock(AIDriverInterface::class);
        $driver->shouldReceive('streamChat')
            ->once()
            ->andReturn([]);

        $manager = Mockery::mock(AIManager::class);
        $manager->shouldReceive('driverForProvider')
            ->andReturn($driver);

        $job = new ProcessConversationTurn($conversation->id, 1, 1);
        $job->handle($manager, app(StopWordService::class), app(TranscriptService::class));

        $conversation->refresh();

        $this->assertSame('failed', $conversation->status);
        $this->assertTrue(true);
    }
}
