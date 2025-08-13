.PHONY: help build up down restart logs shell test pint psalm phpstan install clean
.DEFAULT_GOAL := help

# Colors for output
GREEN := \033[32m
YELLOW := \033[33m
BLUE := \033[34m
RED := \033[31m
NC := \033[0m # No Color

# Docker compose files
DOCKER_COMPOSE_DEV := docker-compose.dev.yml
DOCKER_COMPOSE_CI := docker-compose.ci.yml
DOCKER_COMPOSE_PROD := docker-compose.yml

help: ## Show this help message
	@echo "$(BLUE)AI Blockchain Analytics - Development Commands$(NC)"
	@echo ""
	@awk 'BEGIN {FS = ":.*##"; printf "\nUsage:\n  make $(GREEN)<target>$(NC)\n\nTargets:\n"} /^[a-zA-Z_-]+:.*?##/ { printf "  $(GREEN)%-15s$(NC) %s\n", $$1, $$2 }' $(MAKEFILE_LIST)
	@echo ""

# Development Environment
build: ## Build development Docker containers
	@echo "$(YELLOW)Building development containers...$(NC)"
	docker-compose -f $(DOCKER_COMPOSE_DEV) build --no-cache

up: ## Start development environment
	@echo "$(GREEN)Starting development environment...$(NC)"
	docker-compose -f $(DOCKER_COMPOSE_DEV) up -d

down: ## Stop development environment
	@echo "$(YELLOW)Stopping development environment...$(NC)"
	docker-compose -f $(DOCKER_COMPOSE_DEV) down

restart: ## Restart development environment
	@echo "$(YELLOW)Restarting development environment...$(NC)"
	docker-compose -f $(DOCKER_COMPOSE_DEV) restart

logs: ## Show logs from all services
	docker-compose -f $(DOCKER_COMPOSE_DEV) logs -f

logs-app: ## Show logs from app service
	docker-compose -f $(DOCKER_COMPOSE_DEV) logs -f app

logs-vite: ## Show logs from Vite service
	docker-compose -f $(DOCKER_COMPOSE_DEV) logs -f vite

shell: ## Access application container shell
	docker-compose -f $(DOCKER_COMPOSE_DEV) exec app sh

shell-root: ## Access application container as root
	docker-compose -f $(DOCKER_COMPOSE_DEV) exec --user root app sh

# Installation and Setup
install: ## Install dependencies and setup environment
	@echo "$(GREEN)Installing dependencies...$(NC)"
	docker-compose -f $(DOCKER_COMPOSE_DEV) exec app composer install
	docker-compose -f $(DOCKER_COMPOSE_DEV) exec app php artisan key:generate
	docker-compose -f $(DOCKER_COMPOSE_DEV) exec app php artisan migrate
	docker-compose -f $(DOCKER_COMPOSE_DEV) exec vite npm install

fresh: ## Fresh installation with database seeding
	@echo "$(GREEN)Fresh installation...$(NC)"
	docker-compose -f $(DOCKER_COMPOSE_DEV) exec app php artisan migrate:fresh --seed

# Database
migrate: ## Run database migrations
	docker-compose -f $(DOCKER_COMPOSE_DEV) exec app php artisan migrate

migrate-fresh: ## Fresh migration (drops all tables)
	docker-compose -f $(DOCKER_COMPOSE_DEV) exec app php artisan migrate:fresh

seed: ## Run database seeders
	docker-compose -f $(DOCKER_COMPOSE_DEV) exec app php artisan db:seed

# Assets
assets: ## Build production assets
	docker-compose -f $(DOCKER_COMPOSE_DEV) exec vite npm run build

assets-dev: ## Build development assets
	docker-compose -f $(DOCKER_COMPOSE_DEV) exec vite npm run dev

assets-watch: ## Watch assets for changes
	docker-compose -f $(DOCKER_COMPOSE_DEV) exec vite npm run dev

# Testing
test: ## Run PHPUnit tests
	@echo "$(BLUE)Running PHPUnit tests...$(NC)"
	docker-compose -f $(DOCKER_COMPOSE_DEV) exec app ./vendor/bin/phpunit

test-coverage: ## Run tests with coverage
	@echo "$(BLUE)Running tests with coverage...$(NC)"
	docker-compose -f $(DOCKER_COMPOSE_DEV) exec app ./vendor/bin/phpunit --coverage-html coverage

test-ci: ## Run tests in CI environment
	@echo "$(BLUE)Running CI tests...$(NC)"
	docker-compose -f $(DOCKER_COMPOSE_CI) up -d
	docker-compose -f $(DOCKER_COMPOSE_CI) exec app php artisan migrate --force
	docker-compose -f $(DOCKER_COMPOSE_CI) exec app ./vendor/bin/phpunit
	docker-compose -f $(DOCKER_COMPOSE_CI) down

# Code Quality
pint: ## Run Laravel Pint (code style fixer)
	@echo "$(BLUE)Running Laravel Pint...$(NC)"
	docker-compose -f $(DOCKER_COMPOSE_DEV) exec app ./vendor/bin/pint

pint-test: ## Test code style with Pint (dry run)
	@echo "$(BLUE)Testing code style with Pint...$(NC)"
	docker-compose -f $(DOCKER_COMPOSE_DEV) exec app ./vendor/bin/pint --test

psalm: ## Run Psalm static analysis
	@echo "$(BLUE)Running Psalm...$(NC)"
	docker-compose -f $(DOCKER_COMPOSE_DEV) exec app ./vendor/bin/psalm

phpstan: ## Run PHPStan static analysis
	@echo "$(BLUE)Running PHPStan...$(NC)"
	docker-compose -f $(DOCKER_COMPOSE_DEV) exec app ./vendor/bin/phpstan analyze

quality: ## Run all code quality checks
	@echo "$(BLUE)Running all code quality checks...$(NC)"
	make pint-test
	make psalm
	make phpstan

# Laravel Commands
artisan: ## Run artisan command (e.g., make artisan cmd="route:list")
	docker-compose -f $(DOCKER_COMPOSE_DEV) exec app php artisan $(cmd)

tinker: ## Open Laravel Tinker REPL
	docker-compose -f $(DOCKER_COMPOSE_DEV) exec app php artisan tinker

queue: ## Run queue worker
	docker-compose -f $(DOCKER_COMPOSE_DEV) exec app php artisan queue:work

horizon: ## View Horizon dashboard (runs in background)
	@echo "$(GREEN)Horizon dashboard available at: http://localhost:8003/horizon$(NC)"

# Cleanup
clean: ## Clean up containers, volumes, and images
	@echo "$(RED)Cleaning up Docker resources...$(NC)"
	docker-compose -f $(DOCKER_COMPOSE_DEV) down -v --remove-orphans
	docker system prune -f
	docker volume prune -f

clean-all: ## Clean everything including images
	@echo "$(RED)Cleaning all Docker resources...$(NC)"
	docker-compose -f $(DOCKER_COMPOSE_DEV) down -v --remove-orphans
	docker system prune -a -f
	docker volume prune -f

# Production
prod-build: ## Build production Docker image
	@echo "$(YELLOW)Building production image...$(NC)"
	docker build --target production -t ai-blockchain-analytics:latest .

prod-up: ## Start production environment
	@echo "$(GREEN)Starting production environment...$(NC)"
	docker-compose -f $(DOCKER_COMPOSE_PROD) up -d

prod-down: ## Stop production environment
	@echo "$(YELLOW)Stopping production environment...$(NC)"
	docker-compose -f $(DOCKER_COMPOSE_PROD) down

# Monitoring
stats: ## Show Docker stats
	docker stats

ps: ## Show running containers
	docker-compose -f $(DOCKER_COMPOSE_DEV) ps