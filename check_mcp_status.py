#!/usr/bin/env python3
"""MCP Status Checker with clearer flow and error handling."""

from __future__ import annotations

import asyncio
import sqlite3
from contextlib import closing
from dataclasses import dataclass, field
from pathlib import Path
from typing import Awaitable, Callable, Dict, Iterable, Optional, Sequence

from fastmcp.client import Client, PythonStdioTransport


# Data containers keep output predictable and testable
@dataclass
class DatabaseStatus:
    status: str
    tables: Dict[str, int] = field(default_factory=dict)
    message: Optional[str] = None


@dataclass
class ServerStatus:
    status: str
    resources: int = 0
    tools: int = 0
    health: str = "Not available"
    error: Optional[str] = None


@dataclass
class LogStatus:
    transcripts: int
    logs: int


@dataclass
class ToolTestResult:
    status: str
    message: Optional[str] = None
    sample_id: Optional[str] = None


def check_db(db_path: Path = Path("bridge.db")) -> DatabaseStatus:
    """Check database content and table counts with friendly errors."""

    if not db_path.exists():
        return DatabaseStatus(status="error", message="Database not found")

    try:
        with closing(sqlite3.connect(db_path)) as conn:
            cursor = conn.cursor()
            table_counts: Dict[str, int] = {}
            for table in ("conversations", "messages"):
                cursor.execute(f"SELECT COUNT(*) FROM {table}")
                count_row = cursor.fetchone()
                table_counts[table] = int(count_row[0] if count_row else 0)

        return DatabaseStatus(status="ok", tables=table_counts)
    except sqlite3.Error as exc:  # Explicit SQLite error surfaces actionable info
        return DatabaseStatus(status="error", message=str(exc))
    except Exception as exc:  # Fallback for unexpected issues
        return DatabaseStatus(status="error", message=str(exc))


async def _with_mcp_client(
    server_script: str,
    action: Callable[[Client], Awaitable[ServerStatus]],
) -> ServerStatus:
    """Create a client, run an async action, and surface errors."""

    transport = PythonStdioTransport(server_script)
    async with Client(transport) as client:
        return await action(client)


async def check_mcp_server_async(server_script: str = "mcp_server.py") -> ServerStatus:
    """Check FastMCP server health via stdio transport."""

    async def run(client: Client) -> ServerStatus:
        resources = await client.list_resources()
        tools = await client.list_tools()
        health_text = await _extract_health(resources, client)
        return ServerStatus(
            status="ok",
            resources=len(resources),
            tools=len(tools),
            health=health_text,
        )

    try:
        return await _with_mcp_client(server_script, run)
    except Exception as exc:
        return ServerStatus(status="offline", error=str(exc))


def check_mcp_server(server_script: str = "mcp_server.py") -> ServerStatus:
    """Synchronously wrap the async server probe for CLI use."""

    try:
        return asyncio.run(check_mcp_server_async(server_script))
    except Exception as exc:
        return ServerStatus(status="offline", error=str(exc))


async def _extract_health(resources: Sequence, client: Client) -> str:
    """Return health text if a resource with 'health' in its URI exists."""

    for resource in resources:
        if "health" in str(resource.uri):
            resource_content = await client.read_resource(str(resource.uri))
            if isinstance(resource_content, Iterable) and not isinstance(resource_content, (str, bytes)):
                resource_content = next(iter(resource_content), None)

            if hasattr(resource_content, "text"):
                return getattr(resource_content, "text") or "Not available"
            return str(resource_content)
    return "Not available"


def check_log_files(
    transcript_dir: Path = Path("transcripts"),
    logs_dir: Path = Path("logs"),
) -> LogStatus:
    """Summarise available transcript and session log files."""

    transcript_count = len(list(transcript_dir.glob("*.md"))) if transcript_dir.exists() else 0
    log_count = len(list(logs_dir.glob("*.log"))) if logs_dir.exists() else 0
    return LogStatus(transcripts=transcript_count, logs=log_count)


async def test_mcp_tools(server_script: str = "mcp_server.py") -> ToolTestResult:
    """Test MCP tools by calling them via stdio transport."""

    async def run(client: Client) -> ToolTestResult:
        result = await client.call_tool("get_recent_chats", {"limit": 1})
        sample_items = getattr(result, "data", []) or []
        sample_id = _extract_id(sample_items[0]) if sample_items else None
        return ToolTestResult(status="ok", sample_id=sample_id)

    try:
        return await _with_mcp_client(server_script, run)
    except Exception as exc:
        return ToolTestResult(status="error", message=str(exc))


def _extract_id(item: object) -> str:
    """Extract an identifier from FastMCP responses that vary in shape."""

    if isinstance(item, dict):
        return str(item.get("id", "N/A"))
    return str(getattr(item, "id", "N/A"))


def _print_database_status(status: DatabaseStatus) -> None:
    print("📊 Database Status:")
    if status.status == "ok":
        print("  ✅ Database: FOUND")
        for table, count in status.tables.items():
            print(f"  📦 {table}: {count} entries")
    else:
        print(f"  ❌ Database: {status.message}")


def _print_server_status(status: ServerStatus) -> None:
    print("\n🚀 FastMCP Server Status:")
    if status.status == "ok":
        print("  ✅ Server: ACCESSIBLE")
        print("  📍 Transport: stdio")
        print(f"  🛠️  Tools available: {status.tools}")
        print(f"  📦 Resources available: {status.resources}")
        print(f"  🩺 Health: {status.health}")
    else:
        print(f"  ❌ Server: {status.error or 'Unknown error'}")


def _print_log_status(status: LogStatus) -> None:
    print("\n🏗️  Log Files Available:")
    print(f"  📄 Transcripts: {status.transcripts} files")
    print(f"  📝 Session logs: {status.logs} files")


def _print_tool_test(result: ToolTestResult) -> None:
    print("\n🧪 Testing MCP Tools:")
    if result.status == "ok":
        print("  ✅ Tools: WORKING")
        if result.sample_id:
            print(f"  🔄 Recent conversation: {result.sample_id}")
    else:
        print(f"  ⚠️  Tools test failed: {result.message or 'Unknown error'}")


def main() -> int:
    print("🔍 MCP SYSTEM STATUS CHECK (FastMCP)")
    print("=" * 50)

    db_status = check_db()
    _print_database_status(db_status)

    server_status = check_mcp_server()
    _print_server_status(server_status)

    log_status = check_log_files()
    _print_log_status(log_status)

    if server_status.status == "ok":
        tool_result = asyncio.run(test_mcp_tools())
        _print_tool_test(tool_result)

    print("\n" + "=" * 50)
    print("✨ SUMMARY:")
    has_logs = log_status.transcripts + log_status.logs > 0
    all_good = db_status.status == "ok" and server_status.status == "ok" and has_logs

    if all_good:
        print("  ✅ MCP System: FULLY OPERATIONAL")
        print("  🎯 Ready for conversations with memory!")
        print("  💡 Using FastMCP 2.0 with stdio transport")
    else:
        print("  ⚠️  MCP System: INCOMPLETE")
        if db_status.status != "ok":
            print("    🔧 Run chat_bridge.py first to create database")
        if server_status.status != "ok":
            print("    🔧 Ensure mcp_server.py is accessible in the current directory")
        if not has_logs:
            print("    🔧 Run python populate_mcp_logs.py to import logs")

    return 0 if all_good else 1


if __name__ == "__main__":
    raise SystemExit(main())
