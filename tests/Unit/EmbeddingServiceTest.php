<?php

namespace Tests\Unit;

use App\Services\AI\EmbeddingService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EmbeddingServiceTest extends TestCase
{
    public function test_it_returns_a_dummy_vector_when_no_api_key_is_configured(): void
    {
        Config::set('services.openai.key', null);

        $service = new EmbeddingService;
        $embedding = $service->getEmbedding('hello world');

        $this->assertCount(1536, $embedding);
        $this->assertSame(array_fill(0, 1536, 0.0), $embedding);
    }

    public function test_it_falls_back_to_openrouter_when_openai_quota_is_exhausted(): void
    {
        Config::set('services.openai.key', 'test-openai-key');
        Config::set('services.openrouter.key', 'test-openrouter-key');
        Config::set('services.openrouter.embedding_model', 'openai/text-embedding-3-small');

        Http::fake([
            'https://api.openai.com/v1/embeddings' => Http::response([
                'error' => [
                    'message' => 'You exceeded your current quota',
                    'type' => 'insufficient_quota',
                    'code' => 'insufficient_quota',
                ],
            ], 429),
            'https://openrouter.ai/api/v1/embeddings' => Http::response([
                'data' => [[
                    'embedding' => [0.5, 0.25, 0.125],
                ]],
            ], 200),
        ]);

        $service = new EmbeddingService;
        $embedding = $service->getEmbedding('hello world');

        $this->assertSame([0.5, 0.25, 0.125], $embedding);

        Http::assertSentCount(2);
    }

    public function test_it_throws_openai_error_without_fallback_for_non_quota_failures(): void
    {
        Config::set('services.openai.key', 'test-openai-key');
        Config::set('services.openrouter.key', 'test-openrouter-key');

        Http::fake([
            'https://api.openai.com/v1/embeddings' => Http::response(['error' => 'Unauthorized'], 401),
            'https://openrouter.ai/api/v1/embeddings' => Http::response([
                'data' => [[
                    'embedding' => [1.0],
                ]],
            ], 200),
        ]);

        $service = new EmbeddingService;
        try {
            $service->getEmbedding('hello world');
            $this->fail('Expected exception was not thrown.');
        } catch (\Exception $exception) {
            $this->assertStringContainsString('OpenAI Embedding Error:', $exception->getMessage());
        }

        Http::assertSentCount(1);
    }

    public function test_it_throws_descriptive_error_when_openai_response_lacks_embedding_vector(): void
    {
        Config::set('services.openai.key', 'test-openai-key');

        Http::fake([
            'https://api.openai.com/v1/embeddings' => Http::response([
                'data' => [[
                    'not_embedding' => [1, 2, 3],
                ]],
            ], 200),
        ]);

        $service = new EmbeddingService;

        try {
            $service->getEmbedding('hello world');
            $this->fail('Expected exception was not thrown.');
        } catch (\Exception $exception) {
            $this->assertStringContainsString('OpenAI Embedding Error: response did not include a valid embedding vector.', $exception->getMessage());
        }
    }
}
