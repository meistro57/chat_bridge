<?php

namespace App\Services\AI\Drivers;

use App\Services\AI\Contracts\AIDriverInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class GeminiDriver implements AIDriverInterface
{
    public function __construct(
        protected string $apiKey,
        protected string $model = 'gemini-1.5-flash',
        protected string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta'
    ) {}

    public function chat(Collection $messages, float $temperature = 0.7): string
    {
        $payload = $this->preparePayload($messages, $temperature);

        $response = Http::post("{$this->baseUrl}/models/{$this->model}:generateContent?key={$this->apiKey}", $payload);

        if ($response->failed()) {
            throw new \Exception('Gemini API Error: '.$response->body());
        }

        $content = $response->json('candidates.0.content.parts.0.text');

        if ($content === null) {
            $responseData = $response->json();
            throw new \Exception('Gemini API returned an unexpected response structure. Response: ' . json_encode($responseData));
        }

        return $content;
    }

    public function streamChat(Collection $messages, float $temperature = 0.7): iterable
    {
        $payload = $this->preparePayload($messages, $temperature);

        $response = Http::withOptions(['stream' => true])
            ->post("{$this->baseUrl}/models/{$this->model}:streamGenerateContent?alt=sse&key={$this->apiKey}", $payload);

        $body = $response->toPsrResponse()->getBody();

        while (! $body->eof()) {
            $line = $this->readLine($body);

            if (str_starts_with($line, 'data: ')) {
                $data = substr($line, 6);
                $json = json_decode($data, true);

                $content = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
                if ($content) {
                    yield $content;
                }
            }
        }
    }

    protected function preparePayload(Collection $messages, float $temperature): array
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
                'temperature' => $temperature,
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
