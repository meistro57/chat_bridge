<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class McpTrafficMonitor
{
    private const CACHE_KEY = 'mcp_traffic.events';

    private const MAX_EVENTS = 300;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function record(array $payload): void
    {
        $event = [
            'id' => (string) Str::uuid(),
            'at' => now()->toIso8601String(),
            'tool_name' => (string) ($payload['tool_name'] ?? 'unknown'),
            'provider' => $this->nullableString($payload['provider'] ?? null),
            'model' => $this->nullableString($payload['model'] ?? null),
            'arguments' => is_array($payload['arguments'] ?? null) ? $payload['arguments'] : [],
            'error' => $this->nullableString($payload['error'] ?? null),
            'duration_ms' => (int) round((float) ($payload['duration_ms'] ?? 0)),
            'result_preview' => $this->summarizeResult($payload['result'] ?? null),
        ];

        $events = Cache::get(self::CACHE_KEY, []);
        if (! is_array($events)) {
            $events = [];
        }

        $events[] = $event;
        if (count($events) > self::MAX_EVENTS) {
            $events = array_slice($events, -self::MAX_EVENTS);
        }

        Cache::put(self::CACHE_KEY, $events, now()->addHours(12));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recent(int $limit = 100, ?string $provider = null): array
    {
        $events = Cache::get(self::CACHE_KEY, []);
        if (! is_array($events)) {
            return [];
        }

        $normalizedProvider = $provider !== null ? strtolower(trim($provider)) : null;
        if ($normalizedProvider !== null && $normalizedProvider !== '') {
            $events = array_values(array_filter($events, function ($event) use ($normalizedProvider) {
                $eventProvider = is_array($event) ? strtolower((string) ($event['provider'] ?? '')) : '';

                return $eventProvider === $normalizedProvider;
            }));
        }

        $safeLimit = max(1, min($limit, 250));

        return array_slice(array_reverse($events), 0, $safeLimit);
    }

    public function clear(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function summarizeResult(mixed $result): string
    {
        if ($result === null) {
            return 'null';
        }

        if (is_string($result)) {
            return mb_substr($result, 0, 200);
        }

        if (is_bool($result)) {
            return $result ? 'true' : 'false';
        }

        if (is_numeric($result)) {
            return (string) $result;
        }

        if (is_array($result)) {
            return mb_substr(json_encode($result, JSON_UNESCAPED_SLASHES) ?: '[unencodable array]', 0, 300);
        }

        if (is_object($result)) {
            return 'object('.$result::class.')';
        }

        return gettype($result);
    }
}
