<?php

namespace Tests\Unit;

use App\Services\AI\EmbeddingService;
use Illuminate\Support\Facades\Config;
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
}
