# R23-S03c — Atomic projection publication

## Outcome

The provider-free ADR-0032 generation-bound projection-publication boundary is
complete.

Implemented:

- tenant-scoped projection generations, elements and warnings bound to the
  immutable verified extraction-artifact identity;
- an exact-key streaming artifact reader and bounded database inserts that do
  not materialise the canonical artifact in application memory;
- independent persisted-row count and ordered-manifest verification before a
  generation can become visible;
- one transactional publish operation that retires the prior generation,
  publishes the verified generation and switches the document's active pointer;
- PostgreSQL-generated full-text vectors and a GIN index over published element
  text without moving extraction ownership into Laravel;
- typed, durable failure state for incomplete or mismatched builds, with failed
  generations remaining invisible;
- bounded scheduled cleanup for inactive stale building, failed and retired
  generations while the active generation remains protected;
- idempotent acknowledgement and crash recovery: a verified upload is not
  transferred again, but its immutable artifact may be acknowledged again to
  resume projection publication into a fresh inactive generation.

No browser source-delivery or extracted-text route was introduced. That remains
owned by R23-S03d.

## Verification

- Focused Laravel projection, canonicalisation and worker-contract tests: 14
  passed, 166 assertions.
- Version-governance regression plus projection tests: 18 passed, 86 assertions.
- Full Laravel suite: 398 passed, 3 skipped, 2,144 assertions.
- Focused Python ingestion and contract tests: 21 passed.
- Full Python suite: 637 passed, 4 skipped.
- Laravel Pint: passed for 517 files.
- Ruff lint and formatting: passed for 230 files.
- Mypy: passed for 144 source files.
- PostgreSQL migration preview, JSON validation and `git diff --check`: passed.

No providers were called. Retrieval, planner, generation, threshold,
calibration, benchmark, held-out, authority and tenancy behavior were unchanged.

## Next

R23-S03d may implement tenant-authorised Range/HEAD-capable original-source
delivery and bounded structured/extracted-text delivery against the active
verified projection only.
