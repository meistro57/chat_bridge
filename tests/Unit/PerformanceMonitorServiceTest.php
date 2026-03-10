<?php

namespace Tests\Unit;

use App\Services\PerformanceMonitorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class PerformanceMonitorServiceTest extends TestCase
{
    public function test_snapshot_handles_cache_failures_gracefully(): void
    {
        Cache::shouldReceive('get')
            ->once()
            ->andThrow(new \RuntimeException('cache table unavailable'));

        $snapshot = app(PerformanceMonitorService::class)->snapshot();

        $this->assertSame(0, $snapshot['window']['requests_last_5m']);
        $this->assertSame([], $snapshot['route_breakdown']);
    }

    public function test_record_request_persists_sample_even_when_initial_cache_read_fails(): void
    {
        Cache::shouldReceive('get')
            ->once()
            ->andThrow(new \RuntimeException('cache table unavailable'));
        Cache::shouldReceive('forever')
            ->once()
            ->withArgs(function (string $key, array $samples): bool {
                return $key === 'performance:request_samples'
                    && count($samples) === 1
                    && $samples[0]['path'] === '/dashboard';
            });

        app(PerformanceMonitorService::class)->recordRequest(
            Request::create('/dashboard', 'GET'),
            new Response('ok', 200),
            42.0,
            []
        );

        $this->assertTrue(true);
    }
}
