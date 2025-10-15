#!/usr/bin/env python3
"""
MCP Memory Server
FastMCP-based server for the Memory, Continuity, Protocol system.

Provides tools and resources for querying conversation history from bridge.db.
"""

import sqlite3
from datetime import datetime
from typing import List, Dict
import logging

try:
    # Try the expected mcp.server.fastmcp import first (for mcp dev CLI)
    from mcp.server.fastmcp import FastMCP
except ImportError:
    # Fall back to standalone fastmcp package
    from fastmcp import FastMCP

# Configure logging
logging.basicConfig(level=logging.DEBUG)
logger = logging.getLogger(__name__)

# Initialize FastMCP server
mcp = FastMCP("Chat Bridge Memory 🧠")

def get_db_connection():
    """Get database connection."""
    conn = sqlite3.connect('bridge.db')
    conn.row_factory = sqlite3.Row
    return conn


# MCP Tools (functions that can be called by LLMs)

@mcp.tool()
def get_recent_chats(limit: int = 10) -> List[Dict]:
    """
    Query recent conversations from the database.

    Args:
        limit: Maximum number of conversations to return (default: 10)

    Returns:
        List of recent conversations with metadata
    """
    try:
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
    except Exception as e:
        logger.error(f"Error getting recent chats: {e}")
        return []


@mcp.tool()
def search_chats(keyword: str, limit: int = 5) -> List[Dict]:
    """
    Search conversations by keyword in message content.

    Args:
        keyword: The search term to look for in message content
        limit: Maximum number of results to return (default: 5)

    Returns:
        List of conversations containing the keyword
    """
    try:
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
    except Exception as e:
        logger.error(f"Error searching chats: {e}")
        return []


@mcp.tool()
def get_contextual_memory(topic: str, limit: int = 3) -> str:
    """
    Get contextual memory related to a specific topic.

    Args:
        topic: The topic to search for in conversation history
        limit: Maximum number of relevant memories to return (default: 3)

    Returns:
        Formatted string containing relevant conversation excerpts
    """
    try:
        # Search for topic keyword
        results = search_chats(topic, limit)

        if not results:
            return ""

        # Build context string
        context_parts = []
        for result in results:
            context_parts.append(
                f"From {result['timestamp']}: {result['content'][:100]}..."
            )

        return "\n".join(context_parts) if context_parts else ""
    except Exception as e:
        logger.error(f"Error getting contextual memory: {e}")
        return ""


@mcp.tool()
def get_conversation_by_id(conversation_id: str) -> Dict:
    """
    Get full conversation details by ID.

    Args:
        conversation_id: The unique conversation ID

    Returns:
        Dictionary containing conversation metadata and all messages
    """
    try:
        conn = get_db_connection()
        cursor = conn.cursor()

        # Get conversation metadata
        cursor.execute('''
            SELECT id, timestamp, starter, agent_a_provider, agent_b_provider, status
            FROM conversations
            WHERE id = ?
        ''', (conversation_id,))

        conv_row = cursor.fetchone()
        if not conv_row:
            conn.close()
            return {"error": "Conversation not found"}

        # Get all messages for this conversation
        cursor.execute('''
            SELECT agent_provider, role, content, timestamp
            FROM messages
            WHERE conversation_id = ?
            ORDER BY timestamp ASC
        ''', (conversation_id,))

        message_rows = cursor.fetchall()
        conn.close()

        return {
            'id': conv_row['id'],
            'timestamp': conv_row['timestamp'],
            'starter': conv_row['starter'],
            'agent_a_provider': conv_row['agent_a_provider'],
            'agent_b_provider': conv_row['agent_b_provider'],
            'status': conv_row['status'],
            'messages': [{
                'agent_provider': msg['agent_provider'],
                'role': msg['role'],
                'content': msg['content'],
                'timestamp': msg['timestamp']
            } for msg in message_rows]
        }
    except Exception as e:
        logger.error(f"Error getting conversation: {e}")
        return {"error": str(e)}


# MCP Resources (data that can be loaded into LLM context)

@mcp.resource("bridge://stats")
def get_database_stats() -> str:
    """
    Get statistics about the conversation database.

    Returns:
        Formatted statistics about conversations and messages
    """
    try:
        conn = get_db_connection()
        cursor = conn.cursor()

        # Count conversations
        cursor.execute('SELECT COUNT(*) FROM conversations')
        conv_count = cursor.fetchone()[0]

        # Count messages
        cursor.execute('SELECT COUNT(*) FROM messages')
        msg_count = cursor.fetchone()[0]

        # Get most recent conversation time
        cursor.execute('SELECT MAX(timestamp) FROM conversations')
        last_conv = cursor.fetchone()[0]

        conn.close()

        return f"""Chat Bridge Database Statistics:
- Total conversations: {conv_count}
- Total messages: {msg_count}
- Last conversation: {last_conv or 'None'}
- Average messages per conversation: {msg_count / conv_count if conv_count > 0 else 0:.1f}
"""
    except Exception as e:
        logger.error(f"Error getting database stats: {e}")
        return f"Error: {str(e)}"


@mcp.resource("bridge://health")
def health_check() -> str:
    """
    Check the health status of the MCP server.

    Returns:
        Health status information
    """
    try:
        # Test database connection
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute('SELECT 1')
        conn.close()

        return f"""MCP Server Health Check:
- Status: Healthy ✅
- Server: Chat Bridge Memory
- Timestamp: {datetime.now().isoformat()}
- Database: Connected
- Version: 2.0.0 (FastMCP)
"""
    except Exception as e:
        logger.error(f"Health check failed: {e}")
        return f"MCP Server Health Check:\n- Status: Unhealthy ❌\n- Error: {str(e)}"


if __name__ == '__main__':
    # Run the FastMCP server with stdio transport
    mcp.run(transport="stdio")
