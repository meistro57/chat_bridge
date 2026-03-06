<?php

namespace Tests\Feature;

use App\Jobs\RunChatSession;
use App\Models\Conversation;
use App\Models\Persona;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RecoverStaleConversationsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_recovers_stale_active_conversations_and_force_unlocks_hard_stale_lock(): void
    {
        Bus::fake();

        config()->set('ai.active_conversation_auto_recovery_enabled', true);
        config()->set('ai.active_conversation_kickstart_after_seconds', 30);
        config()->set('ai.active_conversation_kickstart_cooldown_seconds', 60);
        config()->set('ai.active_conversation_force_unlock_after_seconds', 120);

        $user = User::factory()->create();
        $personaA = Persona::factory()->create();
        $personaB = Persona::factory()->create();

        $conversation = Conversation::factory()->create([
            'user_id' => $user->id,
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
            'status' => 'active',
            'max_rounds' => 5,
            'updated_at' => now()->subMinutes(10),
            'created_at' => now()->subMinutes(10),
        ]);

        $conversation->messages()->create([
            'role' => 'assistant',
            'persona_id' => $personaA->id,
            'content' => 'Turn one',
        ]);

        $lockKey = (new WithoutOverlapping("run-chat-session:{$conversation->id}"))
            ->getLockKey(new RunChatSession($conversation->id, 1));

        $lock = Cache::lock($lockKey, 600);
        $this->assertTrue($lock->get());

        $this->artisan('chat:recover-stale')
            ->assertExitCode(0);

        Bus::assertDispatched(RunChatSession::class, function (RunChatSession $job) use ($conversation): bool {
            return $job->conversationId === $conversation->id
                && $job->maxRounds === 4;
        });

        $this->assertTrue(Cache::lock($lockKey, 10)->get());
    }

    public function test_it_skips_recovery_when_stop_signal_exists(): void
    {
        Bus::fake();

        config()->set('ai.active_conversation_auto_recovery_enabled', true);
        config()->set('ai.active_conversation_kickstart_after_seconds', 30);
        config()->set('ai.active_conversation_kickstart_cooldown_seconds', 60);
        config()->set('ai.active_conversation_force_unlock_after_seconds', 120);

        $user = User::factory()->create();
        $personaA = Persona::factory()->create();
        $personaB = Persona::factory()->create();

        $conversation = Conversation::factory()->create([
            'user_id' => $user->id,
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
            'status' => 'active',
            'max_rounds' => 5,
            'updated_at' => now()->subMinutes(10),
            'created_at' => now()->subMinutes(10),
        ]);

        Cache::put("conversation.stop.{$conversation->id}", true, now()->addMinutes(5));

        $this->artisan('chat:recover-stale')
            ->assertExitCode(0);

        Bus::assertNotDispatched(RunChatSession::class);
    }
}
