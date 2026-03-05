<?php

namespace App\Services\AI\Drivers;

use App\Services\AI\Contracts\AIDriverInterface;
use App\Services\AI\Data\AIResponse;
use App\Services\AI\Tools\ToolDefinition;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OllamaDriver implements AIDriverInterface
{
    protected ?int $lastTokenUsage = null;

    public function __construct(
        protected string $model = 'llama3.1',
        protected string $baseUrl = 'http://localhost:11434'
    ) {}

    public function chat(Collection $messages, float $temperature = 0.7): AIResponse
    {
        $response = Http::post("{$this->baseUrl}/api/chat", [
            'model' => $this->model,
            'messages' => $messages->map->toArray()->all(),
            'stream' => false,
        ]);

        if ($response->failed()) {
            throw new \Exception('Ollama API Error: '.$response->body());
        }

        $data = $response->json();
        $content = $data['message']['content'] ?? null;

        if ($content === null) {
            throw new \Exception('Ollama API returned an unexpected response structure. Response: '.json_encode($data));
        }

        // Ollama returns token counts in eval_count and prompt_eval_count
        $promptTokens = $data['prompt_eval_count'] ?? null;
        $completionTokens = $data['eval_count'] ?? null;
        $totalTokens = ($promptTokens && $completionTokens) ? $promptTokens + $completionTokens : null;
        $this->lastTokenUsage = $totalTokens;

        return new AIResponse(
            content: $content,
            promptTokens: $promptTokens,
            completionTokens: $completionTokens,
            totalTokens: $totalTokens
        );
    }

    public function streamChat(Collection $messages, float $temperature = 0.7): iterable
    {
        $this->lastTokenUsage = null;

        $response = Http::withOptions(['stream' => true])
            ->timeout(300)
            ->post("{$this->baseUrl}/api/chat", [
                'model' => $this->model,
                'messages' => $messages->map->toArray()->all(),
                'stream' => true,
            ]);

        $body = $response->toPsrResponse()->getBody();

        while (! $body->eof()) {
            $line = $this->readLine($body);
            if (! $line) {
                continue;
            }

            $json = json_decode($line, true);

            // Capture token usage when done
            if (($json['done'] ?? false) === true) {
                $promptTokens = $json['prompt_eval_count'] ?? null;
                $completionTokens = $json['eval_count'] ?? null;
                $this->lastTokenUsage = ($promptTokens && $completionTokens) ? $promptTokens + $completionTokens : null;
                break;
            }

            $content = $json['message']['content'] ?? '';
            if ($content) {
                yield $content;
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
            'messages' => $messages->map->toArray()->all(),
            'tools' => $tools->map(fn (ToolDefinition $tool) => $tool->toOpenAISchema())->all(),
            'stream' => false,
        ];

        $response = Http::post("{$this->baseUrl}/api/chat", $payload);

        if ($response->failed()) {
            $errorBody = (string) $response->body();
            if ($this->isUnsupportedToolsError($errorBody)) {
                Log::warning('Ollama model does not support tools; retrying without tools payload', [
                    'model' => $this->model,
                ]);

                return $this->chatWithoutTools($messages);
            }

            throw new \Exception('Ollama API Error: '.$errorBody);
        }

        $data = $response->json();
        $message = $data['message'] ?? null;
        if (! is_array($message)) {
            throw new \Exception('Ollama API returned an unexpected tool response structure. Response: '.json_encode($data));
        }

        $promptTokens = $data['prompt_eval_count'] ?? null;
        $completionTokens = $data['eval_count'] ?? null;
        $totalTokens = ($promptTokens && $completionTokens) ? $promptTokens + $completionTokens : null;
        $this->lastTokenUsage = $totalTokens;

        $toolCalls = [];
        $rawToolCalls = $message['tool_calls'] ?? [];
        if (is_array($rawToolCalls)) {
            foreach ($rawToolCalls as $rawToolCall) {
                if (! is_array($rawToolCall)) {
                    continue;
                }

                $function = $rawToolCall['function'] ?? [];
                if (! is_array($function)) {
                    continue;
                }

                $name = $function['name'] ?? '';
                if (! is_string($name) || trim($name) === '') {
                    continue;
                }

                $arguments = $function['arguments'] ?? [];
                if (is_string($arguments)) {
                    $decoded = json_decode($arguments, true);
                    $arguments = is_array($decoded) ? $decoded : [];
                }
                if (! is_array($arguments)) {
                    $arguments = [];
                }

                $toolCalls[] = [
                    'id' => is_string($rawToolCall['id'] ?? null) ? $rawToolCall['id'] : uniqid('call_', true),
                    'name' => $name,
                    'arguments' => $arguments,
                ];
            }
        }

        if ($toolCalls !== []) {
            return [
                'response' => null,
                'tool_calls' => $toolCalls,
            ];
        }

        $content = $message['content'] ?? null;
        if (! is_string($content)) {
            throw new \Exception('Ollama API returned an unexpected tool response content. Response: '.json_encode($data));
        }

        return [
            'response' => new AIResponse(
                content: $content,
                promptTokens: $promptTokens,
                completionTokens: $completionTokens,
                totalTokens: $totalTokens
            ),
            'tool_calls' => [],
        ];
    }

    protected function chatWithoutTools(Collection $messages): array
    {
        $response = Http::post("{$this->baseUrl}/api/chat", [
            'model' => $this->model,
            'messages' => $messages->map->toArray()->all(),
            'stream' => false,
        ]);

        if ($response->failed()) {
            throw new \Exception('Ollama API Error: '.$response->body());
        }

        $data = $response->json();
        $message = $data['message'] ?? null;
        if (! is_array($message)) {
            throw new \Exception('Ollama API returned an unexpected fallback response structure. Response: '.json_encode($data));
        }

        $content = $message['content'] ?? null;
        if (! is_string($content)) {
            throw new \Exception('Ollama API returned an unexpected fallback response content. Response: '.json_encode($data));
        }

        $promptTokens = $data['prompt_eval_count'] ?? null;
        $completionTokens = $data['eval_count'] ?? null;
        $totalTokens = ($promptTokens && $completionTokens) ? $promptTokens + $completionTokens : null;
        $this->lastTokenUsage = $totalTokens;

        return [
            'response' => new AIResponse(
                content: $content,
                promptTokens: $promptTokens,
                completionTokens: $completionTokens,
                totalTokens: $totalTokens
            ),
            'tool_calls' => [],
        ];
    }

    protected function isUnsupportedToolsError(string $errorBody): bool
    {
        $normalized = strtolower($errorBody);

        return str_contains($normalized, 'does not support tools')
            || str_contains($normalized, 'unsupported tools');
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
}
