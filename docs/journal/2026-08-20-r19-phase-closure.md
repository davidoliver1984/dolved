# Phase 19 — Administration Closure

**Date:** 2026-08-20
**Status:** Completed
**Architecture:** ADR-0025 (Accepted 2026-08-19)

## Closure decision

Phase 19 is complete. Its three stages deliver tenant-scoped document
administration, membership/invitation/ownership administration, and usage
visibility, all governed by ADR-0025's owner/admin/member capability model,
Laravel-owned durable state, and Python's narrow authenticated
provider-native role.

This closure ran the full repository-boundary verification required before
a phase gate — `make format-check lint typecheck test ps`, `make
aws-status`, and each service's test suite individually where the chained
target stopped early — rather than relying solely on each session's own
already-recorded focused evidence.

That sweep found four genuine, Phase-19-scoped gaps invisible to any
individual session's focused verification, all fixed during closure:

- `DocumentDeletionOrchestrator` was typed against the concrete
  `DocumentDeletionClient` class and the full ten-method `VectorStore`
  protocol, though it only calls three of the store's methods and three of
  the client's. Narrowed to two purpose-built Protocols
  (`DeletionReportingClient`, `VectorCleanupStore`) matching its actual
  dependency surface — a type-only, behaviour-preserving change; the real
  `DocumentDeletionClient` and `QdrantVectorStore` already satisfy both
  narrower Protocols structurally.
- The `web` container's bind mount scopes only `apps/web`, so
  `branding.test.ts`'s repository-wide legacy-branding regression test
  (added in Stage 19.3) could never see `.git` or the rest of the
  monorepo from inside the container and failed with `git ENOENT`. Added a
  read-only whole-repository mount (`REPOSITORY_ROOT=/workspace`),
  matching the existing `contracts:ro` cross-mount convention `api` and
  `ai` already use, and gave that one slow whole-repository scan a
  realistic timeout instead of vitest's 5-second default.
- A stale `.next` dev-cache artefact referencing an already-deleted
  `chat-review` route broke `typecheck-web`; cleared the cache and removed
  the empty, untracked leftover directory.
- Four already-committed Phase 16-era evaluation test files had Ruff
  import-sort violations that blocked `make lint` from completing at all;
  fixed with `ruff check --fix` (zero-risk, mechanical).

It also found, and deliberately left unfixed as genuinely out of scope,
technical debt predating Phase 19 entirely: five Phase 16/17-era
evaluation/retrieval test files with unrelated Mypy errors; two Phase
16/17-era evaluation test files whose collection depends on a
`/evaluation/engineering/{corpus,expectations}.json` fixture absent from
this environment; and eight Laravel `Exp0007DefinitionTest`/
`Exp0008DefinitionTest`/`V3Engineering*` failures caused by that same
missing fixture. None of these touch Phase 19's own code — confirmed by
git history for each file.

## Evidence

- `make format-check lint typecheck` clean across Laravel, Python and web
  after the fixes above; `make aws-status` clean; `make ps` all services
  healthy.
- Laravel: 303 passed, 2 skipped, 8 failed (pre-existing missing-fixture
  evaluation tests only, unrelated to Phase 19).
- Python: 545 passed, 3 skipped, 2 failed (same missing-fixture cause);
  2 pre-existing, unrelated collection errors excluded from this run.
- Web: 16 test files and 49 tests passed, including the now-working
  repository-wide branding regression test.
- docs/journal/2026-08-20-r19-phase-closure.md

## Next

R20-S01 — Standardise Structured Logging.
