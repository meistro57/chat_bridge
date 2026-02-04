<?php

namespace App\Services\AI\Drivers;

use App\Services\AI\Contracts\AIDriverInterface;
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
        $response = $this->sendChatRequest($messages, $temperature, false);
        if ($response->failed()) {
            if ($this->isTemperatureUnsupported($response->body())) {
                Log::warning('OpenAI temperature unsupported, retrying without temperature.', [
                    'model' => $this->model,
                ]);
                $response = $this->sendChatRequest($messages, null, false);
            }
        }

        if ($response->failed()) {
            throw new \Exception('OpenAI API Error: '.$response->body());
        }

        $content = $response->json('choices.0.message.content');

        if ($content === null) {
            $responseData = $response->json();
            throw new \Exception('OpenAI API returned an unexpected response structure. Response: '.json_encode($responseData));
        }

        return $content;
    }

    public function streamChat(Collection $messages, float $temperature = 0.7): iterable
    {
        $response = $this->sendChatRequest($messages, $temperature, true);
        if ($response->failed()) {
            if ($this->isTemperatureUnsupported($response->body())) {
                Log::warning('OpenAI temperature unsupported, retrying stream without temperature.', [
                    'model' => $this->model,
                ]);
                $response = $this->sendChatRequest($messages, null, true);
            }
        }

        if ($response->failed()) {
            throw new \Exception('OpenAI API Error: '.$response->body());
        }

        $body = $response->toPsrResponse()->getBody();

        while (! $body->eof()) {
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
        while (! $stream->eof()) {
            $char = $stream->read(1);
            if ($char === "\n") {
                break;
            }
            $buffer .= $char;
        }

        return trim($buffer);
    }

    private function sendChatRequest(Collection $messages, ?float $temperature, bool $stream)
    {
        $payload = [
            'model' => $this->model,
            'messages' => $messages->map->toArray()->all(),
        ];

        if ($temperature !== null) {
            $payload['temperature'] = $temperature;
        }

        if ($stream) {
            $payload['stream'] = true;
        }

        $client = Http::withToken($this->apiKey);

        if ($stream) {
            $client = $client->withOptions(['stream' => true]);
        }

        return $client->post("{$this->baseUrl}/chat/completions", $payload);
    }

    private function isTemperatureUnsupported(string $body): bool
    {
        if ($body === '') {
            return false;
        }

        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            $error = $decoded['error'] ?? [];
            $param = $error['param'] ?? null;
            if ($param === 'temperature') {
                return true;
            }

            $message = $error['message'] ?? '';
            if (is_string($message) && str_contains(strtolower($message), 'temperature')) {
                return true;
            }
        }

        return false;
    }
}
