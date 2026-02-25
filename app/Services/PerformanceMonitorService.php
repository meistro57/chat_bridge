<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class PerformanceMonitorService
{
    private const REQUEST_SAMPLES_KEY = 'performance:request_samples';

    private const MAX_SAMPLES = 500;

    /**
     * @param  array{query_count?:int,total_query_ms?:float,slow_query_count?:int,max_query_ms?:float,slow_queries?:array<int, array{sql:string,time_ms:float}>}  $dbMetrics
     */
    public function recordRequest(Request $request, Response $response, float $durationMs, array $dbMetrics = []): void
    {
        $path = ltrim($request->path(), '/');

        if ($this->shouldSkipPath($path)) {
            return;
        }

        $samples = Cache::get(self::REQUEST_SAMPLES_KEY, []);
        if (! is_array($samples)) {
            $samples = [];
        }

        $samples[] = [
            'timestamp' => now()->toIso8601String(),
            'method' => $request->method(),
            'path' => '/'.$path,
            'status' => $response->getStatusCode(),
            'duration_ms' => round($durationMs, 2),
            'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'db_query_count' => (int) ($dbMetrics['query_count'] ?? 0),
            'db_total_query_ms' => (float) ($dbMetrics['total_query_ms'] ?? 0.0),
            'db_slow_query_count' => (int) ($dbMetrics['slow_query_count'] ?? 0),
            'db_max_query_ms' => (float) ($dbMetrics['max_query_ms'] ?? 0.0),
            'db_slow_queries' => is_array($dbMetrics['slow_queries'] ?? null) ? $dbMetrics['slow_queries'] : [],
        ];

        if (count($samples) > self::MAX_SAMPLES) {
            $samples = array_slice($samples, -self::MAX_SAMPLES);
        }

        Cache::forever(self::REQUEST_SAMPLES_KEY, $samples);
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $samples = $this->validSamples();

        return [
            'window' => $this->windowSummary($samples),
            'route_breakdown' => $this->routeBreakdown($samples),
            'status_breakdown' => $this->statusBreakdown($samples),
            'recent_slow_requests' => $this->slowRequests($samples),
            'recent_slow_queries' => $this->recentSlowQueries($samples),
            'throughput' => $this->throughputSeries($samples),
            'runtime' => $this->runtimeStats(),
            'queue' => $this->queueStats(),
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $samples
     * @return array<string, int|float>
     */
    private function windowSummary(array $samples): array
    {
        $now = now();
        $fiveMinutesAgo = $now->copy()->subMinutes(5);
        $oneMinuteAgo = $now->copy()->subMinute();

        $windowSamples = collect($samples)
            ->filter(fn (array $sample): bool => isset($sample['timestamp']) && CarbonImmutable::parse((string) $sample['timestamp'])->greaterThanOrEqualTo($fiveMinutesAgo))
            ->values();

        $lastMinuteCount = collect($samples)
            ->filter(fn (array $sample): bool => isset($sample['timestamp']) && CarbonImmutable::parse((string) $sample['timestamp'])->greaterThanOrEqualTo($oneMinuteAgo))
            ->count();

        $durations = $windowSamples->pluck('duration_ms')->filter(fn ($value): bool => is_numeric($value))->map(fn ($value): float => (float) $value)->values();
        $dbTotals = $windowSamples->pluck('db_total_query_ms')->filter(fn ($value): bool => is_numeric($value))->map(fn ($value): float => (float) $value)->values();
        $dbQueryCounts = $windowSamples->pluck('db_query_count')->filter(fn ($value): bool => is_numeric($value))->map(fn ($value): float => (float) $value)->values();
        $errorCount = $windowSamples->filter(fn (array $sample): bool => ((int) ($sample['status'] ?? 200)) >= 500)->count();
        $requestsWithSlowQueries = $windowSamples->filter(fn (array $sample): bool => ((int) ($sample['db_slow_query_count'] ?? 0)) > 0)->count();

        return [
            'requests_last_minute' => $lastMinuteCount,
            'requests_last_5m' => $windowSamples->count(),
            'avg_response_ms' => round($durations->avg() ?? 0, 2),
            'p95_response_ms' => $this->percentile($durations->all(), 95),
            'max_response_ms' => round($durations->max() ?? 0, 2),
            'error_rate_percent' => $windowSamples->isNotEmpty() ? round(($errorCount / $windowSamples->count()) * 100, 2) : 0.0,
            'avg_memory_peak_mb' => round($windowSamples->pluck('memory_peak_mb')->avg() ?? 0, 2),
            'avg_db_query_count' => round($dbQueryCounts->avg() ?? 0, 2),
            'avg_db_total_query_ms' => round($dbTotals->avg() ?? 0, 2),
            'p95_db_total_query_ms' => $this->percentile($dbTotals->all(), 95),
            'max_db_total_query_ms' => round($dbTotals->max() ?? 0, 2),
            'slow_query_request_rate_percent' => $windowSamples->isNotEmpty() ? round(($requestsWithSlowQueries / $windowSamples->count()) * 100, 2) : 0.0,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $samples
     * @return array<int, array{path:string,count:int,avg_ms:float,p95_ms:float,max_ms:float}>
     */
    private function routeBreakdown(array $samples): array
    {
        return collect($samples)
            ->groupBy(fn (array $sample): string => (string) ($sample['path'] ?? '/unknown'))
            ->map(function ($group, string $path): array {
                $durations = collect($group)->pluck('duration_ms')->filter(fn ($value): bool => is_numeric($value))->map(fn ($value): float => (float) $value)->values();

                return [
                    'path' => $path,
                    'count' => $group->count(),
                    'avg_ms' => round($durations->avg() ?? 0, 2),
                    'p95_ms' => $this->percentile($durations->all(), 95),
                    'max_ms' => round($durations->max() ?? 0, 2),
                ];
            })
            ->sortByDesc('avg_ms')
            ->take(8)
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $samples
     * @return array<string, int>
     */
    private function statusBreakdown(array $samples): array
    {
        return [
            '2xx' => collect($samples)->filter(fn (array $sample): bool => ((int) ($sample['status'] ?? 0)) >= 200 && ((int) ($sample['status'] ?? 0)) < 300)->count(),
            '3xx' => collect($samples)->filter(fn (array $sample): bool => ((int) ($sample['status'] ?? 0)) >= 300 && ((int) ($sample['status'] ?? 0)) < 400)->count(),
            '4xx' => collect($samples)->filter(fn (array $sample): bool => ((int) ($sample['status'] ?? 0)) >= 400 && ((int) ($sample['status'] ?? 0)) < 500)->count(),
            '5xx' => collect($samples)->filter(fn (array $sample): bool => ((int) ($sample['status'] ?? 0)) >= 500)->count(),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $samples
     * @return array<int, array{timestamp:string,path:string,method:string,status:int,duration_ms:float}>
     */
    private function slowRequests(array $samples): array
    {
        return collect($samples)
            ->filter(fn (array $sample): bool => ((float) ($sample['duration_ms'] ?? 0)) >= 1000)
            ->sortByDesc('duration_ms')
            ->take(10)
            ->map(fn (array $sample): array => [
                'timestamp' => (string) ($sample['timestamp'] ?? now()->toIso8601String()),
                'path' => (string) ($sample['path'] ?? '/unknown'),
                'method' => (string) ($sample['method'] ?? 'GET'),
                'status' => (int) ($sample['status'] ?? 200),
                'duration_ms' => round((float) ($sample['duration_ms'] ?? 0), 2),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $samples
     * @return array<int, array{timestamp:string,path:string,sql:string,time_ms:float}>
     */
    private function recentSlowQueries(array $samples): array
    {
        return collect($samples)
            ->flatMap(function (array $sample): array {
                $queries = $sample['db_slow_queries'] ?? [];
                if (! is_array($queries)) {
                    return [];
                }

                return collect($queries)->map(function (array $query) use ($sample): array {
                    return [
                        'timestamp' => (string) ($sample['timestamp'] ?? now()->toIso8601String()),
                        'path' => (string) ($sample['path'] ?? '/unknown'),
                        'sql' => (string) ($query['sql'] ?? ''),
                        'time_ms' => round((float) ($query['time_ms'] ?? 0), 2),
                    ];
                })->all();
            })
            ->filter(fn (array $query): bool => $query['time_ms'] >= 100)
            ->sortByDesc('time_ms')
            ->take(12)
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $samples
     * @return array<int, array{minute:string,count:int}>
     */
    private function throughputSeries(array $samples): array
    {
        $buckets = [];

        foreach (range(14, 0) as $minutesAgo) {
            $minuteKey = now()->subMinutes($minutesAgo)->format('H:i');
            $buckets[$minuteKey] = 0;
        }

        foreach ($samples as $sample) {
            if (! isset($sample['timestamp'])) {
                continue;
            }

            $minuteKey = CarbonImmutable::parse((string) $sample['timestamp'])->format('H:i');
            if (array_key_exists($minuteKey, $buckets)) {
                $buckets[$minuteKey]++;
            }
        }

        return collect($buckets)
            ->map(fn (int $count, string $minute): array => [
                'minute' => $minute,
                'count' => $count,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{php_memory_mb:float,php_peak_memory_mb:float,php_memory_limit:string,system_load_1m:float|int,system_load_5m:float|int,system_load_15m:float|int,db_connection:string,cache_driver:string}
     */
    private function runtimeStats(): array
    {
        $load = function_exists('sys_getloadavg') ? sys_getloadavg() : [0, 0, 0];

        return [
            'php_memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'php_peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'php_memory_limit' => (string) ini_get('memory_limit'),
            'system_load_1m' => $load[0] ?? 0,
            'system_load_5m' => $load[1] ?? 0,
            'system_load_15m' => $load[2] ?? 0,
            'db_connection' => (string) config('database.default', 'unknown'),
            'cache_driver' => (string) config('cache.default', 'unknown'),
        ];
    }

    /**
     * @return array{queued_jobs:int,failed_jobs:int,jobs_table_present:bool,failed_jobs_table_present:bool}
     */
    private function queueStats(): array
    {
        $jobsTablePresent = Schema::hasTable('jobs');
        $failedJobsTablePresent = Schema::hasTable('failed_jobs');

        return [
            'queued_jobs' => $jobsTablePresent ? DB::table('jobs')->count() : 0,
            'failed_jobs' => $failedJobsTablePresent ? DB::table('failed_jobs')->count() : 0,
            'jobs_table_present' => $jobsTablePresent,
            'failed_jobs_table_present' => $failedJobsTablePresent,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function validSamples(): array
    {
        $samples = Cache::get(self::REQUEST_SAMPLES_KEY, []);

        if (! is_array($samples)) {
            return [];
        }

        return collect($samples)
            ->filter(fn ($sample): bool => is_array($sample) && isset($sample['timestamp'], $sample['duration_ms']))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, float>  $values
     */
    private function percentile(array $values, int $percentile): float
    {
        if ($values === []) {
            return 0.0;
        }

        sort($values);

        $index = (int) ceil(($percentile / 100) * count($values)) - 1;
        $index = max(0, min($index, count($values) - 1));

        return round($values[$index], 2);
    }

    private function shouldSkipPath(string $path): bool
    {
        foreach (['up', '_debugbar', '_ignition', 'admin/performance/stats'] as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }
}
