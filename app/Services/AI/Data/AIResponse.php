<?php

namespace App\Services\AI\Data;

class AIResponse
{
    public function __construct(
        public readonly string $content,
        public readonly ?int $promptTokens = null,
        public readonly ?int $completionTokens = null,
        public readonly ?int $totalTokens = null
    ) {}

    /**
     * Get total tokens used (calculated or provided)
     */
    public function getTokensUsed(): ?int
    {
        if ($this->totalTokens !== null) {
            return $this->totalTokens;
        }

        if ($this->promptTokens !== null && $this->completionTokens !== null) {
            return $this->promptTokens + $this->completionTokens;
        }

        return null;
    }
}
