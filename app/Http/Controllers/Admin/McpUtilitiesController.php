<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Api\McpController;
use App\Http\Controllers\Controller;
use App\Http\Requests\PopulateEmbeddingsRequest;
use App\Jobs\PopulateMessageEmbeddingJob;
use App\Models\Message;
use App\Services\AI\AIManager;
use App\Services\AI\EmbeddingService;
use App\Services\AI\MessageEmbeddingPopulator;
use App\Support\McpTrafficMonitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Inertia\Inertia;
use Inertia\Response;

class McpUtilitiesController extends Controller
{
    public function index(): Response
    {
        $health = $this->resolveMcpPayload('health');
        $stats = $this->resolveMcpPayload('stats');

        $baseUrl = rtrim((string) config('app.url'), '/');

        return Inertia::render('Admin/McpUtilities', [
            'health' => $health,
            'stats' => $stats,
            'traffic' => [
                'events' => app(McpTrafficMonitor::class)->recent(40),
            ],
            'ollamaToolsSupported' => $this->resolveOllamaToolsSupport(),
            'endpoints' => [
                $this->endpointDefinition(
                    method: 'GET',
                    path: '/api/mcp/health',
                    description: 'Basic MCP health and capability flags.',
                    baseUrl: $baseUrl,
                    requiresApiKey: true,
                ),
                $this->endpointDefinition(
                    method: 'GET',
                    path: '/api/mcp/stats',
                    description: 'Conversation, message, and embedding counts.',
                    baseUrl: $baseUrl,
                    requiresApiKey: true,
                ),
                $this->endpointDefinition(
                    method: 'GET',
                    path: '/api/mcp/recent-chats?limit=10',
                    description: 'Most recent conversations (limit defaults to 10).',
                    baseUrl: $baseUrl,
                    requiresApiKey: true,
                ),
                $this->endpointDefinition(
                    method: 'GET',
                    path: '/api/mcp/search-chats?keyword=memory',
                    description: 'Keyword search across message content.',
                    baseUrl: $baseUrl,
                    requiresApiKey: true,
                ),
                $this->endpointDefinition(
                    method: 'GET',
                    path: '/api/mcp/contextual-memory?topic=queues&limit=5',
                    description: 'Embedding-powered contextual memory lookup with keyword fallback.',
                    baseUrl: $baseUrl,
                    requiresApiKey: true,
                ),
                $this->endpointDefinition(
                    method: 'GET',
                    path: '/api/admin/mcp-utilities/embeddings/compare',
                    description: 'Compare message totals vs. embeddings and return missing counts.',
                    baseUrl: $baseUrl,
                    requiresApiKey: true,
                ),
                $this->endpointDefinition(
                    method: 'POST',
                    path: '/api/admin/mcp-utilities/embeddings/populate',
                    description: 'Generate embeddings for messages that are missing them.',
                    baseUrl: $baseUrl,
                    requiresApiKey: true,
                    jsonBody: ['limit' => 100],
                ),
                $this->endpointDefinition(
                    method: 'GET',
                    path: '/api/admin/mcp-utilities/traffic?limit=40&provider=ollama',
                    description: 'Recent in-app MCP tool traffic (filterable by provider).',
                    baseUrl: $baseUrl,
                    requiresApiKey: true,
                ),
                $this->endpointDefinition(
                    method: 'POST',
                    path: '/api/admin/mcp-utilities/flush',
                    description: 'Flush failed queue jobs and stale RunChatSession overlap locks, then restart workers.',
                    baseUrl: $baseUrl,
                    requiresApiKey: true,
                ),
            ],
        ]);
    }

    public function traffic(Request $request, McpTrafficMonitor $trafficMonitor): JsonResponse
    {
        $limit = max(1, min((int) $request->query('limit', 40), 250));
        $provider = $request->query('provider');
        $provider = is_string($provider) && trim($provider) !== '' ? trim($provider) : null;

        return response()->json([
            'ok' => true,
            'events' => $trafficMonitor->recent($limit, $provider),
        ]);
    }

    public function compareEmbeddings(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'audit' => $this->embeddingAudit(),
        ]);
    }

    public function populateEmbeddings(
        PopulateEmbeddingsRequest $request,
        EmbeddingService $embeddingService,
        MessageEmbeddingPopulator $messageEmbeddingPopulator,
    ): JsonResponse {
        $limit = $request->integer('limit');
        $updated = 0;
        $failed = 0;
        $skipped = 0;
        $queuedRetries = 0;
        $maxAttempts = $messageEmbeddingPopulator->maxAttempts();

        $messages = Message::query()
            ->whereNull('embedding')
            ->where(function ($query) {
                $query->whereNull('embedding_status')
                    ->orWhere('embedding_status', '!=', 'skipped');
            })
            ->where(function ($query) {
                $query->whereNull('embedding_next_retry_at')
                    ->orWhere('embedding_next_retry_at', '<=', now());
            })
            ->where('embedding_attempts', '<', $maxAttempts)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($messages as $message) {
            $result = $messageEmbeddingPopulator->populate($message, $embeddingService);

            if ($result['status'] === 'embedded') {
                $updated++;

                continue;
            }

            if ($result['status'] === 'skipped') {
                $skipped++;

                continue;
            }

            $failed++;
            report($result['error']);

            if (
                $result['retriable']
                && $result['next_retry_at'] !== null
                && config('queue.default') !== 'sync'
            ) {
                PopulateMessageEmbeddingJob::dispatch($message->id)
                    ->delay($result['next_retry_at']);
                $queuedRetries++;
            }
        }

        $audit = $this->embeddingAudit();

        return response()->json([
            'ok' => true,
            'summary' => [
                'requested_limit' => $limit,
                'processed' => $messages->count(),
                'updated' => $updated,
                'failed' => $failed,
                'skipped' => $skipped,
                'queued_retries' => $queuedRetries,
                'remaining_missing' => $audit['missing_embeddings_count'],
            ],
            'audit' => $audit,
        ]);
    }

    public function flush(): JsonResponse
    {
        $failedJobsBefore = (int) DB::table('failed_jobs')->count();
        $clearedLockKeys = 0;
        $errors = [];
        $warnings = [];

        try {
            Artisan::call('queue:flush');
        } catch (\Throwable $exception) {
            $errors[] = 'queue:flush failed: '.$exception->getMessage();
        }

        try {
            $clearedLockKeys += $this->clearRedisKeysByPattern('*laravel-queue-overlap:App\\Jobs\\RunChatSession:*');
            $clearedLockKeys += $this->clearRedisKeysByPattern('*conversation.kickstart.*');
        } catch (\Throwable $exception) {
            $warnings[] = 'lock cleanup skipped: '.$exception->getMessage();
        }

        try {
            Artisan::call('queue:restart');
        } catch (\Throwable $exception) {
            $errors[] = 'queue:restart failed: '.$exception->getMessage();
        }

        $failedJobsAfter = (int) DB::table('failed_jobs')->count();

        return response()->json([
            'ok' => $errors === [],
            'summary' => [
                'failed_jobs_before' => $failedJobsBefore,
                'failed_jobs_after' => $failedJobsAfter,
                'failed_jobs_flushed' => max($failedJobsBefore - $failedJobsAfter, 0),
                'cleared_lock_keys' => $clearedLockKeys,
            ],
            'errors' => $errors,
            'warnings' => $warnings,
        ], $errors === [] ? 200 : 500);
    }

    private function resolveMcpPayload(string $action): array
    {
        try {
            $controller = app(McpController::class);
            $response = $controller->{$action}();
            $payload = method_exists($response, 'getData')
                ? $response->getData(true)
                : [];

            return [
                'ok' => ($payload['status'] ?? null) === 'ok' || $action === 'stats',
                'payload' => $payload,
            ];
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'payload' => [
                    'status' => 'error',
                    'message' => $exception->getMessage(),
                ],
            ];
        }
    }

    /**
     * @param  array<string, int|string|float|bool|null>|null  $jsonBody
     * @return array{method: string, path: string, description: string, url: string, curl: string}
     */
    private function endpointDefinition(
        string $method,
        string $path,
        string $description,
        string $baseUrl,
        bool $requiresApiKey = false,
        ?array $jsonBody = null,
    ): array {
        $url = $baseUrl.$path;

        $curlParts = ['curl'];

        if ($method !== 'GET') {
            $curlParts[] = '-X '.$method;
        }

        $curlParts[] = '-H "Accept: application/json"';

        if ($requiresApiKey) {
            $curlParts[] = '-H "Authorization: Bearer YOUR_PERSONAL_ACCESS_TOKEN"';
        }

        if ($jsonBody !== null) {
            $jsonPayload = json_encode($jsonBody, JSON_THROW_ON_ERROR);
            $curlParts[] = '-H "Content-Type: application/json"';
            $curlParts[] = "-d '".$jsonPayload."'";
        }

        $curlParts[] = '"'.$url.'"';

        return [
            'method' => $method,
            'path' => $path,
            'description' => $description,
            'url' => $url,
            'curl' => implode(' ', $curlParts),
        ];
    }

    /**
     * @return array{messages_count:int,embeddings_count:int,missing_embeddings_count:int,unembeddable_count:int,retryable_failed_count:int,terminal_failed_count:int,coverage_percent:float,checked_at:string}
     */
    private function embeddingAudit(): array
    {
        $messagesCount = Message::query()->count();
        $embeddingsCount = Message::query()->whereNotNull('embedding')->count();
        $missingCount = Message::query()->whereNull('embedding')->count();
        $maxAttempts = max(1, (int) config('ai.embedding_population_max_attempts', 5));

        $unembeddableCount = Message::query()
            ->whereNull('embedding')
            ->where('embedding_status', 'skipped')
            ->count();

        $retryableFailedCount = Message::query()
            ->whereNull('embedding')
            ->where('embedding_status', 'failed')
            ->where('embedding_attempts', '<', $maxAttempts)
            ->count();

        $terminalFailedCount = Message::query()
            ->whereNull('embedding')
            ->where('embedding_status', 'failed')
            ->where('embedding_attempts', '>=', $maxAttempts)
            ->count();

        $coveragePercent = $messagesCount > 0
            ? round(($embeddingsCount / $messagesCount) * 100, 2)
            : 100.0;

        return [
            'messages_count' => $messagesCount,
            'embeddings_count' => $embeddingsCount,
            'missing_embeddings_count' => $missingCount,
            'unembeddable_count' => $unembeddableCount,
            'retryable_failed_count' => $retryableFailedCount,
            'terminal_failed_count' => $terminalFailedCount,
            'coverage_percent' => $coveragePercent,
            'checked_at' => now()->toIso8601String(),
        ];
    }

    private function resolveOllamaToolsSupport(): bool
    {
        try {
            $driver = app(AIManager::class)->driverForProvider(
                'ollama',
                (string) config('services.ollama.model', 'llama3.1')
            );

            return $driver->supportsTools();
        } catch (\Throwable) {
            return false;
        }
    }

    private function clearRedisKeysByPattern(string $pattern): int
    {
        $keys = Redis::keys($pattern);

        if (! is_array($keys) || $keys === []) {
            return 0;
        }

        return (int) Redis::del($keys);
    }
}
