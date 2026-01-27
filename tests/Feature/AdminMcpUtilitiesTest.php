<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AdminMcpUtilitiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_mcp_utilities_page(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.mcp.utilities'));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/McpUtilities')
            ->has('health', fn (AssertableInertia $health) => $health
                ->where('ok', true)
                ->has('payload.status')
                ->has('payload.mcp_mode')
                ->has('payload.version')
            )
            ->has('stats', fn (AssertableInertia $stats) => $stats
                ->where('ok', true)
                ->has('payload.conversations_count')
                ->has('payload.messages_count')
                ->has('payload.embeddings_count')
            )
            ->has('endpoints', 5)
        );
    }

    public function test_non_admin_cannot_view_mcp_utilities_page(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
        ]);

        $this->actingAs($user)
            ->get(route('admin.mcp.utilities'))
            ->assertForbidden();
    }
}
