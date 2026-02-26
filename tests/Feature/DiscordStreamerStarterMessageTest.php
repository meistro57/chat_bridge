<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Persona;
use App\Models\User;
use App\Services\Discord\DiscordStreamer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DiscordStreamerStarterMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_posts_starter_question_before_conversation_started_embed(): void
    {
        config()->set('discord.enabled', true);
        config()->set('discord.thread_auto_create', true);

        Http::fake([
            'https://discord.test/webhook*' => Http::sequence()
                ->push(['channel_id' => 'thread-123'], 200)
                ->push(['id' => 'message-2'], 200),
        ]);

        $user = User::factory()->create();
        $personaA = Persona::factory()->create();
        $personaB = Persona::factory()->create();

        $conversation = Conversation::factory()->create([
            'user_id' => $user->id,
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
            'starter_message' => 'What assumptions should we challenge first?',
            'discord_streaming_enabled' => true,
            'discord_webhook_url' => 'https://discord.test/webhook',
        ]);

        app(DiscordStreamer::class)->startConversation($conversation);

        Http::assertSentCount(2);

        $requests = Http::recorded();
        $this->assertCount(2, $requests);

        $firstRequest = $requests[0][0];
        $secondRequest = $requests[1][0];

        $firstPayload = $firstRequest->data();
        $secondPayload = $secondRequest->data();

        $this->assertSame('💬 Starter Question', $firstPayload['embeds'][0]['title'] ?? null);
        $this->assertSame(
            'What assumptions should we challenge first?',
            $firstPayload['embeds'][0]['description'] ?? null
        );
        $this->assertArrayHasKey('thread_name', $firstPayload);

        $this->assertSame('🚀 New Conversation Started', $secondPayload['embeds'][0]['title'] ?? null);
    }
}
