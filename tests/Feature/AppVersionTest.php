<?php

namespace Tests\Feature;

use Tests\TestCase;

class AppVersionTest extends TestCase
{
    public function test_app_version_is_configured(): void
    {
        $this->assertSame('1.0.0', config('app.version'));
    }
}
