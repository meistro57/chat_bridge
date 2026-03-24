<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Conversation;
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

    public function test_persona_index_only_includes_authenticated_users_personas(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $userPersona = Persona::factory()->create([
            'user_id' => $user->id,
        ]);

        Persona::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this->actingAs($user)->get('/personas');

        $response->assertStatus(200);
        $personas = collect($response->viewData('page')['props']['personas'] ?? []);

        $this->assertCount(1, $personas);
        $this->assertSame($userPersona->id, $personas->first()['id'] ?? null);
    }

    public function test_persona_index_includes_sessions_count(): void
    {
        $user = User::factory()->create();
        $personaA = Persona::factory()->create(['user_id' => $user->id]);
        $personaB = Persona::factory()->create(['user_id' => $user->id]);

        Conversation::factory()->create([
            'user_id' => $user->id,
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
        ]);

        Conversation::factory()->create([
            'user_id' => $user->id,
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
        ]);

        $response = $this->actingAs($user)->get('/personas');

        $response->assertStatus(200);
        $personas = collect($response->viewData('page')['props']['personas'] ?? []);

        $personaAData = $personas->firstWhere('id', $personaA->id);
        $personaBData = $personas->firstWhere('id', $personaB->id);

        $this->assertNotNull($personaAData);
        $this->assertNotNull($personaBData);
        $this->assertSame(2, (int) ($personaAData['sessions_count'] ?? 0));
        $this->assertSame(2, (int) ($personaBData['sessions_count'] ?? 0));
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
        $this->assertDatabaseHas('personas', [
            'name' => 'Test Persona',
            'is_favorite' => true,
        ]);
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

    public function test_can_toggle_persona_favorite(): void
    {
        $user = User::factory()->create();
        $persona = Persona::factory()->create([
            'user_id' => $user->id,
            'is_favorite' => true,
        ]);

        $response = $this->actingAs($user)->patch(route('personas.favorite', $persona));

        $response->assertRedirect();
        $this->assertDatabaseHas('personas', [
            'id' => $persona->id,
            'is_favorite' => false,
        ]);
    }

    public function test_can_clear_all_persona_favorites(): void
    {
        $user = User::factory()->create();

        $favoritePersonaA = Persona::factory()->create([
            'user_id' => $user->id,
            'is_favorite' => true,
        ]);

        $favoritePersonaB = Persona::factory()->create([
            'user_id' => $user->id,
            'is_favorite' => true,
        ]);

        $otherUserFavoritePersona = Persona::factory()->create([
            'user_id' => User::factory()->create()->id,
            'is_favorite' => true,
        ]);

        $response = $this->actingAs($user)->patch(route('personas.favorites.clear'));

        $response->assertRedirect();
        $this->assertDatabaseHas('personas', [
            'id' => $favoritePersonaA->id,
            'is_favorite' => false,
        ]);
        $this->assertDatabaseHas('personas', [
            'id' => $favoritePersonaB->id,
            'is_favorite' => false,
        ]);
        $this->assertDatabaseHas('personas', [
            'id' => $otherUserFavoritePersona->id,
            'is_favorite' => true,
        ]);
    }

    public function test_persona_index_prioritizes_favorites(): void
    {
        $user = User::factory()->create();

        $nonFavorite = Persona::factory()->create([
            'user_id' => $user->id,
            'name' => 'Non Favorite Persona',
            'is_favorite' => false,
        ]);

        $favorite = Persona::factory()->create([
            'user_id' => $user->id,
            'name' => 'Favorite Persona',
            'is_favorite' => true,
        ]);

        $response = $this->actingAs($user)->get('/personas');

        $response->assertStatus(200);
        $personas = collect($response->viewData('page')['props']['personas'] ?? []);

        $this->assertSame($favorite->id, $personas->first()['id'] ?? null);
        $this->assertSame($nonFavorite->id, $personas->last()['id'] ?? null);
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

    public function test_generate_persona_falls_back_to_openrouter_when_openai_quota_is_exhausted(): void
    {
        config(['services.openai.key' => 'test-openai-key']);
        config(['services.openai.model' => 'gpt-4o-mini']);
        config(['services.openrouter.key' => 'test-openrouter-key']);
        config(['ai.http_retry_attempts' => 1]); // disable retries so 429 falls through immediately

        $user = User::factory()->create();

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'error' => [
                    'message' => 'You exceeded your current quota, please check your plan and billing details.',
                    'type' => 'insufficient_quota',
                    'code' => 'insufficient_quota',
                ],
            ], 429),
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'name' => 'Fallback Architect',
                            'system_prompt' => 'You are Fallback Architect. Evaluate architecture options, call out risks early, and keep guidance practical for production teams.',
                        ], JSON_THROW_ON_ERROR),
                    ],
                ]],
                'usage' => [
                    'prompt_tokens' => 40,
                    'completion_tokens' => 55,
                    'total_tokens' => 95,
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->postJson(route('personas.generate'), [
            'idea' => 'A pragmatic system design reviewer.',
            'tone' => 'Direct',
            'audience' => 'Backend engineers',
            'style' => 'Structured',
        ]);

        $response->assertOk();
        $response->assertJsonPath('name', 'Fallback Architect');
        $response->assertJsonPath('system_prompt', 'You are Fallback Architect. Evaluate architecture options, call out risks early, and keep guidance practical for production teams.');

        Http::assertSentCount(2);

        Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
            if ($request->url() !== 'https://openrouter.ai/api/v1/chat/completions') {
                return false;
            }

            return ($request->data()['model'] ?? null) === 'openai/gpt-4o-mini';
        });
    }

    public function test_generate_persona_uses_user_openrouter_key_for_fallback_when_service_key_missing(): void
    {
        config(['services.openai.key' => 'test-openai-key']);
        config(['services.openai.model' => 'gpt-4o-mini']);
        config(['services.openrouter.key' => null]);

        $user = User::factory()->create();
        ApiKey::factory()->create([
            'user_id' => $user->id,
            'provider' => 'openrouter',
            'key' => 'test-user-openrouter-key',
            'is_active' => true,
        ]);

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'error' => [
                    'message' => 'You exceeded your current quota, please check your plan and billing details.',
                    'type' => 'insufficient_quota',
                    'code' => 'insufficient_quota',
                ],
            ], 429),
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'name' => 'Context Navigator',
                            'system_prompt' => 'You are Context Navigator. Build consistent summaries from complete source context and flag assumptions explicitly.',
                        ], JSON_THROW_ON_ERROR),
                    ],
                ]],
                'usage' => [
                    'prompt_tokens' => 40,
                    'completion_tokens' => 55,
                    'total_tokens' => 95,
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->postJson(route('personas.generate'), [
            'idea' => 'A consistent summarizer that avoids losing context.',
        ]);

        $response->assertOk();
        $response->assertJsonPath('name', 'Context Navigator');

        Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
            if ($request->url() !== 'https://openrouter.ai/api/v1/chat/completions') {
                return false;
            }

            $authorization = $request->header('Authorization');

            return isset($authorization[0]) && $authorization[0] === 'Bearer test-user-openrouter-key';
        });
    }
}
