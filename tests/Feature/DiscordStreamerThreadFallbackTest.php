<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Persona;
use App\Models\User;
use App\Services\Discord\DiscordStreamer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DiscordStreamerThreadFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_retries_without_thread_id_when_discord_thread_is_unknown(): void
    {
        config()->set('discord.enabled', true);

        Http::fake([
            'https://discord.test/webhook*' => Http::sequence()
                ->push(['message' => 'Unknown Channel', 'code' => 10003], 400)
                ->push(['id' => 'ok'], 200),
        ]);

        $user = User::factory()->create();
        $personaA = Persona::factory()->create();
        $personaB = Persona::factory()->create();

        $conversation = Conversation::factory()->create([
            'user_id' => $user->id,
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
            'discord_streaming_enabled' => true,
            'discord_webhook_url' => 'https://discord.test/webhook',
            'discord_thread_id' => 'stale-thread-id',
        ]);

        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'persona_id' => $personaA->id,
            'role' => 'assistant',
            'content' => 'Reply body',
        ]);

        app(DiscordStreamer::class)->postMessage($conversation, $message, 1);

        Http::assertSentCount(2);
        Http::assertSent(function ($request): bool {
            return str_contains((string) $request->url(), 'thread_id=stale-thread-id');
        });
        Http::assertSent(function ($request): bool {
            return ! str_contains((string) $request->url(), 'thread_id=');
        });

        $conversation->refresh();
        $this->assertNull($conversation->discord_thread_id);
    }

    public function test_it_does_not_persist_channel_id_as_thread_id_when_auto_create_falls_back(): void
    {
        config()->set('discord.enabled', true);
        config()->set('discord.thread_auto_create', true);

        Http::fake([
            'https://discord.test/webhook*' => Http::sequence()
                ->push(['message' => 'Webhooks can only create threads in forum channels', 'code' => 220003], 400)
                ->push(['channel_id' => 'webhook-channel-id'], 200)
                ->push(['id' => 'started-message'], 200),
        ]);

        $user = User::factory()->create();
        $personaA = Persona::factory()->create();
        $personaB = Persona::factory()->create();

        $conversation = Conversation::factory()->create([
            'user_id' => $user->id,
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
            'starter_message' => 'Start here',
            'discord_streaming_enabled' => true,
            'discord_webhook_url' => 'https://discord.test/webhook',
        ]);

        app(DiscordStreamer::class)->startConversation($conversation);

        Http::assertSentCount(3);
        Http::assertSent(function ($request): bool {
            return ! str_contains((string) $request->url(), 'thread_id=');
        });

        $conversation->refresh();
        $this->assertNull($conversation->discord_thread_id);
    }
}
