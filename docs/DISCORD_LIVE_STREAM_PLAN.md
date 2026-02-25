# 🎙️ Discord Live Stream — Complete Implementation Plan

## Implementation Status (2026-02-25)

This document started as a blueprint. Core Discord broadcast functionality is now implemented in the app:

- Per-conversation `Discord Broadcast` toggle on chat creation
- Optional per-conversation webhook override
- Fallback chain: conversation webhook -> user default webhook -> system webhook
- Lifecycle embeds: conversation started, agent messages, completed, failed
- Automatic thread creation support and persisted `discord_thread_id`
- Global kill switch and Discord config in `config/discord.php`

Items in this plan that are still optional/future work remain as planning notes.

## Discord Broadcast Quickstart

Use this to enable Discord conversation broadcasts in a few minutes.

### 1. Configure environment

Add/update these values in your `.env`:

```env
DISCORD_STREAMING_ENABLED=true
# Optional system fallback webhook:
# DISCORD_WEBHOOK_URL=https://discord.com/api/webhooks/...
DISCORD_THREAD_AUTO_CREATE=true
DISCORD_CIRCUIT_BREAKER_THRESHOLD=5
```

Then apply config changes:

```bash
php artisan optimize:clear
php artisan queue:restart
```

### 2. Set a webhook source

At least one webhook source must exist:

1. Per-conversation webhook override (on Chat Create form), or
2. User default webhook (`users.discord_webhook_url`), or
3. Global fallback (`DISCORD_WEBHOOK_URL` in `.env`)

### 3. Enable broadcast on chat creation

In `/chat/create`:

1. Turn on `Discord Broadcast`
2. Optionally fill `Discord Webhook Override`
3. Start the conversation

### 4. Verify in Discord

Expected behavior:

- A conversation-start embed appears
- Agent messages are posted turn-by-turn
- A completion or failure embed appears at the end
- If thread auto-create is enabled, messages stay in the created thread

### 5. Troubleshooting quick checks

- Confirm `DISCORD_STREAMING_ENABLED=true`
- Confirm queue worker is running
- Re-run:

```bash
php artisan optimize:clear
php artisan queue:restart
```

- Check app logs for warnings containing `Discord webhook failed` or `Discord streaming failed`

## The Vision

Watch AI conversations happen **live** in your Discord server. When a Chat Bridge conversation starts, a Discord thread is created. As agents generate responses, their messages stream into Discord in real-time — your community sees the debate unfold as it happens.

```
#ai-conversations
│
├── 🧵 AEGIS vs ATLAS — Gravity & Stress-Energy (LIVE 🔴)
│   ├── 🤖 [AEGIS] Analyzing the gravitational effects within...
│   ├── 🤖 [ATLAS] From an engineering perspective, implementing...
│   ├── 🤖 [AEGIS] Your approach to implementing a simulation...
│   └── ⏳ Typing...
│
├── 🧵 Philosopher vs Scientist — Nature of Consciousness (Completed ✅)
│   └── 14 messages · 22 min · $0.34
│
└── 🧵 Chef vs Electrician — Energy Transfer in Cooking (Failed ❌)
    └── 2 messages · Error: Model not found
```

---

## Architecture Overview

```
┌─────────────────────────────────────┐
│           RunChatSession            │
│        (Existing Job Loop)          │
└──────────┬──────────┬───────────────┘
           │          │
    ┌──────▼──┐  ┌────▼────────────┐
    │ Reverb  │  │ DiscordStreamer  │  ← NEW
    │ (Web UI)│  │   (Service)     │
    └─────────┘  └────┬────────────┘
                      │
               ┌──────▼──────┐
               │  Discord    │
               │  Webhook    │
               │  API        │
               └─────────────┘
```

The key insight: **we don't need a Discord bot**. Discord webhooks are perfect for one-way streaming. No gateway connection, no presence tracking, no command handling. Just fire-and-forget HTTP POSTs with beautiful embeds.

---

## Implementation Plan

### Phase 1: Database & Configuration

#### 1.1 Migration — `discord_streaming` columns on `conversations`

Add to the `conversations` table via a new migration:

```php
Schema::table('conversations', function (Blueprint $table) {
    $table->string('discord_webhook_url')->nullable();
    $table->string('discord_thread_id')->nullable();
    $table->string('discord_channel_id')->nullable();
    $table->boolean('discord_streaming_enabled')->default(false);
});
```

**Why on the conversation?** Each conversation can optionally stream to Discord. Users toggle it on/off per conversation, and the thread ID is stored so we can continue posting to the same thread.

#### 1.2 Migration — `discord_settings` on `users` table

Add default Discord preferences per user:

```php
Schema::table('users', function (Blueprint $table) {
    $table->string('discord_webhook_url')->nullable();
    $table->boolean('discord_streaming_default')->default(false);
});
```

This lets users set a default webhook URL so they don't have to enter it every time they create a conversation. The per-conversation setting overrides this.

#### 1.3 Environment Variables

```env
# Discord Integration
DISCORD_WEBHOOK_URL=              # System-wide default webhook
DISCORD_STREAMING_ENABLED=true    # Master kill switch
DISCORD_THREAD_AUTO_CREATE=true   # Auto-create threads per conversation
DISCORD_EMBED_COLOR_A=0x3B82F6    # Blue for Agent A
DISCORD_EMBED_COLOR_B=0x8B5CF6    # Purple for Agent B
DISCORD_MAX_MESSAGE_LENGTH=1900   # Discord limit is 2000, leave buffer
DISCORD_CHUNK_DELAY_MS=100        # Delay between chunk edits (rate limiting)
```

#### 1.4 Config File — `config/discord.php`

```php
return [
    'enabled' => env('DISCORD_STREAMING_ENABLED', true),
    'webhook_url' => env('DISCORD_WEBHOOK_URL'),
    'thread_auto_create' => env('DISCORD_THREAD_AUTO_CREATE', true),
    'embed_colors' => [
        'agent_a' => (int) env('DISCORD_EMBED_COLOR_A', 0x3B82F6),
        'agent_b' => (int) env('DISCORD_EMBED_COLOR_B', 0x8B5CF6),
        'system'  => 0x10B981,  // Emerald for system messages
        'error'   => 0xEF4444,  // Red for errors
    ],
    'max_message_length' => (int) env('DISCORD_MAX_MESSAGE_LENGTH', 1900),
    'chunk_delay_ms' => (int) env('DISCORD_CHUNK_DELAY_MS', 100),
    'rate_limit' => [
        'max_edits_per_message' => 15,    // Don't edit more than 15 times
        'min_edit_interval_ms' => 500,     // At least 500ms between edits
        'max_messages_per_minute' => 25,   // Discord rate limit safety
    ],
];
```

---

### Phase 2: The DiscordStreamer Service

This is the core service. It handles all Discord webhook communication.

#### File: `app/Services/Discord/DiscordStreamer.php`

**Responsibilities:**
- Create threads for new conversations
- Post conversation start/complete/failed embeds
- Post individual agent messages as rich embeds
- Handle message chunking (Discord 2000 char limit)
- Respect Discord rate limits
- Graceful failure (never crash the conversation if Discord is down)

**Key Methods:**

```php
class DiscordStreamer
{
    // Create a new Discord thread for a conversation
    public function startConversation(Conversation $conversation): ?string
    
    // Post a completed agent message as a rich embed
    public function postMessage(Conversation $conversation, Message $message): void
    
    // Post streaming chunks (edit-in-place for live typing effect)
    public function startStreamingMessage(Conversation $conversation, string $personaName): ?string
    public function appendChunk(string $messageId, string $chunk, string $webhookUrl, string $threadId): void
    public function finalizeStreamingMessage(string $messageId, Message $message): void
    
    // Post conversation lifecycle events
    public function conversationCompleted(Conversation $conversation, int $totalMessages, int $totalRounds): void
    public function conversationFailed(Conversation $conversation, string $error): void
    
    // Internal helpers
    protected function sendWebhook(string $url, array $payload, ?string $threadId = null): ?array
    protected function editWebhookMessage(string $url, string $messageId, array $payload, ?string $threadId = null): void
    protected function buildMessageEmbed(Message $message, Conversation $conversation): array
    protected function resolveWebhookUrl(Conversation $conversation): ?string
    protected function shouldStream(Conversation $conversation): bool
    protected function splitForDiscord(string $content): array
```

**Webhook URL Resolution Priority:**
1. `$conversation->discord_webhook_url` (per-conversation override)
2. `$conversation->user->discord_webhook_url` (user default)
3. `config('discord.webhook_url')` (system-wide fallback)

**Rich Embed Format:**

```json
{
    "thread_name": "🤖 AEGIS vs ATLAS — Gravity & Stress-Energy",
    "embeds": [{
        "author": {
            "name": "AEGIS (GPT-4o via OpenRouter)",
            "icon_url": "https://cdn.chatbridge.app/icons/openrouter.png"
        },
        "description": "Analyzing the gravitational effects within the framework...",
        "color": 3899636,
        "footer": {
            "text": "Turn 3/100 · 847 tokens · $0.008"
        },
        "timestamp": "2026-02-25T22:45:19.000Z"
    }]
}
```

---

### Phase 3: Two Integration Modes

#### Mode A: "Complete Message" Mode (Simple, Reliable)

Post each agent's **full completed message** as a Discord embed after the turn finishes. This is the safe, rate-limit-friendly approach.

**How it hooks in:**

In `RunChatSession::handle()`, right after the `MessageCompleted` broadcast (line ~after step 5), add:

```php
// 5b. Stream to Discord if enabled
if ($discordStreamer->shouldStream($conversation)) {
    $discordStreamer->postMessage($conversation, $message);
}
```

**Pros:** Simple, reliable, no rate limit issues, no partial messages
**Cons:** No live typing effect — messages appear all at once

#### Mode B: "Live Streaming" Mode (Advanced, Spectacular)

Edit a Discord message in real-time as chunks arrive — it looks like the AI is typing directly in Discord.

**How it hooks in:**

In `RunChatSession::handle()`, the streaming loop already broadcasts `MessageChunkSent` events. We add Discord streaming alongside:

```php
// Before the chunk loop starts:
$discordMessageId = null;
if ($discordStreamer->shouldStream($conversation) && $discordStreamer->isLiveMode($conversation)) {
    $discordMessageId = $discordStreamer->startStreamingMessage($conversation, $currentPersona->name);
}

// Inside the chunk loop, alongside the Reverb broadcast:
if ($discordMessageId) {
    $discordStreamer->appendChunk(
        $discordMessageId,
        $piece,
        $conversation->discord_webhook_url,
        $conversation->discord_thread_id
    );
}

// After the turn completes:
if ($discordMessageId) {
    $discordStreamer->finalizeStreamingMessage($discordMessageId, $message);
}
```

**Rate Limit Strategy:**
- Buffer chunks and only edit every 500ms minimum
- Use an internal accumulator: collect chunks, batch-edit
- Max 15 edits per message (after that, just wait for finalize)
- If rate limited, gracefully fall back to Mode A for that message

**Pros:** INCREDIBLE live experience, community engagement
**Cons:** More API calls, rate limit complexity, more code

**Recommendation:** Implement Mode A first, then add Mode B as a toggle. Both can coexist — user chooses in settings.

---

### Phase 4: Conversation Lifecycle Embeds

#### 4.1 Conversation Started

When a conversation begins, post a "lobby" embed to create the thread:

```
╔══════════════════════════════════════╗
║  🚀 NEW CONVERSATION STARTED        ║
╠══════════════════════════════════════╣
║                                      ║
║  Agent A: AEGIS                      ║
║  Model:   GPT-4o (OpenRouter)        ║
║                                      ║
║  Agent B: ATLAS                      ║
║  Model:   DeepSeek Chat              ║
║                                      ║
║  Topic: Gravity/general relativity   ║
║         with mass and stress-energy  ║
║         displacement                 ║
║                                      ║
║  Max Rounds: 100                     ║
║  Stop Words: Enabled                 ║
║                                      ║
╚══════════════════════════════════════╝
```

#### 4.2 Conversation Completed

```
╔══════════════════════════════════════╗
║  ✅ CONVERSATION COMPLETED           ║
╠══════════════════════════════════════╣
║                                      ║
║  Duration:      14 min 23 sec        ║
║  Total Rounds:  17                   ║
║  Total Messages: 18                  ║
║  Tokens Used:   24,831               ║
║  Est. Cost:     $0.47                ║
║                                      ║
║  🔗 View Full Transcript             ║
║                                      ║
╚══════════════════════════════════════╝
```

#### 4.3 Conversation Failed

```
╔══════════════════════════════════════╗
║  ❌ CONVERSATION FAILED              ║
╠══════════════════════════════════════╣
║                                      ║
║  Error: Model 'gpt-5.2' not found   ║
║  Failed at: Round 1                  ║
║  Messages before failure: 1          ║
║                                      ║
╚══════════════════════════════════════╝
```

---

### Phase 5: Frontend Integration

#### 5.1 Chat Creation Form — Discord Toggle

Add to `resources/js/Pages/Chat/Create.jsx`:

- **Toggle switch**: "Stream to Discord" (default from user preferences)
- **Webhook URL input**: Pre-filled from user settings, editable per conversation
- **Mode selector**: "Complete Messages" vs "Live Streaming"
- **Channel preview**: Shows which Discord channel the webhook posts to (parse from webhook URL)

Visual design: Midnight Glass style, with a Discord-purple accent (`#5865F2`) for the toggle section. Discord logo icon next to the toggle.

#### 5.2 Chat Show Page — Discord Indicator

On the conversation view (`Chat/Show.jsx`):

- Small Discord icon badge in the header if streaming is active
- Pulsing indicator when live
- Link to the Discord thread (constructed from channel/thread ID)

#### 5.3 User Profile — Discord Settings

Add to the profile/settings page:

- Default Discord webhook URL
- Default streaming preference (on/off)
- Default streaming mode (complete/live)
- Test webhook button (sends a test embed)

#### 5.4 Admin Dashboard

- Discord streaming statistics (how many conversations streamed)
- Webhook health monitoring
- Rate limit hit counter

---

### Phase 6: Long Message Handling

Discord has a 2000 character limit per message. AI responses can be 3000+ characters. Strategy:

#### For Complete Message Mode:
- Split the message into multiple embeds, each under 1900 chars
- Number them: "AEGIS (1/3)", "AEGIS (2/3)", "AEGIS (3/3)"
- Each part is a separate embed in the same webhook call (Discord allows up to 10 embeds per message)

#### For Live Streaming Mode:
- When the buffer exceeds 1800 chars, finalize the current message
- Start a NEW Discord message for the overflow
- Seamlessly continue streaming into the new message
- On finalize, mark the last message with the full metadata footer

#### For the Starter Message:
- Post as a special "Topic" embed with a distinct color (emerald green)
- Include the full chat parameters if they fit
- Truncate with "..." and a "View Full Prompt" link if too long

---

### Phase 7: Error Handling & Resilience

**Golden Rule:** Discord integration must NEVER crash a conversation. All Discord operations are wrapped in try/catch with logging.

```php
protected function safeDiscordCall(callable $operation, string $context): mixed
{
    try {
        return $operation();
    } catch (\Throwable $e) {
        Log::warning("Discord streaming failed: {$context}", [
            'error' => $e->getMessage(),
        ]);
        return null;
    }
}
```

**Failure scenarios:**
- Webhook URL invalid → Log warning, disable streaming for this conversation
- Discord API down → Log, skip, conversation continues normally
- Rate limited (429) → Back off, switch to Mode A, retry after cooldown
- Thread deleted → Create new thread, continue
- Webhook deleted → Log error, disable streaming

**Circuit Breaker:**
After 5 consecutive Discord failures within a conversation, disable Discord streaming for the remainder of that conversation and log a warning. Don't keep hammering a dead webhook.

---

### Phase 8: File Structure

```
app/
├── Services/
│   └── Discord/
│       ├── DiscordStreamer.php        # Core service
│       ├── DiscordEmbedBuilder.php    # Rich embed construction
│       └── DiscordRateLimiter.php     # Rate limit tracking
├── Events/
│   └── (existing events - no changes needed)
config/
└── discord.php                        # Discord configuration
database/
└── migrations/
    └── xxxx_add_discord_streaming_to_conversations_table.php
resources/
└── js/
    └── Components/
        └── Discord/
            ├── DiscordToggle.jsx      # Streaming toggle component
            ├── DiscordIndicator.jsx   # Live indicator badge
            └── DiscordSettings.jsx    # User settings panel
```

---

### Phase 9: The Conversation Metadata Expansion

Currently `metadata` on `Conversation` stores:
```json
{
    "persona_a_name": "AEGIS",
    "persona_b_name": "ATLAS",
    "notifications_enabled": false
}
```

Expand to include:
```json
{
    "persona_a_name": "AEGIS",
    "persona_b_name": "ATLAS",
    "notifications_enabled": false,
    "discord_streaming": {
        "enabled": true,
        "mode": "complete",
        "thread_id": "1234567890",
        "webhook_url": "https://discord.com/api/webhooks/...",
        "messages_posted": 17,
        "last_posted_at": "2026-02-25T22:49:51.000Z",
        "failures": 0
    }
}
```

Actually, on second thought — the dedicated columns from Phase 1 are cleaner for querying. Use metadata only for runtime counters (messages_posted, failures) that don't need to be indexed.

---

### Phase 10: Discord Webhook Setup Guide

Include a user-facing guide (help text in the UI):

1. **Open Discord** → Go to your server
2. **Select a channel** (e.g., `#ai-conversations`)
3. **Edit Channel** → **Integrations** → **Webhooks**
4. **New Webhook** → Name it "Chat Bridge" → Copy URL
5. **Paste** the webhook URL into Chat Bridge settings
6. **Test** with the "Send Test" button

Optionally provide a "Connect Discord" button that opens the webhook creation page directly.

---

### Phase 11: Testing Strategy

```
tests/Feature/
├── DiscordStreamerTest.php
│   ├── testConversationStartCreatesThread()
│   ├── testMessagePostedAsEmbed()
│   ├── testLongMessageSplitCorrectly()
│   ├── testWebhookFailureDoesNotCrashConversation()
│   ├── testRateLimitBackoff()
│   ├── testCircuitBreakerAfterConsecutiveFailures()
│   ├── testWebhookUrlResolutionPriority()
│   ├── testDisabledStreamingSkipsDiscord()
│   └── testStreamingModeChunkEditing()
```

Mock the Discord webhook HTTP calls using Laravel's `Http::fake()`.

---

### Phase 12: Implementation Order (Recommended)

| Step | What | Time Estimate | Priority |
|------|------|--------------|----------|
| 1 | `config/discord.php` + `.env` vars | 15 min | 🔴 |
| 2 | Migration (conversation + user columns) | 15 min | 🔴 |
| 3 | `DiscordEmbedBuilder` (static embed formatting) | 30 min | 🔴 |
| 4 | `DiscordStreamer` — Mode A (complete messages) | 1 hour | 🔴 |
| 5 | Hook into `RunChatSession` (3 insertion points) | 30 min | 🔴 |
| 6 | Frontend — Discord toggle on Create page | 30 min | 🔴 |
| 7 | Tests for Mode A | 45 min | 🔴 |
| 8 | **🎉 MVP DONE — Mode A working** | **~3.5 hours** | |
| 9 | `DiscordRateLimiter` | 30 min | 🟡 |
| 10 | `DiscordStreamer` — Mode B (live streaming) | 1.5 hours | 🟡 |
| 11 | Frontend — Mode selector + Discord indicator | 30 min | 🟡 |
| 12 | User profile Discord settings | 30 min | 🟡 |
| 13 | Tests for Mode B | 45 min | 🟡 |
| 14 | **🎉 FULL FEATURE — Both modes working** | **~7 hours total** | |
| 15 | Admin dashboard Discord stats | 1 hour | 🟢 |
| 16 | Circuit breaker + advanced error handling | 45 min | 🟢 |

---

## Bonus Ideas

### 🗳️ Community Voting
Add reaction-based voting in Discord: 👍 = interesting point, 🧠 = mind-blown, ❓ = needs clarification. Track reactions via a Discord bot (separate from webhooks) and feed them back to Chat Bridge analytics.

### 📊 Post-Conversation Summary
After a conversation completes, use an AI to generate a brief summary and post it as the final Discord message. This gives the community a TL;DR without reading 50 messages.

### 🔗 Deep Links
Each Discord embed includes a "View in Chat Bridge" link that opens the conversation in the web UI at that specific message. Uses the existing `/chat/{id}` route.

### 🎭 Persona Avatars
Generate or assign avatar images for each persona. Discord webhooks support custom `avatar_url` per message, so each agent can have its own avatar in the Discord thread.

### 🎵 TTS Preview
Ties into your "smooth streaming + TTS" idea from Discord notes. After posting the text message, also post a voice message or audio embed with the TTS version. Discord supports audio file attachments.

### 🌐 Multi-Channel Routing
Different conversation topics auto-route to different Discord channels:
- Physics → `#ai-physics`
- Consciousness → `#ai-consciousness`
- Code → `#ai-dev`
Based on keyword matching in the starter message or persona tags.

---

## Summary

The Discord Live Stream feature turns Chat Bridge from a solo tool into a **spectator sport**. Your QMU community can watch AI agents debate in real time, react, and engage — all without needing access to the Chat Bridge web app. It's synthetic data creation as entertainment.

The MVP (Mode A) is achievable in a single afternoon coding session. The full feature with live streaming (Mode B) is a solid day's work. Both modes are completely non-destructive — if Discord is down, conversations run normally.

**This is the bridge between Chat Bridge and community.** 🌉
