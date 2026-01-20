#!/bin/sh
set -e

echo "🚀 Starting Chat Bridge Docker initialization..."

# Fix permissions on storage and cache to prevent permission issues on host
# This ensures that both the container and the host user can write to logs/cache
echo "🔧 Fixing permissions..."
chmod -R 777 /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true

# Clear cached framework files to avoid stale package discovery issues
rm -f /var/www/html/bootstrap/cache/*.php

# Ensure storage subdirectories exist with correct permissions
mkdir -p /var/www/html/storage/framework/cache/data \
         /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/logs \
         /var/www/html/storage/app/public
chmod -R 777 /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true

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

# Run database migrations (skip for reverb service)
if [ "${SKIP_MIGRATIONS:-false}" != "true" ]; then
    echo "📊 Running database migrations..."
    php artisan migrate --force
else
    echo "⏭️  Skipping database migrations..."
fi

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
