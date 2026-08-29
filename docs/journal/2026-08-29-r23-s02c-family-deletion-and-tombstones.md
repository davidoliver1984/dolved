# R23-S02c — Family deletion and tombstones

## Outcome

The bounded ADR-0031 family-deletion path is implemented without introducing
ADR-0037 behavior.

The implementation adds:

- a tenant-authorised, read-only preview that reports classified versions,
  content counts, active-operation blockers, restoration limits and the
  immediate knowledge-gap consequence;
- a ten-minute HMAC-protected confirmation digest bound to the actor, family
  and complete observed deletion state;
- deterministic family-first and ascending-version locking on confirmation,
  with full state recomputation and fail-closed stale-preview rejection;
- a dedicated `pending → processing → completed / partially_failed` family
  deletion operation and one existing-shape child deletion per frozen version;
- truthful version disposition: current authority is withdrawn, scheduled
  authority is cancelled through the existing withdrawn representation,
  drafts remain `DRAFT`, and historical governance facts remain untouched;
- exact child cleanup evidence for source objects, extraction artifacts,
  projection generations and warnings, corpus assignments, chunks, dense and
  sparse points, and clone-manifest objects;
- parent reconciliation after child completion/failure and an immutable family
  tombstone only after all children complete;
- the reserved `documents.id` export coordination seam as a tested interface
  with a no-op implementation, without an export schema, hold lifecycle or
  other ADR-0037 behavior.

## Verification

- Focused family-deletion tests: 6 passed, 47 assertions.
- Focused source-delivery regression after refreshing the saturated disposable
  test workspace directory: 5 passed, 60 assertions.
- Full Laravel suite: 429 passed, 3 skipped, 2,307 assertions.
- Full Python suite: 649 passed, 4 skipped.
- Laravel Pint passed for 553 files.
- Ruff lint and format checks passed for 235 files.
- Mypy passed for 234 Python source files.
- `git diff --check` passed.

The initial full Laravel run found no application defect: its two failures came
from a disposable test directory that had accumulated the filesystem maximum
of 65,535 workspace subdirectories. The saturated directory was preserved under
a test-only stale name and a fresh empty directory restored the clean focused
and full results.

No provider calls were made. Retrieval, planner, generation, threshold,
calibration, benchmark and held-out behavior were unchanged. Unrelated local
notes, drafts, assets and duplicate evaluation files were not touched.

## Next

R23-S02d may run the complete provider-free ADR-0031 concealment, idempotency,
concurrency, clone, cleanup, tombstone and no-op export-hold acceptance matrix.
