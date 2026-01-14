<?php

namespace App\Console\Commands;

use App\Services\AI\AIManager;
use App\Services\AI\Data\MessageData;
use Illuminate\Console\Command;

class AiTestCommand extends Command
{
    protected $signature = 'ai:test {provider?}';
    protected $description = 'Test AI connectivity via Laravel drivers';

    public function handle(AIManager $ai)
    {
        $provider = $this->argument('provider') ?? 'openai';
        
        $this->info("Testing connectivity for provider: {$provider}");
        
        try {
            $driver = $ai->driver($provider);
            
            $messages = collect([
                new MessageData('system', 'You are a connectivity tester. Respond with "CONNECTED" and nothing else.'),
                new MessageData('user', 'Ping'),
            ]);

            $this->output->write("Streaming response: ");
            $fullResponse = '';
            
            foreach ($driver->streamChat($messages, 0.7) as $chunk) {
                $this->output->write($chunk);
                $fullResponse .= $chunk;
            }
            
            $this->newLine();

            if (stripos($fullResponse, 'CONNECTED') !== false) {
                $this->info("✅ SUCCESS: {$provider} is working!");
            } else {
                $this->warn("⚠️  Partial success: Response received but didn't match expected pattern: {$fullResponse}");
            }

        } catch (\Exception $e) {
            $this->error("❌ FAILED: " . $e->getMessage());
        }
    }
}
