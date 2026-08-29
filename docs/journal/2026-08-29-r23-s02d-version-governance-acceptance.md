# R23-S02d — Version-governance acceptance

## Outcome

The complete provider-free ADR-0031 acceptance matrix is clean and Phase 23 is
closed.

The matrix verifies:

- tenant concealment, governance authorisation, durable command idempotency and
  family/version lineage revalidation;
- clone compatibility against the exact source checksum, completed publication
  evidence, active corpus generation and materialisation-pipeline fingerprint;
- target-owned ingestion claims, chunks, extraction projection, corpus
  assignments, vector point identity and publication evidence;
- atomic publication of the six cloned derived layers and fail-closed cleanup
  before ordinary-ingestion fallback;
- explicit `ingestion` versus `content_clone` callback, usage, telemetry and
  stuck-operation ownership;
- family deletion preview/confirm staleness, truthful current/scheduled/draft
  disposition, child convergence, tombstones and the no-op ADR-0037 export-hold
  seam.

## Defect found and bounded correction

The new end-to-end clone fixture exposed one application defect. The clone
materialiser calculated and supplied the deterministic target chunk
`public_id`, but `DocumentChunk` did not admit that creation field through its
mass-assignment boundary. SQLite therefore rejected the target chunk before
publication. `public_id` is now fillable at creation; the existing model guard
still rejects every update to the immutable canonical chunk row.

No clone or document reached `INDEXED` before the complete evidence set passed.
The failure fixture proves vector-evidence mismatch leaves the target
non-indexed, and fallback becomes available only after chunks, corpus
assignments, extraction artifacts/projections, clone manifests and vector
points are verified absent.

## Verification

- Focused target-lineage and cleanup acceptance: 4 passed, 37 assertions.
- Focused ADR-0031 Laravel matrix: 53 passed, 323 assertions before the final
  origin assertion correction; the corrected focused fixture passed cleanly.
- Full Laravel suite: 433 passed, 3 skipped, 2,344 assertions.
- Full Python suite: 649 passed, 4 skipped.
- Laravel Pint: 554 files clean.
- Ruff formatting and lint: 235 files clean.
- Mypy: 234 source files clean.
- ESLint and TypeScript: clean after removing only the stale generated `.next`
  cache contents.
- `git diff --check`: passed.

No provider calls were made. Retrieval, planner, generation, threshold,
calibration, benchmark and held-out behavior were unchanged. Unrelated local
notes, drafts, assets and duplicate evaluation files were not touched.

## Next

R23-GATE is complete. R24-S01 may begin the ADR-0033 contextual knowledge-
library navigation shell and must stop at its mandatory visual-review boundary.
