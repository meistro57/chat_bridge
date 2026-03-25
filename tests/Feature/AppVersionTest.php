<?php

namespace Tests\Feature;

use Tests\TestCase;

class AppVersionTest extends TestCase
{
    public function test_app_version_is_configured(): void
    {
        $configuredVersion = (string) config('app.version');
        $expectedVersion = (string) env('APP_VERSION', '1.0.0');

        $this->assertNotSame('', $configuredVersion);
        $this->assertSame($expectedVersion, $configuredVersion);
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $configuredVersion);
    }
}
