#!/bin/bash
# Chat Bridge Startup Script

echo "🌉 Starting Chat Bridge..."

# Check if virtual environment exists
if [ -d "venv" ]; then
    echo "📦 Activating virtual environment..."
    source venv/bin/activate
fi

# Check environment variables
if [ ! -f ".env" ]; then
    echo "❌ .env file not found! Please run setup first."
    exit 1
fi

# Start the server
echo "🚀 Starting Chat Bridge server..."
python main.py

echo "👋 Chat Bridge stopped."
