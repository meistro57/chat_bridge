# 🚀 Quick Start Guide

Get Chat Bridge up and running in seconds!

## One-Command Startup

```bash
./start.sh
```

That's it! The startup script handles everything:
- ✅ Checks dependencies
- ✅ Starts FastAPI backend (includes MCP HTTP server)
- ✅ Serves the retro web GUI
- ✅ Shows live status and logs

## Access Your Services

Once started, open your browser:

- **Web GUI**: http://localhost:8000
- **API Docs**: http://localhost:8000/docs
- **MCP Health**: http://localhost:8000/api/mcp/health

## Development Mode

For frontend development with hot-reload:

```bash
./start.sh --dev
```

This starts:
- Backend on port 8000
- Vite dev server on port 5173 (with hot-reload)

## Stop Everything

```bash
./stop.sh
```

Cleanly stops all services.

## First Time Setup

### 1. Install Dependencies

```bash
# Backend dependencies
pip install -r requirements.txt

# Frontend dependencies (for dev mode)
cd web_gui/frontend
npm install
cd ../..
```

### 2. Configure API Keys

Copy `.env.example` to `.env` and add your API keys:

```bash
cp .env.example .env
nano .env  # or your preferred editor
```

Required API keys (add at least one):
```env
OPENAI_API_KEY=sk-...
ANTHROPIC_API_KEY=sk-ant-...
GEMINI_API_KEY=...
```

### 3. Build Frontend (Production Mode)

```bash
cd web_gui/frontend
npm run build
cd ../..
```

### 4. Start Chat Bridge

```bash
./start.sh
```

## What Gets Started

The `start.sh` script starts:

1. **FastAPI Backend Server** (`main.py`)
   - Web GUI API endpoints
   - MCP HTTP memory endpoints
   - WebSocket support for real-time conversations
   - Static file serving for frontend

2. **MCP Memory System** (included in backend)
   - HTTP mode (default): RESTful API endpoints
   - stdio mode: Subprocess spawned automatically if configured

3. **Frontend** (production: served from dist/, dev: Vite server)

## Troubleshooting

### Port 8000 Already in Use

```bash
./stop.sh  # Stop any existing instances
./start.sh  # Start fresh
```

### Missing Dependencies

The script will automatically install missing dependencies, or run:

```bash
pip install -r requirements.txt
```

### Frontend Not Found

If you see "Frontend not built" warnings:

```bash
cd web_gui/frontend
npm install
npm run build
cd ../..
./start.sh
```

### Check Logs

```bash
# Backend log
tail -f logs/backend.log

# Frontend log (dev mode only)
tail -f logs/frontend.log
```

## MCP Mode Configuration

Chat Bridge supports two MCP modes (configured in `.env`):

### HTTP Mode (Default, Recommended)

```env
MCP_MODE=http
MCP_BASE_URL=http://localhost:8000
```

- RESTful HTTP API
- Scalable, concurrent requests
- Built into main.py backend

### stdio Mode (Legacy)

```env
MCP_MODE=stdio
```

- FastMCP protocol-compliant
- Subprocess spawned automatically
- Uses mcp_server.py

See `MCP_MODE_GUIDE.md` for detailed information about MCP modes.

## CLI Usage

To use the command-line interface instead of the web GUI:

```bash
# Interactive mode
python chat_bridge.py

# Direct conversation
python chat_bridge.py \
  --provider-a openai \
  --provider-b anthropic \
  --starter "What is consciousness?" \
  --max-rounds 10

# With MCP memory
python chat_bridge.py --enable-mcp
```

## Next Steps

1. **Explore Personas** - Click "Choose a persona" in the web GUI
2. **Try Different Providers** - OpenAI, Anthropic, Gemini, OpenRouter, etc.
3. **Adjust Settings** - Max rounds, temperature, conversation style
4. **Download Transcripts** - Click "📄 Transcript" after conversations
5. **Read Guides** - Click "📚 Help" for comprehensive documentation

## Advanced Usage

### Custom Personas

Create custom AI personas:

```bash
python roles_manager.py
```

### Provider Testing

Test all configured providers:

```bash
python chat_bridge.py
# Select option 3: Test Provider Connectivity
```

### Certification Suite

Run comprehensive tests:

```bash
python certify.py
```

## Environment Variables

Key configuration options in `.env`:

```env
# MCP Memory System
MCP_MODE=http  # or "stdio"
MCP_BASE_URL=http://localhost:8000

# Provider API Keys
OPENAI_API_KEY=sk-...
ANTHROPIC_API_KEY=sk-ant-...
GEMINI_API_KEY=...
DEEPSEEK_API_KEY=sk-...
OPENROUTER_API_KEY=sk-or-v1-...

# Model Overrides (optional)
OPENAI_MODEL=gpt-4o-mini
ANTHROPIC_MODEL=claude-3-5-sonnet-20241022
GEMINI_MODEL=gemini-2.5-flash
```

## Support

- **Documentation**: Click "📚 Help" in the web GUI
- **Issues**: Check logs in `logs/` directory
- **Guides**: See `CLAUDE.md`, `MCP_MODE_GUIDE.md`, `README.md`

---

**Pro Tip**: Use `./start.sh --dev` for development and `./start.sh` for production. The dev mode provides hot-reload for frontend changes!
