<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Vite;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $this->forceTestingEnvironment();

        /** @var Application $app */
        $app = require Application::inferBasePath().'/bootstrap/app.php';

        $app->detectEnvironment(fn () => 'testing');
        $app->make(Kernel::class)->bootstrap();
        $app['config']->set('app.env', 'testing');

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);

        Vite::useHotFile(base_path('tests/Fixtures/vite.hot'));
    }

    private function forceTestingEnvironment(): void
    {
        foreach ($this->testingEnvironmentVariables() as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }

    /**
     * @return array<string, string>
     */
    private function testingEnvironmentVariables(): array
    {
        return [
            'APP_ENV' => 'testing',
            'APP_KEY' => 'base64:5DlveVTog2/77ktFfSMZaUEqv/Yx540kXfJxZ7n+W/4=',
            'APP_DEBUG' => 'true',
            'APP_CONFIG_CACHE' => '/tmp/chatbridge-testing-config.php',
            'APP_EVENTS_CACHE' => '/tmp/chatbridge-testing-events.php',
            'APP_PACKAGES_CACHE' => '/tmp/chatbridge-testing-packages.php',
            'APP_ROUTES_CACHE' => '/tmp/chatbridge-testing-routes.php',
            'APP_SERVICES_CACHE' => '/tmp/chatbridge-testing-services.php',
            'BROADCAST_CONNECTION' => 'null',
            'CACHE_STORE' => 'array',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'MAIL_MAILER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'SESSION_DRIVER' => 'array',
            'TELESCOPE_ENABLED' => 'false',
        ];
    }
}
