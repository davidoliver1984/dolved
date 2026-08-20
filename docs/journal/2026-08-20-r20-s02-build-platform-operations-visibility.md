# R20-S02 — Operational Metrics, Platform Operations Foundation and Dashboard

**Date:** 2026-08-20
**Status:** Completed
**Architecture:** ADR-0026 (Accepted)

## What changed

Dolved now distinguishes platform operations authority from tenant authority.
Users have an immutable public identity, an optional disabled state and one
nullable platform role. The role is checked live and can be changed only by a
versioned deployment credential through a non-browser command. Commands,
mutations and content-free audit events commit atomically; the final active
administrator cannot be removed without an eligible replacement.

The operational metric surface now covers bounded application stages,
providers, database/object-storage/vector-store/queue dependencies, queue and
outbox age/depth, stuck ingestion/deletion work and first accepted streamed
answer-part latency. Identifiers remain absent from metric labels and telemetry
failures cannot change application work.

A narrow Laravel adapter issues only nine fixed Prometheus queries over the
internal network. It filters returned labels, caps result sets, caches briefly
and represents missing/backend-failed signals as unavailable rather than zero.
The server-rendered platform health page is available only through the live
platform Gate and links, rather than embeds, the specialist Grafana console.

## Verification

- Platform/telemetry focus: 16 Laravel tests, 136 assertions.
- Retrieval, reranking, generation and operational-metric focus: 53 Python
  tests.
- Web: 18 files, 56 tests; ESLint and TypeScript passed.
- Collectable Python: 549 passed, 3 skipped; the two remaining failures require
  the intentionally absent engineering expectations fixture.
- Laravel: 314 passed, 2 skipped; the eight remaining failures require the
  intentionally absent engineering corpus fixture.
- PostgreSQL migration SQL preflight, Ruff lint/format, Mypy, Pint, JSON
  validation and `git diff --check` passed.
- No provider calls were made.

## Boundaries preserved

Platform operations authority is additive and never grants workspace access.
Business audit, tenant usage and operational telemetry remain separate.
Retrieval, planning, generation, threshold, calibration, benchmark and held-out
behaviour did not change. Local ADR notes, drafts and assets were not included.

## Next

R20-S03: close trace-context and span coverage gaps, configure Collector-owned
sampling, and implement authenticated desired-policy reconciliation on top of
the platform authority completed here.
