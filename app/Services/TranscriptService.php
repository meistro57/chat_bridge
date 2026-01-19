<?php

namespace App\Services;

use App\Models\Conversation;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TranscriptService
{
    public function generateMarkdown(Conversation $conversation): string
    {
        $conversation->load(['messages.persona']);

        $md = '# Chat Bridge Session: '.($conversation->metadata['session_id'] ?? $conversation->id)."\n\n";
        $md .= "## Configuration\n";
        $md .= "- **Start Time**: {$conversation->created_at}\n";
        $md .= "- **Provider A**: {$conversation->provider_a} ({$conversation->model_a})\n";
        $md .= "- **Provider B**: {$conversation->provider_b} ({$conversation->model_b})\n";
        $md .= "- **Starter Message**: {$conversation->starter_message}\n\n";

        $md .= "## Conversation History\n\n";

        foreach ($conversation->messages as $index => $message) {
            $role = Str::headline($message->role);
            $name = $message->persona?->name ?? ($message->role === 'user' ? 'Human' : 'Assistant');

            $md .= '### Round '.floor(($index + 1) / 2)." - $name\n";
            $md .= "{$message->content}\n\n";
            $md .= "---\n\n";
        }

        return $md;
    }

    public function saveToFile(Conversation $conversation): string
    {
        $content = $this->generateMarkdown($conversation);
        $filename = "transcripts/transcript_{$conversation->id}_".now()->format('Ymd_His').'.md';

        Storage::disk('local')->put($filename, $content);

        return storage_path("app/$filename");
    }
}
