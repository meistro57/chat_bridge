<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AdminBoostDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_boost_dashboard_page(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        config(['services.qdrant.enabled' => false]);

        $response = $this->actingAs($admin)->get(route('admin.boost.dashboard'));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/BoostDashboard')
            ->has('boost', fn (AssertableInertia $boost) => $boost
                ->has('present')
                ->has('version')
                ->has('agents')
                ->has('editors')
                ->has('mcp_mode')
                ->where('vector_search', false)
                ->has('error')
            )
        );
    }

    public function test_non_admin_cannot_view_boost_dashboard_page(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
        ]);

        $this->actingAs($user)
            ->get(route('admin.boost.dashboard'))
            ->assertForbidden();
    }
}
