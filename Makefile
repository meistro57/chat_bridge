.PHONY: help build up down restart logs shell clean migrate fresh init sync

help: ## Show this help message
	@echo 'Usage: make [target]'
	@echo ''
	@echo 'Available targets:'
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "  \033[36m%-15s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

build: ## Build Docker images
	docker compose build

up: ## Start all services
	docker compose up -d
	@echo "✅ Chat Bridge is starting..."
	@echo "📱 Application: http://localhost:8000"
	@echo "🔌 WebSocket: http://localhost:8080"
	@echo "🧠 Qdrant: http://localhost:6333"

down: ## Stop all services
	docker compose down

restart: ## Restart all services
	docker compose restart

logs: ## Tail logs from all services
	docker compose logs -f

logs-app: ## Tail logs from app service
	docker compose logs -f app

logs-queue: ## Tail logs from queue worker
	docker compose logs -f queue

logs-reverb: ## Tail logs from Reverb WebSocket server
	docker compose logs -f reverb

shell: ## Open shell in app container
	docker compose exec app sh

shell-db: ## Open PostgreSQL shell
	docker compose exec postgres psql -U chatbridge -d chatbridge

clean: ## Remove all containers, volumes, and images
	docker compose down -v --remove-orphans
	docker compose rm -f

migrate: ## Run database migrations
	docker compose exec app php artisan migrate

fresh: ## Fresh database with migrations
	docker compose exec app php artisan migrate:fresh

init: ## Initialize Qdrant vector database
	docker compose exec app php artisan qdrant:init

sync: ## Sync existing messages to Qdrant
	docker compose exec app php artisan qdrant:init --sync

embeddings: ## Generate embeddings for messages
	docker compose exec app php artisan embeddings:generate

setup: ## First-time setup (build, up, and initialize)
	@make build
	@make up
	@echo "⏳ Waiting for services to be ready..."
	@sleep 10
	@echo "✅ Services are ready!"

status: ## Show service status
	docker compose ps

install: ## Install dependencies (dev)
	composer install
	npm install

dev: ## Run in development mode (local)
	composer dev

test: ## Run tests
	docker compose exec app php artisan test
