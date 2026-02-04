<?php

namespace App\Services\AI;

use App\Models\Conversation;
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

        $md = "# Conversation Transcript\n\n";
        $md .= "ID: {$conversation->id}\n";
        $md .= "Date: {$conversation->created_at}\n";
        $md .= "Agents: {$conversation->provider_a} ({$personaAName}) vs {$conversation->provider_b} ({$personaBName})\n";
        $md .= "Starter: {$conversation->starter_message}\n\n";
        $md .= "---\n\n";

        // Group consecutive duplicate messages and track turn numbers
        $turnNumber = 0;
        $lastContent = null;
        $duplicateCount = 0;

        foreach ($conversation->messages as $msg) {
            // Determine which agent this is
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

            // Check for duplicate content
            if ($msg->content === $lastContent) {
                $duplicateCount++;
                continue; // Skip duplicate messages
            }

            // If we had duplicates, note them
            if ($duplicateCount > 0) {
                $md .= "_[Previous message repeated {$duplicateCount} more time(s)]_\n\n";
                $duplicateCount = 0;
            }

            if ($msg->role !== 'user') {
                $turnNumber++;
            }

            $md .= "### Turn {$turnNumber} - {$role}\n";
            $md .= "{$msg->content}\n\n";

            $lastContent = $msg->content;
        }

        // Handle any trailing duplicates
        if ($duplicateCount > 0) {
            $md .= "_[Previous message repeated {$duplicateCount} more time(s)]_\n\n";
        }

        $filename = 'transcripts/'.Str::slug($conversation->id).'.md';
        Storage::disk('local')->put($filename, $md);

        return $filename;
    }
}
