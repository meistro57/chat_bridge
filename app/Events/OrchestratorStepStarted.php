<?php

namespace App\Events;

use App\Models\OrchestratorStepRun;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrchestratorStepStarted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public OrchestratorStepRun $stepRun
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("orchestrator.{$this->stepRun->run->orchestration->user_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'step.started';
    }

    public function broadcastWith(): array
    {
        return [
            'step_run_id' => $this->stepRun->id,
            'run_id' => $this->stepRun->run_id,
            'step_id' => $this->stepRun->step_id,
            'status' => $this->stepRun->status,
            'started_at' => $this->stepRun->started_at?->toISOString(),
        ];
    }
}
