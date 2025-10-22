#!/bin/bash
# Chat Bridge - Stop Script
# Stops all Chat Bridge services

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${YELLOW}Stopping Chat Bridge services...${NC}"

# Stop backend
if pgrep -f "python.*main.py" > /dev/null; then
    pkill -f "python.*main.py"
    echo -e "${GREEN}✓${NC} Stopped FastAPI backend"
else
    echo -e "${YELLOW}⚠${NC}  Backend not running"
fi

# Stop frontend dev server if running
if pgrep -f "vite" > /dev/null; then
    pkill -f "vite"
    echo -e "${GREEN}✓${NC} Stopped Vite dev server"
fi

# Stop any MCP stdio processes
if pgrep -f "mcp_server.py" > /dev/null; then
    pkill -f "mcp_server.py"
    echo -e "${GREEN}✓${NC} Stopped MCP stdio server"
fi

echo -e "${GREEN}All services stopped${NC}"
