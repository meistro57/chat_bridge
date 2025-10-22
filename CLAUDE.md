# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Chat Bridge is an AI conversation bridge that enables two AI assistants from different providers to converse with each other. The system supports multiple AI providers (OpenAI, Anthropic, Gemini, DeepSeek, Ollama, LM Studio, **OpenRouter**), custom persona management, real-time conversation streaming, **HTTP-based MCP memory system**, and comprehensive logging/transcription. The project now includes both **retro web GUI** and traditional CLI interfaces.

**Current Version**: 1.4.2

## New In v1.4.2: Web GUI Asset Fix 🎨

- **Fixed Missing Favicon**: Added `vite.svg` favicon to resolve 404 errors in web GUI
- **Created Public Directory**: Established proper static asset structure in `web_gui/frontend/public/`

## Previous In v1.4.1: Bug Fixes & Improvements 🔧

- **Fixed Web GUI Provider Status**: Added missing `/api/provider-status` endpoint that was causing 404 errors
- **Improved Provider Validation**: Provider status now accurately reflects configured credentials
  - API-based providers (OpenAI, Anthropic, etc.) show connected only when valid credentials exist
  - Local providers (Ollama, LM Studio) accurately show as unverifiable without testing
  - Better error messages for missing or invalid API keys

## Previous In v1.4.0: Retro Web GUI 🎨⚡🤖

Experience AI conversations like never before with the immersive retro web interface featuring:

- **🌈 Nostalgic Retro Aesthetic** - Classic retro interface with beveled buttons, gray backgrounds, and vintage styling
- **⚡ Real-Time Streaming** - Watch AI responses appear live with ultra-low latency WebSocket connections  
- **🎭 Advanced Persona System** - Choose from 32+ pre-built personas (Scientist, Philosopher, Comedian, etc.)
- **🔄 Universal Provider Support** - All AI providers accessible through unified interface
- **📱 Responsive Experience** - Perfect on desktop, tablet, and mobile devices
- **🎨 Visual Feedback** - Connection status, typing indicators, and smooth animations
- **⚙️ Deep Customization** - Temperature controls, max rounds, and conversation settings

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

### Previous In v1.3.0: Enhanced CLI Experience

The CLI now features a streamlined step-by-step configuration:
1. Select option 1 from main menu ("Start Conversation - Simple Setup")
2. Configure Agent A: Persona → Provider → Model → Temperature (default 0.6)
3. Configure Agent B: Persona → Provider → Model → Temperature (default 0.6)
4. Enter conversation starter
5. Watch the conversation begin

This replaces the previous multi-mode setup with a single intuitive flow.

### Web GUI Development

```bash
# Quick start (recommended)
./start_web_gui.sh

# Manual development setup:

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

### Startup Script

The project includes a convenient startup script for quick development:

```bash
./start_web_gui.sh
```

The script handles:
- Installing Python backend dependencies
- Installing Node.js frontend dependencies  
- Starting the FastAPI backend server on port 8000
- Starting the Vite dev server on port 5173
- Graceful shutdown on Ctrl+C
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

# Test MCP server
python check_mcp_status.py  # Verify status and functionality
```

## Architecture

### Core Components

**chat_bridge.py** - Main CLI application orchestrating conversations. Handles interactive menus, provider selection, conversation flow, streaming output, database logging, and transcript generation.

**bridge_agents.py** - Provider abstraction layer defining the agent interface. Contains `ProviderSpec` registry, `AgentRuntime` dataclass, provider-specific chat classes (`OpenAIChat`, `AnthropicChat`, `GeminiChat`, `OllamaChat`), credential management, and model resolution logic. All providers implement async streaming via `AsyncGenerator[str, None]`.

**roles_manager.py** - Standalone persona management utility for creating, editing, and managing AI personalities stored in `roles.json`. Supports custom role creation, guideline management, temperature settings, and stop word configuration.

**launch.py** - Quick launcher with preset conversation configurations to bypass interactive setup.

**certify.py** - Automated certification suite testing all providers, database operations, file system operations, and integration health.

### MCP Memory System (HTTP-based)

**main.py** - FastAPI server with integrated MCP (Memory, Continuity, Protocol) endpoints providing conversation memory and context via RESTful HTTP API. Uses **SQLAlchemy database** (`db/chat_bridge.db`) for unified data storage.

**MCP HTTP Endpoints (RESTful API):**
- `GET /api/mcp/health` - Health check and server status
- `GET /api/mcp/stats` - Database statistics (conversation and message counts)
- `GET /api/mcp/recent-chats?limit=N` - Query recent conversations from database
- `GET /api/mcp/search-chats?keyword=X&limit=N` - Search messages by keyword
- `GET /api/mcp/contextual-memory?topic=X&limit=N` - Retrieve topic-relevant memories
- `GET /api/mcp/conversation/{id}` - Get full conversation details by ID

**MCP Integration in chat_bridge.py:**
- Supports two modes via `MCP_MODE` environment variable:
  - **http** (default): RESTful HTTP API via FastAPI server - scalable, recommended
  - **stdio**: FastMCP stdio transport for MCP client compatibility
- Uses `httpx` HTTP client for async communication with FastAPI server (http mode)
- Uses subprocess with JSON-RPC for stdio mode communication
- Configurable via `MCP_BASE_URL` environment variable (default: `http://localhost:8000`)
- Async-first implementation with sync wrappers for compatibility
- Functions: `query_mcp_memory()`, `get_recent_conversations()`, `check_mcp_server()`
- Enhances agent prompts with relevant historical context when `--enable-mcp` flag is used
- Continuous memory integration provides fresh context on every conversation turn

**MCP Server Options:**
- **main.py** - FastAPI HTTP server (recommended, use with `MCP_MODE=http`)
- **mcp_server.py** - FastMCP stdio server (legacy, use with `MCP_MODE=stdio`)
- **check_mcp_status.py** - Legacy status checker for stdio-based MCP

**Migration History:**
- **v1.0 (Early 2025)**: Flask REST API with basic memory endpoints
- **v1.1 (2025-10-15)**: Migrated to FastMCP 2.0 with stdio transport for standards compliance
- **v1.2 (2025-10-15)**: Migrated to HTTP-based integration via main.py FastAPI server for better scalability, unified database, and RESTful API access
- **v1.4 (2025-10-16)**: Improved MCP server import fallback handling and enhanced HTTP integration
- **v1.4.1 (2025-10-21)**: Fixed provider status endpoint and improved credential validation

### Web GUI Architecture

**web_gui/backend/main.py** - FastAPI server providing RESTful API and WebSocket support for real-time conversations. Imports bridge_agents functionality and exposes endpoints:
- `GET /api/providers` - List available AI providers
- `GET /api/provider-status` - Check provider credential status (validates API keys)
- `GET /api/personas` - Load personas from roles.json
- `POST /api/conversations` - Create conversation session
- `WS /ws/conversations/{id}` - WebSocket for streaming messages

**web_gui/frontend/** - React + TypeScript + Tailwind CSS frontend with **retro aesthetic**:
- **🎨 Retro Design System**: Classic retro button styling with outset/inset effects and gray color palette
- **🪟 Window Interface**: Traditional window-like interface with title bars and system styling
- **📜 Custom Scrollbars**: Classic gray scrollbars throughout
- **💭 Message Bubbles**: Vintage computer chat bubble design
- **🎭 4-Step Setup Flow**: Persona → Provider → Settings → Start conversation wizard
- **⚡ Real-Time Streaming**: WebSocket-powered live AI response streaming
- **📱 Responsive Layout**: Adapts beautifully across all device sizes

### Provider System

The system uses a plugin-style provider architecture. Each provider is registered in `PROVIDER_REGISTRY` (bridge_agents.py:47) with:
- `key`: Provider identifier (openai, anthropic, gemini, ollama, lmstudio, deepseek, openrouter)
- `kind`: API type (chatml, anthropic, gemini, ollama, openai)
- `default_model`: Model name (overridable via env vars like `OPENAI_MODEL`)
- `needs_key`: Whether API key is required
- `key_env`: Environment variable name for API key

**OpenRouter Provider**: Special provider that provides access to 200+ models through a unified API. Uses OpenAI-compatible chat completions format with additional headers (`HTTP-Referer`, `X-Title`) for app identification. Features categorized model browsing with provider filtering detection.

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

# Optional: OpenRouter - Access 200+ models through unified API
OPENROUTER_API_KEY=sk-or-v1-...
OPENROUTER_MODEL=openai/gpt-4o-mini
OPENROUTER_APP_NAME="Chat Bridge"  # Optional: appears in OpenRouter logs
OPENROUTER_REFERER="https://github.com/yourusername/chat-bridge"  # Optional
OPENROUTER_BASE_URL=https://openrouter.ai/api/v1  # Optional: override base URL

# Optional: Local provider URLs
OLLAMA_HOST=http://localhost:11434
LMSTUDIO_BASE_URL=http://localhost:1234/v1
DEEPSEEK_BASE_URL=https://api.deepseek.com/v1

# Optional: MCP Memory System
MCP_MODE=http  # "http" (FastAPI server, recommended) or "stdio" (FastMCP stdio server)
MCP_BASE_URL=http://localhost:8000  # FastAPI server URL for MCP endpoints

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
- `fastmcp` - Model Context Protocol (MCP) server and client framework

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

### Working with MCP Memory System

The MCP memory system provides contextual memory to AI conversations. It supports two modes:

**Mode 1: HTTP (Recommended, Default)**

Start the FastAPI server with MCP endpoints:

```bash
# Set mode in .env
MCP_MODE=http

# Start the FastAPI server
python main.py

# Or use uvicorn for development with auto-reload
uvicorn main:app --reload --host 0.0.0.0 --port 8000
```

**Mode 2: stdio (Legacy, for MCP client compatibility)**

Uses FastMCP stdio server for direct MCP protocol communication:

```bash
# Set mode in .env
MCP_MODE=stdio

# The stdio server process is started automatically by chat_bridge.py
# Or start manually for testing:
python mcp_server.py
```

**Checking MCP status (HTTP mode):**

```bash
# Health check
curl http://localhost:8000/api/mcp/health

# Database statistics
curl http://localhost:8000/api/mcp/stats

# Test recent chats
curl "http://localhost:8000/api/mcp/recent-chats?limit=5"
```

**Checking MCP status (stdio mode):**

```bash
# Test stdio server manually
python mcp_server.py
# Then send JSON-RPC requests via stdin
```

**Using MCP in conversations:**

```bash
# Make sure the FastAPI server is running first!

# Enable MCP memory integration
python chat_bridge.py --enable-mcp

# Or use interactive mode and enable MCP when prompted
python chat_bridge.py
```

**Key features:**
- **Dual mode support**: Choose between HTTP (scalable) or stdio (MCP standard)
- **HTTP mode**: RESTful API endpoints accessible from anywhere
- **stdio mode**: FastMCP protocol-compliant for integration with MCP clients
- **Unified database**: Single SQLAlchemy database for all conversation data
- **Scalable**: FastAPI server handles multiple concurrent MCP requests (HTTP mode)
- **Continuous memory**: Fresh context retrieved on every conversation turn
- **Topic-based search**: Automatically finds relevant past conversations
- **6 HTTP endpoints**: Health, stats, recent chats, search, contextual memory, conversation details

**Configuration:**

Set the MCP mode and server URL via environment variables:

```bash
# Choose mode: "http" (recommended) or "stdio"
export MCP_MODE=http

# Set server URL (HTTP mode only)
export MCP_BASE_URL=http://localhost:8000

# Or set in .env file
echo "MCP_MODE=http" >> .env
echo "MCP_BASE_URL=http://localhost:8000" >> .env
```

**Troubleshooting MCP:**

*HTTP mode:*
- Ensure FastAPI server is running: `curl http://localhost:8000/health`
- Check MCP endpoints are accessible: `curl http://localhost:8000/api/mcp/health`
- Verify database exists with data: `curl http://localhost:8000/api/mcp/stats`
- Check server logs for errors if MCP queries fail

*stdio mode:*
- Check if mcp_server.py process is running
- Review stderr output for errors
- Verify database file `bridge.db` exists and is accessible

*Both modes:*
- MCP integration gracefully degrades if server unavailable
- Conversations will continue without memory context if MCP fails

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

**Web GUI Endpoints:**
- `GET /` - Health check returning "Chat Bridge Web API"
- `GET /health` - Server health check
- `GET /api/providers` - Returns list of available providers with labels and descriptions
- `GET /api/provider-status` - Returns credential validation status for each provider
- `GET /api/personas` - Returns persona library from roles.json
- `POST /api/conversations` - Creates conversation, returns conversation_id
- `GET /api/conversations` - List conversations with optional search
- `GET /api/conversations/{id}` - Get conversation details
- `WS /ws/conversations/{id}` - WebSocket stream for messages

**MCP Memory Endpoints:**
- `GET /api/mcp/health` - MCP server health check and status
- `GET /api/mcp/stats` - Database statistics (conversation and message counts)
- `GET /api/mcp/recent-chats?limit=N` - Get N most recent conversations
- `GET /api/mcp/search-chats?keyword=X&limit=N` - Search conversations by keyword
- `GET /api/mcp/contextual-memory?topic=X&limit=N` - Get topic-relevant conversation memories
- `GET /api/mcp/conversation/{id}` - Get full conversation details by ID

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

### Cyberpunk Frontend Structure

- `src/App.tsx` - Main cyberpunk application with particle effects, aurora backgrounds, and 4-step setup flow
- `src/types/` - TypeScript interfaces for Personas, Messages, and WebSocket data structures  
- `tailwind.config.js` - Tailwind CSS with custom cyberpunk color scheme and animations
- `vite.config.ts` - Vite build configuration optimized for cyberpunk visual assets

**💡 Retro Design Features:**
- **3D Button Effects**: Classic outset/inset button styling for authentic retro feel
- **Window-Like Interface**: Familiar window management and dialog boxes
- **Vintage Color Palette**: Classic gray backgrounds, blue title bars, and system colors
- **Old-School Fonts**: MS Sans Serif and VT323 nostalgic typography
- **Bubble Messages**: Vintage computer chat bubble design with retro aesthetics
- **Custom Scrollbars**: Classic gray scrollbar styling
- **Throwback Animations**: Pulse effects and smooth transitions from the 90s

## Known Issues and Limitations

- **Gemini**: Strict rate limits on free tier (15 RPM)
- **Ollama**: Requires manual model installation via `ollama pull <model>`
- **LM Studio**: Requires manual server startup and model loading
- **Long conversations**: Token limits vary by provider (OpenAI 128k, Anthropic 200k, Gemini 1M context)
- **Stop words**: Case-sensitive matching; lowercase recommended
- **Web GUI**: Fully functional retro interface with all features implemented - real-time streaming, persona selection, provider flexibility, and nostalgic aesthetic

## Code Style Notes

- Use Python 3.10+ features (type hints with `from __future__ import annotations`)
- Async/await for all provider interactions
- ANSI color codes defined at top of chat_bridge.py for consistent terminal output
- Error handling: catch provider-specific errors and provide actionable troubleshooting
- Logging: use Python `logging` module, not print statements for debug info
- Type hints: use dataclasses for structured data (Turn, ProviderSpec, AgentRuntime)
