# 🌉 Chat Bridge - Unified Edition

A beautiful, interactive chat bridge that connects two AI assistants with colorful menus and enhanced user experience! Watch AI assistants converse while everything is logged with comprehensive transcripts and SQLite storage.

## ✨ What's New in Version 1.2.0

- **✨ Custom Role Creation** - Create fully customizable AI roles with user-defined settings
- **🎭 Enhanced Role Modes** - 4 preset role modes: Scientist, Philosopher, Comedian, Steel Worker
- **🎯 Advanced Stop Word Control** - Lessened stop word weight function for nuanced conversation control
- **🎨 Beautiful colorful interface** with styled menus and progress indicators
- **🚀 Single unified script** combining all previous functionality
- **🎯 Interactive mode** with guided setup and provider selection
- **🎭 Persona system** supporting custom AI personalities from `roles.json`
- **⚙️ Comprehensive roles management** - Create, edit, and manage personas interactively
- **🌐 Provider connectivity testing** - Ping and diagnose AI provider connections
- **⚡ Quick launcher** with preset configurations
- **🔒 Enhanced security** with proper API key management
- **🔄 Stop Word Detection Toggle** - Enable/disable conversation termination on stop words

## 🚀 Quick Start

**Option 1: Interactive Launcher (Recommended)**
```bash
python launch.py
```

**Option 2: Direct Interactive Mode**
```bash
python chat_bridge.py
```

**Option 3: Command Line**
```bash
python chat_bridge.py --provider-a openai --provider-b anthropic --starter "What is consciousness?"
```

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
- Dependencies: `httpx`, `python-dotenv`, `google-generativeai` (install via `pip install httpx python-dotenv google-generativeai`).
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
   pip install httpx python-dotenv google-generativeai
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

### Interactive Mode (Recommended)

```bash
python chat_bridge.py
```

You'll see beautiful colored menus guiding you through:

### 🚀 Main Menu Options:
1. **Start Chat Bridge Conversation** - Begin an AI-to-AI conversation
2. **Manage Roles & Personas** - Interactive roles.json configuration
3. **Test Provider Connectivity** - Diagnose and test AI provider connections
4. **Exit** - Gracefully exit the application

### 💬 Conversation Flow:
1. **Provider Selection** - Choose AI providers for both agents
2. **Persona Selection** - Optional personas from `roles.json`
3. **Conversation Starter** - Enter your discussion topic
4. **Live Conversation** - Watch the AI assistants converse with real-time streaming

### Command Line Mode

```bash
python chat_bridge.py --provider-a openai --provider-b anthropic --max-rounds 40 --mem-rounds 12
```

Skip the interactive setup by providing all parameters via command line.

### CLI Options

- `--provider-a` / `--provider-b` – select providers for agents A and B
- `--model-a` / `--model-b` – model overrides for each agent
- `--max-rounds` – maximum conversation rounds (default: 30)
- `--mem-rounds` – context memory rounds (default: 8)
- `--temp-a` / `--temp-b` – sampling temperatures (default: 0.7)
- `--roles` – path to personas JSON file
- `--starter` – conversation starter (skips interactive mode)
- `--version` – show version and exit

**Legacy aliases:** `--openai-model`, `--anthropic-model`

## 🎭 Persona System & Roles Management

### 🎯 Role Modes (New in v1.2.0)
Choose from 4 preset role modes or create your own custom role:

#### Preset Role Modes:
- **🔬 Scientist** - Evidence-based, analytical, methodical approach
- **🤔 Philosopher** - Deep thinking, ethical reasoning, existential exploration
- **😂 Comedian** - Witty, observational, entertaining responses
- **🏭 Steel Worker** - Practical, hands-on, blue-collar wisdom and experience

#### ✨ Custom Role Creation:
Create fully customized AI roles with complete control over:
- **Role Name** - Define your custom role identity
- **AI Provider** - Choose from OpenAI, Anthropic, Gemini, Ollama, or LM Studio
- **Model Override** - Specify custom models if needed
- **System Prompt** - Complete control over AI personality and behavior
- **Guidelines** - Multiple behavioral instructions and rules
- **Temperature** - Custom creativity level (0.0-2.0)
- **Notes** - Optional role descriptions
- **Permanent Saving** - Save custom roles to `roles.json` for future use

### Interactive Roles Configuration
The Chat Bridge includes a comprehensive roles management interface accessible from the main menu:

- **✨ Create New Personas** - Interactive wizard for persona creation
- **✏️ Edit Existing Personas** - Modify system prompts, guidelines, and settings
- **🤖 Edit Default Agents** - Configure Agent A and Agent B defaults
- **🌡️ Temperature Settings** - Adjust creativity levels for each agent
- **🛑 Stop Words Management** - Configure conversation termination phrases
- **🔄 Stop Word Detection Toggle** - Enable/disable stop word detection during conversations
- **📁 Import/Export** - Backup and restore configurations
- **🔄 Reset to Defaults** - Restore original settings

### Roles.json Structure
Create custom AI personalities in `roles.json`:

```json
{
  "agent_a": {
    "provider": "openai",
    "system": "You are ChatGPT. Be concise, truthful, and witty.",
    "guidelines": ["Cite sources", "Use clear structure"]
  },
  "agent_b": {
    "provider": "anthropic",
    "system": "You are Claude. Be thoughtful and reflective.",
    "guidelines": ["Consider multiple perspectives", "Express uncertainty"]
  },
  "persona_library": {
    "scientist": {
      "provider": "openai",
      "system": "You are a research scientist. Approach topics with rigorous scientific methodology...",
      "guidelines": [
        "Base conclusions on empirical evidence",
        "Use the scientific method framework",
        "Acknowledge limitations and uncertainties"
      ]
    },
    "philosopher": {
      "provider": "anthropic",
      "system": "You are a philosopher. Engage with deep questions about existence...",
      "guidelines": [
        "Question assumptions deeply",
        "Explore multiple perspectives",
        "Embrace complexity and nuance"
      ]
    },
    "comedian": {
      "provider": "openai",
      "system": "You are a comedian. Find humor in everyday situations...",
      "guidelines": [
        "Look for absurdity and unexpected connections",
        "Use wordplay and clever observations",
        "Balance entertainment with insight"
      ]
    },
    "steel_worker": {
      "provider": "anthropic",
      "system": "You are a steel worker. Speak from experience with hands-on work...",
      "guidelines": [
        "Emphasize practical solutions",
        "Value hard work and reliability",
        "Focus on what actually works"
      ]
    }
  },
  "temp_a": 0.6,
  "temp_b": 0.7,
  "stop_words": ["wrap up", "end chat", "terminate"],
  "stop_word_detection_enabled": true
}
```

## 🌐 Provider Connectivity Testing

Diagnose connection issues and verify API keys before starting conversations. The enhanced error reporting system provides detailed troubleshooting guidance for each provider.

### 🔍 Testing Features:
- **Test All Providers** - Comprehensive connectivity check for all configured providers
- **Test Specific Provider** - Detailed diagnostics for individual providers
- **System Diagnostics** - Environment variables and configuration overview
- **Real-time Results** - Response times and connection status
- **Enhanced Error Diagnosis** - Specific troubleshooting recommendations with step-by-step solutions
- **Troubleshooting Tips** - Contextual help based on specific error types

### 📊 What Gets Tested:
- ✅ **API Key Validity** - Authentication with each provider
- ✅ **Model Accessibility** - Default model availability
- ✅ **Response Time** - Network latency measurement
- ✅ **Local Services** - Ollama/LM Studio server status
- ✅ **Connection Health** - Network connectivity verification

### Sample Output:
```
🌐 PROVIDER CONNECTIVITY TEST

Testing OpenAI...
  ✅ API key valid, model accessible (245ms)

Testing Anthropic...
  ❌ Invalid API key

📊 PROVIDER STATUS SUMMARY
Overall Status: 1/2 providers online

🟢 ONLINE PROVIDERS:
  • OpenAI (gpt-4.1-mini) - 245ms

🔴 PROVIDERS WITH ISSUES:
  • Anthropic: ❌ Invalid API key

💡 RECOMMENDATIONS:
  • Check your API keys and network connectivity
  • Consider using available providers for conversations
```

## 🎨 Visual Features

- **🌈 Colorful menus** - Beautiful ANSI colors and formatting
- **📊 Real-time progress** - Live conversation streaming
- **💬 Styled output** - Clear agent identification and formatting
- **⚡ Quick launcher** - Preset configurations for common scenarios

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

### 🏆 Comprehensive Certification

Run the automated certification script to validate your entire Chat Bridge installation:

```bash
python certify.py
```

**Enhanced Features:**
- 🔍 **Detailed provider identification** with specific AI model names (ChatGPT, Claude, Gemini, etc.)
- ⏱️ **Comprehensive timestamps** for all test operations
- 📊 **Enhanced reporting** with provider-specific statistics
- 🎯 **Structured JSON reports** saved to `certification_report_YYYYMMDD_HHMMSS.json`

The certification covers:
- ✅ Module imports and dependencies
- ✅ File structure validation
- ✅ Database operations (SQLite)
- ✅ Provider connectivity (OpenAI, Anthropic, Gemini, Ollama, LM Studio)
- ✅ Roles and personas system
- ✅ Error handling and recovery

## 🔧 Quick Diagnostics
Use the built-in **Provider Connectivity Test** from the main menu to quickly diagnose issues:
- Check API key validity
- Test network connectivity
- Verify local services (Ollama/LM Studio)
- View environment configuration

### Common Issues
- The scripts abort if either assistant hits a configured stop phrase.
- A stall longer than 90 seconds triggers a timeout and ends the session gracefully.
- Check the per-session log and the global `chat_bridge.log` for request IDs and errors.
- Missing API keys raise clear runtime errors—set them in `.env` or your shell.

### Provider-Specific Troubleshooting

#### 🔑 OpenAI
- **Invalid API Key (401)**: Verify `OPENAI_API_KEY` is set correctly, check credits, ensure key hasn't expired
- **Access Forbidden (403)**: API key lacks model permissions, try different model (e.g., gpt-3.5-turbo)
- **Rate Limited (429)**: Wait for reset, check usage limits in OpenAI dashboard, consider upgrading plan
- **Network Issues**: Check internet connection, verify firewall/proxy settings

#### 🤖 Anthropic
- **Invalid API Key (401)**: Verify `ANTHROPIC_API_KEY` is set correctly, ensure key is valid and active
- **Access Forbidden (403)**: Check API key permissions, verify account status
- **Rate Limited (429)**: Wait before retrying, check usage limits, consider API tier upgrade

#### 🔮 Gemini
- **Invalid API Key (401)**: Verify `GEMINI_API_KEY`, enable Gemini API in Google Cloud Console
- **Rate Limited (429)**: Wait for reset, check quota in Google Cloud Console, enable billing for higher limits
- **Access Forbidden (403)**: Enable Gemini API, check permissions, verify billing is enabled

#### 🦙 Ollama
- **Connection Refused**: Start Ollama with `ollama serve` or `systemctl start ollama`
- **Model Not Found (404)**: Pull model with `ollama pull llama3.1:8b-instruct`, check `OLLAMA_MODEL`
- **Port Issues**: Verify Ollama runs on port 11434, check `OLLAMA_HOST` variable
- **Firewall**: Ensure firewall allows connections to Ollama port

#### 🏠 LM Studio
- **Connection Refused**: Start LM Studio application and load a model
- **Server Not Started**: Enable local server in LM Studio (usually port 1234)
- **API Endpoint (404)**: Verify server is running, check if model is loaded
- **Port Conflicts**: Check if another application uses port 1234, verify `LMSTUDIO_BASE_URL`

Happy bridging!

