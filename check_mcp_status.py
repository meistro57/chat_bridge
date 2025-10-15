#!/usr/bin/env python3
"""
MCP Status Checker
Verifies FastMCP server and database content.
"""

import sqlite3
import sys
import asyncio
import subprocess
from pathlib import Path
from fastmcp.client import Client, PythonStdioTransport


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


async def check_mcp_server_async():
    """Check FastMCP server health via stdio."""
    try:
        # Create stdio transport for Python MCP server
        transport = PythonStdioTransport("mcp_server.py")

        async with Client(transport) as client:
            # List available resources
            resources = await client.list_resources()

            # Try to read the health resource
            health_resource = None
            for resource in resources:
                if "health" in str(resource.uri):
                    health_resource = await client.read_resource(str(resource.uri))
                    break

            # List available tools
            tools = await client.list_tools()

            health_text = "Not available"
            if health_resource:
                # health_resource is a list of ReadResourceResult
                if isinstance(health_resource, list) and len(health_resource) > 0:
                    health_text = health_resource[0].text if hasattr(health_resource[0], 'text') else str(health_resource[0])
                elif hasattr(health_resource, 'text'):
                    health_text = health_resource.text

            return {
                "status": "ok",
                "resources": len(resources),
                "tools": len(tools),
                "health": health_text
            }
    except Exception as e:
        return {"status": "offline", "error": str(e)}


def check_mcp_server():
    """Check FastMCP server health (sync wrapper)."""
    try:
        return asyncio.run(check_mcp_server_async())
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


async def test_mcp_tools():
    """Test MCP tools by calling them via stdio."""
    try:
        # Create stdio transport for Python MCP server
        transport = PythonStdioTransport("mcp_server.py")

        async with Client(transport) as client:
            # Test get_recent_chats tool
            result = await client.call_tool("get_recent_chats", {"limit": 1})
            return {"status": "ok", "sample_data": result.data}
    except Exception as e:
        return {"status": "error", "message": str(e)}


def main():
    print("🔍 MCP SYSTEM STATUS CHECK (FastMCP)")
    print("=" * 50)

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
    print("\n🚀 FastMCP Server Status:")
    server_status = check_mcp_server()
    if server_status["status"] == "ok":
        print("  ✅ Server: ACCESSIBLE")
        print(f"  📍 Transport: stdio")
        print(f"  🛠️  Tools available: {server_status.get('tools', 0)}")
        print(f"  📦 Resources available: {server_status.get('resources', 0)}")
    else:
        print(f"  ❌ Server: {server_status.get('error', 'Unknown error')}")

    # Check log files
    print("\n🏗️  Log Files Available:")
    log_status = check_log_files()
    print(f"  📄 Transcripts: {log_status['transcripts']} files")
    print(f"  📝 Session logs: {log_status['logs']} files")

    # Test MCP tools
    if server_status["status"] == "ok":
        print("\n🧪 Testing MCP Tools:")
        test_result = asyncio.run(test_mcp_tools())
        if test_result["status"] == "ok":
            print("  ✅ Tools: WORKING")
            sample = test_result.get("sample_data", [])
            if sample and len(sample) > 0:
                # Handle different data types returned by FastMCP
                first_item = sample[0]
                if isinstance(first_item, dict):
                    conv_id = first_item.get('id', 'N/A')
                else:
                    conv_id = getattr(first_item, 'id', 'N/A')
                print(f"  🔄 Recent conversation: {conv_id}")
        else:
            print(f"  ⚠️  Tools test failed: {test_result.get('message', 'Unknown')}")

    # Summary
    print("\n" + "=" * 50)
    print("✨ SUMMARY:")
    all_good = (
        db_status["status"] == "ok" and
        server_status["status"] == "ok" and
        log_status['transcripts'] + log_status['logs'] > 0
    )

    if all_good:
        print("  ✅ MCP System: FULLY OPERATIONAL")
        print("  🎯 Ready for conversations with memory!")
        print("  💡 Using FastMCP 2.0 with stdio transport")
    else:
        print("  ⚠️  MCP System: INCOMPLETE")
        if db_status["status"] != "ok":
            print("    🔧 Run chat_bridge.py first to create database")
        if server_status["status"] != "ok":
            print("    🔧 Ensure mcp_server.py is accessible in the current directory")
        if log_status['transcripts'] + log_status['logs'] == 0:
            print("    🔧 Run python populate_mcp_logs.py to import logs")

    return 0 if all_good else 1


if __name__ == "__main__":
    exit(main())
