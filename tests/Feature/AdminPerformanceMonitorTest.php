<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AdminPerformanceMonitorTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_performance_monitor_page(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.performance.index'));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/PerformanceMonitor')
            ->has('snapshot.window')
            ->has('snapshot.route_breakdown')
            ->has('snapshot.status_breakdown')
            ->has('snapshot.recent_slow_requests')
            ->has('snapshot.throughput')
            ->has('snapshot.runtime')
            ->has('snapshot.queue')
        );
    }

    public function test_non_admin_cannot_view_performance_monitor_page(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
        ]);

        $this->actingAs($user)
            ->get(route('admin.performance.index'))
            ->assertForbidden();
    }

    public function test_admin_can_fetch_performance_stats_after_request_activity(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->actingAs($admin)->get(route('dashboard'))->assertOk();

        $response = $this->actingAs($admin)->getJson(route('admin.performance.stats'));

        $response->assertOk();
        $response->assertJsonStructure([
            'window' => [
                'requests_last_minute',
                'requests_last_5m',
                'avg_response_ms',
                'p95_response_ms',
                'max_response_ms',
                'error_rate_percent',
                'avg_memory_peak_mb',
                'avg_db_query_count',
                'avg_db_total_query_ms',
                'p95_db_total_query_ms',
                'max_db_total_query_ms',
                'slow_query_request_rate_percent',
            ],
            'route_breakdown',
            'status_breakdown' => ['2xx', '3xx', '4xx', '5xx'],
            'recent_slow_requests',
            'recent_slow_queries',
            'throughput',
            'runtime' => [
                'php_memory_mb',
                'php_peak_memory_mb',
                'php_memory_limit',
                'system_load_1m',
                'system_load_5m',
                'system_load_15m',
                'db_connection',
                'cache_driver',
            ],
            'queue' => [
                'queued_jobs',
                'failed_jobs',
                'jobs_table_present',
                'failed_jobs_table_present',
            ],
            'timestamp',
        ]);

        $this->assertGreaterThanOrEqual(1, $response->json('window.requests_last_5m'));
    }
}
