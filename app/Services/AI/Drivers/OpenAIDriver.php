<?php

namespace App\Services\AI\Drivers;

use App\Services\AI\Contracts\AIDriverInterface;
use App\Services\AI\Data\AIResponse;
use App\Services\AI\Tools\ToolDefinition;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

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
        $response = $this->sendChatRequest($messages, false);

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

        $response = $this->sendChatRequest($messages, true);

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
        $response = $this->sendToolChatRequest($messages, $tools);

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

    private function sendChatRequest(Collection $messages, bool $stream)
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

        if ($stream) {
            $payload['stream'] = true;
        }

        $client = $this->buildRequest();

        if ($stream) {
            $client = $client->withOptions(['stream' => true]);
        }

        return $client->post("{$this->baseUrl}/chat/completions", $payload);
    }

    private function sendToolChatRequest(Collection $messages, Collection $tools)
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

        return $this->buildRequest()->post("{$this->baseUrl}/chat/completions", $payload);
    }

    private function buildRequest(): PendingRequest
    {
        $timeoutSeconds = max(1, (int) config('ai.http_timeout_seconds', 90));
        $connectTimeoutSeconds = max(1, (int) config('ai.http_connect_timeout_seconds', 15));
        $retryAttempts = max(1, (int) config('ai.http_retry_attempts', 2));
        $retryDelayMs = max(0, (int) config('ai.http_retry_delay_ms', 500));

        $request = Http::withToken($this->apiKey)
            ->timeout($timeoutSeconds)
            ->connectTimeout($connectTimeoutSeconds);

        if ($retryAttempts > 1) {
            $request = $request->retry(
                $retryAttempts,
                $retryDelayMs,
                fn (\Exception $exception): bool => $exception instanceof ConnectionException
            );
        }

        return $request;
    }
}
