<?php

namespace App\Services\AI\Contracts;

use App\Services\AI\Data\AIResponse;
use App\Services\AI\Data\MessageData;
use App\Services\AI\Tools\ToolDefinition;
use Illuminate\Support\Collection;

interface AIDriverInterface
{
    /**
     * @param  Collection<int, MessageData>  $messages
     */
    public function chat(Collection $messages, float $temperature = 0.7): AIResponse;

    /**
     * @param  Collection<int, MessageData>  $messages
     * @return iterable<string>
     */
    public function streamChat(Collection $messages, float $temperature = 0.7): iterable;

    /**
     * Get token usage from the last API call (for streaming responses)
     */
    public function getLastTokenUsage(): ?int;

    /**
     * Chat with tool calling support (non-streaming only)
     *
     * @param  Collection<int, MessageData>  $messages
     * @param  Collection<int, ToolDefinition>  $tools
     * @return array{response: ?AIResponse, tool_calls: array<int, array{id: string, name: string, arguments: array<string, mixed>}>}
     */
    public function chatWithTools(Collection $messages, Collection $tools, float $temperature = 0.7): array;

    /**
     * Check if this driver supports tool calling
     */
    public function supportsTools(): bool;
}
