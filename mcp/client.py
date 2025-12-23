"""
MCP (Memory, Continuity, Protocol) client for Chat Bridge.

Supports both HTTP mode (FastAPI server) and stdio mode (FastMCP server).
Provides conversation memory and contextual search capabilities.
"""

from __future__ import annotations

import asyncio
import json
import logging
import os
import re
import sys
from typing import Dict, List

import httpx


# MCP Mode Configuration
# MCP_MODE can be "http" (FastAPI server) or "stdio" (FastMCP stdio server)
MCP_MODE = os.getenv("MCP_MODE", "http").lower()
MCP_BASE_URL = os.getenv("MCP_BASE_URL", "http://localhost:8000")

# Global MCP client for stdio mode
_mcp_process = None


def _init_stdio_mcp():
    """Initialize stdio MCP server process."""
    global _mcp_process
    if _mcp_process is None:
        import subprocess
        try:
            # Start mcp_server.py as a subprocess with stdio transport
            _mcp_process = subprocess.Popen(
                [sys.executable, "mcp_server.py"],
                stdin=subprocess.PIPE,
                stdout=subprocess.PIPE,
                stderr=subprocess.PIPE,
                text=True,
                bufsize=1
            )
            logging.debug("MCP stdio server process started")
        except Exception as e:
            logging.error(f"Failed to start MCP stdio server: {e}")
    return _mcp_process


def _query_stdio_mcp(tool_name: str, **kwargs) -> dict:
    """Query MCP stdio server with a tool call."""
    proc = _init_stdio_mcp()
    if proc is None:
        return {"success": False, "error": "MCP process not initialized"}

    try:
        # Format JSON-RPC request for MCP tool call
        request = {
            "jsonrpc": "2.0",
            "id": 1,
            "method": "tools/call",
            "params": {
                "name": tool_name,
                "arguments": kwargs
            }
        }

        # Send request
        proc.stdin.write(json.dumps(request) + "\n")
        proc.stdin.flush()

        # Read response
        response_line = proc.stdout.readline()
        if response_line:
            response = json.loads(response_line)
            if "result" in response:
                return {"success": True, "data": response["result"]}
            elif "error" in response:
                return {"success": False, "error": response["error"]}

        return {"success": False, "error": "No response from MCP server"}
    except Exception as e:
        logging.debug(f"MCP stdio query failed: {e}")
        return {"success": False, "error": str(e)}


async def query_mcp_memory_async(topic: str, limit: int = 3) -> str:
    """Query MCP server for contextual memory about a topic."""
    if MCP_MODE == "stdio":
        # Use stdio mode with mcp_server.py
        try:
            result = _query_stdio_mcp("get_contextual_memory", topic=topic, limit=limit)
            if result.get("success"):
                return result.get("data", "")
            else:
                logging.debug(f"MCP memory query failed: {result.get('error', 'Unknown error')}")
                return ""
        except Exception as e:
            logging.debug(f"MCP memory query failed: {e}")
            return ""
    else:
        # Use HTTP mode with FastAPI server (default)
        try:
            async with httpx.AsyncClient(timeout=10.0) as client:
                response = await client.get(
                    f"{MCP_BASE_URL}/api/mcp/contextual-memory",
                    params={"topic": topic, "limit": limit}
                )
                response.raise_for_status()
                data = response.json()

                if data.get("success"):
                    return data.get("context", "")
                else:
                    logging.debug(f"MCP memory query failed: {data.get('error', 'Unknown error')}")
                    return ""
        except Exception as e:
            logging.debug(f"MCP memory query failed: {e}")
            return ""


def query_mcp_memory(topic: str, limit: int = 3) -> str:
    """Query MCP server for contextual memory about a topic (sync wrapper)."""
    try:
        return asyncio.run(query_mcp_memory_async(topic, limit))
    except Exception as e:
        logging.debug(f"MCP memory query failed: {e}")
        return ""


async def get_recent_conversations_async(limit: int = 3) -> List[Dict]:
    """Get recent conversations from MCP server."""
    if MCP_MODE == "stdio":
        # Use stdio mode with mcp_server.py
        try:
            result = _query_stdio_mcp("get_recent_chats", limit=limit)
            if result.get("success"):
                return result.get("data", [])
            else:
                logging.debug(f"MCP recent conversations query failed: {result.get('error', 'Unknown error')}")
                return []
        except Exception as e:
            logging.debug(f"MCP recent conversations query failed: {e}")
            return []
    else:
        # Use HTTP mode with FastAPI server (default)
        try:
            async with httpx.AsyncClient(timeout=10.0) as client:
                response = await client.get(
                    f"{MCP_BASE_URL}/api/mcp/recent-chats",
                    params={"limit": limit}
                )
                response.raise_for_status()
                data = response.json()

                if data.get("success"):
                    return data.get("data", [])
                else:
                    logging.debug(f"MCP recent conversations query failed: {data.get('error', 'Unknown error')}")
                    return []
        except Exception as e:
            logging.debug(f"MCP recent conversations query failed: {e}")
            return []


def get_recent_conversations(limit: int = 3) -> List[Dict]:
    """Get recent conversations from MCP server (sync wrapper)."""
    try:
        return asyncio.run(get_recent_conversations_async(limit))
    except Exception as e:
        logging.debug(f"MCP recent conversations query failed: {e}")
        return []


async def check_mcp_server_async() -> bool:
    """Check if MCP server is available."""
    if MCP_MODE == "stdio":
        # Use stdio mode - check if process can be initialized
        try:
            proc = _init_stdio_mcp()
            return proc is not None and proc.poll() is None
        except Exception as e:
            logging.debug(f"MCP stdio server check failed: {e}")
            return False
    else:
        # Use HTTP mode with FastAPI server (default)
        try:
            async with httpx.AsyncClient(timeout=5.0) as client:
                response = await client.get(f"{MCP_BASE_URL}/api/mcp/health")
                response.raise_for_status()
                data = response.json()
                return data.get("success", False) and data.get("status") == "healthy"
        except Exception as e:
            logging.debug(f"MCP server check failed: {e}")
            return False


def check_mcp_server() -> bool:
    """Check if MCP server is available (sync wrapper)."""
    try:
        return asyncio.run(check_mcp_server_async())
    except:
        return False


def enhance_prompt_with_memory(original_prompt: str, enable_mcp: bool) -> str:
    """Enhance a prompt with relevant memory context if MCP is enabled."""
    if not enable_mcp or not check_mcp_server():
        return original_prompt

    # Extract key topics from the prompt for memory search
    key_terms = []
    words = original_prompt.lower().split()
    # Look for important words (skip common words)
    skip_words = {'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'is', 'are', 'was', 'were', 'be', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should', 'may', 'might', 'can', 'this', 'that', 'these', 'those', 'i', 'you', 'he', 'she', 'it', 'we', 'they'}

    for word in words:
        clean_word = re.sub(r'[^\w]', '', word)
        if len(clean_word) > 3 and clean_word not in skip_words:
            key_terms.append(clean_word)

    # Get memory for key terms
    memory_context = ""
    for term in key_terms[:3]:  # Limit to top 3 terms
        context = query_mcp_memory(term, limit=2)
        if context:
            memory_context += f"\n[Memory about {term}]: {context}"

    if memory_context:
        return f"{original_prompt}{memory_context}\n\n[Note: The above memory context is from previous conversations - use it if relevant, ignore if not.]"

    return original_prompt


def get_turn_memory_context(agent_id: str, turns, enable_mcp: bool, max_terms: int = 2) -> str:
    """Get contextual memory for a specific agent's turn.

    Args:
        agent_id: Identifier of the agent ('a' or 'b')
        turns: Recent conversation turns for context
        enable_mcp: Whether MCP is enabled
        max_terms: Maximum number of key terms to query

    Returns:
        Memory context string for the turn, or empty string if MCP unavailable/disabled
    """
    if not enable_mcp or not check_mcp_server():
        return ""

    try:
        # Extract recent messages for context
        recent_messages = [turn.text for turn in turns[-3:]]  # Last 3 turns

        # Combine recent messages to find key topics
        combined_text = " ".join(recent_messages)
        words = combined_text.lower().split()

        # Find important keywords
        skip_words = {'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'is', 'are', 'was', 'were', 'be', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should', 'may', 'might', 'can', 'this', 'that', 'these', 'those', 'i', 'you', 'he', 'she', 'it', 'we', 'they'}
        key_terms = []

        for word in words:
            clean_word = re.sub(r'[^\w]', '', word)
            if len(clean_word) > 2 and clean_word not in skip_words:
                key_terms.append(clean_word)

        # Get unique terms, limit to max_terms
        unique_terms = list(set(key_terms))[:max_terms]

        # Query MCP for each term
        memory_contexts = []
        for term in unique_terms:
            context = query_mcp_memory(term, limit=1)  # Single result per term for speed
            if context:
                memory_contexts.append(f"[{term.upper()}: {context}]")

        if memory_contexts:
            context_block = "\n".join(memory_contexts)
            logging.debug(f"MCP context for agent {agent_id}: {len(memory_contexts)} terms found")
            return f"\n[Recent Memory Context]\n{context_block}\n[Use this context if relevant to the current conversation]\n"

        logging.debug(f"No MCP context found for agent {agent_id}")
        return ""

    except Exception as e:
        logging.debug(f"Error getting MCP context for agent {agent_id}: {e}")
        return ""
