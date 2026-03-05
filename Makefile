.PHONY: help setup up down restart logs sh test lint analyse fresh horizon migrate seed cache-clear

# ══════════════════════════════════════════════
# API MaisVendas - Makefile
# ══════════════════════════════════════════════

help: ## Show available commands
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'

# ── Docker ──────────────────────────────────

setup: ## First-time project setup (Docker)
	cp -n .env.example .env || true
	docker compose build
	docker compose up -d
	docker compose exec app composer install
	docker compose exec app php artisan key:generate
	docker compose exec app php artisan migrate
	@echo "\n✅ Project ready!"
	@echo "   API:      http://api.maisvendas.localhost"
	@echo "   Traefik:  http://localhost:8080"
	@echo "   Horizon:  http://api.maisvendas.localhost/horizon"
	@echo "   RabbitMQ: http://rabbitmq.maisvendas.localhost"

up: ## Start all containers
	docker compose up -d

down: ## Stop all containers
	docker compose down

restart: ## Restart all containers
	docker compose restart

rebuild: ## Rebuild all containers (no cache)
	docker compose build --no-cache
	docker compose up -d

logs: ## Tail all container logs
	docker compose logs -f --tail=100

logs-app: ## Tail app container logs
	docker compose logs -f --tail=100 app

logs-horizon: ## Tail Horizon logs
	docker compose logs -f --tail=100 horizon

logs-worker: ## Tail RabbitMQ worker logs
	docker compose logs -f --tail=100 rabbitmq-worker

sh: ## Open shell in app container
	docker compose exec app bash

# ── Development ─────────────────────────────

test: ## Run tests
	docker compose exec app php artisan test

test-coverage: ## Run tests with coverage
	docker compose exec app php artisan test --coverage --min=80

lint: ## Fix code style
	docker compose exec app vendor/bin/pint

lint-check: ## Check code style (no fix)
	docker compose exec app vendor/bin/pint --test

analyse: ## Run static analysis
	docker compose exec app vendor/bin/phpstan analyse

check: lint-check analyse test ## Run all checks (lint + analyse + test)

# ── Database ────────────────────────────────

migrate: ## Run migrations
	docker compose exec app php artisan migrate

migrate-fresh: ## Drop all tables and re-run migrations
	docker compose exec app php artisan migrate:fresh

seed: ## Run database seeders
	docker compose exec app php artisan db:seed

fresh: ## Fresh migration + seed
	docker compose exec app php artisan migrate:fresh --seed

# ── Cache & Queue ───────────────────────────

cache-clear: ## Clear all caches
	docker compose exec app php artisan config:clear
	docker compose exec app php artisan cache:clear
	docker compose exec app php artisan route:clear
	docker compose exec app php artisan view:clear
	docker compose exec app php artisan event:clear

cache-warmup: ## Warm up all caches
	docker compose exec app php artisan config:cache
	docker compose exec app php artisan route:cache
	docker compose exec app php artisan view:cache
	docker compose exec app php artisan event:cache

horizon: ## Restart Horizon
	docker compose restart horizon

# ── Scaling ─────────────────────────────────

scale-workers: ## Scale RabbitMQ workers (usage: make scale-workers N=5)
	docker compose up -d --scale rabbitmq-worker=$(N) --no-recreate

# ── Local (without Docker) ──────────────────

local-setup: ## Setup for local development (without Docker)
	cp -n .env.example .env || true
	composer install
	php artisan key:generate
	php artisan migrate
	@echo "\n✅ Run 'php artisan serve' to start"

local-test: ## Run tests locally
	php artisan test

local-lint: ## Run Pint locally
	vendor/bin/pint

local-analyse: ## Run PHPStan locally
	vendor/bin/phpstan analyse

# ── Production ──────────────────────────────

prod-build: ## Build production Docker image
	docker build -f docker/app/Dockerfile --target production -t maisvendas-api:latest .

prod-deploy: cache-warmup ## Deploy production optimizations
	docker compose exec app php artisan config:cache
	docker compose exec app php artisan route:cache
	docker compose exec app php artisan view:cache
	docker compose exec app php artisan event:cache
	docker compose exec app php artisan horizon:terminate
