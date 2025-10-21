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
    search_conversations, Conversation, ChatMessage
)
from sqlalchemy import text

# Import Chat Bridge functionality
import sys
from pathlib import Path
sys.path.insert(0, str(Path(__file__).parent.parent.parent.resolve()))

from bridge_agents import create_agent, get_spec, PROVIDER_REGISTRY

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
    """Get available personas from roles.json"""
    try:
        # Try to load existing roles.json
        import os
        if os.path.exists("roles.json"):
            with open("roles.json", "r") as f:
                roles_data = json.load(f)
                personas = []
                
                # Add personas from persona_library
                persona_library = roles_data.get("persona_library", {})
                for name, data in persona_library.items():
                    personas.append({
                        "id": name,
                        "name": name.replace("_", " ").title(),
                        "provider": data.get("provider", "unknown"),
                        "description": data.get("notes", "Custom persona"),
                        "system_preview": data.get("system", "")[:100] + "..." if len(data.get("system", "")) > 100 else data.get("system", "")
                    })
                
                return {"personas": personas}
    except Exception as e:
        print(f"Error loading personas: {e}")
    
    # Return default personas if no roles.json
    return {"personas": [
        {
            "id": "default_a",
            "name": "Assistant A",
            "provider": "openai",
            "description": "Default helpful assistant",
            "system_preview": "You are a helpful AI assistant..."
        },
        {
            "id": "default_b", 
            "name": "Assistant B",
            "provider": "anthropic",
            "description": "Default creative assistant",
            "system_preview": "You are a creative AI assistant..."
        }
    ]}

@app.get("/api/providers")
async def get_providers():
    """Get available providers"""
    providers = []
    for key, spec in PROVIDER_REGISTRY.items():
        providers.append({
            "key": key,
            "label": spec.label,
            "description": f"Uses {spec.label} models"
        })
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

@app.get("/api/conversations/{conversation_id}/transcript")
async def get_conversation_transcript(conversation_id: str, db: Session = Depends(get_db)):
    """Generate and return a markdown transcript of the conversation"""
    conversation = get_conversation(db, conversation_id)
    if not conversation:
        raise HTTPException(status_code=404, detail="Conversation not found")

    messages = get_conversation_messages(db, conversation_id)

    # Generate transcript
    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    filename = f"transcript_{conversation_id[:8]}_{timestamp}.md"

    transcript_lines = [
        f"# Chat Bridge Conversation Transcript",
        f"",
        f"**Conversation ID:** {conversation_id}",
        f"**Date:** {conversation.created_at.strftime('%Y-%m-%d %H:%M:%S')}",
        f"**Provider A:** {conversation.provider_a}",
        f"**Provider B:** {conversation.provider_b}",
        f"**Persona A:** {conversation.persona_a or 'None'}",
        f"**Persona B:** {conversation.persona_b or 'None'}",
        f"**Max Rounds:** {conversation.max_rounds}",
        f"**Status:** {conversation.status}",
        f"",
        f"---",
        f""
    ]

    # Add messages
    for i, msg in enumerate(messages):
        sender_label = msg.persona if msg.persona else msg.sender.replace('_', ' ').title()
        transcript_lines.append(f"## Message {i + 1} - {sender_label}")
        transcript_lines.append(f"*{msg.timestamp.strftime('%Y-%m-%d %H:%M:%S')}*")
        if msg.round_number > 0:
            transcript_lines.append(f"*Round {msg.round_number}*")
        transcript_lines.append(f"")
        transcript_lines.append(msg.content)
        transcript_lines.append(f"")
        transcript_lines.append(f"---")
        transcript_lines.append(f"")

    transcript = "\n".join(transcript_lines)

    return {
        "transcript": transcript,
        "filename": filename,
        "conversation_id": conversation_id,
        "message_count": len(messages)
    }

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
        # Create agents using your existing bridge_agents
        agent_a = create_agent(
            conversation.provider_a, 
            "agent_a",
            None,  # Use default model
            conversation.temperature_a,
            get_spec(conversation.provider_a).default_system
        )
        
        agent_b = create_agent(
            conversation.provider_b, 
            "agent_b", 
            None,  # Use default model
            conversation.temperature_b,
            get_spec(conversation.provider_b).default_system
        )
        
        current_message = conversation.starter_message
        
        for round_num in range(1, conversation.max_rounds + 1):
            # Agent A responds
            context_messages = get_recent_context(db, conversation_id, conversation.mem_rounds)
            context = "\n".join([f"{msg.sender}: {msg.content}" for msg in context_messages])
            
            # Use your bridge_agents streaming
            response_a = ""
            async for chunk in agent_a.stream_reply(current_message, context):
                response_a += chunk
                # Optionally send streaming chunks to WebSocket
                await manager.send_message(conversation_id, {
                    "type": "stream_chunk",
                    "data": {
                        "sender": "agent_a",
                        "chunk": chunk,
                        "round_number": round_num
                    }
                })
            
            # Store Agent A's message
            msg_a = add_message(db, conversation_id, response_a, "agent_a", 
                              conversation.persona_a, round_num)
            
            # Send complete message to WebSocket
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
            
            response_b = ""
            async for chunk in agent_b.stream_reply(response_a, context):
                response_b += chunk
                await manager.send_message(conversation_id, {
                    "type": "stream_chunk",
                    "data": {
                        "sender": "agent_b",
                        "chunk": chunk,
                        "round_number": round_num
                    }
                })
            
            # Store Agent B's message
            msg_b = add_message(db, conversation_id, response_b, "agent_b", 
                              conversation.persona_b, round_num)
            
            # Send complete message to WebSocket
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

# ───────────────────────── MCP Tool Endpoints ─────────────────────────

@app.get("/api/mcp/recent-chats")
async def mcp_get_recent_chats(limit: int = 10, db: Session = Depends(get_db)):
    """
    MCP Tool: Get recent conversations from the database.

    Args:
        limit: Maximum number of conversations to return (default: 10)

    Returns:
        List of recent conversations with metadata
    """
    try:
        conversations = db.query(Conversation).order_by(
            Conversation.created_at.desc()
        ).limit(limit).all()

        return {
            "success": True,
            "data": [{
                'id': conv.id,
                'timestamp': conv.created_at.isoformat(),
                'starter': conv.starter_message,
                'agent_a_provider': conv.provider_a,
                'agent_b_provider': conv.provider_b,
                'status': conv.status
            } for conv in conversations]
        }
    except Exception as e:
        return {"success": False, "error": str(e), "data": []}


@app.get("/api/mcp/search-chats")
async def mcp_search_chats(keyword: str, limit: int = 5, db: Session = Depends(get_db)):
    """
    MCP Tool: Search conversations by keyword in message content.

    Args:
        keyword: The search term to look for in message content
        limit: Maximum number of results to return (default: 5)

    Returns:
        List of conversations containing the keyword
    """
    try:
        # Search in messages and get distinct conversations
        results = db.query(
            Conversation.id,
            Conversation.created_at,
            Conversation.starter_message,
            ChatMessage.content
        ).join(
            ChatMessage, Conversation.id == ChatMessage.conversation_id
        ).filter(
            ChatMessage.content.contains(keyword)
        ).order_by(
            Conversation.created_at.desc()
        ).limit(limit).all()

        return {
            "success": True,
            "data": [{
                'conversation_id': row[0],
                'timestamp': row[1].isoformat(),
                'starter': row[2],
                'content': row[3]
            } for row in results]
        }
    except Exception as e:
        return {"success": False, "error": str(e), "data": []}


@app.get("/api/mcp/contextual-memory")
async def mcp_get_contextual_memory(topic: str, limit: int = 3, db: Session = Depends(get_db)):
    """
    MCP Tool: Get contextual memory related to a specific topic.

    Args:
        topic: The topic to search for in conversation history
        limit: Maximum number of relevant memories to return (default: 3)

    Returns:
        Formatted string containing relevant conversation excerpts
    """
    try:
        # Search for topic keyword
        results = db.query(
            Conversation.created_at,
            ChatMessage.content
        ).join(
            ChatMessage, Conversation.id == ChatMessage.conversation_id
        ).filter(
            ChatMessage.content.contains(topic)
        ).order_by(
            Conversation.created_at.desc()
        ).limit(limit).all()

        if not results:
            return {"success": True, "context": ""}

        # Build context string
        context_parts = []
        for row in results:
            timestamp = row[0].isoformat()
            content = row[1][:100] + "..." if len(row[1]) > 100 else row[1]
            context_parts.append(f"From {timestamp}: {content}")

        context = "\n".join(context_parts) if context_parts else ""
        return {"success": True, "context": context}
    except Exception as e:
        return {"success": False, "error": str(e), "context": ""}


@app.get("/api/mcp/conversation/{conversation_id}")
async def mcp_get_conversation_by_id(conversation_id: str, db: Session = Depends(get_db)):
    """
    MCP Tool: Get full conversation details by ID.

    Args:
        conversation_id: The unique conversation ID

    Returns:
        Dictionary containing conversation metadata and all messages
    """
    try:
        conversation = get_conversation(db, conversation_id)
        if not conversation:
            return {"success": False, "error": "Conversation not found"}

        messages = get_conversation_messages(db, conversation_id)

        return {
            "success": True,
            "data": {
                'id': conversation.id,
                'timestamp': conversation.created_at.isoformat(),
                'starter': conversation.starter_message,
                'agent_a_provider': conversation.provider_a,
                'agent_b_provider': conversation.provider_b,
                'status': conversation.status,
                'messages': [{
                    'sender': msg.sender,
                    'content': msg.content,
                    'timestamp': msg.timestamp.isoformat(),
                    'persona': msg.persona
                } for msg in messages]
            }
        }
    except Exception as e:
        return {"success": False, "error": str(e)}


@app.get("/api/mcp/stats")
async def mcp_get_database_stats(db: Session = Depends(get_db)):
    """
    MCP Resource: Get statistics about the conversation database.

    Returns:
        Statistics about conversations and messages
    """
    try:
        # Count conversations
        conv_count = db.query(Conversation).count()

        # Count messages
        msg_count = db.query(ChatMessage).count()

        # Get most recent conversation time
        last_conv = db.query(Conversation).order_by(
            Conversation.created_at.desc()
        ).first()
        last_conv_time = last_conv.created_at.isoformat() if last_conv else "None"

        avg_messages = msg_count / conv_count if conv_count > 0 else 0

        return {
            "success": True,
            "stats": {
                "total_conversations": conv_count,
                "total_messages": msg_count,
                "last_conversation": last_conv_time,
                "average_messages_per_conversation": round(avg_messages, 1)
            }
        }
    except Exception as e:
        return {"success": False, "error": str(e)}


@app.get("/api/mcp/health")
async def mcp_health_check(db: Session = Depends(get_db)):
    """
    MCP Resource: Check the health status of the MCP server.

    Returns:
        Health status information
    """
    try:
        # Test database connection
        db.execute(text("SELECT 1"))

        return {
            "success": True,
            "status": "healthy",
            "server": "Chat Bridge Memory",
            "timestamp": datetime.utcnow().isoformat(),
            "database": "connected",
            "version": "2.0.0"
        }
    except Exception as e:
        return {
            "success": False,
            "status": "unhealthy",
            "error": str(e)
        }


# Health check
@app.get("/health")
async def health_check():
    return {"status": "healthy", "version": "2.0.0"}

# Serve static files (frontend) - only if frontend exists
import os
if os.path.exists("web_gui/frontend/dist"):
    app.mount("/", StaticFiles(directory="web_gui/frontend/dist", html=True), name="static")
else:
    @app.get("/")
    async def root():
        return {
            "message": "Chat Bridge API is running!",
            "version": "2.0.0",
            "endpoints": {
                "health": "/health",
                "conversations": "/api/conversations",
                "personas": "/api/personas",
                "providers": "/api/providers",
                "websocket": "/ws/conversations/{id}"
            }
        }

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=8000)
