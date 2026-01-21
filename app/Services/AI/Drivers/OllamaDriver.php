<?php

namespace App\Services\AI\Drivers;

use App\Services\AI\Contracts\AIDriverInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class OllamaDriver implements AIDriverInterface
{
    public function __construct(
        protected string $model = 'llama3.1',
        protected string $baseUrl = 'http://localhost:11434'
    ) {}

    public function chat(Collection $messages, float $temperature = 0.7): string
    {
        $response = Http::post("{$this->baseUrl}/api/chat", [
            'model' => $this->model,
            'messages' => $messages->map->toArray()->all(),
            'options' => [
                'temperature' => $temperature,
            ],
            'stream' => false,
        ]);

        if ($response->failed()) {
            throw new \Exception('Ollama API Error: '.$response->body());
        }

        $content = $response->json('message.content');

        if ($content === null) {
            $responseData = $response->json();
            throw new \Exception('Ollama API returned an unexpected response structure. Response: ' . json_encode($responseData));
        }

        return $content;
    }

    public function streamChat(Collection $messages, float $temperature = 0.7): iterable
    {
        $response = Http::withOptions(['stream' => true])
            ->timeout(300)
            ->post("{$this->baseUrl}/api/chat", [
                'model' => $this->model,
                'messages' => $messages->map->toArray()->all(),
                'options' => [
                    'temperature' => $temperature,
                ],
                'stream' => true,
            ]);

        $body = $response->toPsrResponse()->getBody();

        while (! $body->eof()) {
            $line = $this->readLine($body);
            if (! $line) {
                continue;
            }

            $json = json_decode($line, true);
            $content = $json['message']['content'] ?? '';

            if ($content) {
                yield $content;
            }

            if (($json['done'] ?? false) === true) {
                break;
            }
        }
    }

    protected function readLine($stream): string
    {
        $buffer = '';
        while (! $stream->eof()) {
            $char = $stream->read(1);
            if ($char === "\n") {
                break;
            }
            $buffer .= $char;
        }

        return trim($buffer);
    }
}
