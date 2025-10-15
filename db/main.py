import asyncio
import json
import uuid
from datetime import datetime
from typing import Dict, List, Optional

from fastapi import FastAPI, WebSocket, WebSocketDisconnect, HTTPException, Depends
from fastapi.middleware.cors import CORSMiddleware
from fastapi.staticfiles import StaticFiles
from pydantic import BaseModel
from sqlalchemy.orm import Session

# Import our database components
from database import (
    init_db, get_db, create_conversation, get_conversation, 
    add_message, get_conversation_messages, get_recent_context,
    update_conversation_status, get_conversation_stats,
    search_conversations
)

# Your existing imports
from core.agent_manager import AgentManager
from core.conversation_manager import ConversationManager

app = FastAPI(title="Chat Bridge API", version="2.0.0")

# Initialize database
init_db()

# CORS middleware
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Initialize managers
agent_manager = AgentManager()
conversation_manager = ConversationManager()

# Pydantic models
class ConversationRequest(BaseModel):
    provider_a: str
    provider_b: str
    persona_a: Optional[str] = None
    persona_b: Optional[str] = None
    starter_message: str
    max_rounds: int = 10
    mem_rounds: int = 5
    temperature_a: float = 0.7
    temperature_b: float = 0.7

class ConversationResponse(BaseModel):
    conversation_id: str
    status: str
    starter_message: str

class MessageResponse(BaseModel):
    content: str
    sender: str
    timestamp: str
    persona: Optional[str] = None
    round_number: int = 0

# WebSocket connection manager
class ConnectionManager:
    def __init__(self):
        self.active_connections: Dict[str, WebSocket] = {}

    async def connect(self, websocket: WebSocket, conversation_id: str):
        await websocket.accept()
        self.active_connections[conversation_id] = websocket

    def disconnect(self, conversation_id: str):
        if conversation_id in self.active_connections:
            del self.active_connections[conversation_id]

    async def send_message(self, conversation_id: str, message: dict):
        if conversation_id in self.active_connections:
            await self.active_connections[conversation_id].send_text(json.dumps(message))

manager = ConnectionManager()

# API Routes
@app.get("/api/personas")
async def get_personas():
    """Get available personas"""
    personas = agent_manager.get_available_personas()
    return {"personas": personas}

@app.get("/api/providers")
async def get_providers():
    """Get available providers"""
    providers = agent_manager.get_available_providers()
    return {"providers": providers}

@app.post("/api/conversations", response_model=ConversationResponse)
async def create_conversation_endpoint(
    request: ConversationRequest, 
    db: Session = Depends(get_db)
):
    """Create a new conversation"""
    conversation_id = str(uuid.uuid4())
    
    # Store conversation in database
    conversation_data = {
        "id": conversation_id,
        "starter_message": request.starter_message,
        "max_rounds": request.max_rounds,
        "mem_rounds": request.mem_rounds,
        "provider_a": request.provider_a,
        "provider_b": request.provider_b,
        "persona_a": request.persona_a,
        "persona_b": request.persona_b,
        "temperature_a": request.temperature_a,
        "temperature_b": request.temperature_b,
        "status": "active"
    }
    
    create_conversation(db, conversation_data)
    
    # Store initial message
    add_message(db, conversation_id, request.starter_message, "user", round_number=0)
    
    return ConversationResponse(
        conversation_id=conversation_id,
        status="created",
        starter_message=request.starter_message
    )

@app.get("/api/conversations/{conversation_id}")
async def get_conversation_endpoint(conversation_id: str, db: Session = Depends(get_db)):
    """Get conversation details"""
    conversation = get_conversation(db, conversation_id)
    if not conversation:
        raise HTTPException(status_code=404, detail="Conversation not found")
    
    messages = get_conversation_messages(db, conversation_id)
    stats = get_conversation_stats(db, conversation_id)
    
    return {
        "conversation": {
            "id": conversation.id,
            "status": conversation.status,
            "created_at": conversation.created_at.isoformat(),
            "starter_message": conversation.starter_message,
            "max_rounds": conversation.max_rounds,
            "persona_a": conversation.persona_a,
            "persona_b": conversation.persona_b
        },
        "messages": [
            {
                "content": msg.content,
                "sender": msg.sender,
                "persona": msg.persona,
                "timestamp": msg.timestamp.isoformat(),
                "round_number": msg.round_number
            } for msg in messages
        ],
        "stats": stats
    }

@app.get("/api/conversations")
async def list_conversations(
    query: Optional[str] = None, 
    limit: int = 10, 
    db: Session = Depends(get_db)
):
    """List conversations with optional search"""
    conversations = search_conversations(db, query, limit)
    
    return {
        "conversations": [
            {
                "id": conv.id,
                "status": conv.status,
                "created_at": conv.created_at.isoformat(),
                "starter_message": conv.starter_message[:100] + "..." if len(conv.starter_message) > 100 else conv.starter_message,
                "total_messages": conv.total_messages,
                "max_rounds": conv.max_rounds
            } for conv in conversations
        ]
    }

@app.websocket("/ws/conversations/{conversation_id}")
async def websocket_endpoint(websocket: WebSocket, conversation_id: str):
    """WebSocket endpoint for real-time conversation"""
    await manager.connect(websocket, conversation_id)
    
    # Get database session
    db = next(get_db())
    
    try:
        # Verify conversation exists
        conversation = get_conversation(db, conversation_id)
        if not conversation:
            await websocket.send_text(json.dumps({
                "type": "error",
                "data": "Conversation not found"
            }))
            return
        
        # Send existing messages
        messages = get_conversation_messages(db, conversation_id)
        for message in messages:
            await manager.send_message(conversation_id, {
                "type": "message",
                "data": {
                    "content": message.content,
                    "sender": message.sender,
                    "persona": message.persona,
                    "timestamp": message.timestamp.isoformat(),
                    "round_number": message.round_number
                }
            })
        
        # Start conversation if it's new
        if conversation.status == "active" and conversation.total_messages <= 1:
            await run_conversation(conversation_id, conversation, db)
            
    except WebSocketDisconnect:
        manager.disconnect(conversation_id)
    except Exception as e:
        await manager.send_message(conversation_id, {
            "type": "error", 
            "data": str(e)
        })
    finally:
        db.close()

async def run_conversation(conversation_id: str, conversation, db: Session):
    """Run the AI conversation with database persistence"""
    try:
        # Initialize agents
        agent_a = agent_manager.create_agent(
            conversation.provider_a, 
            conversation.persona_a,
            temperature=conversation.temperature_a
        )
        agent_b = agent_manager.create_agent(
            conversation.provider_b, 
            conversation.persona_b,
            temperature=conversation.temperature_b
        )
        
        current_message = conversation.starter_message
        round_number = 1
        
        for round_num in range(1, conversation.max_rounds + 1):
            # Agent A responds
            context_messages = get_recent_context(db, conversation_id, conversation.mem_rounds)
            context = "\n".join([f"{msg.sender}: {msg.content}" for msg in context_messages])
            
            response_a = await agent_a.generate_response(current_message, context)
            
            # Store Agent A's message
            msg_a = add_message(db, conversation_id, response_a, "agent_a", 
                              conversation.persona_a, round_num)
            
            # Send to WebSocket
            await manager.send_message(conversation_id, {
                "type": "message",
                "data": {
                    "content": response_a,
                    "sender": "agent_a",
                    "persona": conversation.persona_a,
                    "timestamp": msg_a.timestamp.isoformat(),
                    "round_number": round_num
                }
            })
            
            await asyncio.sleep(1)  # Brief pause
            
            # Agent B responds
            context_messages = get_recent_context(db, conversation_id, conversation.mem_rounds)
            context = "\n".join([f"{msg.sender}: {msg.content}" for msg in context_messages])
            
            response_b = await agent_b.generate_response(response_a, context)
            
            # Store Agent B's message
            msg_b = add_message(db, conversation_id, response_b, "agent_b", 
                              conversation.persona_b, round_num)
            
            # Send to WebSocket
            await manager.send_message(conversation_id, {
                "type": "message",
                "data": {
                    "content": response_b,
                    "sender": "agent_b",
                    "persona": conversation.persona_b,
                    "timestamp": msg_b.timestamp.isoformat(),
                    "round_number": round_num
                }
            })
            
            current_message = response_b
            await asyncio.sleep(1)
        
        # Mark conversation as completed
        update_conversation_status(db, conversation_id, "completed")
        
        await manager.send_message(conversation_id, {
            "type": "conversation_end",
            "data": f"Conversation completed after {conversation.max_rounds} rounds"
        })
        
    except Exception as e:
        # Mark conversation as error
        update_conversation_status(db, conversation_id, "error")
        
        await manager.send_message(conversation_id, {
            "type": "error",
            "data": f"Conversation error: {str(e)}"
        })

# Health check
@app.get("/health")
async def health_check():
    return {"status": "healthy", "version": "2.0.0"}

# Serve static files (frontend)
app.mount("/", StaticFiles(directory="web_gui/frontend/dist", html=True), name="static")

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=8000)
