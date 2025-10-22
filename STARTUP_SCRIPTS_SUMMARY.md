# Startup Scripts Summary

## ✅ What Was Created

### 1. **start.sh** - Unified Startup Script
A comprehensive script that starts everything with one command:

```bash
./start.sh          # Production mode
./start.sh --dev    # Development mode with hot-reload
```

**Features:**
- ✅ Checks all dependencies automatically
- ✅ Installs missing packages if needed
- ✅ Detects and handles port conflicts
- ✅ Starts FastAPI backend (includes MCP HTTP endpoints)
- ✅ Serves retro web GUI
- ✅ Optional Vite dev server for frontend development
- ✅ Color-coded status messages
- ✅ Live log tailing in dev mode
- ✅ Graceful shutdown on Ctrl+C

**What it starts:**
1. FastAPI backend on port 8000
   - Web GUI API endpoints
   - MCP HTTP memory system
   - WebSocket support
   - Static file serving
2. Vite dev server on port 5173 (dev mode only)

### 2. **stop.sh** - Clean Shutdown Script
Stops all Chat Bridge services cleanly:

```bash
./stop.sh
```

**Stops:**
- FastAPI backend (main.py)
- Vite dev server (if running)
- MCP stdio processes (if running)

### 3. **QUICKSTART.md** - Getting Started Guide
Comprehensive quick start documentation covering:
- One-command startup
- First-time setup
- Development mode
- Troubleshooting
- MCP mode configuration
- Environment variables
- CLI usage

### 4. **Updated README.md**
- Added prominent one-command startup section
- References to new scripts and QUICKSTART.md
- Clear distinction between production and dev modes

### 5. **Updated Web GUI**
- Added QUICKSTART.md to the guides menu
- Now accessible via 📚 Help button

## 🚀 Usage Examples

### Quick Start (Production)
```bash
# First time setup
cp .env.example .env
# Edit .env and add your API keys
pip install -r requirements.txt
cd web_gui/frontend && npm install && npm run build && cd ../..

# Start everything
./start.sh

# Open browser
# http://localhost:8000
```

### Development Mode
```bash
# Start with hot-reload
./start.sh --dev

# Frontend: http://localhost:5173 (hot-reload)
# Backend:  http://localhost:8000
```

### Stop Everything
```bash
./stop.sh
```

## 📊 Script Comparison

| Feature | start.sh | stop.sh | Manual |
|---------|----------|---------|--------|
| Starts Backend | ✅ | - | ✅ |
| Starts Frontend | ✅ (dev mode) | - | ✅ |
| Dependency Checks | ✅ | - | ❌ |
| Port Conflict Handling | ✅ | - | ❌ |
| MCP Integration | ✅ | - | ✅ |
| Color-Coded Output | ✅ | ✅ | ❌ |
| Graceful Shutdown | ✅ | ✅ | ❌ |
| Live Logs | ✅ | - | ❌ |

## 🎯 Benefits

### For Users
- **One Command**: No need to remember complex startup sequences
- **Automatic Setup**: Checks and installs dependencies
- **Clear Feedback**: Color-coded status messages
- **Error Handling**: Handles port conflicts and missing files
- **Documentation**: QUICKSTART.md has everything needed

### For Developers
- **Dev Mode**: Hot-reload for rapid development
- **Live Logs**: See backend logs in real-time
- **Clean Shutdown**: Ctrl+C stops everything cleanly
- **Flexible**: Can still use manual commands if needed

## 📝 Log Files

Both scripts create log files in the `logs/` directory:

```
logs/
├── backend.log    # FastAPI server logs
└── frontend.log   # Vite dev server logs (dev mode only)
```

## 🔧 Configuration

The scripts respect your `.env` configuration:

```env
# MCP Mode (http or stdio)
MCP_MODE=http
MCP_BASE_URL=http://localhost:8000

# API Keys
OPENAI_API_KEY=sk-...
ANTHROPIC_API_KEY=sk-ant-...
# etc.
```

## 🌐 Services Started

When you run `./start.sh`, these services become available:

| Service | URL | Description |
|---------|-----|-------------|
| Web GUI | http://localhost:8000 | Retro interface |
| API Docs | http://localhost:8000/docs | Interactive API documentation |
| MCP Health | http://localhost:8000/api/mcp/health | MCP server status |
| MCP Stats | http://localhost:8000/api/mcp/stats | Database statistics |
| Guides API | http://localhost:8000/api/guides | Documentation endpoints |
| Dev Server* | http://localhost:5173 | Frontend with hot-reload |

*Only in `--dev` mode

## 💡 Tips

1. **First Time**: Use production mode (`./start.sh`)
2. **Development**: Use dev mode (`./start.sh --dev`) for frontend work
3. **Troubleshooting**: Check `logs/backend.log` for errors
4. **Clean Start**: Run `./stop.sh` then `./start.sh`
5. **Help**: Click 📚 Help in the web GUI for more guides

## 🎉 What This Means

You now have a **professional, production-ready startup experience** for Chat Bridge:

- ✅ No more "which terminal is which?"
- ✅ No more "did I start the MCP server?"
- ✅ No more "why isn't port 8000 working?"
- ✅ Everything just works™

## 📚 Related Documentation

- **QUICKSTART.md** - Comprehensive quick start guide
- **README.md** - Full project overview
- **CLAUDE.md** - Development documentation
- **MCP_MODE_GUIDE.md** - MCP mode configuration

---

**Created**: 2025-10-22
**Version**: 1.4.2
**Scripts**: `start.sh`, `stop.sh`, `QUICKSTART.md`
