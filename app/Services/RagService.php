<?php

namespace App\Services;

use App\Models\Message;
use App\Services\AI\EmbeddingService;
use App\Services\Qdrant\QdrantConnector;
use App\Services\Qdrant\Requests\CreateCollectionRequest;
use App\Services\Qdrant\Requests\GetCollectionRequest;
use App\Services\Qdrant\Requests\SearchPointsRequest;
use App\Services\Qdrant\Requests\UpsertPointsRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class RagService
{
    protected QdrantConnector $qdrant;

    protected string $collectionName = 'chat_messages';

    protected int $vectorSize = 1536;

    public function __construct(protected EmbeddingService $embeddingService)
    {
        $this->qdrant = new QdrantConnector(
            host: (string) config('services.qdrant.host', 'localhost'),
            port: (int) config('services.qdrant.port', 6333),
        );
    }

    /**
     * Initialize the Qdrant collection if it doesn't exist
     */
    public function initializeCollection(): bool
    {
        try {
            // Check if collection exists
            $response = $this->qdrant->send(new GetCollectionRequest($this->collectionName));

            if ($response->successful()) {
                Log::info('Qdrant collection already exists', ['collection' => $this->collectionName]);

                return true;
            }
        } catch (\Exception $e) {
            // Collection doesn't exist, create it
            Log::info('Creating Qdrant collection', ['collection' => $this->collectionName]);
        }

        try {
            $response = $this->qdrant->send(
                new CreateCollectionRequest(
                    collectionName: $this->collectionName,
                    vectorSize: $this->vectorSize,
                    distance: 'Cosine'
                )
            );

            if ($response->successful()) {
                Log::info('Qdrant collection created successfully', ['collection' => $this->collectionName]);

                return true;
            }

            Log::error('Failed to create Qdrant collection', [
                'collection' => $this->collectionName,
                'response' => $response->body(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Exception creating Qdrant collection', [
                'collection' => $this->collectionName,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Store a message with its embedding in Qdrant
     */
    public function storeMessage(Message $message): bool
    {
        try {
            if (empty($message->embedding)) {
                Log::warning('Message has no embedding, generating...', ['message_id' => $message->id]);
                $embedding = $this->embeddingService->getEmbedding($message->content);

                if (! $embedding) {
                    Log::error('Failed to generate embedding for message', ['message_id' => $message->id]);

                    return false;
                }

                $message->update(['embedding' => $embedding]);
            }

            $conversation = $message->conversation()->first(['id', 'user_id']);
            $point = [
                'id' => $message->id,
                'vector' => $message->embedding,
                'payload' => [
                    'message_id' => $message->id,
                    'conversation_id' => $message->conversation_id,
                    'user_id' => $conversation?->user_id,
                    'persona_id' => $message->persona_id,
                    'role' => $message->role,
                    'content' => $message->content,
                    'created_at' => $message->created_at->toIso8601String(),
                    'tokens_used' => $message->tokens_used,
                ],
            ];

            $response = $this->qdrant->send(
                new UpsertPointsRequest($this->collectionName, [$point])
            );

            if ($response->successful()) {
                Log::info('Message stored in Qdrant', ['message_id' => $message->id]);

                return true;
            }

            Log::error('Failed to store message in Qdrant', [
                'message_id' => $message->id,
                'response' => $response->body(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Exception storing message in Qdrant', [
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Search for similar messages using RAG
     *
     * @param  string  $query  The search query
     * @param  int  $limit  Maximum number of results
     * @param  array|null  $filter  Additional filters (e.g., conversation_id, persona_id)
     * @param  float  $scoreThreshold  Minimum similarity score (0-1)
     * @return Collection Collection of Message models with similarity scores
     */
    public function searchSimilarMessages(
        string $query,
        int $limit = 10,
        ?array $filter = null,
        float $scoreThreshold = 0.7
    ): Collection {
        $filter = $filter ?? [];

        try {
            $queryEmbedding = $this->embeddingService->getEmbedding($query);

            if (! $queryEmbedding) {
                Log::error('Failed to generate embedding for query');

                return collect();
            }

            $driver = $this->resolveSearchDriver();

            if ($driver === 'database') {
                return $this->searchSimilarMessagesInDatabase($queryEmbedding, $limit, $filter, $scoreThreshold);
            }

            $qdrantResults = $this->searchSimilarMessagesInQdrant(
                $queryEmbedding,
                $limit,
                $filter,
                $scoreThreshold
            );

            if ($qdrantResults !== null) {
                if ($qdrantResults->isNotEmpty() || $driver === 'qdrant') {
                    return $qdrantResults;
                }

                Log::info('Qdrant returned no RAG matches; using database fallback', [
                    'query' => $query,
                    'filter' => $filter,
                ]);

                return $this->searchSimilarMessagesInDatabase($queryEmbedding, $limit, $filter, $scoreThreshold);
            }

            Log::warning('Qdrant RAG search failed; using database fallback', [
                'query' => $query,
                'filter' => $filter,
            ]);

            return $this->searchSimilarMessagesInDatabase($queryEmbedding, $limit, $filter, $scoreThreshold);
        } catch (\Exception $e) {
            Log::error('Exception searching RAG index', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return collect();
        }
    }

    /**
     * @param  array<int, float|int>  $queryEmbedding
     * @param  array<string, mixed>  $filter
     */
    protected function searchSimilarMessagesInDatabase(
        array $queryEmbedding,
        int $limit,
        array $filter,
        float $scoreThreshold
    ): Collection {
        $query = Message::query()
            ->whereNotNull('embedding')
            ->with(['persona', 'conversation']);

        $query = $this->applyDatabaseFilter($query, $filter);

        $messages = $query->latest()->limit(500)->get();

        if ($messages->isEmpty()) {
            return collect();
        }

        return $messages
            ->map(function (Message $message) use ($queryEmbedding) {
                $message->similarity_score = $this->cosineSimilarity(
                    $queryEmbedding,
                    is_array($message->embedding) ? $message->embedding : []
                );

                return $message;
            })
            ->filter(fn (Message $message) => $message->similarity_score >= $scoreThreshold)
            ->sortByDesc('similarity_score')
            ->take($limit)
            ->values();
    }

    /**
     * @param  array<int, float|int>  $queryEmbedding
     * @param  array<string, mixed>  $filter
     */
    protected function searchSimilarMessagesInQdrant(
        array $queryEmbedding,
        int $limit,
        array $filter,
        float $scoreThreshold
    ): ?Collection {
        $qdrantFilter = $this->buildQdrantFilter($filter);

        try {
            $response = $this->qdrant->send(
                new SearchPointsRequest(
                    collectionName: $this->collectionName,
                    vector: $queryEmbedding,
                    limit: $limit,
                    filter: $qdrantFilter,
                    scoreThreshold: $scoreThreshold
                )
            );

            if (! $response->successful()) {
                Log::error('Failed to search Qdrant', ['response' => $response->body()]);

                return null;
            }

            $results = $response->json('result', []);

            if (! is_array($results) || $results === []) {
                return collect();
            }

            $scoresByMessageId = [];
            $messageIds = [];
            foreach ($results as $result) {
                $rawMessageId = $result['id'] ?? null;
                if (! is_int($rawMessageId) && ! (is_string($rawMessageId) && ctype_digit($rawMessageId))) {
                    continue;
                }
                $messageId = (int) $rawMessageId;

                $scoresByMessageId[$messageId] = (float) ($result['score'] ?? 0.0);
                $messageIds[] = $messageId;
            }

            if ($messageIds === []) {
                return collect();
            }

            $messagesQuery = Message::query()
                ->whereIn('id', $messageIds)
                ->with(['persona', 'conversation']);
            $messages = $this->applyDatabaseFilter($messagesQuery, $filter)
                ->get()
                ->keyBy('id');

            return collect($messageIds)
                ->map(function (int $messageId) use ($messages, $scoresByMessageId) {
                    $message = $messages->get($messageId);
                    if (! $message) {
                        return null;
                    }

                    $message->similarity_score = $scoresByMessageId[$messageId] ?? 0.0;

                    return $message;
                })
                ->filter()
                ->values();
        } catch (\Throwable $exception) {
            Log::warning('Qdrant search failed with exception', [
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $filter
     * @return \Illuminate\Database\Eloquent\Builder<Message>
     */
    protected function applyDatabaseFilter(\Illuminate\Database\Eloquent\Builder $query, array $filter): \Illuminate\Database\Eloquent\Builder
    {
        if (isset($filter['conversation_id'])) {
            $query->where('conversation_id', (string) $filter['conversation_id']);
        }

        if (isset($filter['persona_id'])) {
            $query->where('persona_id', (string) $filter['persona_id']);
        }

        if (isset($filter['role'])) {
            $query->where('role', (string) $filter['role']);
        }

        if (isset($filter['user_id'])) {
            $userId = (int) $filter['user_id'];
            $query->whereHas('conversation', function ($conversationQuery) use ($userId) {
                $conversationQuery->where('user_id', $userId);
            });
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filter
     * @return array<string, array<int, array<string, mixed>>>|null
     */
    protected function buildQdrantFilter(array $filter): ?array
    {
        $must = [];

        foreach (['conversation_id', 'persona_id', 'role', 'user_id'] as $key) {
            if (! array_key_exists($key, $filter)) {
                continue;
            }

            $must[] = [
                'key' => $key,
                'match' => ['value' => $filter[$key]],
            ];
        }

        if ($must === []) {
            return null;
        }

        return ['must' => $must];
    }

    protected function resolveSearchDriver(): string
    {
        $configured = (string) config('ai.rag_driver', 'auto');

        if ($configured === 'database') {
            return 'database';
        }

        if ($configured === 'qdrant') {
            return 'qdrant';
        }

        return (bool) config('services.qdrant.enabled', false) ? 'qdrant' : 'database';
    }

    /**
     * @param  array<int, float|int>  $leftVector
     * @param  array<int, float|int>  $rightVector
     */
    protected function cosineSimilarity(array $leftVector, array $rightVector): float
    {
        $dotProduct = 0.0;
        $leftNorm = 0.0;
        $rightNorm = 0.0;
        $dimensions = min(count($leftVector), count($rightVector));

        if ($dimensions === 0) {
            return 0.0;
        }

        for ($index = 0; $index < $dimensions; $index++) {
            $leftValue = (float) $leftVector[$index];
            $rightValue = (float) $rightVector[$index];
            $dotProduct += $leftValue * $rightValue;
            $leftNorm += $leftValue ** 2;
            $rightNorm += $rightValue ** 2;
        }

        if ($leftNorm <= 0.0 || $rightNorm <= 0.0) {
            return 0.0;
        }

        return $dotProduct / (sqrt($leftNorm) * sqrt($rightNorm));
    }

    /**
     * Get relevant context for a conversation using RAG
     * This retrieves similar past messages to provide context for the AI
     *
     * @param  string  $currentMessage  The current message to find context for
     * @param  string|null  $conversationId  Optional conversation ID to limit search
     * @param  int  $limit  Maximum number of context messages
     * @return Collection Collection of relevant messages
     */
    public function getRelevantContext(
        string $currentMessage,
        ?string $conversationId = null,
        int $limit = 5
    ): Collection {
        $filter = [];

        if ($conversationId) {
            $filter['conversation_id'] = $conversationId;
        }

        return $this->searchSimilarMessages(
            query: $currentMessage,
            limit: $limit,
            filter: $filter,
            scoreThreshold: 0.75
        );
    }

    /**
     * Batch store multiple messages
     */
    public function batchStoreMessages(Collection $messages): int
    {
        $stored = 0;

        foreach ($messages as $message) {
            if ($this->storeMessage($message)) {
                $stored++;
            }
        }

        return $stored;
    }

    /**
     * Check if the Qdrant service is reachable (does not require the collection to exist).
     */
    public function ping(): bool
    {
        try {
            $host = config('services.qdrant.host', 'localhost');
            $port = config('services.qdrant.port', 6333);
            $response = \Illuminate\Support\Facades\Http::timeout(5)
                ->get("http://{$host}:{$port}/collections");

            return $response->successful();
        } catch (\Exception $e) {
            Log::warning('Qdrant ping failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Check if the Qdrant collection exists and is available.
     */
    public function isAvailable(): bool
    {
        try {
            $response = $this->qdrant->send(new GetCollectionRequest($this->collectionName));

            return $response->successful();
        } catch (\Exception $e) {
            Log::warning('Qdrant service not available', ['error' => $e->getMessage()]);

            return false;
        }
    }
}
