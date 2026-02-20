<?php

namespace App\Services\AI\Drivers;

use App\Services\AI\Contracts\AIDriverInterface;
use App\Services\AI\Data\AIResponse;
use App\Services\AI\Tools\ToolDefinition;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIDriver implements AIDriverInterface
{
    protected ?int $lastTokenUsage = null;

    public function __construct(
        protected string $apiKey,
        protected string $model = 'gpt-4o-mini',
        protected string $baseUrl = 'https://api.openai.com/v1'
    ) {}

    public function chat(Collection $messages, float $temperature = 0.7): AIResponse
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

        $data = $response->json();
        $content = $data['choices'][0]['message']['content'] ?? null;

        if ($content === null) {
            throw new \Exception('OpenAI API returned an unexpected response structure. Response: '.json_encode($data));
        }

        $usage = $data['usage'] ?? [];
        $this->lastTokenUsage = $usage['total_tokens'] ?? null;

        return new AIResponse(
            content: $content,
            promptTokens: $usage['prompt_tokens'] ?? null,
            completionTokens: $usage['completion_tokens'] ?? null,
            totalTokens: $usage['total_tokens'] ?? null
        );
    }

    public function streamChat(Collection $messages, float $temperature = 0.7): iterable
    {
        $this->lastTokenUsage = null;

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

                // Capture token usage if present
                if (isset($json['usage']['total_tokens'])) {
                    $this->lastTokenUsage = $json['usage']['total_tokens'];
                }

                $content = $json['choices'][0]['delta']['content'] ?? '';

                if ($content) {
                    yield $content;
                }
            }
        }
    }

    public function getLastTokenUsage(): ?int
    {
        return $this->lastTokenUsage;
    }

    public function chatWithTools(Collection $messages, Collection $tools, float $temperature = 0.7): array
    {
        $payload = [
            'model' => $this->model,
            'messages' => $messages->map(function ($m) {
                $msgArray = $m->toArray();
                if ($m->name && $m->role === 'assistant') {
                    $msgArray['content'] = "[{$m->name}]: {$msgArray['content']}";
                    unset($msgArray['name']);
                }

                return $msgArray;
            })->all(),
            'tools' => $tools->map(fn (ToolDefinition $tool) => $tool->toOpenAISchema())->all(),
            'tool_choice' => 'auto',
        ];

        if ($temperature !== null) {
            $payload['temperature'] = $temperature;
        }

        $response = Http::withToken($this->apiKey)->post("{$this->baseUrl}/chat/completions", $payload);

        if ($response->failed()) {
            throw new \Exception('OpenAI API Error: '.$response->body());
        }

        $data = $response->json();
        $choice = $data['choices'][0] ?? [];
        $message = $choice['message'] ?? [];

        $usage = $data['usage'] ?? [];
        $this->lastTokenUsage = $usage['total_tokens'] ?? null;

        // Check for tool calls
        $toolCalls = [];
        if (isset($message['tool_calls']) && is_array($message['tool_calls'])) {
            foreach ($message['tool_calls'] as $toolCall) {
                $toolCalls[] = [
                    'id' => $toolCall['id'] ?? '',
                    'name' => $toolCall['function']['name'] ?? '',
                    'arguments' => json_decode($toolCall['function']['arguments'] ?? '{}', true),
                ];
            }
        }

        // If there are tool calls, return them without a text response
        if (! empty($toolCalls)) {
            return [
                'response' => null,
                'tool_calls' => $toolCalls,
            ];
        }

        // Otherwise return the text response
        $content = $message['content'] ?? null;
        if ($content === null) {
            throw new \Exception('OpenAI API returned unexpected response structure: '.json_encode($data));
        }

        return [
            'response' => new AIResponse(
                content: $content,
                promptTokens: $usage['prompt_tokens'] ?? null,
                completionTokens: $usage['completion_tokens'] ?? null,
                totalTokens: $usage['total_tokens'] ?? null
            ),
            'tool_calls' => [],
        ];
    }

    public function supportsTools(): bool
    {
        return true;
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
            'messages' => $messages->map(function ($m) {
                $msgArray = $m->toArray();
                // Prepend speaker name to assistant messages for clarity
                if ($m->name && $m->role === 'assistant') {
                    $msgArray['content'] = "[{$m->name}]: {$msgArray['content']}";
                    unset($msgArray['name']); // Remove name field, use content prefix instead
                }
                return $msgArray;
            })->all(),
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
