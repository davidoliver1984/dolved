# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Documentation authority — read this first

This repo treats documentation as load-bearing, not optional. Before making a change, check the authority for the question you have:

| Document | Answers |
|---|---|
| `PROJECT_ROADMAP.md` | What will be built, and in what order (phases) |
| `IMPLEMENTATION_GUIDE.md` | How each stage was actually built, verified and committed — the durable build log |
| `tasks.json` | Which session/phase is current right now |
| `docs/adr/` | Why a durable architecture decision was made (numbered, immutable once accepted) |
| `docs/journal/` | What happened during a specific engineering session |
| `PROJECT_JOURNEY.md` | Plain-language narrative of what's being built and why, for non-technical stakeholders — update it at the same stage/phase boundaries as `IMPLEMENTATION_GUIDE.md`/`tasks.json`, skip it for purely internal sessions |
| `CONTRIBUTING.md` | Full engineering philosophy, AI-behaviour rules, Laravel style conventions, security and commit rules |

**Do not duplicate these documents in conversation or in new files.** If two of them disagree, stop and flag the conflict rather than picking one silently.

`CONTRIBUTING.md` is the canonical, detailed rulebook (Laravel Actions/Queries/Services conventions, ADR criteria, security rules, commit conventions, tenancy rules) — read it in full before doing substantive work here. This file only adds what's needed to get oriented quickly.

### Current state

Read `tasks.json` for the live phase and session. Do not infer current state from
this file because it will become stale as the tracker advances.

## How this repo wants AI-assisted work done

`CONTRIBUTING.md` describes a teaching-session workflow originally built around Ralph/ChatGPT (architecture mentor) + Codex (implementer in teaching mode). When operating as the implementer in that loop:

- Inspect existing code/contracts/ADRs before introducing a new pattern — never assume.
- Explain reasoning before implementing; don't silently broaden scope past the agreed session boundary.
- Stop and present options (with a recommendation) when a decision is architectural — don't decide unilaterally. See "ADR is normally appropriate" criteria in `CONTRIBUTING.md`.
- Never fabricate verification — only report a check as passing if it was actually run.
- The human developer owns all final architecture decisions, commit boundaries, and authorises commits.

The developer is new to AI pair-programming and to building a RAG system, and wants a learning-oriented style, not "just write the code":

- Default to explaining the *why* behind a design choice or a piece of unfamiliar code (Laravel, Next.js, FastAPI, RAG concepts) before or alongside making it.
- Where reasonable, let the developer attempt the tricky/pedagogically-important part first, and review it, rather than always producing the finished implementation directly.
- Favour smaller, explained steps over large silent diffs, especially when introducing a new concept (e.g. chunking strategy, vector search, event-driven ingestion).
- It's fine to just implement routine/mechanical changes without ceremony — reserve the teaching pace for genuinely new concepts, not every line.

## Commands

All commands run through Docker Compose via the root Makefile — there is no supported host-native workflow (no local Node/PHP/Python toolchain use).

```bash
make bootstrap       # first-time setup: creates .env, builds, starts, waits healthy, migrates
make up / make down  # start / stop (down does not delete data)
make ps               # service state + health
make logs             # follow logs (TAIL=100 by default)

make lint             # web ESLint + Laravel Pint --test + Python Ruff check
make format           # apply fixes (ESLint --fix, Pint, Ruff format)
make format-check     # verify formatting without changing files
make typecheck        # TypeScript tsc --noEmit + Python mypy (no separate Laravel static analysis)

make test             # all three suites
make test-web         # Vitest (apps/web)
make test-api         # Laravel test suite (apps/api)
make test-ai          # Pytest (apps/ai)

make migrate / make seed
make aws-status        # verify LocalStack S3 bucket + SQS queues + redrive policy
make reset             # DESTRUCTIVE — deletes Compose volumes incl. Postgres data; requires typing RESET
```

Run a single test by execing straight into the container rather than adding a new make target:

```bash
docker compose exec -T web npx vitest run src/lib/api.test.ts
docker compose exec -T api php artisan test --filter=ExampleTest
docker compose exec -T ai uv run pytest tests/test_health.py::test_health -v
```

Shells: `make shell-web`, `make shell-api`, `make shell-ai`, `make shell-db` (psql), `make shell-aws` (LocalStack).

Before a session/phase boundary, run: `make format-check lint typecheck test ps`, plus `make aws-status` if the change touches LocalStack/S3/SQS.

## Architecture

Three independently-runnable services composed together (`compose.yaml`), each with its own Dockerfile `development` target, bind-mounted source, and a named volume for its dependency cache (`web_node_modules`/`web_next`, `api_vendor`, `ai_venv`):

- **`apps/web`** — Next.js/TypeScript (App Router). Browser interface only; calls the API, never the AI service directly.
- **`apps/api`** — Laravel. The system of record: auth (Sanctum + Fortify, session-cookie based, see `docs/adr/0005-*`), authorisation, tenancy, and all relational data in PostgreSQL. This is the security boundary — Next.js-side guards are UX only, never authorisation.
- **`apps/ai`** — FastAPI/Python. AI workloads (ingestion, embeddings, retrieval as those phases land), reachable only from `apps/api`, never directly from the browser.
- **`contracts/`** — language-neutral HTTP and event contract definitions shared across services; a cross-service contract change must update every producer/consumer and their tests together.
- **`infrastructure/`** — LocalStack config (S3 + SQS emulation) and future Terraform.
- **`docs/adr/`** — numbered, immutable decision records; see key ones already accepted: `0002` (three-service split), `0003` (container-first dev), `0004` (LocalStack 4.14), `0005` (Sanctum+Fortify auth).

Request path: browser → `web` → `api` → (`postgres`, `localstack` S3/SQS, `ai`). `ai` currently only depends on `localstack`; `api` depends on `ai`, `postgres`, `localstack`, `mailpit` all being healthy before it starts (see `compose.yaml` healthchecks).

Laravel internally follows a layered convention (detailed in `CONTRIBUTING.md`): thin controllers → Form Requests for validation → Actions (`app/Actions/`, one state-changing use case each) → Queries (`app/Queries/`, tenant-scoped reads) → Services (external/infra capabilities) → API Resources (response shapes). Tenant scoping must happen at the query boundary itself, not only in a policy.

Multi-tenancy (Phase 7, in progress) has not been architecturally decided yet — no tenant model, membership, or role code exists. Don't assume a shape for it; that decision is pending an ADR (`R07-S01`).
