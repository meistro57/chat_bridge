# Docker Deployment Guide

This guide explains how to deploy Chat Bridge using Docker with integrated RAG (Retrieval-Augmented Generation) functionality for AI memory and context-aware conversations.

## Architecture

The Docker deployment consists of the following services:

- **app**: Main Laravel application (Nginx + PHP-FPM)
  - *Volume Mounted*: Local `./app` and `./database` are mounted for development convenience.
- **queue**: Background queue worker for processing conversations
- **reverb**: WebSocket server for real-time updates
- **postgres**: PostgreSQL database for persistent data (stored in `./storage/postgres`)
- **redis**: Redis for caching, sessions, and queue management
- **qdrant**: Vector database for RAG functionality and AI memory

## Prerequisites

- Docker (version 20.10+)
- Docker Compose (version 2.0+)
- At least 4GB of available RAM
- API keys for AI providers (OpenAI, Anthropic, etc.)

## Quick Start

### 1. Clone and Setup

```bash
git clone <repository-url>
cd chat_bridge
```

### 2. Configure Environment

```bash
# Copy the Docker environment file
cp .env.docker .env

# Generate application key
docker run --rm -v $(pwd):/app -w /app php:8.3-cli php artisan key:generate --show

# Edit .env and add your settings
nano .env
```

**Required Configuration:**

```env
APP_KEY=<generated-key>
APP_URL=http://localhost:8000

# Database (already configured for Docker)
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_DATABASE=chatbridge
DB_USERNAME=chatbridge
DB_PASSWORD=secret

# AI Provider Keys (at least one required)
OPENAI_API_KEY=sk-...
# ANTHROPIC_API_KEY=sk-ant-...
# DEEPSEEK_API_KEY=...

# RAG Configuration
QDRANT_ENABLED=true
QDRANT_HOST=qdrant
```

### 3. Build and Start

Using Make (recommended):

```bash
# First-time setup
make setup

# Or manually
make build
make up
```

Using Docker Compose directly:

```bash
# Build images
docker compose build

# Start services
docker compose up -d

# Wait for initialization (check logs)
docker compose logs -f app
```

### 4. Access the Application

- **Web Interface**: http://localhost:8000
- **WebSocket Server**: http://localhost:8080
- **Qdrant Dashboard**: http://localhost:6333/dashboard

## RAG Functionality

Chat Bridge includes built-in RAG (Retrieval-Augmented Generation) for AI memory and context-aware conversations.

### How It Works

1. **Message Storage**: Every conversation message is:
   - Saved to PostgreSQL
   - Vectorized using embeddings
   - Stored in Qdrant vector database

2. **Context Retrieval**: When generating responses, the AI:
   - Searches for similar past messages
   - Retrieves relevant context from previous conversations
   - Uses this context to provide more informed responses

3. **Memory Persistence**: The AI can:
   - Reference past conversations
   - Maintain context across sessions
   - Learn from previous interactions

### Initialize RAG

After first deployment, initialize the Qdrant vector database:

```bash
# Initialize Qdrant collection
make init

# Or using Docker Compose
docker compose exec app php artisan qdrant:init
```

### Sync Existing Messages

If you have existing conversations, sync them to Qdrant:

```bash
# Generate embeddings for existing messages
make embeddings

# Sync to Qdrant
make sync

# Or combined
docker compose exec app php artisan embeddings:generate
docker compose exec app php artisan qdrant:init --sync
```

### Disable RAG

To disable RAG functionality, set in `.env`:

```env
QDRANT_ENABLED=false
```

## Common Commands

### Using Make

```bash
make help          # Show all available commands
make up            # Start all services
make down          # Stop all services
make restart       # Restart all services
make logs          # View all logs
make logs-app      # View app logs only
make logs-queue    # View queue worker logs
make shell         # Open shell in app container
make migrate       # Run database migrations
make init          # Initialize Qdrant
make sync          # Sync messages to Qdrant
make clean         # Remove all containers (keeps volumes/data)
make clean-volumes # Remove all containers and volumes (destructive, wipes DB)
```

### Using Docker Compose

```bash
# View logs
docker compose logs -f [service]

# Execute commands in containers
docker compose exec app php artisan [command]
docker compose exec queue php artisan queue:work
docker compose exec postgres psql -U chatbridge

# Restart specific service
docker compose restart app

# Scale queue workers
docker compose up -d --scale queue=3
```

## Database Management

### Persistence & Seeding

- PostgreSQL data is persisted at `./storage/postgres` by default.
- The app container runs database seeding on boot to ensure required data (including the default admin user) is present.
- Use `make clean-volumes` only when you want a clean slate.

### Migrations

```bash
# Run migrations
make migrate

# Fresh database (WARNING: deletes all data)
make fresh

# Create new migration
docker compose exec app php artisan make:migration create_table_name
```

### Backups

```bash
# Backup PostgreSQL database
docker compose exec postgres pg_dump -U chatbridge chatbridge > backup.sql

# Restore from backup
docker compose exec -T postgres psql -U chatbridge chatbridge < backup.sql

# Backup Qdrant data
docker compose exec qdrant tar czf /qdrant/storage/backup.tar.gz /qdrant/storage
docker cp chatbridge-qdrant:/qdrant/storage/backup.tar.gz ./qdrant-backup.tar.gz
```

## Monitoring

### Service Status

```bash
# Check all services
make status

# View resource usage
docker stats
```

### Logs

```bash
# Follow all logs
make logs

# Specific service logs
docker compose logs -f app
docker compose logs -f queue
docker compose logs -f reverb

# Last 100 lines
docker compose logs --tail=100 app
```

## Frontend Assets & WebSocket Configuration

### First-Time Setup

Chat Bridge requires encryption keys to be set before building Docker images. Use the setup script:

```bash
./setup-docker.sh
```

This script automatically:
1. Generates `APP_KEY`, `REVERB_APP_KEY`, and `REVERB_APP_SECRET`
2. Configures `.env` for Docker environment
3. Builds Docker images with correct frontend assets
4. Starts all containers

### Manual Frontend Build

If you change `REVERB_APP_KEY` or other frontend variables in `.env`:

**Development (with public volume mounted):**
```bash
npm run build
docker compose restart app
```

**Production (without public volume):**
```bash
docker compose build --no-cache
docker compose up -d
```

### WebSocket Connection Issues

If you see "You must pass your app key when you instantiate Pusher":

1. Verify `REVERB_APP_KEY` is set in `.env`
2. Rebuild frontend assets:
   ```bash
   npm run build
   docker compose restart app
   ```

## Troubleshooting

### Services Won't Start

```bash
# Check service status
docker compose ps

# View detailed logs
docker compose logs

# Restart problematic service
docker compose restart [service]
```

### Docker Build Fails With "permission denied" on `storage/postgres`

The PostgreSQL data directory can end up owned by `root`, which causes Docker
to fail while sending the build context (for example: `open ./storage/postgres:
permission denied`).

This repository now excludes that directory from the build context via
`.dockerignore`. If you still see this error:

```bash
# Ensure the data directory is ignored by Docker builds
grep -n "storage/postgres" .dockerignore

# Rebuild after updating .dockerignore or data permissions
docker compose build --no-cache
```

If needed, fix ownership on the host machine before rebuilding.

### Composer Changes Not Reflected In Containers

The Docker services do not mount `composer.json` / `composer.lock`. After
changing PHP dependencies, you must rebuild the images:

```bash
docker compose build app queue reverb
docker compose up -d app queue reverb
```

### Database Connection Issues

```bash
# Verify PostgreSQL is running
docker compose exec postgres pg_isready

# Check database exists
docker compose exec postgres psql -U chatbridge -l

# Fix "Duplicate table" or "Relation already exists" errors
# This wipes the database and re-runs migrations fresh
docker compose exec app php artisan migrate:fresh
```

### Qdrant Not Working

```bash
# Check Qdrant health
curl http://localhost:6333/

# Reinitialize collection
docker compose exec app php artisan qdrant:init

# View Qdrant logs
docker compose logs qdrant
```

### Queue Not Processing

```bash
# Check queue worker logs
docker compose logs -f queue

# Restart queue worker
docker compose restart queue

# Process jobs manually
docker compose exec app php artisan queue:work --once
```

### Clear Caches

```bash
docker compose exec app php artisan cache:clear
docker compose exec app php artisan config:clear
docker compose exec app php artisan route:clear
docker compose exec app php artisan view:clear
```

## Production Deployment

### Security Checklist

- [ ] Set `APP_DEBUG=false`
- [ ] Set `APP_ENV=production`
- [ ] Use strong database passwords
- [ ] Enable HTTPS with reverse proxy (Nginx/Traefik)
- [ ] Configure firewall rules
- [ ] Set up regular backups
- [ ] Enable log rotation
- [ ] Use Docker secrets for sensitive data

### Performance Optimization

```bash
# Scale queue workers
docker compose up -d --scale queue=5

# Optimize caches
docker compose exec app php artisan optimize

# Use production Redis configuration
# Add to .env:
REDIS_CACHE_DB=0
REDIS_QUEUE_DB=1
REDIS_SESSION_DB=2
```

### Reverse Proxy Setup (Nginx)

```nginx
server {
    listen 80;
    server_name chatbridge.example.com;

    location / {
        proxy_pass http://localhost:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    location /reverb {
        proxy_pass http://localhost:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
    }
}
```

## Updating

```bash
# Pull latest code
git pull

# Rebuild images
make build

# Restart services
make down
make up

# Run migrations
make migrate
```

## Uninstalling

```bash
# Stop and remove everything
make clean

# Stop and remove everything (including volumes)
make clean-volumes

# Remove Docker images
docker rmi $(docker images -q chat_bridge*)
```

## Support

For issues and questions:
- GitHub Issues: <repository-url>/issues
- Documentation: <repository-url>/wiki
