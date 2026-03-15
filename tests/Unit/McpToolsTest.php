<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\McpController;
use App\Services\AI\Tools\McpTools;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            ->with(Mockery::on(function (Request $request): bool {
                return $request->query('keyword') === 'match';
            }))
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

    public function test_get_recent_chats_sends_limit_as_query_parameter(): void
    {
        $controller = Mockery::mock(McpController::class);
        $controller->shouldReceive('recentChats')
            ->once()
            ->with(Mockery::on(function (Request $request): bool {
                return (int) $request->query('limit') === 7;
            }))
            ->andReturn(collect([
                ['id' => 'abc-123', 'status' => 'completed'],
            ]));

        $tools = new McpTools($controller);
        $tool = $tools->getAllTools()->firstWhere('name', 'get_recent_chats');

        $result = $tool->execute(['limit' => 7]);

        $this->assertSame([
            ['id' => 'abc-123', 'status' => 'completed'],
        ], $result);
    }

    public function test_get_conversation_schema_uses_string_identifier(): void
    {
        $controller = Mockery::mock(McpController::class);
        $tools = new McpTools($controller);

        $tool = $tools->getAllTools()->firstWhere('name', 'get_conversation');

        $this->assertSame('string', $tool->parameters['properties']['conversation_id']['type']);
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
