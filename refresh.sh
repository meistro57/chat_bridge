#!/bin/bash
set -e

# --- CONFIGURATION ---
REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CLEAN_MODE=false
QUICK_MODE=false
WIPE_VOLUMES=false
SKIP_FRONTEND_BUILD=false
SKIP_TESTS=false
SKIP_CODEX_CHECK=false
HOST_UID="$(id -u)"
HOST_GID="$(id -g)"

# Parse arguments
for arg in "$@"; do
    case $arg in
        --clean) CLEAN_MODE=true ;;
        --quick) QUICK_MODE=true ;;
        --clean-volumes|--wipe-volumes) WIPE_VOLUMES=true ;;
        --skip-build) SKIP_FRONTEND_BUILD=true ;;
        --skip-tests) SKIP_TESTS=true ;;
        --skip-codex-check) SKIP_CODEX_CHECK=true ;;
    esac
done

echo "🚀 Starting Chat Bridge refresh..."

sync_local_ids() {
    if [ ! -f ".env" ]; then
        return
    fi

    if grep -q "^LOCAL_UID=" .env; then
        sed -i "s|^LOCAL_UID=.*|LOCAL_UID=${HOST_UID}|g" .env
    else
        echo "LOCAL_UID=${HOST_UID}" >> .env
    fi

    if grep -q "^LOCAL_GID=" .env; then
        sed -i "s|^LOCAL_GID=.*|LOCAL_GID=${HOST_GID}|g" .env
    else
        echo "LOCAL_GID=${HOST_GID}" >> .env
    fi
}

echo "🆔 Syncing Docker UID/GID mapping (uid=${HOST_UID}, gid=${HOST_GID})..."
sync_local_ids
export LOCAL_UID="${HOST_UID}"
export LOCAL_GID="${HOST_GID}"

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
# Avoid pull conflicts when local changes exist; continue refresh regardless.
if [ -n "$(git status --porcelain)" ]; then
    echo "⚠️ Working tree has local changes; skipping git pull."
else
    # We allow this to fail so the script continues to restart the app no matter what.
    git pull --rebase || echo "⚠️ Git pull had issues, but we are pressing on!"
fi

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
docker compose exec -T app php artisan queue:restart

if [ "$SKIP_FRONTEND_BUILD" = "false" ]; then
    echo "🧱 Building frontend assets..."
    docker compose exec -T app sh -lc '
        if ! command -v npm >/dev/null 2>&1; then
            echo "⚠️ npm not found in app container; skipping frontend build."
            exit 0
        fi

        if [ -x node_modules/.bin/vite ]; then
            npm run build
            exit 0
        fi

        if [ -f public/build/manifest.json ]; then
            echo "⚠️ Vite CLI unavailable in runtime image; using prebuilt public/build assets."
            exit 0
        fi

        echo "❌ Vite CLI unavailable and no prebuilt assets found."
        exit 1
    '
else
    echo "⏭️  Skipping frontend build (--skip-build)."
fi

echo "🌱 Checking seed data..."
SEED_STATUS=$(docker compose exec -T app php -r "require 'vendor/autoload.php'; \$app=require 'bootstrap/app.php'; \$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); \$hasAdmin=\\App\\Models\\User::where('email', 'admin@chatbridge.local')->exists(); \$hasPersona=\\App\\Models\\Persona::query()->exists(); echo (\$hasAdmin && \$hasPersona) ? 'present' : 'missing';")
SEED_STATUS=$(echo "$SEED_STATUS" | tr -d '\r')

if [ "$SEED_STATUS" = "missing" ]; then
    echo "🌱 Seeding database..."
    docker compose exec -T app php artisan db:seed --force
else
    echo "✅ Seed data already present."
fi

echo "🔎 Running post-refresh health checks..."
docker compose ps

echo "⏳ Waiting for dependency health..."
for i in {1..30}; do
    POSTGRES_HEALTH=$(docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}}' chatbridge-postgres 2>/dev/null || echo "missing")
    REDIS_HEALTH=$(docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}}' chatbridge-redis 2>/dev/null || echo "missing")

    if [ "$POSTGRES_HEALTH" = "healthy" ] && [ "$REDIS_HEALTH" = "healthy" ]; then
        echo "✅ Dependencies are healthy."
        break
    fi

    if [ $i -eq 30 ]; then
        echo "❌ Dependencies did not become healthy in time."
        exit 1
    fi

    sleep 2
done

HEALTH_PORT="${APP_PORT:-8000}"
HEALTH_URL="http://localhost:${HEALTH_PORT}"
echo "🌐 Verifying HTTP endpoint..."
for i in {1..30}; do
    if curl -fsS "${HEALTH_URL}" > /dev/null; then
        break
    fi

    if [ $i -eq 30 ]; then
        echo "❌ Web endpoint failed health check after retries."
        exit 1
    fi

    sleep 2
done
echo "✅ Web endpoint is reachable."

echo "🧪 Verifying Laravel boots cleanly..."
docker compose exec -T app php artisan about > /dev/null
echo "✅ Laravel bootstrap check passed."

if [ "$SKIP_TESTS" = "false" ]; then
    echo "🧪 Running test suite..."
    docker compose exec -T app php artisan test --compact

    if [ -d "Modules" ]; then
        MODULE_TEST_DIRS=$(find Modules -maxdepth 2 -type d -name Tests | sort)
        if [ -n "$MODULE_TEST_DIRS" ]; then
            echo "🧩 Running module test suites..."
            while IFS= read -r module_tests; do
                if [ -d "$module_tests" ]; then
                    echo "→ $module_tests"
                    docker compose exec -T app php artisan test --compact "$module_tests"
                fi
            done <<< "$MODULE_TEST_DIRS"
        fi
    fi
else
    echo "⏭️  Skipping test suite (--skip-tests)."
fi

if [ "$SKIP_CODEX_CHECK" = "false" ]; then
    echo "🤖 Verifying built-in Codex integration..."

    if [ ! -f "boost.json" ]; then
        echo "❌ boost.json is missing."
        exit 1
    fi

    if ! grep -q '"codex"' "boost.json"; then
        echo "❌ boost.json does not register the codex agent."
        exit 1
    fi

    docker compose exec -T app sh -lc "test -x node_modules/.bin/codex"
    docker compose exec -T app sh -lc "node_modules/.bin/codex --version >/dev/null"
    docker compose exec -T app sh -lc "node_modules/.bin/codex --help >/dev/null"

    docker compose exec -T app php -r "require 'vendor/autoload.php'; \$app=require 'bootstrap/app.php'; \$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); \$home=(string)config('services.codex.home'); if(\$home===''){fwrite(STDERR,'missing codex.home'.PHP_EOL); exit(1);} if(!is_dir(\$home)){fwrite(STDERR,'codex.home not found: '.\$home.PHP_EOL); exit(1);} if((string)config('services.openai.key','')===''){fwrite(STDERR,'services.openai.key is missing'.PHP_EOL); exit(1);} echo 'codex-ok'.PHP_EOL;"

    echo "✅ Codex integration checks passed."
else
    echo "⏭️  Skipping Codex checks (--skip-codex-check)."
fi

echo "✅ Refresh Complete! Your app is running."
echo "   Web: ${HEALTH_URL}"
