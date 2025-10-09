# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Chat Bridge is an AI conversation bridge that enables two AI assistants from different providers to converse with each other. The system supports multiple AI providers (OpenAI, Anthropic, Gemini, DeepSeek, Ollama, LM Studio, OpenRouter), custom persona management, real-time conversation streaming, and comprehensive logging/transcription. The project includes both CLI and web GUI interfaces.

## Development Commands

### Python CLI Application

```bash
# Interactive mode with simplified setup (recommended)
python chat_bridge.py

# Quick launcher with presets
python launch.py

# Standalone roles manager
python roles_manager.py

# Command-line mode (bypass interactive setup)
python chat_bridge.py --provider-a openai --provider-b anthropic --starter "test" --max-rounds 10

# Provider connectivity testing
python chat_bridge.py  # Select option 3 from menu

# Comprehensive certification suite
python certify.py
```

### New in v1.3.0: Simplified Setup Flow

The CLI now features a streamlined step-by-step configuration:
1. Select option 1 from main menu ("Start Conversation - Simple Setup")
2. Configure Agent A: Persona → Provider → Model → Temperature (default 0.6)
3. Configure Agent B: Persona → Provider → Model → Temperature (default 0.6)
4. Enter conversation starter
5. Watch the conversation begin

This replaces the previous multi-mode setup with a single intuitive flow.

### Web GUI Development

```bash
# Backend (FastAPI server on port 8000)
cd web_gui/backend
pip install -r requirements.txt
python main.py

# Backend with auto-reload
cd web_gui/backend
uvicorn main:app --reload --host 0.0.0.0 --port 8000

# Frontend (React + Vite on port 5173)
cd web_gui/frontend
npm install
npm run dev

# Frontend build
cd web_gui/frontend
npm run build
npm run preview
```

### Testing

```bash
# Run automated certification
python certify.py

# Test specific providers
python -c "import asyncio; from chat_bridge import ping_provider; asyncio.run(ping_provider('openai'))"

# Run unit tests (if available)
python -m pytest test_chat_bridge.py -v
python test_bridge_agents.py
python simple_test.py

# Test roles manager
python test_roles_manager_validation.py
python test_roles_debug.py
```

## Architecture

### Core Components

**chat_bridge.py** - Main CLI application orchestrating conversations. Handles interactive menus, provider selection, conversation flow, streaming output, database logging, and transcript generation.

**bridge_agents.py** - Provider abstraction layer defining the agent interface. Contains `ProviderSpec` registry, `AgentRuntime` dataclass, provider-specific chat classes (`OpenAIChat`, `AnthropicChat`, `GeminiChat`, `OllamaChat`), credential management, and model resolution logic. All providers implement async streaming via `AsyncGenerator[str, None]`.

**roles_manager.py** - Standalone persona management utility for creating, editing, and managing AI personalities stored in `roles.json`. Supports custom role creation, guideline management, temperature settings, and stop word configuration.

**launch.py** - Quick launcher with preset conversation configurations to bypass interactive setup.

**certify.py** - Automated certification suite testing all providers, database operations, file system operations, and integration health.

### Web GUI Architecture

**web_gui/backend/main.py** - FastAPI server providing RESTful API and WebSocket support for real-time conversations. Imports bridge_agents functionality and exposes endpoints:
- `GET /api/providers` - List available AI providers
- `GET /api/personas` - Load personas from roles.json
- `POST /api/conversations` - Create conversation session
- `WS /ws/conversations/{id}` - WebSocket for streaming messages

**web_gui/frontend/** - React + TypeScript + Tailwind CSS frontend with three-step setup wizard (persona selection → provider selection → conversation start), real-time chat interface with WebSocket streaming, and responsive design with dark mode.

### Provider System

The system uses a plugin-style provider architecture. Each provider is registered in `PROVIDER_REGISTRY` (bridge_agents.py:47) with:
- `key`: Provider identifier (openai, anthropic, gemini, ollama, lmstudio, deepseek, openrouter)
- `kind`: API type (chatml, anthropic, gemini, ollama, openai)
- `default_model`: Model name (overridable via env vars like `OPENAI_MODEL`)
- `needs_key`: Whether API key is required
- `key_env`: Environment variable name for API key

**Adding a new provider**: Add entry to `PROVIDER_REGISTRY`, implement chat class inheriting from appropriate base, and ensure `create_agent()` handles the new provider type.

### Conversation Flow

1. User selects providers and personas via CLI menus or web GUI
2. `create_agent()` instantiates appropriate chat classes with credentials
3. `AgentRuntime` manages conversation state (history, max_rounds, mem_rounds)
4. Each turn: agent generates response → stream to console/WebSocket → check stop words → add to history → switch agents
5. On completion: save transcript to `transcripts/`, log to SQLite `bridge.db`, optionally save session log to `logs/`

### Database Schema

SQLite database `bridge.db` with two tables:

```sql
CREATE TABLE conversations (
    id TEXT PRIMARY KEY,
    provider_a TEXT,
    provider_b TEXT,
    starter_message TEXT,
    timestamp TEXT,
    status TEXT
);

CREATE TABLE messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    conversation_id TEXT,
    agent_provider TEXT,
    role TEXT,
    content TEXT,
    timestamp TEXT,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id)
);
```

### Persona System

**roles.json** structure:
- `agent_a` / `agent_b`: Default agent configurations with provider, system prompt, guidelines
- `persona_library`: Named personas (scientist, philosopher, comedian, steel_worker, deepseek, adhd_kid, complainer) with full configuration
- `temp_a` / `temp_b`: Temperature overrides
- `stop_words`: Conversation termination phrases
- `stop_word_detection_enabled`: Toggle for stop word checking

Personas override default system prompts and can specify provider preference, custom temperature, and behavioral guidelines.

## Configuration

### Required Environment Variables

Create `.env` file in project root:

```bash
# API Keys (required for cloud providers)
OPENAI_API_KEY=sk-...
ANTHROPIC_API_KEY=sk-ant-...
GEMINI_API_KEY=...
DEEPSEEK_API_KEY=sk-...

# Optional: Model overrides
OPENAI_MODEL=gpt-4o-mini
ANTHROPIC_MODEL=claude-3-5-sonnet-20241022
GEMINI_MODEL=gemini-2.5-flash
DEEPSEEK_MODEL=deepseek-chat
OLLAMA_MODEL=llama3.1:8b-instruct
LMSTUDIO_MODEL=lmstudio-community/Meta-Llama-3-8B-Instruct-GGUF

# Optional: Local provider URLs
OLLAMA_HOST=http://localhost:11434
LMSTUDIO_BASE_URL=http://localhost:1234/v1
DEEPSEEK_BASE_URL=https://api.deepseek.com/v1

# Optional: Agent-specific overrides
BRIDGE_MODEL_A=gpt-4o-mini
BRIDGE_MODEL_B=claude-3-5-sonnet-20241022
BRIDGE_SYSTEM_A="Custom system prompt for Agent A"
BRIDGE_SYSTEM_B="Custom system prompt for Agent B"
```

### Dependencies

**Python** (requires 3.10+):
- `httpx` - Async HTTP client for API calls
- `python-dotenv` - Environment variable loading
- `google-generativeai` - Gemini API client
- `inquirer` - Interactive CLI menus

**Web GUI Backend**:
- `fastapi` - Web framework
- `uvicorn[standard]` - ASGI server
- `websockets` - WebSocket support
- `pydantic` - Data validation

**Web GUI Frontend**:
- `react` 18.2+ - UI framework
- `typescript` 5.2+ - Type safety
- `vite` - Build tool and dev server
- `tailwindcss` - Styling
- `lucide-react` - Icons

Install Python dependencies: `pip install -r requirements.txt`
Install web backend: `pip install -r web_gui/backend/requirements.txt`
Install web frontend: `cd web_gui/frontend && npm install`

## Common Development Tasks

### Adding a New Persona

Edit `roles.json` or use the interactive roles manager:

```bash
python roles_manager.py
# Select "Create New Persona" and follow prompts
```

Or edit `roles.json` directly under `persona_library`:

```json
{
  "persona_library": {
    "my_persona": {
      "provider": "openai",
      "system": "You are a helpful assistant with...",
      "guidelines": [
        "Follow these principles...",
        "Use this communication style..."
      ],
      "temperature": 0.7,
      "notes": "Optional description"
    }
  }
}
```

### Testing Provider Connectivity

Before starting development with a new provider or after changing API keys:

```bash
python chat_bridge.py
# Select option 3 (Test Provider Connectivity)
# Select option 1 (Test all providers)
```

Or test programmatically in bridge_agents.py by calling `ping_provider(provider_name)`.

### Debugging Conversation Issues

1. Check global log: `chat_bridge.log` contains all request IDs and errors
2. Check session log: `logs/<timestamp>__<slug>.log` for session-specific details
3. Enable verbose logging: Set `logging.basicConfig(level=logging.DEBUG)` in chat_bridge.py
4. Test single round: `python chat_bridge.py --provider-a openai --provider-b anthropic --max-rounds 1 --starter "test"`

### Modifying Stop Word Detection

Stop words trigger conversation termination. Manage via:

1. **Interactive**: Run `python chat_bridge.py` → Select option 2 (Manage Roles & Personas) → Configure stop words
2. **Direct edit**: Modify `stop_words` array in `roles.json`
3. **Toggle detection**: Set `stop_word_detection_enabled` to `true` or `false` in `roles.json`

Stop word checking occurs in chat_bridge.py around line 2530-2540 in the conversation loop.

### Handling Rate Limits

Providers have different rate limits. When hitting limits:
- **OpenAI**: Free tier 3 RPM, paid 3500+ RPM
- **Anthropic**: 5-50 RPM depending on tier
- **Gemini**: Free 15 RPM, with billing 300 RPM
- **Local providers** (Ollama, LM Studio): No limits

Increase `--mem-rounds` sparingly as it increases context size. Adjust `MAX_TOKENS` in bridge_agents.py:16 to control response length (default 800).

## File Locations

- **Transcripts**: `transcripts/<timestamp>__<slug>.md` - Full conversation Markdown with session metadata, round markers, and persona names
- **Session logs**: `logs/<timestamp>__<slug>.log` - Per-session structured logs
- **Global log**: `chat_bridge.log` - Append-only log for all sessions
- **Database**: `bridge.db` - SQLite database with conversations and messages tables
- **Personas**: `roles.json` - Persona library and agent configurations
- **Chatlogs** (legacy): `chatlogs/` - Old transcript location, current scripts use `transcripts/`

### Transcript Format

Transcripts now include:
- **Round markers**: Each conversation turn is prefixed with `**Round N**` for easy tracking
- **Persona names**: When personas are selected, they appear as speaker labels (e.g., "Steel Worker" instead of "Agent A")
- **Session metadata**: Full configuration including providers, models, temperatures, and persona details
- **Timestamps**: Each turn includes a timestamp for debugging and analysis

## Web GUI Specifics

### API Endpoints

- `GET /` - Health check returning "Chat Bridge Web API"
- `GET /api/providers` - Returns list of available providers with labels and descriptions
- `GET /api/personas` - Returns persona library from roles.json
- `POST /api/conversations` - Creates conversation, returns conversation_id
- `WS /ws/conversations/{id}` - WebSocket stream for messages

### WebSocket Message Format

Messages sent from server to client:

```json
{
  "type": "message",
  "data": {
    "content": "message text",
    "sender": "agent_a" | "agent_b",
    "timestamp": "ISO timestamp",
    "persona": "persona_name"
  }
}
```

### Frontend Structure

- `src/App.tsx` - Main application component with step wizard
- `src/components/` - React components for chat UI and setup
- `src/types/` - TypeScript type definitions
- `tailwind.config.js` - Tailwind CSS configuration
- `vite.config.ts` - Build configuration

## Known Issues and Limitations

- **Gemini**: Strict rate limits on free tier (15 RPM)
- **Ollama**: Requires manual model installation via `ollama pull <model>`
- **LM Studio**: Requires manual server startup and model loading
- **Long conversations**: Token limits vary by provider (OpenAI 128k, Anthropic 200k, Gemini 1M context)
- **Stop words**: Case-sensitive matching; lowercase recommended
- **Web GUI**: Currently under active development, CLI is more feature-complete

## Code Style Notes

- Use Python 3.10+ features (type hints with `from __future__ import annotations`)
- Async/await for all provider interactions
- ANSI color codes defined at top of chat_bridge.py for consistent terminal output
- Error handling: catch provider-specific errors and provide actionable troubleshooting
- Logging: use Python `logging` module, not print statements for debug info
- Type hints: use dataclasses for structured data (Turn, ProviderSpec, AgentRuntime)
