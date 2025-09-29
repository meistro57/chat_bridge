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
  "stop_word_detection_enabled": true,
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
- **`stop_word_detection_enabled`** – Boolean flag to enable/disable stop word detection (default: true)
- **`temp_a` / `temp_b`** – Default sampling temperatures (0.0-1.0)

All fields are optional and fall back to built-in defaults.

## Usage Examples

### Simplified Menu Structure (New!)

The Chat Bridge now offers three streamlined setup modes for different use cases:

#### 🚀 Quick Start Mode
```bash
python chat_bridge.py
# Select Option 1: Quick Start - Default Conversation
```
- Instantly starts with OpenAI vs Anthropic
- No persona selection, uses default system prompts
- Perfect for quick discussions and testing

#### 🎭 Role Personality Mode
```bash
python chat_bridge.py
# Select Option 2: Start with Role Personalities
```
- **Role-first approach**: Choose personalities before providers
- **Auto-provider selection**: Providers automatically chosen based on role preferences
- **Streamlined flow**: Direct access to scientist, philosopher, comedian, steel worker
- **Fallback creation**: Creates basic personas if no roles.json exists

#### ⚙️ Advanced Setup Mode
```bash
python chat_bridge.py
# Select Option 3: Advanced Setup
```
- Full control over providers, models, and personas (original functionality)
- Choose from Quick Role Modes, Full Persona Library, or skip personas
- Maximum customization for power users

### Legacy Usage (Still Supported)
```bash
# Interactive mode with roles file
python chat_bridge.py --roles roles.json

# Command line with roles
python chat_bridge.py --roles custom_personas.json --max-rounds 50 --mem-rounds 15

# Quick Launcher with personas
python launch.py
# Select option [5] 🎭 Persona Mode
```

### Benefits of the New Role-First Flow

The new **Role Personality Mode** offers several advantages over the traditional provider-first approach:

#### 🎭 **Personality-Focused Conversations**
- **Direct Role Selection**: Choose "Scientist vs Philosopher" without worrying about technical providers
- **Meaningful Interactions**: Conversations center around personality traits rather than AI model capabilities
- **Intuitive Setup**: Natural flow that matches how humans think about conversations

#### 🚀 **Simplified User Experience**
- **Fewer Steps**: From 4 menu navigation steps down to 2 for role-based conversations
- **Auto-Configuration**: Providers, models, and settings automatically optimized for each role
- **Smart Defaults**: Built-in personas created automatically if no roles.json exists

#### ⚡ **Quick Access to Popular Combinations**
- **Scientist + Philosopher**: Empirical evidence meets ethical reasoning
- **Comedian + Steel Worker**: Humor meets practical wisdom
- **DeepSeek + ADHD Kid**: Strategic analysis meets intuitive pattern recognition
- **Any Combination**: Mix and match from multiple preset personalities including new additions

#### 🔄 **Flow Comparison**

**Traditional Flow** (Advanced Setup):
```
1. Select Provider A (OpenAI/Anthropic/etc.)
2. Select Provider B (OpenAI/Anthropic/etc.)
3. Choose persona mode (Quick/Full/None)
4. Select personas if chosen Quick mode
5. Enter conversation topic
```

**New Role-First Flow**:
```
1. Select Role Personality mode
2. Choose Role A and Role B
3. Enter conversation topic
   (Providers auto-selected based on roles)
```

## Creating Custom Personas

### 🎯 Interactive Management (Recommended)

The Chat Bridge now includes a comprehensive **Roles & Personas Manager** accessible from the main menu:

```bash
python chat_bridge.py
# Choose option 2: "Manage Roles & Personas"
```

#### Features:
- **✨ Create New Personas** - Interactive wizard with guided setup
- **✏️ Edit Existing Personas** - Modify any persona property
- **🤖 Edit Default Agents** - Configure Agent A and Agent B defaults
- **🌡️ Temperature Settings** - Adjust creativity levels (0.0-2.0)
- **🛑 Stop Words Management** - Configure conversation termination phrases
- **🔄 Stop Word Detection Toggle** - Enable/disable stop word detection during conversations
- **📁 Import/Export** - Backup and restore configurations
- **🔄 Reset to Defaults** - Restore original settings

#### Interactive Creation Process:
1. **Persona Name** - Choose a unique identifier
2. **Provider Selection** - Pick from available AI providers
3. **System Prompt** - Multi-line system prompt entry
4. **Guidelines** - Add behavioral guidelines one by one
5. **Model Override** - Optional specific model selection
6. **Notes** - Optional documentation

### 📝 Manual Configuration

### Step 1: Edit roles.json
You can also manually add new personas to the `persona_library` section:

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

## 🌐 Provider Testing Integration

Before creating personas for specific providers, use the **Provider Connectivity Test** from the main menu to verify:

- **API Key Validity** - Ensure credentials are working
- **Model Accessibility** - Verify default models are available
- **Response Times** - Check network performance
- **Connection Health** - Diagnose any issues

This helps ensure your personas will work smoothly with their assigned providers.

## 🔄 Stop Word Detection Control

The Chat Bridge includes a flexible stop word detection system that can be controlled through the roles configuration:

### Configuration
- **`stop_words`** - Array of phrases that trigger conversation termination
- **`stop_word_detection_enabled`** - Boolean flag to enable/disable detection (default: `true`)

### Interactive Toggle
Access the stop word detection toggle through the Roles & Personas Manager:
1. Run `python chat_bridge.py`
2. Select "Manage Roles & Personas" from the main menu
3. Choose "Toggle stop word detection" (option 6)
4. Confirm to enable or disable the feature

### Behavior
- When **enabled**: Conversations automatically end when stop words are detected (after round 10)
- When **disabled**: Stop words are ignored and conversations continue until max rounds or manual termination
- The current status is displayed in the session configuration summary

### Use Cases
- **Enable** for natural conversation flow with graceful endings
- **Disable** for extended research sessions or when stop words might appear naturally in discussion

## Legacy Compatibility

The current system maintains backward compatibility with older role configurations. Files using the old format will continue to work seamlessly.

