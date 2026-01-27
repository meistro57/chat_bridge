<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Api\McpController;
use App\Http\Controllers\Controller;
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
            ],
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
}
