# ADR 0029: Define the Platform Testing and Contract-Verification Strategy

## Status

Accepted

## Date

2026-08-22

## Relationship to prior ADRs

### Consumes, does not redefine, every product decision this ADR tests against

This ADR makes no retrieval, generation, tenancy, deletion, observability or
interface decision. It consumes, unchanged: ADR-0006 (workspace tenancy, the
`404`-not-`403` concealment discipline); ADR-0008/ADR-0009/ADR-0015/ADR-0016
(the transactional outbox, HMAC worker authentication, ingestion
orchestration and publication/recovery semantics); ADR-0010/ADR-0011
(extraction and chunking contracts); ADR-0013/ADR-0014 (embedding and vector
storage); ADR-0018/ADR-0019/ADR-0020/ADR-0021/ADR-0022 (retrieval planning,
evaluation, hybrid retrieval and the rc1 protocol); ADR-0023 (grounded
generation, the outcome taxonomy, `answer_parts[]`); ADR-0024 (the
conversation/streaming domain, resumable SSE, connection-independent
execution); ADR-0025 (administration, asynchronous deletion, tenant-scoped
usage); ADR-0026 (observability, platform-administrator authority, the
Collector-owned sampling boundary); ADR-0027/ADR-0028 (the product interface,
route hierarchy, and Platform Operations split). Every test category this
ADR defines proves an *already-decided* behaviour is correctly implemented —
it never becomes the place a behaviour gets decided.

### Supersedes nothing

No accepted decision is reopened. Where this ADR names an existing test file
as mis-scoped for what it's called (see "Verified current implementation"
below), the correction is a taxonomy-conformance rename for R22-S01 —
filename and class name only — not a claim that the test itself is wrong or
that its underlying Laravel behaviour needs to change.

## Context

### Verified current implementation

This is not a green-field testing decision. Direct inspection confirms
substantial, real coverage already exists across all three services:

- **`apps/web`**: Vitest 4 + Testing Library + jsdom, 28 test files (`npm
  test` via `make test-web`). `apps/web/Dockerfile` already pins
  `ARG NODE_VERSION=24-alpine` for the container runtime, but no `engines`
  field in `apps/web/package.json` and no `.node-version`/`.nvmrc` anywhere
  in the repository formalise that as a developer/toolchain contract — the
  Dockerfile's choice is the only place Node 24 is currently written down.
- **`apps/api`**: PHPUnit 12.5, 43 test classes across two suites — verified
  directly by file count, not estimated — `tests/Unit` (21 classes) and
  `tests/Feature` (22 classes) — run against an in-memory SQLite database
  (`phpunit.xml` forces `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`,
  `QUEUE_CONNECTION=sync`), never the real Postgres container.
- **`apps/ai`**: Pytest, 71 test modules (70 top-level `test_*.py` plus one
  under `tests/integration/`) — verified directly, excluding the two
  non-test support modules (`docx_fixtures.py`, `pdf_fixtures.py`), which
  are fixture helpers, not test modules themselves — one `integration`
  marker ("opt-in tests that call external services"), a naming convention
  (`*_live.py`) that already separates paid-provider tests from the rest.
- **`contracts/`**: a mature, already-versioned JSON Schema tree
  (`contracts/http/{ingestion-worker/v1, retrieval-call/rc1}`,
  `contracts/events/{document-ingestion-requested, document-deletion-requested}`,
  `contracts/evaluation/{v1,v2,v3}`), JSON Schema 2020-12,
  `additionalProperties: false` throughout, and — critically — **already
  validated on both language sides**: `apps/api/composer.json` requires
  `opis/json-schema ^2.6`, `apps/ai/pyproject.toml` requires
  `jsonschema[format-nongpl]>=4.26.0`, and negative fixtures already exist
  (`contracts/events/document-ingestion-requested/fixtures/invalid-*.json`).
  Cross-language HMAC canonicalisation/signature vectors already exist too
  (`contracts/http/{retrieval-call/rc1,ingestion-worker/v1}/canonicalisation-vectors.json`).
- **`apps/ai/app` already has a clean provider-neutral protocol/adapter
  pattern, and a deterministic fake *class* already exists and is checked in
  for each external-provider capability** — `embedding/fake.py`
  (`DeterministicFakeEmbedder`, a stable unit-length vector derived from
  `SHA-256(profile fingerprint, purpose, text)`, records requests for
  assertions, supports injected failures), `generation/fake.py`,
  `reranking/fake.py`, `sparse/fake.py`. **This establishes a useful pattern
  but is not yet a complete, E2E-selectable provider profile** — verified
  directly, each capability's `factory.py` still unconditionally constructs
  the real adapter: `embedding/factory.py`'s `create_embedder()` always
  returns `VoyageEmbedder`; `reranking/factory.py`'s `create_reranker()`
  always returns `VoyageReranker`; `generation/factory.py`'s
  `build_generator()` raises `ValueError("unsupported generation provider")`
  for anything other than `settings.generation_provider == "openai"`;
  `conversation/factory.py`'s `build_contextualizer()` raises the equivalent
  `ValueError` for anything other than `"openai"`. None of the four
  factories has a branch that would ever select a `fake.py` class — the
  fakes exist and are used directly by today's unit tests, but no
  `Settings`-driven selection mechanism connects them to a running process.
  Retrieval planning has no fake at all in the same sense: `retrieval/planner.py`
  does contain a `FixedRetrievalPlanner` class, explicitly documented in its
  own docstring as *"Deterministic test double; never selected as the
  production planner"* — but it takes one fixed `RetrievalPlan` as a
  constructor argument (not a catalogue mapping many fixture questions to
  distinct outcomes) and `retrieval/routes.py`'s `planner_dependency()`
  constructs `StructuredChatRetrievalPlanner` directly, with no factory or
  settings branch a test environment could redirect. Closing this gap —
  fail-closed factory/settings wiring for all four capabilities, plus a
  genuine deterministic scenario catalogue for the planner and
  contextualiser — is real, bounded new work this ADR allocates explicitly
  to R22-S03/R22-S04, not an already-solved problem.
- **`apps/api`'s Feature tests already fake the Laravel→Python boundary at
  the HTTP-client layer**, not the contract layer: `HybridRetrievalFoundationTest.php:227`
  uses `Http::fake(function (Request $request) ...)` to script a Python
  response and assert Laravel's own request construction/parsing — this
  proves Laravel's orchestration logic is correct *assuming* Python responds
  in a given shape, but never proves Python actually produces that shape.
  This is a real, valuable, existing test category (Laravel Feature tests
  with a faked HTTP boundary) that this ADR keeps, names precisely, and
  explicitly distinguishes from the two things it is not: a contract test
  (which must prove both sides against the same fixture, see "Contract
  verification strategy") and a genuine end-to-end test (which must run the
  real Python service).
- **Retrieval evaluation machinery is mature, provider-free by default, and
  already gated** — but this does not extend to generation evaluation, which
  is a materially different, provider-dependent pipeline; conflating the two
  was a factual error in an earlier draft of this ADR, corrected here.
  `tests/evaluation/` contains three versioned engineering benchmark
  generations (`v1`/`v2`/`v3` under
  `tests/evaluation/benchmarks/dolved-care-engineering/`), a compiled
  corpus/population pipeline, immutable per-experiment `runtime-lineage.json`
  records, and — already accepted, not something this ADR invents — an
  explicit quality-gate policy at `tests/evaluation/policies/v1/policy.json`:
  zero-tolerance (`allowed_regressions: {recall_at_k: 0.0, mrr: 0.0,
  ndcg_at_k: 0.0}`) on load-bearing slices (`CURRENT`, `COMPARE`,
  `applicability`, `cross-workspace`, `adversarial`), a fixed set of
  `absolute_failures` (`cross_workspace_evidence`, `unauthorised_evidence`,
  `temporally_ineligible_evidence`, `applicability_ineligible_evidence`,
  `lost_evaluation_case`, `metric_non_reproducibility`), and advisory-only
  metrics (`CONTEXT_RELEVANCE`, `latency_ms`, `provider_cost`) that never
  gate. **`make evaluation-run`'s actual mechanism, verified directly against
  `scripts/evaluation/run.py` and the makefile target, is report generation,
  not gate enforcement — a second, deeper correction beyond the earlier
  "replay, not live execution" finding.** The makefile target passes
  `--observations tests/evaluation/observations/$(EVALUATION_CORPUS_VERSION)/offline-baseline.json`;
  `run.py`'s `run()` function loads that file's pre-recorded
  `VariantObservation` entries and evaluates *them* — via
  `RetrievalEvaluationHarness().evaluate(...)` — against the corpus, then
  writes `result.json`. It never constructs a `Retriever`, never calls
  `planner_dependency()`, never touches the current embedding/sparse/
  reranking/planning implementation — **and it never calls `assess_gate()`
  and never exits non-zero when the accepted policy fails.** `assess_gate()`
  exists only inside `run.py`'s separate `compare` subcommand, which itself
  writes "Status: **FAIL**" into a text report on policy failure but *also*
  does not `sys.exit(1)` — the process exits `0` either way today. So
  `make evaluation-run`, as it stands, is genuinely just a **report
  generator** over historical observations: it does not prove those
  observations satisfy the policy, because nothing in its invocation checks
  that. This ADR corrects an earlier draft that claimed otherwise, and
  defines the actual required gate command, `make evaluation-policy-gate`,
  precisely in "Real and representative corpus strategy," Level 2, below —
  report generation alone is never described as a gate anywhere in this
  ADR from this point on. `make evaluation-live-hybrid` is already gated
  behind `RUN_LIVE_HYBRID_EVALUATION=1` and, unlike `evaluation-run`, does
  execute a real pipeline — verified directly:
  `apps/ai/app/evaluation/live_hybrid_retrieval.py` imports
  `create_embedder()` (the real factory) and constructs `VoyageReranker`
  directly, runs real Qdrant search, and evaluates against
  *authored per-case plans/queries already carried in the corpus* rather
  than invoking a live planner — but it is optional, live-provider evidence,
  not a required, provider-free check. **Because Phase 22 explicitly
  promises representative-corpus testing, two things are required, not
  one**: a genuine policy-enforcement gate over the historical evidence
  (`make evaluation-policy-gate`) and a genuine current-code execution gate
  (`make evaluation-retrieval-current`) — both defined precisely in "Real
  and representative corpus strategy," Level 2.
- **Generation evaluation is a separate population and pipeline, verified
  directly, and is not provider-free — and its documentation and its
  immutable evidence live in two distinct places, not one.** The population/
  harness documentation lives under `docs/evaluation/generation/`
  (`README.md` + `populations/grounded-generation-v1.json`), not inside
  `tests/evaluation/benchmarks/`. The immutable, per-run manifests,
  configurations, results and recorded observations — the actual committed
  evidence a verifier needs to check — live separately, under the relevant
  `docs/evaluation/runs/GEN-EXP-*/` directories (verified directly:
  `docs/evaluation/runs/GEN-EXP-0001-grounded-generation-baseline/` and
  `docs/evaluation/runs/GEN-EXP-0002-corrected-grounded-generation-evaluator/`
  — **verified directly to have genuinely different, run-specific
  inventories, not one uniform filename set**: both share `population.json`,
  `config.json`, `result.json`, `run-manifest.json`,
  `application-observations.json`, `evaluation-observations.json`,
  `checksums.sha256` and `report.md`/`report.html`, but GEN-EXP-0001 has
  `checkpoint-observations.json` and no `closure.md`, while GEN-EXP-0002 has
  the differently-named `checkpoint-evaluation-observations.json` *and* a
  `closure.md` GEN-EXP-0001 doesn't have. This is exactly why the verifier
  defined below reads each run's own manifest/checksum-declared inventory
  rather than assuming a single filename set applies to every run.
  `scripts/evaluation/run_generation.py` imports
  `apps/ai/app/generation/factory.py`'s real `build_generator()` (OpenAI)
  and `apps/ai/app/evaluation/openai_answer_evaluator.py`'s
  `OpenAIAnswerEvaluator` directly — it makes real generation calls *and*
  real evaluator calls. `scripts/evaluation/reevaluate_generation.py`
  ("Re-evaluate immutable generation observations without generation calls")
  avoids re-*generating* against a fixed, checksum-pinned historical
  observation (`SOURCE_OBSERVATION_SHA256`), but still imports and calls the
  same real `OpenAIAnswerEvaluator` to (re-)score it — it is not
  provider-free either, only generation-call-free. The existing generation
  unit tests exercise the deterministic evaluation *harness* and structural
  metrics (parsing, aggregation, checksum/lineage integrity); they do not,
  and cannot, prove current live-model semantic quality — that requires an
  actual model call. The committed GEN-EXP evidence under
  `docs/evaluation/runs/GEN-EXP-*/` is historical, already-accepted evidence
  from a past run, not a rerunnable, provider-free proof of the *current*
  provider/model's groundedness or factual quality.
- **A naming-inflation example already exists in the repository**:
  `apps/api/tests/Feature/EndToEndIngestionOrchestrationTest.php` (483
  lines) is named "end-to-end" but is a Laravel Feature test — in-memory
  SQLite, `Http::fake`-free direct Action/HTTP-request construction within
  one PHP process, no real Python service, no real queue, no real Qdrant. It
  is a genuine, valuable test of Laravel's own ingestion-orchestration
  logic across several of its internal classes — exactly the "crosses
  several classes inside one process" pattern this ADR's taxonomy must not
  call end-to-end. **R22-S01 renames the file and its PHP class** — to
  `IngestionOrchestrationFeatureTest.php` — rather than leaving a
  documentation-only reclassification: once R22-S03 introduces a genuine
  multi-service Playwright ingestion journey, leaving both files answering
  to "end-to-end" would still actively mislead a reader, not merely
  historically mislabel one file. The rename touches only the filename and
  class name; the test's logic and assertions are unchanged (see "Session
  allocation").
- **`tests/end-to-end/` already exists at the repository root**, containing
  only a `README.md` that documents the Stage 12.5 cross-service telemetry
  acceptance script (`scripts/telemetry/verify-cross-service.sh`, run via
  `make telemetry-verify`) and explicitly states: *"Browser-driven product
  journeys will be added here when a later stage defines their test runner
  and fixtures."* This ADR is that later stage.
- **No workspace tooling exists at the repository root**: no root
  `package.json`, no `pnpm-workspace.yaml`/`yarn.lock`, no root
  `tsconfig.json`. `apps/web`, `apps/api` and `apps/ai` are three fully
  independent dependency trees. This matters directly for where Playwright
  is installed (see "Playwright project location").
- **No `compose.test.yaml` or Compose `profiles:` block exists.** Tests
  currently run via `docker compose exec -T <service> ...` inside the same
  containers used for local development, against the same Postgres/Qdrant/
  LocalStack — except PHPUnit, which sidesteps this entirely via in-memory
  SQLite. There is no existing precedent for an isolated, identifiable test
  environment topology; this ADR must define one for Playwright specifically
  (see "Environment and orchestration").

## Decision

### 1. Test taxonomy

Fifteen categories, each defined by what it proves, what it deliberately
does not prove, its runner, its normal dependencies, its external-provider
policy, its speed/cost class, its isolation requirement, when a developer
normally runs it, and its likely Phase 23 CI placement (placement only —
this ADR does not design CI).

| Category | Proves | Does not prove | Runner | Deps | Providers | Speed | Isolation | Run when | Likely CI placement |
|---|---|---|---|---|---|---|---|---|---|
| Frontend unit | Pure functions/hooks/utilities in isolation | Rendered DOM, routing, real fetch | Vitest | none | none | ms | none needed | Every save/PR | Fast tier, every push |
| Frontend component | A component renders correct DOM/ARIA for given props/mocked data | Real backend, real routing, real network | Vitest + Testing Library + jsdom | none | none | ms–low-s | none needed | Every save/PR | Fast tier |
| Frontend integration/route | A Next.js route/server component composes correctly against a mocked API boundary | Real Laravel, real browser chrome, real navigation | Vitest + Testing Library | mocked `fetch`/API layer | none | low-s | none needed | Every save/PR | Fast tier |
| Laravel unit | Pure PHP logic (canonicalisation, calculators, value objects) in isolation | Database, HTTP, real Python | PHPUnit `tests/Unit` | none | none | ms | none needed | Every save/PR | Fast tier |
| Laravel feature/API | Laravel's own routes/Actions/Policies/queries against a real (in-memory) database, with the Python boundary faked at the HTTP-client layer where relevant | That Python actually returns the faked shape; that the two languages agree on that shape | PHPUnit `tests/Feature` | in-memory SQLite (`RefreshDatabase`) | `Http::fake()` for Python calls | low-s | per-test DB via `RefreshDatabase` | Every save/PR | Fast tier |
| Python unit | Pure Python logic (canonicalisation, chunkers, calculators) in isolation | Network, filesystem beyond fixtures | Pytest (no marker) | none | none | ms | none needed | Every save/PR | Fast tier |
| Python integration | Python components composed together (ingestion pipeline stages, evaluation metrics) against deterministic fakes | External provider correctness, real Postgres/SQS/S3 | Pytest (no marker, or `integration` marker for the subset needing LocalStack/etc.) | LocalStack where genuinely needed | deterministic `fake.py` adapters | low–mid-s | per-test | Every save/PR | Fast/mid tier |
| Shared HTTP-contract | Laravel's constructed requests/responses and Python's constructed requests/responses both validate against the *same* committed JSON Schema and the *same* committed valid/invalid fixtures | End-to-end behaviour, business correctness beyond shape | PHPUnit + Pytest, run independently against `contracts/http/**` | none beyond the schema files | none | ms–low-s | none needed | Every save/PR; mandatory on any `contracts/` change | Fast tier |
| Shared event-contract | Published/consumed event payloads validate against `contracts/events/**`, including the negative fixtures | Actual queue delivery semantics | PHPUnit + Pytest | none beyond the schema files | none | ms | none needed | Every save/PR; mandatory on any `contracts/` change | Fast tier |
| Infrastructure/configuration | Compose topology, Collector config, Prometheus/Alertmanager rules, migration SQL validate (already covered by e.g. `test-telemetry`, migration preflight) | Application-level correctness | shell/`promtool`/`otelcol validate`/psql preflight, as already established in Phase 20 | Docker services under test | none | low-s–mid-s | container-scoped | Every save/PR; mandatory on relevant config changes | Fast/mid tier |
| Playwright E2E platform | A small set of critical, product-boundary-observable journeys work through the real running stack (browser → Next.js → Laravel → Python → Postgres/SQS/S3/Qdrant), with the OpenAI/Voyage provider boundary and the locally-executed SPLADE sparse-encoder model substituted at the same factory seam | Retrieval/generation *quality*, load capacity, exhaustive state coverage, real SPLADE model loading/inference | Playwright | full `docker compose` stack (`compose.e2e.yaml` override) | deterministic Python-side `fake.py` adapters, selected via factory/settings wiring | mid–high-s per test | dedicated E2E Compose project using the committed override, unique run namespace | On demand locally; required in Phase 23 CI on PR/merge | Slow/required tier |
| Retrieval evaluation | (required, `make evaluation-policy-gate`) historical retrieval-evidence enforced against the committed promoted baseline and policy, real non-zero exit on failure; (required, `make evaluation-retrieval-current`) current retrieval pipeline execution against its own deterministic baseline, from the authored plan/query boundary onward | Individual unit correctness, UI behaviour, generation quality, current live-planner/real-provider semantic quality (`evaluation-run` alone is report generation, not a gate) | `scripts/evaluation/run.py` (`run`+`compare`) + Python evaluation package; `evaluation-retrieval-current` adapts `live_hybrid_retrieval.py` onto deterministic factory selection | compiled `tests/evaluation` corpus; committed baselines under `docs/evaluation/baselines/` | both required checks provider-free; `evaluation-live-hybrid` opt-in live, expected once at R22-GATE closure | mid–high (minutes) | run-scoped, versioned artifacts | On evaluation-relevant change | **Required** at R22-GATE |
| Generation evaluation | (required tier, `make evaluation-generation-verify`) generation-evidence/harness integrity and deterministic structural reproducibility — the population, harness and committed GEN-EXP evidence remain reproducible/untampered — **not** current live-model groundedness/factual quality; (optional tier, `make evaluation-generation-live`) actual live-model generation quality, via a real, bounded `run_generation.py` invocation | Retrieval correctness; that this is the *same* controlled population as retrieval evaluation; general "quality regressions" beyond generation-evidence/harness integrity for the required tier | Python evaluation package (`generation_evaluation.py`, harness/integrity checks provider-free); `run_generation.py`/`OpenAIAnswerEvaluator` for the optional live tier | population/harness docs under `docs/evaluation/generation/`; immutable per-run evidence under `docs/evaluation/runs/GEN-EXP-*/` | harness/integrity: none; optional live tier: real OpenAI generation + evaluator calls, explicitly | integrity checks: low-s; live tier: minutes, tightly bounded | run-scoped | Integrity checks every evaluation-relevant change; live tier manually | Integrity checks (`evaluation-generation-verify`) **required** at R22-GATE; live tier never required |
| Live-provider smoke | Real OpenAI/Voyage credentials, quotas and API shape still work as expected, as an observational check — covers both the retrieval-side (`evaluation-live-hybrid`) and generation-side (live `run_generation.py`) live-invocation cases | Nothing about regression correctness — never gates | dedicated opt-in script/target (`RUN_LIVE_HYBRID_EVALUATION=1`-style pattern, extended per this ADR to generation) | live credentials | real OpenAI/Voyage, explicitly | seconds–low-minutes, tightly bounded | isolated, disposable | Manually, occasionally | Never required; optional scheduled tier only |
| Security regression | Deterministic security boundaries (tenancy, IDOR, upload validation, contract rejection, concealment) at the cheapest layer that can prove them | Universal immunity to any attack; probabilistic model behaviour | Distributed across Laravel Feature, Python unit/integration, shared contract, and a small Playwright slice — see "Security-test allocation" | varies by layer | none, except the evaluation-tier prompt-injection cases | varies by layer | varies by layer | Every save/PR for the deterministic majority | Fast tier for the deterministic majority; gate tier for evaluation cases |

**Preventing naming inflation, as a standing rule, not just a one-time
correction**: a test is "end-to-end" only if it exercises the real running
multi-service stack through a product-observable boundary (browser or an
authorised public/test-safe API), never merely because it crosses several
classes or layers inside one process. `EndToEndIngestionOrchestrationTest.php`
is the concrete example this ADR uses to make that rule unambiguous (see
"Session allocation").

### 2. Playwright as the product-level E2E runner

**Node version: 24, selected explicitly.** Verified directly against current
official documentation: Playwright's supported Node.js line is "latest 22.x,
24.x, or 26.x." Verified directly against this repository:
`apps/web/Dockerfile:3` already declares `ARG NODE_VERSION=24-alpine` as the
web application's own container runtime — Node 24 is not a new choice this
ADR introduces, it is the major already running the product today. Node 24
is a currently-supported LTS line, sits inside Playwright's supported range,
and selecting it for `tests/end-to-end/` as well means the browser-test
package runs the same Node major as the application it is testing, rather
than introducing a second Node major into the repository's toolchain for no
reason connected to either Playwright's or Next.js's actual requirements.
This ADR pins the *major*/runtime contract, not one forever-static patch
version — ordinary lockfile and base-image maintenance carries patch/minor
updates within Node 24 the normal way; this decision does not need revisiting
each time Node 24 receives a patch release, only if Playwright's or the
application's supported-major requirements move past it.

R22-S03 establishes one consistent Node 24 contract shared by `apps/web` and
`tests/end-to-end`, with each piece doing only what it actually does — no
piece is credited with enforcement it cannot actually perform:

- `.node-version` (new, root-level, since repository inspection confirms
  neither it nor `.nvmrc` exists anywhere today, so adding one new file is
  the least duplicative mechanism rather than a second, independently
  hand-maintained version string) is a **developer hint only** — it does not
  claim to be automatically read by every version manager (`nvm`, `fnm` and
  `asdf` do not all default to the same filename), and adopting any specific
  tool's convention on a developer's own machine is left to that developer,
  not mandated here.
- `apps/web/package.json` and `tests/end-to-end/package.json` each gain an
  `engines: {"node": "24.x"}` declaration — this **declares the supported
  contract** in a standard, tool-readable place, but this ADR does not
  overstate what that alone does: ordinary `npm install` only **warns** on
  an `engines` mismatch by default, it does not fail, unless
  `engine-strict` is separately configured. `engines` is documentation of
  intent that other tooling can read, not an enforcement mechanism on its
  own.
- `apps/web/Dockerfile`'s `ARG NODE_VERSION=24-alpine` **fixes the actual
  container runtime** the application (and, once R22-S03 builds it, the
  E2E container path) really executes under — this is the one place a wrong
  Node major cannot silently slip through today, because the image simply
  is Node 24.
- **`make test-e2e` performs its own explicit startup preflight**, run
  before installing or running Playwright, that checks the resolved Node
  major and rejects (fails closed, with a clear message) anything other
  than 24 — this is the actual enforcement point for a developer running
  the suite directly on the host outside the Dockerfile's guarantee; it is
  not left to `engines`' best-effort warning.
- **Phase 23 CI must later enforce the same Node 24 major** wherever it runs
  `make test-e2e` (host runner or container, per "Environment and
  orchestration") — this ADR states that requirement without designing
  Phase 23's CI pipeline itself.

Next.js 16.2.11 targets React 19.2 on the App Router; nothing in
Playwright's browser-automation model depends on framework internals it
would need special support for, and Next.js 16.3 ships its own first-party
Playwright test helper for navigation-performance assertions, confirming
Playwright is an actively supported pairing for this exact framework
generation, not a legacy choice.

**Project location**: `tests/end-to-end/`, exactly the directory the
repository already reserves for this purpose (its `README.md` explicitly
anticipates "browser-driven product journeys" arriving here). Given the
repository has no workspace tooling at the root (no root `package.json`, no
pnpm/yarn workspace), the Playwright project is its own independent Node
package — `tests/end-to-end/package.json` with its own `@playwright/test`
dependency and lockfile — consistent with how `apps/web`, `apps/api` and
`apps/ai` are already three fully independent dependency trees, not a
workspace-aware monorepo tool this repository has never adopted. Introducing
a root workspace manager solely to share Playwright's dependency would be
new tooling architecture this ADR does not authorise; a fourth independent
package following the established pattern is the smaller, consistent choice.

**Scope**: Playwright owns product-level black-box journeys requiring a real
browser — authentication/cookies, route navigation, file upload,
asynchronous status polling, streaming UI, citations and source navigation,
browser-level accessibility semantics, and user-visible tenancy/concealment
outcomes. Playwright's API-request facilities (`request` fixture) may be
used for bounded test *setup*, *polling* and *cleanup* — provisioning a
workspace, polling document status, deleting fixtures after a run — so that
every system-level assertion is not artificially forced through a visible UI
control merely for ceremony, provided the actual behaviour under test is
still exercised through the real boundary it belongs to (see the ingestion
and chat journeys below for where UI assertion is required versus where API
polling is the honest choice).

**No second browser runner.** Cypress is not introduced. Both are capable
tools; Playwright is selected because it already has a documented,
first-party relationship with this exact Next.js generation, supports
multi-browser/multi-context testing natively (useful for the cross-tenant
concealment journeys this ADR requires), and because running two E2E
frameworks side by side would itself be exactly the "competing second
testing architecture" this ADR's brief explicitly prohibits.

### 3. Provider and heavyweight-model adapter boundary

**Renamed deliberately from "deterministic-provider boundary."** "Only
external OpenAI/Voyage calls are substituted" is not quite what the required
suite actually does, and an earlier draft of this ADR said exactly that.
**Normal required E2E and integration tests exercise the real internal
platform** — Next.js, Laravel, the Python application/worker, PostgreSQL,
the real queue transport (SQS via LocalStack), real object storage (S3 via
LocalStack), real Qdrant, and the real signed/versioned internal HTTP and
event contracts — but the deterministic required suite substitutes three
things, not one:

- **OpenAI-backed planning, contextualisation and generation** — genuinely
  external network calls to a paid provider.
- **Voyage embedding and reranking** — likewise genuinely external network
  calls to a paid provider.
- **The locally executed SPLADE sparse encoder** (`apps/ai/app/sparse/fastembed_adapter.py`'s
  `FastEmbedSparseEncoder`, which loads and runs a real `fastembed`
  `SparseTextEmbedding` ONNX model **in-process**, making no network call at
  all) — substituted **not** because it is an external provider, but because
  it is a heavyweight, model-load-dependent computation this ADR requires
  substituted for deterministic, fast E2E execution, the same way the other
  three are substituted for determinism and cost.

This distinction matters for what the required suite can honestly claim:
the real sparse **factory/orchestration boundary** is exercised (the same
`Settings`/factory selection mechanism, the same `Protocol` the real
`FastEmbedSparseEncoder` implements, the same downstream fusion/retrieval
code path) — but **required E2E does not prove real SPLADE model loading or
inference**, because the model is deliberately not run in that tier.

**Corrected claim about existing coverage — the existing Python tests do not
already prove real SPLADE loading either.** An earlier draft of this ADR
said real SPLADE behaviour "remains covered by its own existing Python
unit/integration/configuration tests." Verified directly and wrong:
`apps/ai/tests/test_sparse_encoding.py` exercises `FastEmbedSparseEncoder`
exclusively through an injected `RecordingEngine` test double — every test
in that file constructs `RecordingEngine(...)` and passes it as the
adapter's `engine=` argument; none of them constructs the real
`fastembed.SparseTextEmbedding` class the adapter wraps in production. This
proves the adapter's own logic (profile/lineage handling, index sorting,
input validation, malformed-output rejection) correctly, but it proves
nothing about whether the real model actually loads or produces valid
output. **No test in the repository currently exercises the real model.**

**A bounded, provider-free real-model integration check is added, not
declared infeasible, as its own unambiguously required target** —
`make test-splade-integration`, following this repository's existing
`test-<component>` naming convention (`test-web`, `test-api`, `test-ai`,
`test-telemetry`). Inspection found no genuine obstacle to building it:
`compose.yaml` already defines a `fastembed_cache` named volume, mounted
into both `ai` and `worker`, specifically for caching the downloaded ONNX
model, so a deliberately-provisioned environment already has somewhere for
the pinned model artifact to live without a fresh download on every run.
R22-S03 adds this check, alongside its other deterministic-adapter/factory
and E2E-environment work:

- runs as its own named target, **outside** the ordinary fast unit/
  typecheck/`test-ai` suite — never bundled into a sweep a developer might
  run without realising it needs the pinned model cache present;
- constructs the genuinely configured `FastEmbedSparseEncoder` with the real
  `SparseTextEmbedding` engine — no `RecordingEngine`, no fake;
- encodes a very small, fixed set of safe test strings (no document content,
  no corpus data — a handful of short literal strings committed alongside
  the test);
- verifies the model loads successfully and the returned sparse encodings
  are structurally valid: indices and weights present and correctly typed,
  non-empty output for non-empty input, the same sorted-indices invariant
  the existing adapter unit tests already check structurally, and no
  non-finite (`NaN`/`inf`) or malformed values — **not** brittle exact-
  floating-point-value assertions, since this repository has no existing,
  justified reproducibility contract for exact SPLADE output that would
  make such an assertion meaningful rather than fragile;
- makes no OpenAI, Voyage, or other external-provider call — the only
  "external" resource is the pinned local model artifact itself, and no
  network fetch happens silently during the gate: acquiring the model is an
  explicit, separately documented preparation step (an idempotent
  provisioning command populating `fastembed_cache` with a pinned model
  revision/checksum before `test-splade-integration` ever runs), not
  something the check does on demand;
- **is unambiguously mandatory, not conditionally skippable, at two
  specific points**: R22-S03's own acceptance (it must run successfully
  before R22-S03 is considered complete) and R22-GATE (it must run
  successfully again there — R22-S03-time success is not trusted forward
  without re-verification, and **the final Phase 22 gate cannot pass
  without evidence that `make test-splade-integration` executed
  successfully**). At *both* points, a missing, unpinned, incompatible, or
  unloadable model/cache is an outright **test failure** (non-zero exit) —
  never a skip. Honest skipping (matching this ADR's "safe to skip when
  credentials are unavailable" language elsewhere) is permitted only for
  ordinary, casual developer invocation of this same target outside R22-S03
  acceptance or R22-GATE — it is never permitted at either of those two
  required points, and the target itself fails non-zero whenever the model
  cannot load, inference fails, or a returned encoding violates the
  structural invariants above;
- proves, explicitly and only, that the real, configured SPLADE model
  artifact can load and perform bounded basic inference in the controlled
  environment — **not** corpus-level retrieval quality, which remains the
  evaluation tier's job entirely; deterministic Playwright E2E continues to
  substitute SPLADE for speed/reproducibility regardless of this check's
  existence, and real retrieval quality remains governed separately by
  `make evaluation-live-hybrid`.

**The distinction across all four SPLADE-touching surfaces stays explicit**:
fast sparse unit/adapter tests use `RecordingEngine` (proving adapter
logic); `make test-splade-integration` uses the real model (proving it
loads and produces structurally valid output); deterministic Playwright E2E
substitutes SPLADE with the fake for speed/reproducibility (proving
orchestration, not the model); and live/current real retrieval quality is
assessed separately, by the real-provider evaluation path
(`make evaluation-live-hybrid`), which is the only one of the four that says
anything about actual retrieval quality.

The required suite must never claim that every internal computational
implementation runs unchanged end-to-end when SPLADE is deliberately
substituted — it claims the orchestration path around it is real, not that
the model itself ran.

**The fake-class pattern already exists; the selection mechanism does not,
and this ADR requires it be built rather than assuming it already works.**
`apps/ai/app/{embedding,generation,reranking,sparse}/fake.py` already
preserve the real internal call path (they implement the same `Protocol`
the real adapter implements, so Python's orchestration code, contract
construction, and response typing are exercised exactly as they would be
against the real provider) and already produce stable, reproducible output
(the embedder's SHA-256-derived vector is the existing pattern for
"deterministic but not hand-coded" test data). But — verified directly, see
"Context" — none of the four capability factories (`embedding`,
`reranking`, `generation`, `conversation`'s `build_contextualizer`) currently
has a branch that could select a fake; each unconditionally constructs or
requires its real provider. Retrieval planning has no factory boundary at
all today (`planner_dependency()` constructs `StructuredChatRetrievalPlanner`
directly), and while `FixedRetrievalPlanner` already demonstrates the
deterministic-test-double pattern extends to planning, it is a single-plan
constructor argument, not a scenario-mapping double an E2E environment could
select and configure per fixture question.

This ADR requires **new** fail-closed `Settings`/factory wiring — for all
four provider capabilities plus a new deterministic planner and a
scenario-mapping contextualiser double — allocated explicitly between
R22-S03 and R22-S04 below, not assumed to already exist. The selection
mechanism, once built, must still be the *existing* `Settings`-driven
pattern each capability's `factory.py`/`build_*` function already uses (an
added conditional branch per factory, not a new parallel configuration
system), so the shape of the fix matches this repository's own convention
even though the wiring itself is new.

**Fail-closed test-provider selection.** Provider selection must not be a
runtime switch a request can flip — no production browser control, no public
test-control endpoint, matching this repository's standing practice (ADR-0026's
platform-administrator boundary and the ingestion worker's environment-gated
credentials both already establish "test/administrative capability must not
be reachable from an ordinary request" as this codebase's convention). The
deterministic-fake selection is fixed at process configuration time (the
same `Settings`-driven mechanism that already selects the real adapter),
scoped to environments this ADR and R22-S03 explicitly recognise as test
environments (the `dolved-e2e` Compose identity defined below), and defaults
to the real adapter everywhere else — an unrecognised environment fails
toward the real, credentialed provider path (which then fails its own
credential check) rather than silently toward a fake that could mask a
misconfiguration in a non-test environment. Concretely, this requires the
required deterministic E2E command to **refuse to start if any capability
resolves to a real external-provider adapter** — a startup assertion, not a
per-request check — and the `dolved-e2e` environment must never have real
OpenAI/Voyage credentials mounted into it at all, so a wiring mistake fails
loud (missing/empty credential) rather than quietly succeeding against a
real, billed provider.

**Deterministic outcomes are configuration, not a runtime-mutable
endpoint.** The planner/contextualiser/generator scenario catalogue this ADR
requires (see R22-S03/R22-S04) is a committed mapping — known synthetic
fixture questions/evidence to stable, pre-defined outcomes — loaded at
process startup alongside the rest of `Settings`, the same way the existing
fakes' behaviour is already fixed in code rather than mutable per-request.
It must never be reachable via a browser header, a query parameter, or any
runtime-mutation endpoint; the E2E suite gets deterministic behaviour by
asking known fixture questions the catalogue already maps, not by telling a
running process what to answer.

**What this does not permit**: bypassing Laravel/Python orchestration,
inserting the desired final answer directly into persistence, or replacing
the entire Python service with a fixture server. The fake sits at exactly
the same seam the real adapter occupies — one HTTP/SDK call outward — and
every layer above it (contract construction, canonicalisation, retrieval
planning, evidence threshold policy, answer-part assembly, streaming
projection) runs unmodified and for real.

### 4. Optional live-provider smoke suite

Retained and formalised as its own taxonomy category (table above), not
merged into either the deterministic E2E suite or the evaluation gate.
Requirements, matching the existing `evaluation-live-hybrid` precedent this
ADR generalises: **opt-in** (an explicit environment variable, following the
established `RUN_LIVE_HYBRID_EVALUATION=1` pattern, extended per-capability
as needed); **excluded from the normal required gate and from ordinary local
`make test`** unless deliberately requested via its own target; **protected
by explicit environment/configuration requirements** (real credentials must
be present, or the suite honestly skips — see below); **bounded by strict
call, time and spending limits**, enforced in the test harness itself (a
fixed small call budget per run, a wall-clock timeout, never an
unboundedly-parameterised sweep); **safe to skip** — missing credentials
produce an explicit, honestly-reported skip, never a false pass or a
fabricated result; and **its results are reported and consumed as
live-provider evidence, explicitly labelled as such, never silently treated
as deterministic regression coverage or folded into a pass/fail CI gate**. A
live-provider result must never be hand-copied into a golden expected-answer
fixture — provider output varies run to run by design, and a fixture built
from one live sample would make ordinary deterministic tests
non-reproducible for reasons entirely outside this codebase's control. Phase
23 decides how and when this suite runs in staging/CI; this ADR fixes only
its category and safety boundary.

### 5. Contract-verification strategy

**The existing `contracts/` JSON Schema tree remains the sole source of
truth.** Direct inspection found no concrete reason it cannot remain so:
schemas are already versioned, already `additionalProperties: false`,
already validated on both language sides by mature, widely-used libraries
(`opis/json-schema` in PHP, `jsonschema` in Python), and canonicalisation/
signature vectors already exist as shared, language-neutral fixtures.

**Pact/a contract broker is not introduced.** A broker's value proposition —
discovering, at CI time, when a consumer's expectations of a producer have
drifted, across many independently-deployed services with their own release
cadences — does not fit this repository: there are exactly two producer/
consumer pairs (Laravel↔Python across two capability boundaries: the rc1
retrieval-call contract and the ingestion-worker/deletion HTTP contracts),
both already versioned by hand in one shared repository, both already
validated by schema libraries on both sides, and both already exercised by
the canonicalisation-vector fixtures. A broker would add a new service
dependency, a new coordination protocol, and a second parallel notion of
"contract" (broker-recorded interactions versus the committed schemas)
without solving a problem this repository actually has — schema drift here
is caught by two test suites reading the same file, not by inferring
expectations from recorded traffic between independently-versioned
deployables. This is exactly the "another parallel contract-authority
system" this ADR's brief says must not be introduced without genuine
necessity, and inspection found none.

**Producer/consumer verification principles, made explicit and testable**:

- Producers (Laravel constructing a retrieval-call request; Python
  constructing a generation-stream event; either language constructing an
  ingestion/deletion event) validate or construct only schema-valid output —
  enforced by running the same `opis/json-schema` / `jsonschema` validation
  the consumer would run, in the producer's own test suite, against the
  payload the producer actually emits.
- Consumers reject invalid input fail-closed — already the existing pattern
  (`DocumentIngestionContractValidator.php`, `apps/ai/app/ingestion/contract.py`,
  `apps/ai/app/deletion/contract.py`); R22-S02 extends this coverage to
  every contract surface listed below, not just ingestion/deletion.
- **Both languages run the same committed valid and invalid fixtures** — the
  fixtures live once, under `contracts/**/fixtures/` or equivalent, and both
  PHPUnit and Pytest load and assert against the identical files, so a
  fixture edit is a single, reviewable change both languages' tests
  automatically pick up, rather than two independently-maintained fixture
  sets that could silently diverge.
- Unknown required fields, missing fields, invalid enums, and unsupported
  contract versions are each an explicit negative fixture, on both sides —
  the existing `document-ingestion-requested/fixtures/invalid-*.json` set
  (missing-workspace-id, unknown-field, unsupported-version, zero-byte-size)
  is the pattern; R22-S02 extends an equivalent set to
  `retrieval-call/rc1`'s plan/search/rerank/generation-answer/
  generation-stream-event/conversation-contextualize schemas and to
  `document-deletion-requested`.
- Additional-property policy is read from the schema (`additionalProperties:
  false`, already set repository-wide) rather than assumed from either
  language's default — Laravel and Python each have their own default
  leniency about extra JSON keys, and relying on that default rather than
  the schema's explicit setting is exactly the kind of silent,
  language-specific divergence this ADR's contract tests exist to catch.
- Canonicalisation/signature vectors are the existing shared files
  (`contracts/http/{retrieval-call/rc1,ingestion-worker/v1}/canonicalisation-vectors.json`,
  `.../provenance-vectors.json`) — both languages' signing implementations
  must independently reproduce the same signature for the same vector;
  R22-S02 confirms this coverage is complete for every contract that signs,
  and adds it where a signed contract surface (per the covered-surfaces list
  below) is found to lack it.
- A breaking change cannot be hidden by updating one consumer's private copy
  of a fixture — because there is exactly one committed fixture set per
  contract, consumed by both languages' test suites directly from
  `contracts/`, not copied into `apps/api`/`apps/ai`-local fixture
  directories that could drift.
- Schema duplication in language-specific hand-written types (Laravel's
  typed DTOs, Python's Pydantic models) is controlled by requiring each such
  type's own unit test to validate a representative payload against both the
  hand-written type *and* the JSON Schema, so a divergence between "what the
  hand-written type accepts" and "what the schema accepts" fails a test
  rather than surfacing only in production.
- Backward-compatibility requirements are explicit per contract version, not
  assumed globally — the existing `rc1`/`v1`/`v2` version segments already
  establish this; R22-S02 documents, per contract, which versions must
  currently be accepted and which have been retired, rather than leaving
  "how many versions back must we support" as convention.

**Covered contract surfaces — corrected against a complete, verified
inventory, not assumed complete.** An earlier draft of this ADR implied
"signed HTTP requests/responses (ingestion-worker, deletion, retrieval-call)"
already had shared JSON Schemas across the board. Verified directly against
`contracts/http/`: `retrieval-call/rc1` genuinely has shared schemas for
every operation it exposes. `contracts/http/ingestion-worker/v1/` contains
only `canonicalisation.json`, `canonicalisation-vectors.json`,
`provenance-vectors.json` and a `README.md` — **no request/response JSON
Schema files at all** for any of its operations. Deletion's request-side
event (`contracts/events/document-deletion-requested/v1.schema.json`) has a
shared schema, but its worker-callback (claim/complete/fail) surfaces do
not. This ADR corrects course: it authorises R22-S02 to **add** versioned
JSON Schemas for the current, already-implemented wire behaviour of every
in-scope operation below — documenting and testing existing behaviour, not
redesigning it. If implementation and a prior accepted ADR (ADR-0009,
ADR-0015, ADR-0016) disagree about what the wire shape should be, R22-S02
stops and reports the contradiction rather than picking a new shape while
"just adding tests."

**Complete operation inventory, verified directly against
`apps/api/routes/api.php`** — nine ingestion-worker operations (not eight;
this ADR corrects that count against the real route table), each its own
signed request/response pair, Laravel-consumed-and-produced, Python-worker-
consumed-and-produced in the claim/renew/submit/seal/resume/authorise
directions and Laravel-consumed in the complete/fail/cancel directions:
`ingestion.claim`, `ingestion.lease.renew`, `ingestion.chunks.submit`,
`ingestion.chunks.seal`, `ingestion.attempt.resume`,
`ingestion.publication.authorise`, `ingestion.complete`, `ingestion.fail`,
`ingestion.attempt.cancel` — and three deletion-worker operations,
symmetrically: `document.deletion.claim`, `document.deletion.complete`,
`document.deletion.fail`. R22-S02 adds a versioned request/response schema
for each of these twelve operations (naming/versioning to follow the
existing `ingestion-worker/v1`/`document-deletion-requested` convention),
each with canonical valid fixtures and the required invalid cases (unknown
required fields, missing fields, invalid enums, unsupported versions),
validated identically by both Laravel and Python against the same committed
fixtures, with unsupported versions and additional properties failing
closed exactly as the rest of this section requires. Signed operations
(all twelve are) retain and extend the existing shared canonicalisation/
signature vector files rather than introducing a second signing-vector
mechanism.

Also covered, already schema-backed and unchanged in scope: retrieval/
planning (`plan-v1`, `plan-response-v2`, `search-v1`); contextualisation
(`conversation-contextualize-v1`/`-response-v1`); generation answers
(`generation-answer-v1`/`-response-v1`); generation streaming events
(`generation-stream-event-v1`); the ingestion request event
(`document-ingestion-requested`); the deletion request event
(`document-deletion-requested`); supported/unsupported contract versions
(the existing `unsupported-version` fixture pattern, generalised);
canonicalisation/signing vectors (`ingestion-worker/v1`, `retrieval-call/rc1`).
If any wire surface in this repository is deliberately **not** made a
shared JSON contract (for example an internal-only shape neither language
needs to independently validate), R22-S02 names it explicitly and states
why — "every contract" is not left as an ambiguous, self-reported claim.

**Generated types: not warranted, on inspection.** Both languages already
have hand-written typed models (Laravel's typed request/response classes and
Actions; Python's Pydantic models) that are independently readable, already
IDE-supported, and already exercised by the language's own test suite. A
code-generation pipeline (e.g. `openapi-generator`/`quicktype`-style
JSON-Schema-to-language-type generation) would add a build-time dependency,
a generated-output review burden, and a translation layer between "what the
schema says" and "what the generator decided that means in each language's
type system" — solving a problem (types drifting from the schema) that the
principle above (validate a representative payload against both the
hand-written type and the schema, in a test) already solves more directly,
without new tooling. This ADR selects the smallest reliable mechanism —
schema-validated hand-written types, cross-checked by tests — over
generation machinery adopted because it is a common pattern elsewhere, not
because inspection found this repository needs it.

### 6. R22-S03 — Ingestion E2E boundary

A compact, deterministic journey through the real internal services (real
Laravel, real Python worker, real Postgres, real SQS/LocalStack, real S3/
LocalStack, real Qdrant), proving exactly the eleven numbered steps the
brief specifies, through product-observable boundaries. **Embedding is not
the only substituted seam here.** Ingestion itself needs only a
deterministic embedder, but step 9's real retrievability assertion — the
evidence must actually come back from a query, not merely have been written
to Qdrant — crosses the retrieval-planning and reranking boundary too, so
this journey's substituted profile is the embedder, the locally-executed
sparse encoder (substituted for deterministic, fast execution, not because
it's an external provider — see "Provider and heavyweight-model adapter
boundary"), the retrieval planner, and the reranker — the four capabilities
R22-S03 itself must wire selection for (see "Session allocation").
Generation/contextualisation remain on the real (unselected) path in this
journey, since nothing in the eleven steps below exercises them.

1. A user/workspace is provisioned through the environment-guarded E2E
   bootstrap command, `php artisan e2e:bootstrap` (see "E2E user/workspace
   bootstrap boundary," below) — not a direct database insert, and not a
   public HTTP endpoint, since no such workspace-creation endpoint exists
   (verified directly: no `POST /workspaces` route is defined anywhere in
   `apps/api/routes/api.php`). The bootstrap command creates the synthetic
   user and workspace through real application invariants (`CreateWorkspace`,
   the real owner-membership action); the test then authenticates through
   the real browser login flow using that bootstrapped account — login
   itself is never bypassed.
2. A representative document is uploaded through the real upload workflow
   (presigned upload, then the real "upload complete" notification) —
   through the browser for the one journey that needs UI proof, via API for
   setup-only repeats.
3. The ingestion event is published through the real transactional outbox
   and the real SQS publisher (`make publish-ingestion`'s underlying
   mechanism, exercised as a live background process, not invoked
   out-of-band by the test).
4. The Python worker claims and processes it — the real `worker` container,
   unmodified, consuming from the real queue.
5. Parsing and chunking run for real; embedding runs against the
   deterministic embedder (and deterministic sparse encoder, where hybrid
   retrieval needs one for stable, fast execution) — the only substitutions
   in the ingestion pipeline itself.
6. Vectors are written to the real Qdrant service.
7. Laravel receives the authoritative completion acknowledgement over the
   real signed ingestion-worker HTTP contract.
8. The document reaches its ready/searchable state, observed by polling the
   real, authorised document-status API/UI (bounded deadline, not a fixed
   sleep — see "Flake, timeout and retry policy").
9. **Its evidence can actually be retrieved** — the test performs (or
   triggers, via the real retrieval boundary) a query that must surface the
   ingested document's content, proving retrievability rather than merely
   that internal ingestion methods were invoked.
10. A representative failure scenario becomes an observable, terminal, typed
    failure state through the same status boundary — not a raw exception,
    not a silently stuck document. This uses a genuinely
    corrupt/unsupported fixture document exercising the real parsing/
    extraction failure path, not request-time mutation of the embedding
    fake's `failure` injection point: a real bad-input fixture proves the
    actual failure path a user's malformed upload would hit, while the
    fake's injected-failure capability remains available and valuable for
    Python integration tests (see the taxonomy) without needing to be
    wired into this E2E journey's control surface.
11. A second, independently-provisioned workspace cannot retrieve the first
    workspace's document — proven through the same retrieval boundary as
    step 9, from the second workspace's authenticated context.

**Assertion boundary**: black-box, product-observable outcomes ("ready and
retrievable," "a second workspace gets no result/a concealed response") are
preferred throughout. Narrow infrastructure inspection (e.g. confirming a
specific Qdrant collection point-count, or a specific outbox row's published
state) is permitted only where no product boundary can honestly expose the
thing being verified — for example, confirming a *provisional* vector was
never returned before publication is a real ADR-0016 invariant with no
clean product-facing signal — and each such inspection in the implemented
suite must carry a comment explaining why the product boundary was
insufficient, so a future reader can tell a deliberate exception from
scope-creep.

**E2E user/workspace bootstrap boundary.** An earlier draft of this ADR
claimed Playwright could provision a workspace "through the real
registration/workspace API." Verified directly and corrected: registration
(`POST /api/auth/register`) is real and exists, but there is no public
`POST /workspaces` route anywhere in `apps/api/routes/api.php` — every
workspace-scoped route requires an already-verified user who already holds
membership, and workspace creation today happens only through
`App\Actions\Workspaces\CreateWorkspace`, invoked from a non-HTTP context
(matching ADR-0006's "workspace provisioning is currently platform-admin
controlled" position). This ADR does **not** create a public
workspace-creation endpoint for testing — that would be new, unauthorised
production surface area introduced solely to make a test easier, exactly
the kind of shortcut "What this does not permit" already rejects for the
provider seam.

The selected mechanism is an **environment-guarded Laravel command**,
`php artisan e2e:bootstrap` (the exact name may follow whatever command-
naming convention R22-S03 finds already established; the boundary below is
what's fixed, not the literal string). It must:

- run only when `APP_ENV=e2e`, **and additionally** require the dedicated
  `dolved-e2e` environment/database/resource marker (see "Environment and
  orchestration") — two independent checks, not one, so neither a stray
  `APP_ENV` value nor a stray database name alone is sufficient to unlock
  it;
- refuse execution outright in local, development, staging or production
  identities — this is not a command with a "test mode flag," it is a
  command that does not run at all outside the one recognised identity;
- accept a validated, unique E2E run/scenario identity as input, so
  repeated invocations for different scenarios never collide;
- create a synthetic **verified** user through existing application/domain
  invariants (the same user-creation and email-verification state
  machinery the product already has), not a hand-crafted row that skips
  invariants a real user would have to satisfy;
- create the workspace through the real `CreateWorkspace` action, and
  establish the real owner membership through that same action — never raw
  SQL, never a direct Eloquent-model fixture insert that bypasses the
  action's invariants;
- be safely **repeatable** for both the primary and secondary tenants
  cross-tenant isolation tests need — invoked twice (or with an equally
  explicit bounded mode covering two identities in one invocation) to
  produce two independent users/workspaces, never one shared fixture
  tenant reused across scenarios;
- return only bounded, machine-readable setup output: user public ID,
  email, and workspace public ID — nothing beyond what a test actually
  needs to proceed;
- obtain the synthetic account's password through the dedicated E2E
  process environment (an environment variable already present in the
  `dolved-e2e` identity), never printed to stdout and never passed as a
  command-line argument where it could leak into shell history or process
  listings;
- never expose provider credentials, HMAC secrets, or any production data —
  consistent with "the `dolved-e2e` environment must never have real
  OpenAI/Voyage credentials mounted into it at all";
- be invoked through the isolated E2E Compose API container, by
  Playwright's global setup — never over HTTP, never from the browser;
  it is a non-browser, non-HTTP test-support mechanism, structurally
  incapable of being reached the way a public endpoint could be.

**Login itself remains genuinely real.** Playwright authenticates through
the real browser login flow using the bootstrapped synthetic account's
credentials — the bootstrap command does not mint a session cookie, does
not call an internal "log this user in" helper, and does not bypass the
login route in any way. Registration and email-verification themselves may
remain covered at the cheaper Laravel feature/component test layers unless
a dedicated browser scenario for that specific flow is independently
justified — the bootstrap command's existence does not imply registration
no longer needs its own coverage, it means E2E scenarios that need an
*already-provisioned* account don't have to re-prove registration every
time.

**Tests required** (R22-S03): the command succeeds only under the exact
E2E identity; it refuses every non-E2E environment (each of local/
development/staging/production exercised as its own negative case); it
creates workspace ownership through the real `CreateWorkspace` action
(asserted the same way any other Feature test would assert real action
invocation, not merely that a row appeared); it does not create a public
HTTP workspace-provisioning surface (a negative assertion that no such
route exists, so a future accidental addition would be caught); and
duplicate/re-run behaviour for the same run/scenario identity is
deterministic and safe (neither a silent no-op that hides a real problem
nor an unhandled uniqueness violation).

**Product provisioning remains a separate, explicitly un-decided
question.** This command solves automated-test setup only. It does not
decide, and this ADR does not decide, how a genuine customer creates their
first workspace — the absence of a user-facing first-workspace creation/
onboarding flow is a real, separate product/provisioning gap that must be
addressed before production readiness, not something this ADR quietly
closes by building a test-only shortcut. ADR-0029 does not authorise
exposing `e2e:bootstrap` itself, or adding any public endpoint modelled on
it, to solve that gap. The later product decision — who may create a new
tenant, what invitation/onboarding behaviour looks like, what abuse
controls apply, how billing/commercial provisioning interacts with it, and
how initial ownership is established for a self-service tenant — belongs to
a Phase 23 readiness handoff or an earlier, separately approved product
decision, not to hidden Phase 22 test-infrastructure scope.

### 7. R22-S04 — Chat E2E boundary

A compact authenticated journey through the real browser, real authenticated
routes (per ADR-0027's route hierarchy), a document ingested through the
real R22-S03 pipeline (not a separately-mocked fixture), real Laravel
retrieval/orchestration, real Python planning/retrieval/generation
orchestration, real SSE streaming transport, and R22-S03's deterministic
embedding/sparse/planner/reranker profile **plus the two capabilities this
session completes the deterministic profile with — contextualisation and
generation** (the `build_contextualizer()`/`build_generator()` factory
wiring and scenario-catalogue work this ADR allocates to R22-S04, since
R22-S03's ingestion journey never exercises either), proving the ten
numbered points the brief specifies:

1. The user authenticates through the real login flow.
2. A representative tenant document becomes searchable (reusing the R22-S03
   pipeline, not a shortcut).
3. A relevant question retrieves the expected evidence — asserted through
   the citation identity the answer actually surfaces, not an internal
   retrieval-plan inspection.
4. Generation runs through the real Laravel↔Python orchestration boundary,
   with the deterministic generation fake producing stable prose *and* a
   stable, real `answer_parts[]`/citation structure — the fake returns
   realistic content, it does not shortcut the boundary that assembles it.
5. Streamed progress/answer-completion events reach the browser over the
   real SSE transport (ADR-0024's resumable projection), asserted via the
   UI's actual progress/streaming presentation.
6. The final answer contains the expected grounded content (a stable
   substring/structure the deterministic fake is configured to produce).
7. Citations resolve to the expected `EvidenceSnapshot`/document-source
   behaviour — including, per ADR-0027's citation presentation-contract
   extension, that "View source" only appears when a real destination
   exists and correctly navigates to the document-detail route.
8. Another tenant cannot retrieve, deep-link to, or infer the existence of
   the document or conversation — a second authenticated browser context
   (Playwright's native multi-context support) attempts the same
   deep-linked URLs and receives the platform's real concealment behaviour
   (ADR-0006's `404`-not-`403` discipline, and its extension by ADR-0027 to
   the conversation/document route segments).
9. Retry/reconnect behaviour is exercised deterministically — a deliberate,
   test-controlled connection interruption (not a flaky real-network
   condition) proves the resumable-SSE reconnect path.
10. At least one of clarification, no-answer, timeout/retraction or failure
    is covered **at this layer specifically because it depends on the full
    streaming/orchestration path being real** — the deterministic generation
    fake is configured to produce that specific outcome for one scenario;
    every other typed state in ADR-0024's full state machine is proven at
    the Python/Laravel unit or integration layer instead, not expanded into
    additional slow E2E cases, per the taxonomy's explicit non-goal of
    "exhaustive state coverage" for this category.

The deterministic adapter's stable expected prose does not make this test
trivial: what it proves is the real evidence identity, real citation
resolution, real orchestration hand-off, and real streaming transport — the
prose is a fixed input specifically so the *assertions* can be exact without
the test becoming a retrieval/generation-quality check, which belongs in
evaluation (Level 2, below), not here.

### 8. Real and representative corpus strategy — three explicit levels

**Level 1 — compact representative E2E corpus (R22-S03/S04).** A small,
versioned, synthetic-or-safely-licensed corpus purpose-built to exercise the
full workflow: relevant vs. irrelevant evidence, multiple documents, a
version/temporal distinction where the product supports one, grounded
citations, a no-answer/insufficient-evidence case, tenant isolation, and a
small representative set of supported file formats — without multiplying
the E2E matrix per format. This corpus is **not** a subset carved out of
`tests/evaluation/benchmarks/dolved-care-engineering/`: that corpus is
versioned, curated and reviewed specifically for retrieval/generation
*quality measurement* (its `reviews/`, `lineage/`, and calibration
machinery exist for that purpose), and coupling the E2E suite to it would
mean every future evaluation-corpus revision risks silently breaking E2E
tests that were never asking a quality question in the first place. A
small, purpose-built, separately-versioned E2E fixture set under
`tests/end-to-end/fixtures/` (lineage: authored directly for this ADR,
distinct from the evaluation corpus, explicitly documented as such) is the
clearer choice, matching the brief's "if a separate mini-corpus is clearer,
define its lineage and purpose explicitly" instruction.

**Level 2 — the existing engineering evaluation population, with retrieval
and generation kept explicitly distinct (Phase 22 gate).** `tests/evaluation/`'s
existing retrieval machinery — the compiled corpus, the versioned policy at
`tests/evaluation/policies/v1/policy.json`, `scripts/evaluation/run.py`, and
the already-committed promoted baseline
(`docs/evaluation/baselines/$(EVALUATION_CORPUS_VERSION)/{experiment-result.json,baseline-promotion.json}`)
— already provides every piece a real gate needs; what's missing, verified
directly, is a command that actually chains them into one enforced pass/
fail. **Three required, provider-free retrieval commands, not one, and none
of them alone is a gate by itself:**

- **`make evaluation-run`** — **report generation, explicitly not a gate**
  (see "Context" for the corrected finding: it never calls `assess_gate()`
  and never exits non-zero). It replays the committed historical
  observations (`offline-baseline.json`) through the harness and writes
  `result.json`. Kept, unchanged, purely for its existing role — producing
  the candidate report the next command consumes — and this ADR is explicit
  that generating that report is not, by itself, proof of anything passing.
- **`make evaluation-policy-gate` (new, required)** — the actual historical-
  evidence gate, defined precisely just below.
- **`make evaluation-retrieval-current` (new, required)** — the actual
  current-code execution gate, defined precisely just below, with its own
  separate deterministic baseline.

**`make evaluation-policy-gate` — the exact historical policy-enforcement
contract, built from existing CLI machinery already present in `run.py`,
not new evaluation machinery.** Verified directly: `run.py`'s `compare`
subcommand already calls `verify_baseline_identity()` and `assess_gate()`
and already produces the right `passed`/`failures` values — it just never
propagates them to the process exit code, and nothing today chains `run`
then `compare` as one command. `make evaluation-policy-gate`:

- **Inputs**: `tests/evaluation/corpus/$(EVALUATION_CORPUS_VERSION)/corpus.json`,
  `tests/evaluation/policies/v1/policy.json`,
  `tests/evaluation/observations/$(EVALUATION_CORPUS_VERSION)/offline-baseline.json`
  (fed to `run` to produce the candidate), and the already-committed
  promoted baseline pair,
  `docs/evaluation/baselines/$(EVALUATION_CORPUS_VERSION)/experiment-result.json`
  and `.../baseline-promotion.json` (fed to `compare` as `--baseline`/
  `--promotion`).
- **Mechanism**: runs `run.py run` to produce a candidate `result.json` from
  the committed observations (identical operation to today's
  `evaluation-run`), then runs `run.py compare` with that candidate against
  the committed promoted baseline and policy.
- **Outputs**: the candidate `result.json` and the comparison report, both
  written to the existing ephemeral output directory
  (`/tmp/rag-platform-evaluation`, matching `evaluation-run`'s current
  convention) — not committed, since this is a gate check, not a promotion.
- **Successful exit condition**: `assess_gate()` returns `passed = True`
  (empty `failures`) — exit `0`.
- **Failure exit condition**: `assess_gate()` returns `passed = False` — the
  new required command **explicitly propagates this to a non-zero process
  exit**, printing the `failures` tuple. This is the one small, stated code
  change this ADR requires: `run.py`'s `compare` subcommand gains an
  explicit `sys.exit(1)` when `not passed`, since neither `run` nor
  `compare` does this today — a one-line addition to an existing,
  already-computed value, not new gate logic.
- **Provider-call boundary**: zero, and more strictly provider-free than
  `evaluation-retrieval-current` — this command touches only already-
  committed JSON (observations, corpus, baseline, promotion, policy); it
  runs no embedder, sparse encoder, planner or reranker at all. It assesses
  committed observations and historical evidence; it does not execute the
  current retrieval implementation — that remains
  `evaluation-retrieval-current`'s job specifically.
- **Session allocation**: **R22-S02** — the same "wrap existing machinery
  into one required, enforced command" character of work R22-S02 already
  owns for `evaluation-generation-verify`, plus the one-line `run.py` change
  above.

**`make evaluation-retrieval-current` — reclassified as a deterministic
current-pipeline/orchestration regression gate against its own baseline,
never the real-provider baseline.** The deterministic embedder, sparse
encoder and reranker produce synthetic/hash-derived values with no
numerical relationship to real Voyage/SPLADE/reranking output — comparing
their metrics against `docs/evaluation/baselines/$(EVALUATION_CORPUS_VERSION)/experiment-result.json`
(the real-provider-produced baseline) would be a meaningless comparison
between two unrelated numeric spaces, not a regression signal. This command
therefore gets its **own, separate, reviewed, versioned, lineage-bound
deterministic baseline** — e.g.
`docs/evaluation/baselines/deterministic-v1/{experiment-result.json,baseline-promotion.json,checksums.sha256}`,
created and governed by exactly the same human-reviewed process already
established for the real-provider baseline (`promote_baseline`,
`record_gate_decision`, a committed `manual-gate.json`): a person runs
`evaluation-retrieval-current` once, inspects the resulting metrics and
report, and explicitly promotes them as the new deterministic baseline —
**the command never regenerates or accepts its own baseline automatically**.

**Corpus/policy identity alone is not enough to know whether a candidate is
comparable with the promoted deterministic baseline, and this ADR does not
claim `verify_baseline_identity()` already covers this.** Verified
directly: `verify_baseline_identity(candidate, promotion)` compares only
`(corpus_version, corpus_digest, policy_version, policy_digest)`. Nothing
in it binds the promoted baseline to the deterministic *machinery and
configuration* that actually produced it — a candidate run against a
changed deterministic embedder, a changed authored-plan catalogue, or a
changed retrieval configuration would pass that check today while comparing
metrics that no longer mean the same thing. This ADR closes that gap with a
new, explicitly-scoped identity, extending existing governance machinery
rather than replacing it or inventing an unrelated one.

**The deterministic execution-profile manifest and digest.** A small,
deliberately narrow JSON object — never a digest of the whole repository —
built almost entirely from fields `ExperimentLineage` (`apps/ai/app/evaluation/models.py`)
already carries, plus two new per-capability fingerprints following the
exact pattern `retrieval/models.py`'s `PlannerLineage` and
`FixedRetrievalPlanner.lineage()` already establish
(`{provider, model, contract_schema_version, prompt_version,
adapter_version}` → `content_digest(...)`, the same `SHA-256(canonical_json(...))`
helper `apps/ai/app/evaluation/canonical.py` already provides for corpus/
policy digests):

- `embedding_profile_fingerprint` — already an `ExperimentLineage` field;
  reused as-is, computed by whichever embedder actually ran (the
  deterministic fake's own fingerprint scheme when it's selected, so a
  swap back to the real adapter is itself a fingerprint change);
- `sparse_profile_fingerprint` — **new field**, same
  `{provider, model, adapter_version}` → `content_digest(...)` pattern,
  identifying the deterministic sparse encoder's implementation/version/
  configuration;
- `reranker_profile_fingerprint` — **new field**, same pattern, for the
  deterministic reranker;
- `plan_catalogue_checksum` — **new field**, `content_digest()` of the
  committed authored-plan/scenario-catalogue file R22-S03 introduces (the
  question-keyed mapping the deterministic planner reads), so an edit to
  which fixture questions map to which plans is itself a profile change;
- `retrieval_configuration` — already an `ExperimentLineage` field
  (eligibility, candidate-selection, limits, fusion and reranking
  configuration); reused as-is;
- `harness_version` — already an `ExperimentLineage` field; reused as-is,
  covering the evaluation harness identity corpus/policy identity doesn't
  already conclusively pin;
- `repository_commit` — already an `ExperimentLineage` field and retained
  unchanged as provenance, but deliberately **excluded** from the
  comparability digest: the deterministic baseline created during R22-S03
  must remain comparable after unrelated R22-S04/R22-S05 commits. Behaviour-
  relevant invalidation comes from the component/profile, catalogue,
  configuration and harness identities above, not the whole-repository commit;
- schema/contract versions that materially affect the evaluated pipeline
  are covered implicitly, not as a separate top-level field: each new
  per-capability fingerprint above already includes its own
  `contract_schema_version`, following `PlannerLineage`'s existing pattern,
  so a contract-version change is itself a fingerprint change without a
  duplicate top-level field.

The manifest's fields, canonicalised and hashed the same way
(`content_digest(manifest)`), produce one `deterministic_profile_digest` —
this is the single value compared below. Generation is mechanical, not a
new authoring burden: every field already exists on the `ExperimentResult`
the gate command produces, or is a `content_digest()` of a file R22-S03
already commits: no hand-maintained manifest to keep in sync.

**`BaselinePromotion` is extended, for the deterministic lineage only, to
bind the promoted baseline to this profile — not just to a future
candidate.** The real-provider baseline's existing `BaselinePromotion`
record is untouched by this ADR. A new, deterministic-specific promotion
shape (extending `BaselinePromotion` with a `deterministic_profile_digest`
field, used only under `docs/evaluation/baselines/deterministic-*/`) records
the profile digest *at promotion time*, alongside the existing corpus/
policy identity — so `baseline-promotion.json` for the deterministic
lineage binds the promoted baseline result to its experiment identity,
corpus, policy, **and** deterministic execution profile together, not to
corpus/policy alone.

**The required deterministic current-retrieval gate performs three checks,
in this order, and a failure at any step stops before the next**:

1. **Promoted baseline result ↔ promotion record.** The baseline
   `experiment-result.json` named by `baseline-promotion.json` exists; its
   `experiment_id` matches the promotion's; its corpus/policy identity
   matches the promotion's (the same fields `verify_baseline_identity()`
   already checks, applied here to the *baseline itself*, which nothing
   checks today); its recorded `deterministic_profile_digest` matches a
   digest freshly recomputed from the baseline result's own lineage (so the
   committed baseline file can't silently drift from what it was promoted
   as); and the baseline directory's own `checksums.sha256` (added for this
   new baseline, mirroring the existing GEN-EXP run-directory pattern)
   validates.
2. **Candidate corpus and policy ↔ promotion record.** Exactly today's
   `verify_baseline_identity(candidate, promotion)` — unchanged, reused
   as-is. A mismatch fails closed.
3. **Candidate execution profile ↔ promoted deterministic baseline
   profile.** The candidate's own freshly-computed
   `deterministic_profile_digest` (from the run that just executed) must
   equal the promoted baseline's recorded digest. **A mismatch means the
   baseline is structurally stale, and the gate fails here — before
   `assess_gate()`'s metric/regression comparison ever runs**, so a stale
   profile is never mistaken for either a passing or a failing metric
   comparison; it is its own distinct, explicitly reported failure mode.

Only if all three checks pass does the gate proceed to `assess_gate()`'s
existing regression-delta comparison against the promoted baseline's
metrics.

**Stale-baseline and promotion behaviour, stated explicitly**: a stale or
mismatched profile is never treated as a regression pass, and never
triggers an automatic baseline refresh — the gate simply fails, honestly.
The command never regenerates, promotes, or approves its own baseline under
any circumstance. An intentional retrieval-behaviour or configuration
change (a planner, fusion, eligibility, reranking, or authored-plan-
catalogue change) requires a fresh candidate run, human review of that
run's report, and an explicit new baseline promotion — exactly the same
human-reviewed `promote_baseline`/`record_gate_decision` process used to
create the baseline in the first place, never a side effect of running the
gate. The old, now-superseded baseline remains immutable historical
evidence — it is not deleted, only no longer the currently-promoted one.
Precisely, the command:

- executes the current retrieval implementation from the authored plan/
  query boundary through **deterministic embedding, deterministic sparse
  encoding, real Qdrant search, real eligibility resolution, real fusion,
  and deterministic reranking**, then evaluates through the same harness;
- compares against its own deterministic baseline via the same
  `compare`/`assess_gate` mechanism `evaluation-policy-gate` uses — never
  against the real-provider baseline;
- **proves**: deterministic orchestration correctness and behavioural
  stability of the current retrieval pipeline, from the authored plan/query
  boundary onward;
- **does not prove**: current real-model retrieval quality, live-planner
  semantic quality, or any provider's actual quality — none of those are
  measurable from synthetic/hash-derived inputs, and this ADR does not
  claim otherwise anywhere.
- **Session allocation**: **R22-S03**, unchanged from the prior draft —
  direct inspection found no reason to move it; R22-S03 already owns the
  deterministic factory wiring and isolated evaluation infrastructure this
  command's own execution depends on, and creating/reviewing its
  first deterministic baseline is a natural extension of standing that
  environment up, not separately-justified new-session scope.

**Where real retrieval-quality evidence actually comes from, and when it's
expected.** `make evaluation-live-hybrid` remains, unchanged, the mechanism
that generates current real-provider retrieval-quality evidence — real
Voyage embedding, real reranking, evaluated against the real-provider
baseline and the same evaluation population. It is **not** promoted to a
required, gating check by this correction, and paid live-provider execution
is never made part of every normal local test run — that would contradict
the deterministic-by-default requirement this whole ADR is built around.
Instead, this ADR states one controlled milestone at which fresh live
retrieval-quality evidence is *expected*, deliberately, not merely
permitted: **at R22-GATE/Phase 22 closure**, where credentials and a
bounded budget are available by design for that one occasion, a live
`evaluation-live-hybrid` run is performed and its result recorded as
observational evidence (per "Optional live-provider smoke suite"'s existing
rules — labelled evidence, never a required pass). If credentials or budget
are genuinely unavailable at that moment, the honest outcome is an explicit,
reported skip, and the requirement carries forward as an explicit Phase 23
staging-readiness item — never silently dropped, and never treated as
satisfied by the deterministic gates above, which this ADR is explicit
never establish real retrieval quality on their own.

**Generation evaluation is related governance, not the same controlled
population, and is not provider-free — this ADR does not claim it gates
Phase 22 the same way.** Verified directly (see "Context"): the generation
population/harness documentation lives separately under
`docs/evaluation/generation/`, with its immutable per-run evidence under
`docs/evaluation/runs/GEN-EXP-*/`;
`scripts/evaluation/run_generation.py` makes real generation calls and real
`OpenAIAnswerEvaluator` calls; `scripts/evaluation/reevaluate_generation.py`
avoids new *generation* calls but still calls the real evaluator against a
fixed historical observation. Groundedness, citation correctness,
unsupported-claim detection, and clarification/no-answer *generation*
correctness are measured by this pipeline, but measuring them **for the
current live model** is inherently a live-provider operation, not a
provider-free regression check this ADR can require on every ordinary Phase
22 completion.

**`make evaluation-retrieval-current` — the exact current-code, provider-free
retrieval gate.** Selected as the smallest buildable mechanism after
inspecting the existing evaluation runner, the live-hybrid runner, the
engineering corpus, the planner-expectations fixture, and R22-S03's
already-planned deterministic adapter work — not an invented, unrelated
framework. `apps/ai/app/evaluation/live_hybrid_retrieval.py` already does
almost exactly the right thing: it builds real candidate funnels by calling
the real embedder, running real Qdrant search, and running a real reranker,
then evaluates the result through the same `RetrievalEvaluationHarness` used
elsewhere — the only reason it isn't already this gate is that it hardcodes
`create_embedder()` (the real Voyage factory) and constructs `VoyageReranker`
directly rather than going through a settings-selectable factory, and it is
wired to the live/paid path (`evaluation-live-hybrid`) rather than a
provider-free one. `make evaluation-retrieval-current` is this same harness,
adapted rather than reinvented: the same candidate-funnel/Qdrant-search/
evaluate-through-the-harness shape, with its provider calls now going
through `create_embedder()`/`create_reranker()`/the sparse factory the same
way the rest of the codebase does, so it picks up whichever adapter the
`dolved-e2e`/evaluation-current profile's settings select. Concretely, it:

- executes the **current retrieval implementation** — real embedding
  request construction, real sparse encoding, real fusion, real eligibility
  resolution, real Qdrant search, real reranking — rather than replaying
  committed observations;
- runs against the **approved versioned engineering evaluation population**
  (`tests/evaluation/benchmarks/dolved-care-engineering/`), the same
  population `evaluation-live-hybrid` already uses;
- runs against **isolated test infrastructure** (the `dolved-e2e`/evaluation
  Qdrant instance and configuration, never a developer's ordinary dev data);
- uses **deterministic, provider-free planning inputs** — the same
  authored-plan/query pattern `live_hybrid_retrieval.py` already uses (each
  evaluation case already carries its expected retrieval queries/plan in the
  corpus), not a live call to `StructuredChatRetrievalPlanner`. **This is a
  precise, bounded claim, not a broader one**: a question-keyed authored
  plan makes retrieval execution reproducible and proves the current
  retrieval pipeline *from the authored plan/query boundary onward* — it
  does not prove the semantic quality of the current live OpenAI planner
  itself. Current live planner/provider behaviour remains part of the
  optional `make evaluation-live-hybrid` evidence, unchanged;
- compares the resulting current observations against **its own reviewed,
  versioned, lineage-bound deterministic baseline** — under the same
  accepted `tests/evaluation/policies/v1/policy.json` document, but never
  against the real-provider baseline
  (`docs/evaluation/baselines/$(EVALUATION_CORPUS_VERSION)/`), since
  deterministic and real-provider metrics occupy unrelated numeric spaces
  and a comparison between them would be meaningless — see "Reclassified as
  a deterministic current-pipeline/orchestration regression gate," above,
  for exactly how that baseline is created, reviewed and invalidated;
- produces a run-scoped result carrying current repository lineage (the
  same `ExperimentLineage`/`repository_commit` shape `run.py` already
  produces), not a replay of historical lineage;
- makes **zero external provider calls** — deterministic embedder, sparse
  encoder and reranker throughout;
- **fails closed if any capability resolves to a live provider adapter** —
  the same startup-refusal requirement "Provider and heavyweight-model
  adapter boundary" already establishes for `make test-e2e`, applied
  identically here.

**What it proves**: deterministic orchestration correctness and
behavioural stability of the current retrieval implementation, from the
authored plan/query boundary through fusion, eligibility, search and
reranking, against its own deterministic baseline. **What it does not
prove**: current real-model retrieval quality, current live-planner
semantic quality, or any provider's actual quality (all three remain
`evaluation-live-hybrid`'s job, optional and live, expected once at
R22-GATE per "Where real retrieval-quality evidence actually comes from,"
above), or anything about generation (a separate gate entirely).

**What Phase 22's required gate actually is, split honestly**:

- **Required deterministic gates**: the full fast test tiers; cross-language
  contract tests; the deterministic, provider-free Playwright E2E suite
  (`make test-e2e`); historical retrieval-evidence policy enforcement via
  `make evaluation-policy-gate` (new — chains the existing `make
  evaluation-run` report generation with an enforced `assess_gate()` exit
  code, against the committed promoted baseline); current-code,
  provider-free retrieval execution via `make evaluation-retrieval-current`
  against its own deterministic baseline and the representative engineering
  corpus (new, defined precisely above); and the provider-free generation-
  integrity verifier, `make evaluation-generation-verify` (defined precisely
  below). `make evaluation-run` itself remains report generation only and is
  never, on its own, one of the required gates.
- **Optional live evidence, never a required pass, and never presented as a
  Playwright variant**: `make evaluation-live-hybrid` (already existing,
  live retrieval/provider smoke); `make evaluation-generation-live` (new,
  R22-S05 — a bounded, explicitly-invoked live run of `run_generation.py`
  against a fixed population, under the same opt-in/ceiling/honest-skip/
  immutable-run-identity rules as "Optional live-provider smoke suite,"
  above); and the evaluation-tier prompt-injection cases that require a real
  model call. None of these is Playwright E2E, none may be reported as a
  required deterministic pass, and a missing credential is an honest skip,
  never a fabricated pass. An optional umbrella live-evaluation target may
  group these for convenience, provided it stays obviously distinct from
  `make test-e2e` and preserves each check's own opt-in and budget rather
  than hiding them behind one flag.

Phase 22's evaluation gate, precisely: **three** required, provider-free
commands must pass as required steps of R22-GATE — `make
evaluation-policy-gate` (retrieval, historical evidence/policy enforcement
with an actual enforced exit code, new — built from existing `run`/`compare`
machinery plus the one-line `sys.exit(1)` addition), `make
evaluation-retrieval-current` (retrieval, current-code execution against its
own deterministic baseline, new), and `make evaluation-generation-verify`
(generation-evidence/harness integrity, new) — while live semantic
generation-quality measurement (`make evaluation-generation-live`) and live
retrieval/provider smoke (`make evaluation-live-hybrid`, expected once at
R22-GATE closure specifically, honestly skipped and carried to Phase 23 if
credentials/budget are unavailable then) remain explicitly optional and
non-gating for ordinary Phase 22 completion. `make evaluation-run` continues
to exist as the plain report-generation step `evaluation-policy-gate`
internally relies on; it is never itself listed as a required gate.

**`make evaluation-generation-verify` — the exact provider-free generation
verification contract, against the two real, distinct committed
locations.** Makes zero provider or evaluator calls. Verifies, against
`docs/evaluation/generation/` (`README.md` + `populations/
grounded-generation-v1.json`, the population/harness documentation) and,
separately, each relevant `docs/evaluation/runs/GEN-EXP-*/` directory
(verified directly to carry genuinely different, run-specific inventories —
GEN-EXP-0001 has `checkpoint-observations.json` and no `closure.md`;
GEN-EXP-0002 has the differently-named
`checkpoint-evaluation-observations.json` and does have `closure.md`; both
share `population.json`, `config.json`, `result.json`, `run-manifest.json`,
`application-observations.json`, `evaluation-observations.json` and
`checksums.sha256`). **The verifier never assumes one universal filename
set**: for each run directory, it reads that run's own `checksums.sha256`
(and `run-manifest.json`) as the authoritative declaration of which
artifacts that specific run actually has, validates exactly those, and
fails if a file the manifest/checksums declare is missing or if an
unexpected required artifact for that run's version is absent — future runs
may have their own further-differing inventories, and the verifier is
required to follow each run's declared inventory rather than a hand-coded
list of filenames. Against that per-run inventory: the committed
grounded-generation population under `docs/evaluation/generation/` parses
and validates; each GEN-EXP run directory's declared manifest/
configuration/result/recorded-observation files validate against their
typed/schema contracts; population and observation digests match their
recorded bindings; each run's `checksums.sha256` matches its actual
committed artifact identities;
recorded generation outputs can be rerun through the deterministic
structural evaluator (the non-model-assisted metrics); outcome,
citation-membership, over-refusal/overclaiming and leakage calculations
remain reproducible from the recorded outputs; immutable historical
generation observations are confirmed unmodified (their checksums match
what's recorded, e.g. the `SOURCE_OBSERVATION_SHA256` pattern
`reevaluate_generation.py` already uses); and no historical semantic score
is dishonestly recomputed without the model-assisted evaluator — a semantic
score either comes from a real, attributed evaluator run or is not silently
regenerated by this provider-free target at all. **What it proves**:
integrity and reproducibility of the population, harness, recorded outputs
and deterministic (non-semantic) metrics. **What it does not prove**: that
the current live model still produces those historical outputs, or current
semantic groundedness, factual precision or completeness — those questions
belong exclusively to the optional live tier.

**Level 3 — production-shaped staging corpus (Phase 23, handoff only).**
Phase 23 staging runs a larger, sanitised/licensed/synthetic
production-shaped corpus against deployment-like infrastructure, optionally
including the live-provider smoke suite. This ADR establishes the handoff
requirement — Phase 23 needs a documented process for sourcing, sanitising,
versioning and retiring that corpus — but does not implement staging or
design that pipeline. **Actual private customer documents must never
silently enter the normal automated suite.** Any future use of real
customer content in any test tier requires an explicitly approved,
access-controlled, sanitised process with documented retention and deletion
handling — this ADR does not authorise that process, it only states the
boundary a future one would need to satisfy.

### 9. Is R22-S06 needed? — **No, on the evidence found.**

The brief asks this ADR to determine, from direct inspection, whether
material new implementation is genuinely required to make representative-
corpus evaluation reproducible before recommending a narrowly bounded
R22-S06, and to prefer keeping the existing five-session plan otherwise.
Reassessed here specifically in light of the retrieval-versus-generation
correction above, since that correction is exactly the kind of finding that
could have changed this answer.

**Retrieval tier — reassessed, and genuinely new work exists here too, not
"nothing to build."** A versioned corpus/policy/manifest pipeline already
exists and already produces immutable, checksummed compiled artifacts
(`tests/evaluation/benchmarks/.../compiled/checksums.json`); an already-
accepted quality-gate policy already exists with explicit pass/fail
semantics; a reporting/index mechanism already exists (`evaluation-report`,
`evaluation-index`, `docs/evaluation/EXPERIMENTS.md`); reproducibility is
already a named, enforced concern (`metric_non_reproducibility` is one of
the policy's own `absolute_failures`) — all still true. What's newly
required, now that both `make evaluation-run`'s replay-only mechanism *and*
its lack of enforced exit-code gating are correctly described, is **two**
commands, not one: `make evaluation-policy-gate` (a one-line `run.py`
`sys.exit(1)`-on-failure addition plus Makefile wiring around entirely
existing `run`/`compare` machinery — the smallest possible fix, genuinely
new but genuinely thin) and `make evaluation-retrieval-current` (defined
precisely in "Real and representative corpus strategy," Level 2 — genuine
new implementation, including its own reviewed deterministic baseline, not
glue code). Both fit inside already-planned sessions rather than justifying
a new one: `evaluation-policy-gate` belongs in **R22-S02**, the same
"wrap existing machinery into one required, enforced command" work that
session already does for `evaluation-generation-verify`; `evaluation-retrieval-current`
belongs in **R22-S03** specifically because **R22-S03 already owns every
ingredient it needs**: the fail-closed deterministic embedding/sparse/
reranking factory wiring, the new deterministic retrieval planner, and the
isolated Qdrant/Compose execution environment (`dolved-e2e`) —
`evaluation-retrieval-current` is a thin adaptation of
`live_hybrid_retrieval.py` onto that same wiring, not a parallel mechanism
R22-S03 doesn't already touch.

**Generation tier**: the *harness* (parsing, aggregation, checksum/lineage
integrity, the `reevaluate_generation.py` re-scoring-without-regenerating
path) already exists and is already provider-free-for-the-harness-itself in
the sense that its own correctness doesn't need a real model call to test.
What's newly required, now that the corrected boundary is explicit, is
narrow: (a) `make evaluation-generation-verify` — a small, concrete,
provider-free verification target wrapping the existing harness/checksum
machinery into one required, gating command (the exact contract is defined
in "Real and representative corpus strategy," Level 2) — genuinely new
implementation, but a thin verifier over already-existing machinery, not new
evaluation machinery in its own right; and (b) `make evaluation-generation-live`,
a small safety wrapper around the *optional* live generation-evaluation
invocation (`run_generation.py`), enforcing the same opt-in/ceiling/
honest-skip/immutable-run-identity rules "Optional live-provider smoke
suite" already defines, so live generation evaluation doesn't silently
bypass those rules by being invoked directly. Both fit honestly as small
additions to already-planned sessions: `evaluation-generation-verify`
belongs in **R22-S02** (contract fixtures and cross-language verification)
alongside that session's other schema/fixture/contract-integrity work — it
is itself an integrity/reproducibility verifier, the same character of work
R22-S02 already owns, not R22-S01's taxonomy/documentation role — and the
live-safety wrapper belongs in R22-S05 (security-focused tests) alongside
the prompt-injection model-behaviour evaluation work that session already
owns. R22-S01 documents this tier's category and required-gate role in the
taxonomy; it does not implement the verifier itself.

**R22-S06 remains not recommended, but not because there's nothing new to
build — because everything new fits where it naturally belongs.** Both
tiers now carry genuine new implementation
(`evaluation-policy-gate` and `evaluation-generation-verify` in R22-S02;
`evaluation-retrieval-current` — including its deterministic execution-
profile manifest/digest and extended baseline-promotion binding — and the
mandatory, no-skip `make test-splade-integration` target in R22-S03;
`evaluation-generation-live` in R22-S05) — this reassessment
does not understate that. What it concludes is narrower and still holds:
none of that new work requires a *new, separately-justified session*, because
each piece is a natural, bounded extension of a session that already owns
the exact mechanism it needs (R22-S03 already owns the deterministic
factory wiring and isolated evaluation infrastructure both
`evaluation-retrieval-current` and the real-SPLADE check require; R22-S02
already owns contract/fixture-integrity verification, the same character of
work `evaluation-policy-gate` and `evaluation-generation-verify` both are;
R22-S05 already owns the live-provider safety-wrapper pattern). The existing
R22-S01–S05 plan, plus R22-GATE explicitly requiring all three provider-free
evaluation commands (see "Real
and representative corpus strategy," Level 2), is sufficient.

### 10. Test-data strategy

Synthetic or safely-licensed content only for every committed fixture — no
production database dumps, no personal/customer data, ever, in any committed
fixture at any tier. Immutable versioned fixture identities (matching the
existing `contracts/**` and `tests/evaluation/**` convention of versioned,
checksummed artifacts — Level 1's new E2E fixtures follow the same
discipline). Deterministic timestamps wherever behaviour depends on time
(matching the existing pattern already used in Laravel Feature tests via
`CarbonImmutable::setTestNow(...)`, extended to Playwright setup calls).
Every E2E run provisions unique workspace/user/document/conversation
identities (UUIDs or equivalent, namespaced per run) rather than reusing a
shared fixture identity across runs — this is what makes parallel execution
and re-run-after-failure both safe without a shared-mutable-state race.
Tests must not depend on execution order. Asynchronous completion is
observed via bounded polling (see "Flake, timeout and retry policy"), never
a fixed sleep as the primary correctness mechanism.

**Cleanup: a two-level model, corrected against what authorised APIs
actually exist.** An earlier draft of this ADR promised that all Postgres
rows, queue messages, S3 objects and Qdrant state are deleted through the
same authorised product APIs — verified against the repository and not
actually true: the product's authorised deletion surface is workspace/
document-level (ADR-0025's asynchronous document deletion), not a general
per-resource cleanup API for every table/queue/bucket/collection a test run
might touch (a SQS dead-letter message or a raw Qdrant collection has no
product-facing "delete this" endpoint at all). Promising a cleanup path that
doesn't exist would be exactly the kind of unverified claim this ADR must
not make. The corrected, two-level model:

- **Scenario-level**: each Playwright scenario uses unique application
  identities (per "unique workspace/user/document/conversation identities,"
  above) and uses authorised product cleanup APIs *where those genuinely
  exist* — for example, the real document-deletion flow for a document a
  scenario created and no longer needs mid-suite. Where no product API
  exists for a piece of state a scenario created (a raw queue message, a
  Qdrant collection), scenario-level cleanup simply does not attempt to
  fabricate one.
- **Environment-level (authoritative)**: the disposable `dolved-e2e` Compose
  project and its scoped volumes/resources are the actual, complete cleanup
  boundary — tearing down the project's containers and named volumes
  removes every Postgres row, every queue message, every S3 object and
  every Qdrant collection the run touched, regardless of whether a
  product-level API existed for that specific piece of state. This is
  honest specifically because the whole stack is disposable and
  project-scoped (see "Environment and orchestration"): the environment
  itself, not a patchwork of per-resource API calls, is what guarantees
  nothing leaks into a subsequent run.

**On a successful local run**, the orchestration command may tear down the
`dolved-e2e` project and its volumes immediately — there's nothing left to
diagnose. **On failure**, the orchestration command first retains
privacy-safe Playwright traces/screenshots and service logs (per "Failure-
artifact retention," below) *before* any teardown, and then leaves the
`dolved-e2e` stack itself up and preserved rather than tearing it down.
Because the selected topology permits only **one** fixed `dolved-e2e` stack
at a time (see "Environment and orchestration") and fails closed on
occupied ports, simply re-running `make test-e2e` against a preserved failed
stack would be ambiguous — it is not defined whether that reuses, restarts
alongside, or is blocked by the leftover stack, and this ADR does not leave
that ambiguity unresolved. After a failure, a developer must deliberately
choose one of exactly two paths: **inspect/resume the preserved diagnostic
stack** through an explicit, documented diagnostic command (e.g. `make
test-e2e-inspect`, which attaches to the still-running `dolved-e2e`
containers for log/state inspection without altering them), or **discard
it** via `make test-e2e-clean` and then start a genuinely fresh run. A plain
`make test-e2e` must never silently reuse or silently destroy an
unidentified leftover stack — if a leftover `dolved-e2e` stack is detected
and the developer has not explicitly chosen inspect or clean, `make
test-e2e` fails closed with an explicit message naming the leftover stack,
the same way it fails closed on an occupied port or an ambiguous identity.
CI's exact teardown behaviour is a Phase 23 decision, except that CI must
capture the same evidence before any teardown it performs — that constraint
is fixed here, not deferred. **Never** run a broad cleanup against an
ambiguously identified environment — cleanup, like startup, is gated by the
same `dolved-e2e` identity check "Environment and orchestration" defines.

**Failure-artifact retention**: Playwright's built-in screenshot/trace/video
capture on failure is enabled, plus relevant response summaries and
correlation identifiers (per ADR-0026's correlation-ID discipline) — but
never document content or credentials. Where a fixture document's content
itself would appear in a failure trace (e.g. a screenshot of the rendered
answer), that is acceptable because Level 1 fixtures are synthetic/safely
licensed by definition; this retention policy is precisely why customer
content must never enter this tier.

**Parallel-run isolation**: guaranteed by the unique-namespace-per-run
requirement above, not by serialising Playwright's execution — parallel
workers are safe exactly because no two runs ever share a workspace/user/
document identity.

### 11. Flake, timeout and retry policy

No automatic whole-test retries to paper over flakiness — a flaky-looking
E2E failure is a signal to investigate (a real race, a too-tight timeout, an
environment problem), not something to hide by re-running until green.
Asynchronous workflows (document ingestion reaching "ready," a generation
run reaching a terminal state) are observed by polling an *observable
product state* with a bounded deadline — never an arbitrary `sleep()` as the
primary correctness mechanism, though a small polling interval is fine as
the *mechanism* between observations. On timeout, the failure report
includes the last observed state and the run's correlation identifier(s), so
a timeout is diagnosable rather than a bare "test timed out." Deterministic
provider adapters must not inject their own uncontrolled timing (no random
sleep to "seem more realistic") — their latency, like their output, is
fixed and predictable. Any scenario that *is* deliberately retried in a test
(step 9 of the chat journey, reconnect behaviour) is explicitly testing the
product's own retry/reconnect semantics, never the test runner's retry
mechanism standing in for a flaky assertion. On any E2E failure: screenshots,
traces, logs and relevant response summaries are retained (per the retention
policy above). **Quarantine** (marking a known-flaky test as non-blocking
pending a fix) requires a named owner, a stated reason, and an expiry — an
unowned, open-ended quarantine is not permitted, and quarantined tests must
never silently count toward critical coverage being satisfied.

### 12. Security-test allocation

Security tests live as close to the authority boundary they protect as
possible, matching this repository's existing pattern (ADR-0006's
defence-in-depth layering, ADR-0026's platform-administrator boundary tests
already living in `PlatformOperationsTest.php`, not in a separate security
suite):

- **Laravel feature/API tests**: tenancy and cross-tenant `404`-not-`403`
  concealment (per resource type), IDOR, authentication/authorisation
  failures, platform/workspace-role boundaries, upload validation
  (malicious filenames, oversized files, unsupported media types),
  presigned-upload expiry, rate limiting, and safe (non-leaking) error
  responses.
- **Python unit/integration tests**: signed internal request verification
  (the ingestion-worker/rc1 HMAC boundary, tampered/replayed/expired
  signatures), schema rejection (every contract's negative fixtures),
  queue/event tampering (malformed or adversarial SQS payloads), unsafe
  provider-output validation (a provider response that doesn't match its
  expected typed shape is rejected, not passed through), and document-
  content treatment at ingestion (untrusted document text is carried as
  content, never interpreted as instruction, at the point it enters the
  pipeline).
- **Shared contract tests**: signature/canonicalisation/version/unknown-field
  failure cases, exactly as defined in "Contract-verification strategy."
- **Playwright E2E**: only the small number of critical cross-system
  security journeys whose correctness genuinely depends on the full running
  platform — principally, the cross-tenant concealment journey already
  specified as chat-journey step 8 (a second real authenticated browser
  context cannot retrieve, deep-link to, or infer another tenant's
  resources) — not a broad security suite duplicated at this expensive
  layer.
- **Evaluation**: model-behaviour security cases, specifically
  prompt-injection resistance, as a distinct measurement tier from the
  deterministic tests above.

**The prompt-injection claim is deliberately bounded, in three distinct
layers, none of which claims universal immunity**:

1. **Deterministic tests** (Python unit/integration) prove a structural
   fact independent of any model's behaviour: document text is carried
   through the pipeline as untrusted evidence/content — placed in the
   content/evidence position of whatever structure the contextualisation
   and generation contracts define — and is never promoted into a
   system/developer instruction position. This is testable exactly, with no
   model call, because it is a data-plumbing fact about this codebase, not
   a claim about model behaviour.
2. **Contract/orchestration tests** (shared contract + Laravel/Python
   integration) prove that untrusted content cannot mutate an authoritative
   control field — a document containing text that looks like an
   instruction to change the outcome, the workspace, the retrieval scope,
   or any other control field must not be able to do so, because those
   fields are constructed by the application from authorised inputs, never
   parsed out of document content.
3. **Evaluation cases** (a representative-attack population, run through
   real model calls or the model-assisted evaluator) measure whether the
   *currently supported models* follow the intended instruction hierarchy
   across representative attack patterns — this is inherently probabilistic
   and belongs in evaluation precisely because it is a quality/behaviour
   measurement, not a pass/fail correctness fact.

**No test suite in any tier may claim to prove universal prompt-injection
immunity** — layers 1 and 2 prove structural facts about this codebase that
hold regardless of model behaviour; layer 3 measures observed model
behaviour on a representative, necessarily incomplete, sample of attacks,
and its result is a quality signal, not a security guarantee.

### 13. Environment and orchestration

**One isolated Compose topology, selected explicitly rather than left as an
either/or.** The existing services (`web`, `api`, `ai`, `worker`,
`publisher`, `conversation-worker`, `postgres`, `qdrant`, `localstack`,
`mailpit`) are what the E2E suite runs against; this ADR does not propose
new infrastructure services. The selected design:

- `compose.yaml` remains the base topology, unmodified — it is not forked
  or duplicated.
- R22-S03 adds a **committed override file**, `compose.e2e.yaml`, applied on
  top of the base topology (`docker compose -f compose.yaml -f
  compose.e2e.yaml ...`), carrying only the E2E-specific differences: the
  deterministic environment identity (see "Provider and heavyweight-model
  adapter boundary"), distinct host ports, distinct resource names, and the
  mount isolation defined next.
- **The base services' mounts are broader than the E2E suite should ever see
  through, and `compose.e2e.yaml` must replace them, not merely add to
  them.** Verified directly against `compose.yaml`: `web` mounts the entire
  repository root (`.:/workspace:ro`); `api` mounts `./tests/evaluation:/evaluation:ro`
  and `./docs/evaluation/runs:/evaluation-runs`; `ai` mounts those two plus
  `./docs/evaluation/generation:/generation-evaluation:ro`. Left as-is under
  `dolved-e2e`, the deterministic product-journey suite would run inside
  containers that can see the entire repository, the full evaluation corpus,
  every historical evaluation run, and generation-evaluation history — the
  opposite of the physical separation Level 1's compact E2E corpus is
  supposed to have from Level 2's evaluation evidence. `compose.e2e.yaml`'s
  per-service `volumes:` key must **replace the inherited list**, not append
  to it (Compose's array-merge behaviour for the pinned Compose version must
  be verified during R22-S03 implementation; if the installed version cannot
  safely replace an inherited list, R22-S03 uses an explicit reset/override
  mechanism it does support, or falls back to a separately enumerated E2E
  service topology rather than accepting silent appension). The E2E mount
  allowlist, per service, contains only what that service genuinely needs:
  its own application source/runtime-dependency volumes (unchanged);
  `contracts/:/contracts:ro` where an application actually reads contract
  schemas at runtime; the dedicated `tests/end-to-end/fixtures/` Level-1
  corpus, read-only, mounted **only** into the component that genuinely
  needs it (the `api`/`ai` containers the ingestion/chat journeys exercise —
  not `web`, which has no reason to read fixture files directly); and any
  genuinely required scripts/configuration, mounted individually and
  explicitly rather than by mounting the repository root to reach them. It
  **removes or masks**, for every E2E-profile service: `web`'s whole-repository
  `/workspace` mount; `/evaluation`; `/evaluation-runs`;
  `/generation-evaluation`; and any other broad documentation/history mount
  not needed by the deterministic product journey. Evaluation commands
  (`make evaluation-run`, `make evaluation-retrieval-current`, `make
  evaluation-generation-verify`, and the optional live commands) run in
  their **own**, separately selected evaluation Compose context — the one
  `compose.yaml` already uses today for `make evaluation-run`/`evaluation-live-hybrid`
  — never inside the Level-1 E2E service filesystem `compose.e2e.yaml`
  defines. Playwright's own host-side project may read its own
  `tests/end-to-end/fixtures/` directly (it runs outside any container); the
  *application containers* under `dolved-e2e` receive only the minimum data
  above — this is not a "the suite promises not to read those paths"
  convention, it is those paths not being mounted at all. **R22-S03 adds a
  rendered-Compose/configuration test** — resolving `docker compose -f
  compose.yaml -f compose.e2e.yaml config` (or equivalent) and asserting
  none of the forbidden paths (`/workspace` whole-repo, `/evaluation`,
  `/evaluation-runs`, `/generation-evaluation`) appear in any E2E-profile
  service's mount list — run as part of `make test-e2e`'s own startup
  preflight, alongside the identity/port/Node checks: the suite **fails
  closed before starting** if a forbidden evaluation/repository-history path
  remains mounted, exactly as it does for an ambiguous identity or an
  occupied port.
- The stack is invoked under the explicit Compose project identity
  `dolved-e2e` (`COMPOSE_PROJECT_NAME=dolved-e2e` or `-p dolved-e2e`) — never
  the default/dev project name — so its containers, named volumes and
  network are Compose-scoped and separated from an ordinary dev environment
  by construction, not by convention a developer has to remember.
- R22-S03 adds a **committed, non-secret** E2E environment/configuration
  file (e.g. `.env.e2e`, containing no real credentials — see below) that
  `compose.e2e.yaml` reads from, so the E2E identity is reviewable,
  versioned repository content, not a developer's ad hoc local override.
- Distinct host ports and resource names (database name, S3 bucket
  prefixes, SQS queue names) for every E2E-exposed service, so a developer's
  ordinary dev stack (default project, default ports) is structurally
  untouched even if both happen to be running at once.
- Laravel and Python's environment identity is set explicitly to E2E in
  `compose.e2e.yaml`/`.env.e2e` (not inferred from project name alone), and
  only deterministic providers are selected for that identity, per
  "Provider and heavyweight-model adapter boundary" — real provider
  credentials are never mounted into the `dolved-e2e` stack at all, so
  there is nothing for a wiring mistake to accidentally call.
- Named-volume and network separation is provided by Compose's own project
  scoping (a distinct project name automatically gets distinct default
  volume/network names) — no new isolation mechanism is invented beyond
  using the project-identity feature Compose already provides.
- **Fails before startup**, not silently, in four distinct ways: (a) if the
  resolved project/environment/database/bucket/queue identities do not
  carry the required `dolved-e2e`/E2E marker (the same "refuses to run
  against an ambiguous or non-test-looking identity" requirement as before,
  now anchored to a concrete, checkable marker rather than an abstract
  one); (b) if the dedicated E2E ports are already occupied — the E2E entry
  point checks port availability and fails clearly, rather than silently
  falling back to a different port and creating an ambiguous environment a
  developer might mistake for something else; (c) if the resolved Node
  major is not 24 — `make test-e2e`'s own startup preflight (see "Playwright
  as the product-level E2E runner") rejects any other major before
  installing or running Playwright, rather than relying on `engines`'
  best-effort warning alone; and (d) if the rendered `compose.e2e.yaml`
  configuration still contains a forbidden evaluation/repository-history
  mount (per the mount-isolation check above).
- Explicit readiness checks before the suite starts — reusing the health
  checks `compose.yaml` already defines for every service — rather than a
  fixed startup sleep.
- One documented command for the required deterministic E2E suite, `make
  test-e2e`, wrapping the `dolved-e2e` `docker compose -f compose.yaml -f
  compose.e2e.yaml` invocation plus `npx playwright test` in
  `tests/end-to-end/` — deterministic and provider-free, always. Two further
  `dolved-e2e`-scoped commands support it without expanding what it does:
  `make test-e2e-inspect` (attach to a preserved failed stack for
  diagnosis, without altering it) and `make test-e2e-clean` (discard a
  preserved failed stack). **`make test-e2e-live` does not exist as a
  concept** — live-provider evaluation is not Playwright E2E and must never
  be presented as a variant of it (see "Optional live-provider smoke suite"
  and "Real and representative corpus strategy," Level 2, for the actual
  live commands: the existing `make evaluation-live-hybrid` for retrieval,
  and the new `make evaluation-generation-live` for generation) — never the
  same command as `make test-e2e` with a flag that could be forgotten.

**Playwright's own execution environment**: an independent Node package
under `tests/end-to-end/`, run on Node 24 (see "Playwright as the
product-level E2E runner"). Whether Phase 23 CI runs Playwright on the host
runner or inside a pinned official Playwright container is explicitly a
Phase 23 CI decision this ADR does not make prematurely — both are
compatible with the `dolved-e2e` stack and command defined here.

**Concurrency, stated honestly rather than implied.** Playwright's own
workers, within one running `dolved-e2e` stack, use unique per-scenario/
per-run namespaces (see "Test-data strategy") and are therefore safe to
parallelise against each other. The initial local implementation supports
**one `dolved-e2e` Compose stack at a time** — fixed host ports and a fixed
project identity mean a second, independently-running `dolved-e2e` stack
would collide with the first, not run alongside it. This ADR does not
promise concurrent independent E2E stacks; that is dynamic project/port
allocation, genuine additional design work with no current justification —
Phase 23 may introduce it if CI's actual concurrency needs require it, but
this ADR does not pre-design that.

This ADR does not design Phase 23 CI, but the command and topology above are
defined precisely so Phase 23 can invoke them unchanged as CI steps rather
than needing to re-derive how the E2E environment comes up.

### 14. Performance and accessibility boundaries

Phase 22's E2E suite validates critical functional journeys, not load or
capacity testing — a latency assertion in this suite is a bounded workflow
timeout (a generous ceiling that catches "this broke" not "this got 200ms
slower"), never a fragile workstation-performance budget. Detailed
load/performance testing is explicitly out of this ADR's scope and belongs
in a separately-justified future or staging boundary. Accessibility
component/route tests (already established in ADR-0027 and Phase 21) remain
valuable and continue at their existing tier; Playwright additionally covers
a small number of critical keyboard/focus/live-region journeys (matching
ADR-0027's bounded live-region requirement) precisely because those specific
behaviours can only be genuinely proven against a real browser's
accessibility tree — this is additive to, not a replacement for, component-level
axe-style checks. Automated accessibility checks at any tier do not replace
the manual usability review Phase 21 already performed and does not
reintroduce that review as this phase's responsibility.

## Alternatives considered

**Playwright versus Cypress.** Selected Playwright — see "Playwright as the
product-level E2E runner" above: verified first-party alignment with this
exact Next.js/React generation, native multi-browser-context support
(directly useful for the cross-tenant journeys this ADR requires), and
avoiding two competing E2E frameworks.

**One enormous E2E suite versus layered testing.** Rejected the enormous
suite: it would be slow, flaky-prone, and would re-prove what unit/feature/
contract tests already prove cheaply — exactly the "expensive end-to-end
test for everything" outcome the phase objective exists to avoid. Layered
testing (this ADR's 15-category taxonomy) selected instead.

**Real external providers in every E2E run.** Rejected: nondeterministic,
costly, rate-limited, and would make ordinary CI runs depend on OpenAI/
Voyage availability and quota — exactly what the deterministic-provider
boundary exists to prevent. Retained only as the explicitly separate,
non-gating live-provider smoke tier.

**Mocking entire internal services.** Rejected for the required E2E tier:
mocking Laravel or Python wholesale (rather than only the outermost provider
call) would stop proving the real internal orchestration this ADR's brief
specifically requires E2E tests to exercise, and would silently duplicate
what Laravel's `Http::fake()`-based Feature tests already do at a cheaper
layer — the whole point of the E2E tier is that everything *except* the
paid-provider boundary is real.

**Pact/a contract broker versus the existing repository-owned JSON Schemas
and fixtures.** Rejected — see "Contract-verification strategy": two
producer/consumer pairs, already versioned in one repository, already
schema-validated on both sides; a broker adds infrastructure and a second
notion of "contract" without solving a problem this repository has.

**Generated contract types versus controlled hand-written types.** Rejected
generation machinery — see "Contract-verification strategy": the existing
hand-written types, cross-checked against the schema in tests, are the
smaller, already-working mechanism; generation would add build tooling and
a translation layer without fixing a problem inspection actually found.

**Database/Qdrant internals as primary E2E assertions versus product-boundary
outcomes.** Rejected internals-as-primary: primary assertions are
product-observable ("ready and retrievable," concealment behaviour);
internal inspection is permitted only as a narrow, justified exception where
no product boundary can honestly expose the fact being checked (see R22-S03's
provisional-vector example).

**Automatic test retries.** Rejected as a default — see "Flake, timeout and
retry policy": retries-to-hide-flakes would erode the suite's meaning over
time; only product-semantic retry scenarios are deliberately exercised.

**Shared mutable E2E tenant versus per-run isolation.** Rejected the shared
tenant: it would make parallel execution unsafe and make one test's leftover
state a hazard for the next; per-run unique namespaces selected instead.

**Using the large evaluation corpus directly for every Playwright run.**
Rejected — see "Real and representative corpus strategy," Level 1 versus
Level 2: coupling E2E to the evaluation corpus would make E2E fragile to
evaluation-corpus revisions that have nothing to do with workflow
correctness, and would make every Playwright run as slow as an evaluation
run. A small, purpose-built, separately-versioned E2E fixture set is used
instead.

**Treating one golden model answer as a stable quality benchmark.**
Rejected: model output varies; a single golden answer either becomes stale
noise (constant false failures as models/prompts evolve) or gets
hand-tuned to always pass (defeating its purpose). Quality is measured by
the evaluation tier's statistical/threshold-based approach instead, over a
population, against an explicit policy.

**Deferring all representative-corpus testing to Phase 23.** Rejected:
Phase 22 already has mature, already-accepted machinery available now for
exactly what it can honestly gate — retrieval quality regressions via `make
evaluation-run`, and generation-evidence/harness integrity regressions via
`make evaluation-generation-verify` (see "Is R22-S06 needed?") — deferring
either to Phase 23 would leave those specific, already-checkable
regressions uncaught for an entire phase for no inspection-supported reason.
This does not extend to live-model semantic generation quality, which
remains optional evidence in both Phase 22 and Phase 23 — deferring *that*
is not what this alternative rejects.

**Introducing R22-S06 versus using the existing evaluation machinery at the
phase gate.** Evaluated directly against the repository's actual state — see
"Is R22-S06 needed?" — and rejected: real new implementation is required
(`evaluation-policy-gate`, `evaluation-retrieval-current` with its own
deterministic baseline, `evaluation-generation-verify`, the bounded
real-SPLADE integration check, the E2E bootstrap command, the twelve new
contract schemas), but none of it justifies a new, separately-purposed
session, because each piece is a bounded extension of a session that
already owns the exact mechanism it needs. The existing five-session plan is
sufficient **only** with **all three** required R22-GATE evaluation
commands in place — `make evaluation-policy-gate` (retrieval, historical
evidence, enforced policy exit code), `make evaluation-retrieval-current`
(retrieval, current-code execution against its own baseline), and `make
evaluation-generation-verify` (generation-evidence/harness integrity) — not
any one or two of them alone, and never with plain `make evaluation-run`
substituted for `evaluation-policy-gate`, since report generation alone
enforces nothing.

## Required session allocation

- **R22-S01 (taxonomy/documentation)**: document the fifteen-category
  taxonomy above; **rename**
  `EndToEndIngestionOrchestrationTest.php`/its PHP class to
  `IngestionOrchestrationFeatureTest.php` (filename and class name only, no
  logic/assertion change); document the test-data/cleanup/flake/quarantine
  policies above; document the required-versus-optional evaluation-gate
  split and `make evaluation-generation-verify`'s category and required-gate
  role (documentation only — its implementation is R22-S02's, see below; see
  "Is R22-S06 needed?"). No new dependency. **Small cross-language-adjacent
  change**: the PHP rename itself touches `apps/api`, though it is
  mechanical (filename/class name only).
- **R22-S02 (contract fixtures and cross-language verification) — now
  includes genuine new schema authorship, not only test extension.** Extend
  negative-fixture coverage (unknown field, missing field, invalid enum,
  unsupported version) to every contract surface listed in "Covered contract
  surfaces" that doesn't already have it; add the hand-written-type-versus-
  schema cross-check test per typed model; confirm/extend
  canonicalisation-vector coverage; **author the twelve new versioned
  request/response JSON Schemas** for the nine ingestion-worker and three
  deletion operations that currently have none (documenting existing wire
  behaviour, not redesigning it — stopping to report any contradiction with
  ADR-0009/ADR-0015/ADR-0016 rather than choosing a new shape), each with
  canonical valid fixtures and the required invalid cases; **implement
  `make evaluation-generation-verify`**, the provider-free generation-
  integrity verifier defined precisely in "Real and representative corpus
  strategy," Level 2 — a thin, new wrapper over the existing harness/
  checksum machinery; **implement `make evaluation-policy-gate`**, the
  historical retrieval policy-enforcement gate defined precisely in the same
  section — Makefile wiring around the existing `run.py` `run`/`compare`
  subcommands plus one explicit `sys.exit(1)`-on-failure line added to
  `compare` itself, since neither subcommand enforces an exit code today —
  both new commands are the same character of "wrap existing machinery into
  one required, enforced command" work this session already owns for
  contracts. **Cross-language change**: both
  `apps/api` and `apps/ai` test suites gain new tests reading the same
  shared fixtures, and twelve new schema files land under `contracts/http/`;
  no new runtime dependency in either language (both schema-validation
  libraries already exist) — genuine new contract-documentation/test work
  that does not change runtime wire semantics.
- **R22-S03 (ingestion E2E) — the largest new-implementation session, not
  the only one.** Introduces the `tests/end-to-end/` Playwright package
  (**new dependency**: `@playwright/test`, plus the Node 24 `engines`/
  `.node-version` contract shared with `apps/web`, plus `make test-e2e`'s
  own Node-major startup preflight); authors the Level 1 compact fixture
  corpus; implements the eleven-point ingestion journey; introduces
  `compose.e2e.yaml` (replacing, not appending to, the base services'
  inherited volume lists, per "Environment and orchestration"'s mount
  isolation), the `dolved-e2e` Compose project identity, the committed
  non-secret `.env.e2e`, the rendered-Compose mount-isolation preflight
  test, and the deterministic, provider-free `make test-e2e` command plus
  its `make test-e2e-inspect`/`make test-e2e-clean` diagnostic/cleanup
  companions — no live-provider command is introduced here. **New Laravel
  implementation**: the environment-guarded `php artisan e2e:bootstrap`
  command (see "E2E user/workspace bootstrap boundary") and its required
  tests (E2E-identity-only execution, refusal in every other environment,
  real-`CreateWorkspace`-action provisioning, no public HTTP surface,
  deterministic duplicate/re-run behaviour). **New Python implementation**:
  fail-closed `Settings`/factory wiring for the embedding, sparse-encoding
  and reranking capabilities (a conditional branch in each existing
  `factory.py`, selecting the existing `fake.py` classes under the
  `dolved-e2e` identity); a new deterministic retrieval planner capable of
  mapping a small catalogue of known fixture questions to stable planning
  outcomes (extending the `FixedRetrievalPlanner` pattern, which today only
  returns one fixed plan per instance, not a question-keyed catalogue) and
  the corresponding factory branch for `planner_dependency()`, which
  currently has none; **`make evaluation-retrieval-current`**, adapting
  `apps/ai/app/evaluation/live_hybrid_retrieval.py`'s existing real-
  embedding/real-Qdrant-search/real-reranking/authored-plan harness onto
  this same factory-selected deterministic profile; the new
  `sparse_profile_fingerprint`/`reranker_profile_fingerprint`/
  `plan_catalogue_checksum` fields, the composite deterministic execution-
  profile manifest/digest built from them plus `ExperimentLineage`'s
  existing fields, and the extended deterministic-lineage
  `BaselinePromotion` shape that records that digest (see "Real and
  representative corpus strategy," Level 2, for the exact manifest
  contents and the three-check verification sequence); creating and
  reviewing the first deterministic baseline under this new binding; and
  **`make test-splade-integration`** (see "Provider and heavyweight-model
  adapter boundary") — a small, separately-run Pytest target constructing
  the genuine `FastEmbedSparseEncoder`/`SparseTextEmbedding` against the
  pinned model cached in the existing `fastembed_cache` volume, replacing
  this ADR's earlier, incorrect claim that existing sparse tests already
  covered this, and **mandatory (no skip permitted) as part of R22-S03's
  own acceptance**, not only at R22-GATE.
- **R22-S04 (chat E2E) — completes the deterministic profile, real new
  implementation here too.** Implements the ten-point chat journey on top of
  R22-S03's environment and fixtures. **New Python implementation**:
  fail-closed `Settings`/factory wiring for `build_contextualizer()` and
  `build_generator()` (both currently `raise ValueError` for any provider
  but `"openai"`), and a deterministic, scenario-catalogue-capable
  contextualiser plus generation fake configuration covering the
  clarification/no-answer/timeout/retraction scenario chat-journey step 10
  requires — extending the existing `generation/fake.py`/`conversation`
  fakes' pattern, not inventing a new one, but genuinely new catalogue
  content and wiring.
- **R22-S05 (security regressions and the live-generation-evaluation safety
  wrapper)**: distributes the deterministic security cases across Laravel/
  Python/contract tiers per "Security-test allocation"; adds the one
  Playwright cross-tenant-concealment journey if R22-S04 didn't already
  cover it as chat-journey step 8; adds the evaluation-tier prompt-injection
  representative-attack cases to the existing evaluation package;
  **implements `make evaluation-generation-live`**, the small, explicitly-
  named safety wrapper around `run_generation.py` (opt-in/ceiling/honest-
  skip/immutable-run-identity, matching "Optional live-provider smoke
  suite"), per "Is R22-S06 needed?" — clearly named and clearly separate
  from `make test-e2e`, not a flag on it; this is a small addition to an
  already-planned session, not new scope creep.
- **R22-GATE**: required deterministic gates — the full fast tier; the
  cross-language contract tests; the deterministic, provider-free `make
  test-e2e` Playwright suite; `make test-splade-integration` (real SPLADE
  model load/inference, mandatory here with no skip permitted, per
  "Provider and heavyweight-model adapter boundary"); `make
  evaluation-policy-gate` (retrieval, historical evidence enforced against
  the committed promoted baseline and
  `tests/evaluation/policies/v1/policy.json` with a real non-zero exit on
  failure, provider-free); `make evaluation-retrieval-current` (retrieval,
  current-code execution against its own deterministic baseline, verified
  through all three of its execution-profile checks, provider-free); and
  `make evaluation-generation-verify` (generation-evidence/harness
  integrity, provider-free). Also performed once at this
  gate, as deliberately-scheduled optional evidence, never a required pass:
  a live `make evaluation-live-hybrid` run for real-provider retrieval-
  quality evidence (credentials/budget available by design for this one
  occasion; an honest, reported skip and a carried-forward Phase 23
  staging-readiness item if not). Optional, non-gating evidence at any other
  time — never reported as a required pass, never presented as a Playwright
  variant — `make evaluation-live-hybrid` (ad hoc), `make
  evaluation-generation-live` (live semantic generation evaluation), and any
  prompt-injection evaluation case requiring a real model call.
- **Not recommended**: R22-S06. See "Is R22-S06 needed?" for the evidence.

## Consequences

### Positive

- A single, named taxonomy (fifteen categories) replaces informal
  convention, closing the exact naming-inflation gap this repository already
  exhibits — `EndToEndIngestionOrchestrationTest.php` is genuinely renamed
  to `IngestionOrchestrationFeatureTest.php`, not merely reclassified in
  prose, so the correction actually holds once a real multi-service
  Playwright ingestion test exists alongside it.
- The provider and heavyweight-model adapter boundary builds on an
  already-proven, already-checked-in *pattern* (`fake.py`,
  `FixedRetrievalPlanner`, the real `live_hybrid_retrieval.py` harness)
  rather than inventing a new one — the wiring that connects that pattern to
  a selectable E2E/evaluation-current profile is genuinely new work,
  honestly allocated to R22-S03/R22-S04 rather than assumed to already
  exist, which is itself a lower-risk position than the earlier draft's
  overstated one.
- The contract-verification strategy formalises and extends test coverage
  the repository already has the tooling for (both schema-validation
  libraries already present) rather than introducing new infrastructure
  (Pact) this repository's actual shape doesn't need — and now correctly
  authorises the twelve genuinely-missing ingestion-worker/deletion schemas
  rather than assuming they already existed.
- Node 24 aligns the E2E toolchain with the web application's own existing
  container runtime (`apps/web/Dockerfile`) instead of introducing a second,
  unrelated Node major into the repository.
- Phase 22's retrieval gate is now honestly two required, provider-free
  checks with real enforcement (`evaluation-policy-gate`, which actually
  enforces the accepted policy with a real exit code, for historical
  evidence; `evaluation-retrieval-current`, against its own reviewed
  deterministic baseline, for current-code execution) rather than one
  report-generation command (`evaluation-run`) mistakenly described as a
  gate; the generation-evaluation tier is correctly split so only its
  provider-free harness/integrity check is required, and live semantic-
  quality measurement for both retrieval and generation is honestly
  optional, expected once at R22-GATE closure specifically — this ADR no
  longer overclaims a provider-free proof of current pipeline/model quality
  it cannot actually provide, and no longer calls report generation alone a
  gate anywhere.
- The real-SPLADE integration check closes a second overclaim this ADR's
  earlier draft made (that existing sparse tests already proved real model
  loading) without inventing new infrastructure — it reuses the
  already-defined `fastembed_cache` volume rather than adding a new caching
  mechanism.
- The E2E user/workspace bootstrap boundary closes a real gap (no public
  workspace-creation endpoint exists) without creating new production
  attack surface, and explicitly separates automated-test provisioning from
  the still-undecided real-customer workspace-provisioning question rather
  than quietly deciding it as a side effect of test infrastructure.
- Three explicit corpus levels (E2E/evaluation/staging) prevent "real corpus
  testing" from falling into an undefined gap between phases, directly
  answering the brief's stated concern.
- The selected Compose topology (`compose.e2e.yaml` + the `dolved-e2e`
  project identity) and the two-level cleanup model are both concretely
  buildable against mechanisms Compose and this repository's actual
  authorised APIs genuinely provide, rather than promising an isolation or
  cleanup guarantee inspection couldn't support.

### Negative

- A genuinely new dependency and toolchain (Playwright on Node 24) is
  introduced — real, if modest, ongoing maintenance cost, though the Node
  major itself is not new to the repository.
- **Real new Python implementation is required in R22-S03 and R22-S04, not
  contained to one session**: fail-closed factory/settings wiring for four
  provider capabilities, a new deterministic retrieval planner with a
  question-keyed scenario catalogue, a new deterministic contextualiser
  configuration, and adapting `live_hybrid_retrieval.py` into
  `evaluation-retrieval-current` — genuine design and implementation work,
  not glue code around an already-complete mechanism.
- **Real new Laravel implementation is also required** (R22-S03): the
  `php artisan e2e:bootstrap` command and its full negative/positive test
  suite — a new, carefully-scoped authority boundary in `apps/api`, not
  merely test glue.
- **R22-S02 gains genuine schema-authorship work, not only test extension**:
  twelve new versioned JSON Schemas for ingestion-worker/deletion operations
  that currently have none, each needing valid/invalid fixtures on both
  language sides — real, non-trivial documentation-and-test work even though
  it changes no runtime wire behaviour.
- The `compose.e2e.yaml`/`dolved-e2e` topology, its committed `.env.e2e`,
  its fail-closed identity/port/Node/mount-isolation checks, and the
  requirement that `compose.e2e.yaml` genuinely *replace* rather than append
  to the base services' inherited volume lists are new operational surface
  area beyond "just run the dev containers," requiring real implementation
  care in R22-S03 — including verifying the installed Compose version's
  actual merge semantics — to get genuinely safe rather than theoretical.
- The two-level cleanup model means environment-level teardown (not a
  per-resource API) is the actual cleanup guarantee for state with no
  authorised deletion API — correct and honest, but it does mean a
  developer cannot selectively inspect one piece of leftover state without
  either the whole `dolved-e2e` project remaining up or falling back to
  direct infrastructure inspection.
- The three-level corpus strategy, while it prevents the gap the brief
  warns about, does mean Level 1's E2E fixtures are a second, deliberately
  separate corpus from Level 2's evaluation corpus — a small ongoing
  authoring/maintenance cost distinct from reusing one corpus everywhere,
  accepted here specifically to avoid coupling workflow-correctness tests to
  quality-measurement corpus revisions.
- R22-S02's per-contract negative-fixture and cross-check extension is
  real, non-trivial implementation work across two languages, even though it
  needs no new dependency.
- Phase 22 completion still leaves current live-model generation quality
  unmeasured by any required, gating check — an accepted, explicit
  trade-off (see "Real and representative corpus strategy," Level 2), not
  an oversight, but a real limit on what "Phase 22 passed" actually proves
  about live generation quality.
