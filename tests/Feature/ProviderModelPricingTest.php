<?php

namespace Tests\Feature;

use App\Models\ModelPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProviderModelPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_gemini_models_are_fetched_from_api_when_key_is_available(): void
    {
        config(['services.gemini.key' => 'test-gemini-key']);

        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models?key=test-gemini-key' => Http::response([
                'models' => [
                    [
                        'name' => 'models/gemini-2.0-flash',
                        'displayName' => 'Gemini 2.0 Flash',
                        'supportedGenerationMethods' => ['generateContent', 'countTokens'],
                    ],
                    [
                        'name' => 'models/gemini-2.0-flash-lite',
                        'displayName' => 'Gemini 2.0 Flash Lite',
                        'supportedGenerationMethods' => ['generateContent', 'countTokens'],
                    ],
                    [
                        'name' => 'models/text-embedding-004',
                        'displayName' => 'Text Embedding 004',
                        'supportedGenerationMethods' => ['embedContent'],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->getJson('/api/providers/models?provider=gemini');

        $response->assertOk();
        $ids = collect($response->json('models'))->pluck('id');
        $this->assertTrue($ids->contains('gemini-2.0-flash'));
        $this->assertTrue($ids->contains('gemini-2.0-flash-lite'));
        $this->assertFalse($ids->contains('text-embedding-004'));
        $this->assertSame('$0.10/$0.40', $response->json('models.0.cost'));
    }

    public function test_gemini_models_fall_back_to_defaults_when_no_key(): void
    {
        config(['services.gemini.key' => null]);

        $response = $this->getJson('/api/providers/models?provider=gemini');

        $response->assertOk();
        $ids = collect($response->json('models'))->pluck('id');
        $this->assertTrue($ids->contains('gemini-2.0-flash'));
        $this->assertFalse($ids->contains('gemini-1.5-flash'));
    }

    public function test_openrouter_model_query_persists_pricing_for_analytics(): void
    {
        Cache::forget('analytics:pricing:version');

        Http::fake([
            'https://openrouter.ai/api/v1/models' => Http::response([
                'data' => [
                    [
                        'id' => 'openai/gpt-4o-mini',
                        'name' => 'GPT-4o Mini',
                        'context_length' => 128000,
                        'pricing' => [
                            'prompt' => '0.00000015',
                            'completion' => '0.00000060',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->getJson('/api/providers/models?provider=openrouter');

        $response->assertOk();
        $response->assertJsonPath('models.0.id', 'openai/gpt-4o-mini');
        $response->assertJsonPath('models.0.cost', '$0.15/$0.60');

        $this->assertDatabaseHas('model_prices', [
            'provider' => 'openrouter',
            'model' => 'openai/gpt-4o-mini',
        ]);

        $storedPrice = ModelPrice::query()
            ->where('provider', 'openrouter')
            ->where('model', 'openai/gpt-4o-mini')
            ->first();

        $this->assertNotNull($storedPrice);
        $this->assertSame(0.15, $storedPrice->prompt_per_million);
        $this->assertSame(0.6, $storedPrice->completion_per_million);
        $this->assertSame(2, Cache::get('analytics:pricing:version'));
    }
}
