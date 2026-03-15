<?php

namespace App\Services\AI\Tools;

use App\Http\Controllers\Api\McpController;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

class McpTools
{
    public function __construct(
        protected McpController $mcpController
    ) {}

    /**
     * Get all available MCP tools
     *
     * @return Collection<int, ToolDefinition>
     */
    public function getAllTools(): Collection
    {
        return collect([
            $this->searchConversationsTool(),
            $this->getContextualMemoryTool(),
            $this->getRecentChatsTool(),
            $this->getConversationTool(),
            $this->getMcpStatsTool(),
        ]);
    }

    protected function searchConversationsTool(): ToolDefinition
    {
        return new ToolDefinition(
            name: 'search_conversations',
            description: 'Search through past conversation messages by keyword. Returns matching messages with their conversation context. Useful for finding specific topics or information discussed previously.',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'keyword' => [
                        'type' => 'string',
                        'description' => 'The keyword or phrase to search for in message content',
                    ],
                ],
                'required' => ['keyword'],
            ],
            executor: function (array $args) {
                $request = Request::create('/', 'GET', ['keyword' => $args['keyword'] ?? '']);

                return $this->normalizeToolResult($this->mcpController->search($request));
            }
        );
    }

    protected function getContextualMemoryTool(): ToolDefinition
    {
        return new ToolDefinition(
            name: 'get_contextual_memory',
            description: "Retrieve semantically similar messages from past conversations using vector search. This finds messages that are contextually related to a topic, even if they don't contain the exact keywords. Returns the most relevant messages ranked by similarity.",
            parameters: [
                'type' => 'object',
                'properties' => [
                    'topic' => [
                        'type' => 'string',
                        'description' => 'The topic or concept to find related messages about',
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Maximum number of results to return (default: 5, max: 20)',
                        'default' => 5,
                    ],
                ],
                'required' => ['topic'],
            ],
            executor: function (array $args) {
                $request = Request::create('/', 'GET', [
                    'topic' => $args['topic'] ?? '',
                    'limit' => min($args['limit'] ?? 5, 20),
                ]);

                return $this->normalizeToolResult(
                    $this->mcpController->contextualMemory(
                        $request,
                        app(\App\Services\AI\EmbeddingService::class)
                    )
                );
            }
        );
    }

    protected function getRecentChatsTool(): ToolDefinition
    {
        return new ToolDefinition(
            name: 'get_recent_chats',
            description: 'Get a list of recent conversations. Returns conversation summaries without full message history. Useful for seeing what topics have been discussed recently.',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Maximum number of conversations to return (default: 10, max: 50)',
                        'default' => 10,
                    ],
                ],
                'required' => [],
            ],
            executor: function (array $args) {
                $request = Request::create('/', 'GET', [
                    'limit' => min($args['limit'] ?? 10, 50),
                ]);

                return $this->normalizeToolResult($this->mcpController->recentChats($request));
            }
        );
    }

    protected function getConversationTool(): ToolDefinition
    {
        return new ToolDefinition(
            name: 'get_conversation',
            description: 'Get the full details of a specific conversation including all messages. Use this after finding a conversation ID from search results or recent chats.',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'conversation_id' => [
                        'type' => 'string',
                        'description' => 'The ID of the conversation to retrieve',
                    ],
                ],
                'required' => ['conversation_id'],
            ],
            executor: function (array $args) {
                $conversationId = $args['conversation_id'] ?? null;
                if (! $conversationId) {
                    return ['error' => 'conversation_id is required'];
                }

                $conversation = \App\Models\Conversation::find($conversationId);
                if (! $conversation) {
                    return ['error' => 'Conversation not found'];
                }

                return $this->normalizeToolResult($this->mcpController->conversation($conversation));
            }
        );
    }

    protected function getMcpStatsTool(): ToolDefinition
    {
        return new ToolDefinition(
            name: 'get_mcp_stats',
            description: 'Get statistics about the conversation database including total conversations, messages, and embeddings. Useful for understanding the scale of available data.',
            parameters: [
                'type' => 'object',
                'properties' => [],
                'required' => [],
            ],
            executor: function (array $args) {
                return $this->normalizeToolResult($this->mcpController->stats());
            }
        );
    }

    protected function normalizeToolResult(mixed $result): mixed
    {
        if ($result instanceof JsonResponse) {
            return $result->getData(true);
        }

        if ($result instanceof Arrayable) {
            return $result->toArray();
        }

        if ($result instanceof \JsonSerializable) {
            return $result->jsonSerialize();
        }

        if ($result instanceof Response) {
            $content = $result->getContent();

            return is_string($content) ? json_decode($content, true) ?? $content : $content;
        }

        return $result;
    }
}
