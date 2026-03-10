<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\McpController;
use App\Services\AI\Tools\McpTools;
use Illuminate\Http\JsonResponse;
use Mockery;
use PHPUnit\Framework\TestCase;

class McpToolsTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_search_conversations_normalizes_collection_results(): void
    {
        $controller = Mockery::mock(McpController::class);
        $controller->shouldReceive('search')
            ->once()
            ->andReturn(collect([
                ['id' => 1, 'content' => 'match'],
            ]));

        $tools = new McpTools($controller);
        $tool = $tools->getAllTools()->firstWhere('name', 'search_conversations');

        $result = $tool->execute(['keyword' => 'match']);

        $this->assertSame([
            ['id' => 1, 'content' => 'match'],
        ], $result);
    }

    public function test_get_mcp_stats_normalizes_json_response_results(): void
    {
        $controller = Mockery::mock(McpController::class);
        $controller->shouldReceive('stats')
            ->once()
            ->andReturn(new JsonResponse([
                'conversations_count' => 10,
                'messages_count' => 25,
            ]));

        $tools = new McpTools($controller);
        $tool = $tools->getAllTools()->firstWhere('name', 'get_mcp_stats');

        $result = $tool->execute([]);

        $this->assertSame([
            'conversations_count' => 10,
            'messages_count' => 25,
        ], $result);
    }
}
