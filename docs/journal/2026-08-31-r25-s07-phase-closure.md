# R25-S07 — Import acceptance and Phase 25 closure

Date: 31 August 2026
Status: Complete

## Outcome

Phase 25 is complete. The accepted ADR-0034 import workflow passed its full
provider-free and Playwright acceptance boundary, including ADR-0033's deferred
small-corpus journey. The evidence comes from the real `ImportBatch` staging,
preflight, matching, immutable review, promotion and ordinary ingestion path;
the legacy direct-upload flow was not used as a substitute.

## Browser acceptance evidence

- A mixed batch of ten representative text documents was staged concurrently.
- The durable batch was left and resumed before review.
- Every item completed preflight, matching, immutable review and promotion.
- All ten promoted documents reached `INDEXED`, were approved/current-authorised
  and produced exactly ten genuinely searchable document families.
- Retrieval returned the expected medication EvidenceUnit.
- The conversation flow rendered a grounded answer and a valid source citation
  linked to the authorised document detail page.
- A follow-up requiring an unsupported exact time limit produced the controlled
  insufficient-evidence answer and preserved stream reconnection behavior.
- A second workspace could discover neither the workspace, conversation nor
  source document.
- An exact live duplicate was blocked. A corrected source was staged as a
  set-once replacement inside the same batch, preserving the original item as
  immutable lineage before the correction was reviewed, promoted and indexed.
- A promotion whose initiating actor lost authority terminalised as `CONFLICT`.
  A different currently authorised actor created a revised immutable decision
  and explicitly adopted it through to `Indexed`.

## Bounded implementation corrections

- Exposed the existing adoption domain primitive through the authenticated API
  and import UI, while requiring a prior conflict and a different actor.
- Added same-batch corrected-source replacement with deterministic batch/item
  locking, set-once relational lineage and ordinary staging/preflight reuse.
- Restored SQLite's intended partial open-attempt unique index after Laravel's
  table reconstruction had widened it during tests. PostgreSQL behavior is
  unchanged.

## Verification

- Playwright: 2 passed in the isolated `dolved-e2e` project; resources removed
  after success.
- Laravel: 508 passed, 6 skipped, 2,734 assertions.
- Web: 37 files, 147 tests passed.
- Python: 654 passed, 4 skipped.
- Pint, ESLint, Ruff lint/format, TypeScript and Mypy passed.
- Collector sampling configuration validation passed.
- `git diff --check` passed.
- No external provider was called.

## Gate decision

R25-S07 and R25-GATE pass. Phase 26 may begin at R26-S01. Export/import
interchange remains excluded for the later ADR-0037 boundary.
