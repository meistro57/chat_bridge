<?php

namespace App\Services\AI\Drivers;

use App\Services\AI\Contracts\AIDriverInterface;
use App\Services\AI\Data\AIResponse;
use App\Services\AI\Tools\ToolDefinition;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
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
        $response = $this->sendChatRequest($messages, false, $temperature);

        if ($response->failed()) {
            throw new \Exception('OpenAI API Error: '.$response->body(), $response->status());
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

        $response = $this->sendChatRequest($messages, true, $temperature);

        if ($response->failed()) {
            throw new \Exception('OpenAI API Error: '.$response->body(), $response->status());
        }

        $body = $response->toPsrResponse()->getBody();
        $yieldedContent = false;

        while (! $body->eof()) {
            try {
                $line = $this->readLine($body);
            } catch (\RuntimeException $e) {
                if ($yieldedContent && $this->isStreamReadError($e)) {
                    \Log::warning('OpenAI stream read error after partial content; stopping gracefully', [
                        'model' => $this->model,
                        'error' => $e->getMessage(),
                    ]);
                    break;
                }
                throw $e;
            }

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
                    $yieldedContent = true;
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
        $response = $this->sendToolChatRequest($messages, $tools, $temperature);

        if ($response->failed()) {
            throw new \Exception('OpenAI API Error: '.$response->body(), $response->status());
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

    protected function isStreamReadError(\RuntimeException $e): bool
    {
        $msg = strtolower($e->getMessage());

        return str_contains($msg, 'unable to read from stream')
            || str_contains($msg, 'stream is detached')
            || str_contains($msg, 'connection reset')
            || str_contains($msg, 'broken pipe');
    }

    protected function readLine($stream): string
    {
        $buffer = '';
        $startedAt = microtime(true);
        $streamReadTimeoutSeconds = max(1, (int) config('ai.http_timeout_seconds', 90));

        while (! $stream->eof()) {
            $char = $stream->read(1);

            if ($char === '') {
                if ((microtime(true) - $startedAt) >= $streamReadTimeoutSeconds) {
                    throw new \Exception('OpenAI stream read timed out before receiving a full event line.');
                }

                continue;
            }

            if ($char === "\n") {
                break;
            }
            $buffer .= $char;
        }

        return trim($buffer);
    }

    private function sendChatRequest(Collection $messages, bool $stream, float $temperature = 0.7)
    {
        $payload = [
            'model' => $this->model,
            'temperature' => $temperature,
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
            $readTimeoutSeconds = max(1, (int) config('ai.http_timeout_seconds', 90));
            $client = $client->withOptions([
                'stream' => true,
                'read_timeout' => $readTimeoutSeconds,
            ]);
        }

        $response = $client->post("{$this->baseUrl}/chat/completions", $payload);

        if ($response->failed()) {
            Log::error('AI API request failed', [
                'provider_url' => $this->baseUrl,
                'model' => $this->model,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }

        return $response;
    }

    private function sendToolChatRequest(Collection $messages, Collection $tools, float $temperature = 0.7)
    {
        $payload = [
            'model' => $this->model,
            'temperature' => $temperature,
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

        $response = $this->buildRequest()->post("{$this->baseUrl}/chat/completions", $payload);

        if ($response->failed()) {
            Log::error('AI API request failed', [
                'provider_url' => $this->baseUrl,
                'model' => $this->model,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }

        return $response;
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
                function (\Exception $exception): bool {
                    if ($exception instanceof ConnectionException) {
                        return true;
                    }
                    if ($exception instanceof RequestException) {
                        $status = $exception->response->status();
                        // Retry on 429 (rate limit) and 5xx (server errors); never on 4xx client errors.
                        return $status === 429 || $status >= 500;
                    }

                    return false;
                },
                throw: false
            );
        }

        return $request;
    }
}
