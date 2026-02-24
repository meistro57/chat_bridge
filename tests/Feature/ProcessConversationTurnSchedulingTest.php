<?php

namespace Tests\Feature;

use App\Jobs\ProcessConversationTurn;
use App\Models\Conversation;
use App\Services\AI\AIManager;
use App\Services\AI\Contracts\AIDriverInterface;
use App\Services\AI\StopWordService;
use App\Services\AI\TranscriptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class ProcessConversationTurnSchedulingTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_uses_configured_millisecond_delay_for_next_turn(): void
    {
        Queue::fake();
        config()->set('ai.inter_turn_delay_ms', 150);
        config()->set('ai.initial_stream_chunk', '...');

        $conversation = Conversation::factory()->create([
            'max_rounds' => 2,
            'status' => 'active',
            'stop_word_detection' => false,
        ]);

        $conversation->messages()->create([
            'role' => 'user',
            'content' => 'Start',
        ]);

        $driver = Mockery::mock(AIDriverInterface::class);
        $driver->shouldReceive('streamChat')
            ->once()
            ->andReturn(['Hello world']);

        $manager = Mockery::mock(AIManager::class);
        $manager->shouldReceive('driverForProvider')
            ->once()
            ->andReturn($driver);

        $job = new ProcessConversationTurn($conversation->id, 1, 2);
        $job->handle($manager, app(StopWordService::class), app(TranscriptService::class));

        $conversation->refresh();
        $this->assertSame('active', $conversation->status);
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Hello world',
        ]);

        Queue::assertPushed(ProcessConversationTurn::class, function (ProcessConversationTurn $nextJob): bool {
            if (! ($nextJob->delay instanceof Carbon)) {
                return false;
            }

            $delayMs = now()->diffInMilliseconds($nextJob->delay, false);

            return $nextJob->round === 2
                && $delayMs >= 0
                && $delayMs <= 1000;
        });
    }
}
