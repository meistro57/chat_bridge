<?php

namespace App\Actions\Chat;

use App\Http\Requests\StoreChatRequest;
use App\Models\Conversation;
use App\Models\ConversationTemplate;
use App\Models\Persona;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CreateConversationAction
{
    public function execute(StoreChatRequest $request): Conversation
    {
        $validated = $request->validated();

        $selectedTemplate = $this->resolveTemplate($validated, $request);
        $personaA = Persona::findOrFail($validated['persona_a_id']);
        $personaB = Persona::findOrFail($validated['persona_b_id']);

        Log::info('Creating new conversation', [
            'user_id' => $request->user()->id,
            'persona_a' => $personaA->name,
            'persona_b' => $personaB->name,
            'provider_a' => $validated['provider_a'],
            'provider_b' => $validated['provider_b'],
            'request_id' => $request->header('X-Request-ID'),
        ]);

        $conversation = DB::transaction(function () use ($request, $validated, $selectedTemplate, $personaA, $personaB): Conversation {
            $conversation = $request->user()->conversations()->create([
                'persona_a_id' => $personaA->id,
                'persona_b_id' => $personaB->id,
                'provider_a' => $validated['provider_a'],
                'provider_b' => $validated['provider_b'],
                'model_a' => $validated['model_a'],
                'model_b' => $validated['model_b'],
                'temp_a' => 1.0,
                'temp_b' => 1.0,
                'starter_message' => $validated['starter_message'],
                'status' => 'active',
                'max_rounds' => $validated['max_rounds'],
                'stop_word_detection' => $validated['stop_word_detection'] ?? false,
                'stop_words' => $validated['stop_words'] ?? [],
                'stop_word_threshold' => $validated['stop_word_threshold'] ?? 0.8,
                'metadata' => [
                    'persona_a_name' => $personaA->name,
                    'persona_b_name' => $personaB->name,
                    'notifications_enabled' => $request->boolean('notifications_enabled', false),
                    'template_id' => $selectedTemplate?->id,
                    'memory' => [
                        'history_limit' => (int) ($validated['memory_history_limit'] ?? 10),
                    ],
                    'rag' => [
                        'enabled' => (bool) ($validated['memory_rag_enabled'] ?? ($selectedTemplate?->rag_enabled ?? true)),
                        'source_limit' => (int) ($validated['memory_rag_source_limit'] ?? ($selectedTemplate?->rag_source_limit ?? 6)),
                        'score_threshold' => (float) ($validated['memory_rag_score_threshold'] ?? ($selectedTemplate?->rag_score_threshold ?? 0.3)),
                        'system_prompt' => (string) ($selectedTemplate?->rag_system_prompt ?? ''),
                        'files' => $selectedTemplate?->rag_files ?? [],
                    ],
                ],
                'discord_streaming_enabled' => $request->has('discord_streaming_enabled')
                    ? $request->boolean('discord_streaming_enabled')
                    : (bool) $request->user()->discord_streaming_default,
                'discord_webhook_url' => $validated['discord_webhook_url'] ?? null,
                'discourse_streaming_enabled' => $request->has('discourse_streaming_enabled')
                    ? $request->boolean('discourse_streaming_enabled')
                    : (bool) $request->user()->discourse_streaming_default,
                'discourse_topic_id' => $validated['discourse_topic_id'] ?? null,
            ]);

            $sessionFiles = $request->file('rag_session_files', []);
            if ($sessionFiles !== []) {
                $storedPaths = $this->storeSessionRagFiles($conversation, $sessionFiles);
                $metadata = $conversation->metadata;
                $metadata['rag']['files'] = array_merge($metadata['rag']['files'] ?? [], $storedPaths);
                $conversation->update(['metadata' => $metadata]);
            }

            $conversation->messages()->create([
                'user_id' => $request->user()->id,
                'role' => 'user',
                'content' => $validated['starter_message'],
            ]);

            return $conversation;
        });

        Log::info('Conversation created successfully', [
            'conversation_id' => $conversation->id,
            'starter_message_length' => strlen($validated['starter_message']),
            'request_id' => $request->header('X-Request-ID'),
        ]);

        return $conversation;
    }

    private function resolveTemplate(array $validated, StoreChatRequest $request): ?ConversationTemplate
    {
        if (empty($validated['template_id'])) {
            return null;
        }

        return ConversationTemplate::query()
            ->where('id', $validated['template_id'])
            ->where(function ($query) use ($request): void {
                $query->where('is_public', true)
                    ->orWhere('user_id', $request->user()->id);
            })
            ->first();
    }

    /**
     * @param  array<int, \Illuminate\Http\UploadedFile>  $files
     * @return array<int, string>
     */
    private function storeSessionRagFiles(Conversation $conversation, array $files): array
    {
        $paths = [];

        foreach ($files as $file) {
            $safeFilename = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
            $filename = trim($safeFilename) !== '' ? $safeFilename : 'rag-document';
            $storedPath = $file->storeAs(
                "session-rag/{$conversation->user_id}/{$conversation->id}",
                $filename.'-'.Str::uuid().'.'.$file->getClientOriginalExtension()
            );

            if ($storedPath !== false) {
                $paths[] = $storedPath;
            }
        }

        return $paths;
    }
}
