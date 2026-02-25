<?php

namespace Tests\Feature;

use App\Exports\ConversationsExport;
use App\Models\ApiKey;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\ModelPrice;
use App\Models\Persona;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_analytics_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('analytics.index'));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Analytics/Index')
            ->has('overview')
            ->has('metrics')
            ->has('tokenUsageByProvider')
            ->has('providerUsage')
            ->has('personaStats')
            ->has('trendData')
            ->has('recentConversations')
            ->has('costByProvider')
        );
    }

    public function test_metrics_endpoint_returns_expected_values(): void
    {
        Cache::flush();
        $user = User::factory()->create();
        $personaA = Persona::factory()->create(['user_id' => $user->id]);
        $personaB = Persona::factory()->create(['user_id' => $user->id]);

        $conversationOne = Conversation::factory()->for($user)->completed()->create([
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
            'provider_a' => 'openai',
            'provider_b' => 'anthropic',
            'model_a' => 'gpt-4o-mini',
            'model_b' => 'claude-sonnet-4-5-20250929',
        ]);

        $conversationTwo = Conversation::factory()->for($user)->create([
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
            'provider_a' => 'openai',
            'provider_b' => 'openai',
            'model_a' => 'gpt-4o-mini',
            'model_b' => 'gpt-4o-mini',
        ]);

        Message::factory()->create([
            'conversation_id' => $conversationOne->id,
            'persona_id' => $personaA->id,
            'tokens_used' => 100,
        ]);

        Message::factory()->create([
            'conversation_id' => $conversationOne->id,
            'persona_id' => $personaB->id,
            'tokens_used' => 200,
        ]);

        Message::factory()->create([
            'conversation_id' => $conversationTwo->id,
            'persona_id' => $personaA->id,
            'tokens_used' => 150,
        ]);

        $response = $this->actingAs($user)->getJson(route('analytics.metrics'));

        $response->assertOk();
        $response->assertJsonFragment([
            'average_length' => 1.5,
            'completion_rate' => 0.5,
        ]);

        $response->assertJsonFragment([
            'total_conversations' => 2,
            'total_messages' => 3,
            'total_tokens' => 450,
        ]);
    }

    public function test_user_can_export_analytics_csv(): void
    {
        if (! class_exists(Excel::class)) {
            $this->markTestSkipped('Laravel Excel is not available in this environment.');
        }

        Excel::fake();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-02-05 10:00:00'));

        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('analytics.export'), [
            'format' => 'csv',
        ]);

        $response->assertOk();

        Excel::assertDownloaded('chat-analytics-export-2026-02-05-100000.csv', function (ConversationsExport $export) {
            return $export !== null;
        });
    }

    public function test_user_can_clear_only_their_chat_history_data(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $personaA = Persona::factory()->create(['user_id' => $user->id]);
        $personaB = Persona::factory()->create(['user_id' => $user->id]);
        $apiKey = ApiKey::factory()->create(['user_id' => $user->id, 'provider' => 'openai']);

        $conversation = Conversation::factory()->create([
            'user_id' => $user->id,
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
        ]);
        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'persona_id' => $personaA->id,
        ]);

        $otherPersonaA = Persona::factory()->create(['user_id' => $otherUser->id]);
        $otherPersonaB = Persona::factory()->create(['user_id' => $otherUser->id]);
        $otherConversation = Conversation::factory()->create([
            'user_id' => $otherUser->id,
            'persona_a_id' => $otherPersonaA->id,
            'persona_b_id' => $otherPersonaB->id,
        ]);
        $otherMessage = Message::factory()->create([
            'conversation_id' => $otherConversation->id,
            'persona_id' => $otherPersonaA->id,
        ]);

        $response = $this->actingAs($user)->delete(route('analytics.history.clear'));

        $response->assertRedirect(route('analytics.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('conversations', ['id' => $conversation->id]);
        $this->assertDatabaseMissing('messages', ['id' => $message->id]);

        $this->assertDatabaseHas('personas', ['id' => $personaA->id]);
        $this->assertDatabaseHas('personas', ['id' => $personaB->id]);
        $this->assertDatabaseHas('api_keys', ['id' => $apiKey->id]);

        $this->assertDatabaseHas('conversations', ['id' => $otherConversation->id]);
        $this->assertDatabaseHas('messages', ['id' => $otherMessage->id]);
    }

    public function test_analytics_uses_stored_model_pricing_when_available(): void
    {
        Cache::flush();

        $user = User::factory()->create();
        $personaA = Persona::factory()->create(['user_id' => $user->id]);
        $personaB = Persona::factory()->create(['user_id' => $user->id]);

        $conversation = Conversation::factory()->for($user)->completed()->create([
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
            'provider_a' => 'openai',
            'provider_b' => 'openai',
            'model_a' => 'gpt-4o-mini',
            'model_b' => 'gpt-4o-mini',
        ]);

        Message::factory()->create([
            'conversation_id' => $conversation->id,
            'persona_id' => $personaA->id,
            'tokens_used' => 1000,
        ]);

        ModelPrice::query()->create([
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'prompt_per_million' => 10.0,
            'completion_per_million' => 10.0,
        ]);

        $response = $this->actingAs($user)->getJson(route('analytics.metrics'));
        $response->assertOk();

        $payload = $response->json();

        $this->assertSame(0.01, $payload['overview']['total_cost']);
        $this->assertSame(0.01, $payload['costByProvider'][0]['cost']);
        $this->assertSame('openai', $payload['costByProvider'][0]['provider']);
    }

    public function test_analytics_marks_unresolved_provider_instead_of_misattributing_cost(): void
    {
        Cache::flush();

        $user = User::factory()->create();
        $persona = Persona::factory()->create(['user_id' => $user->id]);

        $conversation = Conversation::factory()->for($user)->create([
            'persona_a_id' => null,
            'persona_b_id' => null,
            'provider_a' => 'openai',
            'provider_b' => 'anthropic',
            'model_a' => 'gpt-4o-mini',
            'model_b' => 'claude-sonnet-4-5-20250929',
        ]);

        Message::factory()->create([
            'conversation_id' => $conversation->id,
            'persona_id' => $persona->id,
            'tokens_used' => 1500,
        ]);

        $response = $this->actingAs($user)->getJson(route('analytics.metrics'));
        $response->assertOk();

        $providers = collect($response->json('costByProvider'))->pluck('provider')->all();

        $this->assertContains('unresolved', $providers);
        $this->assertNotContains('anthropic', $providers);
    }
}
