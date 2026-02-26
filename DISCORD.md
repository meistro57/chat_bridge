# Discord Live Streaming Guide

Chat Bridge supports real-time streaming of AI-to-AI conversations directly to Discord channels using Webhooks. This allows for a spectator-friendly experience with formatted embeds, provider icons, and automatic threading.

## Quick Start

1.  **Create a Discord Webhook**:
    *   Go to your Discord Server Settings > Integrations > Webhooks.
    *   Create a "New Webhook" and copy the **Webhook URL**.
2.  **Configure Environment**:
    *   Add `DISCORD_WEBHOOK_URL=your_webhook_url` to your `.env` file.
    *   Ensure `DISCORD_STREAMING_ENABLED=true` is set.
3.  **Start a Chat**:
    *   In the Chat Bridge UI, toggle **"Stream to Discord"** to ON when creating a new conversation.

---

## Configuration Reference

Settings are managed in `config/discord.php`. You can override these using environment variables:

| Variable | Description | Default |
| :--- | :--- | :--- |
| `DISCORD_STREAMING_ENABLED` | Global master switch for Discord features. | `true` |
| `DISCORD_WEBHOOK_URL` | System-wide fallback webhook URL. | `null` |
| `DISCORD_THREAD_AUTO_CREATE`| Create a new thread for every conversation. | `true` |
| `DISCORD_MAX_EMBED_DESCRIPTION` | Max characters per embed description (Discord limit is 4096). | `3900` |
| `DISCORD_CIRCUIT_BREAKER_THRESHOLD` | Disable streaming after X consecutive failures. | `5` |

### Embed Colors
Customize the visual identity of your agents:
- `DISCORD_EMBED_COLOR_A`: Color for Persona A (Default: Blue `0x3B82F6`)
- `DISCORD_EMBED_COLOR_B`: Color for Persona B (Default: Purple `0x8B5CF6`)

---

## Technical Features

### 1. Automatic Threading
When `DISCORD_THREAD_AUTO_CREATE` is active, the system first tries to create a unique thread named after the personas and the topic (e.g., `🤖 Claude vs GPT-4 — Quantum Physics`). If the webhook channel does not support thread creation, Chat Bridge automatically falls back to posting directly in the webhook channel.

### 2. Message Order
For each new conversation stream:
- First Discord message: **starter question** (`💬 Starter Question` embed)
- Second Discord message: **conversation metadata** (`🚀 New Conversation Started` embed)
- Then each agent turn streams in order.

### 3. Smart Content Splitting
Discord restricts embed descriptions to 4,096 characters. The `DiscordEmbedBuilder` service automatically:
- Detects long responses.
- Splits them at paragraph or sentence boundaries.
- Posts them as ordered embeds.

### 4. Provider Icons
The system automatically attaches official icons to the "Author" field of the embed based on the AI provider (OpenAI, Anthropic, Gemini, etc.), making it easy to identify which model is speaking at a glance.

### 5. Resiliency & Rate Limiting
- **Rate Limit Handling**: The streamer automatically detects `429 Too Many Requests` responses and retries after the specified delay.
- **Circuit Breaker**: If the webhook fails repeatedly (e.g., invalid URL or Discord outage), it will silently disable streaming for that specific session to ensure the AI conversation itself isn't interrupted.

---

## Troubleshooting

- **Messages aren't appearing**: Check `storage/logs/laravel.log` for "Discord webhook failed" errors. Ensure your Webhook URL is valid and the `DISCORD_STREAMING_ENABLED` flag is true.
- **No threads created**: Ensure the Webhook has "Create Public Threads" permissions in Discord.
- **Queue issues**: Discord streaming happens asynchronously. Ensure your queue worker is running: `php artisan queue:work`.

---
💘 Generated with Crush
