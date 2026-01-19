<?php

namespace App\Services\ChatBridge;

use App\Models\ChatBridgeMessage;
use App\Models\ChatBridgeThread;
use NeuronAI\Chat\Enums\MessageRole;
use NeuronAI\Chat\Messages\Message;

class HistoryStore
{
    /**
     * Get or create a thread by its bridge ID.
     */
    public function getOrCreateThread(string $bridgeThreadId): ChatBridgeThread
    {
        return ChatBridgeThread::firstOrCreate(
            ['bridge_thread_id' => $bridgeThreadId]
        );
    }

    /**
     * Append a message to the thread.
     */
    public function appendMessage(ChatBridgeThread $thread, string $role, string $content, ?array $meta = null): ChatBridgeMessage
    {
        return $thread->messages()->create([
            'role' => $role,
            'content' => $content,
            'metadata' => $meta,
        ]);
    }

    /**
     * Get recent messages formatted for Neuron AI.
     *
     * @return array<Message>
     */
    public function fetchRecentMessages(ChatBridgeThread $thread, int $limit = 50): array
    {
        $messages = $thread->messages()
            ->latest()
            ->take($limit)
            ->get()
            ->reverse();

        return $messages->map(function ($msg) {
            $role = match ($msg->role) {
                'user' => MessageRole::USER,
                'assistant' => MessageRole::ASSISTANT,
                'system' => MessageRole::SYSTEM,
                default => MessageRole::USER,
            };

            return new Message($role, $msg->content);
        })->all();
    }
}
