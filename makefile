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
	test test-web test-api test-ai test-telemetry test-postgres-role-topology test-e2e test-e2e-inspect test-e2e-clean test-splade-integration \
	bootstrap migrate postgres-roles-verify seed reset clean \
	aws-provision aws-status qdrant-status publish-ingestion consume-ingestion \
	telemetry-smoke telemetry-verify telemetry-outage \
	evaluation-run evaluation-policy-gate evaluation-generation-verify evaluation-generation-live \
	evaluation-r28-s02-preflight evaluation-r28-s02-retrieval-live evaluation-r28-s02-generation-live \
	evaluation-retrieval-current-candidate evaluation-retrieval-current \
	evaluation-benchmark-sync \
	evaluation-benchmark-compile \
	evaluation-exp-0003 \
	evaluation-exp-0004-runtime evaluation-exp-0004 \
	evaluation-exp-0005-runtime evaluation-exp-0005 \
	evaluation-calibration-runtime evaluation-calibration-run evaluation-calibration-replay \
	evaluation-cal-exp-0002-runtime evaluation-cal-exp-0002-run evaluation-cal-exp-0002-replay \
	evaluation-cal-exp-0003-runtime evaluation-cal-exp-0003-run evaluation-cal-exp-0003-replay \
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
		'  make postgres-roles-verify  Verify runtime/migrator isolation and effective grants' \
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
		'  make evaluation-policy-gate  Enforce the historical retrieval policy without providers' \
		'  make evaluation-retrieval-current  Run the current provider-free retrieval regression gate' \
		'  make evaluation-retrieval-current-candidate  Generate a deterministic retrieval candidate for review' \
		'  make evaluation-generation-verify  Verify immutable generation evidence without providers' \
		'  make evaluation-generation-live  Run the bounded opt-in live prompt-injection evaluation' \
		'  make evaluation-r28-s02-preflight  Verify both R28-S02 live boundaries without provider calls' \
		'  make evaluation-r28-s02-retrieval-live  Run the approved R28-S02 retrieval component' \
		'  make evaluation-r28-s02-generation-live  Run the approved R28-S02 generation-security component' \
		'  make evaluation-benchmark-sync  Synchronise authored Markdown paths into the benchmark catalogue' \
		'  make evaluation-benchmark-compile  Validate and compile the engineering benchmark pilot' \
		'  make evaluation-exp-0003  Run the post-reliability full V2 engineering baseline' \
		'  make evaluation-exp-0004-runtime  Start and verify the isolated EXP-0004 runtime' \
		'  make evaluation-exp-0004  Run the immutable RRF k=5 engineering experiment' \
		'  make evaluation-exp-0005-runtime  Start and verify the isolated EXP-0005 runtime' \
		'  make evaluation-exp-0006-runtime  Start and verify the isolated EXP-0006 runtime' \
		'  make evaluation-exp-0005  Run the immutable ADR-0022-v2 consolidated engineering baseline' \
		'  make evaluation-calibration-runtime  Start and verify the isolated threshold-calibration runtime' \
		'  make evaluation-calibration-run  Run one immutable calibration provider pass' \
		'  make evaluation-cal-exp-0002-runtime  Start and verify isolated CAL-EXP-0002 runtime' \
		'  make evaluation-cal-exp-0002-run  Run the single CAL-EXP-0002 provider pass' \
		'  make evaluation-cal-exp-0002-replay  Validate and replay CAL-EXP-0002 provider-free' \
		'  make evaluation-cal-exp-0003-runtime  Start and verify isolated CAL-EXP-0003 runtime' \
		'  make evaluation-cal-exp-0003-run  Run the single CAL-EXP-0003 provider pass' \
		'  make evaluation-cal-exp-0003-replay  Validate and replay CAL-EXP-0003 provider-free' \
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
		'  make test-telemetry  Verify the pinned Collector sampling component and configuration' \
		'  make test-postgres-role-topology  Verify Compose credential isolation without starting services' \
		'  make test-e2e       Run the isolated deterministic ingestion journey' \
		'  make test-e2e-inspect  Inspect a preserved failed dolved-e2e stack' \
		'  make test-e2e-clean  Remove only the isolated dolved-e2e stack and volumes' \
		'  make test-splade-integration  Prove the pinned real SPLADE model loads and encodes' \
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

test: test-web test-api test-ai test-telemetry test-postgres-role-topology

test-web:
	$(EXEC) web npm test

test-api:
	$(EXEC) api php artisan test

test-ai:
	$(EXEC) ai uv run pytest

test-telemetry:
	./scripts/telemetry/test_collector_configuration.sh

test-postgres-role-topology:
	./scripts/postgres/test_runtime_role_topology.sh

test-e2e:
	./scripts/e2e/run.sh

test-e2e-inspect:
	./scripts/e2e/inspect.sh

test-e2e-clean:
	./scripts/e2e/clean.sh

test-splade-integration:
	$(EXEC) ai uv run pytest tests/integration/test_real_splade.py -q

bootstrap:
	@test -f .env || { cp .env.example .env; printf '%s\n' 'Created .env from .env.example'; }
	$(COMPOSE) up --detach --build --wait --wait-timeout $(WAIT_TIMEOUT)
	$(MAKE) migrate

migrate:
	$(COMPOSE) run --rm migrator php artisan migrate --force

postgres-roles-verify:
	./scripts/postgres/verify_runtime_roles.sh

postgres-bulk-verify:
	./scripts/postgres/verify_bulk_operation_foundation.sh

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
E2E_COMPOSE := docker compose --env-file .env.e2e --project-name dolved-e2e --file compose.yaml --file compose.e2e.yaml
EVALUATION_CURRENT_COMPOSE := docker compose --env-file .env.e2e --project-name dolved-evaluation-current --file compose.yaml --file compose.e2e.yaml --file compose.evaluation-current.yaml

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

evaluation-exp-0005-runtime:
	./scripts/evaluation/exp0005_runtime.sh start

evaluation-exp-0005:
	./scripts/evaluation/exp0005_runtime.sh run

evaluation-exp-0006-runtime:
	./scripts/evaluation/exp0006_runtime.sh start

evaluation-exp-0006:
	./scripts/evaluation/exp0006_runtime.sh run

evaluation-calibration-runtime:
	./scripts/evaluation/calibration_runtime.sh start

evaluation-calibration-run:
	./scripts/evaluation/calibration_runtime.sh run

evaluation-calibration-replay:
	./scripts/evaluation/calibration_runtime.sh replay

evaluation-cal-exp-0002-runtime:
	./scripts/evaluation/cal_exp_0002_runtime.sh start

evaluation-cal-exp-0002-run:
	./scripts/evaluation/cal_exp_0002_runtime.sh run

evaluation-cal-exp-0002-replay:
	./scripts/evaluation/cal_exp_0002_runtime.sh replay

evaluation-cal-exp-0003-runtime:
	./scripts/evaluation/cal_exp_0003_runtime.sh start

evaluation-cal-exp-0003-run:
	./scripts/evaluation/cal_exp_0003_runtime.sh run

evaluation-cal-exp-0003-replay:
	./scripts/evaluation/cal_exp_0003_runtime.sh replay

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

evaluation-policy-gate: evaluation-run
	$(COMPOSE) run --rm --no-deps \
		--volume "$(CURDIR):/workspace" \
		--volume "$(CURDIR)/apps/ai/app:/app/app:ro" \
		--volume "/app/.venv" \
		--volume "/tmp/rag-platform-evaluation:/output" \
		--workdir /workspace \
		--env PYTHONPATH=/app \
		ai python scripts/evaluation/run.py compare \
			--candidate /output/result.json \
			--baseline docs/evaluation/baselines/$(EVALUATION_CORPUS_VERSION)/experiment-result.json \
			--promotion docs/evaluation/baselines/$(EVALUATION_CORPUS_VERSION)/baseline-promotion.json \
			--policy tests/evaluation/policies/v1/policy.json \
			--output /output/comparison-report.md

evaluation-retrieval-current-candidate:
	./scripts/evaluation/run_current_retrieval.sh

evaluation-retrieval-current: evaluation-retrieval-current-candidate
	$(EVALUATION_CURRENT_COMPOSE) run --rm --no-deps \
		--volume "$(CURDIR)/scripts/evaluation/run.py:/evaluation/run.py:ro" \
		--volume "$(CURDIR)/docs/evaluation/baselines/deterministic-v1:/baseline:ro" \
		--volume "$(CURDIR)/tests/evaluation/policies/v1/policy.json:/evaluation/policy.json:ro" \
		--volume "/tmp/rag-platform-evaluation-current:/output" \
		--env ENVIRONMENT=evaluation-current \
		--env PYTHONPATH=/app \
		ai python /evaluation/run.py compare-deterministic \
			--candidate /output/experiment-result.json \
			--baseline /baseline/experiment-result.json \
			--promotion /baseline/baseline-promotion.json \
			--checksums /baseline/checksums.sha256 \
			--baseline-eligibility-artifact /baseline/eligibility-artifact.json \
			--candidate-eligibility-artifact /output/eligibility-artifact.json \
			--policy /evaluation/policy.json \
			--output /output/comparison-report.md
	$(EVALUATION_CURRENT_COMPOSE) down --volumes --remove-orphans

evaluation-generation-verify:
	$(COMPOSE) run --rm --no-deps \
		--volume "$(CURDIR):/workspace:ro" \
		--volume "$(CURDIR)/apps/ai/app:/app/app:ro" \
		--volume "/app/.venv" \
		--workdir /workspace \
		--env PYTHONPATH=/app \
		ai python scripts/evaluation/verify_generation_evidence.py \
			--generation-root docs/evaluation/generation \
			--runs-root docs/evaluation/runs

evaluation-generation-live:
	@test "$${RUN_LIVE_GENERATION_EVALUATION:-}" = "1" || \
		{ printf '%s\n' 'Set RUN_LIVE_GENERATION_EVALUATION=1 to permit paid live-provider calls.'; exit 1; }
	@test -n "$${GENERATION_LIVE_EXPERIMENT_ID:-}" || \
		{ printf '%s\n' 'GENERATION_LIVE_EXPERIMENT_ID is required and must be a new immutable run identity.'; exit 1; }
	@test -z "$$(git status --porcelain --untracked-files=no)" || \
		{ printf '%s\n' 'The tracked worktree must be clean before live generation evaluation.'; exit 1; }
	@mkdir -p /tmp/rag-platform-generation-live
	$(COMPOSE) run --rm --no-deps \
		--volume "$(CURDIR):/workspace:ro" \
		--volume "/tmp/rag-platform-generation-live:/output" \
		--workdir /workspace \
		--env PYTHONPATH=/app \
		--env RUN_LIVE_GENERATION_EVALUATION=1 \
		ai python scripts/evaluation/run_generation_live.py \
			--policy tests/evaluation/security/v1/live-generation-policy.json \
			--repository-root /workspace \
			--repository-commit "$$(git rev-parse HEAD)" \
			--experiment-id "$${GENERATION_LIVE_EXPERIMENT_ID}"

evaluation-r28-s02-preflight:
	@test -z "$$(git status --porcelain --untracked-files=no)" || \
		{ printf '%s\n' 'The tracked worktree must be clean for R28-S02 preflight.'; exit 1; }
	@mkdir -p /tmp/rag-platform-r28-s02
	$(COMPOSE) run --rm --no-deps \
		--volume "$(CURDIR):/workspace:ro" \
		--volume "/tmp/rag-platform-r28-s02:/output" \
		--workdir /workspace \
		--env PYTHONPATH=/app \
		--env EMBEDDING_MAX_ATTEMPTS=1 \
		--env RERANKER_MAX_ATTEMPTS=1 \
		ai python scripts/evaluation/run_r28_s02_retrieval_live.py \
			--policy tests/evaluation/policies/v1/r28-s02-live-retrieval-policy.json \
			--repository-root /workspace \
			--repository-commit "$$(git rev-parse HEAD)" \
			--experiment-id R28-S02-LIVE-RETRIEVAL-BASELINE-0001 \
			--preflight-only
	$(COMPOSE) run --rm --no-deps \
		--volume "$(CURDIR):/workspace:ro" \
		--volume "/tmp/rag-platform-r28-s02:/output" \
		--workdir /workspace \
		--env PYTHONPATH=/app \
		ai python scripts/evaluation/run_generation_live.py \
			--policy tests/evaluation/security/v1/r28-s02-live-generation-policy.json \
			--repository-root /workspace \
			--repository-commit "$$(git rev-parse HEAD)" \
			--experiment-id GEN-SEC-LIVE-R28-S02-BASELINE-0001 \
			--preflight-only

evaluation-r28-s02-retrieval-live:
	@test "$${RUN_R28_S02_LIVE_RETRIEVAL:-}" = "1" || \
		{ printf '%s\n' 'Set RUN_R28_S02_LIVE_RETRIEVAL=1 to permit paid Voyage calls.'; exit 1; }
	@test -z "$$(git status --porcelain --untracked-files=no)" || \
		{ printf '%s\n' 'The tracked worktree must be clean before R28-S02 retrieval.'; exit 1; }
	@mkdir -p /tmp/rag-platform-r28-s02
	$(COMPOSE) run --rm --no-deps \
		--volume "$(CURDIR):/workspace:ro" \
		--volume "/tmp/rag-platform-r28-s02:/output" \
		--workdir /workspace \
		--env PYTHONPATH=/app \
		--env EMBEDDING_MAX_ATTEMPTS=1 \
		--env RERANKER_MAX_ATTEMPTS=1 \
		--env RUN_R28_S02_LIVE_RETRIEVAL=1 \
		ai python scripts/evaluation/run_r28_s02_retrieval_live.py \
			--policy tests/evaluation/policies/v1/r28-s02-live-retrieval-policy.json \
			--repository-root /workspace \
			--repository-commit "$$(git rev-parse HEAD)" \
			--experiment-id R28-S02-LIVE-RETRIEVAL-BASELINE-0001

evaluation-r28-s02-generation-live:
	@test "$${RUN_LIVE_GENERATION_EVALUATION:-}" = "1" || \
		{ printf '%s\n' 'Set RUN_LIVE_GENERATION_EVALUATION=1 to permit paid OpenAI calls.'; exit 1; }
	@test -z "$$(git status --porcelain --untracked-files=no)" || \
		{ printf '%s\n' 'The tracked worktree must be clean before R28-S02 generation.'; exit 1; }
	@mkdir -p /tmp/rag-platform-r28-s02
	$(COMPOSE) run --rm --no-deps \
		--volume "$(CURDIR):/workspace:ro" \
		--volume "/tmp/rag-platform-r28-s02:/output" \
		--workdir /workspace \
		--env PYTHONPATH=/app \
		--env RUN_LIVE_GENERATION_EVALUATION=1 \
		ai python scripts/evaluation/run_generation_live.py \
			--policy tests/evaluation/security/v1/r28-s02-live-generation-policy.json \
			--repository-root /workspace \
			--repository-commit "$$(git rev-parse HEAD)" \
			--experiment-id GEN-SEC-LIVE-R28-S02-BASELINE-0001

evaluation-live-hybrid:
	@test "$${RUN_LIVE_HYBRID_EVALUATION:-}" = "1" || \
		{ printf '%s\n' 'Set RUN_LIVE_HYBRID_EVALUATION=1 to permit paid live-provider calls.'; exit 1; }
	@test -z "$$(git status --porcelain --untracked-files=no)" || \
		{ printf '%s\n' 'The tracked worktree must be clean before live retrieval evaluation.'; exit 1; }
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
			--repository-root /workspace \
			--repository-commit "$$(git rev-parse HEAD)" \
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
