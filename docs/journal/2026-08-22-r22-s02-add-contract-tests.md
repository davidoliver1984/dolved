# R22-S02 — Add shared contract tests

**Date:** 2026-08-22
**Status:** Completed

## What changed

R22-S02 closed the remaining language boundary between Laravel and Python.
All nine ingestion operations and three deletion operations now publish
versioned request and response schemas with one shared fixture inventory.
Both test suites validate the same successful payloads and prove that unknown
fields, missing fields, invalid values and unsupported versions fail closed.

The shared signing inventory covers all twelve signed purposes. Python
reproduces every expected signature, Laravel accepts the same vectors, and the
real Python clients were exercised against fixture responses to prove their
emitted request structures match the contracts. Retrieval rc1 received the
same shared-fixture treatment across every committed schema.

Two provider-free evaluation commands were also made real. The historical
retrieval policy command now chains candidate replay and comparison and exits
non-zero when the already-computed gate fails. Its first execution exposed the
promoted baseline's intentional V1 shape, so the CLI was connected to the
existing comparison-only V1 adapter rather than changing the immutable file.
The generation verifier reads each run's checksum inventory, respects its V1
or V2 result shape, validates population and observation lineage, and
recomputes only deterministic structural evidence. Recorded semantic scores
remain historical provider evidence and are not regenerated.

## Verification

- Shared Laravel contract tests: 6 passed, 176 assertions.
- Full Laravel suite: 336 passed, 2 skipped, 1,789 assertions.
- Full Python suite: 570 passed, 4 skipped.
- Full web suite: 115 passed.
- `make evaluation-generation-verify`: PASS, 2 runs, 26 case instances,
  18 checksum-declared artefacts, 0 provider calls.
- `make evaluation-policy-gate`: PASS.
- Temporary degraded candidate: non-zero exit with
  `regression:recall_at_k`.
- Draft 2020-12 meta-validation: 35 affected schemas valid.
- ESLint, Pint, Ruff lint/format, TypeScript, Mypy, Collector configuration
  validation and `git diff --check`: PASS.

No provider calls were made. No application runtime semantics, retrieval
configuration, planner, threshold, calibration population, benchmark content
or held-out material changed.

## Next

R22-S03 can now implement the isolated deterministic ingestion E2E path on top
of the verified shared contracts.
