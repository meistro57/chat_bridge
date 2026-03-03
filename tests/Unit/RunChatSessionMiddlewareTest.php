<?php

namespace Tests\Unit;

use App\Jobs\RunChatSession;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Tests\TestCase;

class RunChatSessionMiddlewareTest extends TestCase
{
    public function test_it_prevents_overlapping_processing_for_same_conversation(): void
    {
        $job = new RunChatSession('conversation-123', 5);
        $middleware = $job->middleware();

        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(WithoutOverlapping::class, $middleware[0]);
    }
}
