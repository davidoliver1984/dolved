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

The current retrieval gate now executes the current implementation from the
authored-plan boundary through deterministic dense/sparse encoding, real Qdrant,
RRF and deterministic reranking. Its execution profile binds the three adapter
fingerprints, authored-plan catalogue checksum, retrieval configuration and
harness version. Candidate output includes a report marked **CANDIDATE — NOT
PROMOTED**. Comparison fails closed unless the baseline result, promotion record,
profile digest and checksum manifest all agree; no command can promote or refresh
its own baseline.

## Verification

- Clean `make test-e2e`: 1 Playwright journey passed in the isolated stack; the
  stack and volumes were removed after success.
- `make test-splade-integration`: 1 passed using the real configured SPLADE model.
- Laravel: 345 passed, 2 skipped, 1,827 assertions.
- Python: 578 passed, 4 skipped.
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
