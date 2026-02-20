<?php

namespace App\Console\Commands;

use App\Models\Message;
use Illuminate\Console\Command;

class EstimateMessageTokens extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'messages:estimate-tokens {--dry-run : Show what would be updated without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Estimate token counts for messages that have NULL tokens_used';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $this->info('Finding messages with NULL tokens_used...');

        $messages = Message::whereNull('tokens_used')
            ->where('role', 'assistant')
            ->get();

        if ($messages->isEmpty()) {
            $this->info('No messages found with NULL tokens. All messages already have token counts!');

            return Command::SUCCESS;
        }

        $this->info("Found {$messages->count()} messages without token counts.");

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        $bar = $this->output->createProgressBar($messages->count());
        $bar->start();

        $updated = 0;

        foreach ($messages as $message) {
            // Estimate tokens using character count / 4
            // This is a rough approximation (OpenAI uses ~1 token per 4 characters)
            $estimatedTokens = (int) ceil(strlen($message->content) / 4);

            if (! $dryRun) {
                $message->update(['tokens_used' => $estimatedTokens]);
                $updated++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        if ($dryRun) {
            $this->info("Would update {$messages->count()} messages with estimated token counts.");
            $this->info('Run without --dry-run to apply changes.');
        } else {
            $this->info("✓ Successfully updated {$updated} messages with estimated token counts.");
        }

        return Command::SUCCESS;
    }
}
