<?php

namespace Tests\Unit;

use App\Services\AI\Tools\ToolDefinition;
use PHPUnit\Framework\TestCase;

class ToolDefinitionSchemaTest extends TestCase
{
    public function test_empty_properties_are_normalized_to_object_for_openai_schema(): void
    {
        $tool = new ToolDefinition(
            name: 'get_mcp_stats',
            description: 'Get MCP stats',
            parameters: [
                'type' => 'object',
                'properties' => [],
                'required' => [],
            ],
            executor: fn (array $args) => $args
        );

        $schema = $tool->toOpenAISchema();

        $this->assertIsObject($schema['function']['parameters']['properties']);
    }

    public function test_non_empty_properties_remain_array_in_openai_schema(): void
    {
        $tool = new ToolDefinition(
            name: 'search_conversations',
            description: 'Search conversations',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'keyword' => [
                        'type' => 'string',
                    ],
                ],
                'required' => ['keyword'],
            ],
            executor: fn (array $args) => $args
        );

        $schema = $tool->toOpenAISchema();

        $this->assertIsArray($schema['function']['parameters']['properties']);
        $this->assertArrayHasKey('keyword', $schema['function']['parameters']['properties']);
    }
}
