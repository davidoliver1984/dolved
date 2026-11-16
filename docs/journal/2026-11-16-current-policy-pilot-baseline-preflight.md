# Current-policy pilot: baseline preflight hold

## Authority and boundary

David approved Stop 1 in the external `codex-implementation-approval-prompt.md` on 16 November 2026. Created worktree `/Users/davidoliver/Desktop/RAG-current-policy-pilot`, branch `codex/current-policy-pilot-20261116`, from `a123ad8aef28f592bc885601c75dc0d42254713c`. The original hold checkout, its four tracked edits and untracked content remain untouched. No application implementation, push, provider call, evaluation deployment or migration occurred. PROJECT_JOURNEY remains untouched as directed.

## Baseline attempted

Ran `make format-check`, `make lint`, `make typecheck`, and `make test` using an explicit disposable Compose tooling configuration. Make's aggregate test target stopped at web failure; ran API and AI separately with raw JUnit, then the telemetry and PostgreSQL-topology checks. Initial setup failures are retained separately from configured attempts.

The three tooling containers were `dolved-current-policy-checks-{api,ai,web}-1`, using the existing development images whose dependency-lock hashes match the checkout. All three had `network_mode: none`; no provider credentials or private overrides were mounted. API used SQLite `:memory:` with a synthetic test encryption key. AI had no reachable database/vector service. Web needed read-only git metadata for its tracked-file branding check. Dependencies were prepared only in the new worktree (ignored API vendor copy and web node_modules link). Contracts were mounted read-only. No evaluation corpus was mounted.

The repository telemetry script itself launched two disposable `rag-platform-otel-collector-run-*` tooling containers; its validation passed. The role-topology script passed. No API/AI/database/evaluation stack was started by those scripts. The three named pilot tooling containers were stopped after the baseline.

## Observations, not a regression conclusion

- Format/lint: existing PHP style failures in `LocationReferenceCandidates.php` and `SemanticRetrievalTest.php`; aggregate targets short-circuit before all downstream checks.
- Typecheck: eight MyPy errors across four files; TypeScript target completed.
- Web: 153 passed, one branding test failed against three already-tracked files. An earlier run failed because worktree git metadata was not mounted; the corrected run is retained separately.
- API configured attempt: 65 failures/errors, 125 passes and 762 warnings as reported by Artisan. Raw JUnit and exact identities retained.
- AI configured attempt: 147 failed, 1,387 passed, four skipped and nine errors. Raw JUnit and exact identities retained.

These are not the old 38 AI / 2 Laravel baseline and are not asserted to be all product defects. The isolated environment exposes missing suite prerequisites. For example, `EngineeringBenchmarkSourceTest` tries to read `/evaluation/engineering-snapshots/dolved-care-engineering/v2/corpus.json`; Qdrant tests attempt a real vector-service connection. Missing fixture/configuration inputs and denied connectivity contaminate comparison. No assertions were changed or skipped to manufacture a baseline.

## Concrete blocker and disposition needed

The approval requires the full repository baseline first, while forbidding checks from touching evaluation data/corpus and limiting them to test databases and fake providers. The standard full suites depend on external evaluation snapshots and a real Qdrant service. Those accesses were not enabled. Stop before application edits rather than silently mount prohibited corpus data or treat missing dependencies as a sound regression baseline.

David needs to specify whether immutable engineering-only fixtures and a disposable test-only vector service are permitted for these existing tests, or whether those named dependency-bound checks should remain explicitly unavailable under this approval. Neither option is implemented here. This is a verification-environment dependency, not a new product design comparison.

Evidence: `/Users/davidoliver/Desktop/dolved-experiments/current-policy-pilot-20261116/implementation/stop-1/`. Stop 1 is **not delivered**: scope contracts, Proposed ADR and implementation remain unstarted. The original gate remains FAILED, its programme STOPPED; no readiness claim.
