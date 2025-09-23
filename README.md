# Chat Bridge

A small toolkit for watching OpenAI's ChatGPT and Anthropic's Claude talk to each other. The project currently ships with two entrypoints:

- **`chat_bridge_pro.py`** – the "classic" bridge with default personas for both models.
- **`chat_bridge_roles.py`** – a variant that reads rich role definitions from `roles.json` before the session starts.

Both scripts stream tokens live to the terminal, log every exchange to SQLite, and emit a Markdown transcript that you can review later.

## Features at a Glance

- **Live streaming:** watch both assistants type in real time.
- **Persistent history:** conversations are saved to `bridge.db` and summarised per-turn in Markdown transcripts.
- **Session artefacts:** alongside the transcript you also get an optional per-session `.log` file and an append-only `chat_bridge.log`.
- **Loop + stop detection:** automatic safeguards stop the run if the models repeat themselves or hit predefined stop phrases.
- **Anthropic fallback:** seamlessly downgrades from the Anthropic Messages API to the older Completions API when required.

## Requirements

- Python 3.10 or newer.
- API keys for both OpenAI and Anthropic.
- The packages listed below (installable via `pip`).

## Setup

1. Clone the repository.
2. Create a virtual environment (optional but recommended):
   ```bash
   python -m venv .venv
   source .venv/bin/activate  # Windows: .venv\Scripts\activate
   ```
3. Install dependencies:
   ```bash
   pip install httpx python-dotenv
   ```
4. Create a `.env` file alongside the scripts and add your keys:
   ```bash
   OPENAI_API_KEY=sk-...
   ANTHROPIC_API_KEY=ak-...
   # Optional overrides
   OPENAI_MODEL=gpt-4o-mini
   ANTHROPIC_MODEL=claude-3-5-sonnet
   ```

## Running the Bridge

### Default personas (`chat_bridge_pro.py`)

```bash
python chat_bridge_pro.py --max-rounds 60 --mem-rounds 12
```

You'll be prompted for the starter message. The bridge alternates between ChatGPT and Claude until it reaches the round limit, encounters a stop word, or detects a loop.

### Role-driven personas (`chat_bridge_roles.py`)

```bash
python chat_bridge_roles.py --roles roles.json --max-rounds 60 --mem-rounds 12
```

This variant loads structured instructions from `roles.json` before the chat begins, allowing you to tailor system prompts, guidelines, stop words, and default temperatures. See [docs/roles.md](docs/roles.md) for the JSON schema and customisation tips.

### Shared CLI options

- `--max-rounds` – maximum number of assistant replies across the session (default: `30`).
- `--mem-rounds` – how many recent turns each provider can see (default: `8`).
- `--openai-model` – override the OpenAI model name (default: `gpt-4o-mini` or the `OPENAI_MODEL` env var).
- `--anthropic-model` – override the Anthropic model (default: `claude-3-5-sonnet` or the `ANTHROPIC_MODEL` env var).
- `--temp-a` / `--temp-b` – sampling temperature for OpenAI and Anthropic respectively (default: `0.7`).
- `--roles` – path to a roles JSON file (roles edition only).

## Outputs & Logs

Each session produces several artefacts:

- `transcripts/<timestamp>__<starter-slug>.md` – the human-readable dialogue.
- `logs/<timestamp>__<starter-slug>.log` – optional structured session log.
- `chat_bridge.log` – append-only global log capturing request IDs and errors.
- `bridge.db` – SQLite database containing conversation metadata and turn-by-turn content.

Legacy transcripts from earlier experiments may be stored in `chatlogs/`; current scripts write to `transcripts/` automatically.

## Running Longer Sessions

To keep the conversation going:

- Increase `--max-rounds` (e.g. `--max-rounds 200`).
- Raise `--mem-rounds` if you want each model to retain more context (values between 12–20 work well).
- Monitor your token budgets: OpenAI models typically cap around 128k tokens, Anthropic models around 200k.

## Troubleshooting

- The scripts will abort if either model outputs a stop phrase (configurable via roles.json in the roles edition).
- A stall longer than 90 seconds triggers a timeout and exits gracefully.
- Check the per-session log and the global `chat_bridge.log` for API request IDs and error details.

Happy bridging!
