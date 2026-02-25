<?php

namespace App\Services\Discord;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DiscordStreamer
{
    protected int $consecutiveFailures = 0;

    public function __construct(
        protected DiscordEmbedBuilder $embedBuilder
    ) {}

    /**
     * Determine whether this conversation should stream to Discord.
     */
    public function shouldStream(Conversation $conversation): bool
    {
        if (! config('discord.enabled', true)) {
            return false;
        }

        if (! $conversation->discord_streaming_enabled) {
            return false;
        }

        if ($this->isCircuitOpen()) {
            return false;
        }

        return $this->resolveWebhookUrl($conversation) !== null;
    }

    /**
     * Create a Discord thread and post the "conversation started" embed.
     * Returns the thread ID on success.
     */
    public function startConversation(Conversation $conversation): ?string
    {
        if (! $this->shouldStream($conversation)) {
            return null;
        }

        return $this->safeCall(function () use ($conversation) {
            $webhookUrl = $this->resolveWebhookUrl($conversation);

            if ($webhookUrl === null) {
                return null;
            }

            $payload = $this->embedBuilder->conversationStarted($conversation);

            // Create a thread by including thread_name in the webhook payload
            if (config('discord.thread_auto_create', true)) {
                $payload['thread_name'] = $this->embedBuilder->threadName($conversation);
            }

            $response = $this->executeWebhook($webhookUrl, $payload);

            if ($response === null) {
                return null;
            }

            // Extract the thread ID from the response
            // When thread_name is provided, Discord creates a thread and the
            // response includes the channel_id of the new thread.
            $threadId = $response['channel_id'] ?? null;

            if ($threadId) {
                $conversation->updateQuietly([
                    'discord_thread_id' => $threadId,
                ]);

                Log::info('Discord thread created for conversation', [
                    'conversation_id' => $conversation->id,
                    'thread_id' => $threadId,
                ]);
            }

            return $threadId;
        }, 'startConversation');
    }

    /**
     * Post a completed agent message to Discord.
     */
    public function postMessage(Conversation $conversation, Message $message, int $turnNumber): void
    {
        if (! $this->shouldStream($conversation)) {
            return;
        }

        $this->safeCall(function () use ($conversation, $message, $turnNumber) {
            $webhookUrl = $this->resolveWebhookUrl($conversation);

            if ($webhookUrl === null) {
                return;
            }

            $payload = $this->embedBuilder->agentMessage($message, $conversation, $turnNumber);

            // Discord allows max 10 embeds per message.  If the content was
            // split into more parts we need multiple webhook calls.
            $embeds = $payload['embeds'] ?? [];
            $chunks = array_chunk($embeds, 10);

            foreach ($chunks as $embedChunk) {
                $this->executeWebhook(
                    $webhookUrl,
                    ['embeds' => $embedChunk],
                    $conversation->discord_thread_id
                );
            }
        }, 'postMessage');
    }

    /**
     * Post the "conversation completed" summary.
     */
    public function conversationCompleted(
        Conversation $conversation,
        int $totalMessages,
        int $totalRounds,
        float $durationSeconds
    ): void {
        if (! $this->shouldStream($conversation)) {
            return;
        }

        $this->safeCall(function () use ($conversation, $totalMessages, $totalRounds, $durationSeconds) {
            $webhookUrl = $this->resolveWebhookUrl($conversation);

            if ($webhookUrl === null) {
                return;
            }

            $payload = $this->embedBuilder->conversationCompleted(
                $conversation,
                $totalMessages,
                $totalRounds,
                $durationSeconds
            );

            $this->executeWebhook($webhookUrl, $payload, $conversation->discord_thread_id);
        }, 'conversationCompleted');
    }

    /**
     * Post the "conversation failed" error embed.
     */
    public function conversationFailed(Conversation $conversation, string $error): void
    {
        if (! config('discord.enabled', true)) {
            return;
        }

        if (! $conversation->discord_streaming_enabled) {
            return;
        }

        $this->safeCall(function () use ($conversation, $error) {
            $webhookUrl = $this->resolveWebhookUrl($conversation);

            if ($webhookUrl === null) {
                return;
            }

            $payload = $this->embedBuilder->conversationFailed($conversation, $error);

            $this->executeWebhook($webhookUrl, $payload, $conversation->discord_thread_id);
        }, 'conversationFailed');
    }

    /**
     * Execute a Discord webhook request.
     *
     * @return array<string, mixed>|null
     */
    protected function executeWebhook(string $url, array $payload, ?string $threadId = null): ?array
    {
        $queryParams = ['wait' => 'true'];

        if ($threadId) {
            $queryParams['thread_id'] = $threadId;
        }

        $response = Http::timeout(10)
            ->connectTimeout(5)
            ->asJson()
            ->post($url.'?'.http_build_query($queryParams), $payload);

        if ($response->status() === 429) {
            $retryAfter = $response->json('retry_after', 1);
            Log::warning('Discord rate limited', [
                'retry_after' => $retryAfter,
            ]);
            usleep((int) ($retryAfter * 1_000_000));

            // Retry once
            $response = Http::timeout(10)
                ->connectTimeout(5)
                ->asJson()
                ->post($url.'?'.http_build_query($queryParams), $payload);
        }

        if ($response->failed()) {
            $this->recordFailure();
            Log::warning('Discord webhook failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $this->recordSuccess();

        return $response->json();
    }

    /**
     * Resolve the webhook URL for a conversation using the priority chain:
     * 1. Per-conversation override
     * 2. User default
     * 3. System-wide config
     */
    protected function resolveWebhookUrl(Conversation $conversation): ?string
    {
        if (! empty($conversation->discord_webhook_url)) {
            return $conversation->discord_webhook_url;
        }

        $user = $conversation->user;
        if ($user && ! empty($user->discord_webhook_url)) {
            return $user->discord_webhook_url;
        }

        $systemUrl = config('discord.webhook_url');

        return ! empty($systemUrl) ? $systemUrl : null;
    }

    /**
     * Wrap a Discord operation so failures never crash the conversation.
     */
    protected function safeCall(callable $operation, string $context): mixed
    {
        try {
            return $operation();
        } catch (\Throwable $e) {
            $this->recordFailure();
            Log::warning("Discord streaming failed: {$context}", [
                'error' => $e->getMessage(),
                'consecutive_failures' => $this->consecutiveFailures,
            ]);

            return null;
        }
    }

    protected function recordFailure(): void
    {
        $this->consecutiveFailures++;
    }

    protected function recordSuccess(): void
    {
        $this->consecutiveFailures = 0;
    }

    protected function isCircuitOpen(): bool
    {
        $threshold = (int) config('discord.circuit_breaker_threshold', 5);

        return $this->consecutiveFailures >= $threshold;
    }
}
