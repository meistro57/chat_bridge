<?php

namespace Tests\Feature;

use App\Models\Persona;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PersonaTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_personas(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get('/personas');
        $response->assertStatus(200);
    }

    public function test_can_create_persona(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->post('/personas', [
            'name' => 'Test Persona',
            'system_prompt' => 'Be helpful',
            'temperature' => 0.7,
        ]);

        $response->assertRedirect('/personas');
        $this->assertDatabaseHas('personas', ['name' => 'Test Persona']);
    }

    public function test_can_update_persona(): void
    {
        $user = User::factory()->create();
        $persona = Persona::factory()->create([
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->put("/personas/{$persona->id}", [
            'name' => 'Updated Persona',
            'system_prompt' => 'Be precise and concise.',
            'temperature' => 1.1,
            'notes' => 'Internal note',
        ]);

        $response->assertRedirect('/personas');
        $this->assertDatabaseHas('personas', [
            'id' => $persona->id,
            'name' => 'Updated Persona',
            'notes' => 'Internal note',
        ]);
    }

    public function test_can_generate_persona_with_ai_creator_endpoint(): void
    {
        config(['services.openai.key' => 'test-service-key']);
        config(['services.openai.model' => 'gpt-4o-mini']);

        $user = User::factory()->create();

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'name' => 'Precision Analyst',
                            'system_prompt' => 'You are Precision Analyst. Evaluate claims using evidence, quantify uncertainty, and present concise tradeoffs.',
                        ], JSON_THROW_ON_ERROR),
                    ],
                ]],
                'usage' => [
                    'prompt_tokens' => 32,
                    'completion_tokens' => 64,
                    'total_tokens' => 96,
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->postJson(route('personas.generate'), [
            'idea' => 'A rigorous analyst who challenges weak assumptions.',
            'tone' => 'Direct',
            'audience' => 'Engineering teams',
            'style' => 'Structured',
            'constraints' => 'Avoid fluff',
        ]);

        $response->assertOk();
        $response->assertJsonPath('name', 'Precision Analyst');
        $response->assertJsonPath('system_prompt', 'You are Precision Analyst. Evaluate claims using evidence, quantify uncertainty, and present concise tradeoffs.');

        Http::assertSentCount(1);
    }

    public function test_generate_persona_returns_validation_error_without_openai_service_key(): void
    {
        config(['services.openai.key' => null]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('personas.generate'), [
            'idea' => 'A calm mentor for beginner developers.',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'OpenAI service API key is not configured.');
    }
}
