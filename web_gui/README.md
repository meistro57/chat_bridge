# Chat Bridge Web GUI

Modern web interface for the Chat Bridge AI conversation platform.

## Architecture

### Backend (FastAPI)
- **API Server**: RESTful endpoints for persona management and conversation creation
- **WebSocket Server**: Real-time bidirectional communication for live conversations
- **Persona Management**: Dynamic loading and validation from `roles.json`

### Frontend (React + TypeScript + Tailwind)
- **Setup Wizard**: Three-step conversation configuration
- **Chat Interface**: Real-time message display with WebSocket streaming
- **Persona Selection**: Interactive dropdown with persona previews

## Quick Start

### Backend Setup

```bash
cd web_gui/backend
pip install -r requirements.txt
python main.py
```

Server runs at `http://localhost:8000`

API Endpoints:
- `GET /` - Health check
- `GET /api/providers` - List available AI providers
- `GET /api/personas` - List available personas from roles.json
- `POST /api/conversations` - Create new conversation
- `WS /ws/conversations/{id}` - WebSocket for real-time chat

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

- ✅ Real-time AI conversation streaming via WebSocket
- ✅ Dynamic persona selection from roles.json
- ✅ Multi-step conversation setup wizard
- ✅ Responsive design (mobile & desktop)
- ✅ Dark mode support
- ✅ Type-safe TypeScript throughout
- ✅ Comprehensive error handling

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
OPENAI_API_KEY=sk-...
ANTHROPIC_API_KEY=sk-...
GEMINI_API_KEY=...
DEEPSEEK_API_KEY=...
OLLAMA_HOST=http://localhost:11434
LMSTUDIO_BASE_URL=http://localhost:1234/v1
```

## Contributing

The web GUI is part of the Chat Bridge modernization effort. See `../TODO.md` and `../devplan.md` for development roadmap.
