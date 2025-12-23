"""
Database operations for Chat Bridge.

Handles SQLite database setup and logging of conversations and messages.
"""

from __future__ import annotations

import sqlite3
from datetime import datetime


def setup_database():
    """Initialize SQLite database"""
    conn = sqlite3.connect("bridge.db")
    conn.execute("""
        CREATE TABLE IF NOT EXISTS conversations (
            id TEXT PRIMARY KEY,
            timestamp TEXT,
            starter TEXT,
            agent_a_provider TEXT,
            agent_b_provider TEXT,
            status TEXT
        )
    """)
    conn.execute("""
        CREATE TABLE IF NOT EXISTS messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            conversation_id TEXT,
            agent_provider TEXT,
            role TEXT,
            content TEXT,
            timestamp TEXT,
            FOREIGN KEY (conversation_id) REFERENCES conversations(id)
        )
    """)
    conn.commit()
    return conn


def log_conversation_start(conn: sqlite3.Connection, cid: str, starter: str,
                          provider_a: str, provider_b: str):
    """Log conversation start to database"""
    timestamp = datetime.now().isoformat()
    conn.execute(
        "INSERT INTO conversations VALUES (?, ?, ?, ?, ?, ?)",
        (cid, timestamp, starter, provider_a, provider_b, "active")
    )
    conn.commit()


def log_message_sql(cid: str, provider: str, role: str, content: str):
    """Log message to SQLite database"""
    conn = sqlite3.connect("bridge.db")
    timestamp = datetime.now().isoformat()
    conn.execute(
        "INSERT INTO messages (conversation_id, agent_provider, role, content, timestamp) VALUES (?, ?, ?, ?, ?)",
        (cid, provider, role, content[:10000], timestamp)  # Truncate very long messages
    )
    conn.commit()
    conn.close()
