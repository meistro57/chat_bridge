# 🎭 Roles & Personas Configuration Guide

The unified Chat Bridge (`chat_bridge.py`) can load personas from a JSON file to customize
AI personalities, system prompts, and conversation behavior. This guide explains how to
create and use custom personas in your conversations.

## File Structure

The `roles.json` file contains two main sections: **agent defaults** and **persona library**.

```json
{
  "agent_a": {
    "provider": "openai",
    "model": null,
    "system": "Default system prompt for agent A",
    "guidelines": ["Optional bullet points appended to system prompt"]
  },
  "agent_b": {
    "provider": "anthropic",
    "model": null,
    "system": "Default system prompt for agent B",
    "guidelines": ["Optional guidelines for agent B"]
  },
  "persona_library": {
    "philosopher": {
      "provider": "anthropic",
      "model": null,
      "system": "You are a thoughtful philosopher who explores deep questions.",
      "guidelines": [
        "Question assumptions and explore multiple perspectives",
        "Use concrete examples to illustrate abstract concepts",
        "Acknowledge uncertainty when appropriate"
      ]
    },
    "scientist": {
      "provider": "openai",
      "model": "gpt-4o",
      "system": "You are a rigorous scientist focused on evidence and methodology.",
      "guidelines": [
        "Cite sources and explain methodology",
        "Distinguish between hypotheses and established facts",
        "Suggest experiments to test claims"
      ]
    }
  },
  "stop_words": ["wrap up", "end chat", "terminate"],
  "temp_a": 0.6,
  "temp_b": 0.7
}
```

### Configuration Keys

#### Agent Defaults
- **`agent_a` / `agent_b`** – Default settings for each agent
- **`provider`** – Backend provider: `openai`, `anthropic`, `gemini`, `ollama`, or `lmstudio`
- **`model`** – Specific model name (optional, uses provider defaults if null)
- **`system`** – Base system prompt for the agent
- **`guidelines`** – List of behavioral guidelines appended to system prompt

#### Persona Library
- **`persona_library`** – Collection of reusable AI personalities
- Each persona has the same structure as agent defaults
- Personas can override provider, model, system prompt, and guidelines
- Selected interactively during conversation setup

#### Global Settings
- **`stop_words`** – Phrases that end the conversation when detected
- **`temp_a` / `temp_b`** – Default sampling temperatures (0.0-1.0)

All fields are optional and fall back to built-in defaults.

## Usage Examples

### Interactive Mode with Personas
```bash
python chat_bridge.py --roles roles.json
```
The script will show beautiful menus where you can select personas from your library.

### Command Line with Roles
```bash
python chat_bridge.py --roles custom_personas.json --max-rounds 50 --mem-rounds 15
```

### Quick Launcher with Personas
```bash
python launch.py
# Select option [5] 🎭 Persona Mode
```

## Creating Custom Personas

### Step 1: Edit roles.json
Add new personas to the `persona_library` section:

```json
{
  "persona_library": {
    "debate_coach": {
      "provider": "anthropic",
      "system": "You are a skilled debate coach who helps structure arguments.",
      "guidelines": [
        "Break down complex arguments into logical components",
        "Identify logical fallacies and suggest improvements",
        "Encourage evidence-based reasoning"
      ]
    },
    "creative_writer": {
      "provider": "openai",
      "model": "gpt-4o",
      "system": "You are an imaginative writer who crafts vivid stories.",
      "guidelines": [
        "Use descriptive language and sensory details",
        "Develop interesting characters with clear motivations",
        "Build narrative tension through pacing"
      ]
    }
  }
}
```

### Step 2: Test Your Personas
Run the interactive mode and select your new personas to see them in action!

## Best Practices

- **Clear Identity**: Give each persona a distinct personality and role
- **Specific Guidelines**: Use concrete, actionable guidelines rather than vague instructions
- **Provider Matching**: Choose providers that work well with your persona's style
- **Model Selection**: Specify models when you need specific capabilities
- **Testing**: Always test new personas in short conversations first

## Legacy Compatibility

The current system maintains backward compatibility with older role configurations. Files using the old format will continue to work seamlessly.

