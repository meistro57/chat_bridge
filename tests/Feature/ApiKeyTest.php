<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\User;
use App\Services\AI\Contracts\AIDriverInterface;
use App\Services\AI\Data\AIResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ApiKeyTest extends TestCase
{
    use RefreshDatabase;

    public function test_ollama_key_can_be_saved_without_a_key_value(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->post('/api-keys', [
                'provider' => 'ollama',
                'label' => 'Local Ollama',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect('/api-keys');

        $apiKey = ApiKey::query()->where('provider', 'ollama')->first();

        $this->assertNotNull($apiKey);
        $this->assertSame('', $apiKey->key);
    }

    public function test_non_local_providers_still_require_a_key(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/api-keys/create')
            ->post('/api-keys', [
                'provider' => 'openai',
                'label' => 'Missing Key',
            ]);

        $response->assertSessionHasErrors('key');
        $response->assertRedirect('/api-keys/create');
    }

    public function test_gemini_key_is_marked_valid_when_api_call_succeeds(): void
    {
        $user = User::factory()->create();
        $apiKey = ApiKey::factory()->create(['user_id' => $user->id, 'provider' => 'gemini']);

        $mockDriver = Mockery::mock(AIDriverInterface::class);
        $mockDriver->shouldReceive('chat')->andReturn(new AIResponse(content: 'OK'));

        $mockManager = Mockery::mock(\App\Services\AI\AIManager::class)->makePartial();
        $mockManager->shouldReceive('driverForApiKey')->withArgs(function (ApiKey $resolvedApiKey) use ($apiKey) {
            return $resolvedApiKey->is($apiKey);
        })->andReturn($mockDriver);
        $this->app->instance('ai', $mockManager);

        $response = $this->actingAs($user)->post("/api-keys/{$apiKey->id}/test");

        $response->assertStatus(200);
        $response->assertJson(['success' => true, 'is_validated' => true]);
        $this->assertDatabaseHas('api_keys', ['id' => $apiKey->id, 'is_validated' => true]);
    }

    public function test_gemini_key_is_marked_invalid_when_api_call_fails(): void
    {
        $user = User::factory()->create();
        $apiKey = ApiKey::factory()->create(['user_id' => $user->id, 'provider' => 'gemini']);

        $mockDriver = Mockery::mock(AIDriverInterface::class);
        $mockDriver->shouldReceive('chat')->andThrow(new \Exception('API key not valid. Please pass a valid API key.'));

        $mockManager = Mockery::mock(\App\Services\AI\AIManager::class)->makePartial();
        $mockManager->shouldReceive('driverForApiKey')->withArgs(function (ApiKey $resolvedApiKey) use ($apiKey) {
            return $resolvedApiKey->is($apiKey);
        })->andReturn($mockDriver);
        $this->app->instance('ai', $mockManager);

        $response = $this->actingAs($user)->post("/api-keys/{$apiKey->id}/test");

        $response->assertStatus(422);
        $response->assertJson(['success' => false, 'is_validated' => false]);
        $this->assertDatabaseHas('api_keys', [
            'id' => $apiKey->id,
            'is_validated' => false,
            'validation_error' => 'API key not valid. Please pass a valid API key.',
        ]);
    }

    public function test_key_test_endpoint_forbidden_for_other_users(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $apiKey = ApiKey::factory()->create(['user_id' => $owner->id, 'provider' => 'gemini']);

        $response = $this->actingAs($other)->post("/api-keys/{$apiKey->id}/test");

        $response->assertStatus(403);
    }

    public function test_key_test_endpoint_marks_provider_key_invalid_when_key_is_blank(): void
    {
        $user = User::factory()->create();
        $apiKey = ApiKey::factory()->create([
            'user_id' => $user->id,
            'provider' => 'gemini',
            'key' => '',
        ]);

        $mockManager = Mockery::mock(\App\Services\AI\AIManager::class)->makePartial();
        $mockManager->shouldNotReceive('driverForApiKey');
        $this->app->instance('ai', $mockManager);

        $response = $this->actingAs($user)->post("/api-keys/{$apiKey->id}/test");

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'is_validated' => false,
            'error' => 'No API key is stored for this provider.',
        ]);
        $this->assertDatabaseHas('api_keys', [
            'id' => $apiKey->id,
            'is_validated' => false,
            'validation_error' => 'No API key is stored for this provider.',
        ]);
    }
}
