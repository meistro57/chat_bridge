from sqlalchemy import create_engine, Column, String, Text, DateTime, Integer, Float
from sqlalchemy.ext.declarative import declarative_base
from sqlalchemy.orm import sessionmaker, Session
from datetime import datetime
import os

# Database setup
# Create db directory if it doesn't exist
os.makedirs("db", exist_ok=True)
DATABASE_URL = os.getenv("DATABASE_URL", "sqlite:///./db/chat_bridge.db")
engine = create_engine(DATABASE_URL, connect_args={"check_same_thread": False} if "sqlite" in DATABASE_URL else {})
SessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)
Base = declarative_base()

# Database Models
class Conversation(Base):
    __tablename__ = "conversations"
    
    id = Column(String, primary_key=True)
    created_at = Column(DateTime, default=datetime.utcnow)
    status = Column(String, default="active")  # active, completed, error
    starter_message = Column(Text)
    max_rounds = Column(Integer)
    mem_rounds = Column(Integer)
    
    # Persona/Provider info
    provider_a = Column(String)
    provider_b = Column(String)
    persona_a = Column(String, nullable=True)
    persona_b = Column(String, nullable=True)
    temperature_a = Column(Float, default=0.7)
    temperature_b = Column(Float, default=0.7)
    
    # Metadata
    total_messages = Column(Integer, default=0)
    completed_at = Column(DateTime, nullable=True)


class ChatMessage(Base):
    __tablename__ = "chat_messages"
    
    id = Column(Integer, primary_key=True, autoincrement=True)
    conversation_id = Column(String, index=True)
    content = Column(Text)
    sender = Column(String)  # 'user', 'agent_a', 'agent_b'
    persona = Column(String, nullable=True)
    timestamp = Column(DateTime, default=datetime.utcnow)
    
    # Message metadata
    round_number = Column(Integer, default=0)
    tokens_used = Column(Integer, nullable=True)
    processing_time = Column(Float, nullable=True)


class ConversationMemory(Base):
    __tablename__ = "conversation_memory"
    
    id = Column(Integer, primary_key=True, autoincrement=True)
    conversation_id = Column(String, index=True)
    memory_type = Column(String)  # 'context', 'summary', 'key_points'
    content = Column(Text)
    created_at = Column(DateTime, default=datetime.utcnow)
    round_number = Column(Integer)


# Database functions
def get_db() -> Session:
    """Dependency to get database session"""
    db = SessionLocal()
    try:
        yield db
    finally:
        db.close()


def init_db():
    """Initialize database tables"""
    Base.metadata.create_all(bind=engine)


def create_conversation(db: Session, conversation_data: dict) -> Conversation:
    """Create a new conversation record"""
    conversation = Conversation(**conversation_data)
    db.add(conversation)
    db.commit()
    db.refresh(conversation)
    return conversation


def get_conversation(db: Session, conversation_id: str) -> Conversation:
    """Get conversation by ID"""
    return db.query(Conversation).filter(Conversation.id == conversation_id).first()


def add_message(db: Session, conversation_id: str, content: str, sender: str, 
                persona: str = None, round_number: int = 0) -> ChatMessage:
    """Add a new message to conversation"""
    message = ChatMessage(
        conversation_id=conversation_id,
        content=content,
        sender=sender,
        persona=persona,
        round_number=round_number
    )
    db.add(message)
    
    # Update conversation message count
    conversation = get_conversation(db, conversation_id)
    if conversation:
        conversation.total_messages += 1
    
    db.commit()
    db.refresh(message)
    return message


def get_conversation_messages(db: Session, conversation_id: str, limit: int = None) -> list[ChatMessage]:
    """Get all messages for a conversation"""
    query = db.query(ChatMessage).filter(ChatMessage.conversation_id == conversation_id).order_by(ChatMessage.timestamp)
    if limit:
        query = query.limit(limit)
    return query.all()


def get_recent_context(db: Session, conversation_id: str, mem_rounds: int) -> list[ChatMessage]:
    """Get recent messages for context (based on mem_rounds)"""
    return (db.query(ChatMessage)
            .filter(ChatMessage.conversation_id == conversation_id)
            .order_by(ChatMessage.timestamp.desc())
            .limit(mem_rounds * 2)  # 2 messages per round (A and B)
            .all()[::-1])  # Reverse to get chronological order


def update_conversation_status(db: Session, conversation_id: str, status: str):
    """Update conversation status"""
    conversation = get_conversation(db, conversation_id)
    if conversation:
        conversation.status = status
        if status == "completed":
            conversation.completed_at = datetime.utcnow()
        db.commit()


def get_conversation_stats(db: Session, conversation_id: str) -> dict:
    """Get conversation statistics"""
    conversation = get_conversation(db, conversation_id)
    if not conversation:
        return {}
    
    messages = get_conversation_messages(db, conversation_id)
    
    return {
        "total_messages": len(messages),
        "duration_minutes": (datetime.utcnow() - conversation.created_at).total_seconds() / 60,
        "status": conversation.status,
        "rounds_completed": len(messages) // 2,
        "max_rounds": conversation.max_rounds
    }


def search_conversations(db: Session, query: str = None, limit: int = 10) -> list[Conversation]:
    """Search conversations by content or metadata"""
    db_query = db.query(Conversation).order_by(Conversation.created_at.desc())
    
    if query:
        # Search in starter message or join with messages
        db_query = db_query.filter(Conversation.starter_message.contains(query))
    
    return db_query.limit(limit).all()


# Context management for memory
def store_context_memory(db: Session, conversation_id: str, content: str, 
                        memory_type: str, round_number: int):
    """Store context memory for conversation"""
    memory = ConversationMemory(
        conversation_id=conversation_id,
        memory_type=memory_type,
        content=content,
        round_number=round_number
    )
    db.add(memory)
    db.commit()


def get_context_memory(db: Session, conversation_id: str, memory_type: str = None) -> list[ConversationMemory]:
    """Get context memory for conversation"""
    query = db.query(ConversationMemory).filter(ConversationMemory.conversation_id == conversation_id)
    if memory_type:
        query = query.filter(ConversationMemory.memory_type == memory_type)
    return query.order_by(ConversationMemory.created_at.desc()).all()
