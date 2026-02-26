# Model Context Protocol (MCP) Server

Chat Bridge includes a native MCP server implementation, allowing AI agents (like [Crush](https://crush.charm.land)) to interact with your conversation data as a set of tools.

## Overview

The MCP server provides a standardized JSON-RPC 2.0 interface for AI models to query your local database. It uses HTTP as the primary transport.

- **Endpoint**: `POST /api/mcp`
- **Protocol Version**: `2024-11-05`
- **Transport**: HTTP (Stateless)

## Available Tools

| Tool | Description | Parameters |
| :--- | :--- | :--- |
| `search_chats` | Search messages by keyword. | `keyword` (string) |
| `recent_chats` | Get N most recent conversations. | `limit` (int, default 10) |
| `get_conversation`| Get full history for an ID. | `conversation_id` (string) |
| `get_stats` | Database statistics. | None |

## Configuration

### Crush Integration

To use Chat Bridge as an MCP server for Crush, add the following to your `crush.json` (usually managed via `mcp set` or manually):

```json
{
  "mcpServers": {
    "chat-bridge": {
      "type": "http",
      "url": "http://localhost:8000/api/mcp"
    }
  }
}
```

Replace `http://localhost:8000` with your actual application URL if it is running on a different port.

## Technical Implementation

The server implements the following JSON-RPC 2.0 methods:

1.  **`initialize`**: Negotiates protocol capabilities and server info.
2.  **`tools/list`**: Returns the list of tools defined in `App\Http\Controllers\McpController`.
3.  **`tools/call`**: Executes the requested tool and returns the result in MCP-compatible content blocks.

### Example Request (Initialize)

```bash
curl -X POST http://localhost:8000/api/mcp \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2024-11-05","clientInfo":{"name":"test","version":"1.0"}}}'
```

### Security

For local development, no authentication is required. For production use, ensure this endpoint is protected by your preferred authentication middleware or network-level security.

---
💘 Generated with Crush
