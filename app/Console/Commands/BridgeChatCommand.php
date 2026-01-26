<?php

namespace App\Console\Commands;

use App\Events\MessageChunkSent;
use App\Events\MessageCompleted;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Persona;
use App\Services\AI\AIManager;
use App\Services\AI\Data\MessageData;
use App\Services\AI\EmbeddingService;
use App\Services\AI\StopWordService;
use App\Services\AI\TranscriptService;
use Illuminate\Console\Command;

use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class BridgeChatCommand extends Command
{
    protected $signature = 'bridge:chat {--max-rounds=10}';

    protected $description = 'Start an AI vs AI conversation bridge';

    public function handle(AIManager $ai, StopWordService $stopWords, TranscriptService $transcripts, EmbeddingService $embeddings): int
    {
        info('🌉 Welcome to Chat Bridge (Laravel Edition)');

        $personaA = $this->selectPersona('Select Persona for Agent A');
        $personaB = $this->selectPersona('Select Persona for Agent B');
        $provider = config('ai.default', 'openai');

        $starter = text(
            label: 'Conversation Starter',
            placeholder: 'e.g., What is the future of AI?',
            required: true
        );

        $conversation = Conversation::create([
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
            'provider_a' => $provider,
            'provider_b' => $provider,
            'model_a' => null,
            'model_b' => null,
            'temp_a' => $personaA->temperature,
            'temp_b' => $personaB->temperature,
            'starter_message' => $starter,
            'status' => 'active',
            'max_rounds' => (int) $this->option('max-rounds'),
            'stop_word_detection' => false,
            'stop_words' => [],
            'stop_word_threshold' => 0.8,
        ]);

        $history = collect([
            new MessageData('user', $starter),
        ]);

        // Save starter message
        $conversation->messages()->create([
            'role' => 'user',
            'content' => $starter,
        ]);

        $currentPersona = $personaA;
        $currentId = 'a';

        for ($round = 1; $round <= $this->option('max-rounds'); $round++) {
            $this->newLine();
            info("Round $round: {$currentPersona->name} is thinking...");

            $settings = $conversation->settingsForPersona($currentPersona);
            $driver = $ai->driverForProvider($settings['provider'], $settings['model']);
            $fullResponse = '';

            $messages = collect();
            $messages->push(new MessageData('system', $currentPersona->system_prompt));
            foreach ($currentPersona->guidelines ?? [] as $guideline) {
                $messages->push(new MessageData('system', "Guideline: $guideline"));
            }
            $messages = $messages->concat($history->take(-10));

            try {
                $this->output->write("<options=bold;fg=green>{$currentPersona->name}:</> ");

                foreach ($driver->streamChat($messages, $settings['temperature']) as $chunk) {
                    $this->output->write($chunk);
                    $fullResponse .= $chunk;

                    broadcast(new MessageChunkSent(
                        conversationId: $conversation->id,
                        chunk: $chunk,
                        role: 'assistant',
                        personaName: $currentPersona->name
                    ));
                }

                $this->newLine();

            } catch (\Exception $e) {
                $this->newLine();
                $this->error('Error: '.$e->getMessage());
                break;
            }

            // Save message
            $message = $conversation->messages()->create([
                'persona_id' => $currentPersona->id,
                'role' => 'assistant',
                'content' => $fullResponse,
            ]);

            // Async/Background task would be better, but implementing inline for now
            try {
                $vector = $embeddings->getEmbedding($fullResponse);
                $message->update(['embedding' => $vector]);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('Embedding generation failed: '.$e->getMessage());
            }

            broadcast(new MessageCompleted($message));

            $history->push(new MessageData('assistant', $fullResponse));

            if ($stopWords->shouldStop($fullResponse)) {
                $this->newLine();
                $this->warn('🛑 Stop word detected. Terminating conversation early.');
                break;
            }

            // Switch agent
            $currentPersona = ($currentId === 'a') ? $personaB : $personaA;
            $currentId = ($currentId === 'a') ? 'b' : 'a';
        }

        $this->newLine();
        info('✅ Conversation completed.');
        $transcripts->generate($conversation);
        info('📄 Transcript generated.');

        return 0;
    }

    protected function selectPersona(string $label): Persona
    {
        $personas = Persona::all();
        $options = $personas->pluck('name', 'id')->toArray();

        $id = select(
            label: $label,
            options: $options
        );

        return Persona::find($id);
    }
}
