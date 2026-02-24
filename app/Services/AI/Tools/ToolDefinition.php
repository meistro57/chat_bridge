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
        $parameters = $this->normalizeSchema($this->parameters);

        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name,
                'description' => $this->description,
                'parameters' => $parameters,
            ],
        ];
    }

    public function toAnthropicSchema(): array
    {
        $parameters = $this->normalizeSchema($this->parameters);

        return [
            'name' => $this->name,
            'description' => $this->description,
            'input_schema' => $parameters,
        ];
    }

    public function toGeminiSchema(): array
    {
        $parameters = $this->normalizeSchema($this->parameters);

        return [
            'name' => $this->name,
            'description' => $this->description,
            'parameters' => $parameters,
        ];
    }

    public function execute(array $arguments): mixed
    {
        return ($this->executor)($arguments);
    }

    private function normalizeSchema(array $schema): array
    {
        if (array_key_exists('properties', $schema) && $schema['properties'] === []) {
            $schema['properties'] = (object) [];
        }

        return $schema;
    }
}
