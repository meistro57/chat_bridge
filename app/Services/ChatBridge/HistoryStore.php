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

    public function updatePersistentContext(ChatBridgeThread $thread, ?array $metadata): void
    {
        if (! is_array($metadata) || $metadata === []) {
            return;
        }

        $context = $this->extractPersistentContext($metadata);

        if ($context === null || $context === '') {
            return;
        }

        $threadMetadata = is_array($thread->metadata) ? $thread->metadata : [];
        $threadMetadata['persistent_context'] = $context;
        $thread->update(['metadata' => $threadMetadata]);
    }

    /**
     * Get recent messages formatted for Neuron AI.
     *
     * @return array<Message>
     */
    public function fetchRecentMessages(ChatBridgeThread $thread, int $limit = 120): array
    {
        $messages = $thread->messages()
            ->latest()
            ->take($limit)
            ->get()
            ->reverse();

        $history = $messages->map(function ($msg) {
            $role = match ($msg->role) {
                'user' => MessageRole::USER,
                'assistant' => MessageRole::ASSISTANT,
                'system' => MessageRole::SYSTEM,
                default => MessageRole::USER,
            };

            return new Message($role, $msg->content);
        })->all();

        $persistentContext = $this->persistentContextMessage($thread);

        if ($persistentContext !== null) {
            array_unshift($history, new Message(MessageRole::SYSTEM, $persistentContext));
        }

        return $history;
    }

    private function extractPersistentContext(array $metadata): ?string
    {
        $keys = [
            'context',
            'source_context',
            'source_text',
            'chart_data',
            'birth_data',
            'reading_context',
            'summary_context',
        ];

        foreach ($keys as $key) {
            if (! array_key_exists($key, $metadata)) {
                continue;
            }

            $value = $metadata[$key];

            if (is_string($value)) {
                $trimmed = trim($value);
                if ($trimmed !== '') {
                    return mb_substr($trimmed, 0, 12000);
                }
            }

            if (is_array($value) && $value !== []) {
                $encoded = json_encode($value, JSON_PRETTY_PRINT);
                if (is_string($encoded) && $encoded !== '') {
                    return mb_substr($encoded, 0, 12000);
                }
            }
        }

        return null;
    }

    private function persistentContextMessage(ChatBridgeThread $thread): ?string
    {
        $threadMetadata = is_array($thread->metadata) ? $thread->metadata : [];
        $context = $threadMetadata['persistent_context'] ?? null;

        if (! is_string($context) || trim($context) === '') {
            return null;
        }

        return "Reference data for this thread:\n".$context."\n\nUse this data when generating summaries unless the user explicitly overrides it.";
    }
}
