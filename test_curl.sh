#!/bin/bash

# Simple test script to verify Chat Bridge is working

echo "Testing Chat Bridge health..."

# Test main application
echo "Testing web app..."
if curl -s -I http://localhost:8000 | head -1 | grep -q "200\|302"; then
    echo "✅ Web app responding"
else
    echo "❌ Web app not responding"
fi

# Test API health endpoint (if exists)
echo "Testing API..."
if curl -s -I http://localhost:8000/api/health | head -1 | grep -q "200"; then
    echo "✅ API responding"
else
    echo "⚠️ API health endpoint not available (might be normal)"
fi

# Test WebSocket port
echo "Testing WebSocket port..."
if nc -z localhost 8080 2>/dev/null; then
    echo "✅ WebSocket port open"
else
    echo "❌ WebSocket port not accessible"
fi

echo ""
echo "Test complete. Check docker-compose logs if issues persist."