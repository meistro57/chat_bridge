<?php

namespace App\Console\Commands;

use App\Models\Message;
use App\Services\RagService;
use Illuminate\Console\Command;

class InitializeQdrant extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'qdrant:init {--sync : Sync existing messages to Qdrant}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Initialize Qdrant vector database and optionally sync existing messages';

    /**
     * Execute the console command.
     */
    public function handle(RagService $rag): int
    {
        $this->info('Initializing Qdrant vector database...');

        // Check if Qdrant service is reachable (does not require the collection to exist yet)
        if (! $rag->ping()) {
            $this->error('Qdrant is not reachable. Please check your connection settings.');
            $this->info('Host: '.config('services.qdrant.host'));
            $this->info('Port: '.config('services.qdrant.port'));

            return self::FAILURE;
        }

        $this->info('Qdrant is reachable!');

        // Initialize collection
        $this->info('Creating collection if it doesn\'t exist...');
        if (! $rag->initializeCollection()) {
            $this->error('Failed to initialize Qdrant collection.');

            return self::FAILURE;
        }

        $this->info('✓ Qdrant collection initialized successfully!');

        // Sync existing messages if requested
        if ($this->option('sync')) {
            $this->info('');
            $this->info('Syncing existing messages to Qdrant...');

            $messagesWithEmbeddings = Message::whereNotNull('embedding')->count();
            $totalMessages = Message::count();

            $this->info("Found {$totalMessages} total messages, {$messagesWithEmbeddings} with embeddings.");

            if ($messagesWithEmbeddings === 0) {
                $this->warn('No messages with embeddings found. Generate embeddings first using: php artisan embeddings:generate');

                return self::SUCCESS;
            }

            $bar = $this->output->createProgressBar($messagesWithEmbeddings);
            $bar->start();

            $stored = 0;
            $failed = 0;

            Message::whereNotNull('embedding')
                ->with('conversation', 'persona')
                ->chunk(100, function ($messages) use ($rag, $bar, &$stored, &$failed) {
                    foreach ($messages as $message) {
                        if ($rag->storeMessage($message)) {
                            $stored++;
                        } else {
                            $failed++;
                        }
                        $bar->advance();
                    }
                });

            $bar->finish();
            $this->newLine(2);

            $this->info('✓ Sync complete!');
            $this->info("  - Stored: {$stored}");
            if ($failed > 0) {
                $this->warn("  - Failed: {$failed}");
            }
        }

        $this->newLine();
        $this->info('Qdrant initialization complete!');

        return self::SUCCESS;
    }
}
