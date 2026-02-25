<?php

namespace App\Services\Discord;

use App\Models\Conversation;
use App\Models\Message;

class DiscordEmbedBuilder
{
    /**
     * Build the "conversation started" embed posted when a thread is created.
     *
     * @return array<string, mixed>
     */
    public function conversationStarted(Conversation $conversation): array
    {
        $personaA = $conversation->personaA?->name ?? 'Agent A';
        $personaB = $conversation->personaB?->name ?? 'Agent B';

        $starterPreview = mb_strlen($conversation->starter_message) > 300
            ? mb_substr($conversation->starter_message, 0, 300).'…'
            : $conversation->starter_message;

        return [
            'embeds' => [
                [
                    'title' => '🚀 New Conversation Started',
                    'color' => config('discord.embed_colors.system'),
                    'fields' => [
                        [
                            'name' => '🎭 Agent A',
                            'value' => "**{$personaA}**\n{$conversation->provider_a} · `{$conversation->model_a}`",
                            'inline' => true,
                        ],
                        [
                            'name' => '🎭 Agent B',
                            'value' => "**{$personaB}**\n{$conversation->provider_b} · `{$conversation->model_b}`",
                            'inline' => true,
                        ],
                        [
                            'name' => '⚙️ Settings',
                            'value' => "Max Rounds: **{$conversation->max_rounds}**\nStop Words: ".($conversation->stop_word_detection ? '✅ Enabled' : '❌ Disabled'),
                            'inline' => true,
                        ],
                        [
                            'name' => '💬 Topic',
                            'value' => "```\n{$starterPreview}\n```",
                            'inline' => false,
                        ],
                    ],
                    'timestamp' => $conversation->created_at?->toIso8601String(),
                    'footer' => [
                        'text' => 'Chat Bridge · Live Stream',
                    ],
                ],
            ],
        ];
    }

    /**
     * Build an embed for a completed agent message.
     *
     * @return array<string, mixed>
     */
    public function agentMessage(Message $message, Conversation $conversation, int $turnNumber): array
    {
        $persona = $message->persona;
        $personaName = $persona?->name ?? 'Unknown';
        $isAgentA = $persona && $conversation->persona_a_id === $persona->id;

        $color = $isAgentA
            ? config('discord.embed_colors.agent_a')
            : config('discord.embed_colors.agent_b');

        $provider = $isAgentA ? $conversation->provider_a : $conversation->provider_b;
        $model = $isAgentA ? $conversation->model_a : $conversation->model_b;
        $agentLabel = $isAgentA ? 'A' : 'B';

        $iconUrl = config("discord.provider_icons.{$provider}");

        $content = $message->content ?? '';
        $maxLen = (int) config('discord.max_embed_description', 3900);
        $parts = $this->splitContent($content, $maxLen);

        $embeds = [];
        $totalParts = count($parts);

        foreach ($parts as $index => $part) {
            $embed = [
                'color' => $color,
                'description' => $part,
                'timestamp' => $message->created_at?->toIso8601String(),
            ];

            // Author only on first part
            if ($index === 0) {
                $embed['author'] = [
                    'name' => "{$personaName} [{$agentLabel}] · {$model}",
                ];

                if ($iconUrl) {
                    $embed['author']['icon_url'] = $iconUrl;
                }
            }

            // Footer only on last part
            if ($index === $totalParts - 1) {
                $tokens = $message->tokens_used ?? 0;
                $footerParts = ["Turn {$turnNumber}/{$conversation->max_rounds}"];

                if ($tokens > 0) {
                    $footerParts[] = number_format($tokens).' tokens';
                }

                if ($totalParts > 1) {
                    $footerParts[] = 'Part '.($index + 1)."/{$totalParts}";
                }

                $embed['footer'] = ['text' => implode(' · ', $footerParts)];
            } elseif ($totalParts > 1) {
                $embed['footer'] = ['text' => 'Part '.($index + 1)."/{$totalParts}"];
            }

            $embeds[] = $embed;
        }

        return ['embeds' => $embeds];
    }

    /**
     * Build the "conversation completed" embed.
     *
     * @return array<string, mixed>
     */
    public function conversationCompleted(Conversation $conversation, int $totalMessages, int $totalRounds, float $durationSeconds): array
    {
        $duration = $this->formatDuration($durationSeconds);

        return [
            'embeds' => [
                [
                    'title' => '✅ Conversation Completed',
                    'color' => config('discord.embed_colors.system'),
                    'fields' => [
                        [
                            'name' => '⏱️ Duration',
                            'value' => $duration,
                            'inline' => true,
                        ],
                        [
                            'name' => '🔄 Rounds',
                            'value' => (string) $totalRounds,
                            'inline' => true,
                        ],
                        [
                            'name' => '💬 Messages',
                            'value' => (string) $totalMessages,
                            'inline' => true,
                        ],
                    ],
                    'timestamp' => now()->toIso8601String(),
                    'footer' => [
                        'text' => 'Chat Bridge · Stream Ended',
                    ],
                ],
            ],
        ];
    }

    /**
     * Build the "conversation failed" embed.
     *
     * @return array<string, mixed>
     */
    public function conversationFailed(Conversation $conversation, string $error): array
    {
        $errorPreview = mb_strlen($error) > 200
            ? mb_substr($error, 0, 200).'…'
            : $error;

        $messageCount = $conversation->messages()->count();

        return [
            'embeds' => [
                [
                    'title' => '❌ Conversation Failed',
                    'color' => config('discord.embed_colors.error'),
                    'fields' => [
                        [
                            'name' => 'Error',
                            'value' => "```\n{$errorPreview}\n```",
                            'inline' => false,
                        ],
                        [
                            'name' => 'Messages Before Failure',
                            'value' => (string) $messageCount,
                            'inline' => true,
                        ],
                    ],
                    'timestamp' => now()->toIso8601String(),
                    'footer' => [
                        'text' => 'Chat Bridge · Stream Ended',
                    ],
                ],
            ],
        ];
    }

    /**
     * Build the thread name for a new conversation.
     */
    public function threadName(Conversation $conversation): string
    {
        $personaA = $conversation->personaA?->name ?? 'Agent A';
        $personaB = $conversation->personaB?->name ?? 'Agent B';

        $topic = mb_strlen($conversation->starter_message) > 60
            ? mb_substr($conversation->starter_message, 0, 60).'…'
            : $conversation->starter_message;

        // Remove newlines for thread title
        $topic = str_replace(["\r\n", "\r", "\n"], ' ', $topic);

        $name = "🤖 {$personaA} vs {$personaB} — {$topic}";

        // Discord thread names max 100 characters
        if (mb_strlen($name) > 100) {
            $name = mb_substr($name, 0, 97).'…';
        }

        return $name;
    }

    /**
     * Split long content into parts that fit within Discord's embed description limit.
     *
     * @return array<int, string>
     */
    protected function splitContent(string $content, int $maxLength): array
    {
        if (mb_strlen($content) <= $maxLength) {
            return [$content];
        }

        $parts = [];
        $remaining = $content;

        while (mb_strlen($remaining) > 0) {
            if (mb_strlen($remaining) <= $maxLength) {
                $parts[] = $remaining;
                break;
            }

            // Try to break at a paragraph boundary
            $chunk = mb_substr($remaining, 0, $maxLength);
            $breakPos = mb_strrpos($chunk, "\n\n");

            // Fall back to sentence boundary
            if ($breakPos === false || $breakPos < $maxLength * 0.5) {
                $breakPos = mb_strrpos($chunk, '. ');
            }

            // Fall back to word boundary
            if ($breakPos === false || $breakPos < $maxLength * 0.5) {
                $breakPos = mb_strrpos($chunk, ' ');
            }

            // Last resort: hard cut
            if ($breakPos === false || $breakPos < $maxLength * 0.3) {
                $breakPos = $maxLength;
            }

            $parts[] = mb_substr($remaining, 0, $breakPos);
            $remaining = ltrim(mb_substr($remaining, $breakPos));
        }

        return $parts;
    }

    /**
     * Format seconds into a human-readable duration string.
     */
    protected function formatDuration(float $seconds): string
    {
        $minutes = (int) floor($seconds / 60);
        $secs = (int) ($seconds % 60);

        if ($minutes > 0) {
            return "{$minutes}m {$secs}s";
        }

        return "{$secs}s";
    }
}
