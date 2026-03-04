<?php

namespace App\Services\AI\Tools;

use App\Support\McpTrafficMonitor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ToolExecutor
{
    public function __construct(
        protected McpTools $mcpTools,
        protected McpTrafficMonitor $trafficMonitor
    ) {}

    /**
     * Execute a tool call and return the result
     *
     * @param  array<string, mixed>  $arguments
     * @param  array<string, mixed>  $context
     * @return array{tool_name: string, result: mixed, error: ?string}
     */
    public function execute(string $toolName, array $arguments, array $context = []): array
    {
        $startedAt = microtime(true);

        try {
            $tool = $this->findTool($toolName);

            if (! $tool) {
                Log::warning('Tool not found', ['tool_name' => $toolName]);
                $this->trafficMonitor->record([
                    'tool_name' => $toolName,
                    'provider' => $context['provider'] ?? null,
                    'model' => $context['model'] ?? null,
                    'arguments' => $arguments,
                    'result' => null,
                    'error' => "Tool '$toolName' not found",
                    'duration_ms' => (microtime(true) - $startedAt) * 1000,
                ]);

                return [
                    'tool_name' => $toolName,
                    'result' => null,
                    'error' => "Tool '$toolName' not found",
                ];
            }

            Log::info('Executing tool', [
                'tool_name' => $toolName,
                'arguments' => $arguments,
            ]);

            $result = $tool->execute($arguments);
            $this->trafficMonitor->record([
                'tool_name' => $toolName,
                'provider' => $context['provider'] ?? null,
                'model' => $context['model'] ?? null,
                'arguments' => $arguments,
                'result' => $result,
                'error' => null,
                'duration_ms' => (microtime(true) - $startedAt) * 1000,
            ]);

            return [
                'tool_name' => $toolName,
                'result' => $result,
                'error' => null,
            ];
        } catch (\Exception $e) {
            Log::error('Tool execution failed', [
                'tool_name' => $toolName,
                'arguments' => $arguments,
                'error' => $e->getMessage(),
            ]);
            $this->trafficMonitor->record([
                'tool_name' => $toolName,
                'provider' => $context['provider'] ?? null,
                'model' => $context['model'] ?? null,
                'arguments' => $arguments,
                'result' => null,
                'error' => $e->getMessage(),
                'duration_ms' => (microtime(true) - $startedAt) * 1000,
            ]);

            return [
                'tool_name' => $toolName,
                'result' => null,
                'error' => $e->getMessage(),
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
