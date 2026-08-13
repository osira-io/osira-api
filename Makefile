SHELL := /bin/sh

.DEFAULT_GOAL := help
.DELETE_ON_ERROR:
MAKEFLAGS += --no-builtin-rules

PHP ?= php
COMPOSER ?= composer
DOCKER ?= docker

ACTIONLINT_IMAGE := rhysd/actionlint@sha256:b1934ee5f1c509618f2508e6eb47ee0d3520686341fec936f3b79331f9315667

.PHONY: help install update validate qa ci lint cs-check cs-fix analyse test security grumphp workflow-lint hooks cache-clear cache-warmup console

help: ## Show the available targets.
	@awk 'BEGIN {FS = ":.*## "; printf "Usage: make <target> [ARGS=\"...\"]\n\nTargets:\n"} /^[a-zA-Z0-9_-]+:.*## / {printf "  %-16s %s\n", $$1, $$2}' $(MAKEFILE_LIST)

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

security: ## Audit locked dependencies and fail on abandoned packages.
	$(COMPOSER) security

grumphp: ## Run the complete GrumPHP CI task suite.
	$(COMPOSER) grumphp -- --no-interaction

workflow-lint: ## Lint GitHub Actions workflows in the pinned actionlint image.
	$(DOCKER) run --rm --volume "$(CURDIR):/repo:ro" --workdir /repo $(ACTIONLINT_IMAGE) -color

hooks: ## Install or refresh GrumPHP Git hooks.
	$(PHP) vendor/bin/grumphp git:init

cache-clear: ## Clear the Symfony cache; pass an environment with ARGS="--env=test".
	$(PHP) bin/console cache:clear $(ARGS)

cache-warmup: ## Warm the Symfony cache; pass an environment with ARGS="--env=test".
	$(PHP) bin/console cache:warmup $(ARGS)

console: ## Run a Symfony command, for example ARGS="about".
	$(PHP) bin/console $(ARGS)
