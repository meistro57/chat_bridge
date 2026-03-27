<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class PersonalAccessTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_tokens_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('personal-tokens.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('PersonalTokens/Index'));
    }

    public function test_user_can_create_a_token(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('personal-tokens.store'), ['name' => 'My Integration'])
            ->assertRedirect(route('personal-tokens.index'));

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'My Integration',
        ]);
    }

    public function test_token_name_is_required(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('personal-tokens.store'), ['name' => ''])
            ->assertSessionHasErrors('name');
    }

    public function test_user_can_revoke_their_own_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('Test Token');
        $pat = PersonalAccessToken::findToken($token->plainTextToken);

        $this->actingAs($user)
            ->delete(route('personal-tokens.destroy', $pat->id))
            ->assertRedirect(route('personal-tokens.index'));

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $pat->id]);
    }

    public function test_user_cannot_revoke_another_users_token(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $token = $owner->createToken('Owner Token');
        $pat = PersonalAccessToken::findToken($token->plainTextToken);

        $this->actingAs($attacker)
            ->delete(route('personal-tokens.destroy', $pat->id))
            ->assertForbidden();

        $this->assertDatabaseHas('personal_access_tokens', ['id' => $pat->id]);
    }

    public function test_chat_bridge_endpoint_accepts_sanctum_bearer_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test');

        $this->withHeaders(['Authorization' => 'Bearer ' . $token->plainTextToken])
            ->postJson('/api/chat-bridge/respond', [])
            ->assertStatus(422); // passes auth, fails validation
    }

    public function test_chat_bridge_endpoint_still_accepts_env_token(): void
    {
        config(['services.chat_bridge.token' => 'test-env-token']);

        $this->withHeaders(['X-CHAT-BRIDGE-TOKEN' => 'test-env-token'])
            ->postJson('/api/chat-bridge/respond', [])
            ->assertStatus(422); // passes auth, fails validation
    }

    public function test_chat_bridge_endpoint_rejects_invalid_tokens(): void
    {
        config(['services.chat_bridge.token' => 'real-token']);

        $this->withHeaders(['X-CHAT-BRIDGE-TOKEN' => 'wrong-token'])
            ->postJson('/api/chat-bridge/respond', [])
            ->assertUnauthorized();
    }

    public function test_mcp_routes_require_sanctum_token(): void
    {
        $this->getJson('/api/mcp/health')
            ->assertUnauthorized();
    }

    public function test_mcp_routes_accept_sanctum_bearer_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test');

        $this->withHeaders(['Authorization' => 'Bearer ' . $token->plainTextToken])
            ->getJson('/api/mcp/health')
            ->assertOk();
    }

    public function test_contextual_memory_alias_route_accepts_sanctum_bearer_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-contextual-memory-alias');

        $this->withHeaders(['Authorization' => 'Bearer ' . $token->plainTextToken])
            ->getJson('/api/mcp/contextual_memory?topic=queues&limit=5')
            ->assertOk();
    }

    public function test_mcp_routes_reject_env_token(): void
    {
        config(['services.chat_bridge.token' => 'test-env-token']);

        $this->withHeaders(['X-CHAT-BRIDGE-TOKEN' => 'test-env-token'])
            ->getJson('/api/mcp/health')
            ->assertUnauthorized();
    }

    public function test_providers_models_requires_session_authentication(): void
    {
        $this->getJson('/api/providers/models?provider=openai')
            ->assertUnauthorized();
    }

    public function test_providers_models_is_available_to_authenticated_session_users(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/providers/models?provider=openai')
            ->assertOk();
    }
}
