<?php

/**
 * tests/Feature/SystemDiagnosticsTest.php
 */

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class SystemDiagnosticsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Ensure the system diagnostics page exposes Codex/Boost details for admins.
     */
    public function test_admin_can_view_system_diagnostics_with_boost_details(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.system'));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/System')
            ->has('systemInfo', fn (AssertableInertia $systemInfo) => $systemInfo
                ->has('boost', fn (AssertableInertia $boost) => $boost
                    ->has('present')
                    ->has('agents')
                    ->has('editors')
                    ->has('error')
                )
                ->has('mcp', fn (AssertableInertia $mcp) => $mcp
                    ->has('ok')
                    ->has('details')
                )
                ->etc()
            )
        );
    }

    public function test_admin_can_run_fix_permissions_action(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.system.diagnostic'), [
            'action' => 'fix_permissions',
        ]);

        $response->assertOk();

        $output = $response->json('output');
        $this->assertIsString($output);
        $this->assertStringContainsString('Setting permissions', $output);
    }
}
