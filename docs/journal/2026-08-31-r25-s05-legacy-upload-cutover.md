# R25-S05 — Legacy upload cutover and drain

**Date:** 2026-08-31
**Status:** Completed

## Delivered boundary

R25-S05 implements ADR-0034's temporary legacy browser-upload cutover without
turning that path into an alternative import workflow. A singleton PostgreSQL
gate carries one immutable cutover-operation identity. Every still-permitted
initializer locks that gate and records the server-owned legacy marker and a
human audit fact in the document-creation transaction. A resumable bounded
inventory marks pre-existing `UPLOADING` and `UPLOADED` rows with system audit
facts. The final bounded transaction locks the same gate, handles the final
remainder, proves that no eligible row remains unmarked and closes
initialisation atomically.

Browser completion and browser ingestion request now pass through distinct
marked-only continuation actions. They remain available during the bounded
drain but reject post-cutover or mismatched identities and close once the
drain is complete. The shared internal `RequestDocumentIngestion` action and
worker callbacks are unchanged, so ADR-0034 promotion continues into the
ordinary ingestion pipeline.

The new drain reconciler considers `QUEUED` sufficient for browser-upload
drain completion without misrepresenting it as terminal in the document
lifecycle. Its dedicated expiry action can move only the exact marked
population from stalled `UPLOADING`/`UPLOADED` to `FAILED`, writing the
existing required failure fields with category
`legacy_upload_drain_expired`. It cannot be used as a general document
transition.

## Local cutover evidence

The migration and bounded cutover command were applied to the shared local
development database after all provider-free gates passed.

- Cutover operation: `d09cc97a-68e7-4f4f-aee2-f9a5fc755ccb`.
- Pending candidates before cutover: 15 (1 uploading, 14 uploaded).
- Pending candidates marked after cutover: 15.
- Pending candidates unmarked after cutover: 0.
- Per-row system audit facts: 15, all `inventory_backfill`.
- Gate-closure summary events: 1, bound to a total marked count of 15.
- Initialisation gate: closed at `2026-08-31 14:49:43+00`.
- Drain: open; no document was expired or otherwise advanced by this stage.

## Verification

- Focused legacy upload/ingestion regression: 47 passed, 273 assertions.
- Versioning/retrieval/cutover regression after the SQLite migration-path
  correction: 54 passed, 266 assertions.
- Complete Laravel suite: 502 passed, 6 skipped, 2,694 assertions.
- Clean fresh-PostgreSQL migration passed.
- PostgreSQL gate serialization and immutable marker/gate proof: 1 test,
  6 assertions.
- PostgreSQL catalog inspection confirmed all S05 checks, foreign keys,
  uniqueness rules and both forward-only triggers.
- Pint, API lint and `git diff --check` passed.

The first complete-suite run exposed that Laravel's SQLite table-rebuild path
would discard the predicate from an established partial document-family
index when adding the new foreign key. The migration was corrected to add
that production foreign key with native PostgreSQL DDL; focused and complete
regressions then passed. The PostgreSQL schema and constraint remained the
same.

No provider was called. Retrieval, planner, RRF, reranker, threshold,
calibration, held-out, benchmark, import promotion and application-answer
semantics were not changed. Unrelated untracked notes, drafts, assets and
historical evaluation copies remain untouched.

## Next

R25-S06 may replace the now-closed direct-upload browser surface with the real
ADR-0034 import, staging, review, matching and promotion workflow. Every
named visual checkpoint remains subject to David's explicit approval.
