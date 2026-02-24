<?php

namespace Tests\Feature;

use App\Jobs\RunChatSession;
use App\Models\Conversation;
use App\Models\Persona;
use App\Models\User;
use App\Services\AI\StopWordService;
use App\Services\ConversationService;
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
        $job->handle($service, app(StopWordService::class));

        $conversation->refresh();
        $this->assertSame('completed', $conversation->status);
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Recovered response',
            'tokens_used' => 42,
        ]);
    }

    public function test_it_uses_fallback_message_when_turn_stays_empty_after_retries(): void
    {
        config()->set('ai.empty_turn_retry_attempts', 1);
        config()->set('ai.empty_turn_retry_delay_ms', 0);
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
        $job->handle($service, app(StopWordService::class));

        $conversation->refresh();
        $this->assertSame('completed', $conversation->status);
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Fallback turn content',
            'tokens_used' => 7,
        ]);
    }
}
