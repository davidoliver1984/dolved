# R23-S01c — Backfill document metadata

## Outcome

Implemented and ran ADR-0030's bounded, resumable legacy metadata backfill.
The command processes owner, title, audit-lineage and checksum lanes in stable
identity order with a configurable maximum of 1,000 records per lane.

Legacy family titles are reinterpreted only while they still exactly equal the
lineage-root source filename. New families now receive the same deterministic
basename-without-extension title derivation. User-renamed titles are not
overwritten.

Owner assignment uses the lineage-root uploader identity regardless of current
membership eligibility, falling back to the workspace creator only when no
lineage-root identity exists. Completion validates the transitional PostgreSQL
owner constraint, makes `owner_user_id` non-nullable and removes the temporary
constraint.

Legacy checksum reads use the same bounded streamed SHA-256 implementation as
new upload completion. Confirmed absence becomes `unavailable`; a size mismatch
becomes `source_unrecoverable`; transient storage errors remain `pending` and
retryable. Audit records contain only the algorithm, bounded outcome and stable
identities, never source bytes or storage keys.

## Local backfill evidence

- Population: 163 document families and 207 document versions.
- Owners assigned: 163.
- Titles reinterpreted: 20.
- Audit-lineage markers: 163.
- Checksums verified: 0.
- Confirmed source-missing outcomes: 207.
- Transient/retryable failures: 0.
- Remaining work after the first pass: 0.
- Immediate second pass: all counters zero, proving idempotent resumability.
- Final PostgreSQL owner nullability: `NO`.
- Final named system-event reconciliation: 553 events, exactly
  163 owner + 20 title + 163 audit-lineage + 207 checksum outcomes.

The local retained object store contained none of the 207 legacy source keys,
so the run truthfully recorded `source_missing`; it did not fabricate checksums
or treat absence as a transient provider failure. Existing citations,
tombstones, retrieval authority and applicability remain unchanged.

## Provider-free verification

- Focused metadata/backfill tests: 15 passed, 57 assertions.
- Full Laravel suite: 379 passed, 2 skipped, 2,017 assertions.
- Laravel Pint: 485 files passed.
- PostgreSQL paths covered owner constraint finalisation and the bounded
  system-actor vocabulary.
- Confirmed transient storage failure remains pending and retryable.
- Confirmed missing and size-mismatched sources become bounded unavailable
  outcomes.
- Confirmed lineage-root and workspace-creator owner paths.
- `git diff --check`: passed before final verification.

No provider calls were made. Retrieval, planner, generation, threshold,
calibration, benchmark, held-out, temporal authority and applicability behavior
were not changed.

## Next

R23-S01d may run the complete ADR-0030 provider-free acceptance matrix.
