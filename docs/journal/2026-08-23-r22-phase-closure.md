# R22-GATE — Close testing and quality strategy

**Date:** 2026-08-23
**Status:** Complete

## What closed

Phase 22 now has a truthful layered verification boundary: fast unit, feature,
integration and contract checks; isolated deterministic browser journeys; a
real-SPLADE integration check; historical retrieval-policy enforcement;
current-code deterministic retrieval comparison; and immutable generation-
evidence verification.

The final review identified one soft proof gap. The chat journey counted native
SSE reconnections but did not assert the intermediate progress presentation
required by ADR-0029. The E2E-only one-event connection now pauses for 500 ms
after flushing its first durable event, with a production-default value of
zero. Playwright therefore observes `Understanding your question…` before the
forced disconnect, then proves native reconnection, durable replay and final
completion. This does not change normal streaming behaviour.

## Verification

- Full fast tier: web 115 passed; Laravel 357 passed, 2 skipped and 1,906
  assertions; Python 629 passed and 4 skipped.
- Pint, Ruff lint/format, ESLint, TypeScript, Mypy and Collector configuration:
  passed.
- Clean isolated `make test-e2e`: 1 passed in 39.6 seconds; all isolated
  containers and volumes removed.
- `make test-splade-integration`: 1 passed.
- `make evaluation-policy-gate`: passed.
- `make evaluation-retrieval-current`: passed against the promoted
  deterministic orchestration-regression baseline.
- `make evaluation-generation-verify`: passed for 26 cases and 18 artefacts,
  with zero provider calls.
- JSON/documentation validation and `git diff --check`: passed.

## Evidence boundary

No OpenAI or Voyage call was made. The optional live retrieval and generation
evaluations were not invoked and remain non-gating. Phase 22 makes no new
live-provider retrieval-quality, generation-quality or prompt-injection-
resistance claim.

## Outcome

R22-GATE is complete. R23-S01 is the next session; no Phase 23 implementation
began during this closure.
