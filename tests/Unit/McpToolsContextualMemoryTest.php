<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\McpController;
use App\Services\AI\EmbeddingService;
use App\Services\AI\Tools\McpTools;
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

class McpToolsContextualMemoryTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_get_contextual_memory_sends_topic_and_limit_as_query_parameters(): void
    {
        $embeddingService = Mockery::mock(EmbeddingService::class);
        $this->app->instance(EmbeddingService::class, $embeddingService);

        $controller = Mockery::mock(McpController::class);
        $controller->shouldReceive('contextualMemory')
            ->once()
            ->with(
                Mockery::on(function (Request $request): bool {
                    return $request->query('topic') === 'queue recovery'
                        && (int) $request->query('limit') === 20;
                }),
                $embeddingService
            )
            ->andReturn(collect([
                ['id' => 1, 'content' => 'matched context'],
            ]));

        $tools = new McpTools($controller);
        $tool = $tools->getAllTools()->firstWhere('name', 'get_contextual_memory');

        $result = $tool->execute(['topic' => 'queue recovery', 'limit' => 999]);

        $this->assertSame([
            ['id' => 1, 'content' => 'matched context'],
        ], $result);
    }
}
