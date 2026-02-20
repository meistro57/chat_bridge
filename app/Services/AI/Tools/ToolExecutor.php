<?php

namespace App\Services\AI\Tools;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ToolExecutor
{
    public function __construct(
        protected McpTools $mcpTools
    ) {}

    /**
     * Execute a tool call and return the result
     *
     * @param  string  $toolName
     * @param  array<string, mixed>  $arguments
     * @return array{tool_name: string, result: mixed, error: ?string}
     */
    public function execute(string $toolName, array $arguments): array
    {
        try {
            $tool = $this->findTool($toolName);
            
            if (!$tool) {
                Log::warning("Tool not found", ["tool_name" => $toolName]);
                return [
                    "tool_name" => $toolName,
                    "result" => null,
                    "error" => "Tool '$toolName' not found",
                ];
            }

            Log::info("Executing tool", [
                "tool_name" => $toolName,
                "arguments" => $arguments,
            ]);

            $result = $tool->execute($arguments);

            return [
                "tool_name" => $toolName,
                "result" => $result,
                "error" => null,
            ];
        } catch (\Exception $e) {
            Log::error("Tool execution failed", [
                "tool_name" => $toolName,
                "arguments" => $arguments,
                "error" => $e->getMessage(),
            ]);

            return [
                "tool_name" => $toolName,
                "result" => null,
                "error" => $e->getMessage(),
            ];
        }
    }

    /**
     * Get all available tools
     *
     * @return Collection<int, ToolDefinition>
     */
    public function getAllTools(): Collection
    {
        return $this->mcpTools->getAllTools();
    }

    /**
     * Find a tool by name
     */
    protected function findTool(string $name): ?ToolDefinition
    {
        return $this->getAllTools()->first(fn (ToolDefinition $tool) => $tool->name === $name);
    }
}
