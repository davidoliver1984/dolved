# R26-S02 — Frozen bulk domain

Date: 1 September 2026
Status: Complete

## Outcome

ADR-0035 now has its complete provider-free domain and membership boundary.
The implementation freezes a bounded, deterministic target population, records
typed eligibility truth for every V1 operation and stops at
`awaiting_confirmation`; queue execution remains deliberately unimplemented
until R26-S03.

## Implemented boundary

- Added `BulkOperation`, `BulkOperationItem`, `BulkOperationItemAttempt` and
  `BulkOperationItemSubordinateTransition` models, enums and relational schema.
- Enforced actor XOR provenance, operation and target discriminators, immutable
  target identity, full item truth-table shapes, operation-specific result
  shapes, attempt evidence shapes, incorporation lineage and subordinate
  identity bindings in PostgreSQL.
- Added target-side retirement, workspace, immutability and incorporation
  triggers plus narrowed runtime column grants under the R26-S01 role model.
- Added owner/admin API creation and inspection with workspace concealment,
  request idempotency and a deterministic membership digest.
- Added bounded `current_page` and server-resolved `all_filtered` membership.
  Library membership uses the same shared query builder as the visible document
  library; promotion membership is bound to ADR-0034 `ImportItem` state.
- Implemented typed preflight/exclusion classification for approval, promotion,
  applicability, owner, category, tag and review-date operations.
- Kept confirmation, claiming, mutation, retry and subordinate reconciliation
  outside this session so no operation can be queued before R26-S03 exists.

## Verification

- Focused bulk foundation and terminal-state suite: 16 tests, 71 assertions.
- Complete Laravel suite: 524 passed with six documented skips.
- Pint: 686 files passed.
- PostgreSQL bulk verifier: 17 validated constraints, four partial uniqueness
  backstops, seven integrity triggers and transaction-rolled-back invalid-state,
  discriminator and concurrent-open-attempt probes passed.
- PostgreSQL runtime-role verification passed with the protected bulk table and
  column grant boundary.
- Compose validation and `git diff --check` passed.

No external provider was called. Retrieval, planner, reranker, threshold,
calibration, held-out and benchmark behavior did not change. Historical and
unrelated local files remain untouched.

## Next

R26-S03 may implement the accepted generation-aware claim algorithm, fenced
mutation and incorporation, retry ceiling, cancellation convergence,
subordinate reconciliation and complete bulk audit chain over this frozen
domain.
