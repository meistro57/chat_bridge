# Roles Configuration Guide

The roles edition (`chat_bridge_roles.py`) lets you script bespoke personas for each provider before a conversation begins. It loads a JSON file (default: `roles.json`) and merges it with built-in defaults.

## File Structure

```json
{
  "openai": {
    "system": "System prompt for ChatGPT",
    "guidelines": ["Optional bullet points shown to ChatGPT"]
  },
  "anthropic": {
    "system": "System prompt for Claude",
    "guidelines": ["Optional bullet points shown to Claude"]
  },
  "stop_words": ["wrap up", "end chat"],
  "temp_a": 0.6,
  "temp_b": 0.7
}
```

### Keys

- **`openai.system` / `anthropic.system`** – base instructions injected as the respective system prompts.
- **`openai.guidelines` / `anthropic.guidelines`** – extra bullet points appended to the system prompt. Omit or leave empty for a plain prompt.
- **`stop_words`** – optional list of phrases that will halt the conversation if either model says them.
- **`temp_a` / `temp_b`** – default temperatures supplied to the CLI if no override is passed.

All fields are optional; anything you leave out falls back to the defaults baked into the script.

## Workflow

1. Duplicate `roles.json` (or point `--roles` to a new file).
2. Edit the JSON to capture the tone, constraints, or safety instructions you need.
3. Run the bridge with the roles file:
   ```bash
   python chat_bridge_roles.py --roles my_roles.json --max-rounds 40 --mem-rounds 10
   ```
4. Adjust stop words or temperatures to shape the flow of the conversation.

If the file is missing, the script will write the default configuration to help you get started.
