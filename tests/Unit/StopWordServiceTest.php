<?php

namespace Tests\Unit;

use App\Services\AI\StopWordService;
use Tests\TestCase;

class StopWordServiceTest extends TestCase
{
    public function test_stop_word_threshold_triggers_when_ratio_met(): void
    {
        $service = new StopWordService;

        $shouldStop = $service->shouldStopWithThreshold(
            'Time to say goodbye.',
            ['goodbye', 'halt'],
            0.5
        );

        $this->assertTrue($shouldStop);
    }

    public function test_stop_word_threshold_does_not_trigger_when_ratio_not_met(): void
    {
        $service = new StopWordService;

        $shouldStop = $service->shouldStopWithThreshold(
            'Time to say goodbye.',
            ['goodbye', 'halt'],
            0.9
        );

        $this->assertFalse($shouldStop);
    }
}
