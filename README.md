# 🌉 Chat Bridge - Unified Edition: Retro Edition 🎨⚡🤖
https://forum.quantummindsunited.com/t/the-chatbridge-project/66?u=meistro
A beautiful, interactive chat bridge that connects two AI assistants with **retro Windows 95-style web GUI** and enhanced CLI experience! Watch AI assistants converse with **real-time streaming**, facial expressions, and comprehensive transcripts and SQLite storage.
<img width="1132" height="1759" alt="image" src="https://github.com/user-attachments/assets/0c513cb4-8c29-43f8-bd93-57d3cacc0a80" />

## ✨ What's New in Version 1.4.3 Luxury Gaming-Grade UX Overhaul 🎮🎨
Experience the **most advanced AI conversation platform** with cutting-edge user experience that rivals commercial gaming and chat applications!

### 🖱️ Multi-Phase Typing Indicators
- **Thinking Phase:** Water droplet animation when AI starts processing
- **Composing Phase:** Horizontal dot animation during response generation  
- **Sending Phase:** Chevron animations as message delivers
- **Agent-Specific:** Personalized feedback showing which AI is thinking

### 📜 Smart Auto-Scroll System  
- **Predictive Scrolling:** Automatically follows conversation flow
- **Position Awareness:** Shows unread message count when scrolled up
- **Manual Control:** Toggle auto-scroll on/off with blue unread counter button
- **Smooth Animations:** Never-jarring, bounce-free scrolling experience

### ⚡ Quick Actions Menu (Hover-Powered)
- **Instant Copy:** One-click clipboard with retro button styling
- **Bookmark System:** Save favorite exchanges to local storage
- **Like Function:** Positive feedback with heart animation
- **Context Menu:** Hover on any message for instant interactions

### 🎨 Advanced Theming Engine (3 Professional Themes)
- **Retro Windows:** Classic beige/sky blue Windows 95 aesthetic (default)
- **Cyberpunk:** Neon pink/green matrix coding style
- **Vaporwave:** Purple/cyan 2000s nostalgic gradient themes
- **Dynamic Switching:** One-click theme changes via 🎨 palette button

### 💾 Enterprise Conversation Persistence
- **Auto-Save Every 60s:** Never lose conversations due to crashes
- **Draft Recovery:** Message drafts preserved across browser sessions
- **Crash Protection:** Automatic recovery of last active conversation
- **Conversation Browser:** History with metadata, timestamps, personas

### 🔊 Immersive Sound Design (Optional)
- **Context-Aware Audio:** New message pings, typing sounds, action confirmations
- **Volume Control:** Fine-tuned audio levels with persistent settings
- **Retro Sound Effects:** Nostalgic 90s computing audio cues
- **Toggle Controls:** Easy on/off with visual feedback

## ✨ What's New in Version 1.4.0 **Retro Edition** 🎨⚡🤖

### 🌈 **Retro Web GUI - Nostalgic AI Conversations**
- **🎭 Intimate 4-Step Setup**: Persona → Provider → Settings → Start flow
- **⚡ Real-Time Streaming**: Watch AI responses appear live as they're generated
- **🎨 Retro Computing Aesthetic**: Classic retro-inspired interface with beveled buttons, classic color schemes, and throwback design
- **🏙️ Immersive Experience**: Window-like interface, scrollbars, and vintage computer styling
- **🎯 Dual Provider Selection**: Choose any combination of AI providers (OpenAI, Anthropic, Gemini, OpenRouter, etc.)
- **🌡️ Advanced Controls**: Adjustable max rounds, temperature settings per agent
- **🔄 Instant Conversations**: Click⚡ to launch WebSocket-streaming AI dialogues
- **📱 Modern Interface**: React + TypeScript + Tailwind CSS for smooth, professional experience
- **🎪 Modal Persona Selection**: Interactive persona selection with descriptions and previews
- **📊 Live Status**: Real-time connection indicators and typing animations
- **🎨 Classic Color Scheme**: Classic grays, blues, and system colors throughout
- **⚡ Quick Startup Script**: New `start_web_gui.sh` for easy single-command startup

### 🚀 **Multiple Interface Options**
- **🌐 Web GUI**: Modern browser interface (recommended for visual experience)
- **💻 CLI Mode**: Traditional command-line interface (always reliable)
- **🔌 Hybrid**: Switch between interfaces based on your needs

### 📝 **Enhanced Retro Features**
- **Window-Like Menus**: Classic window management with title bars and buttons
- **3D Button Effects**: Outset/inset button styling for authentic retro feel
- **Scrollbar Styling**: Classic gray scrollbars throughout the interface
- **Bubble Messages**: Vintage chat bubble design with retro colors
- **Animated Elements**: Pulsing status indicators and smooth transitions
- **Responsive Design**: Adapts beautifully to desktop, tablet, and mobile
- **WebSocket Streaming**: Ultra-fast real-time message delivery
- **Visual Feedback**: Typing indicators, connection status, hover effects

### 📝 **Enhanced Transcript Features**
- **🔢 Round Markers** - Conversation turns now include visible round numbers in transcripts for easy tracking
- **👤 Persona Names** - Speaker labels now display persona names (e.g., "Steel Worker") instead of generic "Agent A"/"Agent B" labels

### 🔧 **Previous Version Highlights (1.4.0)**
- **🎭 Enhanced Persona Library** - Added DeepSeek, ADHD Kid, and Complainer personas for diverse conversation dynamics
- **⚙️ Improved Roles Management** - Updated roles_manager.py with better persona handling and configuration options
- **🛠️ Additional Utilities** - Added check_port.py for database connectivity testing
- **📦 Version Management** - Centralized version string in version.py for better release tracking
- **🔄 Stop Word Detection Toggle** - Enable/disable conversation termination control through interactive menu
- **📝 Enhanced Session Transcripts** - Comprehensive session configuration tracking in transcript headers

### 🛠️ **Core Features**
- **✨ Custom Role Creation** - Create fully customizable AI roles with user-defined settings
- **🎭 Enhanced Role Modes** - Multiple preset personas including Scientist, Philosopher, Comedian, Steel Worker, DeepSeek Strategist, ADHD Kid, and more
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
- **🛠️ Utility Scripts** - Standalone roles manager, port connectivity checker, and comprehensive certification suite

## 🚀 Quick Start

### 🌐 **Web GUI (Retro Computing Experience) - Recommended**

The new retro web interface provides a nostalgic AI conversation experience:

```bash
# 🚀 One-Command Startup (Recommended)
./start.sh

# Development mode with hot-reload
./start.sh --dev

# Stop all services
./stop.sh
```

**What gets started:**
- ✅ FastAPI backend server (includes MCP HTTP endpoints)
- ✅ Retro web GUI at http://localhost:8000
- ✅ MCP memory system (HTTP or stdio mode)
- ✅ Live status monitoring and logs

**See [QUICKSTART.md](QUICKSTART.md) for detailed instructions**

**Alternative: Manual startup**
```bash
# Backend only
python main.py

# Frontend development
cd web_gui/frontend
npm run dev
# Open: http://localhost:5173
```

**🌟 Retro GUI Features:**
- **🎨 Retro Aesthetic**: Classical retro-inspired design with beveled buttons, classic colors, and window-like interface
- **⚡ Live Streaming**: Watch AI responses appear in real-time as they're generated
- **🎭 32 Personas**: Scientist, Philosopher, Comedian, Steel Worker, and many more
- **🚀 Multiple Providers**: OpenAI, Anthropic, Gemini, OpenRouter, Ollama, LM Studio
- **🌡️ Advanced Controls**: Temperature, max rounds, and conversation settings
- **📱 Responsive**: Works perfectly on desktop, tablet, and mobile devices

### 💻 **CLI Interface (Traditional)**

**Option 1: Interactive Launcher (Recommended)**
```bash
python launch.py
```

**Option 2: Roles Manager (Standalone)**
```bash
python roles_manager.py
```

**Option 3: Direct Interactive Mode**
```bash
python chat_bridge.py
```

**Option 4: Command Line**
```bash
python chat_bridge.py --provider-a openai --provider-b anthropic --starter "What is consciousness?"
```

## 🛠️ Utility Scripts

### Roles Manager (Standalone)
```bash
python roles_manager.py
```
Dedicated interface for creating, editing, and managing AI personas independently of the main chat bridge.

### Port Connectivity Checker
```bash
python check_port.py
```
Database connectivity tester for MySQL/MariaDB connections with detailed diagnostics.

### Certification Suite
```bash
python certify.py
```
Comprehensive automated testing and certification system for validating the entire Chat Bridge installation.

## Features at a Glance

- **Multi-provider bridge** – choose any combination of OpenAI, Anthropic, Gemini,
  DeepSeek, Ollama, LM Studio, or **OpenRouter** for Agent A and Agent B.
- **Turbo defaults** – out of the box the scripts target GPT-4o Mini, Claude 3.5 Sonnet (Oct 2024),
  Gemini 2.5 Flash, llama3.1 8B (Ollama), and LM Studio's meta-llama3 instruct build.
- **OpenRouter integration** – access 200+ AI models through a unified API with categorized browsing and model discovery.
- **MCP Memory System** – HTTP-based memory integration provides contextual awareness across conversations using RESTful API endpoints.
- **Interactive setup** – each run offers a multiple-choice picker for providers and
  models alongside CLI flags and environment overrides.
- **Streaming transcripts** – watch tokens arrive live, capture the Markdown transcript,
  and persist structured logs in SQLite plus optional `.log` files.
- **Loop + stop guards** – configurable stop phrases and repetition detection end the
  chat gracefully.
- **Versioned releases** – the project now exposes a semantic version (`--version`) so you
  can keep track of updates.

## Requirements

**For CLI (Python only):**
- Python 3.10 or newer
- Dependencies: `httpx`, `python-dotenv`, `google-generativeai`, `inquirer` (install via `pip install -r requirements.txt`)

**For Web GUI (Full Experience):**
- All Python requirements above
- **Web GUI Backend**: `fastapi`, `uvicorn[standard]`, `websockets`, `pydantic` (install via `pip install -r web_gui/backend/requirements.txt`)
- **Web GUI Frontend**: Node.js 16+, npm (install via `cd web_gui/frontend && npm install`)
- **Web GUI Build**: Modern browser with WebSocket support

### API Keys Setup

Create a `.env` file in the project root with your API keys:

```bash
# Primary API Keys
OPENAI_API_KEY=sk-proj-...
ANTHROPIC_API_KEY=sk-ant-...
GEMINI_API_KEY=AIza...
DEEPSEEK_API_KEY=sk-...

# OpenRouter - Access 200+ models through unified API
OPENROUTER_API_KEY=sk-or-v1-...
OPENROUTER_MODEL=openai/gpt-4o-mini
OPENROUTER_APP_NAME="Chat Bridge"  # Optional: appears in OpenRouter logs
OPENROUTER_REFERER="https://github.com/yourusername/chat-bridge"  # Optional

# Model Configuration
OPENAI_MODEL=gpt-4o-mini
ANTHROPIC_MODEL=claude-3-5-sonnet-20241022
GEMINI_MODEL=gemini-2.5-flash
DEEPSEEK_MODEL=deepseek-chat

# Local Model Hosts (optional)
OLLAMA_HOST=http://localhost:11434
OLLAMA_MODEL=llama3.1:8b-instruct
LMSTUDIO_BASE_URL=http://localhost:1234/v1
LMSTUDIO_MODEL=lmstudio-community/Meta-Llama-3-8B-Instruct-GGUF

# MCP Memory System (HTTP-based)
MCP_BASE_URL=http://localhost:8000  # FastAPI server for conversation memory
```

## Running the Bridge

### Interactive Mode (Recommended)

```bash
python chat_bridge.py
```

You'll see beautiful colored menus guiding you through:

### 🚀 Main Menu Options (v1.3.0):
1. **Start Conversation - Simple Setup (Step-by-step)** - New streamlined configuration flow
2. **Manage Roles & Personas** - Interactive roles.json configuration and persona creation
3. **Test Provider Connectivity** - Diagnose and test AI provider connections
4. **Exit** - Gracefully exit the application

### 💬 Simple Setup Flow (New in v1.3.0):

The new simplified turn-based configuration walks you through setting up each agent:

#### 🎭 Agent A Configuration:
1. **Step 1: Select Persona** - Choose from persona library or skip for defaults
2. **Step 2: Select Provider** - Choose AI provider (OpenAI, Anthropic, Gemini, etc.)
3. **Step 3: Select Model** - Choose from dynamically fetched available models
4. **Step 4: Set Temperature** - Enter temperature (default: 0.6 for balanced responses)

#### 🎭 Agent B Configuration:
1. **Step 1: Select Persona** - Choose from persona library or skip for defaults
2. **Step 2: Select Provider** - Choose AI provider (OpenAI, Anthropic, Gemini, etc.)
3. **Step 3: Select Model** - Choose from dynamically fetched available models
4. **Step 4: Set Temperature** - Enter temperature (default: 0.6 for balanced responses)

#### 💬 Start Conversation:
1. **Conversation Starter** - Enter your discussion topic
2. **Live Conversation** - Watch the AI assistants converse with real-time streaming

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
  • OpenAI (gpt-4o-mini) - 245ms

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

- `transcripts/<timestamp>__<starter-slug>.md` – Enhanced Markdown transcript with complete session configuration, round markers, and persona names
- `logs/<timestamp>__<starter-slug>.log` – optional structured per-session log.
- `chat_bridge.log` – global append-only log capturing request IDs and errors.
- `bridge.db` – SQLite database containing metadata plus turn-by-turn content.

### 📝 Enhanced Transcript Features
- **Round Markers** - Each conversation turn is prefixed with `**Round N**` for easy tracking and navigation
- **Persona Names** - When personas are selected, they appear as speaker labels (e.g., "Steel Worker" instead of "Agent A")
- **Session Configuration Header** - Complete configuration details including providers, models, temperatures
- **Agent Configuration** - Detailed settings for both agents including personas and system prompts
- **Session Settings** - Max rounds, memory rounds, and stop word detection status
- **Stop Words List** - Active stop words with current detection status
- **Timestamps** - Each turn includes precise timestamps for debugging and analysis
- **Structured Format** - Clear sections for easy navigation and analysis

Legacy transcripts from earlier experiments may be stored in `chatlogs/`; current scripts
write to `transcripts/` automatically.

## Running Longer Sessions

- Increase `--max-rounds` (e.g. `--max-rounds 200`).
- Raise `--mem-rounds` if you want each model to retain more context (values between
  `12`–`20` work well).
- Monitor token budgets: OpenAI GPT-4o Mini typically caps around 128k tokens, Anthropic
  Claude models around 200k, and Gemini 2.5 Flash around 1M context (depending on release).

## Troubleshooting

### 🏆 Comprehensive Certification

Run the automated certification script to validate your entire Chat Bridge installation:

```bash
python certify.py
```

**Enhanced Features:**
- 🔍 **Detailed provider identification** with specific AI model names (GPT-4o Mini, Claude 3.5 Sonnet, Gemini 2.5 Flash, etc.)
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
- **Access Forbidden (403)**: API key lacks model permissions, try different model (e.g., gpt-4o-mini)
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

#### 🔍 DeepSeek
- **Invalid API Key (401)**: Verify `DEEPSEEK_API_KEY` is set correctly, ensure key is valid and active
- **Access Forbidden (403)**: Check API key permissions, verify account status
- **Rate Limited (429)**: Wait before retrying, check usage limits, consider API tier upgrade
- **Network Issues**: Verify connection to DeepSeek API endpoint

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

#### 🔀 OpenRouter
- **Invalid API Key (401)**: Verify `OPENROUTER_API_KEY`, ensure key is valid at openrouter.ai/keys
- **Provider Filtering (404)**: Model's provider is blocked in OpenRouter settings, visit https://openrouter.ai/settings/preferences to adjust
- **Model Not Found (404)**: Check model ID format (e.g., `openai/gpt-4o-mini`, `anthropic/claude-3-5-sonnet`)
- **Rate Limited (429)**: Wait before retrying, check credits at openrouter.ai/account
- **Network Issues**: Verify connection to openrouter.ai API endpoint

### 🧠 MCP Memory System

The MCP (Memory, Continuity, Protocol) system provides conversation memory via HTTP-based RESTful API:

**Starting MCP:**
```bash
# Start the FastAPI server with MCP endpoints
python main.py

# Or use uvicorn for development
uvicorn main:app --reload --host 0.0.0.0 --port 8000
```

**MCP Features:**
- **HTTP-based integration**: RESTful API endpoints for memory operations
- **Unified database**: SQLAlchemy-powered storage with SQLite backend
- **6 endpoints**: Health, stats, recent chats, search, contextual memory, conversation details
- **Continuous memory**: Fresh context retrieved on every conversation turn
- **Graceful degradation**: Conversations work without MCP if server unavailable

**Using MCP in conversations:**
```bash
# Enable MCP memory integration (requires MCP server running)
python chat_bridge.py --enable-mcp

# Check MCP status
curl http://localhost:8000/api/mcp/health
curl http://localhost:8000/api/mcp/stats
```

**MCP Troubleshooting:**
- Ensure FastAPI server is running: `curl http://localhost:8000/health`
- Check MCP endpoints are accessible: `curl http://localhost:8000/api/mcp/health`
- Verify database exists with data: `curl http://localhost:8000/api/mcp/stats`
- MCP integration gracefully degrades if server unavailable
- Check server logs if MCP queries fail

Happy bridging!

