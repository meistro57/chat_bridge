<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Api\McpController;
use App\Http\Controllers\Controller;
use App\Http\Requests\PopulateEmbeddingsRequest;
use App\Models\Message;
use App\Services\AI\AIManager;
use App\Services\AI\EmbeddingService;
use App\Support\McpTrafficMonitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
                ),
                $this->endpointDefinition(
                    method: 'GET',
                    path: '/api/mcp/stats',
                    description: 'Conversation, message, and embedding counts.',
                    baseUrl: $baseUrl,
                ),
                $this->endpointDefinition(
                    method: 'GET',
                    path: '/api/mcp/recent-chats?limit=10',
                    description: 'Most recent conversations (limit defaults to 10).',
                    baseUrl: $baseUrl,
                ),
                $this->endpointDefinition(
                    method: 'GET',
                    path: '/api/mcp/search-chats?keyword=memory',
                    description: 'Keyword search across message content.',
                    baseUrl: $baseUrl,
                ),
                $this->endpointDefinition(
                    method: 'GET',
                    path: '/api/mcp/contextual-memory?topic=queues&limit=5',
                    description: 'Embedding-powered contextual memory lookup with keyword fallback.',
                    baseUrl: $baseUrl,
                ),
                $this->endpointDefinition(
                    method: 'GET',
                    path: '/admin/mcp-utilities/embeddings/compare',
                    description: 'Compare message totals vs. embeddings and return missing counts.',
                    baseUrl: $baseUrl,
                ),
                $this->endpointDefinition(
                    method: 'POST',
                    path: '/admin/mcp-utilities/embeddings/populate',
                    description: 'Generate embeddings for messages that are missing them.',
                    baseUrl: $baseUrl,
                ),
                $this->endpointDefinition(
                    method: 'GET',
                    path: '/admin/mcp-utilities/traffic?limit=40&provider=ollama',
                    description: 'Recent in-app MCP tool traffic (filterable by provider).',
                    baseUrl: $baseUrl,
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

    public function populateEmbeddings(PopulateEmbeddingsRequest $request, EmbeddingService $embeddingService): JsonResponse
    {
        $limit = $request->integer('limit');
        $updated = 0;
        $failed = 0;

        $messages = Message::query()
            ->whereNull('embedding')
            ->whereNotNull('content')
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'content']);

        foreach ($messages as $message) {
            try {
                $content = trim((string) $message->content);

                if ($content === '') {
                    $failed++;

                    continue;
                }

                $embedding = $embeddingService->getEmbedding($content);
                $message->update(['embedding' => $embedding]);
                $updated++;
            } catch (\Throwable $exception) {
                $failed++;
                report($exception);
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
                'remaining_missing' => $audit['missing_embeddings_count'],
            ],
            'audit' => $audit,
        ]);
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
     * @return array{method: string, path: string, description: string, url: string}
     */
    private function endpointDefinition(
        string $method,
        string $path,
        string $description,
        string $baseUrl,
    ): array {
        return [
            'method' => $method,
            'path' => $path,
            'description' => $description,
            'url' => $baseUrl.$path,
        ];
    }

    /**
     * @return array{messages_count:int,embeddings_count:int,missing_embeddings_count:int,coverage_percent:float,checked_at:string}
     */
    private function embeddingAudit(): array
    {
        $messagesCount = Message::query()->count();
        $embeddingsCount = Message::query()->whereNotNull('embedding')->count();
        $missingCount = max($messagesCount - $embeddingsCount, 0);
        $coveragePercent = $messagesCount > 0
            ? round(($embeddingsCount / $messagesCount) * 100, 2)
            : 100.0;

        return [
            'messages_count' => $messagesCount,
            'embeddings_count' => $embeddingsCount,
            'missing_embeddings_count' => $missingCount,
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
}
