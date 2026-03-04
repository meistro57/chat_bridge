<?php

namespace App\Services\AI\Drivers;

use App\Services\AI\Contracts\AIDriverInterface;
use App\Services\AI\Data\AIResponse;
use App\Services\AI\Data\MessageData;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class BedrockDriver implements AIDriverInterface
{
    protected ?int $lastTokenUsage = null;

    public function __construct(
        protected string $accessKeyId,
        protected string $secretAccessKey,
        protected ?string $sessionToken = null,
        protected string $region = 'us-east-1',
        protected string $model = 'anthropic.claude-3-7-sonnet-20250219-v1:0',
        protected ?string $baseUrl = null
    ) {}

    public function chat(Collection $messages, float $temperature = 0.7): AIResponse
    {
        $payloadArray = $this->buildPayload($messages, $temperature);
        $payloadJson = json_encode($payloadArray, JSON_UNESCAPED_SLASHES);

        if ($payloadJson === false) {
            throw new \Exception('Bedrock payload serialization failed.');
        }

        $uri = '/model/'.rawurlencode($this->model).'/invoke';
        $response = $this->sendSignedRequest('POST', $uri, $payloadJson);

        if ($response->failed()) {
            throw new \Exception('Bedrock API Error: '.$response->body(), $response->status());
        }

        $data = $response->json();
        $content = $this->extractContent($data);

        if ($content === null) {
            throw new \Exception('Bedrock API returned unexpected response structure. Response: '.json_encode($data));
        }

        $usage = $data['usage'] ?? [];
        $promptTokens = $usage['input_tokens'] ?? null;
        $completionTokens = $usage['output_tokens'] ?? null;
        $totalTokens = (is_int($promptTokens) && is_int($completionTokens))
            ? $promptTokens + $completionTokens
            : null;
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
        $response = $this->chat($messages, $temperature);

        if ($response->content !== '') {
            yield $response->content;
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

    /**
     * @param  Collection<int, MessageData>  $messages
     * @return array<string, mixed>
     */
    protected function buildPayload(Collection $messages, float $temperature): array
    {
        $systemMessages = $messages->where('role', 'system');
        $systemPrompt = $systemMessages->isNotEmpty()
            ? $systemMessages
                ->pluck('content')
                ->map(fn ($content) => rtrim((string) $content))
                ->implode("\n\n")
            : null;

        $chatMessages = $messages
            ->where('role', '!=', 'system')
            ->map(function (MessageData $message): array {
                $role = $message->role === 'assistant' ? 'assistant' : 'user';
                $text = $message->name && $message->role === 'assistant'
                    ? "[{$message->name}]: {$message->content}"
                    : (string) $message->content;

                return [
                    'role' => $role,
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => rtrim($text),
                        ],
                    ],
                ];
            })
            ->values()
            ->all();

        return array_filter([
            'anthropic_version' => 'bedrock-2023-05-31',
            'max_tokens' => 8192,
            'temperature' => $temperature,
            'system' => $systemPrompt,
            'messages' => $chatMessages,
        ], fn ($value) => $value !== null);
    }

    protected function resolveEndpointHost(): string
    {
        if (is_string($this->baseUrl) && $this->baseUrl !== '') {
            $host = parse_url($this->baseUrl, PHP_URL_HOST);
            if (is_string($host) && $host !== '') {
                return $host;
            }
        }

        return "bedrock-runtime.{$this->region}.amazonaws.com";
    }

    protected function resolveEndpointUrl(string $host, string $uri): string
    {
        if (is_string($this->baseUrl) && $this->baseUrl !== '') {
            return rtrim($this->baseUrl, '/').$uri;
        }

        return "https://{$host}{$uri}";
    }

    protected function sendSignedRequest(string $method, string $uri, string $payloadJson)
    {
        $method = strtoupper($method);
        $host = $this->resolveEndpointHost();
        $service = 'bedrock-runtime';
        $algorithm = 'AWS4-HMAC-SHA256';
        $amzDate = gmdate('Ymd\THis\Z');
        $dateStamp = gmdate('Ymd');
        $payloadHash = hash('sha256', $payloadJson);
        $credentialScope = "{$dateStamp}/{$this->region}/{$service}/aws4_request";

        $headers = [
            'content-type' => 'application/json',
            'host' => $host,
            'x-amz-date' => $amzDate,
            'x-amz-content-sha256' => $payloadHash,
        ];

        if (is_string($this->sessionToken) && $this->sessionToken !== '') {
            $headers['x-amz-security-token'] = $this->sessionToken;
        }

        ksort($headers);

        $canonicalHeaders = '';
        foreach ($headers as $name => $value) {
            $canonicalHeaders .= strtolower($name).':'.trim((string) $value)."\n";
        }

        $signedHeaders = implode(';', array_keys($headers));

        $canonicalRequest = implode("\n", [
            $method,
            $uri,
            '',
            $canonicalHeaders,
            $signedHeaders,
            $payloadHash,
        ]);

        $stringToSign = implode("\n", [
            $algorithm,
            $amzDate,
            $credentialScope,
            hash('sha256', $canonicalRequest),
        ]);

        $signingKey = $this->getSignatureKey($this->secretAccessKey, $dateStamp, $this->region, $service);
        $signature = hash_hmac('sha256', $stringToSign, $signingKey);

        $authorization = "{$algorithm} Credential={$this->accessKeyId}/{$credentialScope}, SignedHeaders={$signedHeaders}, Signature={$signature}";

        $outboundHeaders = [
            'Authorization' => $authorization,
            'Content-Type' => 'application/json',
            'Host' => $host,
            'X-Amz-Date' => $amzDate,
            'X-Amz-Content-Sha256' => $payloadHash,
        ];

        if (is_string($this->sessionToken) && $this->sessionToken !== '') {
            $outboundHeaders['X-Amz-Security-Token'] = $this->sessionToken;
        }

        return Http::withHeaders($outboundHeaders)
            ->timeout(max(1, (int) config('ai.http_timeout_seconds', 90)))
            ->connectTimeout(max(1, (int) config('ai.http_connect_timeout_seconds', 15)))
            ->withBody($payloadJson, 'application/json')
            ->send($method, $this->resolveEndpointUrl($host, $uri));
    }

    protected function getSignatureKey(string $secretKey, string $dateStamp, string $regionName, string $serviceName): string
    {
        $kDate = hash_hmac('sha256', $dateStamp, 'AWS4'.$secretKey, true);
        $kRegion = hash_hmac('sha256', $regionName, $kDate, true);
        $kService = hash_hmac('sha256', $serviceName, $kRegion, true);

        return hash_hmac('sha256', 'aws4_request', $kService, true);
    }

    protected function extractContent(?array $payload): ?string
    {
        if ($payload === null) {
            return null;
        }

        $contentBlocks = $payload['content'] ?? null;

        if (is_array($contentBlocks)) {
            $texts = [];
            foreach ($contentBlocks as $block) {
                if (is_array($block) && ($block['type'] ?? null) === 'text') {
                    $texts[] = (string) ($block['text'] ?? '');
                }
            }

            if ($texts !== []) {
                return implode('', $texts);
            }

            if ($contentBlocks === []) {
                return '';
            }
        }

        return null;
    }
}
