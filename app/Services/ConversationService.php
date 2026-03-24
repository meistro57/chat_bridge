<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Persona;
use App\Services\AI\AIManager;
use App\Services\AI\Data\MessageData;
use App\Services\AI\EmbeddingService;
use App\Services\AI\StreamingChunker;
use App\Services\AI\Tools\ToolExecutor;
use App\Services\AI\TranscriptService;
use App\Services\Broadcast\SafeBroadcaster;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ConversationService
{
    public function __construct(
        protected AIManager $ai,
        protected TranscriptService $transcripts,
        protected EmbeddingService $embeddings,
        protected RagService $rag,
        protected ToolExecutor $toolExecutor,
        protected StreamingChunker $streamingChunker
    ) {}

    /**
     * Generate a turn for the given persona based on history.
     * Yields chunks of text.
     * Returns an array with 'content' generator and 'driver' instance.
     *
     * @return array{content: \Generator<string>, driver: \App\Services\AI\Contracts\AIDriverInterface}
     */
    public function generateTurn(Conversation $conversation, Persona $persona, Collection $history): array
    {
        $settings = $conversation->settingsForPersona($persona);
        $driver = $this->ai->driverForProvider($settings['provider'], $settings['model']);

        $messages = $this->fitMessagesWithinPromptBudget(
            $this->buildMessages($conversation, $persona, $history),
            $settings['provider'] ?? null
        );

        // Check if driver supports tools and tools are enabled
        if ($driver->supportsTools() && config('ai.tools_enabled', true)) {
            try {
                // Use agentic loop with tools (non-streaming)
                $result = $this->generateWithTools($driver, $this->copyMessages($messages), $settings['temperature'], [
                    'provider' => $settings['provider'] ?? null,
                    'model' => $settings['model'] ?? null,
                ]);

                // Stream the final tools response in smaller chunks for smoother UI updates.
                $generator = function () use ($result) {
                    foreach ($this->streamingChunker->split($result, $this->toolResponseChunkSize()) as $chunk) {
                        yield $chunk;
                    }
                };

                return [
                    'content' => $generator(),
                    'driver' => $driver,
                ];
            } catch (\Throwable $exception) {
                Log::warning('Tool-enabled generation failed; falling back to standard generation', [
                    'provider' => $settings['provider'] ?? null,
                    'model' => $settings['model'] ?? null,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        // Standard streaming response without tools
        $generator = function () use ($driver, $messages, $settings, $conversation, $persona) {
            $streamYieldedContent = false;
            $streamedContent = '';
            foreach ($driver->streamChat($messages, $settings['temperature']) as $chunk) {
                $streamYieldedContent = true;
                $streamedContent .= $chunk;
                yield $chunk;
            }

            if (! $streamYieldedContent || trim($streamedContent) === '') {
                if ($streamYieldedContent) {
                    Log::warning('Streaming turn content was whitespace-only; using non-stream fallback', [
                        'conversation_id' => $conversation->id,
                        'persona_id' => $persona->id,
                    ]);
                }

                $response = $driver->chat($messages, $settings['temperature']);
                if (trim($response->content) !== '') {
                    yield $response->content;
                }
            }
        };

        return [
            'content' => $generator(),
            'driver' => $driver,
        ];
    }

    protected function toolResponseChunkSize(): int
    {
        $configuredLimit = (int) config('ai.stream_chunk_size', 1500);

        return max(1, min($configuredLimit, 120));
    }

    /**
     * Build message collection with system prompts, guidelines, and history
     */
    protected function buildMessages(Conversation $conversation, Persona $persona, Collection $history): Collection
    {
        $messages = collect();

        // System Prompt
        $messages->push(new MessageData('system', $persona->system_prompt));

        // Add conversation context for multi-turn awareness
        $conversationContext = "IMPORTANT: This is an ongoing multi-turn conversation simulation. You MUST respond to each message.\n\n".
            "Your task:\n".
            "1. Read the most recent message from the other participant\n".
            "2. Provide a substantive response from YOUR professional perspective\n".
            "3. Engage with their points - agree, disagree, add context, or raise new concerns\n".
            "4. Keep the dialogue active and interesting\n\n".
            'Even if the previous message seems complete, find an angle to respond from your expertise. '.
            'Share your thoughts, concerns, alternative approaches, or practical considerations. '.
            "NEVER leave a message unanswered.\n\n".
            'You have access to tools that can search past conversations and retrieve contextual information. '.
            'Use these tools when relevant to provide informed responses based on conversation history.';
        $messages->push(new MessageData('system', $conversationContext));

        // Guidelines
        foreach ($this->normalizeToArray($persona->guidelines, 'persona.guidelines', "persona:{$persona->id} conversation:{$conversation->id}") as $guideline) {
            $messages->push(new MessageData('system', "Guideline: $guideline"));
        }

        $ragConfig = $this->conversationRagConfig($conversation);
        $crossChatMemoryEnabled = (bool) ($ragConfig['enabled'] ?? true);
        $fileContext = $this->templateFileContextMessage($ragConfig);

        if ($crossChatMemoryEnabled || $fileContext !== null) {
            $ragSystemPrompt = trim((string) ($ragConfig['system_prompt'] ?? ''));
            if ($ragSystemPrompt !== '') {
                $messages->push(new MessageData('system', "RAG instruction: {$ragSystemPrompt}"));
            }

            if ($fileContext !== null) {
                $messages->push(new MessageData('system', $fileContext));
            }
        }

        if ($crossChatMemoryEnabled) {
            $relevantContext = $this->getRelevantContext($persona, $history, $ragConfig);
            if ($relevantContext->isNotEmpty()) {
                $messages->push(new MessageData('system', $this->formatRelevantContextMessage($relevantContext)));

                Log::info('Added RAG context to conversation', [
                    'persona_id' => $persona->id,
                    'context_messages' => $relevantContext->count(),
                ]);
            }
        }

        // History (last 10 messages)
        return $messages->concat($history);
    }

    /**
     * Generate response using agentic tool calling loop
     */
    protected function generateWithTools($driver, Collection $messages, float $temperature, array $toolContext = []): string
    {
        $tools = $this->toolExecutor->getAllTools();
        $maxIterations = config('ai.max_tool_iterations', 5);
        $iteration = 0;

        while ($iteration < $maxIterations) {
            $iteration++;

            Log::info('Tool iteration', [
                'iteration' => $iteration,
                'max' => $maxIterations,
            ]);

            $result = $driver->chatWithTools($messages, $tools, $temperature);

            // If AI returned a text response (no tool calls), we're done
            if ($result['response'] !== null) {
                if (trim($result['response']->content) !== '') {
                    return $result['response']->content;
                }

                Log::warning('Tool-enabled response was empty; requesting retry', [
                    'iteration' => $iteration,
                    'max' => $maxIterations,
                ]);

                $fallbackResponse = $driver->chat($messages, $temperature);
                if (trim($fallbackResponse->content) !== '') {
                    Log::info('Recovered empty tool response via plain chat fallback', [
                        'iteration' => $iteration,
                    ]);

                    return $fallbackResponse->content;
                }

                $messages->push(new MessageData(
                    'system',
                    'Your previous answer was empty. Respond with a complete, substantive reply to the latest message.'
                ));

                continue;
            }

            // AI wants to call tools
            $toolCalls = $result['tool_calls'];
            if (empty($toolCalls)) {
                throw new \Exception('AI returned neither response nor tool calls');
            }

            Log::info('AI requested tool calls', [
                'tool_calls' => array_map(fn ($tc) => $tc['name'], $toolCalls),
            ]);

            // Execute all tool calls
            $toolResults = [];
            foreach ($toolCalls as $toolCall) {
                $toolResult = $this->toolExecutor->execute($toolCall['name'], $toolCall['arguments'], $toolContext);
                $toolResults[] = [
                    'tool_call_id' => $toolCall['id'],
                    'tool_name' => $toolCall['name'],
                    'result' => $toolResult['result'],
                    'error' => $toolResult['error'],
                ];
            }

            // Add tool results back to messages for next iteration
            $messages->push(new MessageData('system', $this->formatToolResultsForPrompt($toolResults)));
        }

        // Max iterations reached - ask AI to respond without more tools
        Log::warning('Max tool iterations reached', ['max' => $maxIterations]);
        $finalResponse = trim($driver->chat($messages, $temperature)->content);
        if ($finalResponse !== '') {
            return $finalResponse;
        }

        Log::warning('Tool-enabled generation remained empty after retries; failing generation', [
            'max_iterations' => $maxIterations,
        ]);

        throw new \RuntimeException("Tool-enabled generation remained empty after {$maxIterations} iterations.");
    }

    /**
     * Save the completed message to the database and generate embedding.
     */
    public function saveTurn(Conversation $conversation, Persona $persona, string $content, ?int $tokensUsed = null): Message
    {
        $message = $conversation->messages()->create([
            'persona_id' => $persona->id,
            'role' => 'assistant',
            'content' => $content,
            'tokens_used' => $tokensUsed,
        ]);

        // Generate embedding asynchronously (or inline if queue driver is sync)
        try {
            $vector = $this->embeddings->getEmbedding($content);
            $message->update(['embedding' => $vector]);

            // Store in Qdrant for RAG if available and enabled
            if (config('services.qdrant.enabled', false) && $this->rag->isAvailable()) {
                $message->refresh(); // Ensure we have the latest embedding
                $this->rag->storeMessage($message);
            }
        } catch (\Exception $e) {
            Log::warning("Embedding generation failed for message {$message->id}: ".$e->getMessage());
        }

        return $message;
    }

    /**
     * Get relevant context from previous conversations using RAG
     */
    protected function getRelevantContext(Persona $persona, Collection $history, array $ragConfig): Collection
    {
        if ((bool) ($ragConfig['enabled'] ?? true) === false) {
            return collect();
        }

        $conversationId = (string) ($ragConfig['conversation_id'] ?? '');
        if ($conversationId === '') {
            return collect();
        }

        $ragSourceLimit = (int) ($ragConfig['source_limit'] ?? 6);
        $ragSourceLimit = max(1, min(20, $ragSourceLimit));
        $ragScoreThreshold = (float) ($ragConfig['score_threshold'] ?? 0.3);
        $ragScoreThreshold = max(0.0, min(1.0, $ragScoreThreshold));
        $ragUserId = (int) ($ragConfig['user_id'] ?? 0);

        $lastMessage = $history->last();

        if (! $lastMessage || empty($lastMessage->content)) {
            return collect();
        }

        $relevantMessages = $this->rag->searchSimilarMessages(
            query: $lastMessage->content,
            limit: $ragSourceLimit,
            filter: array_filter([
                'persona_id' => $persona->id,
                'user_id' => $ragUserId > 0 ? $ragUserId : null,
            ], fn ($value) => $value !== null),
            scoreThreshold: $ragScoreThreshold
        );

        return $relevantMessages
            ->reject(fn ($message) => (string) $message->conversation_id === $conversationId)
            ->values();
    }

    protected function conversationRagConfig(Conversation $conversation): array
    {
        $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
        $ragConfig = is_array($metadata['rag'] ?? null) ? $metadata['rag'] : [];

        return [
            ...$ragConfig,
            'conversation_id' => $conversation->id,
            'user_id' => $conversation->user_id,
        ];
    }

    protected function templateFileContextMessage(array $ragConfig): ?string
    {
        $paths = collect($this->normalizeToArray($ragConfig['files'] ?? null, 'ragConfig.files', "conversation:{$ragConfig['conversation_id']}"))
            ->filter(fn ($path) => is_string($path) && trim($path) !== '')
            ->values();

        if ($paths->isEmpty()) {
            return null;
        }

        $userId = (int) ($ragConfig['user_id'] ?? 0);
        if ($userId <= 0) {
            return null;
        }

        $maxFiles = max(1, min(10, (int) config('ai.rag_template_max_files', 3)));
        $paths = $paths->take($maxFiles);
        $maxChars = max(600, (int) config('ai.rag_template_max_chars', 3000));
        $charsPerFile = max(200, (int) floor($maxChars / max(1, $paths->count())));
        $allowedPrefixes = ["template-rag/{$userId}/", "session-rag/{$userId}/"];
        $snippets = [];

        foreach ($paths as $path) {
            $hasAllowedPrefix = collect($allowedPrefixes)->contains(fn (string $prefix) => str_starts_with($path, $prefix));
            if (! $hasAllowedPrefix || ! Storage::disk('local')->exists($path)) {
                continue;
            }

            $snippet = $this->readTemplateFileSnippet($path, $charsPerFile);
            if ($snippet === null) {
                continue;
            }

            $snippets[] = 'File: '.basename($path)."\n".$snippet;
        }

        if ($snippets === []) {
            return null;
        }

        return "Relevant template file excerpts:\n\n".implode("\n\n---\n\n", $snippets);
    }

    /**
     * @param  Collection<int, MessageData>  $messages
     * @return Collection<int, MessageData>
     */
    protected function copyMessages(Collection $messages): Collection
    {
        return $messages->map(fn (MessageData $message) => MessageData::fromArray($message->toArray()));
    }

    /**
     * @param  Collection<int, MessageData>  $messages
     * @return Collection<int, MessageData>
     */
    protected function fitMessagesWithinPromptBudget(Collection $messages, ?string $provider): Collection
    {
        $budget = $this->promptCharBudgetForProvider($provider);
        $sanitizedMessages = $messages
            ->map(function (MessageData $message): MessageData {
                if ($message->role !== 'system') {
                    return $message;
                }

                return new MessageData(
                    role: $message->role,
                    content: $this->compactSystemMessage($message->content),
                    name: $message->name,
                );
            })
            ->values();

        while ($this->estimatedPromptChars($sanitizedMessages) > $budget) {
            $dropIndex = $sanitizedMessages->search(
                fn (MessageData $message): bool => $message->role !== 'system'
            );

            if ($dropIndex === false) {
                break;
            }

            $sanitizedMessages->forget($dropIndex);
            $sanitizedMessages = $sanitizedMessages->values();
        }

        return $sanitizedMessages;
    }

    /**
     * @param  Collection<int, object>  $relevantContext
     */
    protected function formatRelevantContextMessage(Collection $relevantContext): string
    {
        $perMessageChars = max(120, (int) config('ai.rag_context_message_max_chars', 600));
        $maxChars = max($perMessageChars, (int) config('ai.rag_context_max_chars', 4000));
        $lines = [];

        foreach ($relevantContext as $contextMessage) {
            $createdAt = $contextMessage->created_at ?? null;
            $relativeTime = is_object($createdAt) && method_exists($createdAt, 'diffForHumans')
                ? $contextMessage->created_at->diffForHumans()
                : 'earlier';

            $lines[] = sprintf(
                '- [%s] %s',
                $relativeTime,
                Str::limit(trim((string) $contextMessage->content), $perMessageChars, '...')
            );
        }

        return Str::limit(
            "Relevant context from previous conversations:\n\n".implode("\n", $lines),
            $maxChars,
            '...'
        );
    }

    /**
     * @param  array<int, array{tool_call_id:string,tool_name:string,result:mixed,error:mixed}>  $toolResults
     */
    protected function formatToolResultsForPrompt(array $toolResults): string
    {
        $maxEntries = max(1, (int) config('ai.tool_result_max_entries', 3));
        $entryMaxChars = max(120, (int) config('ai.tool_result_entry_max_chars', 1200));
        $totalMaxChars = max($entryMaxChars, (int) config('ai.tool_result_max_chars', 4000));
        $lines = ['Tool execution results:'];

        foreach (array_slice($toolResults, 0, $maxEntries) as $toolResult) {
            $resultText = $toolResult['error']
                ? 'Error: '.Str::limit($this->encodeToolResult($toolResult['error']), $entryMaxChars, '...')
                : 'Result: '.Str::limit($this->encodeToolResult($toolResult['result']), $entryMaxChars, '...');

            $lines[] = "Tool: {$toolResult['tool_name']}";
            $lines[] = $resultText;
        }

        if (count($toolResults) > $maxEntries) {
            $lines[] = sprintf('Additional tool results omitted: %d', count($toolResults) - $maxEntries);
        }

        return Str::limit(implode("\n", $lines), $totalMaxChars, '...');
    }

    protected function encodeToolResult(mixed $result): string
    {
        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($encoded !== false) {
            return $encoded;
        }

        if (is_scalar($result) || $result === null) {
            return (string) $result;
        }

        return '[unserializable tool result]';
    }

    protected function compactSystemMessage(string $content): string
    {
        if (str_starts_with($content, 'Relevant context from previous conversations:')) {
            return Str::limit($content, max(120, (int) config('ai.rag_context_max_chars', 4000)), '...');
        }

        if (str_starts_with($content, 'Relevant template file excerpts:')) {
            return Str::limit($content, max(120, (int) config('ai.rag_template_prompt_max_chars', 2500)), '...');
        }

        if (str_starts_with($content, 'Tool execution results:')) {
            return Str::limit($content, max(120, (int) config('ai.tool_result_max_chars', 4000)), '...');
        }

        return $content;
    }

    /**
     * @param  Collection<int, MessageData>  $messages
     */
    protected function estimatedPromptChars(Collection $messages): int
    {
        return $messages->sum(fn (MessageData $message): int => mb_strlen($message->content) + 32);
    }

    protected function promptCharBudgetForProvider(?string $provider): int
    {
        $providerKey = is_string($provider) && $provider !== '' ? strtolower($provider) : 'default';
        $providerBudget = config("ai.prompt_char_budgets.{$providerKey}");

        if (is_numeric($providerBudget)) {
            return max(1000, (int) $providerBudget);
        }

        return max(1000, (int) config('ai.prompt_char_budgets.default', 120000));
    }

    /**
     * Normalize a value to a plain PHP array.
     *
     * Handles: array → as-is, Collection → all(), JSON-encoded array string → decoded,
     * other non-iterable values → [] with a warning log so failures stay non-fatal.
     *
     * @return array<mixed>
     */
    protected function normalizeToArray(mixed $value, string $fieldName, string $logContext = ''): array
    {
        if (is_array($value)) {
            return $value;
        }

        if ($value instanceof \Illuminate\Support\Collection) {
            return $value->all();
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                Log::warning("ConversationService: {$fieldName} was a JSON-encoded string; decoded to array", [
                    'field' => $fieldName,
                    'context' => $logContext,
                    'sample' => mb_substr($value, 0, 120),
                ]);

                return $decoded;
            }

            Log::warning("ConversationService: {$fieldName} is a non-iterable string; skipping", [
                'field' => $fieldName,
                'type' => gettype($value),
                'context' => $logContext,
                'sample' => mb_substr($value, 0, 120),
            ]);

            return [];
        }

        if ($value !== null) {
            Log::warning("ConversationService: {$fieldName} is not iterable; skipping", [
                'field' => $fieldName,
                'type' => gettype($value),
                'context' => $logContext,
            ]);
        }

        return [];
    }

    protected function readTemplateFileSnippet(string $path, int $maxChars): ?string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $rawContent = (string) Storage::disk('local')->get($path);

        $content = match ($extension) {
            'txt', 'md', 'csv', 'json' => $this->normalizeExtractedText($rawContent),
            'docx' => $this->extractDocxText($rawContent),
            'pdf' => $this->extractPdfText($rawContent),
            default => null,
        };

        if ($content === null || $content === '') {
            return null;
        }

        return Str::limit($content, $maxChars, '...');
    }

    protected function extractDocxText(string $binaryContent): ?string
    {
        if (! class_exists(\ZipArchive::class)) {
            return null;
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'rag-docx-');
        if ($tempPath === false) {
            return null;
        }

        file_put_contents($tempPath, $binaryContent);

        $zip = new \ZipArchive;
        $opened = $zip->open($tempPath);

        if ($opened !== true) {
            @unlink($tempPath);

            return null;
        }

        $documentXml = $zip->getFromName('word/document.xml');
        $zip->close();
        @unlink($tempPath);

        if (! is_string($documentXml) || trim($documentXml) === '') {
            return null;
        }

        $text = str_replace(['</w:p>', '</w:tr>', '</w:tc>'], "\n", $documentXml);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');

        return $this->normalizeExtractedText($text);
    }

    protected function extractPdfText(string $binaryContent): ?string
    {
        preg_match_all('/\((?<text>(?:\\\\.|[^\\\\()])*)\)/s', $binaryContent, $matches);

        $segments = collect($matches['text'] ?? [])
            ->map(fn (string $segment): string => $this->decodePdfTextSegment($segment))
            ->filter(fn (string $segment): bool => $segment !== '')
            ->values();

        if ($segments->isEmpty()) {
            return null;
        }

        return $this->normalizeExtractedText($segments->implode("\n"));
    }

    protected function decodePdfTextSegment(string $segment): string
    {
        $decoded = preg_replace_callback('/\\\\([nrtbf()\\\\])/', function (array $matches): string {
            return match ($matches[1]) {
                'n' => "\n",
                'r' => "\r",
                't' => "\t",
                'b' => "\x08",
                'f' => "\f",
                default => $matches[1],
            };
        }, $segment);

        if (! is_string($decoded)) {
            return '';
        }

        $decoded = preg_replace('/\\\\[0-7]{1,3}/', ' ', $decoded) ?? $decoded;

        return trim($decoded);
    }

    protected function normalizeExtractedText(?string $content): ?string
    {
        if (! is_string($content)) {
            return null;
        }

        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8');
        }

        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $content = preg_replace('/[ \t]+/', ' ', $content) ?? $content;
        $content = preg_replace("/\n{3,}/", "\n\n", $content) ?? $content;
        $content = trim($content);

        return $content === '' ? null : $content;
    }

    /**
     * Finalize the conversation
     */
    public function completeConversation(Conversation $conversation): void
    {
        Log::info('Completing conversation', [
            'conversation_id' => $conversation->id,
            'total_messages' => $conversation->messages()->count(),
            'previous_status' => $conversation->status,
        ]);

        $conversation->update(['status' => 'completed']);
        $this->transcripts->generate($conversation);

        app(SafeBroadcaster::class)->broadcast(
            new \App\Events\ConversationStatusUpdated($conversation),
            [
                'conversation_id' => $conversation->id,
                'phase' => 'status',
            ]
        );
    }
}
