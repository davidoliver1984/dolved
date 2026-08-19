# R19-S01 — Build Document Administration

**Date:** 2026-08-19
**Status:** Completed
**Architecture:** ADR-0025 (Accepted)

## What changed

The workspace now has an authoritative document-administration surface rather
than relying on upload controls alone. Active members can inspect documents,
status, metadata, extraction warnings and safe failure information. Owners and
admins can request an idempotent ingestion retry or asynchronous deletion; the
server remains the source of both permissions and lifecycle truth.

Deletion deliberately reuses the established outbox, queue, HMAC and lease
patterns. Laravel first moves the document to `DELETING`, prevents ingestion
from reclaiming or renewing work, and waits for the active-attempt snapshot to
cancel or expire. It then sends Python the exact bounded vector scopes that
must be removed. Python performs only those Qdrant operations and reports
verifiable outcomes; Laravel alone removes the object and relational source
content and marks the document `DELETED`.

## Important correctness details

Retry does not create another source document and a repeated idempotency key
cannot create another ingestion event. A deletion command has one open durable
operation per document and does not treat an unavailable vector store as an
empty collection. In-flight ingestion cannot publish after deletion begins,
and cancellation is not misclassified as an ingestion failure.

Historical citations remain durable. Evidence snapshots now own stable source
chunk and ingestion-event lineage before their live chunk reference is allowed
to become null. Deleting source content therefore removes it from future
retrieval without erasing the cited text already preserved with an accepted
answer.

## Verification

- Laravel focused verification: 79 tests, 389 assertions passed.
- Laravel broad verification: 287 passed and 2 skipped; 8 evaluation tests
  could not run because the isolated runtime intentionally does not mount the
  engineering benchmark.
- Python focused verification: 35 tests passed.
- Python broad compatible verification: 544 passed and 3 skipped; two tests
  require the absent engineering expectations mount.
- Ruff, Ruff format, Mypy and Pint passed for the application and changed
  boundary.
- Web verification: 11 files and 33 tests passed; ESLint, TypeScript and the
  production build passed.
- JSON and diff checks passed.
- No provider calls were made.

## Next

R19-S02 — Build Tenant and Membership Administration.
