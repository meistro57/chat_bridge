# MCP stdio Transport Migration

## Date
October 15, 2025

## Summary
Migrated the Chat Bridge MCP (Model Context Protocol) system from HTTP transport to stdio transport for better process isolation, no port conflicts, and simpler deployment.

## Changes Made

### 1. mcp_server.py
- Changed `mcp.run()` from `transport="http"` to `transport="stdio"`
- Updated all print statements to use `file=sys.stderr` to avoid interfering with stdio protocol
- Removed port and host parameters (no longer needed)

### 2. check_mcp_status.py
- Changed from `Client("http://localhost:5001/mcp/")` to `Client(PythonStdioTransport("mcp_server.py"))`
- Updated imports: `from fastmcp.client import Client, PythonStdioTransport`
- Updated status messages to reflect stdio transport

### 3. chat_bridge.py
- Updated all MCP functions to use `PythonStdioTransport("mcp_server.py")`
- Changed imports: `from fastmcp.client import Client, PythonStdioTransport`
- Three functions updated:
  - `query_mcp_memory_async()`
  - `get_recent_conversations_async()`
  - `check_mcp_server_async()`

### 4. CLAUDE.md
- Updated documentation to reflect stdio transport
- Removed instructions about starting MCP server manually
- Updated "Working with MCP Memory System" section
- Added migration history

## Benefits

1. **No Port Conflicts**: No need to manage HTTP server on port 5001
2. **Process Isolation**: Each MCP query runs in its own subprocess
3. **Simpler Deployment**: No need to manually start/stop the MCP server
4. **Better Resource Management**: Server processes are automatically cleaned up
5. **Standards Compliant**: stdio is the recommended transport for MCP

## Usage

The MCP server now launches automatically on-demand. No manual server startup required:

```bash
# Check MCP status
python check_mcp_status.py

# Use MCP in conversations
python chat_bridge.py --enable-mcp
```

## Technical Details

- **Transport**: PythonStdioTransport from fastmcp.client
- **Server**: FastMCP 2.12.4 with MCP SDK 1.17.0
- **Communication**: Subprocess with stdin/stdout piping
- **Logging**: Server logs go to stderr, avoiding protocol interference

## Backward Compatibility

This change is **not backward compatible** with HTTP-based MCP clients. All client code has been updated in this migration.

## Testing

Verified with:
- `python check_mcp_status.py` - All checks pass
- Database queries work correctly
- Tools and resources accessible
- Server launches and terminates properly
