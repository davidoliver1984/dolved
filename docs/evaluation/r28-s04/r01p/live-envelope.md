# R01P eight-question development slice

Prepared 2026-09-06 before execution under David's standing R01P provider permission. This is not the frozen full R28 certification run.

- Input: `R28-R01P-SLICE-20260906-V1`, SHA-256 `443f315e82d4f70e27b3021379249373c3839811920d00c9c0202847e9c58261`; eight fresh conversations, questions only. No expected outputs enter execution.
- Hypothesis: independent bounded needs retain both historical comparison and current support, with truthful gaps for unavailable aspects. Inspect each answer and its actual citations after execution; this sample cannot establish general accuracy.
- Code: local planning HEAD `f1c7e69f3798558ddeef3e1f43aa6df2d0dacc9a` plus the uncommitted accepted R01P implementation. Capture file hashes alongside results; no clean-commit or certification claim.
- Existing primary generation: `b24a79c2-875e-4f7c-8fa9-3681db92ae73`; 982 chunks, largest canonical chunk 2,798 UTF-8 bytes. No corpus embedding or index change.
- Providers: planner/generation `gpt-5-mini`; embeddings `voyage-4-large`/1024; reranker `rerank-2.5`; existing SPLADE revision unchanged. Generation is `generation-result-v2` / `grounded-generation-needs-v1` / `openai-responses-needs-v1`, low reasoning, 4,096 output tokens, 400,000 context limit. Record full runtime profile in manifest.
- Concurrency one. Native retries retained. No separate contextualiser, judge or full-population execution.

## Request and estimated spend envelope

Provider-schema restriction checkpoint (2026-09-06): next execution pin is `bounded-needs-slice-v5`, planner adapter `structured-chat-needs-v2`; prompt/wire stay v8/v2. The builder restricts provider authority shapes and per-request family IDs. The complete serialized provider request is capped at 100,000 bytes before dispatch. Existing 100,000-token conservative planner estimate and $4.82204928 total estimate remain unchanged. No live v5 run has occurred yet.

2026-09-06 subject-binding correction: execution `bounded-needs-slice-v4`, planner `evidence-needs-plan-v2` / `bounded-evidence-needs-v8`, generation prompt remains `grounded-generation-needs-v2`. Catalogue metadata is included in the existing planner call, not an extra call. For new runs the planner body bound is 65,000 bytes and conservative planner input estimate is 100,000 tokens including schema/prompt. Planner envelope becomes $0.531072; total becomes **$4.82204928**, still below $5. All other stage bounds below remain unchanged; earlier numbers describe the preceding runs. First execute only controls 06 and 08, then all eight unchanged questions on this code only if those controls pass.

David approved the narrow two-needs/four-lane extension on 2026-09-06. Run 0010 used execution pin `bounded-needs-slice-v2`; the subsequent pair-rank tie-break correction uses `bounded-needs-slice-v3` and selection identity `bounded-needs-k5-v2`. K=5, model identities, two-need limit and all grounding/tenancy rules remain unchanged. The following bounds supersede the original three-lane envelope for new runs only; prior manifests remain immutable.

| Stage | Nominal maximum | Conservative physical attempts |
| --- | ---: | ---: |
| Planner | 8 | 16 |
| Query embedding | 16 | 128 |
| Reranking sides | 32 | 192 |
| Generation | 8 | 24 |
| Total | 64 | 360 |

Laravel infrastructure retries are conservatively counted even though exhausted provider failures are not ordinarily retried by Laravel. The diagnostic limits outer dispatch counts and checks query bytes at 2,048, planner input-body bytes at 4,000, candidate JSON bytes at 9,000 and candidates per side at 15. These are stop-only experiment allowances, not production query rewriting. No malformed response is repaired. Runtime planner prompt/schema is 5,114 bytes; 20,000 tokens per attempt is a deliberately generous input estimate including request wrapping. Use 9,500 bytes per rendered reranker document to allow representation overhead, and account for the query repeated per document. Byte-based Voyage estimates are conservative diagnostic estimates, not a provider tokenizer certification.

Configured price snapshots: OpenAI 2026-08-19 ($0.25/M input, $2/M output, ignoring caching savings); Voyage embedding 2026-08-12 ($0.12/M); reranking 2026-09-04 ($0.05/M). Estimated attempt envelope:

- Planner: `16 × (20000 × .25 + 4096 × 2) / 1e6 = $0.211072`.
- Embedding: `128 × 2048 × .12 / 1e6 = $0.03145728`.
- Reranking: `192 × 15 × (9500 + 2048) × .05 / 1e6 = $1.662912`.
- Generation: `24 × (400000 × .25 + 4096 × 2) / 1e6 = $2.596608`.
- Total estimated conservative envelope: **$4.50204928**, within the **$5 practical experiment safeguard** and $30 cumulative repair safeguard. Actual usage should be substantially lower; unavailable provider costs remain labelled and estimated from tokens rather than called zero.

A 90-minute pre-dispatch wall-clock check stops further service requests; an in-flight native request may finish after that boundary. This is not a new hard-budget ledger or a guarantee of forcibly cancelling remote work. Use existing provider receipts/usage and retain every completed case. A failed case stops the experiment for inspection; no automatic whole-slice rerun. Activation is process-local to this workspace, not a global environment change. Two additive migrations were applied through the existing migrator role after the application role correctly rejected DDL. Prior runs and index data remain unchanged.
