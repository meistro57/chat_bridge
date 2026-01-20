<?php

namespace App\Services\AI\Drivers;

use App\Services\AI\Contracts\AIDriverInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class AnthropicDriver implements AIDriverInterface
{
    public function __construct(
        protected string $apiKey,
        protected string $model = 'claude-sonnet-4-5-20250929',
        protected string $version = '2023-06-01',
        protected string $baseUrl = 'https://api.anthropic.com/v1'
    ) {}

    public function chat(Collection $messages, float $temperature = 0.7): string
    {
        $payload = $this->preparePayload($messages, $temperature);

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => $this->version,
            'content-type' => 'application/json',
        ])->post("{$this->baseUrl}/messages", $payload);

        if ($response->failed()) {
            $errorBody = $response->json();
            $errorMessage = $errorBody['error']['message'] ?? $response->body();
            throw new \Exception('Anthropic API Error: '.$errorMessage);
        }

        $content = $response->json('content.0.text');

        if ($content === null) {
            throw new \Exception('Anthropic returned unexpected response format. Response: ' . json_encode($response->json()));
        }

        return $content;
    }

    public function streamChat(Collection $messages, float $temperature = 0.7): iterable
    {
        $payload = $this->preparePayload($messages, $temperature);
        $payload['stream'] = true;

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => $this->version,
            'content-type' => 'application/json',
        ])->withOptions(['stream' => true])
            ->post("{$this->baseUrl}/messages", $payload);

        if ($response->failed()) {
            throw new \Exception('Anthropic API Connection Failed: '.$response->body());
        }

        $body = $response->toPsrResponse()->getBody();

        $event = '';
        while (! $body->eof()) {
            $line = $this->readLine($body);

            if (str_starts_with($line, 'event: ')) {
                $event = trim(substr($line, 7));

                continue;
            }

            if (str_starts_with($line, 'data: ')) {
                $data = substr($line, 6);

                if ($event === 'error') {
                    throw new \Exception('Anthropic Stream Error: '.$data);
                }

                $json = json_decode($data, true);

                if ($event === 'content_block_delta') {
                    $content = $json['delta']['text'] ?? '';
                    if ($content) {
                        yield $content;
                    }
                }

                if ($event === 'message_stop') {
                    break;
                }
            }
        }
    }

    protected function preparePayload(Collection $messages, float $temperature): array
    {
        // Combine all system messages (system prompt + guidelines) into a single string
        $systemMessages = $messages->where('role', 'system');
        $system = $systemMessages->isNotEmpty()
            ? $systemMessages->pluck('content')->implode("\n\n")
            : null;

        $filteredMessages = $messages->where('role', '!=', 'system')->values();

        return array_filter([
            'model' => $this->model,
            'messages' => $filteredMessages->map(fn ($m) => [
                'role' => $m->role === 'assistant' ? 'assistant' : 'user',
                'content' => $m->content,
            ])->all(),
            'system' => $system,
            'max_tokens' => 1024,
            'temperature' => $temperature,
        ]);
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
