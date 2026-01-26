<?php

namespace Tests\Unit;

use App\Http\Controllers\Admin\SystemController;
use App\Services\System\EnvFileService;
use Tests\TestCase;

class SystemControllerCodexTest extends TestCase
{
    public function test_builds_codex_command_with_output_and_prompt(): void
    {
        $controller = new class($this->createMock(EnvFileService::class)) extends SystemController
        {
            public function __construct(EnvFileService $envService)
            {
                parent::__construct($envService);
            }

            public function exposedBuildCodexCommand(string $prompt, string $outputPath): array
            {
                return $this->buildCodexCommand($prompt, $outputPath);
            }
        };

        $prompt = 'Test prompt';
        $outputPath = storage_path('app/codex/test-output.txt');

        $command = $controller->exposedBuildCodexCommand($prompt, $outputPath);

        $this->assertSame('npx', $command[0]);
        $this->assertSame('--no-install', $command[1]);
        $this->assertSame('codex', $command[2]);
        $this->assertSame('exec', $command[3]);
        $this->assertContains('--output-last-message', $command);
        $this->assertContains($outputPath, $command);
        $this->assertSame($prompt, $command[count($command) - 1]);
    }

    public function test_builds_codex_environment_with_terminal_overrides(): void
    {
        $controller = new class($this->createMock(EnvFileService::class)) extends SystemController
        {
            public function __construct(EnvFileService $envService)
            {
                parent::__construct($envService);
            }

            public function exposedBuildCodexEnvironment(string $openaiKey): array
            {
                return $this->buildCodexEnvironment($openaiKey);
            }
        };

        $environment = $controller->exposedBuildCodexEnvironment('test-key');

        $this->assertSame('test-key', $environment['CODEX_API_KEY']);
        $this->assertSame('test-key', $environment['OPENAI_API_KEY']);
        $this->assertSame('dumb', $environment['TERM']);
        $this->assertSame('1', $environment['NO_COLOR']);
        $this->assertSame('120', $environment['COLUMNS']);
        $this->assertSame('40', $environment['LINES']);
    }
}
