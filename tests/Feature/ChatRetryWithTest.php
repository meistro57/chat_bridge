<?php

namespace Tests\Feature;

use App\Jobs\RunChatSession;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ChatRetryWithTest extends TestCase
{
    use RefreshDatabase;

    public function test_retry_with_updates_model_and_resumes_failed_conversation(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $conversation = Conversation::factory()->create([
            'user_id' => $user->id,
            'status' => 'failed',
            'provider_a' => 'anthropic',
            'model_a' => 'claude-3-7-sonnet-20250219',
            'provider_b' => 'openrouter',
            'model_b' => 'deepseek/deepseek-chat',
            'max_rounds' => 10,
        ]);

        $response = $this->actingAs($user)->post("/chat/{$conversation->id}/retry-with", [
            'provider_a' => 'anthropic',
            'model_a' => 'claude-sonnet-4-5-20250929',
        ]);

        $response->assertRedirect();

        $conversation->refresh();
        $this->assertSame('claude-sonnet-4-5-20250929', $conversation->model_a);
        $this->assertSame('anthropic', $conversation->provider_a);
        $this->assertSame('deepseek/deepseek-chat', $conversation->model_b, 'Unchanged fields should be preserved');
        $this->assertSame('active', $conversation->status);
        Queue::assertPushed(RunChatSession::class);
    }

    public function test_retry_with_updates_both_agents(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $conversation = Conversation::factory()->create([
            'user_id' => $user->id,
            'status' => 'failed',
            'provider_a' => 'anthropic',
            'model_a' => 'old-model',
            'provider_b' => 'openrouter',
            'model_b' => 'old-model-b',
            'max_rounds' => 10,
        ]);

        $this->actingAs($user)->post("/chat/{$conversation->id}/retry-with", [
            'provider_a' => 'openai',
            'model_a' => 'gpt-4o',
            'provider_b' => 'deepseek',
            'model_b' => 'deepseek-chat',
        ]);

        $conversation->refresh();
        $this->assertSame('openai', $conversation->provider_a);
        $this->assertSame('gpt-4o', $conversation->model_a);
        $this->assertSame('deepseek', $conversation->provider_b);
        $this->assertSame('deepseek-chat', $conversation->model_b);
        $this->assertSame('active', $conversation->status);
    }

    public function test_retry_with_rejects_non_failed_conversation(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $conversation = Conversation::factory()->create([
            'user_id' => $user->id,
            'status' => 'completed',
            'max_rounds' => 10,
        ]);

        $response = $this->actingAs($user)->post("/chat/{$conversation->id}/retry-with", [
            'model_a' => 'gpt-4o',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        Queue::assertNothingPushed();
    }

    public function test_retry_with_rejects_other_users_conversation(): void
    {
        Queue::fake();

        $owner = User::factory()->create();
        $other = User::factory()->create();
        $conversation = Conversation::factory()->create([
            'user_id' => $owner->id,
            'status' => 'failed',
            'max_rounds' => 10,
        ]);

        $this->actingAs($other)
            ->post("/chat/{$conversation->id}/retry-with", ['model_a' => 'gpt-4o'])
            ->assertForbidden();

        Queue::assertNothingPushed();
    }
}
