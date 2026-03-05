<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Redis;
use Inertia\Inertia;
use Inertia\Response;

class RedisDashboardController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Redis/Index', [
            'snapshot' => $this->snapshot(),
        ]);
    }

    public function stats(): JsonResponse
    {
        return response()->json($this->snapshot());
    }

    /**
     * @return array{
     *     status:string,
     *     connected:bool,
     *     error:?string,
     *     client:string,
     *     host:?string,
     *     port:?int,
     *     database:?int,
     *     ping_ms:?int,
     *     db_size:?int,
     *     memory:array{used:?string,peak:?string,fragmentation:?string},
     *     traffic:array{
     *         ops_per_sec:?int,
     *         total_connections_received:?int,
     *         total_commands_processed:?int,
     *         rejected_connections:?int,
     *         evicted_keys:?int,
     *         expired_keys:?int
     *     },
     *     cache:array{hits:?int,misses:?int,hit_rate_percent:float},
     *     keyspace:array<int, array{db:string,keys:int,expires:int,avg_ttl_ms:int}>,
     *     timestamp:string
     * }
     */
    private function snapshot(): array
    {
        $host = config('database.redis.default.host');
        $port = config('database.redis.default.port');
        $database = config('database.redis.default.database');
        $client = (string) config('database.redis.client', 'unknown');

        $stats = [
            'status' => 'checking',
            'connected' => false,
            'error' => null,
            'client' => $client,
            'host' => is_string($host) ? $host : null,
            'port' => is_numeric($port) ? (int) $port : null,
            'database' => is_numeric($database) ? (int) $database : null,
            'ping_ms' => null,
            'db_size' => null,
            'memory' => [
                'used' => null,
                'peak' => null,
                'fragmentation' => null,
            ],
            'traffic' => [
                'ops_per_sec' => null,
                'total_connections_received' => null,
                'total_commands_processed' => null,
                'rejected_connections' => null,
                'evicted_keys' => null,
                'expired_keys' => null,
            ],
            'cache' => [
                'hits' => null,
                'misses' => null,
                'hit_rate_percent' => 0.0,
            ],
            'keyspace' => [],
            'timestamp' => now()->toIso8601String(),
        ];

        try {
            $connection = Redis::connection();

            $startedAt = microtime(true);
            $connection->command('PING');
            $stats['ping_ms'] = (int) round((microtime(true) - $startedAt) * 1000);
            $stats['connected'] = true;
            $stats['status'] = 'ok';

            $dbSize = $connection->command('DBSIZE');
            $stats['db_size'] = is_numeric($dbSize) ? (int) $dbSize : null;

            $infoMap = $this->parseInfoPayload($connection->command('INFO'));

            $stats['memory'] = [
                'used' => $this->nullableString($infoMap['used_memory_human'] ?? null),
                'peak' => $this->nullableString($infoMap['used_memory_peak_human'] ?? null),
                'fragmentation' => $this->nullableString($infoMap['mem_fragmentation_ratio'] ?? null),
            ];

            $hits = $this->nullableInt($infoMap['keyspace_hits'] ?? null);
            $misses = $this->nullableInt($infoMap['keyspace_misses'] ?? null);
            $hitRate = 0.0;
            if (($hits + $misses) > 0) {
                $hitRate = round(($hits / ($hits + $misses)) * 100, 2);
            }

            $stats['cache'] = [
                'hits' => $hits,
                'misses' => $misses,
                'hit_rate_percent' => $hitRate,
            ];

            $stats['traffic'] = [
                'ops_per_sec' => $this->nullableInt($infoMap['instantaneous_ops_per_sec'] ?? null),
                'total_connections_received' => $this->nullableInt($infoMap['total_connections_received'] ?? null),
                'total_commands_processed' => $this->nullableInt($infoMap['total_commands_processed'] ?? null),
                'rejected_connections' => $this->nullableInt($infoMap['rejected_connections'] ?? null),
                'evicted_keys' => $this->nullableInt($infoMap['evicted_keys'] ?? null),
                'expired_keys' => $this->nullableInt($infoMap['expired_keys'] ?? null),
            ];

            $stats['keyspace'] = $this->parseKeyspace($infoMap);
        } catch (\Throwable $exception) {
            $stats['status'] = 'error';
            $stats['error'] = $exception->getMessage();
        }

        return $stats;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseInfoPayload(mixed $payload): array
    {
        if (is_string($payload)) {
            $map = [];

            foreach (preg_split('/\r\n|\n|\r/', $payload) as $line) {
                $trimmed = trim((string) $line);
                if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                    continue;
                }

                if (! str_contains($trimmed, ':')) {
                    continue;
                }

                [$key, $value] = explode(':', $trimmed, 2);
                $map[trim($key)] = trim($value);
            }

            return $map;
        }

        if (! is_array($payload)) {
            return [];
        }

        $map = [];
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $nestedKey => $nestedValue) {
                    $map[(string) $nestedKey] = $nestedValue;
                }

                continue;
            }

            $map[(string) $key] = $value;
        }

        return $map;
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function nullableString(mixed $value): ?string
    {
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $infoMap
     * @return array<int, array{db:string,keys:int,expires:int,avg_ttl_ms:int}>
     */
    private function parseKeyspace(array $infoMap): array
    {
        $rows = [];

        foreach ($infoMap as $key => $value) {
            if (! is_string($key) || ! preg_match('/^db\d+$/', $key)) {
                continue;
            }

            $parts = collect(explode(',', (string) $value))
                ->mapWithKeys(function (string $part): array {
                    if (! str_contains($part, '=')) {
                        return [];
                    }

                    [$subKey, $subValue] = explode('=', $part, 2);

                    return [trim($subKey) => trim($subValue)];
                });

            $rows[] = [
                'db' => $key,
                'keys' => (int) ($parts['keys'] ?? 0),
                'expires' => (int) ($parts['expires'] ?? 0),
                'avg_ttl_ms' => (int) ($parts['avg_ttl'] ?? 0),
            ];
        }

        return collect($rows)->sortBy('db')->values()->all();
    }
}
