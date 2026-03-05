.PHONY: help setup up down restart logs sh test lint analyse fresh horizon migrate seed cache-clear

help: ## Show available commands
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'

# ---- Docker ----

setup: ## First-time project setup
	cp -n .env.example .env || true
	docker compose build
	docker compose up -d
	docker compose exec app composer install
	docker compose exec app php artisan key:generate
	docker compose exec app php artisan migrate
	docker compose exec app php artisan horizon:install
	@echo "\n✅ Project ready at http://localhost:8000"

up: ## Start all containers
	docker compose up -d

down: ## Stop all containers
	docker compose down

restart: ## Restart all containers
	docker compose restart

logs: ## Tail container logs
	docker compose logs -f --tail=100

sh: ## Open shell in app container
	docker compose exec app bash

# ---- Development ----

test: ## Run tests
	docker compose exec app php artisan test

test-coverage: ## Run tests with coverage
	docker compose exec app php artisan test --coverage --min=80

lint: ## Run code style fixer
	docker compose exec app vendor/bin/pint

lint-check: ## Check code style without fixing
	docker compose exec app vendor/bin/pint --test

analyse: ## Run static analysis
	docker compose exec app vendor/bin/phpstan analyse

# ---- Database ----

migrate: ## Run migrations
	docker compose exec app php artisan migrate

migrate-fresh: ## Drop all tables and re-run migrations
	docker compose exec app php artisan migrate:fresh

seed: ## Run database seeders
	docker compose exec app php artisan db:seed

fresh: ## Fresh migration + seed
	docker compose exec app php artisan migrate:fresh --seed

# ---- Cache & Queue ----

cache-clear: ## Clear all caches
	docker compose exec app php artisan config:clear
	docker compose exec app php artisan cache:clear
	docker compose exec app php artisan route:clear
	docker compose exec app php artisan view:clear

cache-warmup: ## Warm up all caches
	docker compose exec app php artisan config:cache
	docker compose exec app php artisan route:cache
	docker compose exec app php artisan view:cache

horizon: ## Start Horizon queue worker
	docker compose exec app php artisan horizon

# ---- Local (without Docker) ----

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
