<?php

namespace App\Services\AI\Drivers;

use App\Services\AI\Contracts\AIDriverInterface;
use App\Services\AI\Data\AIResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

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
            'options' => [
                'temperature' => $temperature,
            ],
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
        throw new \Exception(get_class($this).' does not support tool calling yet');
    }

    public function supportsTools(): bool
    {
        return false;
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
