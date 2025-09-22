# chat_bridge

# Chat Bridge Pro

*OpenAI ↔ Anthropic conversational bridge*

# Overview

This script creates a direct, turn-based conversation between **OpenAI (ChatGPT)** and **Anthropic (Claude)**. It streams tokens live, logs each session into a SQLite database, and automatically saves a **Markdown transcript** for easy reading.

Think of it as a digital “chatroom” where two AIs can bounce ideas back and forth, while you watch, log, and analyze.

# Features

* **Live streaming**: Tokens print in real time for both sides.
* **Dual logging**:
   * SQLite database (`bridge.db`) stores structured turns.
   * Per-session Markdown transcript in `transcripts/`.
* **Session isolation**: Each starter prompt creates its own `.md` transcript and `.log` file.
* **Loop detector**: Stops if the conversation repeats itself.
* **Stop words**: Ends gracefully if either model outputs “stop”, “terminate”, etc.
* **Adaptive Anthropic client**: Works with both the `/v1/messages` API and the older `/v1/complete`.

# Setup

1. Clone or copy the repo.
2. Create a virtual environment:python -m venv .venv .\\.venv\\Scripts\\activate  # Windows # or source .venv/bin/activate  # Linux/Mac
3. Install dependencies:pip install httpx python-dotenv
4. Create a `.env` file with your API keys:OPENAI\_API\_KEY=sk-... ANTHROPIC\_API\_KEY=ak-...

# Usage

Run the script:

    python chat_bridge_pro.py --max-rounds 60 --mem-rounds 12

You’ll be prompted for a **starter prompt**. The two models then alternate messages until:

* max rounds is reached,
* a stop word is detected, or
* a loop is detected.

# CLI Options

* `--max-rounds` → maximum number of replies (default: 30)
* `--mem-rounds` → how many recent turns each side “remembers” (default: 8)
* `--openai-model` → override default model (default: gpt-4o-mini)
* `--anthropic-model` → override default model (default: claude-3-5-sonnet)
* `--temp-a` → temperature for OpenAI (default: 0.7)
* `--temp-b` → temperature for Anthropic (default: 0.7)

# Outputs

* **Markdown transcript** Saved in `transcripts/<timestamp>__<starter-slug>.md` Contains the full dialogue in clean, human-readable form.
* **Session log** Saved in `logs/<timestamp>__<starter-slug>.log` Contains request IDs, errors, and debug info.
* **Global log** Appended to `chat_bridge.log`.
* **SQLite DB** `bridge.db` holds all structured conversation data:
   * `conversations` table → session metadata
   * `messages` table → each turn of dialogue

# Running Longer Sessions

To stretch conversations:

* Increase `--max-rounds` (e.g., `--max-rounds 200`).
* Increase `--mem-rounds` if you want more context remembered (12–20 is a good sweet spot).
* Be mindful of API token limits (OpenAI \~128k tokens, Anthropic \~200k). For ultra-long chats, use the **auto-summariser** version of the script.

# Example

    python chat_bridge_pro.py --max-rounds 100 --mem-rounds 16

Starter prompt:

    What do you think is the meaning behind human life?

Transcript saved to:

    transcripts/2025-09-22_09-51-42__what-do-you-think-is-the-meaning-behind-human-life.md
