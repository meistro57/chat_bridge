# Chat Bridge Web GUI - Retro Edition

Nostalgic retro web interface for the Chat Bridge AI conversation platform featuring classic beveled buttons, gray color schemes, and authentic retro computing aesthetic.

## Architecture

### Backend (FastAPI)
- **API Server**: RESTful endpoints for persona management and conversation creation
- **WebSocket Server**: Real-time bidirectional communication for live conversations
- **MCP Memory Integration**: HTTP-based memory endpoints for contextual conversation awareness
- **Persona Management**: Dynamic loading and validation from `roles.json`
- **Multi-Provider Support**: All 7 AI providers (OpenAI, Anthropic, Gemini, DeepSeek, Ollama, LM Studio, OpenRouter)

### Frontend (React + TypeScript + Tailwind)
- **Retro Design**: Classic beveled buttons, gray backgrounds, window-like interface
- **4-Step Setup Wizard**: Persona → Provider → Settings → Start conversation flow
- **Chat Interface**: Real-time message display with WebSocket streaming and retro bubble styling
- **Persona Selection**: Interactive modal with persona previews and descriptions
- **Responsive Design**: Adapts beautifully to desktop, tablet, and mobile devices

## Quick Start

### Automated Startup (Recommended)

```bash
# From project root
./start_web_gui.sh
```

This starts both backend (port 8000) and frontend (port 5173) automatically with dependency installation.

### Backend Setup

```bash
cd web_gui/backend
pip install -r requirements.txt
python main.py
```

Server runs at `http://localhost:8000`

**API Endpoints:**
- `GET /` - Health check
- `GET /health` - Server health check
- `GET /api/providers` - List available AI providers (OpenAI, Anthropic, Gemini, DeepSeek, Ollama, LM Studio, OpenRouter)
- `GET /api/personas` - List available personas from roles.json
- `POST /api/conversations` - Create new conversation
- `GET /api/conversations` - List conversations with optional search
- `GET /api/conversations/{id}` - Get conversation details
- `WS /ws/conversations/{id}` - WebSocket for real-time chat

**MCP Memory Endpoints:**
- `GET /api/mcp/health` - MCP server health check
- `GET /api/mcp/stats` - Database statistics
- `GET /api/mcp/recent-chats?limit=N` - Get recent conversations
- `GET /api/mcp/search-chats?keyword=X&limit=N` - Search conversations
- `GET /api/mcp/contextual-memory?topic=X&limit=N` - Get topic-relevant memories
- `GET /api/mcp/conversation/{id}` - Get full conversation details

### Frontend Setup

```bash
cd web_gui/frontend
npm install
npm run dev
```

Frontend runs at `http://localhost:5173`

## Development

### Backend Development
```bash
# Run with auto-reload
uvicorn main:app --reload --host 0.0.0.0 --port 8000
```

### Frontend Development
```bash
# Development server with HMR
npm run dev

# Type checking
npm run type-check

# Build for production
npm run build
```

## Features

- ✅ **Retro Design** - Classic beveled buttons, gray color schemes, and nostalgic aesthetic
- ✅ Real-time AI conversation streaming via WebSocket
- ✅ Dynamic persona selection from roles.json (32+ personas)
- ✅ Multi-provider support (OpenAI, Anthropic, Gemini, DeepSeek, Ollama, LM Studio, OpenRouter)
- ✅ 4-step conversation setup wizard with visual feedback
- ✅ Responsive design (desktop, tablet & mobile)
- ✅ Type-safe TypeScript throughout
- ✅ Comprehensive error handling with user-friendly messages
- ✅ MCP memory integration for contextual awareness
- ✅ Real-time typing indicators and connection status
- ✅ Custom scrollbars and retro-style message bubbles

## Tech Stack

**Backend:**
- FastAPI - Modern Python web framework
- WebSockets - Real-time bidirectional communication
- Pydantic - Data validation and serialization

**Frontend:**
- React 18 - UI library
- TypeScript - Type safety
- Tailwind CSS - Utility-first styling
- Vite - Fast build tool and dev server

## Project Structure

```
web_gui/
├── backend/
│   ├── main.py           # FastAPI application
│   └── requirements.txt  # Python dependencies
└── frontend/
    ├── src/
    │   ├── components/   # React components
    │   ├── types/        # TypeScript types
    │   ├── App.tsx       # Main app component
    │   └── main.tsx      # Entry point
    ├── package.json      # Node dependencies
    └── vite.config.ts    # Vite configuration
```

## API Usage Examples

### Create Conversation
```bash
curl -X POST http://localhost:8000/api/conversations \
  -H "Content-Type: application/json" \
  -d '{
    "provider_a": "openai",
    "provider_b": "anthropic",
    "persona_a": "scientist",
    "persona_b": "philosopher",
    "starter_message": "What is consciousness?",
    "max_rounds": 30,
    "temperature_a": 0.7,
    "temperature_b": 0.7
  }'
```

### Connect to WebSocket
```javascript
const ws = new WebSocket('ws://localhost:8000/ws/conversations/{conversation_id}');

ws.onmessage = (event) => {
  const data = JSON.parse(event.data);
  if (data.type === 'message') {
    console.log('New message:', data.data);
  }
};
```

## Environment Variables

Create `.env` file in project root:
```env
# Primary API Keys
OPENAI_API_KEY=sk-...
ANTHROPIC_API_KEY=sk-...
GEMINI_API_KEY=...
DEEPSEEK_API_KEY=...

# OpenRouter - Access 200+ models through unified API
OPENROUTER_API_KEY=sk-or-v1-...
OPENROUTER_MODEL=openai/gpt-4o-mini
OPENROUTER_APP_NAME="Chat Bridge"
OPENROUTER_REFERER="https://github.com/yourusername/chat-bridge"

# Local Model Hosts
OLLAMA_HOST=http://localhost:11434
LMSTUDIO_BASE_URL=http://localhost:1234/v1

# MCP Memory System
MCP_BASE_URL=http://localhost:8000
```

## Contributing

The web GUI is part of the Chat Bridge modernization effort. See `../TODO.md` and `../devplan.md` for development roadmap.
