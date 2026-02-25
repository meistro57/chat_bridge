<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Redis;
use Inertia\Inertia;
use Inertia\Response;

class BoostDashboardController extends Controller
{
    public function index(): Response
    {
        $boostConfig = $this->readBoostConfig();
        $liveStats = $this->getLiveStats();

        return Inertia::render('Admin/BoostDashboard', [
            'boost' => $boostConfig,
            'liveStats' => $liveStats,
        ]);
    }

    /**
     * @return array{
     *     conversations: int,
     *     messages: int,
     *     personas: int,
     *     users: int,
     *     embeddings: int,
     *     mcp_health: array{ok: bool, status: string},
     *     redis: array{
     *         status: string,
     *         connected: bool,
     *         client: string,
     *         host: string|null,
     *         port: int|null,
     *         database: int|null,
     *         keys: int|null,
     *         ping_ms: int|null,
     *         error: string|null
     *     },
     *     timestamp: string
     * }
     */
    public function stats(): JsonResponse
    {
        return response()->json($this->getLiveStats());
    }

    private function getLiveStats(): array
    {
        try {
            $mcpController = app(\App\Http\Controllers\Api\McpController::class);
            $healthResponse = $mcpController->health();
            $healthData = method_exists($healthResponse, 'getData')
                ? $healthResponse->getData(true)
                : [];

            $mcpHealth = [
                'ok' => ($healthData['status'] ?? null) === 'ok',
                'status' => $healthData['status'] ?? 'unknown',
            ];
        } catch (\Throwable) {
            $mcpHealth = [
                'ok' => false,
                'status' => 'error',
            ];
        }

        return [
            'conversations' => \App\Models\Conversation::count(),
            'messages' => \App\Models\Message::count(),
            'personas' => \App\Models\Persona::count(),
            'users' => \App\Models\User::count(),
            'embeddings' => \App\Models\Message::whereNotNull('embedding')->count(),
            'mcp_health' => $mcpHealth,
            'redis' => $this->getRedisStats(),
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array{
     *     status: string,
     *     connected: bool,
     *     client: string,
     *     host: string|null,
     *     port: int|null,
     *     database: int|null,
     *     keys: int|null,
     *     ping_ms: int|null,
     *     error: string|null
     * }
     */
    private function getRedisStats(): array
    {
        $host = config('database.redis.default.host');
        $port = config('database.redis.default.port');
        $database = config('database.redis.default.database');
        $client = (string) config('database.redis.client', 'unknown');
        $cacheDriver = (string) config('cache.default', '');
        $queueConnection = (string) config('queue.default', '');

        $stats = [
            'status' => ($cacheDriver === 'redis' || $queueConnection === 'redis') ? 'checking' : 'optional',
            'connected' => false,
            'client' => $client,
            'host' => is_string($host) ? $host : null,
            'port' => is_int($port) ? $port : (is_numeric($port) ? (int) $port : null),
            'database' => is_int($database) ? $database : (is_numeric($database) ? (int) $database : null),
            'keys' => null,
            'ping_ms' => null,
            'error' => null,
        ];

        try {
            $startedAt = microtime(true);
            $connection = Redis::connection();
            $connection->command('PING');
            $stats['ping_ms'] = (int) round((microtime(true) - $startedAt) * 1000);
            $stats['connected'] = true;
            $stats['status'] = 'ok';

            try {
                $dbSize = $connection->command('DBSIZE');
                $stats['keys'] = is_numeric($dbSize) ? (int) $dbSize : null;
            } catch (\Throwable) {
                $stats['keys'] = null;
            }
        } catch (\Throwable $exception) {
            $stats['status'] = 'error';
            $stats['error'] = $exception->getMessage();
        }

        return $stats;
    }

    /**
     * @return array{
     *     present: bool,
     *     version: string|null,
     *     agents: array<int, string>,
     *     editors: array<int, string>,
     *     mcp_mode: string|null,
     *     vector_search: bool|null,
     *     error: string|null
     * }
     */
    private function readBoostConfig(): array
    {
        $path = base_path('boost.json');
        $present = File::exists($path);

        $version = null;
        if (class_exists(\Composer\InstalledVersions::class)) {
            try {
                $prettyVersion = \Composer\InstalledVersions::getPrettyVersion('laravel/boost');
                $version = $prettyVersion ?: \Composer\InstalledVersions::getVersion('laravel/boost');
            } catch (\OutOfBoundsException) {
                $version = null;
            }
        }

        if (! $present) {
            return [
                'present' => false,
                'version' => $version,
                'agents' => [],
                'editors' => [],
                'mcp_mode' => null,
                'vector_search' => null,
                'error' => 'boost.json is missing from the project root.',
            ];
        }

        try {
            $decoded = json_decode((string) File::get($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $exception) {
            return [
                'present' => true,
                'version' => $version,
                'agents' => [],
                'editors' => [],
                'mcp_mode' => null,
                'vector_search' => null,
                'error' => 'boost.json could not be parsed: '.$exception->getMessage(),
            ];
        }

        $agents = $this->stringListFrom($decoded['agents'] ?? []);
        $editors = $this->stringListFrom($decoded['editors'] ?? []);
        $vectorSearch = is_bool($decoded['vector_search'] ?? null)
            ? $decoded['vector_search']
            : (bool) config('services.qdrant.enabled', false);

        return [
            'present' => true,
            'version' => $version,
            'agents' => $agents,
            'editors' => $editors,
            'mcp_mode' => is_string($decoded['mcp_mode'] ?? null) ? $decoded['mcp_mode'] : null,
            'vector_search' => $vectorSearch,
            'error' => null,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function stringListFrom(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->filter(fn ($item) => is_string($item) && $item !== '')
            ->values()
            ->all();
    }
}
