<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;

class EmbeddingService
{
    protected string $apiKey;
    protected string $baseUrl = 'https://api.openai.com/v1';

    public function __construct()
    {
        $this->apiKey = config('services.openai.key');
    }

    /**
     * Generate embedding using text-embedding-3-small
     */
    public function getEmbedding(string $text): array
    {
        if (empty($this->apiKey)) {
            throw new \Exception("OpenAI API Key is not configured.");
        }

        $response = Http::withToken($this->apiKey)
            ->post("{$this->baseUrl}/embeddings", [
                'input' => $text,
                'model' => 'text-embedding-3-small',
            ]);

        if ($response->failed()) {
            throw new \Exception("OpenAI Embedding Error: " . $response->body());
        }

        return $response->json('data.0.embedding');
    }
}
