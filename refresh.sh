#!/bin/bash
set -e

# --- CONFIGURATION ---
REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CLEAN_MODE=false
QUICK_MODE=false
WIPE_VOLUMES=false

# Parse arguments
for arg in "$@"; do
    case $arg in
        --clean) CLEAN_MODE=true ;;
        --quick) QUICK_MODE=true ;;
        --clean-volumes|--wipe-volumes) WIPE_VOLUMES=true ;;
    esac
done

echo "🚀 Starting Chat Bridge refresh..."

# Ensure SQLite config is container-safe when running via Docker
if [ -f ".env" ]; then
    DB_CONNECTION_VALUE=$(grep -E '^DB_CONNECTION=' .env | cut -d '=' -f2- | tr -d '\r')
    DB_DATABASE_VALUE=$(grep -E '^DB_DATABASE=' .env | cut -d '=' -f2- | tr -d '\r')

    if [ "$DB_CONNECTION_VALUE" = "sqlite" ]; then
        if [[ "$DB_DATABASE_VALUE" == /home/* ]]; then
            echo "🔧 Rewriting SQLite DB path for Docker..."
            sed -i 's|^DB_DATABASE=.*|DB_DATABASE=/var/www/html/database/database.sqlite|' .env
        fi

        echo "🧱 Ensuring SQLite database file exists..."
        mkdir -p database
        touch database/database.sqlite
    fi
fi

# 1. FIX KNOWN CONFLICTS (The "Magic Fix")
# We remove the untracked debugbar ignore file if it exists, so git pull doesn't choke.
if [ -f "storage/debugbar/.gitignore" ]; then
    echo "🔧 Removing conflicting debugbar file..."
    rm "storage/debugbar/.gitignore"
fi

# 2. UPDATE REPOSITORY
echo "📥 Updating repository..."
# We allow this to fail (|| true) so the script continues to restart the app no matter what
git pull --rebase || echo "⚠️ Git pull had issues, but we are pressing on!"

# 3. BACKUP & PREP
# (Inline backup logic to keep it simple)
if [ -f ".env" ]; then
    echo "📦 Backing up environment keys..."
    # Capture keys safely
    source .env 2>/dev/null || true
fi

# 4. STOP & REBUILD
if [ "$QUICK_MODE" = "false" ]; then
    echo "🛑 Stopping containers..."
    if [ "$WIPE_VOLUMES" = "true" ]; then
        echo "⚠️  Removing volumes (destructive)..."
        docker compose down -v --remove-orphans
    else
        docker compose down --remove-orphans
    fi

    echo "🔨 Rebuilding..."
    docker compose build
fi

# 5. START SERVICES
echo "🏃 Starting services..."
docker compose up -d postgres redis qdrant
sleep 5 # Give DBs a moment to wake up

docker compose up -d app queue reverb

# 6. POST-STARTUP CLEANUP
echo "✨ Clearing application cache..."
# We run this INSIDE the container to avoid permission issues
docker compose exec -T app php artisan optimize:clear
docker compose exec -T app php artisan migrate --force

echo "🌱 Checking seed data..."
SEED_STATUS=$(docker compose exec -T app php -r "require 'vendor/autoload.php'; \$app=require 'bootstrap/app.php'; \$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); \$hasAdmin=\\App\\Models\\User::where('email', 'admin@chatbridge.local')->exists(); \$hasPersona=\\App\\Models\\Persona::query()->exists(); echo (\$hasAdmin && \$hasPersona) ? 'present' : 'missing';")
SEED_STATUS=$(echo "$SEED_STATUS" | tr -d '\r')

if [ "$SEED_STATUS" = "missing" ]; then
    echo "🌱 Seeding database..."
    docker compose exec -T app php artisan db:seed --force
else
    echo "✅ Seed data already present."
fi

echo "✅ Refresh Complete! Your app is running."
echo "   Web: http://localhost:8000"
