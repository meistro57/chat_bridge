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
}
