SHELL := /bin/sh

.DEFAULT_GOAL := help
.DELETE_ON_ERROR:
MAKEFLAGS += --no-builtin-rules

PHP ?= php
COMPOSER ?= composer
DOCKER ?= docker
HTTP_PORT ?= 8000
POSTGRES_USER ?= osira
POSTGRES_PASSWORD ?= !ChangeMe!
POSTGRES_TEST_DB ?= osira_test

COMPOSE := $(DOCKER) compose

ACTIONLINT_IMAGE := rhysd/actionlint@sha256:b1934ee5f1c509618f2508e6eb47ee0d3520686341fec936f3b79331f9315667

.PHONY: help dev dev-setup dev-stop dev-logs install update validate qa ci lint cs-check cs-fix analyse test coverage security grumphp workflow-lint hooks database-up database-down database-test-up vendor-sync test-postgres migrate fixtures dev-reset schema-validate jwt-keys openapi cache-clear cache-warmup console

help: ## Show the available targets.
	@awk 'BEGIN {FS = ":.*## "; printf "Usage: make <target> [ARGS=\"...\"]\n\nTargets:\n"} /^[a-zA-Z0-9_-]+:.*## / {printf "  %-16s %s\n", $$1, $$2}' $(MAKEFILE_LIST)

dev-setup: ## Build containers, install dependencies, generate keys, and migrate development.
	HTTP_PORT=$(HTTP_PORT) $(COMPOSE) build app
	HTTP_PORT=$(HTTP_PORT) $(COMPOSE) up --detach --wait database
	HTTP_PORT=$(HTTP_PORT) $(COMPOSE) run --rm --no-deps app composer install --prefer-dist --no-interaction --no-progress
	HTTP_PORT=$(HTTP_PORT) $(COMPOSE) run --rm --no-deps app php bin/console lexik:jwt:generate-keypair --skip-if-exists
	HTTP_PORT=$(HTTP_PORT) $(COMPOSE) run --rm --no-deps app chmod 0600 config/jwt/private.pem
	HTTP_PORT=$(HTTP_PORT) $(COMPOSE) run --rm --no-deps app chmod 0644 config/jwt/public.pem
	HTTP_PORT=$(HTTP_PORT) $(COMPOSE) run --rm app php bin/console doctrine:migrations:migrate --no-interaction

dev: dev-setup ## Prepare and start the complete development stack with FrankenPHP.
	HTTP_PORT=$(HTTP_PORT) $(COMPOSE) up --detach --wait app
	@echo "Osira API is available at http://localhost:$(HTTP_PORT)"

down: ## Stop the development stack and remove containers.
	$(COMPOSE) down

up: ## Start the development stack without rebuilding containers.
	HTTP_PORT=$(HTTP_PORT) $(COMPOSE) up --detach --wait app
	@echo "Osira API is available at http://localhost:$(HTTP_PORT)"

dev-stop: ## Stop the development stack without deleting data.
	$(COMPOSE) stop

dev-logs: ## Follow development application logs.
	$(COMPOSE) logs --follow app

install: ## Install locked dependencies and initialize Git hooks.
	$(COMPOSER) install --prefer-dist --no-interaction --no-progress

update: ## Update dependencies within declared constraints.
	$(COMPOSER) update --prefer-dist --no-interaction --no-progress

validate: ## Validate and normalize Composer metadata.
	$(COMPOSER) validate --strict --no-check-publish
	$(COMPOSER) normalize --dry-run

qa: ## Run the complete application quality gate.
	$(COMPOSER) qa

ci: validate qa grumphp workflow-lint ## Reproduce the complete CI gate locally (requires Docker).

lint: ## Lint PHP, YAML, and the Symfony container.
	$(COMPOSER) lint

cs-check: ## Check PHP coding standards without changing files.
	$(COMPOSER) cs:check

cs-fix: ## Automatically fix PHP coding-standard violations.
	$(COMPOSER) cs:fix

analyse: ## Run PHPStan at the maximum level.
	$(COMPOSER) analyse

test: ## Run PHPUnit; pass options with ARGS="...".
	$(COMPOSER) test -- $(ARGS)

coverage: ## Run PHPUnit and generate var/coverage.xml (requires PCOV or Xdebug).
	$(COMPOSER) test:coverage

security: ## Audit locked dependencies and fail on abandoned packages.
	$(COMPOSER) security

grumphp: ## Run the complete GrumPHP CI task suite.
	$(COMPOSER) grumphp -- --no-interaction

workflow-lint: ## Lint GitHub Actions workflows in the pinned actionlint image.
	$(DOCKER) run --rm --volume "$(CURDIR):/repo:ro" --workdir /repo $(ACTIONLINT_IMAGE) -color

hooks: ## Install or refresh GrumPHP Git hooks.
	$(PHP) vendor/bin/grumphp git:init

database-up: ## Start PostgreSQL and wait until it is healthy.
	$(DOCKER) compose up --detach --wait database

database-down: ## Stop the local PostgreSQL container without deleting its data.
	$(DOCKER) compose stop database

database-test-up: database-up ## Create the dedicated PostgreSQL database used by Postgres-only tests (idempotent).
	$(COMPOSE) exec -T database psql -U $(POSTGRES_USER) -d postgres -tc "SELECT 1 FROM pg_database WHERE datname = '$(POSTGRES_TEST_DB)'" | grep -q 1 || $(COMPOSE) exec -T database psql -U $(POSTGRES_USER) -d postgres -c "CREATE DATABASE $(POSTGRES_TEST_DB)"

vendor-sync: ## Rebuild the app image and sync its named vendor/ volume with composer.lock. The volume can otherwise shadow a freshly built image's vendor/ with stale dependencies.
	$(COMPOSE) run --rm --no-deps --build app composer install --prefer-dist --no-interaction --no-progress

test-postgres: vendor-sync database-test-up ## Run PHPUnit against real PostgreSQL 16, required for the Audit UNION ALL tests; pass options with ARGS="...".
	$(COMPOSE) run --rm -e APP_ENV=test -e DATABASE_TEST_URL="postgresql://$(POSTGRES_USER):$(POSTGRES_PASSWORD)@database:5432/$(POSTGRES_TEST_DB)?serverVersion=16&charset=utf8" app vendor/bin/phpunit $(ARGS)

migrate: ## Apply Doctrine migrations to the configured database.
	$(COMPOSE) run --rm --build app php bin/console doctrine:migrations:migrate --no-interaction

fixtures: vendor-sync database-up ## Load deterministic DEV-only fixtures (RBAC, users, node groups, nodes, agents) into the local database.
	$(COMPOSE) run --rm app php bin/console doctrine:fixtures:load --no-interaction

dev-reset: vendor-sync database-up ## DEV ONLY: drop, recreate, migrate, and reseed the local development database. Refuses to run unless the app container resolves to the "dev" environment.
	@echo "This drops and recreates the LOCAL DEVELOPMENT database. It must never be pointed at production."
	@$(COMPOSE) run --rm --no-deps app php bin/console about | grep -Eq '^\s*Environment\s+dev\s*$$' \
		|| (echo "Refusing to run: the app container did not resolve to the 'dev' environment." >&2 && exit 1)
	$(COMPOSE) run --rm app php bin/console doctrine:database:drop --force --if-exists
	$(COMPOSE) run --rm app php bin/console doctrine:database:create --if-not-exists
	$(COMPOSE) run --rm app php bin/console doctrine:migrations:migrate --no-interaction
	$(COMPOSE) run --rm app php bin/console doctrine:fixtures:load --no-interaction

schema-validate: ## Validate Doctrine mapping and database schema.
	$(COMPOSE) run --rm --build app php bin/console doctrine:schema:validate

jwt-keys: ## Generate the ignored JWT signing key pair without overwriting existing keys.
	$(COMPOSE) run --rm --no-deps --build app php bin/console lexik:jwt:generate-keypair --skip-if-exists
	$(COMPOSE) run --rm --no-deps app chmod 0600 config/jwt/private.pem
	$(COMPOSE) run --rm --no-deps app chmod 0644 config/jwt/public.pem

openapi: ## Export the OpenAPI document to var/openapi.json.
	$(COMPOSE) run --rm --no-deps --build app php bin/console api:openapi:export --output=var/openapi.json

cache-clear: ## Clear the Symfony cache; pass an environment with ARGS="--env=test".
	$(COMPOSE) run --rm --no-deps --build app php bin/console cache:clear $(ARGS)

cache-warmup: ## Warm the Symfony cache; pass an environment with ARGS="--env=test".
	$(COMPOSE) run --rm --no-deps --build app php bin/console cache:warmup $(ARGS)

console: ## Run a Symfony command, for example ARGS="about".
	$(COMPOSE) run --rm --build app php bin/console $(ARGS)
