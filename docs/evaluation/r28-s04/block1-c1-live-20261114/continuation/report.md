# C1 single-pass live checkpoint — completed, ungraded

2026-11-14. **Block 1 remains HOLD. No correctness assessment, C1 acceptance or pilot-readiness conclusion.**

## Execution identity and authority

- Run: `R28-C1-LIVE-20261114-01`; one-use authority `R28-C1-AUTH-20261114-01`.
- Execution HEAD: `44219e2df49181e80f0bcaad2495372917d6c4c1`, branch `codex/block1-extractive-hold-20261112`.
- Application/runtime aggregate: `4dde6707235e66ac707ed1cba90be8845738240dff9e4e6f2a37cc309989b338`, verified before dispatch and between items. Both deployed API/AI mounted files also verified against the same inventory after restart.
- Released question-only input: `e1a54d05d0b688988a083596390ff9a9062b7a32c9352ea716a3a156145a9659`, unchanged. No expectations were sought or accessed during execution.
- Governed input/harness inventory digest: `f1a5f46e1e05cf2a0a6a446b0ef4ea1c6215781b8d7405a090efd350b51cb315`.
- Live capture class: `328a26416cbb32d769eb9e12d12445ca16f51ed82d58917d92fe1e791d7dc81a`; dispatcher: `7e9f9ea629443089642ff5ac97c2be1ed554688f01807b454b591e79546a1ae0`.
- Workspace `5323cd6b-8b7a-40f9-98b0-9413c75f643e`, established actor `ae454ce6-4d91-4c7e-9a40-b4750b696a6b`. Production membership resolution and authenticated actor; no injected plan, catalogue binding or evidence fixture in live execution.
- Five items dispatched in file order, each once. Dispatch window: **09:32:36–09:36:39 Europe/London**. November London is UTC+00:00; production evaluated-at values remain actual runtime times, not a frozen test clock.

## Deployment and provider-free capture proof

Only `dolved-r28-s03` was operated. Initial Compose interpolation lacked `R28_CORPUS_ROOT`; no container started in that attempt. Its value was recovered from the existing isolated API mount, `/private/tmp/dolved-r28-s03.zU3HX6/corpus/eval-corpus-v4-authoring`; no corpus relocation or rematerialisation occurred.

`migrator` used the specified env/project/Compose files, `run --rm --no-deps`, `rag_platform_migrator` and `PGOPTIONS=-c role=rag_platform_owner`. `migrate:status` showed only `2026_11_14_000001_add_temporal_context_to_generated_answers` pending. The migration was then applied once using its exact `--path`. Before: migration record absent, column absent. After: migration record present, column present. No role-bootstrap, role/grant/ownership changes, other migrations or dev-stack operations. API/AI were restarted after migration; database, corpus and schema checks passed before live dispatch.

Capture is checkpoint-only. It invokes the same production conversation actions as the existing diagnostic, retaining its queue suppression to prevent duplicate asynchronous execution. A closure records the existing observation hook; Laravel HTTP events record service-call starts/receipts and the assembled generation request. No request, decision, prompt, ranking or result is changed. Authentication headers/signatures/credentials are not persisted.

Provider-free proof used an evidence-only copy of the existing `EvidenceNeedsRetrievalTest` development fixture: day, modified input16, month and year. All external responses remained fake; SQLite memory was forced by PHPUnit. **4 tests / 240 assertions passed**, including targets, extra lane, allocated evidence target labels, assembled generation request, HTTP receipt capture, validated planner disagreement and persistence/reload. Two tooling failures were retained: host-bind vendor schema read failed (fixed by using the supported `/app/vendor` volume), then an array callable was rejected by the observer's `__invoke` usage (harness changed to a closure). No application change or provider call occurred during proof. The generated test copy and its preparation script remain in `harness/`; the original application/test files were not edited.

## Observed routes — not verdicts

All five generation runs reached `completed`. This is an execution status only.

| Item | Observed operations | Base / additional logical lanes | Final evidence units | Precautionary target admitted | Declared answer use | Generation contract | API→AI calls |
|---|---|---|---:|---|---|---:|---:|
| F1 | compare | 2 / 0 | 4 | No | Existing comparison route; no C1 temporal-use declaration | 3 | 5 |
| F2 | retrieve | 1 / 1 | 5 | Yes | `n1:requested`; no precautionary target used | 4 | 6 |
| F3 | retrieve | 1 / 1 | 5 | Yes | Precautionary `n1:date:2`; no requested target declared | 4 | 6 |
| F4 | compare + retrieve | 3 / 0 | 3 | No | Existing comparison/retrieve route; no C1 temporal-use declaration | 3 | 6 |
| F5 | compare | 2 / 0 | 5 | No | Existing comparison route; no C1 temporal-use declaration | 3 | 4 |

**C1 temporal targets were exercised on F2 and F3 only.** The other three must not be counted as demonstrations of the new target mechanism. All five catalogue bindings were recorded as matched (F4 has two needs). Target count is not search count: targets can reuse an already searched version. Comparison sides also share a search request, so HTTP search-call count differs from logical lane count.

“Requested” above refers to the production planner's requested target/path, not a reviewer determination that it preserved the user's intent. The reviewer alone assesses that question. For F1/F4/F5, persisted temporal context is null; the snapshot's normalised empty collection is explicitly distinguished from a reload discrepancy. F2/F3 persisted temporal contexts match their reloads exactly.

## Evidence captured for each item

`cases/F1.json` through `F5.json`, plus corresponding `*-ledger.ndjson`, retain:

- Full actual plan, source spans, operations, authority and catalogue bindings.
- Date-reference inventory computed with the frozen production inventory function against the recorded needs; this diagnostic inventory does not influence execution.
- Every emitted temporal-target event: purpose, status, versions, reference and reason. Pre-allocation events remain distinct from the targets in the actual assembled generation request.
- Search/lane events, coverage, final allocated evidence and target labels; full versioned generation request when one was sent.
- Validated target/version/interval and `planner_disagreement` in persisted temporal context where present.
- Displayed answer, citations/evidence snapshots, reloaded rendering and temporal context.
- Actual production usage, generation validation/entailment receipts, HTTP call latency and per-stage receipts. No grading labels were added.

`persistence-verification.json` is an independent readback of these five existing runs only: five distinct conversations, one user message and one run in each; exact released question text and persisted/reloaded context/rendering preserved. `verification.json` records those integrity checks. Every per-item ledger hash chain passes. `dispatch.ndjson` contains exactly five starts and five completions in F1–F5 order. The exclusive authorisation marker is retained; there is no resume or rerun path.

## Accounting and limits

David's cap is **USD 1.50**, with USD 0.50 uncertainty reserve and USD 0.50 minimum next-item headroom retained. Each budget gate passed; no retry ceiling or runtime configuration was changed. There were no selective reruns. Production's own internal validation/transport retry behaviour was retained, and its receipts are preserved.

- **27 API-to-AI service calls** recorded individually. This is not a claim of 27 physical provider requests: planner/checker, generation/entailment and retries can be internal to a service call.
- Recorded-cost / retained-rate subtotal: **USD 0.06929542**.
- Separate conservative reranking allowance: **USD 0.00104500**.
- Conservative total including USD 0.50 uncertainty reserve: **USD 0.57034042**.
- Summed recorded provider-stage tokens: **186,392 input / 14,453 output**. Nested receipts remain separately inspectable and are not summed again.
- These are retained-receipt estimates/allowances, **not a complete billed invoice total**.

The generic HTTP receipt projection did not include the reranker envelope's usage. The existing production observer retained those receipts, so `accounting-reconciled.json` adds them after the run without changing any record or dispatch accounting. They fit inside the retained uncertainty reserve at every gate. `accounting.json` preserves the dispatch-time calculation; `nested-stage-receipts.json` exposes component planner/checker and generation/entailment timing/tokens where available, explicitly non-additive to parent totals. Missing fields remain unavailable, never fabricated as zero.

## Boundaries and handoff

No application changes, semantic fixes, expectations access, answer grading, corpus re-embedding, AWS call, C2 work, main merge or Block 1 closure. The originally failed deployment evidence remains unchanged. Main remains `348f09e053c8ad73676921c5c616cced812111d7`; unrelated ADR-0039 and governed-runner/test edits remain excluded. Journey is updated locally only.

The evidence-only publication commit advances hold HEAD after execution; execution remains attributed to `44219e2` and the unchanged application aggregate. No more dispatch is authorised or performed in this task. **Next: the reviewer's independent source-based assessment under the existing stage-specific rules.**
