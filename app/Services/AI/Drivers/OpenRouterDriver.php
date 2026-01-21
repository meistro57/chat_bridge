<?php

namespace App\Services\AI\Drivers;

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

    public function chat(Collection $messages, float $temperature = 0.7): string
    {
        \Log::info('OpenRouter Chat Request', [
            'model' => $this->model,
            'temperature' => $temperature,
            'messages_count' => $messages->count(),
            'headers_keys' => array_keys($this->getHeaders()),
        ]);

        try {
            $requestBody = [
                'model' => $this->model,
                'messages' => $messages->map->toArray()->all(),
                'temperature' => $temperature,
            ];

            \Log::debug('OpenRouter Request Body', [
                'body' => json_encode($requestBody),
            ]);

            $response = Http::withHeaders($this->getHeaders())
                ->timeout(30)  // 30 seconds timeout
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

            $responseJson = $response->json();
            $content = $responseJson['choices'][0]['message']['content'] ?? '';
            
            \Log::info('OpenRouter Chat Response', [
                'content_length' => strlen($content),
                'tokens_used' => $responseJson['usage']['total_tokens'] ?? 0,
            ]);

            if (empty($content)) {
                throw new \Exception('No content returned from OpenRouter API');
            }

            return $content;
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
        $response = Http::withHeaders($this->getHeaders())
            ->withOptions(['stream' => true])
            ->post("{$this->baseUrl}/chat/completions", [
                'model' => $this->model,
                'messages' => $messages->map->toArray()->all(),
                'temperature' => $temperature,
                'stream' => true,
            ]);

        $body = $response->toPsrResponse()->getBody();

        while (! $body->eof()) {
            $line = $this->readLine($body);

            if (str_starts_with($line, 'data: ')) {
                $data = substr($line, 6);

                if (trim($data) === '[DONE]') {
                    break;
                }

                $json = json_decode($data, true);
                if (isset($json['error'])) {
                    throw new \Exception('OpenRouter Stream Error: '.json_encode($json['error']));
                }

                $content = $json['choices'][0]['delta']['content'] ?? '';

                if ($content) {
                    yield $content;
                }
            }
        }
    }
}
