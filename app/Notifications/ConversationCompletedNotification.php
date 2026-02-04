<?php

namespace App\Notifications;

use App\Models\Conversation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ConversationCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Conversation $conversation,
        public int $totalMessages,
        public int $totalRounds
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
            ->subject('Conversation Completed - Chat Bridge')
            ->greeting("Hello {$notifiable->name}!")
            ->line("Your conversation between **{$personaA}** and **{$personaB}** has completed successfully.")
            ->line("**{$this->totalRounds}** rounds were completed with **{$this->totalMessages}** total messages.")
            ->action('View Conversation', url("/chat/{$this->conversation->id}"))
            ->line('Thank you for using Chat Bridge!');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'conversation_id' => $this->conversation->id,
            'total_messages' => $this->totalMessages,
            'total_rounds' => $this->totalRounds,
            'status' => 'completed',
        ];
    }
}
