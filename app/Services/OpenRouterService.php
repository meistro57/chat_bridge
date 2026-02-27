<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class OpenRouterService
{
    protected string $apiKey;
    protected string $baseUrl = 'https://openrouter.ai/api/v1';

    public function __construct()
    {
        $this->apiKey = config('services.openrouter.key', env('OPENROUTER_API_KEY', ''));
    }

    protected function headers(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type'  => 'application/json',
        ];
    }

    /**
     * Get total credits purchased and used.
     */
    public function getCredits(): ?array
    {
        return Cache::remember('openrouter_credits', 60, function () {
            try {
                $response = Http::withHeaders($this->headers())
                    ->timeout(10)
                    ->get("{$this->baseUrl}/credits");

                if ($response->successful()) {
                    $data = $response->json('data', []);
                    $total = $data['total_credits'] ?? 0;
                    $used  = $data['total_usage'] ?? 0;
                    return [
                        'total_credits' => round($total, 4),
                        'total_usage'   => round($used, 4),
                        'balance'       => round($total - $used, 4),
                    ];
                }

                Log::warning('OpenRouter credits fetch failed', ['status' => $response->status()]);
                return null;
            } catch (\Exception $e) {
                Log::error('OpenRouter credits exception', ['error' => $e->getMessage()]);
                return null;
            }
        });
    }

    /**
     * Get activity for the last 30 days, optionally filtered by date.
     */
    public function getActivity(?string $date = null): ?array
    {
        $cacheKey = 'openrouter_activity_' . ($date ?? 'all');
        return Cache::remember($cacheKey, 120, function () use ($date) {
            try {
                $params = $date ? ['date' => $date] : [];
                $response = Http::withHeaders($this->headers())
                    ->timeout(10)
                    ->get("{$this->baseUrl}/activity", $params);

                if ($response->successful()) {
                    return $response->json('data', []);
                }

                Log::warning('OpenRouter activity fetch failed', ['status' => $response->status()]);
                return null;
            } catch (\Exception $e) {
                Log::error('OpenRouter activity exception', ['error' => $e->getMessage()]);
                return null;
            }
        });
    }

    /**
     * Get key info (rate limits, credits remaining).
     */
    public function getKeyInfo(): ?array
    {
        return Cache::remember('openrouter_key_info', 60, function () {
            try {
                $response = Http::withHeaders($this->headers())
                    ->timeout(10)
                    ->get("{$this->baseUrl}/key");

                if ($response->successful()) {
                    return $response->json('data', $response->json());
                }

                Log::warning('OpenRouter key info fetch failed', ['status' => $response->status()]);
                return null;
            } catch (\Exception $e) {
                Log::error('OpenRouter key info exception', ['error' => $e->getMessage()]);
                return null;
            }
        });
    }

    /**
     * Summarized stats for dashboard widget.
     */
    public function getDashboardStats(): array
    {
        $credits  = $this->getCredits();
        $activity = $this->getActivity();

        // Aggregate spend by model from activity
        $byModel = [];
        if (is_array($activity)) {
            foreach ($activity as $item) {
                $model = $item['model'] ?? 'unknown';
                $cost  = $item['cost'] ?? 0;
                $byModel[$model] = ($byModel[$model] ?? 0) + $cost;
            }
            arsort($byModel);
            $byModel = array_slice($byModel, 0, 5, true); // top 5
        }

        // Today's spend
        $today = now()->toDateString();
        $todayActivity = $this->getActivity($today);
        $todaySpend = 0;
        if (is_array($todayActivity)) {
            foreach ($todayActivity as $item) {
                $todaySpend += $item['cost'] ?? 0;
            }
        }

        return [
            'credits'      => $credits,
            'today_spend'  => round($todaySpend, 6),
            'top_models'   => $byModel,
            'activity_days' => is_array($activity) ? count($activity) : 0,
        ];
    }
}
