# R23-S02b — Clone contract and orchestration

## Outcome

The bounded ADR-0031 applicability-only content-reuse path is implemented
against ADR-0032's verified structured-extraction identity.

The implementation adds:

- the exact `ingestion` / `content_clone` attempt-origin vocabulary and an
  audited consumer sweep across callbacks, workspace usage, operational stuck
  counts and telemetry labels;
- a durable write-once materialisation-pipeline fingerprint and component map
  on every ingestion claim, with clone compatibility recomputing the current
  identity and rejecting pipeline drift;
- tenant- and document-bound clone operations, target-owned claims and bounded
  immutable clone manifests;
- the governed applicability-only successor endpoint, deterministic
  family-first/ascending-version locking and durable command idempotency;
- Laravel ownership of source, artifact, projection, warning, chunk and corpus
  assignment copies, with Python ownership limited to exact Qdrant vector
  copy, independent completeness verification, publication and cleanup;
- an explicit guarded clone state machine, verified-derived-content cleanup
  before ordinary-ingestion fallback, and one atomic target-claim/`INDEXED`
  completion transaction;
- a bounded database-led exact-key manifest cleanup sweep.

The target claim is the materialisation identity everywhere: target chunks and
Qdrant payloads use its event identity, provider usage is recorded truthfully
as zero, and clone attempts remain separately observable from ordinary
ingestion. Applicability remains Laravel-owned and is not added to vector
payloads.

## Verification

- Focused clone/origin/governance tests: 9 passed, 46 assertions.
- Full Laravel suite: 423 passed, 3 skipped, 2,257 assertions.
- Full Python suite: 649 passed, 4 skipped.
- Laravel Pint passed for 540 files.
- Ruff lint and format checks passed for 235 files.
- Mypy passed for 148 Python source files.
- The applicability-successor route registered successfully.

An initial full Laravel run exposed stale local test-storage permissions in two
pre-existing source-delivery fixtures. Restoring write access on the disposable
test directory and rerunning produced the clean full result above; no source
change was required for that environment-only condition.

No provider calls were made. Retrieval, planner, generation, threshold,
calibration, benchmark and held-out behavior were unchanged. Family deletion,
tombstones, export holds and UI work remain outside this session.

## Next

R23-S02c may implement ADR-0031 family deletion and tombstones with only the
already-reserved, tested no-op ADR-0037 export-hold seam.
