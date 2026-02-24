<?php

namespace Tests\Feature;

use App\Models\ConversationTemplate;
use App\Models\Persona;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ConversationTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_template_index(): void
    {
        $user = User::factory()->create();
        $personaA = Persona::factory()->create(['user_id' => $user->id]);
        $personaB = Persona::factory()->create(['user_id' => $user->id]);

        ConversationTemplate::factory()->create([
            'user_id' => $user->id,
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
        ]);

        ConversationTemplate::factory()->publicTemplate()->create([
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
        ]);

        $response = $this->actingAs($user)->get(route('templates.index'));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Templates/Index')
            ->has('templates')
            ->has('categories')
            ->has('filters')
        );
    }

    public function test_user_can_create_template(): void
    {
        $user = User::factory()->create();
        $personaA = Persona::factory()->create(['user_id' => $user->id]);
        $personaB = Persona::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post(route('templates.store'), [
            'name' => 'Debate Starter',
            'description' => 'A quick debate template.',
            'category' => 'Debate',
            'starter_message' => 'Debate the pros and cons of remote work.',
            'max_rounds' => 8,
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
            'is_public' => false,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('conversation_templates', [
            'name' => 'Debate Starter',
            'user_id' => $user->id,
        ]);
    }

    public function test_user_can_update_own_template(): void
    {
        $user = User::factory()->create();
        $personaA = Persona::factory()->create(['user_id' => $user->id]);
        $personaB = Persona::factory()->create(['user_id' => $user->id]);

        $template = ConversationTemplate::factory()->create([
            'user_id' => $user->id,
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
        ]);

        $response = $this->actingAs($user)->patch(route('templates.update', $template), [
            'name' => 'Updated Template',
            'description' => 'Updated description',
            'category' => 'Interview',
            'starter_message' => 'Interview about leadership.',
            'max_rounds' => 12,
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
            'is_public' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('conversation_templates', [
            'id' => $template->id,
            'name' => 'Updated Template',
            'is_public' => 1,
        ]);
    }

    public function test_user_cannot_edit_template_they_do_not_own(): void
    {
        $owner = User::factory()->create();
        $user = User::factory()->create();
        $personaA = Persona::factory()->create(['user_id' => $owner->id]);
        $personaB = Persona::factory()->create(['user_id' => $owner->id]);

        $template = ConversationTemplate::factory()->create([
            'user_id' => $owner->id,
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
        ]);

        $response = $this->actingAs($user)->get(route('templates.edit', $template));

        $response->assertForbidden();
    }

    public function test_user_can_use_public_template(): void
    {
        $user = User::factory()->create();
        $personaA = Persona::factory()->create();
        $personaB = Persona::factory()->create();

        $template = ConversationTemplate::factory()->publicTemplate()->create([
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
            'is_public' => true,
        ]);

        $response = $this->actingAs($user)->post(route('templates.use', $template));

        $response->assertRedirect(route('chat.create', ['template' => $template->id]));
    }

    public function test_user_can_clone_template(): void
    {
        $user = User::factory()->create();
        $personaA = Persona::factory()->create();
        $personaB = Persona::factory()->create();

        $template = ConversationTemplate::factory()->publicTemplate()->create([
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
            'is_public' => true,
        ]);

        $response = $this->actingAs($user)->post(route('templates.clone', $template));

        $response->assertRedirect();
        $this->assertDatabaseHas('conversation_templates', [
            'name' => $template->name.' (Copy)',
            'user_id' => $user->id,
            'is_public' => 0,
        ]);
    }

    public function test_create_form_includes_personas_from_other_users(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $ownPersona = Persona::factory()->create(['user_id' => $user->id]);
        $otherPersona = Persona::factory()->create(['user_id' => $otherUser->id]);
        $systemPersona = Persona::factory()->create(['user_id' => null]);

        $response = $this->actingAs($user)->get(route('templates.create'));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Templates/Create')
            ->where('personas', fn ($personas) => collect($personas)->pluck('id')->contains($ownPersona->id)
                && collect($personas)->pluck('id')->contains($otherPersona->id)
                && collect($personas)->pluck('id')->contains($systemPersona->id)
            )
        );
    }

    public function test_user_can_save_template_from_chat(): void
    {
        $user = User::factory()->create();
        $personaA = Persona::factory()->create(['user_id' => $user->id]);
        $personaB = Persona::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->from(route('chat.create'))
            ->post(route('templates.storeFromChat'), [
                'name' => 'Chat Snapshot',
                'description' => 'Saved from chat/create.',
                'category' => 'Snapshot',
                'starter_message' => 'Discuss the future of AI.',
                'max_rounds' => 6,
                'persona_a_id' => $personaA->id,
                'persona_b_id' => $personaB->id,
                'is_public' => false,
            ]);

        $response->assertRedirect(route('chat.create'));
        $this->assertDatabaseHas('conversation_templates', [
            'name' => 'Chat Snapshot',
            'user_id' => $user->id,
        ]);
    }

    public function test_user_can_delete_own_template(): void
    {
        $user = User::factory()->create();
        $personaA = Persona::factory()->create(['user_id' => $user->id]);
        $personaB = Persona::factory()->create(['user_id' => $user->id]);

        $template = ConversationTemplate::factory()->create([
            'user_id' => $user->id,
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
        ]);

        $response = $this->actingAs($user)->delete(route('templates.destroy', $template));

        $response->assertRedirect(route('templates.index'));
        $this->assertDatabaseMissing('conversation_templates', [
            'id' => $template->id,
        ]);
    }
}
