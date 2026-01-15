#!/bin/bash

# Navigate to the project directory
PROJECT_DIR="chat_bridge_laravel"

if [ -d "$PROJECT_DIR" ]; then
    cd "$PROJECT_DIR"
else
    echo "❌ Error: Directory '$PROJECT_DIR' not found."
    exit 1
fi

# Function to kill background processes on exit
cleanup() {
    echo ""
    echo "🛑 Shutting down Chat Bridge..."
    kill $REVERB_PID $QUEUE_PID $VITE_PID $SERVE_PID 2>/dev/null
    exit
}

# Trap Ctrl+C (SIGINT)
trap cleanup SIGINT

echo "🚀 Firing up Chat Bridge (Laravel Reverb Edition)..."

# 1. Start Reverb Server (WebSockets)
echo "🔊 Starting Reverb Server (Port 8080)..."
php artisan reverb:start > /dev/null 2>&1 &
REVERB_PID=$!

# 2. Start Queue Worker (Processes AI turns)
echo "👷 Starting Queue Worker..."
php artisan queue:listen --tries=1 > /dev/null 2>&1 &
QUEUE_PID=$!

# 3. Start Vite (Frontend Assets)
echo "🎨 Starting Frontend (Vite)..."
npm run dev > /dev/null 2>&1 &
VITE_PID=$!

# 4. Start Laravel Application Server
echo "🌐 Starting Application Server..."
php artisan serve --host=0.0.0.0 --port=8000 > /dev/null 2>&1 &
SERVE_PID=$!

# Wait a moment for services to spin up
sleep 3

echo ""
echo "✅ ALL SYSTEMS GO!"
echo "===================================================="
echo "👉 Live Link: http://localhost:8000/chat"
echo "===================================================="
echo "📝 Logs:"
echo "   - Reverb: Running in background"
echo "   - Queue:  Listening for 'RunChatSession' jobs"
echo "   - Vite:   Hot Reload active"
echo ""
echo "Press Ctrl+C to stop all services."

# Keep the script running to monitor processes
wait
