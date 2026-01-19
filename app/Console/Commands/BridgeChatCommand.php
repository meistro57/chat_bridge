<?php

namespace App\Console\Commands;

use App\Events\MessageChunkSent;
use App\Events\MessageCompleted;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Persona;
use App\Services\AI\AIManager;
use App\Services\AI\Data\MessageData;
use App\Services\AI\StopWordService;
use App\Services\AI\TranscriptService;
use Illuminate\Console\Command;
use InvalidArgumentException;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;
use function Laravel\Prompts\info;
use function Laravel\Prompts\spin;

use App\Services\AI\EmbeddingService;

class BridgeChatCommand extends Command
{
    protected $signature = 'bridge:chat {--max-rounds=10} {--persona-a=} {--persona-b=} {--starter=}';
    protected $description = 'Start an AI vs AI conversation bridge';

    public function handle(AIManager $ai, StopWordService $stopWords, TranscriptService $transcripts, EmbeddingService $embeddings)
    {
        info('🌉 Welcome to Chat Bridge (Laravel Edition)');

        $personaA = $this->resolvePersona('Select Persona for Agent A', $this->option('persona-a'));
        $personaB = $this->resolvePersona('Select Persona for Agent B', $this->option('persona-b'));

        $starter = $this->option('starter') ?? text(
            label: 'Conversation Starter',
            placeholder: 'e.g., What is the future of AI?',
            required: true
        );

        $conversation = Conversation::create([
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
            'provider_a' => $personaA->provider,
            'provider_b' => $personaB->provider,
            'model_a' => $personaA->model,
            'model_b' => $personaB->model,
            'temp_a' => $personaA->temperature,
            'temp_b' => $personaB->temperature,
            'starter_message' => $starter,
            'status' => 'active',
        ]);

        $history = collect([
            new MessageData('user', $starter)
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

            $driver = $ai->driver($currentPersona->provider);
            $fullResponse = '';
            
            $messages = collect();
            $messages->push(new MessageData('system', $currentPersona->system_prompt));
            foreach ($currentPersona->guidelines ?? [] as $guideline) {
                $messages->push(new MessageData('system', "Guideline: $guideline"));
            }
            $messages = $messages->concat($history->take(-10));

            try {
                $this->output->write("<options=bold;fg=green>{$currentPersona->name}:</> ");
                
                foreach ($driver->streamChat($messages, $currentPersona->temperature) as $chunk) {
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
                $this->error("Error: " . $e->getMessage());
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
                \Illuminate\Support\Facades\Log::warning("Embedding generation failed: " . $e->getMessage());
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
    }

    protected function resolvePersona(string $label, ?string $optionId): Persona
    {
        if ($optionId) {
            $persona = Persona::find($optionId);

            if (! $persona) {
                throw new InvalidArgumentException("Persona [{$optionId}] not found.");
            }

            return $persona;
        }

        return $this->selectPersona($label);
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
