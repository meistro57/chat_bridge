<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AdminRedisDashboardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Ensure admin can view the Redis dashboard page.
     */
    public function test_admin_can_view_redis_dashboard_page(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.redis.index'));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/Redis/Index')
            ->has('snapshot')
        );
    }

    public function test_admin_can_fetch_redis_dashboard_stats(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $response = $this->actingAs($admin)->getJson(route('admin.redis.stats'));

        $response->assertOk();
        $response->assertJsonStructure([
            'status',
            'connected',
            'client',
            'host',
            'port',
            'database',
            'ping_ms',
            'db_size',
            'memory' => ['used', 'peak', 'fragmentation'],
            'traffic' => [
                'ops_per_sec',
                'total_connections_received',
                'total_commands_processed',
                'rejected_connections',
                'evicted_keys',
                'expired_keys',
            ],
            'cache' => ['hits', 'misses', 'hit_rate_percent'],
            'keyspace',
            'timestamp',
        ]);
    }

    public function test_non_admin_cannot_access_redis_dashboard(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
        ]);

        $this->actingAs($user)
            ->get(route('admin.redis.index'))
            ->assertForbidden();

        $this->actingAs($user)
            ->getJson(route('admin.redis.stats'))
            ->assertForbidden();
    }
}
