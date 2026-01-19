# RAG (Retrieval-Augmented Generation) Guide

This guide explains the RAG functionality in Chat Bridge, which enables AI memory and context-aware conversations through semantic search.

## Overview

RAG (Retrieval-Augmented Generation) allows the AI to:
- **Remember** previous conversations
- **Access** relevant context from past messages
- **Provide** more informed and consistent responses
- **Learn** from interaction history

## How It Works

### 1. Message Embeddings

When a message is created:

1. **Text Embedding**: The message content is converted into a vector representation (embedding) using OpenAI's embedding model
2. **Database Storage**: The embedding is stored in the PostgreSQL `messages` table
3. **Vector Storage**: The embedding is also stored in Qdrant vector database with metadata

```php
// Automatic in ConversationService
$message = $conversation->messages()->create([...]);
$embedding = $embeddingService->getEmbedding($message->content);
$message->update(['embedding' => $embedding]);
$ragService->storeMessage($message);
```

### 2. Semantic Search

When generating AI responses:

1. **Query Embedding**: The current message is converted to an embedding
2. **Similarity Search**: Qdrant finds messages with similar embeddings
3. **Context Retrieval**: Top-N similar messages are retrieved
4. **Score Filtering**: Only messages above similarity threshold are used

```php
// Automatic in ConversationService
$relevantContext = $ragService->getRelevantContext(
    currentMessage: "How do I deploy to production?",
    conversationId: $conversation->id,
    limit: 5
);
```

### 3. Context Injection

Retrieved context is injected into the AI prompt:

```
System: [Original system prompt]

Relevant context from previous conversations:
- [2 hours ago] User asked about deployment, assistant explained Docker
- [1 day ago] Discussion about environment configuration
- [3 days ago] Setup of production database

User: [Current message]
```

## Architecture

```
┌─────────────┐
│   Message   │
│   Created   │
└──────┬──────┘
       │
       ├─────────────────┐
       │                 │
       ▼                 ▼
┌─────────────┐   ┌──────────────┐
│ PostgreSQL  │   │  Embedding   │
│  messages   │   │   Service    │
│   table     │   │  (OpenAI)    │
└─────────────┘   └──────┬───────┘
                         │
                         ▼
                  ┌──────────────┐
                  │   Vector     │
                  │ [1536 dims]  │
                  └──────┬───────┘
                         │
       ┌─────────────────┼─────────────────┐
       │                                   │
       ▼                                   ▼
┌─────────────┐                    ┌──────────────┐
│ PostgreSQL  │                    │   Qdrant     │
│  embedding  │                    │   Vector     │
│   column    │                    │   Database   │
└─────────────┘                    └──────┬───────┘
                                          │
                     ┌────────────────────┘
                     │
                     │  Similarity Search
                     │  (Cosine Distance)
                     │
                     ▼
              ┌──────────────┐
              │   Relevant   │
              │   Messages   │
              │  (Top-N)     │
              └──────┬───────┘
                     │
                     │  Context Injection
                     │
                     ▼
              ┌──────────────┐
              │  AI Model    │
              │  (Enhanced)  │
              └──────────────┘
```

## Configuration

### Enable/Disable RAG

In `.env`:

```env
# Enable RAG
QDRANT_ENABLED=true
QDRANT_HOST=localhost  # or 'qdrant' in Docker
QDRANT_PORT=6333

# Disable RAG
QDRANT_ENABLED=false
```

### Embedding Provider

Currently uses OpenAI embeddings. Configure in `.env`:

```env
OPENAI_API_KEY=sk-...
```

The embedding model used is `text-embedding-3-small` (1536 dimensions).

### Search Parameters

Configured in `app/Services/RagService.php` and `app/Services/ConversationService.php`:

```php
// Number of similar messages to retrieve
$limit = 3;  // Default: 3-5 messages

// Minimum similarity score (0-1)
$scoreThreshold = 0.75;  // Default: 0.75

// Filter by conversation, persona, or role
$filter = [
    'persona_id' => $persona->id,
    'conversation_id' => $conversation->id,  // Optional
    'role' => 'assistant',  // Optional
];
```

## Usage

### Automatic Usage

RAG is automatically used during conversations if:
1. `QDRANT_ENABLED=true` in environment
2. Qdrant service is available
3. Messages have embeddings

No code changes required - it works transparently.

### Manual Usage

#### Search Similar Messages

```php
use App\Services\RagService;

$rag = app(RagService::class);

// Search for similar messages
$similar = $rag->searchSimilarMessages(
    query: "How do I configure the database?",
    limit: 10,
    filter: ['persona_id' => $personaId],
    scoreThreshold: 0.7
);

foreach ($similar as $message) {
    echo "Score: {$message->similarity_score}\n";
    echo "Content: {$message->content}\n";
}
```

#### Get Relevant Context

```php
use App\Services\RagService;

$rag = app(RagService::class);

// Get context for current message
$context = $rag->getRelevantContext(
    currentMessage: "Tell me about Docker setup",
    conversationId: $conversation->id,
    limit: 5
);
```

#### Store Message

```php
use App\Services\RagService;

$rag = app(RagService::class);

// Store single message
$rag->storeMessage($message);

// Batch store
$rag->batchStoreMessages($messages);
```

## Commands

### Initialize Qdrant

```bash
# Create collection
php artisan qdrant:init

# Create collection and sync existing messages
php artisan qdrant:init --sync
```

### Generate Embeddings

```bash
# Generate embeddings for all messages without them
php artisan embeddings:generate

# Limit to first 100 messages
php artisan embeddings:generate --limit=100
```

## Performance

### Embedding Generation

- **Speed**: ~50-100ms per message (OpenAI API)
- **Cost**: $0.00002 per 1K tokens (~$0.000004 per message)
- **Async**: Embeddings generated asynchronously via queue

### Vector Search

- **Speed**: 1-5ms for 10K messages, 10-50ms for 1M messages
- **Scalability**: Qdrant handles millions of vectors efficiently
- **Memory**: ~6KB per message (1536 dims × 4 bytes)

### Optimization Tips

1. **Batch Processing**: Generate embeddings in batches
2. **Caching**: Qdrant has built-in caching
3. **Filtering**: Use filters to reduce search space
4. **Score Threshold**: Higher threshold = fewer results = faster

## Advanced Features

### Custom Filters

Filter by any metadata field:

```php
$filter = [
    'must' => [
        [
            'key' => 'persona_id',
            'match' => ['value' => $personaId]
        ],
        [
            'key' => 'role',
            'match' => ['value' => 'assistant']
        ]
    ]
];

$results = $rag->searchSimilarMessages($query, filter: $filter);
```

### Similarity Scoring

Results include similarity scores (0-1):
- **0.9-1.0**: Very similar (near-duplicate)
- **0.8-0.9**: Highly similar (same topic)
- **0.7-0.8**: Similar (related topic)
- **< 0.7**: Weakly similar (filtered out by default)

### Collection Management

```php
// Check if Qdrant is available
$isAvailable = $rag->isAvailable();

// Initialize collection
$success = $rag->initializeCollection();
```

## Troubleshooting

### Embeddings Not Generated

**Problem**: Messages don't have embeddings

**Solution**:
```bash
# Generate embeddings
php artisan embeddings:generate

# Check for errors
tail -f storage/logs/laravel.log
```

### Qdrant Connection Failed

**Problem**: Cannot connect to Qdrant

**Solution**:
```bash
# Check Qdrant is running
curl http://localhost:6333/

# Check configuration
php artisan config:clear

# Verify in .env
echo $QDRANT_HOST
echo $QDRANT_PORT
```

### No Context Retrieved

**Problem**: RAG doesn't find relevant messages

**Solution**:
1. **Lower threshold**: Try `scoreThreshold: 0.6`
2. **Increase limit**: Try `limit: 10`
3. **Check embeddings**: Ensure messages have embeddings
4. **Verify sync**: Run `php artisan qdrant:init --sync`

### Slow Performance

**Problem**: Search is slow

**Solution**:
1. **Use filters**: Narrow search space
2. **Reduce limit**: Request fewer results
3. **Check Qdrant**: Monitor resource usage
4. **Optimize**: Consider using HNSW index parameters

## Best Practices

### 1. Regular Syncing

Sync new messages periodically:

```bash
# Daily cron job
0 2 * * * cd /path/to/app && php artisan qdrant:init --sync
```

### 2. Monitoring

Monitor embedding generation:

```php
Log::info('Embedding generated', [
    'message_id' => $message->id,
    'vector_size' => count($embedding),
]);
```

### 3. Error Handling

Always handle RAG failures gracefully:

```php
try {
    $context = $rag->getRelevantContext($query);
} catch (\Exception $e) {
    Log::warning('RAG failed, continuing without context', [
        'error' => $e->getMessage()
    ]);
    $context = collect();
}
```

### 4. Context Quality

- Use meaningful similarity thresholds
- Limit context to 3-5 most relevant messages
- Filter by persona for personalized context
- Exclude current conversation messages

## Future Enhancements

Potential improvements:

- [ ] Hybrid search (keyword + semantic)
- [ ] Reranking with cross-encoders
- [ ] Multi-language support
- [ ] Custom embedding models
- [ ] Conversation summarization
- [ ] Temporal weighting (recent > old)
- [ ] User-specific memory isolation

## References

- **Qdrant Documentation**: https://qdrant.tech/documentation/
- **OpenAI Embeddings**: https://platform.openai.com/docs/guides/embeddings
- **RAG Overview**: https://en.wikipedia.org/wiki/Prompt_engineering#Retrieval-augmented_generation

## Support

For RAG-related questions:
- Check logs: `storage/logs/laravel.log`
- Qdrant dashboard: `http://localhost:6333/dashboard`
- GitHub issues: <repository-url>/issues
