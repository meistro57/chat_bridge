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
}
