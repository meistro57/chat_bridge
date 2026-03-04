#!/bin/bash
set -e

echo "🚀 Chat Bridge Docker Setup"
echo "============================"
echo ""

# Check if .env exists
if [ ! -f .env ]; then
    echo "📝 Creating .env file from .env.example..."
    cp .env.example .env
else
    echo "✅ .env file already exists"
fi

# Generate APP_KEY if empty
if ! grep -q "^APP_KEY=base64:" .env; then
    echo "🔑 Generating APP_KEY..."
    APP_KEY=$(php -r "echo 'base64:' . base64_encode(random_bytes(32));")
    sed -i.bak "s|^APP_KEY=.*|APP_KEY=${APP_KEY}|g" .env
    rm -f .env.bak
    echo "✅ APP_KEY generated"
fi

# Generate REVERB_APP_KEY if empty
if ! grep -q "^REVERB_APP_KEY=[a-zA-Z0-9]" .env; then
    echo "🔑 Generating REVERB_APP_KEY..."
    REVERB_APP_KEY=$(openssl rand -hex 32)
    sed -i.bak "s|^REVERB_APP_KEY=.*|REVERB_APP_KEY=${REVERB_APP_KEY}|g" .env
    rm -f .env.bak
    echo "✅ REVERB_APP_KEY generated"
fi

# Generate REVERB_APP_SECRET if empty
if ! grep -q "^REVERB_APP_SECRET=[a-zA-Z0-9]" .env; then
    echo "🔑 Generating REVERB_APP_SECRET..."
    REVERB_APP_SECRET=$(openssl rand -hex 32)
    sed -i.bak "s|^REVERB_APP_SECRET=.*|REVERB_APP_SECRET=${REVERB_APP_SECRET}|g" .env
    rm -f .env.bak
    echo "✅ REVERB_APP_SECRET generated"
fi

# Update .env for Docker environment
echo ""
echo "🐳 Configuring for Docker environment..."

LOCAL_UID=$(id -u)
LOCAL_GID=$(id -g)
if grep -q "^LOCAL_UID=" .env; then
    sed -i.bak "s|^LOCAL_UID=.*|LOCAL_UID=${LOCAL_UID}|g" .env
else
    echo "LOCAL_UID=${LOCAL_UID}" >> .env
fi
if grep -q "^LOCAL_GID=" .env; then
    sed -i.bak "s|^LOCAL_GID=.*|LOCAL_GID=${LOCAL_GID}|g" .env
else
    echo "LOCAL_GID=${LOCAL_GID}" >> .env
fi

sed -i.bak "s|^DB_CONNECTION=sqlite|# DB_CONNECTION=sqlite|g" .env
sed -i.bak "s|^# DB_CONNECTION=pgsql|DB_CONNECTION=pgsql|g" .env
sed -i.bak "s|^# DB_HOST=postgres|DB_HOST=postgres|g" .env
sed -i.bak "s|^# DB_PORT=5432|DB_PORT=5432|g" .env
sed -i.bak "s|^# DB_DATABASE=chatbridge|DB_DATABASE=chatbridge|g" .env
sed -i.bak "s|^# DB_USERNAME=chatbridge|DB_USERNAME=chatbridge|g" .env
sed -i.bak "s|^# DB_PASSWORD=secret|DB_PASSWORD=secret|g" .env

sed -i.bak "s|^QUEUE_CONNECTION=database|QUEUE_CONNECTION=redis|g" .env
sed -i.bak "s|^CACHE_STORE=database|CACHE_STORE=redis|g" .env
sed -i.bak "s|^REDIS_HOST=127.0.0.1|REDIS_HOST=redis|g" .env

sed -i.bak "s|^REVERB_HOST=localhost|REVERB_HOST=reverb|g" .env
sed -i.bak "s|^# REVERB_SERVER_HOST=0.0.0.0|REVERB_SERVER_HOST=0.0.0.0|g" .env

sed -i.bak "s|^QDRANT_HOST=localhost|QDRANT_HOST=qdrant|g" .env
rm -f .env.bak

echo "✅ Docker environment configured"

echo ""
echo "📦 Building Docker images..."
docker compose build

echo ""
echo "🚀 Starting containers..."
docker compose up -d

echo ""
echo "⏳ Waiting for services to be ready..."
sleep 10

echo ""
echo "✅ Chat Bridge is ready!"
echo ""
echo "🌐 Access your application at:"
echo "   - Main app:       http://localhost:8000"
echo "   - WebSocket:      http://localhost:8080"
echo "   - Qdrant:         http://localhost:6333/dashboard"
echo ""
echo "📝 Default admin credentials:"
echo "   Email:    admin@chatbridge.local"
echo "   Password: password"
echo ""
echo "🔒 Remember to change the admin password after first login!"
