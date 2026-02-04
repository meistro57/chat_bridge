<?php

namespace App\Notifications;

use App\Models\Conversation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ConversationFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Conversation $conversation,
        public string $errorMessage
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $personaA = $this->conversation->personaA?->name ?? 'Persona A';
        $personaB = $this->conversation->personaB?->name ?? 'Persona B';

        return (new MailMessage)
            ->subject('Conversation Failed - Chat Bridge')
            ->greeting("Hello {$notifiable->name},")
            ->line("Your conversation between **{$personaA}** and **{$personaB}** has encountered an error.")
            ->line("Error: {$this->errorMessage}")
            ->action('View Conversation', url("/chat/{$this->conversation->id}"))
            ->line('You can try starting a new conversation or reviewing the existing messages.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'conversation_id' => $this->conversation->id,
            'error' => $this->errorMessage,
            'status' => 'failed',
        ];
    }
}
