#!/bin/bash

# Chat Bridge Full Refresh Script
# Updates repository, rebuilds containers, and starts services
# Preserves .env file with API keys and configuration
#
# Usage:
#   ./refresh.sh          # Normal refresh (preserves database)
#   ./refresh.sh --clean  # Full clean (wipes database and volumes)
#   ./refresh.sh --quick  # Quick restart (no rebuild)

set -e

APP_NAME="Chat Bridge"
REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CLEAN_MODE=false
QUICK_MODE=false

# Parse arguments
for arg in "$@"; do
    case $arg in
        --clean)
            CLEAN_MODE=true
            ;;
        --quick)
            QUICK_MODE=true
            ;;
        --help|-h)
            echo "Usage: $0 [OPTIONS]"
            echo ""
            echo "Options:"
            echo "  --clean   Full clean rebuild (wipes database and volumes)"
            echo "  --quick   Quick restart without rebuilding containers"
            echo "  --help    Show this help message"
            exit 0
            ;;
    esac
done

echo "🚀 Starting Chat Bridge full refresh..."
echo "   Working directory: $REPO_DIR"
echo "   Clean mode: $CLEAN_MODE"
echo "   Quick mode: $QUICK_MODE"
echo ""

cd "$REPO_DIR"

# Function to backup env vars
backup_env_vars() {
    local env_file=$1
    if [ -f "$env_file" ]; then
        echo "📦 Backing up environment variables..."
        # Extract sensitive values that should be preserved
        OPENAI_KEY=$(grep "^OPENAI_API_KEY=" "$env_file" | cut -d'=' -f2- || echo "")
        ANTHROPIC_KEY=$(grep "^ANTHROPIC_API_KEY=" "$env_file" | cut -d'=' -f2- || echo "")
        DEEPSEEK_KEY=$(grep "^DEEPSEEK_API_KEY=" "$env_file" | cut -d'=' -f2- || echo "")
        GEMINI_KEY=$(grep "^GEMINI_API_KEY=" "$env_file" | cut -d'=' -f2- || echo "")
        OPENROUTER_KEY=$(grep "^OPENROUTER_API_KEY=" "$env_file" | cut -d'=' -f2- || echo "")
        MCP_KEY=$(grep "^MCP_API_KEY=" "$env_file" | cut -d'=' -f2- || echo "")
        APP_KEY=$(grep "^APP_KEY=" "$env_file" | cut -d'=' -f2- || echo "")
        OLLAMA_HOST=$(grep "^OLLAMA_HOST=" "$env_file" | cut -d'=' -f2- || echo "")
    fi
}

restore_env_vars() {
    local env_file=$1
    echo "🔐 Restoring environment variables..."
    [ -n "$OPENAI_KEY" ] && sed -i "s|^OPENAI_API_KEY=.*|OPENAI_API_KEY=$OPENAI_KEY|" "$env_file"
    [ -n "$ANTHROPIC_KEY" ] && sed -i "s|^ANTHROPIC_API_KEY=.*|ANTHROPIC_API_KEY=$ANTHROPIC_KEY|" "$env_file"
    [ -n "$DEEPSEEK_KEY" ] && sed -i "s|^DEEPSEEK_API_KEY=.*|DEEPSEEK_API_KEY=$DEEPSEEK_KEY|" "$env_file"
    [ -n "$GEMINI_KEY" ] && sed -i "s|^GEMINI_API_KEY=.*|GEMINI_API_KEY=$GEMINI_KEY|" "$env_file"
    [ -n "$OPENROUTER_KEY" ] && sed -i "s|^OPENROUTER_API_KEY=.*|OPENROUTER_API_KEY=$OPENROUTER_KEY|" "$env_file"
    [ -n "$MCP_KEY" ] && sed -i "s|^MCP_API_KEY=.*|MCP_API_KEY=$MCP_KEY|" "$env_file"
    [ -n "$APP_KEY" ] && sed -i "s|^APP_KEY=.*|APP_KEY=$APP_KEY|" "$env_file"
    [ -n "$OLLAMA_HOST" ] && sed -i "s|^OLLAMA_HOST=.*|OLLAMA_HOST=$OLLAMA_HOST|" "$env_file"
}

wait_for_postgres() {
    echo "⏳ Waiting for PostgreSQL..."
    local max_attempts=30
    local attempt=1
    while [ $attempt -le $max_attempts ]; do
        if docker compose exec -T postgres pg_isready -U chatbridge -q 2>/dev/null; then
            echo "✅ PostgreSQL is ready!"
            return 0
        fi
        echo "   Attempt $attempt/$max_attempts..."
        sleep 2
        ((attempt++))
    done
    echo "❌ PostgreSQL failed to start"
    return 1
}

wait_for_redis() {
    echo "⏳ Waiting for Redis..."
    local max_attempts=15
    local attempt=1
    while [ $attempt -le $max_attempts ]; do
        if docker compose exec -T redis redis-cli ping 2>/dev/null | grep -q PONG; then
            echo "✅ Redis is ready!"
            return 0
        fi
        echo "   Attempt $attempt/$max_attempts..."
        sleep 2
        ((attempt++))
    done
    echo "❌ Redis failed to start"
    return 1
}

wait_for_app() {
    echo "⏳ Waiting for application..."
    local max_attempts=30
    local attempt=1
    while [ $attempt -le $max_attempts ]; do
        if curl -s -f http://localhost:8000 > /dev/null 2>&1; then
            echo "✅ Application is ready!"
            return 0
        fi
        echo "   Attempt $attempt/$max_attempts..."
        sleep 3
        ((attempt++))
    done
    echo "❌ Application failed to start"
    return 1
}

# Backup .env values first
backup_env_vars ".env"

# Quick mode - just restart without rebuild
if [ "$QUICK_MODE" = true ]; then
    echo "🔄 Quick restart mode..."
    docker compose down
    docker compose up -d postgres redis qdrant
    wait_for_postgres
    wait_for_redis
    docker compose up -d
    wait_for_app
    echo ""
    echo "🎉 Quick restart completed!"
    exit 0
fi

# Stop containers
echo "🛑 Stopping existing containers..."
if [ "$CLEAN_MODE" = true ]; then
    docker compose down --volumes --remove-orphans 2>/dev/null || true
    echo "🧹 Cleaning up Docker resources..."
    docker system prune -f
    docker volume prune -f
else
    docker compose down --remove-orphans 2>/dev/null || true
fi

# Remove stubborn containers
docker rm -f chatbridge-app chatbridge-queue chatbridge-reverb chatbridge-postgres chatbridge-redis chatbridge-qdrant 2>/dev/null || true

# Update repository
echo "📥 Updating repository..."
git pull --rebase || {
    echo "⚠️ Git pull failed, continuing with local code..."
}

# Set environment for local development
echo "📝 Setting up environment..."
if [ -f ".env" ]; then
    # Update environment variables for local dev
    sed -i 's|^APP_ENV=.*|APP_ENV=local|' .env
    sed -i 's|^APP_DEBUG=.*|APP_DEBUG=true|' .env
    sed -i 's|^APP_PORT=.*|APP_PORT=8000|' .env
    sed -i 's|^REVERB_PORT=.*|REVERB_PORT=8080|' .env
    sed -i 's|^REVERB_SERVER_PORT=.*|REVERB_SERVER_PORT=8080|' .env
    sed -i 's|^VITE_REVERB_PORT=.*|VITE_REVERB_PORT=8080|' .env
    sed -i 's|^VITE_REVERB_HOST=.*|VITE_REVERB_HOST=localhost|' .env
    sed -i 's|^VITE_REVERB_SCHEME=.*|VITE_REVERB_SCHEME=http|' .env
else
    # Copy from example if .env doesn't exist
    cp .env.example .env
    # Generate app key (fixed syntax)
    sed -i "s|^APP_KEY=.*|APP_KEY=base64:$(openssl rand -base64 32)|" .env
fi

# Restore backed up environment variables
restore_env_vars ".env"

# Rebuild containers
echo "🔨 Building containers..."
docker compose build --no-cache --parallel

# Start database services first
echo "🏃 Starting database and cache services..."
docker compose up -d postgres redis qdrant

# Wait for services with proper health checks
wait_for_postgres
wait_for_redis

# Run database migrations and seeders
echo "🗃️ Running database setup..."
docker compose run --rm app php artisan migrate --force

if [ "$CLEAN_MODE" = true ]; then
    echo "🌱 Seeding database..."
    docker compose run --rm app php artisan db:seed --force || {
        echo "⚠️ Seeder had issues, continuing..."
    }
fi

# Start the main application
echo "🚀 Starting application services..."
docker compose up -d

# Wait for app to be ready
wait_for_app || {
    echo ""
    echo "❌ App failed to start. Showing logs:"
    docker compose logs app --tail 50
    exit 1
}

# Show service status
echo ""
echo "📊 Service Status:"
docker compose ps

echo ""
echo "🎉 Chat Bridge refresh completed successfully!"
echo ""
echo "📖 Service URLs:"
echo "   Web App:    http://localhost:8000"
echo "   WebSocket:  ws://localhost:8080"
echo "   Qdrant:     http://localhost:6333/dashboard"
echo ""
echo "🛠️ Useful commands:"
echo "   View logs:      docker compose logs -f"
echo "   Stop services:  docker compose down"
echo "   Restart app:    docker compose restart app"
echo "   Run tests:      docker compose run --rm app php artisan test"
echo "   Shell access:   docker compose exec app sh"
echo ""
echo "🔐 API keys preserved from previous .env file"
