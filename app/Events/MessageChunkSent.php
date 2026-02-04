<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageChunkSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $conversationId,
        public string $chunk,
        public string $role = 'assistant',
        public ?string $personaName = null
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("conversation.{$this->conversationId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.chunk';
    }

    public function broadcastWith(): array
    {
        return [
            'conversationId' => $this->conversationId,
            'chunk' => $this->chunk,
            'role' => $this->role,
            'personaName' => $this->personaName,
        ];
    }
}
