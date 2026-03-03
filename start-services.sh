#!/bin/bash
set -e
cd /home/mark/chat_bridge

# Clear config
php artisan config:clear
php artisan route:clear

# Find available port
PORT=$(php find-port.php)
REVERB_PORT=$((PORT + 1))
APP_HOST="0.0.0.0"

if [ -z "$PORT" ]; then
    echo "Could not find available ports"
    exit 1
fi

echo "Found free ports: Web=$PORT, Reverb=$REVERB_PORT"

# Update or append a .env key
set_env_var() {
    local key="$1"
    local value="$2"

    if grep -q "^${key}=" .env; then
        sed -i "s#^${key}=.*#${key}=${value}#" .env
    else
        echo "${key}=${value}" >> .env
    fi
}

# Update .env
set_env_var "APP_PORT" "$PORT"
set_env_var "APP_URL" "http://$APP_HOST:$PORT"
set_env_var "REVERB_HOST" "$APP_HOST"
set_env_var "REVERB_PORT" "$REVERB_PORT"
set_env_var "REVERB_SERVER_HOST" "$APP_HOST"
set_env_var "REVERB_SERVER_PORT" "$REVERB_PORT"
set_env_var "VITE_REVERB_HOST" "$APP_HOST"
set_env_var "VITE_REVERB_PORT" "$REVERB_PORT"

# Ensure local SQLite path is valid if .env still has placeholder value
if grep -q "^DB_CONNECTION=sqlite" .env; then
    if ! grep -q "^DB_DATABASE=" .env || grep -q "^DB_DATABASE=/absolute/path/to/chat_bridge/database/database.sqlite" .env; then
        set_env_var "DB_DATABASE" "database/database.sqlite"
    fi
fi

# Rebuild frontend with new port config
# We need to set these env vars for the build process
export VITE_REVERB_PORT=$REVERB_PORT
export VITE_REVERB_HOST=$APP_HOST
export VITE_REVERB_SCHEME="http"
npm run build

# Start services
php artisan serve --host=$APP_HOST --port=$PORT > /dev/null 2>&1 &
APP_PID=$!

php artisan reverb:start --host=$APP_HOST --port=$REVERB_PORT > /dev/null 2>&1 &
REVERB_PID=$!

php artisan queue:work > /dev/null 2>&1 &
QUEUE_PID=$!

echo "Chat Bridge is running!"
echo "Web URL: http://$APP_HOST:$PORT"
echo "Reverb: $APP_HOST:$REVERB_PORT"

# Wait
wait $APP_PID $REVERB_PID $QUEUE_PID
