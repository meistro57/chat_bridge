<?php

namespace Tests\Unit;

use App\Services\OpenRouterService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenRouterServiceTest extends TestCase
{
    public function test_service_can_be_constructed_when_openrouter_key_is_null(): void
    {
        Config::set('services.openrouter.key', null);

        $service = new OpenRouterService;

        $this->assertInstanceOf(OpenRouterService::class, $service);
    }

    public function test_dashboard_stats_include_activity_summary_and_top_model_share(): void
    {
        Cache::flush();
        Config::set('services.openrouter.key', 'test-openrouter-key');

        $today = now()->toDateString();
        $twoDaysAgo = now()->subDays(2)->toDateString();

        Http::fake(function ($request) use ($today, $twoDaysAgo) {
            if (str_contains($request->url(), '/credits')) {
                return Http::response([
                    'data' => [
                        'total_credits' => 25.5,
                        'total_usage' => 7.25,
                    ],
                ], 200);
            }

            if (str_contains($request->url(), '/key')) {
                return Http::response([
                    'data' => [
                        'limit' => 1000,
                        'usage' => 42,
                        'is_free_tier' => false,
                    ],
                ], 200);
            }

            if (str_contains($request->url(), '/activity?date=')) {
                return Http::response([
                    'data' => [
                        ['model' => 'openai/gpt-4o-mini', 'cost' => 0.4, 'created_at' => "{$today}T12:00:00Z"],
                    ],
                ], 200);
            }

            if (str_contains($request->url(), '/activity')) {
                return Http::response([
                    'data' => [
                        ['model' => 'anthropic/claude-sonnet', 'cost' => 0.6, 'created_at' => "{$twoDaysAgo}T15:00:00Z"],
                        ['model' => 'openai/gpt-4o-mini', 'cost' => 0.4, 'created_at' => "{$today}T12:00:00Z"],
                    ],
                ], 200);
            }

            return Http::response([], 404);
        });

        $stats = (new OpenRouterService)->getDashboardStats();

        $this->assertSame(18.25, $stats['credits']['balance']);
        $this->assertSame(0.4, $stats['today_spend']);
        $this->assertSame(2, $stats['activity_summary']['requests_30d']);
        $this->assertSame(2, $stats['activity_summary']['requests_7d']);
        $this->assertSame(1.0, $stats['activity_summary']['spend_30d']);
        $this->assertSame(2, $stats['activity_summary']['active_days']);
        $this->assertSame(0.5, $stats['activity_summary']['avg_daily_spend']);
        $this->assertSame(0.5, $stats['activity_summary']['avg_cost_per_request']);
        $this->assertSame('anthropic/claude-sonnet', $stats['top_model']['name']);
        $this->assertSame(0.6, $stats['top_model']['cost']);
        $this->assertSame(60.0, $stats['top_model']['share_percent']);
    }

    public function test_dashboard_stats_falls_back_when_activity_date_filter_returns_400(): void
    {
        Cache::flush();
        Config::set('services.openrouter.key', 'test-openrouter-key');

        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();

        Http::fake(function ($request) use ($today, $yesterday) {
            if (str_contains($request->url(), '/credits')) {
                return Http::response([
                    'data' => [
                        'total_credits' => 10,
                        'total_usage' => 1.5,
                    ],
                ], 200);
            }

            if (str_contains($request->url(), '/key')) {
                return Http::response([
                    'data' => [
                        'limit' => 100,
                        'usage' => 5,
                        'is_free_tier' => false,
                    ],
                ], 200);
            }

            if (str_contains($request->url(), "/activity?date={$today}")) {
                return Http::response([
                    'error' => 'invalid date filter',
                ], 400);
            }

            if (str_contains($request->url(), '/activity')) {
                return Http::response([
                    'data' => [
                        ['model' => 'openai/gpt-4o-mini', 'cost' => 0.25, 'created_at' => "{$today}T10:00:00Z"],
                        ['model' => 'anthropic/claude-sonnet', 'cost' => 0.75, 'created_at' => "{$yesterday}T10:00:00Z"],
                    ],
                ], 200);
            }

            return Http::response([], 404);
        });

        $stats = (new OpenRouterService)->getDashboardStats();

        $this->assertSame(0.25, $stats['today_spend']);
        $this->assertSame(2, $stats['activity_summary']['requests_30d']);
        $this->assertSame(1.0, $stats['activity_summary']['spend_30d']);
    }
}
