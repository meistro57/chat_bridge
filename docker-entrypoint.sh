#!/bin/sh
set -e

echo "🚀 Starting Chat Bridge Docker initialization..."

# Wait for PostgreSQL to be ready
if [ "${DB_CONNECTION}" = "pgsql" ]; then
    echo "⏳ Waiting for PostgreSQL..."
    until pg_isready -h "${DB_HOST}" -U "${DB_USERNAME}"; do
        echo "PostgreSQL is unavailable - sleeping"
        sleep 2
    done
    echo "✅ PostgreSQL is ready!"
fi

# Wait for Redis to be ready
if [ "${QUEUE_CONNECTION}" = "redis" ] || [ "${CACHE_STORE}" = "redis" ]; then
    echo "⏳ Waiting for Redis..."
    until redis-cli -h "${REDIS_HOST}" ping > /dev/null 2>&1; do
        echo "Redis is unavailable - sleeping"
        sleep 2
    done
    echo "✅ Redis is ready!"
fi

# Wait for Qdrant to be ready
if [ "${QDRANT_ENABLED}" = "true" ]; then
    echo "⏳ Waiting for Qdrant..."
    until wget --spider -q "http://${QDRANT_HOST}:${QDRANT_PORT}/" > /dev/null 2>&1; do
        echo "Qdrant is unavailable - sleeping"
        sleep 2
    done
    echo "✅ Qdrant is ready!"
fi

# Run database migrations
echo "📊 Running database migrations..."
php artisan migrate --force

# Clear and cache configuration
echo "⚙️  Optimizing application..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Initialize Qdrant if enabled
if [ "${QDRANT_ENABLED}" = "true" ]; then
    echo "🧠 Initializing Qdrant vector database..."
    php artisan qdrant:init
fi

echo "✨ Chat Bridge is ready!"

# Execute the main command
exec "$@"
