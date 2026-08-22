# R22-S01 — Establish test taxonomy

Date: 2026-08-22
Outcome: Completed

## What changed

ADR-0029 was accepted after a repository-grounded implementation-readiness
review. The resulting architecture defines fifteen test categories and keeps
their claims deliberately separate: fast language-level correctness, shared
contract agreement, deterministic product E2E, historical evidence integrity,
current-pipeline orchestration regression, optional live-provider evidence and
security regression.

`docs/testing/README.md` turns that decision into a concise operational
reference. It records test-data ownership, E2E isolation, cleanup, failure
evidence, bounded polling, no retry-to-green, quarantine requirements and the
required-versus-optional Phase 22 evaluation boundary.

The Laravel test previously named `EndToEndIngestionOrchestrationTest` is now
`IngestionOrchestrationFeatureTest`. Its assertions did not change. The new
name truthfully describes an in-process Laravel Feature test and reserves
"end-to-end" for later Playwright journeys crossing the real running services.

## Review corrections

Implementation review found and corrected several overclaims before
acceptance. Historical `make evaluation-run` is report generation rather than
policy enforcement; the required gate will explicitly propagate
`assess_gate()` failure. Deterministic embedding, sparse and reranking results
are not comparable to real-provider metrics, so current orchestration gets a
separate reviewed baseline and live quality remains separately labelled.
Existing sparse tests inject a recording engine and do not load SPLADE, so a
mandatory bounded real-model integration target is assigned to R22-S03.

The final lineage correction retains `repository_commit` as provenance but
excludes it from the deterministic comparability digest. This prevents an
unrelated later commit from invalidating the R22-S03 baseline while still
binding comparability to component profiles, the authored-plan catalogue,
retrieval configuration and harness identity.

## Important boundaries

This stage added documentation and one mechanical test rename only. It added
no dependency, provider call, schema, E2E fixture, runtime adapter or public
test endpoint. It did not change application behaviour, planner or retrieval
semantics, threshold, calibration, benchmark content or held-out access.

The unrelated local ADR notes, GPT drafts and journey assets were not added,
modified or removed.

## Verification

The renamed Laravel feature test passed with its existing assertions. PHP
formatting, JSON parsing, documentation-reference checks and `git diff
--check` passed. ADR-0029 remains indexed as Accepted and the repository's
Phase 22 tracker advances to R22-S02 only after this verified boundary.

## Next

Begin R22-S02 — Add contract tests.
