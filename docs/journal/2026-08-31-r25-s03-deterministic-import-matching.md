# R25-S03 — Deterministic import matching

**Date:** 2026-08-31
**Status:** Completed

## Outcome

Implemented ADR-0034's deterministic matching boundary without starting
promotion. A verified import item can now be assessed against same-workspace
documents by verified checksum and against live family titles by one versioned,
provider-free normalisation and scoring profile.

Exact matches treat `UPLOADED`, `QUEUED`, `PROCESSING`, `INDEXED` and `FAILED`
documents as live duplicates regardless of governance state. Matches against
`DELETING` or `DELETED` documents remain separate informational history.
Applicability-only intent is represented as a redirect to ADR-0031's existing
successor action rather than a second identical-content version.

Possible family matches use only the normalised source filename stem and family
title. Unicode NFC, Unicode case folding, punctuation removal, whitespace
collapse, control-character rejection and a configured length cap are applied
before a deterministic Levenshtein score. Results are thresholded by the
tracked `family-title-levenshtein-v1` profile, limited to five and sorted by
score descending then family public ID ascending. Matching never mutates the
item's decision or chooses a family automatically.

## Shared serialization boundary

Added a single `WorkspaceChecksumLock` primitive for the durable
`(workspace_id, source_checksum_sha256)` reservation. It requires an active
database transaction, creates the reservation idempotently and then locks the
row `FOR UPDATE`.

Both ADR-0031 clone completion paths now acquire this reservation as their
first final-transaction lock and re-query the verified live source afterward.
If that source is no longer valid, completion fails closed and the transaction
rolls back. No lease or application-managed unlock was introduced.

The promotion consumer does not exist until R25-S04. Consequently, S03 proves
the primitive and current clone consumers, while R25-S04 and R25-S07 retain the
complete import-versus-clone and promotion race matrix. This record makes no
claim that those future races have already been executed.

## Verification

- Complete Laravel suite: 485 passed, 3 skipped, 2,606 assertions.
- Isolated real-PostgreSQL serialization profile: 2 passed, 9 assertions.
- Concurrent first insertion, existing-row reuse, rollback/recreation,
  same-workspace contention and cross-workspace independence passed.
- Matching, immutable source identity and clone regressions passed.
- Pint passed across 619 files.
- Complete Python suite re-run unchanged; Ruff and Mypy remained clean.
- `tasks.json` parsed successfully.
- `git diff --check` passed.

No provider was called. No promotion, decision snapshot, import UI, legacy
cutover, retrieval, planner, threshold, calibration or held-out behavior was
changed.

## Next

R25-S04 implements the promotion state machine, immutable decision-snapshot
consumption, live authorization at commit, adoption, staging ownership transfer
and the promotion side of the shared checksum-serialization protocol.
