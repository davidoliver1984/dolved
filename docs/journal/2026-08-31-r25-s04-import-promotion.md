# R25-S04 — Import promotion state machine

**Date:** 2026-08-31
**Status:** Completed

## Delivered boundary

R25-S04 implements ADR-0034's promotion boundary without adding routes, UI or
legacy cutover behavior. A verified and resolved `ImportItem` receives an
immutable canonical decision snapshot. Promotion then reserves a
content-addressed destination, claims a generation-fenced lease, materialises
and verifies the exact source bytes, and commits through one Laravel-owned
transaction.

The final transaction acquires the shared workspace/checksum reservation first
and then revalidates the current decision, live actor membership, duplicate
state, selected family/predecessor, family-owned metadata, applicability and
effective date. It creates the document, applicability snapshot, family
activity, ingestion request and committed promotion result atomically. Changed
decisions, changed authorization, duplicates and invalidated predecessors
become typed terminal conflicts. Stale leases fail closed.

The finalizer delegates document creation, lineage, applicability and activity
maintenance to ADR-0031's existing `CreateDocumentVersion` domain action via a
verified-promotion entry point; it does not duplicate that ownership boundary.

## Storage and recovery

The development S3-compatible bucket now has versioning enabled. Promotion
records the exact verified version ID on the committed document; document reads
request that exact version, and PostgreSQL rejects later storage-version
identity changes. Existing destination content is reused only after its current
version is re-read and its checksum and size match. An uncommitted terminal
attempt may delete only its recorded version. Once a document is committed,
cleanup refuses because storage ownership has transferred to the document.

Cancellation, failure ceilings and expired-lease reconciliation are durable
and generation-fenced. Adoption is restricted to the current item owner or a
workspace administrator, requires a new decision snapshot and actor identity,
and preserves the earlier attempt rather than rewriting it.

## Verification

- Complete Laravel suite: 494 passed, 5 skipped, 2,655 assertions.
- Focused promotion/matching/clone/ingestion regression: 39 passed,
  214 assertions.
- Fresh PostgreSQL promotion profile: 9 passed, 49 assertions.
- Existing PostgreSQL checksum-serialization profile: 2 passed, 9 assertions.
- PostgreSQL catalog inspection confirmed the workspace-bound committed-
  document foreign key, terminal-result CHECK and document storage-version
  immutability trigger.
- Real LocalStack checks proved bucket versioning, verified object reuse,
  exact-version reads after a newer object version exists and exact-version
  terminal cleanup.
- LocalStack provisioning verification, Pint, shell syntax and
  `git diff --check` passed.

No provider was called. Retrieval, planner, RRF, reranker, threshold,
calibration, held-out, benchmark and application answer semantics were not
changed.

## Next

R25-S05 may reconcile ADR-0034's legacy direct-upload marker, perform the
bounded cutover and prove the drain gate. The legacy route must not be used as
a substitute for the real import workflow or the mandatory R25-S07 journey.
