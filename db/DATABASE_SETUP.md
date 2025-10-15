# Chat Bridge Database Setup

This guide will help you set up persistent memory for your Chat Bridge project using SQLite and SQLAlchemy.

## 🚀 Quick Start

1. **Run the setup script:**
   ```bash
   python setup_chat_bridge.py
   ```

2. **Edit your API keys:**
   ```bash
   # Edit the .env file created by setup
   nano .env
   ```

3. **Start Chat Bridge:**
   ```bash
   python main.py
   # or
   ./start_chat_bridge.sh
   ```

## 📋 Manual Setup

If you prefer manual setup:

### 1. Install Dependencies
```bash
pip install -r requirements.txt
```

### 2. Initialize Database
```bash
python migrate_db.py
```

### 3. Test Database
```bash
python test_database.py
```

### 4. Configure Environment
Create a `.env` file:
```bash
OPENAI_API_KEY=your_openai_key_here
ANTHROPIC_API_KEY=your_anthropic_key_here
GOOGLE_API_KEY=your_google_key_here  # Optional
DATABASE_URL=sqlite:///./chat_bridge.db  # Optional, defaults to this
```

## 🗄️ Database Features

### What's Stored
- **Conversations**: Full conversation metadata, settings, and status
- **Messages**: Every message with timestamps, sender info, and round numbers
- **Memory Context**: Configurable conversation memory for AI agents
- **Statistics**: Performance metrics and conversation analytics

### Key Benefits
- **Persistence**: Conversations survive server restarts
- **Memory Management**: Configurable context windows (mem_rounds)
- **Analytics**: Track conversation patterns and performance
- **Backup/Export**: Easy data backup and conversation export
- **Scalability**: SQLite → PostgreSQL migration path

### Database Schema

#### Conversations Table
```sql
- id (Primary Key)
- created_at, completed_at
- status (active, completed, error)
- starter_message
- max_rounds, mem_rounds
- provider_a, provider_b
- persona_a, persona_b
- temperature_a, temperature_b
- total_messages
```

#### Messages Table
```sql
- id (Primary Key)
- conversation_id (Foreign Key)
- content, sender, persona
- timestamp, round_number
- tokens_used, processing_time
```

#### Memory Table
```sql
- id (Primary Key)
- conversation_id (Foreign Key)
- memory_type (context, summary, key_points)
- content, round_number
- created_at
```

## 🔧 Database Management

### Migration Commands
```bash
# Initialize or update database schema
python migrate_db.py

# Show database information
python migrate_db.py info

# Create backup
python migrate_db.py backup
```

### Testing Commands
```bash
# Run full test suite
python test_database.py

# Performance testing
python test_database.py  # Includes performance tests
```

### API Endpoints

#### Conversation Management
- `POST /api/conversations` - Create new conversation
- `GET /api/conversations/{id}` - Get conversation details
- `GET /api/conversations` - List conversations (with search)

#### Real-time Features
- `WebSocket /ws/conversations/{id}` - Live conversation updates

#### Health Check
- `GET /health` - Server status

## 📊 Memory Management

### Context Windows
The `mem_rounds` parameter controls how much conversation history is passed to AI agents:

```python
# Example: mem_rounds = 3 means last 6 messages (3 rounds)
recent_context = get_recent_context(db, conversation_id, mem_rounds=3)
```

### Memory Types
- **Context**: Recent conversation for AI agents
- **Summary**: Compressed conversation summaries
- **Key Points**: Important conversation highlights

## 🔍 Monitoring & Analytics

### Conversation Statistics
```python
stats = get_conversation_stats(db, conversation_id)
# Returns: total_messages, duration_minutes, status, rounds_completed
```

### Performance Tracking
- Message timestamps for response time analysis
- Token usage tracking (when available)
- Processing time measurements

## 🛠️ Troubleshooting

### Common Issues

1. **Database locked error**
   ```bash
   # Stop all Chat Bridge instances
   pkill -f "python main.py"
   # Then restart
   ```

2. **Permission errors**
   ```bash
   # Check file permissions
   ls -la chat_bridge.db
   # Fix if needed
   chmod 664 chat_bridge.db
   ```

3. **Migration failures**
   ```bash
   # Create backup first
   python migrate_db.py backup
   # Then retry migration
   python migrate_db.py migrate
   ```

### Database Recovery

1. **From backup:**
   ```bash
   cp chat_bridge_backup_YYYYMMDD_HHMMSS.db chat_bridge.db
   ```

2. **Reset database:**
   ```bash
   rm chat_bridge.db
   python migrate_db.py
   ```

## 🚀 Advanced Configuration

### PostgreSQL Migration
To switch from SQLite to PostgreSQL:

1. Install PostgreSQL driver:
   ```bash
   pip install psycopg2-binary
   ```

2. Update DATABASE_URL in .env:
   ```bash
   DATABASE_URL=postgresql://user:password@localhost/chat_bridge
   ```

3. Run migration:
   ```bash
   python migrate_db.py
   ```

### Custom Memory Strategies
Extend the memory system by modifying `database.py`:

```python
# Add custom memory types
def store_custom_memory(db, conversation_id, content, custom_type):
    memory = ConversationMemory(
        conversation_id=conversation_id,
        memory_type=custom_type,
        content=content,
        round_number=get_current_round(db, conversation_id)
    )
    db.add(memory)
    db.commit()
```

## 📝 API Usage Examples

### Create Conversation
```python
import requests

response = requests.post("http://localhost:8000/api/conversations", json={
    "provider_a": "openai",
    "provider_b": "anthropic", 
    "persona_a": "helpful_assistant",
    "persona_b": "creative_writer",
    "starter_message": "Let's discuss AI ethics",
    "max_rounds": 10,
    "mem_rounds": 5,
    "temperature_a": 0.7,
    "temperature_b": 0.9
})

conversation_id = response.json()["conversation_id"]
```

### Monitor via WebSocket
```javascript
const ws = new WebSocket(`ws://localhost:8000/ws/conversations/${conversationId}`);

ws.onmessage = (event) => {
    const data = JSON.parse(event.data);
    if (data.type === 'message') {
        console.log(`${data.data.sender}: ${data.data.content}`);
    }
};
```

## 🎯 Next Steps

1. **Monitor your first conversation** through the web interface
2. **Check database stats** with `python migrate_db.py info`
3. **Experiment with memory settings** (mem_rounds) to optimize performance
4. **Set up automated backups** for production use

For questions or issues, check the troubleshooting section above or review the database test output for clues.
