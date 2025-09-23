# Chat Bridge

Chat Bridge lets you watch two AI assistants riff back and forth while everything is
logged to disk. Both entry points ship with the same features—live token streaming,
SQLite logging, Markdown transcripts—but differ in how you configure personas:

- **`chat_bridge_pro.py`** – quick-start edition with interactive provider selection and
  sensible defaults.
- **`chat_bridge_roles.py`** – reads structured personas from `roles.json` before the
  chat begins.

Under the hood you can mix and match the latest "turbo" models from OpenAI, Anthropic,
Gemini, Ollama, or LM Studio on either side of the bridge.

## Features at a Glance

- **Multi-provider bridge** – choose any combination of OpenAI, Anthropic, Gemini,
  Ollama, or LM Studio for Agent A and Agent B.
- **Turbo defaults** – out of the box the scripts target GPT-4.1 Mini, Claude 3.5 Sonnet,
  Gemini 1.5 Pro, llama3.1 8B (Ollama), and LM Studio's meta-llama3 instruct build.
- **Interactive setup** – each run offers a multiple-choice picker for providers and
  models alongside CLI flags and environment overrides.
- **Streaming transcripts** – watch tokens arrive live, capture the Markdown transcript,
  and persist structured logs in SQLite plus optional `.log` files.
- **Loop + stop guards** – configurable stop phrases and repetition detection end the
  chat gracefully.
- **Versioned releases** – the project now exposes a semantic version (`--version`) so you
  can keep track of updates.

## Requirements

- Python 3.10 or newer.
- Dependencies: `httpx`, `python-dotenv` (install via `pip install httpx python-dotenv`).
- API keys for whichever cloud providers you plan to use.
  - `OPENAI_API_KEY` for OpenAI.
  - `ANTHROPIC_API_KEY` for Anthropic.
  - `GEMINI_API_KEY` for Gemini.
- Local endpoints for on-device models (optional):
  - Ollama running on `http://localhost:11434` (override with `OLLAMA_HOST`).
  - LM Studio's OpenAI-compatible server on `http://localhost:1234/v1`
    (override with `LMSTUDIO_BASE_URL`).

Set provider-specific default models with environment variables such as
`OPENAI_MODEL`, `ANTHROPIC_MODEL`, `GEMINI_MODEL`, `OLLAMA_MODEL`, or
`LMSTUDIO_MODEL`. You can also target particular agents with `BRIDGE_MODEL_A` and
`BRIDGE_MODEL_B`, plus override system prompts with `BRIDGE_SYSTEM_A` /
`BRIDGE_SYSTEM_B`.

## Setup

1. Clone the repository.
2. (Optional) create and activate a virtual environment.
3. Install dependencies:
   ```bash
   pip install httpx python-dotenv
   ```
4. Create a `.env` file alongside the scripts and add your secrets:
   ```bash
   OPENAI_API_KEY=sk-...
   ANTHROPIC_API_KEY=ak-...
   GEMINI_API_KEY=gm-...
   # Optional local hosts / model overrides
   OLLAMA_HOST=http://localhost:11434
   LMSTUDIO_BASE_URL=http://localhost:1234/v1
   OPENAI_MODEL=gpt-4.1-mini
   ANTHROPIC_MODEL=claude-3-5-sonnet-20240620
   GEMINI_MODEL=gemini-1.5-pro-latest
   OLLAMA_MODEL=llama3.1:8b-instruct
   LMSTUDIO_MODEL=lmstudio-community/Meta-Llama-3-8B-Instruct-GGUF
   ```

## Running the Bridge

### Default personas (`chat_bridge_pro.py`)

```bash
python chat_bridge_pro.py --max-rounds 40 --mem-rounds 12
```

You'll be prompted for a starter message and then offered a multiple-choice picker for
both agents. Press Enter to accept the CLI/env defaults or enter values such as `1,3` to
pair OpenAI with Gemini. Additional prompts allow quick model overrides.

### Role-driven personas (`chat_bridge_roles.py`)

```bash
python chat_bridge_roles.py --roles roles.json --max-rounds 40 --mem-rounds 12
```

This variant loads system prompts, provider preferences, stop words, and default
temperatures from `roles.json`. See [docs/roles.md](docs/roles.md) for the schema. The
same interactive provider picker appears after the starter prompt.

### Shared CLI Options

- `--provider-a` / `--provider-b` – select providers for the first and second agent
  (defaults come from env vars or roles file).
- `--model-a` / `--model-b` – model overrides for each agent (`--openai-model` and
  `--anthropic-model` remain as aliases for backwards compatibility).
- `--max-rounds` – maximum number of assistant replies across the session (default `30`).
- `--mem-rounds` – how many recent turns each provider can see (default `8`).
- `--temp-a` / `--temp-b` – sampling temperatures (roles edition may override them via the
  JSON file).
- `--version` – print the current Chat Bridge version and exit.
- `--roles` – path to a roles JSON file (roles edition only).

## Outputs & Logs

Every session produces:

- `transcripts/<timestamp>__<starter-slug>.md` – the Markdown transcript.
- `logs/<timestamp>__<starter-slug>.log` – optional structured per-session log.
- `chat_bridge.log` – global append-only log capturing request IDs and errors.
- `bridge.db` – SQLite database containing metadata plus turn-by-turn content.

Legacy transcripts from earlier experiments may be stored in `chatlogs/`; current scripts
write to `transcripts/` automatically.

## Running Longer Sessions

- Increase `--max-rounds` (e.g. `--max-rounds 200`).
- Raise `--mem-rounds` if you want each model to retain more context (values between
  `12`–`20` work well).
- Monitor token budgets: OpenAI GPT-4.1 Mini typically caps around 128k tokens, Anthropic
  Claude models around 200k, and Gemini 1.5 Pro around 2M context (depending on release).

## Troubleshooting

- The scripts abort if either assistant hits a configured stop phrase.
- A stall longer than 90 seconds triggers a timeout and ends the session gracefully.
- Check the per-session log and the global `chat_bridge.log` for request IDs and errors.
- Missing API keys raise clear runtime errors—set them in `.env` or your shell.

Happy bridging!

