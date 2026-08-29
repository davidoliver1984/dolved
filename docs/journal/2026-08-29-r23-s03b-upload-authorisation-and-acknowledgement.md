# R23-S03b — Upload authorisation and worker acknowledgement

## Outcome

The provider-free ADR-0032 lease-bound artifact-upload and orphan-recovery
boundary is complete.

Implemented:

- durable upload-authorisation and immutable verified-artifact records bound to
  workspace, document, ingestion claim, purpose, exact object key, contract
  version, expiry and lease generation;
- two purpose-scoped, shared Laravel/Python HTTP contracts for upload
  authorisation and acknowledgement, plus the durable lease generation in the
  claim response;
- exact-key presigned `PUT` authorisation with `If-None-Match: *` and bounded
  size/expiry;
- Python canonical-artifact construction and a single-attempt upload adapter
  that cannot choose or broaden the key;
- Laravel-side independent streamed SHA-256, byte-size and contract-version
  verification before one immutable acknowledgement is accepted;
- fail-closed stale-generation, incomplete-upload, identity-mismatch,
  contract-mismatch and conflicting-acknowledgement behavior;
- bounded database-led orphan selection and claiming, live-lease and published
  artifact protection, exact-key deletion, durable retry/exhaustion evidence,
  scheduled execution and low-cardinality stuck-cleanup telemetry;
- cancellation, failure and lease-reclamation transitions that make abandoned
  upload authorisations eligible for safe cleanup.

No structured projection was published and no browser source or extracted-text
delivery route was introduced. Those remain owned by R23-S03c and R23-S03d.

## Verification

- Focused Laravel extraction/worker/orchestration tests: 19 passed, 195
  assertions.
- Full Laravel suite: 396 passed, 3 skipped, 2,121 assertions.
- Full Python suite: 636 passed, 4 skipped.
- Ruff lint and formatting: passed for 230 files.
- Mypy: passed for 144 source files.
- Shared request/response schemas, operation vectors and HMAC signatures passed
  independently in Laravel and Python.
- Laravel Pint and `git diff --check`: passed.

No provider calls were made. Retrieval, planner, generation, threshold,
calibration, benchmark, held-out, chunking, authority and tenancy behavior were
unchanged.

## Next

R23-S03c may implement generation-bound, verify-then-switch atomic structured
projection publication against the immutable verified artifact identity.
