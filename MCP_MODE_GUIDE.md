# MCP Mode Toggle Guide

Chat Bridge now supports **two MCP server modes**: HTTP and stdio. You can easily toggle between them using the `MCP_MODE` environment variable.

## Quick Start

### Option 1: HTTP Mode (Recommended, Default)

```bash
# In .env file
MCP_MODE=http
MCP_BASE_URL=http://localhost:8000

# Start the FastAPI server
python main.py

# Or with uvicorn for development
uvicorn main:app --reload --host 0.0.0.0 --port 8000
```

### Option 2: stdio Mode (Legacy, MCP Standard)

```bash
# In .env file
MCP_MODE=stdio

# The stdio server process is started automatically by chat_bridge.py
# Or test manually:
python mcp_server.py
```

## Mode Comparison

| Feature | HTTP Mode | stdio Mode |
|---------|-----------|------------|
| **Server** | FastAPI (main.py) | FastMCP (mcp_server.py) |
| **Protocol** | RESTful HTTP/JSON | JSON-RPC over stdio |
| **Scalability** | High (handles concurrent requests) | Low (single process) |
| **Access** | Network accessible | Local process only |
| **Startup** | Manual server start | Auto-started by chat_bridge.py |
| **Integration** | Web GUI compatible | CLI only |
| **Standards** | Custom REST API | MCP protocol compliant |
| **Debugging** | curl, browser, HTTP tools | Manual JSON-RPC messages |

## When to Use Each Mode

### Use HTTP Mode When:
- ✅ Running the web GUI
- ✅ Need multiple concurrent conversations
- ✅ Want to access MCP from external services
- ✅ Prefer standard HTTP tooling for debugging
- ✅ Building scalable deployments

### Use stdio Mode When:
- ✅ Need MCP protocol compliance
- ✅ Integrating with MCP clients
- ✅ Running CLI-only workflows
- ✅ Want automatic process management
- ✅ Testing FastMCP features

## Testing Both Modes

Run the test suite to verify both modes work:

```bash
python test_mcp_modes.py
```

Expected output:
```
HTTP mode:  ✅ PASS
stdio mode: ✅ PASS
```

## Configuration

Add these variables to your `.env` file:

```bash
# MCP Memory System Configuration
# MCP_MODE: Choose "http" (default) for FastAPI server or "stdio" for FastMCP stdio server
# - http: RESTful HTTP API via main.py FastAPI server (recommended, scalable)
# - stdio: FastMCP stdio transport via mcp_server.py (legacy, for MCP client compatibility)
MCP_MODE=http
MCP_BASE_URL=http://localhost:8000
```

## Troubleshooting

### HTTP Mode Issues

**Problem**: Server not responding
```bash
# Check if server is running
curl http://localhost:8000/health

# Check MCP endpoints
curl http://localhost:8000/api/mcp/health

# View server logs
tail -f /tmp/mcp_server.log
```

**Solution**: Start the FastAPI server
```bash
python main.py
```

### stdio Mode Issues

**Problem**: Process fails to start
```bash
# Test mcp_server.py directly
python mcp_server.py

# Check for import errors
python -c "from fastmcp import FastMCP; print('FastMCP imported successfully')"
```

**Solution**: Ensure fastmcp is installed
```bash
pip install fastmcp
```

### Both Modes

**Problem**: MCP integration not working

**Check**:
1. Verify `MCP_MODE` is set correctly in `.env`
2. Ensure database file `bridge.db` exists and is accessible
3. Enable MCP in chat_bridge.py: `python chat_bridge.py --enable-mcp`
4. Check logs for error messages

**Note**: MCP integration gracefully degrades - conversations continue without memory if MCP fails.

## API Endpoints (HTTP Mode Only)

When using HTTP mode, these endpoints are available:

- `GET /api/mcp/health` - Health check
- `GET /api/mcp/stats` - Database statistics
- `GET /api/mcp/recent-chats?limit=N` - Recent conversations
- `GET /api/mcp/search-chats?keyword=X&limit=N` - Search messages
- `GET /api/mcp/contextual-memory?topic=X&limit=N` - Topic-relevant memories
- `GET /api/mcp/conversation/{id}` - Full conversation details

Test them with curl:
```bash
curl http://localhost:8000/api/mcp/health
curl "http://localhost:8000/api/mcp/stats"
curl "http://localhost:8000/api/mcp/recent-chats?limit=5"
```

## Implementation Details

### HTTP Mode
- Uses `httpx.AsyncClient` for async HTTP requests
- Connects to FastAPI server at `MCP_BASE_URL`
- Supports concurrent requests
- Returns JSON responses

### stdio Mode
- Spawns mcp_server.py subprocess
- Communicates via stdin/stdout using JSON-RPC
- Single-process architecture
- FastMCP protocol compliant

### Code Structure

**chat_bridge.py:154-250** - MCP integration with mode detection
- `MCP_MODE` - Environment variable for mode selection
- `_init_stdio_mcp()` - Initialize stdio server process
- `_query_stdio_mcp()` - Send JSON-RPC requests to stdio server
- `query_mcp_memory_async()` - Query with mode detection
- `get_recent_conversations_async()` - Get recent chats with mode detection
- `check_mcp_server_async()` - Health check with mode detection

## Migration Path

If you're currently using stdio mode and want to switch to HTTP:

1. Update `.env`:
   ```bash
   MCP_MODE=http
   MCP_BASE_URL=http://localhost:8000
   ```

2. Start the FastAPI server:
   ```bash
   python main.py
   ```

3. Test the connection:
   ```bash
   curl http://localhost:8000/api/mcp/health
   ```

4. Run your conversations:
   ```bash
   python chat_bridge.py --enable-mcp
   ```

If you need to revert to stdio mode:

1. Update `.env`:
   ```bash
   MCP_MODE=stdio
   ```

2. Run conversations (server auto-starts):
   ```bash
   python chat_bridge.py --enable-mcp
   ```

## Version History

- **v1.4.2** - Added MCP mode toggle (HTTP/stdio support)
- **v1.4.1** - Fixed provider status endpoint
- **v1.4.0** - Retro web GUI
- **v1.2.0** - HTTP-based MCP integration via FastAPI
- **v1.1.0** - FastMCP stdio server implementation
- **v1.0.0** - Initial MCP memory system

## Support

For issues or questions:
1. Check this guide
2. Run `python test_mcp_modes.py`
3. Review logs in `chat_bridge.log`
4. Open an issue on GitHub

---

**Recommendation**: Use HTTP mode for most use cases. It's more scalable, easier to debug, and supports the web GUI. Use stdio mode only when you need MCP protocol compliance or are integrating with MCP clients.
