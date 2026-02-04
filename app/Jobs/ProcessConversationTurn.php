<?php

namespace App\Jobs;

use App\Events\MessageChunkSent;
use App\Events\MessageCompleted;
use App\Models\Conversation;
use App\Services\AI\AIManager;
use App\Services\AI\Data\MessageData;
use App\Services\AI\StopWordService;
use App\Services\AI\StreamingChunker;
use App\Services\AI\TranscriptService;
use App\Services\Broadcast\SafeBroadcaster;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessConversationTurn implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $conversationId,
        public int $round = 1,
        public int $maxRounds = 10
    ) {}

    public function handle(AIManager $ai, StopWordService $stopWords, TranscriptService $transcripts): void
    {
        $conversation = Conversation::with(['messages.persona', 'personaA', 'personaB'])->findOrFail($this->conversationId);
        $maxRounds = $this->maxRounds > 0 ? $this->maxRounds : $conversation->max_rounds;

        if ($conversation->status !== 'active' || $this->round > $maxRounds) {
            $conversation->update(['status' => 'completed']);
            $transcripts->generate($conversation);

            return;
        }

        $personaA = $conversation->personaA;
        $personaB = $conversation->personaB;

        if (! $personaA || ! $personaB) {
            // Log error or fallback
            return;
        }

        $lastMessage = $conversation->messages()->whereNotNull('persona_id')->latest()->first();
        $currentPersona = (! $lastMessage || $lastMessage->persona_id === $personaB->id) ? $personaA : $personaB;

        $history = $conversation->messages->map(fn ($m) => new MessageData($m->role, $m->content)
        )->take(-10);

        $settings = $conversation->settingsForPersona($currentPersona);
        $driver = $ai->driverForProvider($settings['provider'], $settings['model']);
        $messages = collect();
        $messages->push(new MessageData('system', $currentPersona->system_prompt));
        foreach ($currentPersona->guidelines ?? [] as $guideline) {
            $messages->push(new MessageData('system', "Guideline: $guideline"));
        }
        $messages = $messages->concat($history);

        $maxChunkSize = (int) config('ai.stream_chunk_size', 1500);
        $chunker = app(StreamingChunker::class);
        $broadcaster = app(SafeBroadcaster::class);
        $startAt = microtime(true);
        $firstChunkAt = null;
        $chunkCount = 0;
        $responseLength = 0;

        \Log::info('Conversation turn started', [
            'conversation_id' => $conversation->id,
            'round' => $this->round,
            'persona' => $currentPersona->name,
            'provider' => $settings['provider'],
            'model' => $settings['model'],
            'temperature' => $settings['temperature'],
            'history_count' => $history->count(),
        ]);

        $fullResponse = '';
        try {
            foreach ($driver->streamChat($messages, $settings['temperature']) as $chunk) {
                if ($firstChunkAt === null) {
                    $firstChunkAt = microtime(true);
                }

                $fullResponse .= $chunk;
                $responseLength += strlen($chunk);

                foreach ($chunker->split($chunk, $maxChunkSize) as $piece) {
                    $chunkCount++;
                    $broadcaster->broadcast(
                        new MessageChunkSent($conversation->id, $piece, 'assistant', $currentPersona->name),
                        [
                            'conversation_id' => $conversation->id,
                            'phase' => 'chunk',
                        ]
                    );
                }
            }
        } catch (\Throwable $exception) {
            \Log::error('Conversation turn failed', [
                'conversation_id' => $conversation->id,
                'round' => $this->round,
                'persona' => $currentPersona->name,
                'provider' => $settings['provider'],
                'model' => $settings['model'],
                'exception' => $exception->getMessage(),
                'exception_class' => $exception::class,
            ]);

            throw $exception;
        }

        $durationMs = (int) round((microtime(true) - $startAt) * 1000);
        $firstChunkMs = $firstChunkAt ? (int) round(($firstChunkAt - $startAt) * 1000) : null;

        if (trim($fullResponse) === '') {
            \Log::warning('Conversation turn empty response', [
                'conversation_id' => $conversation->id,
                'round' => $this->round,
                'persona' => $currentPersona->name,
                'provider' => $settings['provider'],
                'model' => $settings['model'],
                'chunk_count' => $chunkCount,
                'response_length' => $responseLength,
                'duration_ms' => $durationMs,
                'first_chunk_ms' => $firstChunkMs,
            ]);

            $conversation->update(['status' => 'failed']);
            $broadcaster->broadcast(
                new \App\Events\ConversationStatusUpdated($conversation),
                [
                    'conversation_id' => $conversation->id,
                    'phase' => 'status',
                ]
            );

            return;
        }

        \Log::info('Conversation turn completed', [
            'conversation_id' => $conversation->id,
            'round' => $this->round,
            'persona' => $currentPersona->name,
            'provider' => $settings['provider'],
            'model' => $settings['model'],
            'chunk_count' => $chunkCount,
            'response_length' => $responseLength,
            'duration_ms' => $durationMs,
            'first_chunk_ms' => $firstChunkMs,
        ]);

        $message = $conversation->messages()->create([
            'persona_id' => $currentPersona->id,
            'role' => 'assistant',
            'content' => $fullResponse,
        ]);

        $broadcaster->broadcast(
            new MessageCompleted($message),
            [
                'conversation_id' => $conversation->id,
                'phase' => 'completed',
            ]
        );

        if ($conversation->stop_word_detection && $stopWords->shouldStopWithThreshold(
            $fullResponse,
            $conversation->stop_words ?? [],
            (float) $conversation->stop_word_threshold
        )) {
            $conversation->update(['status' => 'completed']);
            $transcripts->generate($conversation);

            return;
        }

        // Schedule next turn
        dispatch(new self($this->conversationId, $this->round + 1, $maxRounds))
            ->delay(now()->addSeconds(2));
    }
}
