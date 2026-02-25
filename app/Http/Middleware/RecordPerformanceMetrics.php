<?php

namespace App\Http\Middleware;

use App\Services\PerformanceMonitorService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class RecordPerformanceMetrics
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $connection = DB::connection();
        $connection->flushQueryLog();
        $connection->enableQueryLog();

        $startedAt = microtime(true);
        $response = $next($request);
        $durationMs = (microtime(true) - $startedAt) * 1000;

        $queryLog = $connection->getQueryLog();
        $connection->disableQueryLog();
        $connection->flushQueryLog();

        $dbMetrics = $this->summarizeQueryLog($queryLog);

        app(PerformanceMonitorService::class)->recordRequest($request, $response, $durationMs, $dbMetrics);

        return $response;
    }

    /**
     * @param  array<int, array<string, mixed>>  $queryLog
     * @return array{query_count:int,total_query_ms:float,slow_query_count:int,max_query_ms:float,slow_queries:array<int, array{sql:string,time_ms:float}>}
     */
    private function summarizeQueryLog(array $queryLog): array
    {
        $queryCount = 0;
        $totalQueryMs = 0.0;
        $slowQueryCount = 0;
        $maxQueryMs = 0.0;
        $slowQueries = [];

        foreach ($queryLog as $entry) {
            $queryCount++;
            $timeMs = (float) ($entry['time'] ?? 0);
            $sql = $this->normalizeSql((string) ($entry['query'] ?? ''));

            $totalQueryMs += $timeMs;
            $maxQueryMs = max($maxQueryMs, $timeMs);

            if ($timeMs >= 100) {
                $slowQueryCount++;
                $slowQueries[] = [
                    'sql' => $sql,
                    'time_ms' => round($timeMs, 2),
                ];
            }
        }

        usort($slowQueries, fn (array $left, array $right): int => $right['time_ms'] <=> $left['time_ms']);

        return [
            'query_count' => $queryCount,
            'total_query_ms' => round($totalQueryMs, 2),
            'slow_query_count' => $slowQueryCount,
            'max_query_ms' => round($maxQueryMs, 2),
            'slow_queries' => array_slice($slowQueries, 0, 5),
        ];
    }

    private function normalizeSql(string $sql): string
    {
        $normalized = preg_replace('/\s+/', ' ', trim($sql));
        $normalized = is_string($normalized) ? $normalized : '';

        if (mb_strlen($normalized) > 180) {
            return mb_substr($normalized, 0, 177).'...';
        }

        return $normalized;
    }
}
