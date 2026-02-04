<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Persona;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ProfileEnhancedTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_displays_usage_stats(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/profile');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Profile/Edit')
            ->has('stats')
            ->where('stats.total_conversations', 0)
            ->where('stats.total_personas', 0)
            ->where('stats.total_api_keys', 0)
            ->where('stats.total_messages', 0)
            ->where('stats.total_tokens', 0)
            ->where('stats.completed_conversations', 0)
        );
    }

    public function test_profile_page_displays_correct_stats_with_data(): void
    {
        $user = User::factory()->create();
        $personaA = Persona::factory()->create(['user_id' => $user->id]);
        $personaB = Persona::factory()->create(['user_id' => $user->id]);

        $conversation = Conversation::factory()->completed()->create([
            'user_id' => $user->id,
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'persona_id' => $personaA->id,
            'role' => 'assistant',
            'content' => 'Test message',
            'tokens_used' => 50,
        ]);

        $response = $this->actingAs($user)->get('/profile');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('stats.total_conversations', 1)
            ->where('stats.total_personas', 2)
            ->where('stats.total_messages', 1)
            ->where('stats.total_tokens', 50)
            ->where('stats.completed_conversations', 1)
        );
    }

    public function test_profile_page_displays_notification_preferences(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/profile');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('notificationPreferences')
            ->where('notificationPreferences.conversation_completed', true)
            ->where('notificationPreferences.conversation_failed', true)
        );
    }

    public function test_user_can_update_bio(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patch('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'bio' => 'This is my bio text.',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect('/profile');

        $user->refresh();
        $this->assertSame('This is my bio text.', $user->bio);
    }

    public function test_bio_is_optional(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patch('/profile', [
            'name' => $user->name,
            'email' => $user->email,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect('/profile');
    }

    public function test_bio_max_length_is_validated(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patch('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'bio' => str_repeat('a', 501),
        ]);

        $response->assertSessionHasErrors('bio');
    }

    public function test_user_can_update_notification_preferences(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patch('/profile/notifications', [
            'conversation_completed' => false,
            'conversation_failed' => true,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect('/profile');

        $user->refresh();
        $this->assertFalse($user->getNotificationPrefs()['conversation_completed']);
        $this->assertTrue($user->getNotificationPrefs()['conversation_failed']);
    }

    public function test_notification_preferences_require_boolean_values(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patch('/profile/notifications', [
            'conversation_completed' => 'not-a-boolean',
            'conversation_failed' => true,
        ]);

        $response->assertSessionHasErrors('conversation_completed');
    }

    public function test_user_wants_notification_defaults_to_true(): void
    {
        $user = User::factory()->create(['notification_preferences' => null]);

        $this->assertTrue($user->wantsNotification('conversation_completed'));
        $this->assertTrue($user->wantsNotification('conversation_failed'));
    }

    public function test_user_wants_notification_respects_preferences(): void
    {
        $user = User::factory()->create([
            'notification_preferences' => [
                'conversation_completed' => false,
                'conversation_failed' => true,
            ],
        ]);

        $this->assertFalse($user->wantsNotification('conversation_completed'));
        $this->assertTrue($user->wantsNotification('conversation_failed'));
    }
}
