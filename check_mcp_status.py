#!/usr/bin/env python3
"""
MCP Status Checker
Verifies MCP server and database content.
"""

import sqlite3
import urllib.request
import json
import sys
from pathlib import Path

def check_db():
    """Check database content."""
    db_path = Path('bridge.db')
    if not db_path.exists():
        return {"status": "error", "message": "Database not found"}

    try:
        conn = sqlite3.connect(db_path)
        cursor = conn.cursor()

        # Check tables
        tables = ['conversations', 'messages']
        table_status = {}
        for table in tables:
            cursor.execute(f"SELECT COUNT(*) FROM {table}")
            count = cursor.fetchone()[0]
            table_status[table] = count

        conn.close()
        return {"status": "ok", "tables": table_status}

    except Exception as e:
        return {"status": "error", "message": str(e)}

def check_mcp_server():
    """Check MCP server health."""
    try:
        with urllib.request.urlopen('http://localhost:5001/health', timeout=5) as f:
            response = json.loads(f.read().decode('utf-8'))
        return {"status": "ok", "response": response}
    except Exception as e:
        return {"status": "offline", "error": str(e)}

def check_log_files():
    """Check available log files."""
    transcript_dir = Path('transcripts')
    logs_dir = Path('logs')

    transcript_count = len(list(transcript_dir.glob('*.md'))) if transcript_dir.exists() else 0
    log_count = len(list(logs_dir.glob('*.log'))) if logs_dir.exists() else 0

    return {
        "transcripts": transcript_count,
        "logs": log_count
    }

def main():
    print("🔍 MCP SYSTEM STATUS CHECK")
    print("=" * 40)

    # Check database
    print("📊 Database Status:")
    db_status = check_db()
    if db_status["status"] == "ok":
        print("  ✅ Database: FOUND")
        for table, count in db_status["tables"].items():
            print(f"  📦 {table}: {count} entries")
    else:
        print(f"  ❌ Database: {db_status}")

    # Check MCP server
    print("\n🚀 MCP Server Status:")
    server_status = check_mcp_server()
    if server_status["status"] == "ok":
        print("  ✅ Server: RUNNING")
        print(f"  📍 Endpoint: localhost:{server_status['response'].get('port', 5001)}")
    else:
        print(f"  ❌ Server: {server_status['error']}")

    # Check log files
    print("\n🏗️  Log Files Available:")
    log_status = check_log_files()
    print(f"  📄 Transcripts: {log_status['transcripts']} files")
    print(f"  📝 Session logs: {log_status['logs']} files")

    # Summary
    print("\n" + "=" * 40)
    print("✨ SUMMARY:")
    all_good = (
        db_status["status"] == "ok" and
        server_status["status"] == "ok" and
        log_status['transcripts'] + log_status['logs'] > 0
    )

    if all_good:
        print("  ✅ MCP System: FULLY OPERATIONAL")
        print("  🎯 Ready for conversations with memory!")
        # Show quick MCP query if possible
        try:
            with urllib.request.urlopen('http://localhost:5001/recent_chats?limit=1', timeout=5) as f:
                recent = json.loads(f.read().decode('utf-8'))
            if recent.get('chats'):
                print(f"  🔄 Recent conversation: {recent['chats'][0]['id']}")
            else:
                print("  ℹ️  No recent conversations found")
        except:
            print("  ℹ️  Could not query recent conversations")
    else:
        print("  ⚠️  MCP System: INCOMPLETE")
        if db_status["status"] != "ok":
            print("    🔧 Run chat_bridge.py first to create database")
        if server_status["status"] != "ok":
            print("    🔧 Run python3 mcp_server.py & to start MCP server")
        if log_status['transcripts'] + log_status['logs'] == 0:
            print("    🔧 Run python populate_mcp_logs.py to import logs")

    return 0 if all_good else 1

if __name__ == "__main__":
    exit(main())