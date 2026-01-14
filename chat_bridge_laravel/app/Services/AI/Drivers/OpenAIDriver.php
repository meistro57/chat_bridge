<?php

namespace App\Services\AI\Drivers;

use App\Services\AI\Contracts\AIDriverInterface;
use App\Services\AI\Data\MessageData;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIDriver implements AIDriverInterface
{
    public function __construct(
        protected string $apiKey,
        protected string $model = 'gpt-4o-mini',
        protected string $baseUrl = 'https://api.openai.com/v1'
    ) {}

    public function chat(Collection $messages, float $temperature = 0.7): string
    {
        $response = Http::withToken($this->apiKey)
            ->post("{$this->baseUrl}/chat/completions", [
                'model' => $this->model,
                'messages' => $messages->map->toArray()->all(),
                'temperature' => $temperature,
            ]);

        if ($response->failed()) {
            throw new \Exception("OpenAI API Error: " . $response->body());
        }

        return $response->json('choices.0.message.content');
    }

    public function streamChat(Collection $messages, float $temperature = 0.7): iterable
    {
        $response = Http::withToken($this->apiKey)
            ->withOptions(['stream' => true])
            ->post("{$this->baseUrl}/chat/completions", [
                'model' => $this->model,
                'messages' => $messages->map->toArray()->all(),
                'temperature' => $temperature,
                'stream' => true,
            ]);

        $body = $response->toPsrResponse()->getBody();

        while (!$body->eof()) {
            $line = $this->readLine($body);
            
            if (str_starts_with($line, 'data: ')) {
                $data = substr($line, 6);
                
                if (trim($data) === '[DONE]') {
                    break;
                }

                $json = json_decode($data, true);
                $content = $json['choices'][0]['delta']['content'] ?? '';
                
                if ($content) {
                    yield $content;
                }
            }
        }
    }

    protected function readLine($stream): string
    {
        $buffer = '';
        while (!$stream->eof()) {
            $char = $stream->read(1);
            if ($char === "\n") {
                break;
            }
            $buffer .= $char;
        }
        return trim($buffer);
    }
}
