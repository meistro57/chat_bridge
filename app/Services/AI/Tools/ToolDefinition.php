<?php

namespace App\Services\AI\Tools;

class ToolDefinition
{
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly array $parameters,
        public readonly \Closure $executor
    ) {}

    public function toOpenAISchema(): array
    {
        return [
            "type" => "function",
            "function" => [
                "name" => $this->name,
                "description" => $this->description,
                "parameters" => $this->parameters,
            ],
        ];
    }

    public function toAnthropicSchema(): array
    {
        return [
            "name" => $this->name,
            "description" => $this->description,
            "input_schema" => $this->parameters,
        ];
    }

    public function toGeminiSchema(): array
    {
        return [
            "name" => $this->name,
            "description" => $this->description,
            "parameters" => $this->parameters,
        ];
    }

    public function execute(array $arguments): mixed
    {
        return ($this->executor)($arguments);
    }
}
