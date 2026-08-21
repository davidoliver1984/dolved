# Phase 20 — Observability and Operations Closure

**Date:** 2026-08-21
**Status:** Completed
**Architecture:** ADR-0026 (Accepted 2026-08-20)

## Closure decision

Phase 20 is complete. Dolved now has privacy-safe structured logging,
operational metrics and platform-administrator visibility, coherent
cross-service traces with Collector-owned sampling, authenticated operational
policy reconciliation, and repository-owned SLO, alert and runbook boundaries.
Business audit, tenant usage and operational telemetry remain separate.

An independent review checked all nine ADR-0026 areas directly against the
implemented code. Seven were conformant without correction. Two bounded gate
findings were closed:

- the repository now has a standing test against the actual pinned Collector
  image, so a future image bump that drops `probabilistic_sampler` or changes
  the traces processor ordering fails the ordinary test gate;
- ADR-0026 has a dated post-acceptance factual clarification correcting its
  credential-family count. Ingestion and deletion intentionally share the
  ingestion-worker HMAC family through distinct signed purposes, while
  observability reconciliation and platform administration retain their own
  credentials and trust boundaries.

The unrelated verification debt carried from the Phase 19 closure was removed
in focused commit `fb294ed`. The correction made historical evaluation test
fixtures portable and explicit, preserved immutable experiment boundaries,
and resolved the five old Mypy test files without changing application,
retrieval, planner, benchmark or provider behaviour.

## Evidence

- `make format-check lint typecheck test ps` passed.
- Web formatting/lint, tests and TypeScript passed.
- Laravel Pint passed over 437 files; 330 tests passed, 2 skipped, with 1,606
  assertions.
- Python Ruff lint/format passed over 212 files; Mypy passed over 211 source
  files; 562 tests passed, 4 skipped.
- The pinned Collector image exposed `probabilistic_sampler`; its committed
  configuration validated and its traces pipeline orders sampling before
  batching.
- All required Compose services were running and healthy where health checks
  exist.
- `make aws-status` verified the local bucket, queue, DLQ and redrive policy.
- No provider calls were made and no calibration or held-out split was
  accessed.

## Next

R21-S01 — Define the Product Design System.
