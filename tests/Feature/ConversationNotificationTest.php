<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Persona;
use App\Models\User;
use App\Notifications\ConversationCompletedNotification;
use App\Notifications\ConversationFailedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ConversationNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_notification_is_sent_when_user_opts_in(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'notification_preferences' => [
                'conversation_completed' => true,
                'conversation_failed' => true,
            ],
        ]);
        $personaA = Persona::factory()->create(['user_id' => $user->id]);
        $personaB = Persona::factory()->create(['user_id' => $user->id]);

        $conversation = Conversation::factory()->completed()->create([
            'user_id' => $user->id,
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
        ]);

        $user->notify(new ConversationCompletedNotification($conversation, 10, 5));

        Notification::assertSentTo($user, ConversationCompletedNotification::class);
    }

    public function test_completed_notification_contains_correct_data(): void
    {
        $user = User::factory()->create();
        $personaA = Persona::factory()->create(['user_id' => $user->id, 'name' => 'Albert Einstein']);
        $personaB = Persona::factory()->create(['user_id' => $user->id, 'name' => 'Marie Curie']);

        $conversation = Conversation::factory()->completed()->create([
            'user_id' => $user->id,
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
        ]);

        $notification = new ConversationCompletedNotification($conversation, 20, 10);

        $mailMessage = $notification->toMail($user);

        $this->assertStringContainsString('Albert Einstein', implode(' ', array_column($mailMessage->introLines, 0) ?: $mailMessage->introLines));
        $this->assertStringContainsString('Marie Curie', implode(' ', array_column($mailMessage->introLines, 0) ?: $mailMessage->introLines));
        $this->assertSame('Conversation Completed - Chat Bridge', $mailMessage->subject);

        $arrayData = $notification->toArray($user);
        $this->assertSame('completed', $arrayData['status']);
        $this->assertSame(20, $arrayData['total_messages']);
        $this->assertSame(10, $arrayData['total_rounds']);
    }

    public function test_failed_notification_contains_error_message(): void
    {
        $user = User::factory()->create();
        $personaA = Persona::factory()->create(['user_id' => $user->id]);
        $personaB = Persona::factory()->create(['user_id' => $user->id]);

        $conversation = Conversation::factory()->create([
            'user_id' => $user->id,
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
            'status' => 'failed',
        ]);

        $notification = new ConversationFailedNotification($conversation, 'API rate limit exceeded');

        $mailMessage = $notification->toMail($user);

        $this->assertSame('Conversation Failed - Chat Bridge', $mailMessage->subject);
        $this->assertStringContainsString('API rate limit exceeded', implode(' ', $mailMessage->introLines));

        $arrayData = $notification->toArray($user);
        $this->assertSame('failed', $arrayData['status']);
        $this->assertSame('API rate limit exceeded', $arrayData['error']);
    }

    public function test_notification_not_sent_when_user_opts_out(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'notification_preferences' => [
                'conversation_completed' => false,
                'conversation_failed' => false,
            ],
        ]);

        $this->assertFalse($user->wantsNotification('conversation_completed'));
        $this->assertFalse($user->wantsNotification('conversation_failed'));

        Notification::assertNothingSent();
    }

    public function test_notification_uses_mail_channel(): void
    {
        $user = User::factory()->create();
        $personaA = Persona::factory()->create(['user_id' => $user->id]);
        $personaB = Persona::factory()->create(['user_id' => $user->id]);

        $conversation = Conversation::factory()->completed()->create([
            'user_id' => $user->id,
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
        ]);

        $completedNotification = new ConversationCompletedNotification($conversation, 10, 5);
        $this->assertSame(['mail'], $completedNotification->via($user));

        $failedNotification = new ConversationFailedNotification($conversation, 'Error');
        $this->assertSame(['mail'], $failedNotification->via($user));
    }
}
