<?php

namespace Tests\Feature;

use App\Models\ModelPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProviderModelPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_openrouter_model_query_persists_pricing_for_analytics(): void
    {
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
    }
}
