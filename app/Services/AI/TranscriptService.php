<?php

namespace App\Services\AI;

use App\Models\Conversation;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TranscriptService
{
    public function generate(Conversation $conversation): string
    {
        $conversation->load('messages.persona');
        
        $md = "# Conversation Transcript\n\n";
        $md .= "ID: {$conversation->id}\n";
        $md .= "Date: {$conversation->created_at}\n";
        $md .= "Agents: {$conversation->provider_a} vs {$conversation->provider_b}\n";
        $md .= "Starter: {$conversation->starter_message}\n\n";
        $md .= "---\n\n";

        foreach ($conversation->messages as $index => $msg) {
            $role = $msg->persona ? $msg->persona->name : ($msg->role === 'user' ? 'Starter' : 'Agent');
            $md .= "### Round " . (int)($index / 2 + 1) . " - {$role}\n";
            $md .= "{$msg->content}\n\n";
        }

        $filename = "transcripts/" . Str::slug($conversation->id) . ".md";
        Storage::disk('local')->put($filename, $md);

        return $filename;
    }
}
