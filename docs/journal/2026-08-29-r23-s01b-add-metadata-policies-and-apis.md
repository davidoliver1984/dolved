# R23-S01b — Add metadata policies and APIs

**Date:** 2026-08-29
**Status:** Complete

## What changed

R23-S01b implemented ADR-0030's authorised metadata application boundary.
Active workspace members may read workspace categories, tags and family
metadata. Only workspace owners and administrators may mutate that metadata;
inaccessible workspaces and cross-workspace family, category, tag and owner
identities remain concealed.

Family rename is a separate audited action. Description, category, recorded
owner and advisory review date update through a distinct audited action. Owner
eligibility is checked live against both current workspace membership and the
user's enabled state, while recorded ownership remains metadata only and grants
no retrieval, applicability, governance or access authority.

Tag replacement locks the `DocumentFamily` row, re-resolves the requested final
workspace-scoped set while holding that lock, rejects more than 20 tags and
applies attach/detach changes in the same transaction. Categories archive rather
than being hard deleted.

New uploads accept bounded publisher labels and strictly validated absolute
HTTPS source URLs. Source URLs reject credentials, queries, fragments, control
characters and internal storage-key-shaped paths, and are never fetched.
Successor versions default both source fields from their immediate predecessor.

Upload completion now streams the retained source in bounded chunks, verifies
the authorised byte count, computes SHA-256 and atomically records the verified
checksum with the `UPLOADING` to `UPLOADED` transition. Missing, unreadable or
size-mismatched new uploads do not advance.

Legacy reinterpretation, checksum backfill and owner backfill were not started;
they remain exclusively allocated to R23-S01c.

## Verification

- Focused metadata and upload tests: 31 passed.
- Actual streamed-object regression covered a multi-megabyte retained source and
  verified its exact SHA-256.
- Full Laravel suite: 373 passed, 2 skipped, 1,984 assertions.
- Laravel Pint: 480 files passed.
- `git diff --check`: passed.

No provider calls were made. Retrieval, planner, generation, applicability,
version authority, threshold, calibration, benchmark and held-out behaviour were
unchanged.

## Next

R23-S01c may implement only ADR-0030's bounded, resumable legacy metadata,
owner, audit-lineage and streamed-checksum backfill.
