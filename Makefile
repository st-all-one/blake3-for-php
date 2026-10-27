SHELL := /bin/bash
.DEFAULT_GOAL := help

DOCKER ?= docker

TRIXIE_IMAGE ?= blake3-php:8.4.27-trixie
ALPINE_IMAGE ?= blake3-php:8.4.27-alpine

DOCKER_RUN = $(DOCKER) run --rm \
	-u "$$(id -u):$$(id -g)" \
	-e HOME=/tmp \
	-v "$(CURDIR):/app" \
	-w /app

.PHONY: help build install update validate lint fmt static test test-trixie test-alpine \
	coverage check audit bench bench-trixie bench-alpine bench-json smoke shell exec php-version clean

help: ## Show available targets
	@grep -hE '^[a-zA-Z0-9_-]+:.*?## ' $(MAKEFILE_LIST) \
		| awk -F '## ' '{printf "  %-14s %s\n", $$1, $$2}'

build: ## Build both development images (glibc and musl)
	$(DOCKER) build -f docker/trixie.Dockerfile -t $(TRIXIE_IMAGE) .
	$(DOCKER) build -f docker/alpine.Dockerfile -t $(ALPINE_IMAGE) .

install: build ## Install locked Composer dependencies
	$(DOCKER_RUN) $(TRIXIE_IMAGE) composer install --no-interaction --prefer-dist --no-progress

update: ## Update Composer dependencies (writes composer.lock)
	$(DOCKER_RUN) $(TRIXIE_IMAGE) composer update --no-interaction --prefer-dist --no-progress

validate: ## Validate composer.json strictly
	$(DOCKER_RUN) $(TRIXIE_IMAGE) composer validate --strict --no-check-publish

lint: ## Run Mago lint and formatter check
	$(DOCKER_RUN) $(TRIXIE_IMAGE) composer lint

fmt: ## Apply Mago formatting
	$(DOCKER_RUN) $(TRIXIE_IMAGE) vendor/bin/mago format

static: ## Run PHPStan at maximum level
	$(DOCKER_RUN) $(TRIXIE_IMAGE) composer static

test: ## Run the test suite on glibc
	$(DOCKER_RUN) $(TRIXIE_IMAGE) composer test

test-trixie: ## Run the test suite on glibc
	$(DOCKER_RUN) $(TRIXIE_IMAGE) composer test

test-alpine: ## Run the test suite on musl
	$(DOCKER_RUN) $(ALPINE_IMAGE) composer test

coverage: ## Run tests and enforce 100% line coverage
	$(DOCKER_RUN) $(TRIXIE_IMAGE) composer coverage

check: install ## Full quality gate on glibc and musl
	$(DOCKER_RUN) $(TRIXIE_IMAGE) composer check
	$(DOCKER_RUN) $(TRIXIE_IMAGE) composer coverage
	$(DOCKER_RUN) $(ALPINE_IMAGE) composer check

audit: ## Audit installed dependencies (requires network)
	$(DOCKER_RUN) $(TRIXIE_IMAGE) composer audit

bench: ## Run the performance baseline on glibc and musl (PCOV/assertions off)
	$(DOCKER_RUN) $(TRIXIE_IMAGE) php -d pcov.enabled=0 -d zend.assertions=-1 benchmarks/bench.php
	$(DOCKER_RUN) $(ALPINE_IMAGE) php -d pcov.enabled=0 -d zend.assertions=-1 benchmarks/bench.php

bench-trixie: ## Run the performance baseline on glibc (PCOV/assertions off)
	$(DOCKER_RUN) $(TRIXIE_IMAGE) php -d pcov.enabled=0 -d zend.assertions=-1 benchmarks/bench.php

bench-alpine: ## Run the performance baseline on musl (PCOV/assertions off)
	$(DOCKER_RUN) $(ALPINE_IMAGE) php -d pcov.enabled=0 -d zend.assertions=-1 benchmarks/bench.php

bench-json: ## Emit the glibc baseline as JSON
	$(DOCKER_RUN) $(TRIXIE_IMAGE) php -d pcov.enabled=0 -d zend.assertions=-1 benchmarks/bench.php --json

smoke: ## Install this package in a temporary external project
	$(DOCKER_RUN) $(TRIXIE_IMAGE) bash tools/consumer-smoke.sh

shell: ## Open a shell in the glibc image
	$(DOCKER) run --rm -it -u "$$(id -u):$$(id -g)" -e HOME=/tmp \
		-v "$(CURDIR):/app" -w /app $(TRIXIE_IMAGE) sh

exec: ## Run a command in the glibc image (cmd='php -v')
	@test -n "$(cmd)" || { echo "usage: make exec cmd='php -v'"; exit 2; }
	$(DOCKER_RUN) $(TRIXIE_IMAGE) sh -c "$(cmd)"

php-version: ## Print PHP versions from both images
	$(DOCKER_RUN) $(TRIXIE_IMAGE) php -v
	$(DOCKER_RUN) $(ALPINE_IMAGE) php -v

clean: ## Remove generated dependencies and caches
	rm -rf vendor build .phpunit.cache
