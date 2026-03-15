<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\User;
use App\Services\AI\EmbeddingService;
use App\Support\McpTrafficMonitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
            ->has('traffic.events')
            ->has('ollamaToolsSupported')
            ->has('endpoints', 9)
        );
    }

    public function test_admin_can_fetch_mcp_traffic_feed(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        app(McpTrafficMonitor::class)->record([
            'tool_name' => 'get_mcp_stats',
            'provider' => 'openai',
            'model' => 'gpt-5',
            'arguments' => [],
            'result' => ['conversations_count' => 10],
            'duration_ms' => 12.4,
        ]);

        $response = $this->actingAs($admin)
            ->getJson(route('admin.mcp.utilities.traffic', ['provider' => 'openai', 'limit' => 10]));

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('events.0.tool_name', 'get_mcp_stats');
        $response->assertJsonPath('events.0.provider', 'openai');
    }

    public function test_admin_mcp_web_utility_json_endpoints_require_session_auth(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $token = $admin->createToken('mcp-admin-api');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token->plainTextToken,
        ])->getJson(route('admin.mcp.utilities.traffic'));

        $response->assertUnauthorized();
    }

    public function test_admin_mcp_web_utility_post_endpoint_requires_session_auth(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $token = $admin->createToken('mcp-admin-api-post');

        Message::factory()->create([
            'embedding' => null,
            'content' => 'Populate this via bearer token',
        ]);

        $this->mock(EmbeddingService::class, function ($mock): void {
            $mock->shouldReceive('getEmbedding')
                ->never();
        });

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token->plainTextToken,
        ])->postJson(route('admin.mcp.utilities.embeddings.populate'), [
            'limit' => 1,
        ]);

        $response->assertUnauthorized();
    }

    public function test_admin_can_use_api_mcp_utility_get_endpoint_with_bearer_token(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $token = $admin->createToken('mcp-admin-api-get-via-api');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token->plainTextToken,
        ])->getJson('/api/admin/mcp-utilities/traffic');

        $response->assertOk();
        $response->assertJsonPath('ok', true);
    }

    public function test_admin_can_use_api_mcp_utility_post_endpoint_with_bearer_token(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $token = $admin->createToken('mcp-admin-api-post-via-api');

        Message::factory()->create([
            'embedding' => null,
            'content' => 'Populate through API mirror',
        ]);

        $this->mock(EmbeddingService::class, function ($mock): void {
            $mock->shouldReceive('getEmbedding')
                ->once()
                ->with('Populate through API mirror')
                ->andReturn([0.91, 0.82, 0.73]);
        });

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token->plainTextToken,
        ])->postJson('/api/admin/mcp-utilities/embeddings/populate', [
            'limit' => 1,
        ]);

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('summary.processed', 1);
        $response->assertJsonPath('summary.updated', 1);
    }

    public function test_api_mcp_utility_endpoints_require_admin_role(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
        ]);

        $token = $user->createToken('mcp-non-admin-api')->plainTextToken;

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson('/api/admin/mcp-utilities/traffic')->assertForbidden();

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->postJson('/api/admin/mcp-utilities/flush')->assertForbidden();
    }

    public function test_api_mcp_utility_endpoints_require_bearer_token(): void
    {
        $this->getJson('/api/admin/mcp-utilities/traffic')->assertUnauthorized();

        $this->postJson('/api/admin/mcp-utilities/flush')->assertUnauthorized();
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

    public function test_populate_embeddings_marks_blank_content_as_skipped(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $message = Message::factory()->create([
            'embedding' => null,
            'content' => "   ",
            'embedding_attempts' => 0,
        ]);

        $response = $this->actingAs($admin)
            ->postJson(route('admin.mcp.utilities.embeddings.populate'), [
                'limit' => 1,
            ]);

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('summary.processed', 1);
        $response->assertJsonPath('summary.skipped', 1);

        $message->refresh();
        $this->assertSame('skipped', $message->embedding_status);
        $this->assertSame('empty_or_invalid_content', $message->embedding_skip_reason);
        $this->assertSame(1, $message->embedding_attempts);
        $this->assertNull($message->embedding);
    }

    public function test_populate_embeddings_tracks_retryable_failures(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $message = Message::factory()->create([
            'embedding' => null,
            'content' => 'This provider call will fail',
            'embedding_attempts' => 0,
        ]);

        $this->mock(EmbeddingService::class, function ($mock): void {
            $mock->shouldReceive('getEmbedding')
                ->once()
                ->andThrow(new \RuntimeException('Provider unavailable'));
        });

        $response = $this->actingAs($admin)
            ->postJson(route('admin.mcp.utilities.embeddings.populate'), [
                'limit' => 1,
            ]);

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('summary.processed', 1);
        $response->assertJsonPath('summary.failed', 1);

        $message->refresh();
        $this->assertSame('failed', $message->embedding_status);
        $this->assertSame(1, $message->embedding_attempts);
        $this->assertSame('Provider unavailable', $message->embedding_last_error);
        $this->assertNotNull($message->embedding_next_retry_at);
    }

    public function test_admin_can_flush_queue_state(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        DB::table('failed_jobs')->insert([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'connection' => 'redis',
            'queue' => 'default',
            'payload' => json_encode(['job' => 'App\\Jobs\\RunChatSession']),
            'exception' => 'Test failure',
            'failed_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->postJson(route('admin.mcp.utilities.flush'));

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('summary.failed_jobs_before', 1);
        $response->assertJsonPath('summary.failed_jobs_after', 0);
        $response->assertJsonPath('summary.failed_jobs_flushed', 1);
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

        $this->actingAs($user)
            ->getJson(route('admin.mcp.utilities.traffic'))
            ->assertForbidden();

        $this->actingAs($user)
            ->postJson(route('admin.mcp.utilities.flush'))
            ->assertForbidden();
    }
}
