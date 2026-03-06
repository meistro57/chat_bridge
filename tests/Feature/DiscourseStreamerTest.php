<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Persona;
use App\Models\User;
use App\Services\Discourse\DiscourseStreamer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DiscourseStreamerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_topic_and_posts_starter_messages(): void
    {
        config()->set('discourse.enabled', true);
        config()->set('discourse.base_url', 'https://forum.example.com');
        config()->set('discourse.api_key', 'api-key');
        config()->set('discourse.api_username', 'system');
        config()->set('discourse.default_tags', ['chat-bridge']);
        config()->set('discourse.default_category_id', 12);

        Http::fake([
            'https://forum.example.com/posts.json' => Http::sequence()
                ->push(['topic_id' => 555, 'id' => 1001], 200)
                ->push(['id' => 1002], 200),
        ]);

        $user = User::factory()->create();
        $personaA = Persona::factory()->create();
        $personaB = Persona::factory()->create();

        $conversation = Conversation::factory()->create([
            'user_id' => $user->id,
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
            'starter_message' => 'Kick this off.',
            'discourse_streaming_enabled' => true,
        ]);

        $topicId = app(DiscourseStreamer::class)->startConversation($conversation);

        $this->assertSame(555, $topicId);
        $conversation->refresh();
        $this->assertSame(555, $conversation->discourse_topic_id);
        Http::assertSentCount(2);
    }

    public function test_it_posts_message_to_existing_topic(): void
    {
        config()->set('discourse.enabled', true);
        config()->set('discourse.base_url', 'https://forum.example.com');
        config()->set('discourse.api_key', 'api-key');
        config()->set('discourse.api_username', 'system');

        Http::fake([
            'https://forum.example.com/posts.json' => Http::response(['id' => 123], 200),
        ]);

        $user = User::factory()->create();
        $personaA = Persona::factory()->create();
        $personaB = Persona::factory()->create();

        $conversation = Conversation::factory()->create([
            'user_id' => $user->id,
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
            'discourse_streaming_enabled' => true,
            'discourse_topic_id' => 999,
            'max_rounds' => 2,
        ]);

        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'persona_id' => $personaA->id,
            'role' => 'assistant',
            'content' => 'Discourse response body',
        ]);

        app(DiscourseStreamer::class)->postMessage($conversation, $message, 1);

        Http::assertSent(function ($request): bool {
            $payload = $request->data();

            return ($payload['topic_id'] ?? null) === 999
                && str_contains((string) ($payload['raw'] ?? ''), 'Discourse response body');
        });
    }

    public function test_it_posts_to_chat_webhook_without_topic_credentials(): void
    {
        config()->set('discourse.enabled', true);
        config()->set('discourse.base_url', null);
        config()->set('discourse.api_key', null);
        config()->set('discourse.api_username', null);
        config()->set('discourse.chat_enabled', true);
        config()->set('discourse.chat_webhook_url', 'https://forum.example.com/chat/hooks/test-key.json');

        Http::fake([
            'https://forum.example.com/chat/hooks/test-key.json' => Http::response(['ok' => true], 200),
        ]);

        $user = User::factory()->create();
        $personaA = Persona::factory()->create();
        $personaB = Persona::factory()->create();

        $conversation = Conversation::factory()->create([
            'user_id' => $user->id,
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
            'starter_message' => 'Kick this off.',
            'discourse_streaming_enabled' => true,
            'max_rounds' => 2,
        ]);

        app(DiscourseStreamer::class)->startConversation($conversation);

        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'persona_id' => $personaA->id,
            'role' => 'assistant',
            'content' => 'Hello from webhook mode',
        ]);

        app(DiscourseStreamer::class)->postMessage($conversation, $message, 1);
        app(DiscourseStreamer::class)->conversationCompleted($conversation, 3, 1, 4.1);

        Http::assertSent(function ($request): bool {
            if ($request->url() !== 'https://forum.example.com/chat/hooks/test-key.json') {
                return false;
            }

            $payload = $request->data();

            return is_string($payload['text'] ?? null)
                && $payload['text'] !== '';
        });
    }

    public function test_it_reuses_existing_topic_when_discourse_rejects_duplicate_title(): void
    {
        config()->set('discourse.enabled', true);
        config()->set('discourse.base_url', 'https://forum.example.com');
        config()->set('discourse.api_key', 'api-key');
        config()->set('discourse.api_username', 'system');
        config()->set('discourse.default_tags', ['chat-bridge']);
        config()->set('discourse.default_category_id', 12);

        Http::fake([
            'https://forum.example.com/posts.json' => Http::sequence()
                ->push(['action' => 'create_post', 'errors' => ['Title has already been used']], 422)
                ->push(['id' => 9002], 200),
            'https://forum.example.com/search/query.json*' => Http::response([
                'topics' => [
                    ['id' => 777, 'title' => 'Chat Bridge #019cc4f8: ORACLE_PRIME vs VOID_WALKER | Demo'],
                ],
            ], 200),
            'https://forum.example.com/search.json*' => Http::response([], 200),
        ]);

        $user = User::factory()->create();
        $personaA = Persona::factory()->create([
            'name' => 'ORACLE_PRIME',
        ]);
        $personaB = Persona::factory()->create([
            'name' => 'VOID_WALKER',
        ]);

        $conversation = Conversation::factory()->create([
            'id' => '019cc4f8-418b-71e8-855d-3847a9bb4dd5',
            'user_id' => $user->id,
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
            'starter_message' => 'Demo',
            'discourse_streaming_enabled' => true,
        ]);

        $topicId = app(DiscourseStreamer::class)->startConversation($conversation);

        $this->assertSame(777, $topicId);
        $conversation->refresh();
        $this->assertSame(777, $conversation->discourse_topic_id);
    }

    public function test_it_skips_out_of_order_messages_for_existing_topic(): void
    {
        config()->set('discourse.enabled', true);
        config()->set('discourse.base_url', 'https://forum.example.com');
        config()->set('discourse.api_key', 'api-key');
        config()->set('discourse.api_username', 'system');

        Http::fake([
            'https://forum.example.com/posts.json' => Http::response(['id' => 321], 200),
        ]);

        $user = User::factory()->create();
        $personaA = Persona::factory()->create();
        $personaB = Persona::factory()->create();

        $conversation = Conversation::factory()->create([
            'user_id' => $user->id,
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
            'discourse_streaming_enabled' => true,
            'discourse_topic_id' => 999,
            'max_rounds' => 5,
        ]);

        $olderMessage = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'persona_id' => $personaB->id,
            'role' => 'assistant',
            'content' => 'Older message should be skipped',
        ]);

        $newerMessage = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'persona_id' => $personaA->id,
            'role' => 'assistant',
            'content' => 'Newer message should post first',
        ]);

        $streamer = app(DiscourseStreamer::class);
        $streamer->postMessage($conversation, $newerMessage, 3);
        $streamer->postMessage($conversation, $olderMessage, 2);

        Http::assertSentCount(1);
        Http::assertSent(function ($request): bool {
            $payload = $request->data();

            return ($payload['topic_id'] ?? null) === 999
                && str_contains((string) ($payload['raw'] ?? ''), 'Newer message should post first');
        });
    }
}
