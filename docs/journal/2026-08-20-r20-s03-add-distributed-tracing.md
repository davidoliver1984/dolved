# R20-S03 — Trace Coverage, Collector Sampling and Policy Reconciliation

**Date:** 2026-08-20
**Status:** Completed
**Architecture:** ADR-0026 (Accepted)

## What changed

Laravel now propagates the active W3C trace context to every synchronous
Python rc1 boundary. Retry and deletion outbox events retain their originating
context, generation jobs restore it, and bounded first-instance spans cover
the missing administration, contextualisation, generation, reranking and
deletion operations. Every added attribute goes through the existing PHP or
Python allowlist.

The pinned Collector now owns the only probabilistic ratio sampler. Application
SDKs remain AlwaysOn/default and export complete traces to the Collector, which
makes one trace-ID-consistent retention decision.

Operational sampling and retention settings are immutable desired-policy
versions expanded through a versioned required-target manifest. Per-target
state changes only through an authenticated plan and acknowledgement protocol.
Deployment attempts are append-only; a current-attempt pointer makes stale and
superseded results inert; identical delivery is idempotent; conflicting,
cross-target and replayed delivery fails closed. The protocol uses dedicated
rotatable HMAC credentials and never exposes secrets or arbitrary payloads in
logs.

The platform operations UI displays desired, per-setting and per-target state.
It cannot self-declare a setting active. The local Collector reconciler applies
only the sampling setting, validates the exact pinned Collector configuration,
inspects the effective container value and then acknowledges the result.

## Live proof

A real local policy version reconciled `trace_sampling_percentage` to the
Collector and reached `ACTIVE`. The aggregate truthfully reported 1 of 5
settings active; retention and alerting/application targets remained pending.
The existing cross-service smoke was corrected to the current Laravel
`{eventId}` route-template name, then verified one Tempo trace across Laravel
API, outbox publisher and Python worker. Its synthetic sensitive marker was
absent and entity identifiers remained absent from metric labels.

## Verification

- Focused Laravel: 57 tests, 338 assertions.
- Web: 19 files, 58 tests; ESLint and TypeScript passed.
- Python provider/telemetry focus: 54 tests; Ruff and focused Mypy passed over
  142 source files.
- Collectable Python: 549 passed, 3 skipped; two known failures require the
  physically absent engineering expectations fixture.
- Laravel: 320 passed, 2 skipped; eight known failures require the physically
  absent engineering corpus fixture.
- PostgreSQL migration SQL preflight and local migration, Pint, pinned
  Collector validation, JSON validation and `git diff --check` passed.
- No OpenAI, Voyage or other provider calls were made.

## Boundaries preserved

Operational telemetry remains separate from business audit and tenant usage.
Desired policy is not effective state. Retrieval, planning, generation,
threshold, calibration, benchmark and held-out behaviour did not change. Local
ADR notes, drafts and assets were not included.

## Next

R20-S04 begins with architecture review to define calibrated SLIs/SLOs,
actionable alerts and operational runbooks.
