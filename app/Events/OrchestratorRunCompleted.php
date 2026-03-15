<?php

namespace App\Events;

use App\Models\OrchestratorRun;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrchestratorRunCompleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public OrchestratorRun $run
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("orchestrator.{$this->run->orchestration->user_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'run.completed';
    }

    public function broadcastWith(): array
    {
        return [
            'run_id' => $this->run->id,
            'orchestration_id' => $this->run->orchestration_id,
            'status' => $this->run->status,
            'triggered_by' => $this->run->triggered_by,
            'started_at' => $this->run->started_at?->toISOString(),
            'completed_at' => $this->run->completed_at?->toISOString(),
        ];
    }
}
