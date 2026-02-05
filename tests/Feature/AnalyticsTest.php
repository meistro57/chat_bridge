<?php

namespace Tests\Feature;

use App\Exports\ConversationsExport;
use App\Models\Conversation;
use App\Models\Message;
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
}
