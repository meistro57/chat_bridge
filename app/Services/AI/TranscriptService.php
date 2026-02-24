<?php

namespace App\Services\AI;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TranscriptService
{
    public function generate(Conversation $conversation): string
    {
        $conversation->load([
            'messages' => fn ($q) => $q->orderBy('created_at'),
            'messages.persona',
            'personaA',
            'personaB',
        ]);

        $personaAName = $conversation->personaA->name ?? 'Unknown';
        $personaBName = $conversation->personaB->name ?? 'Unknown';
        $messages = $conversation->messages;
        $assistantMessages = $messages->where('role', 'assistant')->values();
        $starterMessages = $messages->where('role', 'user')->values();
        $totalTokens = (int) $messages->sum(fn (Message $message) => (int) ($message->tokens_used ?? 0));
        $assistantTokens = (int) $assistantMessages->sum(fn (Message $message) => (int) ($message->tokens_used ?? 0));
        $starterTokens = (int) $starterMessages->sum(fn (Message $message) => (int) ($message->tokens_used ?? 0));
        $firstMessageAt = $messages->first()?->created_at;
        $lastMessageAt = $messages->last()?->created_at;
        $durationSeconds = ($firstMessageAt && $lastMessageAt) ? $lastMessageAt->diffInSeconds($firstMessageAt) : 0;
        $status = $conversation->status ?? 'unknown';
        $notificationsEnabled = (bool) data_get($conversation->metadata, 'notifications_enabled', false);
        $stopWords = is_array($conversation->stop_words) ? $conversation->stop_words : [];
        $providerCounts = $this->providerCounts($conversation, $assistantMessages);
        $messageLengthSummary = $this->messageLengthSummary($messages);
        $metadataJson = json_encode($conversation->metadata ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $formattedDuration = gmdate('H:i:s', max(0, $durationSeconds));

        $md = "# Conversation Report\n\n";
        $md .= "## Executive Summary\n\n";
        $md .= "- **Conversation ID**: `{$conversation->id}`\n";
        $md .= "- **Status**: `{$status}`\n";
        $md .= "- **Created At**: {$conversation->created_at}\n";
        $md .= '- **Completed At**: '.($conversation->updated_at ?? 'N/A')."\n";
        $md .= "- **Duration (first-to-last message)**: {$formattedDuration} ({$durationSeconds}s)\n";
        $md .= "- **Configured Max Rounds**: {$conversation->max_rounds}\n";
        $md .= "- **Actual Assistant Turns**: {$assistantMessages->count()}\n";
        $md .= "- **Total Messages**: {$messages->count()}\n";
        $md .= "- **Total Tokens Recorded**: {$totalTokens}\n";
        $md .= '- **Notifications Enabled**: '.($notificationsEnabled ? 'Yes' : 'No')."\n\n";

        $md .= "## Participants\n\n";
        $md .= "| Side | Persona | Provider | Model | Temperature |\n";
        $md .= "|---|---|---|---|---|\n";
        $md .= "| Agent A | {$personaAName} | {$conversation->provider_a} | ".($conversation->model_a ?? 'N/A')." | {$conversation->temp_a} |\n";
        $md .= "| Agent B | {$personaBName} | {$conversation->provider_b} | ".($conversation->model_b ?? 'N/A')." | {$conversation->temp_b} |\n\n";

        $md .= "## Runtime Metrics\n\n";
        $md .= "- **Starter Messages**: {$starterMessages->count()}\n";
        $md .= "- **Assistant Messages**: {$assistantMessages->count()}\n";
        $md .= '- **First Message Timestamp**: '.($firstMessageAt ?? 'N/A')."\n";
        $md .= '- **Last Message Timestamp**: '.($lastMessageAt ?? 'N/A')."\n";
        $md .= "- **Tokens (Starter)**: {$starterTokens}\n";
        $md .= "- **Tokens (Assistant)**: {$assistantTokens}\n";
        $md .= "- **Average Message Length**: {$messageLengthSummary['average']} chars\n";
        $md .= "- **Shortest Message Length**: {$messageLengthSummary['min']} chars\n";
        $md .= "- **Longest Message Length**: {$messageLengthSummary['max']} chars\n\n";

        $md .= "### Provider Message Distribution\n\n";
        $md .= "- **{$conversation->provider_a} ({$personaAName})**: {$providerCounts['a']}\n";
        $md .= "- **{$conversation->provider_b} ({$personaBName})**: {$providerCounts['b']}\n\n";

        $md .= "## Safety and Stop Conditions\n\n";
        $md .= '- **Stop Word Detection**: '.($conversation->stop_word_detection ? 'Enabled' : 'Disabled')."\n";
        $md .= "- **Stop Word Threshold**: {$conversation->stop_word_threshold}\n";
        $md .= '- **Configured Stop Words**: '.($stopWords !== [] ? implode(', ', $stopWords) : 'None')."\n\n";

        $md .= "## Starter Prompt\n\n";
        $md .= $conversation->starter_message."\n\n";

        $md .= "## Conversation Metadata (Raw)\n\n";
        $md .= "```json\n".($metadataJson ?: '{}')."\n```\n\n";
        $md .= "---\n\n";
        $md .= "# Transcript\n\n";

        $turnNumber = 0;

        foreach ($messages as $msg) {
            $isPersonaA = $msg->persona_id === $conversation->persona_a_id;
            $isPersonaB = $msg->persona_id === $conversation->persona_b_id;

            if ($msg->role === 'user') {
                $role = 'Starter';
            } elseif ($isPersonaA) {
                $role = "Agent A: {$personaAName} ({$conversation->provider_a})";
            } elseif ($isPersonaB) {
                $role = "Agent B: {$personaBName} ({$conversation->provider_b})";
            } else {
                $personaName = $msg->persona->name ?? 'Agent';
                $role = $personaName;
            }

            if ($msg->role !== 'user') {
                $turnNumber++;
            }

            $md .= '## Entry '.($msg->id ?? 'N/A')." - Turn {$turnNumber} - {$role}\n";
            $md .= "- Timestamp: {$msg->created_at}\n";
            $md .= "- Role: {$msg->role}\n";
            $md .= '- Persona ID: '.($msg->persona_id ?? 'N/A')."\n";
            $md .= '- Tokens Used: '.($msg->tokens_used ?? 0)."\n";
            $md .= '- Character Count: '.strlen((string) $msg->content)."\n\n";
            $md .= "{$msg->content}\n\n";
            $md .= "---\n\n";
        }

        $filename = 'transcripts/'.Str::slug($conversation->id).'.md';
        Storage::disk('local')->put($filename, $md);

        return $filename;
    }

    /**
     * @param  Collection<int, Message>  $assistantMessages
     * @return array{a:int, b:int}
     */
    private function providerCounts(Conversation $conversation, Collection $assistantMessages): array
    {
        $countA = $assistantMessages->where('persona_id', $conversation->persona_a_id)->count();
        $countB = $assistantMessages->where('persona_id', $conversation->persona_b_id)->count();

        return [
            'a' => $countA,
            'b' => $countB,
        ];
    }

    /**
     * @param  Collection<int, Message>  $messages
     * @return array{min:int, max:int, average:int}
     */
    private function messageLengthSummary(Collection $messages): array
    {
        if ($messages->isEmpty()) {
            return [
                'min' => 0,
                'max' => 0,
                'average' => 0,
            ];
        }

        $lengths = $messages->map(fn (Message $message) => strlen((string) $message->content));

        return [
            'min' => (int) $lengths->min(),
            'max' => (int) $lengths->max(),
            'average' => (int) round($lengths->average()),
        ];
    }
}
