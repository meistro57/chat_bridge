#!/usr/bin/env python3
"""
MCP Log Population Script
Populates the MCP database with conversation data from existing log files.

This script processes:
- transcripts/*.md files (Markdown conversation transcripts)
- logs/*.log files (Structured session logs)

And ensures all conversation data is available in bridge.db for MCP memory access.
"""

import os
import re
import sqlite3
import json
import glob
from datetime import datetime
from pathlib import Path

def parse_markdown_transcript(file_path: str) -> dict:
    """Parse a Markdown transcript into conversation data."""
    conversation = {
        'id': None,  # Will generate
        'starter': '',
        'agent_a_provider': 'unknown',
        'agent_b_provider': 'unknown',
        'status': 'completed',
        'timestamp': None,
        'messages': []
    }

    try:
        with open(file_path, 'r', encoding='utf-8') as f:
            content = f.read()

        # Extract metadata from filename or content
        filename = os.path.basename(file_path)
        # Filenames like: 2025-10-13__conversation_slug.md
        timestamp_match = re.search(r'(\d{4}-\d{2}-\d{2})__(.+)\.md', filename)
        if timestamp_match:
            date_str, slug = timestamp_match.groups()
            conversation['timestamp'] = f"{date_str}T00:00:00"
            conversation['id'] = slug

        # Look for metadata in the content
        lines = content.split('\n')
        for line in lines[:20]:  # Check first 20 lines for metadata
            if 'Agent A:' in line:
                # Try to extract provider names
                pass
            if 'Session created at' in line:
                # Extract timestamp if not in filename
                ts_match = re.search(r'Session created at (.+)', line)
                if ts_match:
                    conversation['timestamp'] = ts_match.group(1)

        # Extract messages (rough parsing)
        messages = []
        current_agent = None

        for line in lines:
            line = line.strip()
            if 'Agent A:' in line:
                current_agent = 'Agent A (unknown)'
                content = line.replace('**Agent A:**', '').strip()
                if content:
                    messages.append({
                        'conversation_id': conversation['id'],
                        'agent_provider': 'unknown',
                        'role': 'user' if 'Agent A' in line else 'assistant',
                        'content': content,
                        'timestamp': datetime.now().isoformat()
                    })
            elif 'Agent B:' in line:
                current_agent = 'Agent B (unknown)'
                content = line.replace('**Agent B:**', '').strip()
                if content:
                    messages.append({
                        'conversation_id': conversation['id'],
                        'agent_provider': 'unknown',
                        'role': 'assistant' if current_agent else 'user',
                        'content': content,
                        'timestamp': datetime.now().isoformat()
                    })

        conversation['messages'] = messages

        # Try to extract starter message
        if messages:
            conversation['starter'] = messages[0]['content'][:200] + '...' if len(messages[0]['content']) > 200 else messages[0]['content']

    except Exception as e:
        print(f"Error parsing {file_path}: {e}")
        return None

    return conversation if conversation['messages'] else None

def parse_session_log(file_path: str) -> dict:
    """Parse a session log file into conversation data."""
    conversation = {
        'id': None,
        'starter': '',
        'agent_a_provider': 'unknown',
        'agent_b_provider': 'unknown',
        'status': 'completed',
        'timestamp': None,
        'messages': []
    }

    try:
        with open(file_path, 'r', encoding='utf-8') as f:
            logs = f.readlines()

        # Generate ID from filename
        filename = os.path.basename(file_path)
        conversation['id'] = os.path.splitext(filename)[0]

        # Extract timestamp from filename (assuming format: timestamp__slug.log)
        ts_match = re.search(r'(\d{4}-\d{2}-\d{2}[T_]\d{2}[-:]\d{2}[-:]\d{2})', filename)
        if ts_match:
            conversation['timestamp'] = ts_match.group(1).replace('_', 'T').replace('-', ':')

        # Parse log entries (simplified)
        messages = []
        for line in logs:
            if 'Message from' in line:
                # Extract message content
                parts = line.split(':', 2)
                if len(parts) >= 3:
                    agent, role, content = parts[0], parts[1], parts[2]
                    messages.append({
                        'conversation_id': conversation['id'],
                        'agent_provider': agent.replace('Message from ', '').strip(),
                        'role': role.strip(),
                        'content': content.strip(),
                        'timestamp': conversation['timestamp'] or datetime.now().isoformat()
                    })

        conversation['messages'] = messages

        if messages:
            conversation['starter'] = messages[0]['content'][:200] + '...' if len(messages[0]['content']) > 200 else messages[0]['content']

    except Exception as e:
        print(f"Error parsing {file_path}: {e}")
        return None

    return conversation if conversation['messages'] else None

def save_to_database(conversation: dict, conn: sqlite3.Connection) -> bool:
    """Save conversation to database if it doesn't exist."""
    cursor = conn.cursor()

    try:
        # Check if conversation already exists
        cursor.execute('SELECT id FROM conversations WHERE id = ?', (conversation['id'],))
        if cursor.fetchone():
            print(f"Conversation {conversation['id']} already exists, skipping.")
            return False

        # Insert conversation
        cursor.execute('''
            INSERT INTO conversations (id, timestamp, starter, agent_a_provider, agent_b_provider, status)
            VALUES (?, ?, ?, ?, ?, ?)
        ''', (
            conversation['id'],
            conversation['timestamp'],
            conversation['starter'],
            conversation['agent_a_provider'],
            conversation['agent_b_provider'],
            conversation['status']
        ))

        # Insert messages
        for msg in conversation['messages']:
            cursor.execute('''
                INSERT INTO messages (conversation_id, agent_provider, role, content, timestamp)
                VALUES (?, ?, ?, ?, ?)
            ''', (
                msg['conversation_id'],
                msg['agent_provider'],
                msg['role'],
                msg['content'],
                msg['timestamp']
            ))

        conn.commit()
        print(f"Added conversation {conversation['id']} with {len(conversation['messages'])} messages.")
        return True

    except Exception as e:
        print(f"Error saving conversation {conversation['id']}: {e}")
        conn.rollback()
        return False

def main():
    print("🔍 MCP LOG POPULATION SCRIPT")
    print("-" * 50)

    # Setup
    transcript_dir = Path('transcripts')
    logs_dir = Path('logs')
    db_path = Path('bridge.db')

    # Ensure database exists
    if not db_path.exists():
        print("❌ Database not found. Run chat_bridge.py first to create it.")
        return 1

    # Connect to database
    conn = sqlite3.connect(db_path)
    cursor = conn.cursor()

    # Create tables if they don't exist
    cursor.execute('''
        CREATE TABLE IF NOT EXISTS conversations (
            id TEXT PRIMARY KEY,
            timestamp TEXT,
            starter TEXT,
            agent_a_provider TEXT,
            agent_b_provider TEXT,
            status TEXT
        )
    ''')
    cursor.execute('''
        CREATE TABLE IF NOT EXISTS messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            conversation_id TEXT,
            agent_provider TEXT,
            role TEXT,
            content TEXT,
            timestamp TEXT,
            FOREIGN KEY (conversation_id) REFERENCES conversations(id)
        )
    ''')
    conn.commit()

    processed = 0
    skipped = 0
    errors = 0

    # Process transcript files
    if transcript_dir.exists():
        print("📄 Processing transcript files...")
        for transcript_file in transcript_dir.glob('*.md'):
            print(f"  Processing {transcript_file.name}...")
            conversation = parse_markdown_transcript(str(transcript_file))
            if conversation:
                if save_to_database(conversation, conn):
                    processed += 1
                else:
                    skipped += 1
            else:
                print(f"  ⚠️  Could not parse {transcript_file.name}")
                errors += 1
    else:
        print("📂 Transcripts directory not found.")

    # Process log files
    if logs_dir.exists():
        print("📝 Processing session log files...")
        for log_file in logs_dir.glob('*.log'):
            print(f"  Processing {log_file.name}...")
            conversation = parse_session_log(str(log_file))
            if conversation:
                if save_to_database(conversation, conn):
                    processed += 1
                else:
                    skipped += 1
            else:
                print(f"  ⚠️  Could not parse {log_file.name}")
                errors += 1
    else:
        print("📂 Logs directory not found.")

    # Final report
    print("\n" + "=" * 50)
    print("🎯 LOG POPULATION COMPLETE!")
    print(f"✅ Conversations added: {processed}")
    print(f"⏭️  Conversations skipped (already exist): {skipped}")
    print(f"❌ Errors: {errors}")

    # Check final count
    cursor.execute('SELECT COUNT(*) FROM conversations')
    final_count = cursor.fetchone()[0]
    print(f"🏆 Total conversations in MCP: {final_count}")

    conn.close()

    print("\n🚀 MCP is now populated with all available logs!")
    print("Agents can access this conversation history when --enable-mcp is used.")

    return 0 if errors == 0 else 1

if __name__ == '__main__':
    exit(main())