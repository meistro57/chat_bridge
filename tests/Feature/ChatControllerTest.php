<?php

namespace Tests\Feature;

use App\Jobs\RunChatSession;
use App\Models\Conversation;
use App\Models\ConversationTemplate;
use App\Models\Persona;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ChatControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_live_status_requires_authentication(): void
    {
        $response = $this->getJson(route('chat.live-status'));

        $response->assertUnauthorized();
    }

    public function test_live_status_returns_active_conversation_progress_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $personaA = Persona::factory()->create();
        $personaB = Persona::factory()->create();

        $activeConversation = Conversation::factory()->create([
            'user_id' => $user->id,
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
            'status' => 'active',
            'max_rounds' => 10,
        ]);

        $activeConversation->messages()->create([
            'role' => 'assistant',
            'persona_id' => $personaA->id,
            'content' => 'Turn 1',
        ]);
        $activeConversation->messages()->create([
            'role' => 'assistant',
            'persona_id' => $personaB->id,
            'content' => 'Turn 2',
        ]);
        $activeConversation->messages()->create([
            'role' => 'user',
            'persona_id' => null,
            'content' => 'Starter',
        ]);

        Cache::put("conversation.stop.{$activeConversation->id}", true, now()->addMinutes(5));

        Conversation::factory()->create([
            'user_id' => $user->id,
            'status' => 'completed',
        ]);

        Conversation::factory()->create([
            'user_id' => $otherUser->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->getJson(route('chat.live-status'));

        $response->assertOk();
        $response->assertJsonPath('active_count', 1);
        $response->assertJsonPath('items.0.id', $activeConversation->id);
        $response->assertJsonPath('items.0.current_turn', 3);
        $response->assertJsonPath('items.0.max_rounds', 10);
        $response->assertJsonPath('items.0.assistant_turns', 2);
        $response->assertJsonPath('items.0.messages_count', 3);
        $response->assertJsonPath('items.0.stop_requested', true);
    }

    public function test_live_status_returns_active_conversations_when_stop_signal_is_unavailable(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\RecordPerformanceMetrics::class);

        $user = User::factory()->create();
        $personaA = Persona::factory()->create();
        $personaB = Persona::factory()->create();

        $activeConversation = Conversation::factory()->create([
            'user_id' => $user->id,
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
            'status' => 'active',
            'max_rounds' => 10,
        ]);

        $response = $this->actingAs($user)->getJson(route('chat.live-status'));

        $response->assertOk();
        $response->assertJsonPath('active_count', 1);
        $response->assertJsonPath('items.0.id', $activeConversation->id);
        $response->assertJsonPath('items.0.stop_requested', false);
    }

    public function test_live_status_kickstarts_stale_active_conversation_without_stop_signal(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $personaA = Persona::factory()->create();
        $personaB = Persona::factory()->create();

        Carbon::setTestNow(now());
        config()->set('ai.active_conversation_kickstart_after_seconds', 30);
        config()->set('ai.active_conversation_kickstart_cooldown_seconds', 60);

        $conversation = Conversation::factory()->create([
            'user_id' => $user->id,
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
            'status' => 'active',
            'max_rounds' => 8,
            'updated_at' => now()->subMinutes(2),
            'created_at' => now()->subMinutes(2),
        ]);

        $conversation->messages()->create([
            'role' => 'user',
            'content' => 'Kickoff message',
        ]);

        $response = $this->actingAs($user)->getJson(route('chat.live-status'));

        $response->assertOk();
        Bus::assertDispatched(RunChatSession::class, function (RunChatSession $job) use ($conversation): bool {
            return $job->conversationId === $conversation->id
                && $job->maxRounds === 8;
        });

        Carbon::setTestNow();
    }

    public function test_live_status_does_not_kickstart_when_stop_signal_is_present(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $personaA = Persona::factory()->create();
        $personaB = Persona::factory()->create();

        Carbon::setTestNow(now());
        config()->set('ai.active_conversation_kickstart_after_seconds', 30);

        $conversation = Conversation::factory()->create([
            'user_id' => $user->id,
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
            'status' => 'active',
            'max_rounds' => 8,
            'updated_at' => now()->subMinutes(2),
            'created_at' => now()->subMinutes(2),
        ]);

        Cache::put("conversation.stop.{$conversation->id}", true, now()->addMinutes(10));

        $response = $this->actingAs($user)->getJson(route('chat.live-status'));

        $response->assertOk();
        Bus::assertNotDispatched(RunChatSession::class);

        Carbon::setTestNow();
    }

    public function test_store_includes_template_rag_metadata_when_template_selected(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $personaA = Persona::factory()->create();
        $personaB = Persona::factory()->create();

        $template = ConversationTemplate::factory()->create([
            'user_id' => $user->id,
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
            'rag_enabled' => true,
            'rag_source_limit' => 9,
            'rag_score_threshold' => 0.42,
            'rag_system_prompt' => 'Cite retrieved context before conclusions.',
            'rag_files' => ['template-rag/1/template-1/notes.txt'],
        ]);

        $payload = [
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
            'template_id' => $template->id,
            'provider_a' => 'openai',
            'provider_b' => 'openai',
            'model_a' => 'gpt-4o-mini',
            'model_b' => 'gpt-4o-mini',
            'starter_message' => 'Begin with retrieval context.',
            'max_rounds' => 4,
            'stop_word_detection' => false,
        ];

        $response = $this->actingAs($user)->post(route('chat.store'), $payload);

        $response->assertRedirect();

        $conversation = Conversation::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($conversation);
        $this->assertSame($template->id, $conversation->metadata['template_id'] ?? null);
        $this->assertTrue($conversation->metadata['rag']['enabled'] ?? false);
        $this->assertSame(9, $conversation->metadata['rag']['source_limit'] ?? null);
        $this->assertEqualsWithDelta(0.42, (float) ($conversation->metadata['rag']['score_threshold'] ?? 0), 0.0001);
        $this->assertSame('Cite retrieved context before conclusions.', $conversation->metadata['rag']['system_prompt'] ?? null);
        $this->assertSame(['template-rag/1/template-1/notes.txt'], $conversation->metadata['rag']['files'] ?? null);
    }

    public function test_store_persists_ui_settings_and_dispatches_job(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $personaA = Persona::factory()->create();
        $personaB = Persona::factory()->create();

        $payload = [
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
            'provider_a' => 'openai',
            'provider_b' => 'openai',
            'model_a' => 'gpt-4o-mini',
            'model_b' => 'gpt-4o-mini',
            'starter_message' => 'Test kickoff prompt.',
            'max_rounds' => 7,
            'memory_history_limit' => 18,
            'memory_rag_enabled' => true,
            'memory_rag_source_limit' => 8,
            'memory_rag_score_threshold' => 0.45,
            'stop_word_detection' => true,
            'stop_words' => ['goodbye', 'halt'],
            'stop_word_threshold' => 0.5,
            'notifications_enabled' => false,
            'discord_streaming_enabled' => true,
            'discord_webhook_url' => 'https://discord.com/api/webhooks/test/webhook',
            'discourse_streaming_enabled' => true,
            'discourse_topic_id' => 42,
        ];

        $response = $this->actingAs($user)->post(route('chat.store'), $payload);

        $response->assertRedirect();

        $conversation = Conversation::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();
        $this->assertNotNull($conversation);
        $this->assertSame($user->id, $conversation->user_id);
        $this->assertSame($payload['provider_a'], $conversation->provider_a);
        $this->assertSame($payload['model_b'], $conversation->model_b);
        $this->assertEquals(1.0, $conversation->temp_a);
        $this->assertEquals(1.0, $conversation->temp_b);
        $this->assertSame($payload['max_rounds'], $conversation->max_rounds);
        $this->assertSame($payload['stop_words'], $conversation->stop_words);
        $this->assertTrue($conversation->stop_word_detection);
        $this->assertEquals($payload['stop_word_threshold'], $conversation->stop_word_threshold);
        $this->assertSame(false, $conversation->metadata['notifications_enabled'] ?? null);
        $this->assertSame($payload['memory_history_limit'], $conversation->metadata['memory']['history_limit'] ?? null);
        $this->assertSame($payload['memory_rag_enabled'], $conversation->metadata['rag']['enabled'] ?? null);
        $this->assertSame($payload['memory_rag_source_limit'], $conversation->metadata['rag']['source_limit'] ?? null);
        $this->assertEquals($payload['memory_rag_score_threshold'], $conversation->metadata['rag']['score_threshold'] ?? null);
        $this->assertTrue($conversation->discord_streaming_enabled);
        $this->assertSame($payload['discord_webhook_url'], $conversation->discord_webhook_url);
        $this->assertTrue($conversation->discourse_streaming_enabled);
        $this->assertSame($payload['discourse_topic_id'], $conversation->discourse_topic_id);

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $payload['starter_message'],
        ]);

        Bus::assertDispatched(RunChatSession::class, function (RunChatSession $job) use ($conversation, $payload) {
            return $job->conversationId === $conversation->id
                && $job->maxRounds === $payload['max_rounds'];
        });
    }

    public function test_store_accepts_max_rounds_up_to_500(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $personaA = Persona::factory()->create();
        $personaB = Persona::factory()->create();

        $payload = [
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
            'provider_a' => 'openai',
            'provider_b' => 'openai',
            'model_a' => 'gpt-4o-mini',
            'model_b' => 'gpt-4o-mini',
            'starter_message' => 'Long-running conversation kickoff.',
            'max_rounds' => 500,
            'stop_word_detection' => false,
        ];

        $response = $this->actingAs($user)->post(route('chat.store'), $payload);

        $response->assertRedirect();

        $conversation = Conversation::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($conversation);
        $this->assertSame(500, $conversation->max_rounds);

        Bus::assertDispatched(RunChatSession::class, function (RunChatSession $job) use ($conversation): bool {
            return $job->conversationId === $conversation->id
                && $job->maxRounds === 500;
        });
    }

    public function test_store_defaults_notifications_to_off_when_omitted(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $personaA = Persona::factory()->create();
        $personaB = Persona::factory()->create();

        $payload = [
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
            'provider_a' => 'openai',
            'provider_b' => 'openai',
            'model_a' => 'gpt-4o-mini',
            'model_b' => 'gpt-4o-mini',
            'starter_message' => 'Test default notifications setting.',
            'max_rounds' => 7,
            'stop_word_detection' => false,
        ];

        $response = $this->actingAs($user)->post(route('chat.store'), $payload);

        $response->assertRedirect();

        $conversation = Conversation::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($conversation);
        $this->assertSame(false, $conversation->metadata['notifications_enabled'] ?? null);
        $this->assertSame(10, $conversation->metadata['memory']['history_limit'] ?? null);
        $this->assertTrue($conversation->metadata['rag']['enabled'] ?? false);
        $this->assertSame(6, $conversation->metadata['rag']['source_limit'] ?? null);
        $this->assertEquals(0.3, $conversation->metadata['rag']['score_threshold'] ?? null);
    }

    public function test_store_defaults_discord_streaming_to_user_preference_when_omitted(): void
    {
        Bus::fake();

        $user = User::factory()->create([
            'discord_streaming_default' => true,
        ]);
        $personaA = Persona::factory()->create();
        $personaB = Persona::factory()->create();

        $payload = [
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
            'provider_a' => 'openai',
            'provider_b' => 'openai',
            'model_a' => 'gpt-4o-mini',
            'model_b' => 'gpt-4o-mini',
            'starter_message' => 'Test default discord setting.',
            'max_rounds' => 3,
            'stop_word_detection' => false,
        ];

        $response = $this->actingAs($user)->post(route('chat.store'), $payload);

        $response->assertRedirect();

        $conversation = Conversation::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($conversation);
        $this->assertTrue($conversation->discord_streaming_enabled);
        $this->assertNull($conversation->discord_webhook_url);
    }

    public function test_store_defaults_discourse_streaming_to_user_preference_when_omitted(): void
    {
        Bus::fake();

        $user = User::factory()->create([
            'discourse_streaming_default' => true,
        ]);
        $personaA = Persona::factory()->create();
        $personaB = Persona::factory()->create();

        $payload = [
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
            'provider_a' => 'openai',
            'provider_b' => 'openai',
            'model_a' => 'gpt-4o-mini',
            'model_b' => 'gpt-4o-mini',
            'starter_message' => 'Test default discourse setting.',
            'max_rounds' => 3,
            'stop_word_detection' => false,
        ];

        $response = $this->actingAs($user)->post(route('chat.store'), $payload);

        $response->assertRedirect();

        $conversation = Conversation::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($conversation);
        $this->assertTrue($conversation->discourse_streaming_enabled);
        $this->assertNull($conversation->discourse_topic_id);
    }

    public function test_transcript_downloads_markdown_from_private_storage(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $personaA = Persona::factory()->create();
        $personaB = Persona::factory()->create();

        $conversation = Conversation::create([
            'user_id' => $user->id,
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
            'provider_a' => 'openai',
            'provider_b' => 'openai',
            'model_a' => 'gpt-4o-mini',
            'model_b' => 'gpt-4o-mini',
            'temp_a' => 0.7,
            'temp_b' => 0.7,
            'starter_message' => 'Start the test.',
            'status' => 'completed',
            'max_rounds' => 1,
            'stop_word_detection' => false,
            'stop_words' => null,
            'stop_word_threshold' => 0.8,
        ]);

        $conversation->messages()->create([
            'persona_id' => $personaA->id,
            'role' => 'assistant',
            'content' => 'Transcript content.',
        ]);

        $response = $this->actingAs($user)->get(route('chat.transcript', $conversation));

        $expectedFilename = Str::slug($conversation->id).'.md';
        $storedPath = 'transcripts/'.$expectedFilename;

        $response->assertOk();
        $response->assertDownload($expectedFilename);
        Storage::disk('local')->assertExists($storedPath);

        $markdown = Storage::disk('local')->get($storedPath);
        $this->assertStringContainsString('# Conversation Report', $markdown);
        $this->assertStringContainsString('## Executive Summary', $markdown);
        $this->assertStringContainsString('## Participants', $markdown);
        $this->assertStringContainsString('## Runtime Metrics', $markdown);
        $this->assertStringContainsString('## Safety and Stop Conditions', $markdown);
        $this->assertStringContainsString('# Transcript', $markdown);
        $this->assertStringContainsString('Transcript content.', $markdown);
    }

    public function test_show_includes_markdown_message_content(): void
    {
        $user = User::factory()->create();
        $personaA = Persona::factory()->create();
        $personaB = Persona::factory()->create();

        $conversation = Conversation::create([
            'user_id' => $user->id,
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
            'provider_a' => 'openai',
            'provider_b' => 'openai',
            'model_a' => 'gpt-4o-mini',
            'model_b' => 'gpt-4o-mini',
            'temp_a' => 0.7,
            'temp_b' => 0.7,
            'starter_message' => 'Start the test.',
            'status' => 'completed',
            'max_rounds' => 1,
            'stop_word_detection' => false,
            'stop_words' => null,
            'stop_word_threshold' => 0.8,
        ]);

        $markdown = "**Bold** and `code` with a list:\n- One\n- Two";

        $conversation->messages()->create([
            'persona_id' => $personaA->id,
            'role' => 'assistant',
            'content' => $markdown,
        ]);

        $response = $this->actingAs($user)->get(route('chat.show', $conversation));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Chat/Show')
            ->has('conversation.messages', 1)
            ->where('conversation.messages.0.content', $markdown)
        );
    }

    public function test_resume_reactivates_failed_conversation_and_dispatches_remaining_rounds(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $personaA = Persona::factory()->create();
        $personaB = Persona::factory()->create();

        $conversation = Conversation::factory()->create([
            'user_id' => $user->id,
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
            'status' => 'failed',
            'max_rounds' => 5,
            'metadata' => ['notifications_enabled' => false],
        ]);

        $conversation->messages()->create([
            'persona_id' => $personaA->id,
            'role' => 'assistant',
            'content' => 'Turn one',
        ]);

        $conversation->messages()->create([
            'persona_id' => $personaB->id,
            'role' => 'assistant',
            'content' => 'Turn two',
        ]);

        $response = $this->actingAs($user)->post(route('chat.resume', $conversation));

        $response->assertRedirect();

        $conversation->refresh();
        $this->assertSame('active', $conversation->status);
        $this->assertNotEmpty($conversation->metadata['resumed_at'] ?? null);
        $this->assertSame(1, $conversation->metadata['resume_attempts'] ?? 0);

        Bus::assertDispatched(RunChatSession::class, function (RunChatSession $job) use ($conversation) {
            return $job->conversationId === $conversation->id
                && $job->maxRounds === 3;
        });
    }

    public function test_resume_rejects_non_failed_conversation(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $conversation = Conversation::factory()->create([
            'user_id' => $user->id,
            'status' => 'completed',
            'max_rounds' => 4,
        ]);

        $response = $this->actingAs($user)->post(route('chat.resume', $conversation));

        $response->assertRedirect();

        $conversation->refresh();
        $this->assertSame('completed', $conversation->status);
        Bus::assertNotDispatched(RunChatSession::class);
    }

    public function test_store_uploads_rag_session_files_and_stores_paths_in_metadata(): void
    {
        Bus::fake();
        Storage::fake('local');

        $user = User::factory()->create();
        $personaA = Persona::factory()->create();
        $personaB = Persona::factory()->create();

        $file = \Illuminate\Http\UploadedFile::fake()->create('research-notes.txt', 20, 'text/plain');

        $payload = [
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
            'provider_a' => 'openai',
            'provider_b' => 'openai',
            'model_a' => 'gpt-4o-mini',
            'model_b' => 'gpt-4o-mini',
            'starter_message' => 'Use the attached notes.',
            'max_rounds' => 4,
            'stop_word_detection' => false,
            'rag_session_files' => [$file],
        ];

        $response = $this->actingAs($user)->post(route('chat.store'), $payload);

        $response->assertRedirect();

        $conversation = Conversation::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($conversation);
        $ragFiles = $conversation->metadata['rag']['files'] ?? [];
        $this->assertCount(1, $ragFiles);
        $this->assertStringStartsWith("session-rag/{$user->id}/{$conversation->id}/", $ragFiles[0]);
        $this->assertStringContainsString('research-notes', $ragFiles[0]);
        Storage::disk('local')->assertExists($ragFiles[0]);
    }

    public function test_store_without_rag_session_files_leaves_files_from_template(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $personaA = Persona::factory()->create();
        $personaB = Persona::factory()->create();

        $template = ConversationTemplate::factory()->create([
            'user_id' => $user->id,
            'rag_files' => ['template-rag/1/42/context.txt'],
        ]);

        $payload = [
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
            'template_id' => $template->id,
            'provider_a' => 'openai',
            'provider_b' => 'openai',
            'model_a' => 'gpt-4o-mini',
            'model_b' => 'gpt-4o-mini',
            'starter_message' => 'No additional files.',
            'max_rounds' => 3,
            'stop_word_detection' => false,
        ];

        $response = $this->actingAs($user)->post(route('chat.store'), $payload);

        $response->assertRedirect();

        $conversation = Conversation::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($conversation);
        $this->assertSame(['template-rag/1/42/context.txt'], $conversation->metadata['rag']['files'] ?? null);
    }

    public function test_store_rejects_rag_session_files_exceeding_maximum_count(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $personaA = Persona::factory()->create();
        $personaB = Persona::factory()->create();

        $files = array_map(
            fn ($i) => \Illuminate\Http\UploadedFile::fake()->create("file-{$i}.txt", 5, 'text/plain'),
            range(1, 11)
        );

        $payload = [
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
            'provider_a' => 'openai',
            'provider_b' => 'openai',
            'model_a' => 'gpt-4o-mini',
            'model_b' => 'gpt-4o-mini',
            'starter_message' => 'Too many files.',
            'max_rounds' => 2,
            'stop_word_detection' => false,
            'rag_session_files' => $files,
        ];

        $response = $this->actingAs($user)->post(route('chat.store'), $payload);

        $response->assertSessionHasErrors('rag_session_files');
    }
}
