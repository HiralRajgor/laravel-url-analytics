.PHONY: help up down build test test-unit test-feature lint fix migrate seed fresh shell worker logs docs

## ── Help ─────────────────────────────────────────────────────────────────────
help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-18s\033[0m %s\n", $$1, $$2}'

## ── Docker ───────────────────────────────────────────────────────────────────
up: ## Start all containers
	docker compose up -d

down: ## Stop all containers
	docker compose down

build: ## Rebuild containers from scratch
	docker compose build --no-cache

logs: ## Tail app + worker logs
	docker compose logs -f app worker

## ── Application ──────────────────────────────────────────────────────────────
install: ## Install PHP dependencies
	composer install

key: ## Generate application key
	php artisan key:generate

migrate: ## Run database migrations
	php artisan migrate

seed: ## Seed the database with demo data
	php artisan db:seed

fresh: ## Drop all tables and re-migrate + seed
	php artisan migrate:fresh --seed

worker: ## Start the queue worker (analytics queue)
	php artisan queue:work redis --queue=analytics,default --tries=3 --sleep=3

schedule: ## Run the scheduler loop locally
	php artisan schedule:work

docs: ## Generate Swagger API documentation
	php artisan l5-swagger:generate
	@echo "\nDocs available at: http://localhost:8000/api/documentation"

## ── Testing ──────────────────────────────────────────────────────────────────
test: ## Run the full test suite
	php artisan test --parallel

test-unit: ## Run unit tests only
	php artisan test --testsuite=Unit

test-feature: ## Run feature tests only
	php artisan test --testsuite=Feature

test-coverage: ## Run tests with HTML coverage report
	php artisan test --coverage --min=80

## ── Code Quality ─────────────────────────────────────────────────────────────
lint: ## Check code style with Pint (dry run)
	./vendor/bin/pint --test

fix: ## Auto-fix code style with Pint
	./vendor/bin/pint

## ── Artisan shortcuts ────────────────────────────────────────────────────────
sync: ## Manually flush Redis click counters to DB
	php artisan urls:sync-click-counts

sync-dry: ## Preview Redis → DB sync without writing
	php artisan urls:sync-click-counts --dry-run

purge: ## Purge URLs expired 90+ days ago
	php artisan urls:purge-expired --days=90

tinker: ## Open Laravel Tinker REPL
	php artisan tinker

shell: ## Open a shell inside the Docker app container
	docker compose exec app sh
