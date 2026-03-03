<?php

namespace Tests\Unit;

use App\Services\OpenRouterService;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class OpenRouterServiceTest extends TestCase
{
    public function test_service_can_be_constructed_when_openrouter_key_is_null(): void
    {
        Config::set('services.openrouter.key', null);

        $service = new OpenRouterService;

        $this->assertInstanceOf(OpenRouterService::class, $service);
    }
}
