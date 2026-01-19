<?php

namespace App\Services\AI\Drivers;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class OpenRouterDriver extends OpenAIDriver
{
    public function __construct(
        protected string $apiKey,
        protected string $model = 'openai/gpt-4o-mini',
        protected ?string $appName = 'Chat Bridge',
        protected ?string $referer = 'https://github.com/meistro57/chat_bridge',
        protected string $baseUrl = 'https://openrouter.ai/api/v1'
    ) {}

    protected function getHeaders(): array
    {
        return array_filter([
            'Authorization' => "Bearer {$this->apiKey}",
            'HTTP-Referer' => $this->referer,
            'X-Title' => $this->appName,
            'Content-Type' => 'application/json',
        ]);
    }

    public function chat(Collection $messages, float $temperature = 0.7): string
    {
        $response = Http::withHeaders($this->getHeaders())
            ->post("{$this->baseUrl}/chat/completions", [
                'model' => $this->model,
                'messages' => $messages->map->toArray()->all(),
                'temperature' => $temperature,
            ]);

        if ($response->failed()) {
            throw new \Exception('OpenRouter API Error: '.$response->body());
        }

        return $response->json('choices.0.message.content');
    }

    public function streamChat(Collection $messages, float $temperature = 0.7): iterable
    {
        $response = Http::withHeaders($this->getHeaders())
            ->withOptions(['stream' => true])
            ->post("{$this->baseUrl}/chat/completions", [
                'model' => $this->model,
                'messages' => $messages->map->toArray()->all(),
                'temperature' => $temperature,
                'stream' => true,
            ]);

        $body = $response->toPsrResponse()->getBody();

        while (! $body->eof()) {
            $line = $this->readLine($body);

            if (str_starts_with($line, 'data: ')) {
                $data = substr($line, 6);

                if (trim($data) === '[DONE]') {
                    break;
                }

                $json = json_decode($data, true);
                if (isset($json['error'])) {
                    throw new \Exception('OpenRouter Stream Error: '.json_encode($json['error']));
                }

                $content = $json['choices'][0]['delta']['content'] ?? '';

                if ($content) {
                    yield $content;
                }
            }
        }
    }
}
