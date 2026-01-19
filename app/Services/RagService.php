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

    protected EmbeddingService $embeddingService;

    protected string $collectionName = 'chat_messages';

    protected int $vectorSize = 1536; // OpenAI embedding size

    public function __construct()
    {
        $this->qdrant = new QdrantConnector(
            host: config('services.qdrant.host', 'localhost'),
            port: config('services.qdrant.port', 6333),
        );

        $this->embeddingService = app(EmbeddingService::class);
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
            // Ensure message has an embedding
            if (empty($message->embedding)) {
                Log::warning('Message has no embedding, generating...', ['message_id' => $message->id]);
                $embedding = $this->embeddingService->generate($message->content);

                if (! $embedding) {
                    Log::error('Failed to generate embedding for message', ['message_id' => $message->id]);

                    return false;
                }

                $message->update(['embedding' => $embedding]);
            }

            // Prepare point for Qdrant
            $point = [
                'id' => $message->id,
                'vector' => $message->embedding,
                'payload' => [
                    'message_id' => $message->id,
                    'conversation_id' => $message->conversation_id,
                    'persona_id' => $message->persona_id,
                    'role' => $message->role,
                    'content' => $message->content,
                    'created_at' => $message->created_at->toIso8601String(),
                    'tokens_used' => $message->tokens_used,
                ],
            ];

            // Upsert to Qdrant
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
        try {
            // Generate embedding for the query
            $queryEmbedding = $this->embeddingService->generate($query);

            if (! $queryEmbedding) {
                Log::error('Failed to generate embedding for query');

                return collect();
            }

            // Build Qdrant filter if provided
            $qdrantFilter = null;
            if ($filter) {
                $must = [];

                if (isset($filter['conversation_id'])) {
                    $must[] = [
                        'key' => 'conversation_id',
                        'match' => ['value' => $filter['conversation_id']],
                    ];
                }

                if (isset($filter['persona_id'])) {
                    $must[] = [
                        'key' => 'persona_id',
                        'match' => ['value' => $filter['persona_id']],
                    ];
                }

                if (isset($filter['role'])) {
                    $must[] = [
                        'key' => 'role',
                        'match' => ['value' => $filter['role']],
                    ];
                }

                if (! empty($must)) {
                    $qdrantFilter = ['must' => $must];
                }
            }

            // Search in Qdrant
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

                return collect();
            }

            $results = $response->json('result', []);

            // Map results to Message models with scores
            return collect($results)->map(function ($result) {
                $messageId = $result['id'];
                $score = $result['score'];
                $payload = $result['payload'];

                // Load the full message from database
                $message = Message::find($messageId);

                if ($message) {
                    $message->similarity_score = $score;

                    return $message;
                }

                return null;
            })->filter();

        } catch (\Exception $e) {
            Log::error('Exception searching Qdrant', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return collect();
        }
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
     * Check if Qdrant service is available
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
