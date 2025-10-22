#!/bin/bash
# Chat Bridge - Unified Startup Script
# Starts the FastAPI backend (includes MCP HTTP server + Web GUI)

set -e

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# ASCII Art Banner
echo -e "${BLUE}"
cat << "EOF"
   ____  _           _     ____       _     _
  / ___|| |__   __ _| |_  |  _ \ _ __(_) __| | __ _  ___
 | |    | '_ \ / _` | __| | |_) | '__| |/ _` |/ _` |/ _ \
 | |___| | | | (_| | |_  |  _ <| |  | | (_| | (_| |  __/
  \____|_| |_|\__,_|\__| |_| \_\_|  |_|\__,_|\__, |\___|
                                              |___/
  🌉 Retro AI Conversation Bridge 🎨
EOF
echo -e "${NC}"

# Check if we're in the right directory
if [ ! -f "main.py" ]; then
    echo -e "${RED}Error: main.py not found. Please run this script from the chat_bridge directory.${NC}"
    exit 1
fi

# Function to check if a port is in use
check_port() {
    lsof -Pi :$1 -sTCP:LISTEN -t >/dev/null 2>&1
}

# Function to cleanup on exit
cleanup() {
    echo -e "\n${YELLOW}Shutting down Chat Bridge...${NC}"
    if [ ! -z "$BACKEND_PID" ]; then
        kill $BACKEND_PID 2>/dev/null || true
    fi
    if [ ! -z "$FRONTEND_PID" ]; then
        kill $FRONTEND_PID 2>/dev/null || true
    fi
    exit 0
}

trap cleanup SIGINT SIGTERM

# Parse command line arguments
DEV_MODE=false
if [ "$1" == "--dev" ] || [ "$1" == "-d" ]; then
    DEV_MODE=true
    echo -e "${BLUE}🛠️  Starting in DEVELOPMENT mode${NC}"
else
    echo -e "${GREEN}🚀 Starting in PRODUCTION mode${NC}"
fi

echo ""
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}  Startup Checklist${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

# Check if port 8000 is already in use
if check_port 8000; then
    echo -e "${YELLOW}⚠️  Port 8000 is already in use${NC}"
    echo -e "${YELLOW}   Attempting to stop existing process...${NC}"
    pkill -f "python.*main.py" 2>/dev/null || true
    sleep 2
    if check_port 8000; then
        echo -e "${RED}❌ Could not free port 8000. Please stop the existing process manually.${NC}"
        exit 1
    fi
fi

# Check Python
if ! command -v python3 &> /dev/null; then
    echo -e "${RED}❌ Python 3 not found${NC}"
    exit 1
fi
echo -e "${GREEN}✓${NC} Python 3 found: $(python3 --version)"

# Check dependencies
echo -ne "${BLUE}  Checking backend dependencies...${NC}"
if python3 -c "import fastapi, uvicorn, sqlalchemy, httpx" 2>/dev/null; then
    echo -e " ${GREEN}✓${NC}"
else
    echo -e " ${RED}✗${NC}"
    echo -e "${YELLOW}  Installing backend dependencies...${NC}"
    pip install -r requirements.txt
fi

# Check MCP mode
MCP_MODE=$(grep "^MCP_MODE=" .env 2>/dev/null | cut -d'=' -f2 || echo "http")
echo -e "${GREEN}✓${NC} MCP Mode: ${BLUE}${MCP_MODE}${NC}"

# Check if frontend is built
if [ "$DEV_MODE" = false ]; then
    if [ ! -d "web_gui/frontend/dist" ]; then
        echo -e "${YELLOW}  Frontend not built. Building now...${NC}"
        cd web_gui/frontend
        if ! command -v npm &> /dev/null; then
            echo -e "${RED}❌ npm not found. Please install Node.js${NC}"
            exit 1
        fi
        npm install
        npm run build
        cd ../..
    fi
    echo -e "${GREEN}✓${NC} Frontend build found"
else
    # Check Node.js for dev mode
    if ! command -v npm &> /dev/null; then
        echo -e "${RED}❌ npm not found. Please install Node.js for dev mode${NC}"
        exit 1
    fi
    echo -e "${GREEN}✓${NC} Node.js found: $(node --version)"
fi

echo ""
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}  Starting Services${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

# Start backend (FastAPI server with MCP endpoints)
echo -e "${BLUE}🚀 Starting FastAPI backend (includes MCP server)...${NC}"
python3 main.py > logs/backend.log 2>&1 &
BACKEND_PID=$!

# Wait for backend to start
echo -ne "${BLUE}   Waiting for backend to be ready${NC}"
for i in {1..30}; do
    if curl -s http://localhost:8000/health > /dev/null 2>&1; then
        echo -e " ${GREEN}✓${NC}"
        break
    fi
    echo -n "."
    sleep 1
    if [ $i -eq 30 ]; then
        echo -e " ${RED}✗${NC}"
        echo -e "${RED}Backend failed to start. Check logs/backend.log${NC}"
        exit 1
    fi
done

# Verify MCP endpoints
echo -ne "${BLUE}   Checking MCP endpoints${NC}"
if curl -s http://localhost:8000/api/mcp/health > /dev/null 2>&1; then
    echo -e " ${GREEN}✓${NC}"
else
    echo -e " ${YELLOW}⚠${NC}  (MCP endpoints may not be available)"
fi

# Start frontend in dev mode if requested
if [ "$DEV_MODE" = true ]; then
    echo -e "${BLUE}🚀 Starting Vite dev server...${NC}"
    cd web_gui/frontend
    npm run dev > ../../logs/frontend.log 2>&1 &
    FRONTEND_PID=$!
    cd ../..

    echo -ne "${BLUE}   Waiting for frontend to be ready${NC}"
    for i in {1..30}; do
        if curl -s http://localhost:5173 > /dev/null 2>&1; then
            echo -e " ${GREEN}✓${NC}"
            break
        fi
        echo -n "."
        sleep 1
    done
fi

echo ""
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${GREEN}  🎉 Chat Bridge is Running!${NC}"
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""
echo -e "  ${BLUE}Web GUI:${NC}          http://localhost:8000"
if [ "$DEV_MODE" = true ]; then
    echo -e "  ${BLUE}Dev Server:${NC}       http://localhost:5173"
fi
echo -e "  ${BLUE}API Docs:${NC}         http://localhost:8000/docs"
echo -e "  ${BLUE}MCP Health:${NC}       http://localhost:8000/api/mcp/health"
echo -e "  ${BLUE}Backend Log:${NC}      logs/backend.log"
if [ "$DEV_MODE" = true ]; then
    echo -e "  ${BLUE}Frontend Log:${NC}     logs/frontend.log"
fi
echo ""
echo -e "  ${BLUE}MCP Mode:${NC}         ${GREEN}${MCP_MODE}${NC}"
if [ "$MCP_MODE" = "stdio" ]; then
    echo -e "  ${YELLOW}Note: stdio mode uses mcp_server.py subprocess${NC}"
fi
echo ""
echo -e "${YELLOW}Press Ctrl+C to stop all services${NC}"
echo ""

# Keep script running and show live logs
if [ "$DEV_MODE" = true ]; then
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${BLUE}  Live Backend Logs (Ctrl+C to stop)${NC}"
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    tail -f logs/backend.log
else
    # In production mode, just wait
    wait $BACKEND_PID
fi
