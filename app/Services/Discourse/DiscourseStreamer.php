<?php

namespace App\Services\Discourse;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DiscourseStreamer
{
    protected const CHAT_WEBHOOK_MESSAGE_LIMIT = 1800;

    protected int $consecutiveFailures = 0;

    public function shouldStream(Conversation $conversation): bool
    {
        if (! config('discourse.enabled', false)) {
            return false;
        }

        if (! $conversation->discourse_streaming_enabled) {
            return false;
        }

        if ($this->isCircuitOpen()) {
            return false;
        }

        return $this->hasTopicDelivery() || $this->hasChatDelivery();
    }

    public function startConversation(Conversation $conversation): ?int
    {
        if (! $this->shouldStream($conversation)) {
            return null;
        }

        return $this->safeCall(function () use ($conversation): ?int {
            return $this->withConversationLock($conversation, function () use ($conversation): ?int {
                $topicId = $this->resolveTopicId($conversation);

                if ($this->hasChatDelivery()) {
                    $this->sendChatMessage($this->starterMessageChat($conversation));
                    $this->sendChatMessage($this->conversationStartedChat($conversation));
                }

                return $topicId;
            });
        }, 'startConversation');
    }

    public function postMessage(Conversation $conversation, Message $message, int $turnNumber): void
    {
        if (! $this->shouldStream($conversation)) {
            return;
        }

        $this->safeCall(function () use ($conversation, $message, $turnNumber): void {
            $this->withConversationLock($conversation, function () use ($conversation, $message, $turnNumber): void {
                if (! $this->shouldPublishMessage($conversation, $message, $turnNumber)) {
                    return;
                }

                $wasPublished = false;

                if ($this->hasTopicDelivery()) {
                    $topicId = $this->resolveTopicId($conversation);
                    if ($topicId !== null) {
                        $response = $this->executePost([
                            'topic_id' => $topicId,
                            'raw' => $this->messageRaw($conversation, $message, $turnNumber),
                        ]);

                        if (is_array($response)) {
                            $wasPublished = true;
                        }
                    }
                }

                if ($this->hasChatDelivery()) {
                    $chatPosted = $this->sendChatMessage($this->messageChat($conversation, $message, $turnNumber));
                    $wasPublished = $wasPublished || $chatPosted;
                }

                if ($wasPublished) {
                    $this->markMessagePublished($conversation, $message, $turnNumber);
                }
            });
        }, 'postMessage');
    }

    public function conversationCompleted(
        Conversation $conversation,
        int $totalMessages,
        int $totalRounds,
        float $durationSeconds
    ): void {
        if (! $this->shouldStream($conversation)) {
            return;
        }

        $this->safeCall(function () use ($conversation, $totalMessages, $totalRounds, $durationSeconds): void {
            $this->withConversationLock($conversation, function () use ($conversation, $totalMessages, $totalRounds, $durationSeconds): void {
                if ($this->hasTopicDelivery()) {
                    $topicId = $this->resolveTopicId($conversation);
                    if ($topicId !== null) {
                        $this->executePost([
                            'topic_id' => $topicId,
                            'raw' => $this->completedRaw($totalMessages, $totalRounds, $durationSeconds),
                        ]);
                    }
                }

                if ($this->hasChatDelivery()) {
                    $this->sendChatMessage($this->completedChat($totalMessages, $totalRounds, $durationSeconds));
                }
            });
        }, 'conversationCompleted');
    }

    public function conversationFailed(Conversation $conversation, string $error): void
    {
        if (! config('discourse.enabled', false)) {
            return;
        }

        if (! $conversation->discourse_streaming_enabled) {
            return;
        }

        if (! ($this->hasTopicDelivery() || $this->hasChatDelivery())) {
            return;
        }

        $this->safeCall(function () use ($conversation, $error): void {
            $this->withConversationLock($conversation, function () use ($conversation, $error): void {
                if ($this->hasTopicDelivery()) {
                    $topicId = $this->resolveTopicId($conversation);
                    if ($topicId !== null) {
                        $this->executePost([
                            'topic_id' => $topicId,
                            'raw' => $this->failedRaw($error),
                        ]);
                    }
                }

                if ($this->hasChatDelivery()) {
                    $this->sendChatMessage($this->failedChat($error));
                }
            });
        }, 'conversationFailed');
    }

    protected function resolveTopicId(Conversation $conversation): ?int
    {
        if (! $this->hasTopicDelivery()) {
            return null;
        }

        if ($conversation->discourse_topic_id !== null) {
            return (int) $conversation->discourse_topic_id;
        }

        $response = $this->executePost([
            'title' => $this->topicTitle($conversation),
            'raw' => $this->starterMessageRaw($conversation),
            'category' => config('discourse.default_category_id'),
            'tags' => config('discourse.default_tags', ['chat-bridge']),
        ]);

        if (! is_array($response)) {
            return null;
        }

        $topicId = isset($response['topic_id']) ? (int) $response['topic_id'] : null;
        if ($topicId === null || $topicId < 1) {
            return null;
        }

        $conversation->updateQuietly([
            'discourse_topic_id' => $topicId,
        ]);

        $this->executePost([
            'topic_id' => $topicId,
            'raw' => $this->conversationStartedRaw($conversation),
        ]);

        return $topicId;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function executePost(array $payload): ?array
    {
        $baseUrl = rtrim((string) config('discourse.base_url'), '/');
        $url = "{$baseUrl}/posts.json";

        $request = Http::timeout((int) config('discourse.timeout', 15))
            ->connectTimeout((int) config('discourse.connect_timeout', 5))
            ->asJson()
            ->withHeaders([
                'Api-Key' => (string) config('discourse.api_key'),
                'Api-Username' => (string) config('discourse.api_username'),
            ]);

        $response = $request->post($url, $payload);

        if ($response->status() === 429) {
            $retryAfter = (int) ($response->header('Retry-After') ?? 1);
            usleep(max(1, $retryAfter) * 1_000_000);
            $response = $request->post($url, $payload);
        }

        if ($response->failed()) {
            if (
                isset($payload['title'])
                && ! isset($payload['topic_id'])
                && $response->status() === 422
                && str_contains($response->body(), 'Title has already been used')
            ) {
                $existingTopicId = $this->findTopicIdByTitle((string) $payload['title']);
                if ($existingTopicId !== null) {
                    Log::info('Reusing existing Discourse topic after duplicate title response', [
                        'title' => $payload['title'],
                        'topic_id' => $existingTopicId,
                    ]);

                    return [
                        'topic_id' => $existingTopicId,
                    ];
                }
            }

            if (! $response->clientError()) {
                $this->recordFailure();
            }
            Log::warning('Discourse post failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $this->recordSuccess();

        return $response->json();
    }

    private function findTopicIdByTitle(string $title): ?int
    {
        $baseUrl = rtrim((string) config('discourse.base_url'), '/');

        $request = Http::timeout((int) config('discourse.timeout', 15))
            ->connectTimeout((int) config('discourse.connect_timeout', 5))
            ->asJson()
            ->withHeaders([
                'Api-Key' => (string) config('discourse.api_key'),
                'Api-Username' => (string) config('discourse.api_username'),
            ]);

        $queries = [
            "{$baseUrl}/search/query.json?term=".urlencode($title),
            "{$baseUrl}/search.json?q=".urlencode($title),
        ];

        foreach ($queries as $queryUrl) {
            $response = $request->get($queryUrl);
            if ($response->failed()) {
                continue;
            }

            $data = $response->json();
            $topics = $data['topics'] ?? data_get($data, 'grouped_search_result.topics', []);

            if (! is_array($topics)) {
                continue;
            }

            foreach ($topics as $topic) {
                if (! is_array($topic)) {
                    continue;
                }

                $candidateTitle = trim((string) ($topic['title'] ?? ''));
                if (mb_strtolower($candidateTitle) !== mb_strtolower(trim($title))) {
                    continue;
                }

                $topicId = (int) ($topic['id'] ?? $topic['topic_id'] ?? 0);
                if ($topicId > 0) {
                    return $topicId;
                }
            }
        }

        return null;
    }

    protected function hasCredentials(): bool
    {
        return filled(config('discourse.base_url'))
            && filled(config('discourse.api_key'))
            && filled(config('discourse.api_username'));
    }

    protected function hasTopicDelivery(): bool
    {
        return $this->hasCredentials();
    }

    protected function hasChatDelivery(): bool
    {
        return (bool) config('discourse.chat_enabled', false)
            && filled(config('discourse.chat_webhook_url'));
    }

    protected function sendChatMessage(string $text): bool
    {
        $sentAny = false;
        $allSuccessful = true;

        foreach ($this->splitChatMessage($text) as $part) {
            $sentAny = true;
            $allSuccessful = $this->executeChatWebhook($part) && $allSuccessful;
        }

        return $sentAny && $allSuccessful;
    }

    protected function executeChatWebhook(string $text): bool
    {
        $url = (string) config('discourse.chat_webhook_url');
        if ($url === '') {
            return false;
        }

        $request = Http::timeout((int) config('discourse.timeout', 15))
            ->connectTimeout((int) config('discourse.connect_timeout', 5))
            ->asForm();

        $response = $request->post($url, [
            'text' => $text,
        ]);

        if ($response->status() === 429) {
            $retryAfter = (int) ($response->header('Retry-After') ?? 1);
            usleep(max(1, $retryAfter) * 1_000_000);
            $response = $request->post($url, [
                'text' => $text,
            ]);
        }

        if ($response->failed()) {
            if (! $response->clientError()) {
                $this->recordFailure();
            }
            Log::warning('Discourse chat webhook failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        }

        $this->recordSuccess();

        return true;
    }

    protected function safeCall(callable $operation, string $context): mixed
    {
        try {
            return $operation();
        } catch (\Throwable $exception) {
            $this->recordFailure();
            Log::warning("Discourse streaming failed: {$context}", [
                'error' => $exception->getMessage(),
                'consecutive_failures' => $this->consecutiveFailures,
            ]);

            return null;
        }
    }

    protected function withConversationLock(Conversation $conversation, callable $operation): mixed
    {
        try {
            return Cache::lock($this->conversationLockKey($conversation), 30)->block(5, function () use ($operation): mixed {
                return $operation();
            });
        } catch (LockTimeoutException $exception) {
            Log::warning('Discourse streaming lock timeout', [
                'conversation_id' => (string) $conversation->id,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    protected function shouldPublishMessage(Conversation $conversation, Message $message, int $turnNumber): bool
    {
        $lastPublishedTurn = (int) Cache::get($this->lastPublishedTurnKey($conversation), 0);
        if ($lastPublishedTurn > 0 && $turnNumber <= $lastPublishedTurn) {
            Log::info('Skipping out-of-order Discourse turn', [
                'conversation_id' => (string) $conversation->id,
                'turn_number' => $turnNumber,
                'last_published_turn' => $lastPublishedTurn,
                'message_id' => (int) $message->id,
            ]);

            return false;
        }

        $lastMessageId = (int) Cache::get($this->lastPublishedMessageKey($conversation), 0);
        $currentMessageId = (int) $message->id;

        if ($lastMessageId <= 0) {
            return true;
        }

        if ($currentMessageId > $lastMessageId) {
            return true;
        }

        Log::info('Skipping out-of-order Discourse message', [
            'conversation_id' => (string) $conversation->id,
            'message_id' => $currentMessageId,
            'last_published_message_id' => $lastMessageId,
        ]);

        return false;
    }

    protected function markMessagePublished(Conversation $conversation, Message $message, int $turnNumber): void
    {
        Cache::forever($this->lastPublishedMessageKey($conversation), (int) $message->id);
        Cache::forever($this->lastPublishedTurnKey($conversation), (int) $turnNumber);
    }

    protected function conversationLockKey(Conversation $conversation): string
    {
        return "discourse.stream.{$conversation->id}";
    }

    protected function lastPublishedMessageKey(Conversation $conversation): string
    {
        return "discourse.stream.last_message.{$conversation->id}";
    }

    protected function lastPublishedTurnKey(Conversation $conversation): string
    {
        return "discourse.stream.last_turn.{$conversation->id}";
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
        $threshold = (int) config('discourse.circuit_breaker_threshold', 5);

        return $this->consecutiveFailures >= $threshold;
    }

    protected function topicTitle(Conversation $conversation): string
    {
        $personaA = $conversation->personaA?->name ?? 'Agent A';
        $personaB = $conversation->personaB?->name ?? 'Agent B';
        $starter = trim(preg_replace('/\s+/', ' ', (string) $conversation->starter_message) ?? '');
        $starterPreview = mb_strlen($starter) > 70
            ? mb_substr($starter, 0, 70).'...'
            : $starter;
        $conversationToken = mb_substr((string) $conversation->id, 0, 8);

        return mb_substr("Chat Bridge #{$conversationToken}: {$personaA} vs {$personaB} | {$starterPreview}", 0, 240);
    }

    protected function starterMessageRaw(Conversation $conversation): string
    {
        $personaA = $conversation->personaA?->name ?? 'Agent A';
        $personaB = $conversation->personaB?->name ?? 'Agent B';

        $raw = <<<MARKDOWN
        ## Chat Bridge Session Started

        **Agent A:** {$personaA} ({$conversation->provider_a} · `{$conversation->model_a}`)
        **Agent B:** {$personaB} ({$conversation->provider_b} · `{$conversation->model_b}`)
        **Max Rounds:** {$conversation->max_rounds}
        **Stop Word Detection:** {$this->enabledLabel((bool) $conversation->stop_word_detection)}

        ### Starter Prompt
        {$conversation->starter_message}
        MARKDOWN;

        return $this->limitRaw($raw);
    }

    protected function conversationStartedRaw(Conversation $conversation): string
    {
        $url = route('chat.show', $conversation->id);

        return $this->limitRaw("Conversation is now live. Follow along in Chat Bridge: {$url}");
    }

    protected function messageRaw(Conversation $conversation, Message $message, int $turnNumber): string
    {
        $personaName = $message->persona?->name ?? 'Assistant';
        $isAgentA = $message->persona_id === $conversation->persona_a_id;
        $provider = $isAgentA ? $conversation->provider_a : $conversation->provider_b;
        $model = $isAgentA ? $conversation->model_a : $conversation->model_b;
        $tokens = $message->tokens_used ? " | tokens: {$message->tokens_used}" : '';

        $raw = <<<MARKDOWN
        ### Turn {$turnNumber}/{$conversation->max_rounds} · {$personaName}
        _{$provider} · `{$model}`{$tokens}_

        {$message->content}
        MARKDOWN;

        return $this->limitRaw($raw);
    }

    protected function starterMessageChat(Conversation $conversation): string
    {
        $raw = $this->starterMessageRaw($conversation);

        return $this->limitChat("Chat Bridge session started\n\n{$raw}");
    }

    protected function conversationStartedChat(Conversation $conversation): string
    {
        return $this->limitChat($this->conversationStartedRaw($conversation));
    }

    protected function messageChat(Conversation $conversation, Message $message, int $turnNumber): string
    {
        return $this->limitChat($this->messageRaw($conversation, $message, $turnNumber));
    }

    protected function completedChat(int $totalMessages, int $totalRounds, float $durationSeconds): string
    {
        return $this->limitChat($this->completedRaw($totalMessages, $totalRounds, $durationSeconds));
    }

    protected function failedChat(string $error): string
    {
        return $this->limitChat($this->failedRaw($error));
    }

    protected function completedRaw(int $totalMessages, int $totalRounds, float $durationSeconds): string
    {
        $duration = $this->formatDuration($durationSeconds);

        return $this->limitRaw(<<<MARKDOWN
        ## Conversation Completed

        - Duration: {$duration}
        - Rounds: {$totalRounds}
        - Messages: {$totalMessages}
        MARKDOWN);
    }

    protected function failedRaw(string $error): string
    {
        $errorPreview = mb_strlen($error) > 1000
            ? mb_substr($error, 0, 1000).'...'
            : $error;

        return $this->limitRaw(<<<MARKDOWN
        ## Conversation Failed

        ```text
        {$errorPreview}
        ```
        MARKDOWN);
    }

    protected function enabledLabel(bool $enabled): string
    {
        if ($enabled) {
            return 'Enabled';
        }

        return 'Disabled';
    }

    protected function formatDuration(float $seconds): string
    {
        $minutes = (int) floor($seconds / 60);
        $secs = (int) ($seconds % 60);

        if ($minutes > 0) {
            return "{$minutes}m {$secs}s";
        }

        return "{$secs}s";
    }

    protected function limitRaw(string $raw): string
    {
        $sanitized = trim($raw);

        if (mb_strlen($sanitized) <= 28000) {
            return $sanitized;
        }

        return mb_substr($sanitized, 0, 27997).'...';
    }

    protected function limitChat(string $raw): string
    {
        $sanitized = trim($raw);

        if (mb_strlen($sanitized) <= self::CHAT_WEBHOOK_MESSAGE_LIMIT) {
            return $sanitized;
        }

        return mb_substr($sanitized, 0, self::CHAT_WEBHOOK_MESSAGE_LIMIT - 3).'...';
    }

    /**
     * @return array<int, string>
     */
    protected function splitChatMessage(string $text): array
    {
        $content = trim($text);
        if ($content === '') {
            return [];
        }

        $chunks = [];
        $remaining = $content;

        while (mb_strlen($remaining) > self::CHAT_WEBHOOK_MESSAGE_LIMIT) {
            $chunks[] = mb_substr($remaining, 0, self::CHAT_WEBHOOK_MESSAGE_LIMIT);
            $remaining = mb_substr($remaining, self::CHAT_WEBHOOK_MESSAGE_LIMIT);
        }

        if ($remaining !== '') {
            $chunks[] = $remaining;
        }

        return $chunks;
    }
}
