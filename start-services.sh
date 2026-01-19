#!/bin/bash
set -e
cd /home/mark/chat_bridge

# Clear config
php artisan config:clear
php artisan route:clear

# Find available port
PORT=$(php find-port.php)
REVERB_PORT=$((PORT + 1))

if [ -z "$PORT" ]; then
    echo "Could not find available ports"
    exit 1
fi

echo "Found free ports: Web=$PORT, Reverb=$REVERB_PORT"

# Update .env
sed -i "s/^APP_PORT=.*/APP_PORT=$PORT/" .env
sed -i "s/^REVERB_PORT=.*/REVERB_PORT=$REVERB_PORT/" .env
sed -i "s/^REVERB_SERVER_PORT=.*/REVERB_SERVER_PORT=$REVERB_PORT/" .env
sed -i "s/^VITE_REVERB_PORT=.*/VITE_REVERB_PORT=$REVERB_PORT/" .env

# Rebuild frontend with new port config
# We need to set these env vars for the build process
export VITE_REVERB_PORT=$REVERB_PORT
export VITE_REVERB_HOST="localhost"
export VITE_REVERB_SCHEME="http"
npm run build

# Start services
php artisan serve --host=0.0.0.0 --port=$PORT > /dev/null 2>&1 &
APP_PID=$!

php artisan reverb:start --port=$REVERB_PORT > /dev/null 2>&1 &
REVERB_PID=$!

php artisan queue:work > /dev/null 2>&1 &
QUEUE_PID=$!

echo "Chat Bridge is running!"
echo "Web URL: http://localhost:$PORT"
echo "Reverb: localhost:$REVERB_PORT"

# Wait
wait $APP_PID $REVERB_PID $QUEUE_PID