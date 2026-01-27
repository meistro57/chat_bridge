<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
