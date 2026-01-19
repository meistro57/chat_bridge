<?php

namespace App\Console\Commands;

use App\Models\Message;
use App\Services\AI\EmbeddingService;
use Illuminate\Console\Command;

class GenerateEmbeddings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'embeddings:generate {--limit= : Limit number of messages to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate embeddings for messages that don\'t have them';

    /**
     * Execute the console command.
     */
    public function handle(EmbeddingService $embeddingService): int
    {
        $this->info('Generating embeddings for messages...');

        $query = Message::whereNull('embedding');

        if ($this->option('limit')) {
            $query->limit((int) $this->option('limit'));
        }

        $messagesWithoutEmbeddings = $query->count();

        if ($messagesWithoutEmbeddings === 0) {
            $this->info('All messages already have embeddings!');
            return self::SUCCESS;
        }

        $this->info("Found {$messagesWithoutEmbeddings} messages without embeddings.");

        $confirm = $this->confirm('Do you want to generate embeddings for these messages?', true);

        if (!$confirm) {
            $this->info('Operation cancelled.');
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($messagesWithoutEmbeddings);
        $bar->start();

        $generated = 0;
        $failed = 0;

        $query->chunk(50, function ($messages) use ($embeddingService, $bar, &$generated, &$failed) {
            foreach ($messages as $message) {
                try {
                    $embedding = $embeddingService->getEmbedding($message->content);

                    if ($embedding) {
                        $message->update(['embedding' => $embedding]);
                        $generated++;
                    } else {
                        $failed++;
                    }
                } catch (\Exception $e) {
                    $this->newLine();
                    $this->error("Failed to generate embedding for message {$message->id}: {$e->getMessage()}");
                    $failed++;
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info("✓ Embedding generation complete!");
        $this->info("  - Generated: {$generated}");
        if ($failed > 0) {
            $this->warn("  - Failed: {$failed}");
        }

        $this->newLine();
        $this->info('You can now sync these embeddings to Qdrant using: php artisan qdrant:init --sync');

        return self::SUCCESS;
    }
}
