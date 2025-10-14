#!/usr/bin/env python3
"""
MCP Memory Server
HTTP API server for the Memory, Continuity, Protocol system.

Provides endpoints for querying conversation history from bridge.db.
"""

import sqlite3
import json
from flask import Flask, request, jsonify
from datetime import datetime
from typing import List, Dict, Any
import logging

# Configure logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

app = Flask(__name__)

def get_db_connection():
    """Get database connection."""
    conn = sqlite3.connect('bridge.db')
    conn.row_factory = sqlite3.Row
    return conn

def query_recent_chats(limit: int = 10) -> List[Dict]:
    """Query recent conversations from database."""
    conn = get_db_connection()
    cursor = conn.cursor()

    cursor.execute('''
        SELECT id, timestamp, starter, agent_a_provider, agent_b_provider, status
        FROM conversations
        ORDER BY timestamp DESC
        LIMIT ?
    ''', (limit,))

    rows = cursor.fetchall()
    conn.close()

    return [{
        'id': row['id'],
        'timestamp': row['timestamp'],
        'starter': row['starter'],
        'agent_a_provider': row['agent_a_provider'],
        'agent_b_provider': row['agent_b_provider'],
        'status': row['status']
    } for row in rows]

def search_chats(keyword: str, limit: int = 5) -> List[Dict]:
    """Search conversations by keyword in messages."""
    conn = get_db_connection()
    cursor = conn.cursor()

    cursor.execute('''
        SELECT DISTINCT c.id, c.timestamp, c.starter, m.content
        FROM conversations c
        JOIN messages m ON c.id = m.conversation_id
        WHERE m.content LIKE ?
        ORDER BY c.timestamp DESC
        LIMIT ?
    ''', (f'%{keyword}%', limit))

    rows = cursor.fetchall()
    conn.close()

    return [{
        'conversation_id': row['id'],
        'timestamp': row['timestamp'],
        'starter': row['starter'],
        'content': row['content']
    } for row in rows]

def get_contextual_memory(topic: str, limit: int = 3) -> str:
    """Get contextual memory related to a topic."""
    # Simple implementation: search for topic keyword
    results = search_chats(topic, limit)

    if not results:
        return ""

    # Build context string
    context_parts = []
    for result in results:
        context_parts.append(f"From {result['timestamp']}: {result['content'][:100]}...")

    return "\n".join(context_parts) if context_parts else ""

@app.route('/health')
def health_check():
    """Health check endpoint."""
    return jsonify({
        'status': 'healthy',
        'port': 5001,
        'timestamp': datetime.now().isoformat(),
        'version': '1.0.0'
    })

@app.route('/recent_chats', methods=['GET'])
def recent_chats_endpoint():
    """Get recent conversations."""
    try:
        limit = int(request.args.get('limit', 10))
        chats = query_recent_chats(limit)
        return jsonify({'chats': chats})
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/search_chats', methods=['GET'])
def search_chats_endpoint():
    """Search conversations by keyword."""
    try:
        keyword = request.args.get('keyword', '')
        limit = int(request.args.get('limit', 5))
        results = search_chats(keyword, limit)
        return jsonify({'results': results})
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/contextual_memory', methods=['GET'])
def contextual_memory_endpoint():
    """Get contextual memory for a topic."""
    try:
        topic = request.args.get('topic', '')
        limit = int(request.args.get('limit', 3))
        context = get_contextual_memory(topic, limit)
        return jsonify({'context': context})
    except Exception as e:
        return jsonify({'error': str(e)}), 500

if __name__ == '__main__':
    print("🚀 Starting MCP Memory Server on port 5001...")
    print("Available endpoints:")
    print("  GET /health - Server health check")
    print("  GET /recent_chats?limit=N - Recent conversations")
    print("  GET /search_chats?keyword=X&limit=N - Keyword search")
    print("  GET /contextual_memory?topic=X&limit=N - Topic-relevant memory")
    print()
    print("🗄️  Connected to bridge.db for conversation history")
    print("Press Ctrl+C to stop")

    app.run(host='0.0.0.0', port=5001, debug=False)