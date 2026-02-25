<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Persona;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AnalyticsQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_analytics_query_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('analytics.query'));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Analytics/Query')
            ->has('results')
            ->has('filters')
            ->has('personas')
            ->has('sqlPlayground')
            ->has('sqlPlayground.examples')
            ->has('sqlPlayground.schema')
        );
    }

    public function test_user_can_run_read_only_sql_query(): void
    {
        $user = User::factory()->create();
        $persona = Persona::factory()->create(['user_id' => $user->id]);
        $conversation = Conversation::factory()->create([
            'user_id' => $user->id,
            'persona_a_id' => $persona->id,
            'persona_b_id' => $persona->id,
        ]);

        Message::factory()->create([
            'conversation_id' => $conversation->id,
            'persona_id' => $persona->id,
            'user_id' => $user->id,
            'role' => 'assistant',
            'content' => 'SQL query test row',
        ]);

        $response = $this->actingAs($user)->postJson(route('analytics.query.run-sql'), [
            'sql' => "SELECT id, role, content FROM messages WHERE user_id = {$user->id} ORDER BY id DESC",
            'limit' => 5,
        ]);

        $response->assertOk();
        $response->assertJsonPath('row_count', 1);
        $response->assertJsonPath('rows.0.content', 'SQL query test row');
        $response->assertJsonPath('truncated', false);
    }

    public function test_user_cannot_run_write_sql_query(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('analytics.query.run-sql'), [
            'sql' => "UPDATE messages SET role = 'assistant'",
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Only SELECT and WITH queries are allowed.');
    }
}
