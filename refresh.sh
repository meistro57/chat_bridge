#!/bin/bash

# Chat Bridge Full Refresh Script
# Updates repository, rebuilds containers, and starts services
# Preserves .env file with API keys and configuration

set -e

APP_NAME="Chat Bridge"
REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo "🚀 Starting Chat Bridge full refresh..."
echo "Working directory: $REPO_DIR"

cd "$REPO_DIR"

# Function to backup and restore env vars
backup_env_vars() {
    local env_file=$1
    if [ -f "$env_file" ]; then
        # Extract sensitive values that should be preserved
        OPENAI_KEY=$(grep "^OPENAI_API_KEY=" "$env_file" | cut -d'=' -f2- || echo "")
        ANTHROPIC_KEY=$(grep "^ANTHROPIC_API_KEY=" "$env_file" | cut -d'=' -f2- || echo "")
        DEEPSEEK_KEY=$(grep "^DEEPSEEK_API_KEY=" "$env_file" | cut -d'=' -f2- || echo "")
        GEMINI_KEY=$(grep "^GEMINI_API_KEY=" "$env_file" | cut -d'=' -f2- || echo "")
        OPENROUTER_KEY=$(grep "^OPENROUTER_API_KEY=" "$env_file" | cut -d'=' -f2- || echo "")
        MCP_KEY=$(grep "^MCP_API_KEY=" "$env_file" | cut -d'=' -f2- || echo "")
        APP_KEY=$(grep "^APP_KEY=" "$env_file" | cut -d'=' -f2- || echo "")
    fi
}

restore_env_vars() {
    local env_file=$1
    if [ -n "$OPENAI_KEY" ]; then
        sed -i "s|^OPENAI_API_KEY=.*|OPENAI_API_KEY=$OPENAI_KEY|" "$env_file" || true
    fi
    if [ -n "$ANTHROPIC_KEY" ]; then
        sed -i "s|^ANTHROPIC_API_KEY=.*|ANTHROPIC_API_KEY=$ANTHROPIC_KEY|" "$env_file" || true
    fi
    if [ -n "$DEEPSEEK_KEY" ]; then
        sed -i "s|^DEEPSEEK_API_KEY=.*|DEEPSEEK_API_KEY=$DEEPSEEK_KEY|" "$env_file" || true
    fi
    if [ -n "$GEMINI_KEY" ]; then
        sed -i "s|^GEMINI_API_KEY=.*|GEMINI_API_KEY=$GEMINI_KEY|" "$env_file" || true
    fi
    if [ -n "$OPENROUTER_KEY" ]; then
        sed -i "s|^OPENROUTER_API_KEY=.*|OPENROUTER_API_KEY=$OPENROUTER_KEY|" "$env_file" || true
    fi
    if [ -n "$MCP_KEY" ]; then
        sed -i "s|^MCP_API_KEY=.*|MCP_API_KEY=$MCP_KEY|" "$env_file" || true
    fi
    if [ -n "$APP_KEY" ]; then
        sed -i "s|^APP_KEY=.*|APP_KEY=$APP_KEY|" "$env_file" || true
    fi
}

# Stop any running containers
echo "🛑 Stopping existing containers..."
docker compose down --volumes 2>/dev/null || true

# Backup .env values
backup_env_vars ".env"

# Update repository
echo "📥 Updating repository..."
git pull --rebase

# Clean up existing containers and images
echo "🧹 Cleaning up Docker resources..."
docker compose down --volumes --remove-orphans 2>/dev/null || true
docker system prune -f

# Remove containers in case of stubborn ones
docker rm -f chatbridge-app chatbridge-queue chatbridge-reverb chatbridge-postgres chatbridge-redis chatbridge-qdrant 2>/dev/null || true

# Remove old image
docker rmi chatbridge-app 2>/dev/null || true

# Remove dangling volumes
docker volume prune -f

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
    # Generate app key
    sed -i 's|^APP_KEY=.*|APP_KEY=base64:'$(openssl rand -base64 32)\'| .env
fi

# Restore backed up environment variables
restore_env_vars ".env"

# Rebuild containers
echo "🔨 Building containers..."
docker compose build --no-cache --parallel

# Start database services first
echo "🏃 Starting database and cache services..."
docker compose up -d postgres redis qdrant

# Wait for services to be ready
echo "⏳ Waiting for database to be ready..."
sleep 10

# Run database migrations and seeders
echo "🗃️ Running database setup..."
docker compose run --rm app php artisan migrate --force
docker compose run --rm app php artisan db:seed --force

# Start the main application
echo "🚀 Starting application services..."
docker compose up -d

# Wait a bit for services to fully start
echo "⏳ Waiting for services to initialize..."
sleep 15

# Check if services are running
echo "✅ Checking service status..."
docker compose ps

# Set frontend environment and build
echo "📦 Building frontend assets..."
docker compose exec app npm install
docker compose exec app npm run build

# Health checks
echo "🔍 Performing health checks..."

# Check if app is responding
APP_READY=false
for i in {1..30}; do
    if curl -s -f http://localhost:8000 > /dev/null 2>&1; then
        APP_READY=true
        break
    fi
    echo "Waiting for app to be ready... ($i/30)"
    sleep 5
done

if [ "$APP_READY" = false ]; then
    echo "❌ App failed to start properly"
    docker compose logs app
    exit 1
fi

echo ""
echo "🎉 Chat Bridge refresh completed successfully!"
echo ""
echo "📖 Service URLs:"
echo "   Web App: http://localhost:8000"
echo "   WebSocket: ws://localhost:8080"
echo ""
echo "🛠️ Useful commands:"
echo "   View logs: docker compose logs -f"
echo "   Stop services: docker compose down"
echo "   Restart app: docker compose restart app"
echo "   Run tests: docker compose exec app php artisan test"
echo ""
echo "📝 API keys preserved from previous .env file"
echo "   Edit .env file to update them if needed"