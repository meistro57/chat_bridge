<?php

namespace App\Services\AI\Data;

class MessageData
{
    public function __construct(
        public readonly string $role,
        public readonly string $content,
        public readonly ?string $name = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            role: $data['role'],
            content: $data['content'],
            name: $data['name'] ?? null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'role' => $this->role,
            'content' => $this->content,
            'name' => $this->name,
        ]);
    }
}
