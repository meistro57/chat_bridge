<?php

namespace App\Services\AI\Drivers;

use App\Services\AI\Contracts\AIDriverInterface;
use App\Services\AI\Data\AIResponse;
use Illuminate\Support\Collection;

class MockDriver implements AIDriverInterface
{
    public function chat(Collection $messages, float $temperature = 0.7): AIResponse
    {
        return new AIResponse(
            content: 'This is a mock response from the system. API keys are currently not configured.',
            totalTokens: 20
        );
    }

    public function streamChat(Collection $messages, float $temperature = 0.7): iterable
    {
        $words = explode(' ', 'BEEP BOOP. This is a real-time simulated response from the Bridge Network. It appears your API credentials are not yet synchronized with the mainframe. Please check your .env configuration to enable live neural link.');

        foreach ($words as $word) {
            yield $word.' ';
            usleep(100000); // 100ms
        }
    }

    public function getLastTokenUsage(): ?int
    {
        return 20; // Mock token usage
    }

    public function chatWithTools(Collection $messages, Collection $tools, float $temperature = 0.7): array
    {
        throw new \Exception(get_class($this).' does not support tool calling yet');
    }

    public function supportsTools(): bool
    {
        return false;
    }
}
