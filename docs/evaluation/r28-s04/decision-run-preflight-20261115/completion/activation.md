# Prepared capture activation — NOT EXECUTED

1. Bind a new Phase B evidence destination, e.g. an approved run-specific directory
   under `docs/evaluation/r28-s04/decision-run-20261115/`. Create its `capture/`
   subdirectory with write access for the serving AI UID; require real directories,
   not symlinks. Do not reuse the previous diagnostic destination or authority.
2. Recover the established production-provider override from operator-owned secure
   configuration and verify it against the running API/AI environment **in memory**,
   reporting only allowlisted identities and credential-presence booleans.
   Container labels reference `/private/tmp/dolved-r28-production-diagnostic.override.yaml`,
   which is now absent. **Do not run a recreate command without that prerequisite**:
   base e2e configuration selects deterministic providers and r28-s03 blanks keys.
   Do not print credentials, commit the override, or silently substitute defaults.
3. Use the existing env file/project/base compose sequence, then the verified provider
   override and the frozen `../capture.override.yaml`, with
   `DECISION_RUN_EVIDENCE_DESTINATION` set to the approved absolute directory:

   ```sh
   R28_CORPUS_ROOT=/private/tmp/dolved-r28-s03.zU3HX6/corpus/eval-corpus-v4-authoring \
   docker compose --env-file .env.r28-s03 -p dolved-r28-s03 \
     -f compose.yaml -f compose.e2e.yaml -f compose.r28-s03.yaml \
     -f /private/tmp/dolved-r28-production-diagnostic.override.yaml \
     -f docs/evaluation/r28-s04/decision-run-preflight-20261115/capture.override.yaml \
     up -d --no-deps --no-build --pull never api ai
   ```

   This must preserve provider/model/token/retry/timeout settings; only the explicit
   evaluation capture enablement/destination and mount change. Never use `start`
   expecting it to suppress dependencies. No workers, scheduler or role-bootstrap.
4. Read settings through the serving AI process environment: enabled=true,
   destination=`/evaluation-evidence/capture`; verify the mount, writable target,
   all mounted runtime hashes, API database/workspace identities and unchanged
   model/profile limits. Existing `capture_scope` accepts only workspace
   `5323cd6b-8b7a-40f9-98b0-9413c75f643e`; other contexts are not captured.
5. Before any paid dispatch, reuse `tests/test_evaluation_capture.py` with controlled
   providers in an isolated test process using the deployed image/dependency set.
   The existing tests exercise authenticated serving routes, rejected/accepted
   attempts, capture-on/off equivalence and workspace/path refusal; no production
   adapter or real question is needed. Run provider-free harness controls and the
   release adapter tests. Preserve their output. Verify a development-fixture
   sidecar in the approved writable mount, using the existing capture API and a
   labelled development UUID, not an application/database run or new instrumentation.
   If any field is absent, record it; never infer missing planner or review output.
6. Bind original release hash, derived execution hash, frozen harness/dependencies,
   runtime, actor/workspace/context, budget reservation and ceiling into the future
   approved authority. `execute_released` does not create an authority. Check for
   duplicate start/consumption markers before execution; preserve old evidence.

No step here has been used to enable capture or create/consume a live authority.
The missing override and reviewer context decision prevent treating this recipe
as ready for blind execution. Raw internal source-review receipts remain unavailable.
