# C1 live checkpoint — stopped before dispatch, 2026-11-14

**Block 1 HOLD. No questions dispatched or graded.** Main remains unchanged. This is deployment/preflight evidence, not a live C1 result.

## Verified boundary

- Branch: `codex/block1-extractive-hold-20261112`.
- Pre-dispatch HEAD: `c84ccd4d9570c5ceaafb187b2714483c45c597aa`.
- Independently recomputed runtime aggregate: `4dde6707235e66ac707ed1cba90be8845738240dff9e4e6f2a37cc309989b338`.
- Released input SHA-256: `e1a54d05d0b688988a083596390ff9a9062b7a32c9352ea716a3a156145a9659`; five items, original bytes retained. No expectations sought or read during this task.
- Compose project: `dolved-r28-s03`; only this project's API/AI and its database connection were operated. No development-stack operation.
- Database: `pgsql / postgres / dolved_e2e_r28_s03`.
- Workspace: `5323cd6b-8b7a-40f9-98b0-9413c75f643e`; established actor `ae454ce6-4d91-4c7e-9a40-b4750b696a6b`; membership query passed.
- Active corpus: `b24a79c2-875e-4f7c-8fa9-3681db92ae73`, 986 assignments, zero foreign assignments. Point-manifest digest unchanged before/after: `9b3acad2659bd9d959e41819345123389c9d642029b1215a72b01b12b5f6bf80`. Voyage / voyage-4-large / 1024 and FastEmbed / prithivida/Splade_PP_en_v1.

## Exact infrastructure failure

The command below was attempted once on the verified evaluation API:

```
docker compose --env-file .env.r28-s03 -p dolved-r28-s03 -f compose.yaml -f compose.e2e.yaml -f compose.r28-s03.yaml exec -T api php artisan migrate --path=database/migrations/2026_11_14_000001_add_temporal_context_to_generated_answers.php --force --no-interaction
```

It failed with PostgreSQL `42501`: **must be owner of table generated_answers**. The connection role was `rag_platform_app`; the table owner is `rag_platform_owner`.

The implementer invoked the migration using the running API's application role rather than a verified migration-owner connection. This is a deployment-procedure failure, not evidence about C1 retrieval or answer behaviour. Neither role privileges nor table ownership were changed. No retry under another role was attempted.

Before and after: migration record absent; `generated_answers.temporal_context` absent. The failed ALTER did not apply. API and AI were restarted in the same tool batch after the migration command returned nonzero, before its failure was inspected. This restart completed, but **coordinated deployment is incomplete**, and no live-readiness claim is made. They remain isolated; no question or queue worker was started by this task.

The user instructed stopping on infrastructure failure, so execution stopped without consuming the one-use authority. The next operational requirement is to apply the same frozen migration through the established isolated migration-owner procedure, then verify schema and coordinated runtime. Do not grant DDL rights to the application role, alter ownership, change C1 logic, or treat this report as renewed dispatch permission.

## Budget and execution

David amended the cap from USD 1 to **USD 1.50**, retaining USD 0.50 uncertainty reserve and USD 0.50 minimum next-item headroom. This amendment is recorded in `budget-reservation.json`; it was not consumed.

- Provider requests/attempts: **0**.
- Provider tokens/cost: **0 / USD 0**.
- Execution conversations: **0**.
- Dispatched items: **0/5**.
- One-use authorisation file or consumption marker: **not created**.
- Per-call ledger, answers, citations, entailment results and stage latencies: **not produced**, because there were no calls.
- No correctness assessment, selective rerun, C2 work, main merge or Block 1 closure.

The existing generic diagnostic export omits C1 temporal-target fields. Inspection identified this before execution; no application or exporter changes were made. A future authorised checkpoint must capture the existing production observation hook and persisted temporal context without changing the frozen application. No new execution harness was completed here; `harness/preflight.php` is read-only connection/corpus/migration verification only.

## Evidence and publication

`preflight/before-migration.json`, `migration.txt`, `after-migration.json`, and `migration-role-diagnosis.json` preserve the exact state and error. `preflight/runtime-source.sha256` independently inventories all governed runtime files. The released input is committed unchanged alongside these records. Evidence-only publication advances hold HEAD after the failed preflight; it does not alter runtime content or retrospectively change the verified execution commit.

Only this package and the existing repair plan are in the commit. Journey is updated locally only. Pre-existing ADR-0039 and governed-runner/test edits are excluded and preserved. No application changes or full-suite rerun were needed for this stopped deployment; prior 38 AI / 2 Laravel results are not re-certified here. Local checksum, JSON, PHP syntax and diff checks are recorded in `verification.txt`.
