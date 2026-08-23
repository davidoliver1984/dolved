# R22-S05 — Add security-focused tests

**Date:** 2026-08-23
**Status:** Complete

## What changed

The final Phase 22 implementation session added provider-free regression
coverage around the platform's highest-value security boundaries. Laravel now
has explicit coverage for hostile upload names, unsupported and oversized
documents, presigned-upload constraints, privacy-safe storage errors,
authentication throttling and existing tenant concealment. These checks retain
Laravel as the authority for workspace access and document-upload policy.

The ingestion queue now parses `ApproximateReceiveCount` through one bounded,
ASCII-only conversion. Zero, negative, signed, whitespace-bearing, Unicode,
non-string and excessively long values are rejected without crashing polling.
Malformed entries retain the existing privacy-safe warning, are not returned to
the worker and remain unacknowledged.

Generation security gained three separately described evidence layers:

1. deterministic adapter tests prove hostile document text stays in the
   evidence/content position rather than the system/developer position;
2. contract and orchestration tests prove that evidence text cannot alter
   workspace identity, retrieval scope, required evidence sides, outcome
   vocabulary or authorised evidence handles; and
3. `prompt-injection-v1` is an optional three-case real-model measurement, not
   provider-free proof of model resistance or universal prompt-injection
   immunity.

The optional live wrapper is itself fail closed. Before reading credentials or
launching a provider subprocess it verifies a full exact commit, clean tracked
worktree, repository-owned policy and population, immutable output identity and
the bound generation fingerprint. Its real maximum is one generation attempt
and one evaluator attempt for each of three cases: six provider attempts and
18,432 output tokens. Both per-call ceilings are passed to the actual adapters.

## Verification

- Focused SQS, live-wrapper and generation security suite: 53 passed.
- Full Laravel suite: 357 passed, 2 skipped, 1,906 assertions.
- Full Python suite: 629 passed, 4 skipped.
- Full web suite: 115 passed.
- Pint, Ruff lint/format, ESLint, TypeScript and MyPy: passed.
- Generation-evidence verification: 26 cases, 18 artefacts, zero provider
  calls, passed.
- JSON parsing and `git diff --check`: passed.
- No live-provider command was invoked.

## Outcome

R22-S05 is complete and R22-GATE is the next session. The security population
and wrapper do not alter production retrieval, generation or provider policy,
and no live-model security claim has been made.
