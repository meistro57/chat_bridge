<?php

namespace App\Services\AI\Drivers;

use App\Services\AI\Contracts\AIDriverInterface;
use App\Services\AI\Data\AIResponse;
use App\Services\AI\Tools\ToolDefinition;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class GeminiDriver implements AIDriverInterface
{
    protected ?int $lastTokenUsage = null;

    public function __construct(
        protected string $apiKey,
        protected string $model = 'gemini-2.0-flash',
        protected string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta'
    ) {}

    public function chat(Collection $messages, float $temperature = 0.7): AIResponse
    {
        $payload = $this->preparePayload($messages);

        $response = Http::post("{$this->baseUrl}/models/{$this->model}:generateContent?key={$this->apiKey}", $payload);

        if ($response->failed()) {
            throw new \Exception('Gemini API Error: '.$response->body());
        }

        $data = $response->json();
        $content = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if ($content === null) {
            throw new \Exception('Gemini API returned an unexpected response structure. Response: '.json_encode($data));
        }

        // Extract token usage if available
        $usage = $data['usageMetadata'] ?? [];
        $promptTokens = $usage['promptTokenCount'] ?? null;
        $completionTokens = $usage['candidatesTokenCount'] ?? null;
        $totalTokens = $usage['totalTokenCount'] ?? null;
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

        $payload = $this->preparePayload($messages);

        $response = Http::withOptions(['stream' => true])
            ->post("{$this->baseUrl}/models/{$this->model}:streamGenerateContent?alt=sse&key={$this->apiKey}", $payload);

        $body = $response->toPsrResponse()->getBody();

        while (! $body->eof()) {
            $line = $this->readLine($body);

            if (str_starts_with($line, 'data: ')) {
                $data = substr($line, 6);
                $json = json_decode($data, true);

                // Capture token usage if present
                if (isset($json['usageMetadata']['totalTokenCount'])) {
                    $this->lastTokenUsage = $json['usageMetadata']['totalTokenCount'];
                }

                $content = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
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
        $payload = $this->preparePayload($messages);
        $payload['tools'] = [
            [
                'function_declarations' => $tools->map(fn (ToolDefinition $tool) => $tool->toGeminiSchema())->all(),
            ],
        ];

        $response = Http::post("{$this->baseUrl}/models/{$this->model}:generateContent?key={$this->apiKey}", $payload);

        if ($response->failed()) {
            throw new \Exception('Gemini API Error: '.$response->body());
        }

        $data = $response->json();

        // Extract token usage
        $usage = $data['usageMetadata'] ?? [];
        $promptTokens = $usage['promptTokenCount'] ?? null;
        $completionTokens = $usage['candidatesTokenCount'] ?? null;
        $totalTokens = $usage['totalTokenCount'] ?? null;
        $this->lastTokenUsage = $totalTokens;

        // Check for function calls
        $toolCalls = [];
        $parts = $data['candidates'][0]['content']['parts'] ?? [];

        foreach ($parts as $part) {
            if (isset($part['functionCall'])) {
                $toolCalls[] = [
                    'id' => uniqid('call_'),
                    'name' => $part['functionCall']['name'] ?? '',
                    'arguments' => $part['functionCall']['args'] ?? [],
                ];
            }
        }

        // If there are tool calls, return them
        if (! empty($toolCalls)) {
            return [
                'response' => null,
                'tool_calls' => $toolCalls,
            ];
        }

        // Otherwise extract text content
        $content = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if ($content === null) {
            throw new \Exception('Gemini API returned unexpected response: '.json_encode($data));
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

    public function supportsTools(): bool
    {
        return true;
    }

    protected function preparePayload(Collection $messages): array
    {
        // Combine all system messages (system prompt + guidelines) into a single instruction
        $systemMessages = $messages->where('role', 'system');
        $systemInstruction = $systemMessages->isNotEmpty()
            ? ['parts' => [['text' => $systemMessages->pluck('content')->implode("\n\n")]]]
            : null;

        $contents = $messages->where('role', '!=', 'system')->map(fn ($m) => [
            'role' => $m->role === 'assistant' ? 'model' : 'user',
            'parts' => [['text' => $m->content]],
        ])->values()->all();

        return array_filter([
            'contents' => $contents,
            'system_instruction' => $systemInstruction,
            'generationConfig' => [
                'maxOutputTokens' => 2048,
            ],
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
