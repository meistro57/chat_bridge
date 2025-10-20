#!/usr/bin/env python3
"""
Chat Bridge Web API Backend
FastAPI server providing RESTful API for the Chat Bridge web interface.
"""

from fastapi import FastAPI, WebSocket, HTTPException, WebSocketDisconnect
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import StreamingResponse
from pydantic import BaseModel
from typing import Dict, List, Optional
import json
import os
from pathlib import Path
from datetime import datetime
import asyncio
import logging
from dotenv import load_dotenv

# Load environment variables from .env file
load_dotenv()

# Import Chat Bridge functionality
import sys
sys.path.insert(0, str(Path(__file__).parent.parent.parent.resolve()))

from bridge_agents import create_agent, get_spec, provider_choices, ensure_credentials, resolve_model, ProviderSpec

# Set up logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

app = FastAPI(
    title="Chat Bridge Web API",
    description="RESTful API for managing AI agent conversations",
    version="0.1.0"
)

# Add CORS middleware for web frontend
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],  # In production, specify your frontend domain
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Data models for API requests
class PersonaConfig(BaseModel):
    name: str
    provider: str
    system_prompt: str
    temperature: Optional[float] = 0.7
    model: Optional[str] = None
    guidelines: Optional[List[str]] = []

class ConversationRequest(BaseModel):
    persona_a: Optional[str] = None
    persona_b: Optional[str] = None
    provider_a: str
    provider_b: str
    starter_message: str
    max_rounds: int = 30
    mem_rounds: int = 8 # Add mem_rounds to ConversationRequest
    temperature_a: float = 0.7
    temperature_b: float = 0.7

class Message(BaseModel):
    content: str
    sender: str  # 'user', 'agent_a', 'agent_b'
    timestamp: datetime
    persona: Optional[str] = None

class Conversation:
    def __init__(self, request: ConversationRequest):
        self.request = request
        self.messages: List[Message] = []
        self.agent_a = None
        self.agent_b = None
        self.active = True

    def initialize_agents(self) -> None:
        """Initialize AI agents for the conversation.

        Applies persona configurations if provided in the request.

        Raises:
            RuntimeError: If the provider configuration is invalid or
                required credentials are missing.
        """
        global persona_manager

        # Resolve configurations for Agent A
        persona_a_config = persona_manager.persona_library.get(self.request.persona_a) if self.request.persona_a else None
        if persona_a_config:
            provider_a = persona_a_config.provider if persona_a_config.provider else self.request.provider_a
            temp_a = persona_a_config.temperature if persona_a_config.temperature else self.request.temperature_a
            model_a = resolve_model(provider_a, persona_a_config.model)
            system_a = persona_a_config.system_prompt if persona_a_config.system_prompt else get_spec(provider_a).default_system
        else:
            provider_a = self.request.provider_a
            temp_a = self.request.temperature_a
            model_a = resolve_model(provider_a, None)
            system_a = get_spec(provider_a).default_system

        # Resolve configurations for Agent B
        persona_b_config = persona_manager.persona_library.get(self.request.persona_b) if self.request.persona_b else None
        if persona_b_config:
            provider_b = persona_b_config.provider if persona_b_config.provider else self.request.provider_b
            temp_b = persona_b_config.temperature if persona_b_config.temperature else self.request.temperature_b
            model_b = resolve_model(provider_b, persona_b_config.model)
            system_b = persona_b_config.system_prompt if persona_b_config.system_prompt else get_spec(provider_b).default_system
        else:
            provider_b = self.request.provider_b
            temp_b = self.request.temperature_b
            model_b = resolve_model(provider_b, None)
            system_b = get_spec(provider_b).default_system

        # Ensure credentials (raises RuntimeError when missing)
        ensure_credentials(provider_a)
        ensure_credentials(provider_b)

        # Create agents with applied configurations
        self.agent_a = create_agent(
            "A",
            provider_a,
            model_a,
            temp_a,
            system_a,
        )

        self.agent_b = create_agent(
            "B",
            provider_b,
            model_b,
            temp_b,
            system_b,
        )

        logger.info(
            f"Agents initialized with personas {self.request.persona_a or 'default'} vs {self.request.persona_b or 'default'}"
        )

class PersonaManager:
    """Manages roles and personalities configuration"""

    def __init__(self):
        self.script_dir = Path(__file__).parent.parent.parent.resolve()
        self.persona_library: Dict[str, PersonaConfig] = {}

    def load_personas_from_config(self) -> Dict[str, PersonaConfig]:
        """Load persona configurations from roles.json with robust error handling"""
        try:
            roles_path = self.script_dir / "roles.json"

            if not roles_path.exists():
                logger.warning(f"roles.json not found at {roles_path}, starting with empty persona library")
                return {}

            with open(roles_path, 'r', encoding='utf-8') as f:
                roles_data = json.load(f)

            personas = {}
            if 'persona_library' in roles_data:
                for key, persona_data in roles_data['persona_library'].items():
                    try:
                        persona_config = PersonaConfig(
                            name=persona_data.get('name', key),
                            provider=persona_data.get('provider', 'openai'),
                            system_prompt=persona_data.get('system', ''),
                            temperature=persona_data.get('temperature', 0.7),
                            model=persona_data.get('model'),
                            guidelines=persona_data.get('guidelines', [])
                        )
                        personas[key] = persona_config
                    except Exception as e:
                        logger.warning(f"Failed to load persona {key}: {e}")

            logger.info(f"Loaded {len(personas)} personas from roles.json")
            return personas

        except json.JSONDecodeError as e:
            logger.error(f"JSON syntax error in roles.json: line {e.lineno}, column {e.colno}: {e.msg}")
            return {}
        except Exception as e:
            logger.error(f"Error loading persona configurations: {e}")
            return {}

    def get_persona(self, persona_key: str) -> Optional[PersonaConfig]:
        """Get a specific persona by key"""
        return self.persona_library.get(persona_key)

    def get_available_personas(self) -> Dict[str, Dict]:
        """Get available personas in API format - personas are provider-agnostic"""
        available: Dict[str, Dict] = {}
        for key, persona in self.persona_library.items():
            # Since personas are now provider-agnostic, we return all of them
            # Provider selection happens at conversation creation time
            available[key] = {
                "id": key,
                "name": persona.name,
                "description": "AI persona available with any provider",
                "system_preview": persona.system_prompt[:100] + "..." if len(persona.system_prompt) > 100 else persona.system_prompt,
            }

        return available

# Global state (in production, use Redis or database)
conversations: Dict[str, Conversation] = {}
persona_manager = PersonaManager()

@app.on_event("startup")
async def startup_event():
    """Load persona configurations on startup"""
    persona_manager.persona_library = persona_manager.load_personas_from_config()

@app.get("/")
async def root():
    """Health check endpoint"""
    return {"message": "Chat Bridge Web API is running", "version": "0.1.0"}

@app.get("/api/providers")
async def get_providers():
    """Get available AI providers"""
    providers = provider_choices()
    return {
        "providers": [
            {
                "key": p,
                "label": get_spec(p).label,
                "description": get_spec(p).description
            } for p in providers
        ]
    }

@app.get("/api/personas")
async def get_personas():
    """Get available persona configurations"""
    # Ensure personas are loaded if not already
    if not persona_manager.persona_library:
        persona_manager.persona_library = persona_manager.load_personas_from_config()
    return {"personas": list(persona_manager.get_available_personas().values())}

@app.post("/api/conversations", response_model=dict)
async def create_conversation(request: ConversationRequest):
    """Create a new conversation session"""
    try:
        # Generate conversation ID
        conv_id = f"conv_{datetime.now().strftime('%Y%m%d_%H%M%S')}"

        # Create conversation object
        conversation = Conversation(request)
        conversations[conv_id] = conversation

        # Initialize agents (raises when credentials are missing)
        conversation.initialize_agents()

        # Add initial user message
        initial_message = Message(
            content=request.starter_message,
            sender="user",
            timestamp=datetime.now(),
            persona=None
        )
        conversation.messages.append(initial_message)

        return {
            "conversation_id": conv_id,
            "status": "created",
            "starter_message": request.starter_message
        }

    except RuntimeError as e:
        logger.error("Failed to create conversation due to configuration issue: %s", e)
        raise HTTPException(status_code=400, detail=str(e)) from e
    except Exception as e:
        logger.error("Failed to create conversation: %s", e, exc_info=True)
        raise HTTPException(status_code=500, detail="Internal server error") from e

@app.websocket("/ws/conversations/{conversation_id}")
async def websocket_conversation(websocket: WebSocket, conversation_id: str):
    """WebSocket endpoint for real-time conversation streaming"""
    await websocket.accept()

    try:
        if conversation_id not in conversations:
            await websocket.send_json({"error": "Conversation not found"})
            return

        conversation = conversations[conversation_id]
        if not conversation.active:
            await websocket.send_json({"error": "Conversation is inactive"})
            return

        # Send conversation history
        for msg in conversation.messages:
            await websocket.send_json({
                "type": "message",
                "data": {
                    "content": msg.content,
                    "sender": msg.sender,
                    "timestamp": msg.timestamp.isoformat(),
                    "persona": msg.persona
                }
            })

        # Start conversation loop
        current_agent = conversation.agent_a
        agent_name = "agent_a"
        turn_counter = 0

        while conversation.active and turn_counter < conversation.request.max_rounds:
            try:
                turn_counter += 1

                # Agent A response
                context = [msg.content for msg in conversation.messages[-conversation.request.mem_rounds:]]
                response_a = await current_agent.generate_response(
                    " ".join(context),
                    conversation.request.mem_rounds
                )
                message_a = Message(
                    content=response_a,
                    sender="agent_a",
                    timestamp=datetime.now(),
                    persona=getattr(conversation.request, f'persona_a', None)
                )
                conversation.messages.append(message_a)
                await websocket.send_json({
                    "type": "message",
                    "data": {
                        "content": response_a,
                        "sender": "agent_a",
                        "timestamp": message_a.timestamp.isoformat(),
                        "persona": message_a.persona
                    }
                })

                await asyncio.sleep(0.05)

                # Agent B response
                current_agent = conversation.agent_b
                agent_name = "agent_b"
                context = [msg.content for msg in conversation.messages[-conversation.request.mem_rounds:]]
                response_b = await current_agent.generate_response(
                    " ".join(context),
                    conversation.request.mem_rounds
                )
                message_b = Message(
                    content=response_b,
                    sender="agent_b",
                    timestamp=datetime.now(),
                    persona=getattr(conversation.request, f'persona_b', None)
                )
                conversation.messages.append(message_b)
                await websocket.send_json({
                    "type": "message",
                    "data": {
                        "content": response_b,
                        "sender": "agent_b",
                        "timestamp": message_b.timestamp.isoformat(),
                        "persona": message_b.persona
                    }
                })

                await asyncio.sleep(0.05)

                # Switch back to A for next round
                current_agent = conversation.agent_a
                agent_name = "agent_a"

            except Exception as e:
                logger.error(f"Error in conversation loop: {e}")
                await websocket.send_json({
                    "type": "error",
                    "data": str(e)
                })
                break

        # Conversation ended
        await websocket.send_json({"type": "conversation_end"})

    except WebSocketDisconnect:
        logger.info(f"WebSocket disconnected for conversation {conversation_id}")
    except Exception as e:
        logger.error(f"WebSocket error for conversation {conversation_id}: {e}")

async def load_persona_configurations():
    """Load persona configurations from roles.json with robust error handling"""
    try:
        # Convert to absolute path for consistent resolution
        script_dir = Path(__file__).parent.parent.parent.resolve()
        roles_path = script_dir / "roles.json"

        # Check file existence with helpful error messages
        if not roles_path.exists():
            available_files = [f.name for f in script_dir.glob("*.json")]
            logger.warning(f"roles.json not found. Available files: {available_files}")
            return

        # Use explicit encoding to prevent encoding issues
        with open(roles_path, 'r', encoding='utf-8') as f:
            roles_data = json.load(f)

        if 'persona_library' in roles_data:
            for key, persona_data in roles_data['persona_library'].items():
                try:
                    # Convert to PersonaConfig format
                    # Ensure 'provider' is handled, defaulting if missing or null
                    provider_value = persona_data.get('provider')
                    if provider_value is None:  # Handle explicit null as well
                        provider_value = 'openai' # Default provider when explicitly null or missing

                    persona_config = PersonaConfig(
                        name=persona_data.get('name', key),
                        provider=provider_value,
                        system_prompt=persona_data.get('system', ''),
                        temperature=persona_data.get('temperature', 0.7),
                        model=persona_data.get('model'),
                        guidelines=persona_data.get('guidelines', [])
                    )
                    persona_library[key] = persona_config
                except Exception as e:
                    logger.warning(f"Failed to load persona {key}: {e}")

        logger.info(f"Loaded {len(persona_library)} personas from roles.json")

    except json.JSONDecodeError as e:
        logger.error(f"JSON syntax error in roles.json: line {e.lineno}, column {e.colno}: {e.msg}")
        logger.error(f"Error at character position {e.pos}")
    except Exception as e:
        logger.error(f"Error loading persona configurations: {e}")

if __name__ == "__main__":
    import uvicorn
    print("🚀 Starting Chat Bridge Web Backend on http://0.0.0.0:8000")
    print("📁 Loaded personas from roles.json")
    print("⏹️  Press Ctrl+C to stop the server")
    uvicorn.run("web_gui.backend.main:app", host="0.0.0.0", port=8000, log_level="info")