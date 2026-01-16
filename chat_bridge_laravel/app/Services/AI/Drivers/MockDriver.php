<?php

namespace App\Services\AI\Drivers;

use App\Services\AI\Contracts\AIDriverInterface;
use Illuminate\Support\Collection;

class MockDriver implements AIDriverInterface
{
    public function chat(Collection $messages, float $temperature = 0.7): string
    {
        return "This is a mock response from the system. API keys are currently not configured.";
    }

    public function streamChat(Collection $messages, float $temperature = 0.7): iterable
    {
        $words = explode(' ', "BEEP BOOP. This is a real-time simulated response from the Bridge Network. It appears your API credentials are not yet synchronized with the mainframe. Please check your .env configuration to enable live neural link.");
        
        foreach ($words as $word) {
            yield $word . ' ';
            usleep(100000); // 100ms
        }
    }
}
