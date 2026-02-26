<?php

/**
 * tests/Feature/SystemDiagnosticsTest.php
 */

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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

    public function test_admin_can_run_update_laravel_action(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.system.diagnostic'), [
            'action' => 'update_laravel',
        ]);

        $response->assertOk();

        $output = $response->json('output');
        // dump($output);
        $this->assertIsString($output);
        $this->assertStringContainsString('Updating Laravel framework', $output);
        $this->assertTrue(
            str_contains($output, 'Skipped in testing environment') || 
            str_contains($output, 'Laravel framework update completed'),
            "Output did not indicate skip or success: " . $output
        );
    }

    public function test_admin_openai_key_test_uses_response_content(): void
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'OK']],
                ],
            ], 200),
        ]);

        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('admin.system.openai-key.test'), [
            'openai_key' => str_repeat('x', 40),
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'message' => 'OpenAI key is valid.',
            'result' => 'OK',
        ]);
    }
}
