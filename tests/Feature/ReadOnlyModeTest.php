<?php

namespace Tests\Feature;

use App\Jobs\RunChatSession;
use App\Models\Persona;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ReadOnlyModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_mutating_http_requests_are_blocked_when_read_only_mode_is_enabled(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $personaA = Persona::factory()->create(['user_id' => $user->id]);
        $personaB = Persona::factory()->create(['user_id' => $user->id]);
        config(['safety.read_only_mode' => true]);

        $response = $this->actingAs($user)->post(route('chat.store'), [
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
            'provider_a' => 'openai',
            'provider_b' => 'openai',
            'starter_message' => 'This should be blocked',
        ]);

        $response->assertStatus(423);
        $this->assertDatabaseCount('conversations', 0);
        Queue::assertNothingPushed();
    }

    public function test_read_only_sql_runner_remains_accessible_when_read_only_mode_is_enabled(): void
    {
        $user = User::factory()->create();
        config(['safety.read_only_mode' => true]);

        $response = $this->actingAs($user)->postJson(route('analytics.query.run-sql'), [
            'sql' => 'SELECT COUNT(*) AS total_users FROM users',
            'limit' => 5,
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'columns',
            'rows',
            'row_count',
            'truncated',
            'limit',
            'execution_ms',
        ]);
    }

    public function test_database_write_queries_are_blocked_when_read_only_mode_is_enabled(): void
    {
        $user = User::factory()->create([
            'name' => 'Before',
        ]);

        config(['safety.read_only_mode' => true]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Read-only mode is enabled; blocked SQL');

        DB::statement("UPDATE users SET name = 'After' WHERE id = {$user->id}");
    }

    public function test_run_chat_session_job_exits_early_in_read_only_mode(): void
    {
        config(['safety.read_only_mode' => true]);

        $job = new RunChatSession('fake-conversation-id');

        $job->handle(
            Mockery::mock(\App\Services\ConversationService::class),
            Mockery::mock(\App\Services\AI\StopWordService::class),
            Mockery::mock(\App\Services\Discord\DiscordStreamer::class)
        );

        $this->assertTrue(true);
    }
}
