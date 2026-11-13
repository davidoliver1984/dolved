# Extractive fixture migration — 2026-11-13

Held candidate `498e75ae2b6f6bcdb60ced76412b497b8d5304c1` was committed and
pushed only to `origin/codex/block1-extractive-hold-20261112` under David's
explicit approval. Remote main was verified at `348f09e053c8ad73676921c5c616cced812111d7`.
The prior failed-gate report describes its historical stopped state; its raw
JUnit, comparison and checksums are preserved unchanged.

## Attribution, confirmed before fixture changes

| New failures | Cause and source evidence | Fixture migration |
|---|---|---|
| 7 interpretation-check cases | The fake decision cites `ROPA changes`, the diagnostic model aspect. `needs_planner.py` passes `execution_plan()` to the checker; `verify_citation()` cannot find that string in the compiled aspect. The four retry/raise failures and three second-attempt cases therefore receive no retry. | Fake meaning-change citation quotes compiled text; fake scope citation uses the existing typed `authority.value=previous` in the execution view. These are mechanical citation/retry tests, not semantic judgements that this question is wrong. |
| 1 shared response schema | Test reads generation-needs/v2, while the response model now accepts envelopes 2 and 3. | Read the existing v3 snapshot, retain exact schema equality and invalid-envelope assertions. |
| 6 ConversationOrchestrationTest cases | Fake `/plan-needs` responses emit plan v3; `RetrievalClient::planNeeds()` explicitly requires live v4. JUnit records `retrieval_failed`, before generation or clarification. | Update fake plan/lineage versions, echo generation envelope, and keep the strict persisted-schema assertion with its v4 identity. |
| 1 scope/operational case | JUnit records `GenerationException: The generation response envelope is invalid` at GenerationClient.php:53. Request is v3; fake response says 2. | Echo the request envelope. Ownership, scope isolation, persistence and request-count assertions unchanged. |

No application source changed during migration. No assertion was deleted, skipped
or loosened. The sole existing assertion-value update is the exact persisted plan
identity v3 → v4. A standalone verifier fixture explicitly retains `plan_field=aspect`
when overriding the shared helper's quotation; all its assertions remain unchanged.

Added `test_diagnostic_only_aspect_flag_is_unverifiable_without_retry`: diagnostic
phrase retained in the returned raw plan, absent from the checker execution view;
receipt `checker_code=scope_added`, effective `code=accepted`, verification
`unverifiable/plan_quote_not_found`, two requests (planner + checker), zero retries.

## Schema additions (exhaustive response snapshot diff)

Only `contract_version: const 2` → `enum [2, 3]` in the three response definitions:
success, context-budget-exceeded, provider-error. No result properties, required
fields, discriminator rules or safety constraints changed. The already-held v3
snapshot is used; no historical v2 snapshot is rewritten.

## Full-suite gate

| Suite | Baseline | After fixture migration | New failures | Resolved baseline failures |
|---|---:|---:|---:|---:|
| AI | 38 | 38 (1,355 cases: 1,313 pass, 4 skip) | 0 | 0 |
| Laravel | 2 | 2 (864 cases, 9 skip, 4,843 assertions) | 0 | 0 |

Exact names and raw failure detail: `suite-comparison.json`. Raw JUnit/logs:
`../block1-extractive-20261112/verification/migrated-ready-{ai,api}.{xml,log}`.
The comparison script records SHA-256 for all six baseline/held/migrated XML inputs.
All fifteen new held-candidate failures are resolved, not waived into the baseline.

The initial migration attempt is retained as `migrated-*`: Docker had been stopped;
Qdrant/LocalStack were not yet running. Ten AI integration failures plus teardown
errors and one Laravel storage error followed, alongside the standalone citation
fixture setup corrected above. After starting local dependencies and correcting
that test setup, the full suites produced the exact baseline sets. No production
behaviour correction was made.

## Additional checks and checkpoint

Canonical `make typecheck` and `make lint` were run; their logs are retained.
TypeScript initially could not write its ignored build cache (OS error -35); the
same check with incremental caching disabled passed. MyPy's initial SQLite cache
failed with disk I/O error; a container-local-cache run is recorded separately.
Pint flags two unchanged baseline files: LocationReferenceCandidates.php and
SemanticRetrievalTest.php. Ruff separately flags three unchanged baseline test
imports: test_generation_live_safety.py, test_openai_answer_evaluator.py and
test_r28_s02_live_retrieval.py. These files are byte-identical to baseline; no
unrelated formatting cleanup is included.

No provider calls or live expenditure in this fixture proof. Live acceptance and
Recall@5 remain unmeasured. David separately approved a checkpoint-only governed
runner exception, pinned to an exact pushed hold-branch SHA with remote-tip,
HEAD/runtime checks and one-use tests; every other stage must retain main checks.
That harness work is a separate commit, not part of this fixture migration.

MyPy with a working container-local cache reports ten errors in six files. A
clean archive of baseline `348f09e` produces the same ten file/message/code entries;
only two line numbers shift by four in openai_adapter.py. Both outputs are retained.
These are not new candidate errors, but `make typecheck` and `make lint` must not
be represented as passing. The migration's exact full-suite gate passes; the
additional tools remain non-green on unchanged baseline issues.
