<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\User;
use App\Services\AI\EmbeddingService;
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
            ->has('endpoints', 7)
        );
    }

    public function test_admin_can_compare_embedding_coverage(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        Message::factory()->create([
            'embedding' => null,
        ]);
        Message::factory()->create([
            'embedding' => [0.1, 0.2, 0.3],
        ]);

        $response = $this->actingAs($admin)
            ->getJson(route('admin.mcp.utilities.embeddings.compare'));

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('audit.messages_count', 2);
        $response->assertJsonPath('audit.embeddings_count', 1);
        $response->assertJsonPath('audit.missing_embeddings_count', 1);
    }

    public function test_admin_can_populate_missing_embeddings(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $missing = Message::factory()->create([
            'embedding' => null,
            'content' => 'Needs embedding',
        ]);

        $this->mock(EmbeddingService::class, function ($mock): void {
            $mock->shouldReceive('getEmbedding')
                ->once()
                ->with('Needs embedding')
                ->andReturn([0.42, 0.24, 0.12]);
        });

        $response = $this->actingAs($admin)
            ->postJson(route('admin.mcp.utilities.embeddings.populate'), [
                'limit' => 1,
            ]);

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('summary.requested_limit', 1);
        $response->assertJsonPath('summary.processed', 1);
        $response->assertJsonPath('summary.updated', 1);
        $response->assertJsonPath('summary.failed', 0);
        $response->assertJsonPath('audit.missing_embeddings_count', 0);

        $missing->refresh();
        $this->assertNotNull($missing->embedding);
        $this->assertSame([0.42, 0.24, 0.12], $missing->embedding);
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

    public function test_non_admin_cannot_compare_or_populate_embeddings(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
        ]);

        $this->actingAs($user)
            ->getJson(route('admin.mcp.utilities.embeddings.compare'))
            ->assertForbidden();

        $this->actingAs($user)
            ->postJson(route('admin.mcp.utilities.embeddings.populate'), ['limit' => 10])
            ->assertForbidden();
    }
}
