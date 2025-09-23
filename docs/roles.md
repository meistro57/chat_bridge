# Roles Configuration Guide

The roles edition (`chat_bridge_roles.py`) loads a JSON file before each session to
decide which providers, models, system prompts, and safeguards to use. The file is
merged with built-in defaults so you can override only the pieces you care about.

## File Structure

```json
{
  "agent_a": {
    "provider": "openai",
    "model": null,
    "system": "System prompt for the first agent",
    "guidelines": ["Optional bullet points appended to the system prompt"]
  },
  "agent_b": {
    "provider": "anthropic",
    "model": null,
    "system": "System prompt for the second agent",
    "guidelines": ["Optional bullet points for the second agent"]
  },
  "stop_words": ["wrap up", "end chat"],
  "temp_a": 0.6,
  "temp_b": 0.7
}
```

### Keys

- **`agent_a.provider` / `agent_b.provider`** – which backend drives each side of the
  conversation. Supported values are `openai`, `anthropic`, `gemini`, `ollama`, and
  `lmstudio`.
- **`model`** – optional explicit model name for that provider. Leave it `null` (or omit it)
  to fall back to the latest turbo default or any `BRIDGE_MODEL_*` / provider-specific
  environment overrides.
- **`system`** – base system prompt injected into the provider before the chat begins.
- **`guidelines`** – optional bullet points appended to the system prompt.
- **`stop_words`** – list of phrases that will halt the run if either agent says them.
- **`temp_a` / `temp_b`** – default temperatures if you do not provide CLI overrides.

All fields are optional. Missing properties inherit from the defaults bundled with the
repository.

## Legacy Format

Older versions of the project used top-level `openai` / `anthropic` keys. Those files
continue to work—the loader automatically upgrades them to the new schema.

## Workflow

1. Duplicate `roles.json` or point `--roles` to a new file.
2. Edit the JSON to capture the providers, tones, and safeguards you need.
3. Run the bridge:
   ```bash
   python chat_bridge_roles.py --roles my_roles.json --max-rounds 40 --mem-rounds 10
   ```
4. Use the interactive prompt (or CLI flags) to switch providers or models on the fly.

If the roles file is missing the script writes the default configuration to help you get
started.

