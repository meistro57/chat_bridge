<?php

namespace App\Services\AI\Drivers;

use App\Services\AI\Data\AIResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class OpenRouterDriver extends OpenAIDriver
{
    public function __construct(
        protected string $apiKey,
        protected string $model = 'openai/gpt-4o-mini',
        protected ?string $appName = 'Chat Bridge',
        protected ?string $referer = 'https://github.com/meistro57/chat_bridge',
        protected string $baseUrl = 'https://openrouter.ai/api/v1'
    ) {}

    protected function getHeaders(): array
    {
        return array_filter([
            'Authorization' => "Bearer {$this->apiKey}",
            'HTTP-Referer' => $this->referer,
            'X-Title' => $this->appName,
            'Content-Type' => 'application/json',
        ]);
    }

    public function chat(Collection $messages, float $temperature = 0.7): AIResponse
    {
        \Log::info('OpenRouter Chat Request', [
            'model' => $this->model,
            'messages_count' => $messages->count(),
            'headers_keys' => array_keys($this->getHeaders()),
        ]);

        try {
            $requestBody = [
                'model' => $this->model,
                'temperature' => $temperature,
                'messages' => $messages->map->toArray()->all(),
            ];

            \Log::debug('OpenRouter Request Body', [
                'body' => json_encode($requestBody),
            ]);

            $timeoutSeconds = max(1, (int) config('ai.http_timeout_seconds', 90));
            $connectTimeoutSeconds = max(1, (int) config('ai.http_connect_timeout_seconds', 15));

            $response = Http::withHeaders($this->getHeaders())
                ->timeout($timeoutSeconds)
                ->connectTimeout($connectTimeoutSeconds)
                ->post("{$this->baseUrl}/chat/completions", $requestBody);

            \Log::info('OpenRouter Response', [
                'status' => $response->status(),
                'response_body' => substr($response->body(), 0, 500),
            ]);

            if ($response->failed()) {
                $errorContext = [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ];

                // Try to parse JSON error if possible
                try {
                    $jsonBody = $response->json();
                    $errorContext['json_error'] = $jsonBody;
                } catch (\Exception $parseError) {
                    $errorContext['parse_error'] = $parseError->getMessage();
                }

                \Log::error('OpenRouter Chat Failed', $errorContext);

                // Construct a meaningful error message
                $errorMessage = 'OpenRouter API Error: ';
                $errorMessage .= isset($jsonBody['error']['message'])
                    ? $jsonBody['error']['message']
                    : $response->body();

                throw new \Exception($errorMessage, $response->status());
            }

            $data = $response->json();
            $content = $data['choices'][0]['message']['content'] ?? '';

            \Log::info('OpenRouter Chat Response', [
                'content_length' => strlen($content),
                'tokens_used' => $data['usage']['total_tokens'] ?? 0,
            ]);

            if (empty($content)) {
                \Log::warning('OpenRouter API returned empty content', [
                    'model' => $this->model,
                    'data' => $data,
                ]);
            }

            $usage = $data['usage'] ?? [];
            $this->lastTokenUsage = $usage['total_tokens'] ?? null;

            return new AIResponse(
                content: $content,
                promptTokens: $usage['prompt_tokens'] ?? null,
                completionTokens: $usage['completion_tokens'] ?? null,
                totalTokens: $usage['total_tokens'] ?? null
            );
        } catch (\Exception $e) {
            \Log::error('OpenRouter Chat Exception', [
                'error_type' => get_class($e),
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    public function streamChat(Collection $messages, float $temperature = 0.7): iterable
    {
        $this->lastTokenUsage = null;

        $readTimeoutSeconds = max(1, (int) config('ai.http_timeout_seconds', 90));

        $response = Http::withHeaders($this->getHeaders())
            ->withOptions([
                'stream' => true,
                'read_timeout' => $readTimeoutSeconds,
            ])
            ->post("{$this->baseUrl}/chat/completions", [
                'model' => $this->model,
                'temperature' => $temperature,
                'messages' => $messages->map->toArray()->all(),
                'stream' => true,
            ]);

        if ($response->failed()) {
            \Log::error('OpenRouter stream request failed', [
                'model' => $this->model,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('OpenRouter API Error: '.$response->body(), $response->status());
        }

        $body = $response->toPsrResponse()->getBody();
        $yieldedContent = false;

        while (! $body->eof()) {
            try {
                $line = $this->readLine($body);
            } catch (\RuntimeException $e) {
                if ($yieldedContent && $this->isStreamReadError($e)) {
                    \Log::warning('OpenRouter stream read error after partial content; stopping gracefully', [
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
                if (isset($json['error'])) {
                    throw new \Exception('OpenRouter Stream Error: '.json_encode($json['error']));
                }

                // Capture token usage if present in the stream chunk
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

    public function chatWithTools(Collection $messages, Collection $tools, float $temperature = 0.7): array
    {
        throw new \Exception(get_class($this).' does not support tool calling yet');
    }

    public function supportsTools(): bool
    {
        return false;
    }
}
