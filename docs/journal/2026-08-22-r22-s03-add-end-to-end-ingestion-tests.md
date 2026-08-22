# R22-S03 — Add end-to-end ingestion tests

**Date:** 2026-08-22  
**Status:** In progress — implementation verified; deterministic baseline review pending

## What changed

R22-S03 now has a disposable, provider-free Playwright environment that proves
the document journey through the real internal stack. A real browser signs in,
uploads a representative text document and a corrupt PDF, and observes both
authoritative outcomes. Laravel persists and publishes the ingestion event,
LocalStack carries it, the real Python worker parses and chunks the successful
document, deterministic dense and sparse adapters materialise it in real Qdrant,
and Laravel receives the signed completion. The same journey retrieves the
expected evidence and proves that a second workspace receives concealment rather
than access.

The deterministic adapters are selected through the normal settings/factory
seams and are permitted only in the isolated E2E and current-evaluation
environments. A complete deterministic tuple is mandatory in E2E, provider
credentials must be absent, catalogue planning is exact-question and fail-closed,
and no browser-controlled switch can change adapter behaviour.

The heavyweight sparse-model boundary is kept honest by a separate
`make test-splade-integration` target that loads the real configured SPLADE model
and performs bounded inference. The Playwright journey substitutes that model for
speed and determinism and therefore makes no claim about sparse-model quality.

The first current-retrieval implementation was rejected during its read-only
conformance audit. It derived eligibility scopes and searchable chunks from
expected EvidenceUnits, bypassed Laravel's real eligibility resolver, treated
controlled outcomes as automatically correct, and used a smaller generic
population. Its 23-case / 25-variant output is therefore diagnostic history only
and is not eligible for promotion.

The corrected current retrieval gate uses the approved 42-case / 126-variant
engineering snapshot and the independent 93-version document catalogue. A
private Laravel command, guarded to the disposable evaluation environment,
persists the real organisation, aliases, document families, authority windows,
applicability and active generations. It then invokes the production
`BuildAuthorisedKnowledgeScope` and `EligibilityResolver` for every authored
plan at the fixed evaluation clock. The typed Laravel-to-Python artefact records
all 126 resolver outcomes plus explicit isolation probes. Python builds search
chunks only from independently checksummed source documents and uses expected
EvidenceUnits only after retrieval for scoring.

The deterministic profile binds the population, planner catalogue, independent
source catalogue, chunking and retrieval configuration, eligibility mapping,
resolver source/configuration, fixed time, adapter fingerprints and harness
version. The full artefact digest preserves exact repository lineage; a separate
semantic comparability digest excludes repository identity in accordance with
the accepted comparison policy. Candidate output remains marked **CANDIDATE —
NOT PROMOTED**. Comparison fails closed unless the baseline result, promotion
record, profile digest and complete checksum manifest all agree; no command can
promote or refresh its own baseline.

## Verification

- Clean `make test-e2e`: 1 Playwright journey passed in the isolated stack; the
  stack and volumes were removed after success.
- `make test-splade-integration`: 1 passed using the real configured SPLADE model.
- Focused real-eligibility boundary: 5 Laravel tests, 23 assertions.
- Disposable Laravel-to-Python diagnostic: 42 cases, 126 variants, 93
  independent source chunks and 126 real resolver outcomes accounted for.
- Laravel: 350 passed, 2 skipped, 1,850 assertions.
- Python: 588 passed, 4 skipped.
- Web: 115 passed.
- Pint, Ruff lint/format, Mypy, ESLint, web and E2E TypeScript checks: passed.
- Shell syntax, Compose configuration, JSON parsing and `git diff --check`:
  passed.
- No OpenAI or Voyage calls were made.

## Remaining review boundary

The implementation can be committed before producing the authoritative first
candidate so that the candidate records the exact committed SHA. After push, run
`make evaluation-retrieval-current-candidate`, inspect its metrics and report,
and require an explicit human decision before creating the deterministic baseline
promotion, manual gate and checksum inventory. R22-S03 must remain in progress
until that review is complete.
