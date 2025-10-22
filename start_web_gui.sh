#!/bin/bash

# Start Chat Bridge Web GUI Services
echo "🚀 Starting Chat Bridge Web GUI Services..."

# Load environment variables from .env file
if [ -f ".env" ]; then
    export $(cat .env | xargs)
    echo "✅ Loaded environment variables from .env"
else
    echo "⚠️  .env file not found, using system environment variables"
fi

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to install Python backend dependencies
install_backend() {
    echo -e "${BLUE}📦 Installing backend dependencies...${NC}"
    cd web_gui/backend
    pip install -r requirements.txt
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✅ Backend dependencies installed successfully${NC}"
    else
        echo -e "${YELLOW}⚠️  Backend dependency installation may have issues${NC}"
    fi
    cd ../..
}

# Function to install frontend dependencies
install_frontend() {
    echo -e "${BLUE}📦 Installing frontend dependencies...${NC}"
    cd web_gui/frontend
    npm install
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✅ Frontend dependencies installed successfully${NC}"
    else
        echo -e "${YELLOW}⚠️  Frontend dependency installation may have issues${NC}"
    fi
    cd ../..
}

# Function to start backend server
start_backend() {
    echo -e "${BLUE}🖥️  Starting backend server (FastAPI)...${NC}"
    cd web_gui/backend
    # Kill any process on port 8000 first
    lsof -ti:8000 | xargs kill -9 2>/dev/null || true
    uvicorn main:app --reload --host 0.0.0.0 --port 8000 &
    BACKEND_PID=$!
    echo -e "${GREEN}✅ Backend server started (PID: $BACKEND_PID)${NC}"
    echo "🌐 Backend API available at: http://localhost:8000"
    cd ../..
}

# Function to start frontend server
start_frontend() {
    echo -e "${BLUE}🖥️  Starting frontend server (React + Vite)...${NC}"
    cd web_gui/frontend
    npm run dev &
    FRONTEND_PID=$!
    echo -e "${GREEN}✅ Frontend server started (PID: $FRONTEND_PID)${NC}"
    echo "🌐 Frontend available at: http://localhost:5173"
    cd ../..
}

# Function to cleanup processes on exit
cleanup() {
    echo -e "${YELLOW}🛑 Stopping services...${NC}"
    if [ ! -z "$BACKEND_PID" ]; then
        kill $BACKEND_PID 2>/dev/null
        echo -e "${GREEN}✅ Backend server stopped${NC}"
    fi
    if [ ! -z "$FRONTEND_PID" ]; then
        kill $FRONTEND_PID 2>/dev/null
        echo -e "${GREEN}✅ Frontend server stopped${NC}"
    fi
    exit
}

# Set trap to cleanup on script exit
trap cleanup SIGINT SIGTERM

# Main execution
echo -e "${GREEN}🎨 Chat Bridge Web GUI - Retro Edition${NC}"
echo -e "${GREEN}⚡ Real-time AI conversation bridging${NC}"
echo ""

# Check for API keys FIRST
check_api_keys

echo ""

# Install dependencies
install_backend
install_frontend

# Start services
start_backend
start_frontend

echo ""
echo -e "${GREEN}✅ All services started successfully!${NC}"
echo -e "${BLUE}🌐 Access the Chat Bridge Web GUI at: http://localhost:5173${NC}"
echo -e "${BLUE}🔗 Backend API at: http://localhost:8000${NC}"
echo ""
echo -e "${YELLOW}Press Ctrl+C to stop all services${NC}"

# Wait for services
wait