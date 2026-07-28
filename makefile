SHELL := /bin/bash
.DEFAULT_GOAL := help

COMPOSE := docker compose
EXEC := $(COMPOSE) exec -T
WAIT_TIMEOUT ?= 180
TAIL ?= 100

.PHONY: \
	help build up down restart ps logs \
	lint lint-web lint-api lint-ai \
	format format-web format-api format-ai \
	format-check format-check-web format-check-api format-check-ai \
	typecheck typecheck-web typecheck-ai \
	test test-web test-api test-ai \
	bootstrap migrate seed reset clean \
	aws-provision aws-status publish-ingestion \
	shell-web shell-api shell-ai shell-db shell-aws

help:
	@printf '%s\n' \
		'RAG Platform developer commands' \
		'' \
		'Environment' \
		'  make bootstrap       Build, start, wait for health and migrate' \
		'  make build           Build all application images' \
		'  make up              Start the platform and wait for health' \
		'  make down            Stop the platform without deleting data' \
		'  make restart         Recreate the running platform' \
		'  make ps              Show service state and published ports' \
		'  make logs            Follow service logs (TAIL=100 by default)' \
		'' \
		'Database' \
		'  make migrate         Run outstanding Laravel migrations' \
		'  make seed            Run the Laravel database seeder' \
		'  make aws-provision   Idempotently provision local AWS resources' \
		'  make aws-status      Verify the bucket, queues and redrive policy' \
		'  make publish-ingestion  Run one outbox publication batch' \
		'  make reset           Delete local volumes and bootstrap again' \
		'' \
		'Quality' \
		'  make lint            Run all linters' \
		'  make format          Apply all available formatters' \
		'  make format-check    Verify formatting without changing files' \
		'  make typecheck       Run all available static type checks' \
		'  make test            Run every currently configured test suite' \
		'  make test-web        Run Next.js unit tests' \
		'  make test-api        Run Laravel tests' \
		'  make test-ai         Run Python tests' \
		'' \
		'Maintenance and shells' \
		'  make clean           Clear generated caches without deleting data' \
		'  make shell-web       Open a shell in the web container' \
		'  make shell-api       Open a shell in the API container' \
		'  make shell-ai        Open a shell in the AI container' \
		'  make shell-db        Open psql in PostgreSQL' \
		'  make shell-aws       Open a shell in LocalStack'

build:
	$(COMPOSE) build

up:
	$(COMPOSE) up --detach --wait --wait-timeout $(WAIT_TIMEOUT)

down:
	$(COMPOSE) down --remove-orphans

restart:
	$(COMPOSE) up --detach --force-recreate --wait --wait-timeout $(WAIT_TIMEOUT)

ps:
	$(COMPOSE) ps

logs:
	$(COMPOSE) logs --follow --tail $(TAIL)

lint: lint-web lint-api lint-ai

lint-web:
	$(EXEC) web npm run lint

lint-api:
	$(EXEC) api ./vendor/bin/pint --test

lint-ai:
	$(EXEC) ai uv run ruff check .

format: format-web format-api format-ai

format-web:
	$(EXEC) web npm run lint -- --fix

format-api:
	$(EXEC) api ./vendor/bin/pint

format-ai:
	$(EXEC) ai uv run ruff format .

format-check: format-check-web format-check-api format-check-ai

format-check-web:
	$(EXEC) web npm run lint

format-check-api:
	$(EXEC) api ./vendor/bin/pint --test

format-check-ai:
	$(EXEC) ai uv run ruff format --check .

typecheck: typecheck-web typecheck-ai

typecheck-web:
	$(EXEC) web npx tsc --noEmit

typecheck-ai:
	$(EXEC) ai uv run mypy app tests

test: test-web test-api test-ai

test-web:
	$(EXEC) web npm test

test-api:
	$(EXEC) api php artisan test

test-ai:
	$(EXEC) ai uv run pytest

bootstrap:
	@test -f .env || { cp .env.example .env; printf '%s\n' 'Created .env from .env.example'; }
	$(COMPOSE) up --detach --build --wait --wait-timeout $(WAIT_TIMEOUT)
	$(MAKE) migrate

migrate:
	$(EXEC) api php artisan migrate --force

seed:
	$(EXEC) api php artisan db:seed --force

aws-provision:
	$(EXEC) localstack /etc/localstack/init/ready.d/10-provision-aws.sh

aws-status:
	$(EXEC) localstack /opt/rag-platform/localstack/verify.sh

publish-ingestion:
	$(EXEC) api php artisan ingestion:publish --once

reset:
	@printf '%s\n' \
		'WARNING: this deletes all local Compose volumes, including PostgreSQL data.' \
		'Type RESET to continue:'; \
	read -r answer; \
	if [ "$$answer" != 'RESET' ]; then \
		printf '%s\n' 'Reset cancelled.'; \
		exit 1; \
	fi
	$(COMPOSE) down --volumes --remove-orphans
	$(MAKE) bootstrap

clean:
	$(COMPOSE) stop web
	$(COMPOSE) run --rm --no-deps web sh -c 'rm -rf .next/*'
	$(EXEC) api php artisan optimize:clear
	$(EXEC) ai sh -c 'find app tests -type d -name __pycache__ -prune -exec rm -rf {} +; rm -rf .mypy_cache .pytest_cache .ruff_cache'
	$(COMPOSE) up --detach --force-recreate --no-deps web --wait --wait-timeout $(WAIT_TIMEOUT)

shell-web:
	$(COMPOSE) exec web sh

shell-api:
	$(COMPOSE) exec api sh

shell-ai:
	$(COMPOSE) exec ai sh

shell-db:
	$(COMPOSE) exec postgres psql --username "$${POSTGRES_USER:-rag_platform}" --dbname "$${POSTGRES_DB:-rag_platform}"

shell-aws:
	$(COMPOSE) exec localstack bash
