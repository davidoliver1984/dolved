# Platform testing strategy

ADR-0029 defines Dolved's authoritative testing and contract-verification
architecture. This document is the operational reference for choosing the
smallest test layer that can truthfully prove a behaviour. It does not replace
the ADR or decide application behaviour.

## Taxonomy

| Category | Primary runner | What it proves | Normal provider policy |
|---|---|---|---|
| Frontend unit | Vitest | Pure hooks, functions and utilities | None |
| Frontend component | Vitest, Testing Library, jsdom | DOM and accessibility semantics for supplied state | None |
| Frontend integration/route | Vitest and mocked API boundary | Route/component composition | None |
| Laravel unit | PHPUnit | Pure PHP logic and value objects | None |
| Laravel feature/API | PHPUnit with isolated database | Laravel routes, actions, policies and queries | Python HTTP boundary may be faked |
| Python unit | Pytest | Pure Python logic and typed boundaries | None |
| Python integration | Pytest | Composed Python stages against deterministic adapters | Deterministic adapters |
| Shared HTTP contract | PHPUnit and Pytest over the same schemas/fixtures | Both languages agree on HTTP payloads and reject invalid shapes | None |
| Shared event contract | PHPUnit and Pytest over the same schemas/fixtures | Producers and consumers agree on versioned events | None |
| Infrastructure/configuration | Shell and native validators | Compose, telemetry, rules and migration configuration | None |
| Playwright E2E platform | Playwright against `dolved-e2e` | Critical browser-to-storage workflows across the real internal services | External providers and heavyweight SPLADE inference are substituted |
| Retrieval evaluation | Python evaluation harness | Historical policy enforcement, deterministic current-pipeline regression, and separately labelled live quality evidence | Required gates are provider-free; live evidence is explicit |
| Generation evaluation | Python generation-evaluation harness | Recorded evidence integrity and, separately, optional live semantic quality | Integrity gate is provider-free; live evaluation is explicit |
| Live-provider smoke | Explicit opt-in commands | Credentials, quota and current provider API compatibility | Real providers, bounded and non-gating |
| Security regression | Cheapest applicable runner above | Deterministic tenancy, authorisation, upload, contract and concealment boundaries | None except explicitly labelled model evaluation |

A test is named end-to-end only when it crosses the real running services
through a product-observable browser or authorised API boundary. Crossing
several classes inside one process remains a feature or integration test. The
former `EndToEndIngestionOrchestrationTest` is therefore named
`IngestionOrchestrationFeatureTest`; its assertions are unchanged.

## Test data and isolation

- Unit, component, feature and integration tests own minimal fixtures at their
  own layer and must not depend on mutable developer data.
- Playwright uses a compact, separately versioned Level-1 corpus under
  `tests/end-to-end/fixtures/`. It is development/regression evidence, not an
  evaluation or calibration population.
- Retrieval and generation evaluation retain their existing versioned
  populations, manifests and immutable evidence. They are not copied into the
  E2E fixture corpus.
- Every E2E run uses a unique run/scenario namespace and two independently
  provisioned workspaces where tenant isolation is asserted.
- The `dolved-e2e` Compose project must use distinct resources and physically
  exclude broad repository, evaluation-history, calibration and held-out
  mounts. Its startup preflight fails closed on ambiguous identity, occupied
  ports, a non-Node-24 runtime, live provider selection or forbidden mounts.
- Test fixtures contain synthetic or safely licensed material only. Customer
  documents never enter an automated suite without a separately approved,
  access-controlled sanitisation and retention process.

## Cleanup and failure evidence

- Normal successful E2E runs remove their namespaced application and
  infrastructure state through the supported cleanup command.
- A failed E2E stack is preserved for inspection; `make test-e2e-inspect` is
  diagnostic only and `make test-e2e-clean` performs the explicit cleanup.
- Playwright screenshots, traces, videos and service logs are failure evidence,
  not repository artefacts. They remain privacy-safe, run-scoped and governed
  by the documented retention limit.
- Cleanup must never use broad, unresolved paths, unscoped resource names or a
  developer's ordinary Compose project.

## Flakes, timeouts and quarantine

- Automatic retry-to-green is disabled. A product-semantic retry or reconnect
  is tested deliberately; the test runner does not hide an unexplained first
  failure.
- Asynchronous workflows poll a real observable state with a bounded deadline.
  Fixed sleeps are not readiness checks.
- Timeouts are generous correctness ceilings, not workstation performance
  budgets.
- A flaky required test remains a failure until its cause is fixed. Temporary
  quarantine requires an owner, written reason, tracking reference and expiry;
  quarantine cannot silently satisfy a phase gate or security invariant.

## Required and optional evaluation evidence

R22-GATE requires the fast suites, shared contracts, deterministic Playwright
E2E, the real-model SPLADE integration check and these provider-free commands:

- `make evaluation-policy-gate` — regenerates historical retrieval evidence,
  verifies the promoted baseline identity and exits non-zero on policy failure;
- `make evaluation-retrieval-current` — executes the current retrieval
  orchestration from the authored-plan boundary against its separately
  promoted deterministic execution-profile baseline;
- `make evaluation-generation-verify` — verifies generation populations,
  immutable observations, checksums and deterministic structural metrics
  without provider or evaluator calls.

`make evaluation-run` remains report generation, not a gate. Live retrieval,
generation and prompt-injection evaluation are explicitly invoked, bounded,
attributed evidence. They never become an implicit Playwright mode or a
fabricated pass when credentials are absent. `make evaluation-live-hybrid` is
expected once at the Phase 22 closure review when credentials and budget are
available, but remains observational and non-gating.

## Shared contract execution

The nine ingestion-worker and three document-deletion-worker HTTP operations
own versioned request/response schemas and shared fixtures under
`contracts/http/`. PHPUnit and Pytest consume the same positive and negative
vectors; neither language maintains a private copy of the other side's
contract. The ingestion fixture set also owns all twelve canonical HMAC
signing vectors. Retrieval rc1 follows the same rule with one fixture inventory
covering every committed request and response schema.

The generation integrity target is deliberately version-aware. It validates
GEN-EXP-0001 against its historical V1 shape and native V2 runs against the
current model, then recomputes deterministic evidence only. It never upgrades
historical data in place, invokes a semantic evaluator, or treats unavailable
semantic evidence as a newly reproduced score.

## Phase 23 handoff

Phase 23 may map these categories onto CI tiers, but it must invoke the same
repository commands and preserve their required/optional distinction. It must
not rename a cheaper test as E2E, turn optional paid evidence into an implicit
gate, or allow an integrity check to claim current live-model quality.
