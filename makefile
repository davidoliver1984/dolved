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
	aws-provision aws-status qdrant-status publish-ingestion consume-ingestion \
	telemetry-smoke telemetry-verify telemetry-outage \
	evaluation-run \
	evaluation-benchmark-sync \
	evaluation-benchmark-compile \
	evaluation-exp-0003 \
	evaluation-exp-0004-runtime evaluation-exp-0004 \
	evaluation-calibration-runtime evaluation-calibration-run evaluation-calibration-replay \
	evaluation-live-hybrid \
	evaluation-report evaluation-index \
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
		'  make qdrant-status   Verify Qdrant readiness through Compose DNS' \
		'  make publish-ingestion  Run one outbox publication batch' \
		'  make consume-ingestion  Run one ingestion-worker receive batch' \
		'  make telemetry-smoke Verify Collector-to-Grafana trace and metric flow' \
		'  make telemetry-verify Verify cross-service trace, privacy and cardinality' \
		'  make telemetry-outage Verify requests survive a Collector outage' \
		'  make evaluation-run   Run the offline retrieval evaluation corpus' \
		'  make evaluation-benchmark-sync  Synchronise authored Markdown paths into the benchmark catalogue' \
		'  make evaluation-benchmark-compile  Validate and compile the engineering benchmark pilot' \
		'  make evaluation-exp-0003  Run the post-reliability full V2 engineering baseline' \
		'  make evaluation-exp-0004-runtime  Start and verify the isolated EXP-0004 runtime' \
		'  make evaluation-exp-0004  Run the immutable RRF k=5 engineering experiment' \
		'  make evaluation-calibration-runtime  Start and verify the isolated threshold-calibration runtime' \
		'  make evaluation-calibration-run  Run one immutable calibration provider pass' \
		'  make evaluation-calibration-replay  Replay thresholds without provider calls' \
		'  make evaluation-live-hybrid  Run the opt-in live hybrid retrieval evaluation' \
		'  make evaluation-report RUN=<id>  Regenerate one persisted evaluation report' \
		'  make evaluation-index  Regenerate the persisted experiment index' \
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

qdrant-status:
	$(EXEC) ai python -c "import os, urllib.request; print(urllib.request.urlopen(os.environ['QDRANT_URL'] + '/readyz', timeout=5).read().decode())"

publish-ingestion:
	$(EXEC) api php artisan ingestion:publish --once

consume-ingestion:
	$(COMPOSE) run --rm --no-deps worker python -m app.worker --once

telemetry-smoke:
	./scripts/telemetry/smoke-test.sh

telemetry-verify:
	./scripts/telemetry/verify-cross-service.sh

telemetry-outage:
	./scripts/telemetry/verify-collector-outage.sh

EVALUATION_CORPUS_VERSION ?= v2
EVALUATION_BENCHMARK_VERSION ?= v2
EVALUATION_CONTRACT_VERSION ?= v2

evaluation-benchmark-sync:
	$(COMPOSE) run --rm --no-deps \
		--volume "$(CURDIR):/workspace" \
		--volume "$(CURDIR)/apps/ai/app:/app/app:ro" \
		--workdir /workspace \
		ai python scripts/evaluation/sync_engineering_benchmark_catalog.py \
			--benchmark-root tests/evaluation/benchmarks/dolved-care-engineering/$(EVALUATION_BENCHMARK_VERSION)

evaluation-benchmark-compile:
	$(COMPOSE) run --rm --no-deps \
		--volume "$(CURDIR):/workspace" \
		--volume "$(CURDIR)/apps/ai/app:/app/app:ro" \
		--workdir /workspace \
		ai python scripts/evaluation/compile_engineering_benchmark.py \
			--benchmark-root tests/evaluation/benchmarks/dolved-care-engineering/$(EVALUATION_BENCHMARK_VERSION) \
			--contract-root contracts/evaluation/$(EVALUATION_CONTRACT_VERSION) \
			--contract-version $(EVALUATION_CONTRACT_VERSION)

evaluation-exp-0003:
	$(COMPOSE) exec api php artisan evaluation:benchmark:run-exp-0003 \
		--repository-commit="$$(git rev-parse HEAD)" \
		--dirty="$$(test -z "$$(git status --porcelain)" && printf 0 || printf 1)"
	$(COMPOSE) exec --env PYTHONPATH=/app ai \
		python /workspace/scripts/evaluation/compile_application_benchmark_run.py \
		--observations /evaluation-runs/EXP-0003-post-reliability-corrected-engineering-baseline/application-observations.json \
		--output-directory /evaluation-runs/EXP-0003-post-reliability-corrected-engineering-baseline \
		--historical-baseline /evaluation-runs/EXP-0002-adr0022-corrected-planning-baseline/result.json
	$(MAKE) evaluation-report RUN=EXP-0003-post-reliability-corrected-engineering-baseline

evaluation-exp-0004-runtime:
	./scripts/evaluation/exp0004_runtime.sh start

evaluation-exp-0004:
	./scripts/evaluation/exp0004_runtime.sh run

evaluation-calibration-runtime:
	./scripts/evaluation/calibration_runtime.sh start

evaluation-calibration-run:
	./scripts/evaluation/calibration_runtime.sh run

evaluation-calibration-replay:
	./scripts/evaluation/calibration_runtime.sh replay

evaluation-run:
	@mkdir -p /tmp/rag-platform-evaluation
	$(COMPOSE) run --rm --no-deps \
		--volume "$(CURDIR):/workspace" \
		--volume "$(CURDIR)/apps/ai/app:/app/app:ro" \
		--volume "/app/.venv" \
		--volume "/tmp/rag-platform-evaluation:/output" \
		--workdir /workspace \
		--env PYTHONPATH=/app \
		ai python scripts/evaluation/run.py run \
			--corpus tests/evaluation/corpus/$(EVALUATION_CORPUS_VERSION)/corpus.json \
			--policy tests/evaluation/policies/v1/policy.json \
			--observations tests/evaluation/observations/$(EVALUATION_CORPUS_VERSION)/offline-baseline.json \
			--repository-commit "$(shell git rev-parse HEAD)" \
			--output /output/result.json

evaluation-live-hybrid:
	@test "$${RUN_LIVE_HYBRID_EVALUATION:-}" = "1" || \
		{ printf '%s\n' 'Set RUN_LIVE_HYBRID_EVALUATION=1 to permit paid live-provider calls.'; exit 1; }
	@mkdir -p /tmp/rag-platform-evaluation
	$(COMPOSE) run --rm --no-deps \
		--volume "$(CURDIR):/workspace" \
		--volume "$(CURDIR)/apps/ai/app:/app/app:ro" \
		--volume "/tmp/rag-platform-evaluation:/output" \
		--workdir /workspace \
		--env PYTHONPATH=/app \
		ai python scripts/evaluation/run.py live-hybrid \
			--corpus tests/evaluation/corpus/$(EVALUATION_CORPUS_VERSION)/corpus.json \
			--policy tests/evaluation/policies/v1/policy.json \
			--repository-commit "$$(git rev-parse HEAD)-dirty" \
			--evidence-threshold 0.337890625 \
			--text-capture-mode BENCHMARK_TEXT \
			--output /output/live-hybrid-result.json

evaluation-report:
	@test -n "$(RUN)" || { printf '%s\n' 'RUN is required (for example EXP-0001-first-run).'; exit 1; }
	@test -d "docs/evaluation/runs/$(RUN)" || { printf '%s\n' 'Unknown evaluation run: $(RUN)'; exit 1; }
	$(COMPOSE) run --rm --no-deps \
		--volume "$(CURDIR):/workspace" \
		--volume "$(CURDIR)/apps/ai/app:/app/app:ro" \
		--workdir /workspace \
		--env PYTHONPATH=/app \
		ai python scripts/evaluation/report.py generate \
			--run-dir "docs/evaluation/runs/$(RUN)" \
			--runs-root docs/evaluation/runs \
			--index docs/evaluation/EXPERIMENTS.md \
			$(if $(BASELINE),--baseline-result "$(BASELINE)",)

evaluation-index:
	$(COMPOSE) run --rm --no-deps \
		--volume "$(CURDIR):/workspace" \
		--volume "$(CURDIR)/apps/ai/app:/app/app:ro" \
		--workdir /workspace \
		--env PYTHONPATH=/app \
		ai python scripts/evaluation/report.py index \
			--runs-root docs/evaluation/runs \
			--index docs/evaluation/EXPERIMENTS.md

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
