<?php

namespace Tests\Unit;

use App\Http\Controllers\Admin\SystemController;
use App\Services\System\EnvFileService;
use Tests\TestCase;

class SystemControllerCodexCommandTest extends TestCase
{
    public function test_codex_command_includes_skip_git_repo_check_flag(): void
    {
        $controller = new SystemController(app(EnvFileService::class));

        $method = new \ReflectionMethod($controller, 'buildCodexCommand');
        $method->setAccessible(true);

        $command = $method->invoke($controller, 'prompt', '/tmp/output.txt');

        $this->assertContains('--skip-git-repo-check', $command);
    }
}
