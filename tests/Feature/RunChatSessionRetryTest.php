<?php

namespace Tests\Feature;

use App\Jobs\RunChatSession;
use App\Models\Conversation;
use App\Models\Persona;
use App\Models\User;
use App\Services\AI\StopWordService;
use App\Services\ConversationService;
use App\Services\Discord\DiscordStreamer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class RunChatSessionRetryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_retries_retryable_turn_exception_and_continues_conversation(): void
    {
        config()->set('ai.turn_exception_retry_attempts', 1);
        config()->set('ai.turn_exception_retry_delay_ms', 0);
        config()->set('ai.initial_stream_enabled', false);

        $user = User::factory()->create();
        $personaA = Persona::factory()->create(['user_id' => $user->id]);
        $personaB = Persona::factory()->create(['user_id' => $user->id]);
        $conversation = Conversation::factory()->create([
            'user_id' => $user->id,
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
            'max_rounds' => 1,
            'status' => 'active',
            'stop_word_detection' => false,
            'metadata' => ['notifications_enabled' => false],
        ]);

        $conversation->messages()->create([
            'role' => 'user',
            'content' => 'Start the chat',
        ]);

        $driver = new class
        {
            public function getLastTokenUsage(): int
            {
                return 42;
            }
        };

        $service = Mockery::mock(ConversationService::class);
        $service->shouldReceive('generateTurn')
            ->once()
            ->andThrow(new \RuntimeException('cURL error 28: operation timed out'));
        $service->shouldReceive('generateTurn')
            ->once()
            ->andReturn([
                'content' => (function () {
                    yield 'Recovered response';
                })(),
                'driver' => $driver,
            ]);
        $service->shouldReceive('saveTurn')
            ->once()
            ->andReturnUsing(function (Conversation $conversationArg, Persona $personaArg, string $content, ?int $tokensUsed) {
                return $conversationArg->messages()->create([
                    'persona_id' => $personaArg->id,
                    'role' => 'assistant',
                    'content' => $content,
                    'tokens_used' => $tokensUsed,
                ]);
            });
        $service->shouldReceive('completeConversation')
            ->once()
            ->andReturnUsing(function (Conversation $conversationArg): void {
                $conversationArg->update(['status' => 'completed']);
            });

        $job = new RunChatSession($conversation->id, 1);
        $job->handle($service, app(StopWordService::class), app(DiscordStreamer::class));

        $conversation->refresh();
        $this->assertSame('completed', $conversation->status);
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Recovered response',
            'tokens_used' => 42,
        ]);
    }

    public function test_it_fails_conversation_when_turn_stays_empty_after_retries(): void
    {
        config()->set('ai.empty_turn_retry_attempts', 1);
        config()->set('ai.empty_turn_retry_delay_ms', 0);
        config()->set('ai.turn_rescue_attempts', 0);
        config()->set('ai.initial_stream_enabled', false);

        $user = User::factory()->create();
        $personaA = Persona::factory()->create(['user_id' => $user->id]);
        $personaB = Persona::factory()->create(['user_id' => $user->id]);
        $conversation = Conversation::factory()->create([
            'user_id' => $user->id,
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
            'max_rounds' => 1,
            'status' => 'active',
            'stop_word_detection' => false,
            'metadata' => ['notifications_enabled' => false],
        ]);

        $conversation->messages()->create([
            'role' => 'user',
            'content' => 'Start the chat',
        ]);

        $driver = new class
        {
            public function getLastTokenUsage(): int
            {
                return 7;
            }
        };

        $service = Mockery::mock(ConversationService::class);
        $service->shouldReceive('generateTurn')
            ->twice()
            ->andReturnUsing(fn () => [
                'content' => (function () {
                    if (false) {
                        yield '';
                    }
                })(),
                'driver' => $driver,
            ]);
        $service->shouldReceive('saveTurn')->never();
        $service->shouldReceive('completeConversation')->never();

        $job = new RunChatSession($conversation->id, 1);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Turn failed after retries: empty response');
        try {
            $job->handle($service, app(StopWordService::class), app(DiscordStreamer::class));
        } finally {
            $conversation->refresh();
        }

        $this->assertSame('failed', $conversation->status);
        $this->assertSame(0, $conversation->messages()->where('role', 'assistant')->count());
        $this->assertSame('empty_turn_exhausted', data_get($conversation->metadata, 'last_error_context.code'));
        $this->assertSame('openai', data_get($conversation->metadata, 'last_error_context.provider'));
    }

    public function test_it_fails_conversation_when_retryable_exception_persists(): void
    {
        config()->set('ai.turn_exception_retry_attempts', 1);
        config()->set('ai.turn_exception_retry_delay_ms', 0);
        config()->set('ai.turn_rescue_attempts', 0);
        config()->set('ai.initial_stream_enabled', false);

        $user = User::factory()->create();
        $personaA = Persona::factory()->create(['user_id' => $user->id]);
        $personaB = Persona::factory()->create(['user_id' => $user->id]);
        $conversation = Conversation::factory()->create([
            'user_id' => $user->id,
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
            'max_rounds' => 1,
            'status' => 'active',
            'stop_word_detection' => false,
            'metadata' => ['notifications_enabled' => false],
        ]);

        $conversation->messages()->create([
            'role' => 'user',
            'content' => 'Start the chat',
        ]);

        $service = Mockery::mock(ConversationService::class);
        $service->shouldReceive('generateTurn')
            ->twice()
            ->andThrow(new \RuntimeException('cURL error 28: operation timed out'));
        $service->shouldReceive('saveTurn')->never();
        $service->shouldReceive('completeConversation')->never();

        $job = new RunChatSession($conversation->id, 1);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Turn failed after retries: empty response');
        try {
            $job->handle($service, app(StopWordService::class), app(DiscordStreamer::class));
        } finally {
            $conversation->refresh();
        }

        $this->assertSame('failed', $conversation->status);
        $this->assertSame(0, $conversation->messages()->where('role', 'assistant')->count());
        $this->assertSame('empty_turn_exhausted', data_get($conversation->metadata, 'last_error_context.code'));
        $this->assertSame('cURL error 28: operation timed out', data_get($conversation->metadata, 'last_error_context.retryable_exception'));
    }

    public function test_it_uses_rescue_turn_before_fallback_for_empty_turns(): void
    {
        config()->set('ai.empty_turn_retry_attempts', 1);
        config()->set('ai.empty_turn_retry_delay_ms', 0);
        config()->set('ai.turn_rescue_attempts', 1);
        config()->set('ai.initial_stream_enabled', false);
        config()->set('ai.empty_turn_fallback_message', 'Fallback turn content');

        $user = User::factory()->create();
        $personaA = Persona::factory()->create(['user_id' => $user->id]);
        $personaB = Persona::factory()->create(['user_id' => $user->id]);
        $conversation = Conversation::factory()->create([
            'user_id' => $user->id,
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
            'max_rounds' => 1,
            'status' => 'active',
            'stop_word_detection' => false,
            'metadata' => ['notifications_enabled' => false],
        ]);

        $conversation->messages()->create([
            'role' => 'user',
            'content' => 'Start the chat',
        ]);

        $driver = new class
        {
            public function getLastTokenUsage(): int
            {
                return 9;
            }
        };

        $service = Mockery::mock(ConversationService::class);
        $service->shouldReceive('generateTurn')
            ->twice()
            ->andReturnUsing(fn () => [
                'content' => (function () {
                    if (false) {
                        yield '';
                    }
                })(),
                'driver' => $driver,
            ]);
        $service->shouldReceive('generateTurn')
            ->once()
            ->andReturn([
                'content' => (function () {
                    yield 'Recovered by rescue turn';
                })(),
                'driver' => $driver,
            ]);
        $service->shouldReceive('saveTurn')
            ->once()
            ->andReturnUsing(function (Conversation $conversationArg, Persona $personaArg, string $content, ?int $tokensUsed) {
                return $conversationArg->messages()->create([
                    'persona_id' => $personaArg->id,
                    'role' => 'assistant',
                    'content' => $content,
                    'tokens_used' => $tokensUsed,
                ]);
            });
        $service->shouldReceive('completeConversation')
            ->once()
            ->andReturnUsing(function (Conversation $conversationArg): void {
                $conversationArg->update(['status' => 'completed']);
            });

        $job = new RunChatSession($conversation->id, 1);
        $job->handle($service, app(StopWordService::class), app(DiscordStreamer::class));

        $conversation->refresh();
        $this->assertSame('completed', $conversation->status);
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Recovered by rescue turn',
            'tokens_used' => 9,
        ]);
    }

    public function test_it_broadcasts_to_discord_when_streaming_is_enabled(): void
    {
        config()->set('ai.initial_stream_enabled', false);
        config()->set('discord.enabled', true);

        $user = User::factory()->create();
        $personaA = Persona::factory()->create(['user_id' => $user->id]);
        $personaB = Persona::factory()->create(['user_id' => $user->id]);
        $conversation = Conversation::factory()->create([
            'user_id' => $user->id,
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
            'max_rounds' => 1,
            'status' => 'active',
            'stop_word_detection' => false,
            'discord_streaming_enabled' => true,
            'discord_webhook_url' => 'https://discord.com/api/webhooks/test/webhook',
            'metadata' => ['notifications_enabled' => false],
        ]);

        $conversation->messages()->create([
            'role' => 'user',
            'content' => 'Start the chat',
        ]);

        $driver = new class
        {
            public function getLastTokenUsage(): int
            {
                return 12;
            }
        };

        $service = Mockery::mock(ConversationService::class);
        $service->shouldReceive('generateTurn')
            ->once()
            ->andReturn([
                'content' => (function () {
                    yield 'Discord enabled response';
                })(),
                'driver' => $driver,
            ]);
        $service->shouldReceive('saveTurn')
            ->once()
            ->andReturnUsing(function (Conversation $conversationArg, Persona $personaArg, string $content, ?int $tokensUsed) {
                return $conversationArg->messages()->create([
                    'persona_id' => $personaArg->id,
                    'role' => 'assistant',
                    'content' => $content,
                    'tokens_used' => $tokensUsed,
                ]);
            });
        $service->shouldReceive('completeConversation')
            ->once()
            ->andReturnUsing(function (Conversation $conversationArg): void {
                $conversationArg->update(['status' => 'completed']);
            });

        $discordStreamer = Mockery::mock(DiscordStreamer::class);
        $discordStreamer->shouldReceive('startConversation')->once();
        $discordStreamer->shouldReceive('postMessage')->once();
        $discordStreamer->shouldReceive('conversationCompleted')->once();
        $discordStreamer->shouldReceive('conversationFailed')->never();

        $job = new RunChatSession($conversation->id, 1);
        $job->handle($service, app(StopWordService::class), $discordStreamer);

        $conversation->refresh();
        $this->assertSame('completed', $conversation->status);
    }

    public function test_it_uses_configured_memory_history_limit_for_turn_context(): void
    {
        config()->set('ai.initial_stream_enabled', false);

        $user = User::factory()->create();
        $personaA = Persona::factory()->create(['user_id' => $user->id]);
        $personaB = Persona::factory()->create(['user_id' => $user->id]);
        $conversation = Conversation::factory()->create([
            'user_id' => $user->id,
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
            'max_rounds' => 1,
            'status' => 'active',
            'stop_word_detection' => false,
            'metadata' => [
                'notifications_enabled' => false,
                'memory' => ['history_limit' => 3],
            ],
        ]);

        $conversation->messages()->create(['role' => 'user', 'content' => 'Start']);
        $conversation->messages()->create(['role' => 'assistant', 'persona_id' => $personaA->id, 'content' => 'A1']);
        $conversation->messages()->create(['role' => 'assistant', 'persona_id' => $personaB->id, 'content' => 'B1']);
        $conversation->messages()->create(['role' => 'assistant', 'persona_id' => $personaA->id, 'content' => 'A2']);
        $conversation->messages()->create(['role' => 'assistant', 'persona_id' => $personaB->id, 'content' => 'B2']);

        $driver = new class
        {
            public function getLastTokenUsage(): int
            {
                return 5;
            }
        };

        $service = Mockery::mock(ConversationService::class);
        $service->shouldReceive('generateTurn')
            ->once()
            ->withArgs(function (Conversation $conversationArg, Persona $personaArg, \Illuminate\Support\Collection $history) use ($conversation, $personaA): bool {
                return $conversationArg->is($conversation)
                    && $personaArg->is($personaA)
                    && $history->count() === 3
                    && $history->pluck('content')->values()->all() === ['B1', 'A2', 'B2'];
            })
            ->andReturn([
                'content' => (function () {
                    yield 'Memory-limited response';
                })(),
                'driver' => $driver,
            ]);
        $service->shouldReceive('saveTurn')
            ->once()
            ->andReturnUsing(function (Conversation $conversationArg, Persona $personaArg, string $content, ?int $tokensUsed) {
                return $conversationArg->messages()->create([
                    'persona_id' => $personaArg->id,
                    'role' => 'assistant',
                    'content' => $content,
                    'tokens_used' => $tokensUsed,
                ]);
            });
        $service->shouldReceive('completeConversation')
            ->once()
            ->andReturnUsing(function (Conversation $conversationArg): void {
                $conversationArg->update(['status' => 'completed']);
            });

        $job = new RunChatSession($conversation->id, 1);
        $job->handle($service, app(StopWordService::class), app(DiscordStreamer::class));

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Memory-limited response',
            'tokens_used' => 5,
        ]);
    }

    public function test_failed_handler_stores_error_details_in_metadata(): void
    {
        $conversation = Conversation::factory()->create([
            'status' => 'active',
            'metadata' => ['notifications_enabled' => false],
        ]);

        $job = new RunChatSession($conversation->id, 1);
        $job->failed(new \RuntimeException('Discourse API rejected post'));

        $conversation->refresh();

        $this->assertSame('failed', $conversation->status);
        $this->assertSame('Discourse API rejected post', $conversation->metadata['last_error_message'] ?? null);
        $this->assertNotEmpty($conversation->metadata['last_error_at'] ?? null);
    }
}
