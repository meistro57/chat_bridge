<?php

namespace Tests\Unit;

use App\Events\ConversationStatusUpdated;
use App\Events\MessageChunkSent;
use App\Events\MessageCompleted;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use PHPUnit\Framework\TestCase;

class BroadcastEventsNowTest extends TestCase
{
    public function test_streaming_events_broadcast_immediately(): void
    {
        $this->assertContains(
            ShouldBroadcastNow::class,
            class_implements(MessageChunkSent::class)
        );

        $this->assertContains(
            ShouldBroadcastNow::class,
            class_implements(MessageCompleted::class)
        );

        $this->assertContains(
            ShouldBroadcastNow::class,
            class_implements(ConversationStatusUpdated::class)
        );
    }
}
