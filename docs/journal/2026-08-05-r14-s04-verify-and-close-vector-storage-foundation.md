# Session Journal: R14-S04 — Verify and Close the Vector Storage Foundation

## Date

2026-08-05

## Session objective

Verify the completed PostgreSQL and Qdrant foundations against ADR 0014, record
their final boundary and close Phase 14 without beginning the unresolved
cross-service ingestion orchestration assigned to Phase 15.

## What was verified

PostgreSQL remains authoritative for canonical chunk identity, text, ordinal,
token count, chunking configuration and source-element provenance. It also owns
embedding-profile lineage, embedding-space and workspace-corpus generation
lifecycle, corpus membership and each workspace's active corpus pointer. Its
constraints, partial unique index and triggers enforce the accepted relational
and lifecycle invariants, and it stores no raw vectors.

Qdrant remains a disposable, rebuildable projection behind Python's
provider-neutral `VectorStore`. Operations require explicit workspace and
workspace-corpus-generation scope. Deterministic UUIDv5 point identities make
repeated upserts safe; collection and payload-index provisioning are
idempotent; completeness verification compares point identities, payload values
and vector schema rather than counts alone. Activation remains outside the
vector-store boundary.

The V1 collection uses the named `dense` vector with 1,024 dimensions and
cosine distance. Keyword payload indexes exist for `workspace_id`,
`workspace_corpus_generation_id` and `document_id`.

## Verification evidence

* Focused Laravel vector-persistence suite: 19 tests passed with 59 assertions.
* Focused Python vector-store suites: 13 tests passed against local Qdrant.
* A clean disposable PostgreSQL database completed a full migration, full
  rollback and full reapplication. Catalog inspection confirmed the expected
  tables, foreign keys, indexes, lifecycle triggers and absence of vector
  columns. The database was removed afterward.
* A disposable Qdrant collection retained its schema, three payload indexes and
  test point across a forced container recreation using the existing persistent
  volume. The collection was removed afterward.
* Frontend: 7 test files and 26 tests passed.
* Laravel: 146 tests passed with 627 assertions.
* Python: 181 tests passed; the existing credential-dependent live Voyage test
  was skipped as designed.
* ESLint, Pint (131 files), Ruff formatting and lint, TypeScript, MyPy (73 source
  files), Compose validation, Composer validation, container health and Qdrant
  shard readiness all passed.

## Defects and corrections

No defects were found and no application code required correction.

## Boundary held

No worker callback, canonical chunk transfer, completion or failure report,
Document lifecycle outcome, full-pipeline SQS acknowledgement, callback
idempotency, initial generation provisioning or upload-to-`INDEXED` test was
implemented. ADR 0014 and ADR 0009 were not changed. ADR 0015 was not drafted,
and Phase 15 implementation did not begin.

`PROJECT_JOURNEY.md` already records the completed Phase 14 vector-storage
foundation, so this verification-only session did not duplicate that narrative.

## Commit boundary

Proposed commit: `Verify vector storage foundation`

Proposed annotated tags: `phase-14-s04` and `phase-14`
