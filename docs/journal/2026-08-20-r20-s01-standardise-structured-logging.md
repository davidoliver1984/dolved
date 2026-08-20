# R20-S01 — Standardise Structured Logging

**Date:** 2026-08-20
**Status:** Completed
**Architecture:** ADR-0026 (Accepted)

## What changed

Dolved now emits one bounded, machine-readable logging vocabulary across
Laravel, Python HTTP and worker processes, and Next.js server code. Stable
event names, service/environment identity, trace context and allowlisted
durable identifiers replace ad-hoc log context at the central boundary.

The formatters are privacy-safe by construction: unknown fields are discarded
rather than copied, and exception messages, locals and arguments never enter
the record. Errors retain only their type and bounded source-frame metadata.
Failure-isolated handlers preserve ADR-0012's invariant that a logging failure
cannot fail the request or job being observed.

## Verification

- Laravel focused logging tests passed: 3 tests, 11 assertions.
- Python focused logging and adjacent ingestion/embedding tests passed: 33.
- The complete web suite passed: 17 files, 52 tests; ESLint and TypeScript
  passed.
- The collectable Python suite passed 548 tests with 3 skipped. Two historical
  evaluation tests failed only because the isolated engineering fixture is
  intentionally absent.
- Laravel passed 306 tests with 2 skipped. Eight historical evaluation tests
  failed for the same absent-fixture reason.
- Ruff lint/format, Mypy, Pint, JSON validation and `git diff --check` passed.
- No provider calls were made.

## Boundaries preserved

Business audit, tenant usage and operational telemetry remain separate.
Retrieval, planning, generation, threshold, calibration, benchmark and
held-out behaviour did not change. The unrelated local ADR notes, draft files
and assets were not included.

## Next

R20-S02: establish the separately authorised platform operations plane,
complete the operational metric surface and expose its bounded health
dashboard.
