# R20-S04 — Define operational SLOs, alerts and runbooks

Date: 2026-08-20
Outcome: Completed

## What changed

Accepted ADR-0026 was sufficient for the stage's architecture requirement. The
repository now owns a provisional SLO policy, Prometheus recording/alert rules,
a pinned local Alertmanager seam and operator runbooks. The curated platform
operations page shows bounded SLO and active-alert state while specialist tools
retain all alert mutation and diagnostic ownership.

## Important boundaries

Conversation availability counts completed, controlled no-answer and
clarification outcomes as technical success, counts only genuine failed runs as
failure, and excludes cancellation. Empty data is not converted to 100%.
Objectives remain explicitly provisional and unmeasured. Capacity,
telemetry-absence, final latency and multi-window burn alerts were not invented
without representative deployment evidence.

The local Alertmanager receiver deliberately delivers nowhere. It proves rule,
grouping, inhibition and API behavior without contacting a person; production
receiver selection remains Phase 22 work.

## Verification

Prometheus and Alertmanager config validators passed. Prometheus rule tests
proved controlled outcomes do not page, sustained technical failures do, and
an isolated provider rate limit does not. The live local stack loaded 16 healthy
rules and Alertmanager reported ready. Focused Laravel tests passed 8 tests / 64
assertions; focused web tests passed 2 files / 5 tests; ESLint, TypeScript, Pint,
Compose/JSON validation and `git diff --check` passed. No provider calls were
made. The full web suite passed 20 files / 61 tests. The Laravel suite passed
322 tests with 2 skipped; its eight failures are the known tests requiring the
intentionally absent engineering corpus fixture.

## Next

Run the Phase 20 acceptance gate before advancing to Phase 21.
