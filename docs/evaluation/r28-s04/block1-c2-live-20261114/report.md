# C2 single-pass live checkpoint — 14 November 2026

**Execution complete, ungraded. Block 1 HOLD. Hold branch only; main unchanged.**
All six released inputs ran once, in order, in six new authenticated persisted
conversations, with one user turn and one run each. Four records are `completed`;
two are controlled `retrieval_no_answer` / `interpretation_failed` outcomes.
No sealed expectations were accessed and no answer correctness assessment was made.
No application source, prompt, configuration, retry or retrieval behaviour changed.

## Frozen identities and deployment

- Run / authorisation: `R28-C2-LIVE-20261114-01` / `R28-C2-AUTH-20261114-01`.
- Execution HEAD: `829bbf132429ab8d4b408e4df2654e1b76fd2070` throughout all dispatches.
- Implementation: `84f22f862e5001be998e6a8fe027f56a17db6941`.
- Runtime inventory aggregate, before and after:
  `7f20f123cb9e4af9567e0ea0240ebf715d54755ae2611dc66ecd6719756773af`.
- Original released input SHA-256:
  `7603fc82fc2f95f9852f584f28cc6a3d4949a2a30e17b8ab3b56a52ce4d2899f`.
- Main remains `348f09e053c8ad73676921c5c616cced812111d7`.
- Isolated project `dolved-r28-s03`, `.env.r28-s03`, existing Compose configuration
  and read-only V4 corpus mount. Database `dolved_e2e_r28_s03` on `postgres`.
  Primary workspace `5323cd6b-8b7a-40f9-98b0-9413c75f643e`; authenticated actor
  `ae454ce6-4d91-4c7e-9a40-b4750b696a6b`. Active generation
  `b24a79c2-875e-4f7c-8fa9-3681db92ae73`: 986 assigned chunks, zero foreign.
- Owner-role `run --rm --no-deps migrator php artisan migrate:status`: all Ran.
  No migration, bootstrap, grant or ownership change. API and AI restarted together;
  mounted application/contract inventories checked before and between items and at end.
  The closed reviewer-failure header producer and consumer are both in this freeze.

`authorization.json`, `governed-inputs.sha256`, `continuation-freeze.sha256`,
`authorization-consumed/`, `dispatch.ndjson` and `preflight/` retain the governing
records. Original question-only bytes are preserved unchanged.

## Observed routes, not correctness verdicts

| Group | Item | Recorded outcome | Per-need route / discovery | Dated alternative in generation; validated use |
|---|---|---|---|---|
| Fresh confirmation | G1 | completed | n1 C2-resolved; original/effective selected; current reuse equivalent | `n1:date:1` reached generation and was used; planner_disagreement false |
| Fresh confirmation | G2 | interpretation_failed / retrieval_no_answer | No accepted plan; C2 not reached | Unavailable; no generation |
| Fresh confirmation | G3 | interpretation_failed / retrieval_no_answer | No accepted plan; C2 not reached | Unavailable; no generation |
| Fresh confirmation | G4 | completed | n1 C2-fallback: original/effective no_match, no rejection code, guard not_run/discovery_unresolved/original_unbound. n2 C2-resolved: selected, reuse equivalent | `n2:date:1` reached generation; validated use was **current `n2:requested`**, not the dated alternative; disagreement false |
| Partly exposed conflict control | G5 | completed | n1 other: ordinary unbound retrieve; no C2 discovery | None; temporal-use metadata not applicable |
| Development regression | R16 | completed | n1 C2-resolved; original/effective selected; reuse equivalent | `n1:date:1` reached generation and was used; planner_disagreement true |

G1/G4/R16 contain three resolved needs, not three independently graded successes.
G4's fallback need has **no C2 temporal protection**. No planner-bound/C1 or compare
needs occurred. G2/G3 cannot demonstrate C2 because planning stopped first.

Discovery performed four search service calls, four reranks and four reviews
(G1 n1, G4 n1/n2, R16 n1). These are separately recorded and remain uncharged to
the existing logical-lane limit, exactly as the frozen implementation specifies.
There were nine total search service calls: G1 2, G4 4, G5 1, R16 2.
Base/additional logical lanes respectively: G1 1/1; G4 2/1; G5 1/0; R16 1/1.
G2/G3 had no executable plan, rather than a proven zero-capacity requirement.
Final slot allocations, scores, target/version/interval labels, original bindings,
inventoried dates, complete generation requests and version-use receipts remain
in the raw records and hash-chained ledgers. Discovery alone is not a demonstration
that a dated alternative was used, or that the answer is semantically correct.

## Unsuccessful items and harness pause

G2: two completed planner attempts rejected with `schema / needs.0.fact_reference /
fact_date_precision`. G3: first rejected with the same code; second rejected with
`grounding / root / temporal_relation_grounding`. Both retained provider HTTP 200,
finish_reason stop, systemic false, and two-attempt receipts. Production persisted
an interpretation-failure no-answer. Rejected plan bodies are not available and
have not been reconstructed or invented.

The initial reused harness paused after G2 because it treated the outer planner
endpoint's 503 as infrastructure before checking the retained typed diagnostics.
This was a **harness classification defect, not a checkpoint infrastructure stop**.
Under the authorised rule, a completed non-systemic model rejection is permitted
to continue. `continuation/` fixes only that checkpoint classification, tests the
retained G2 and a separate shape plus systemic/provider/authentication/unknown
negative controls, and freezes the original G1/G2 records and ledgers. It dispatches
only G3–R16 under the same authorisation, requires exactly the prior two starts,
uses an exclusive continuation marker, refuses any already-started item, and
rechecks the unchanged original runtime/harness/input freeze. No question was
redispatched; no application handling or validation was changed. The original
G2 stop verdict and both execution logs remain retained beside the later disposition.

No confirmed infrastructure, authorisation, integrity or budget stop occurred.
All unsuccessful item records remain in the six-item denominator.

## Provider-free capture and integrity proof

- Existing controlled C1/C2 development fixtures: final **5 tests / 313 assertions**,
  fake HTTP boundaries, SQLite memory. Original topic-only input16 exercises capture
  of selected family, equivalent current reuse, dated target, generation and reload.
- Initial harness-only fixture expectation wrongly assumed two ordinary search
  hooks on C2; current discovery reuse means one. Initial 5-test/1-failure JUnit
  retained, expectation corrected, then final pass. Application untouched.
- Three initial stop-logic tests and two continuation tests pass; output retained.
  They distinguish typed model rejection from authentication, service/transport,
  final-eligibility, exhausted-deadline and unknown failures.
- A host locale error initially produced an empty container-checksum file; it was
  corrected and re-frozen **before authorisation consumption or any dispatch**.
  The superseded pre-dispatch manifest is retained for accounting of this preparation.
- `verification.json`: exact released questions, six unique persisted conversations,
  one run/turn each, ordered once-only dispatch, ledger chains, and reloaded rendering
  and temporal_context all verified. No new provider calls during verification.
- Every mounted runtime checksum and the repository runtime inventory matched at
  final verification; main and unrelated edits were preserved.

## Accounting

| Category | USD |
|---|---:|
| Recorded service cost estimates | 0.02501152 |
| Additional retained-rate estimates from token receipts | 0.06346175 |
| Recorded/retained-rate subtotal | **0.08847327** |
| Conservative reranker allowance (unpriced receipts) | 0.00218760 |
| Unavailable-cost uncertainty reserve | 0.50000000 |
| Conservative total including reserve | **0.59066087** |
| Authorised cap | 1.50000000 |

USD 0.50 next-item headroom was required at every dispatch, in addition to the
USD 0.50 reserve; initial conservative reservation USD 1.00 was stated before calls.
Retained failed-planner attempt receipts are included, not counted as zero merely
because the outer endpoint provided no usage envelope. Final reconciliation also
uses all nine HTTP reranker receipts, including discovery; it avoids summing those
again through overlapping production observers. The first pre-G2 budget projection
omitted the discovery reranker allowance (USD 0.000329); it was covered by the
uncertainty reserve and included in continuation/final accounting. No cap breach.

Reported provider token totals: 294,359 input / 13,328 output from available
non-overlapping parent receipts. These are not a complete invoice. Reranker pricing
and some infrastructure usage remain unavailable; retained estimates are labelled
with their prior pricing basis, not claimed as current billed actuals. There were
**32 API-to-AI service calls**, not 32 proven physical provider attempts. Native
attempt/checker/entailment details remain in nested receipts, which must not be
added a second time to parent aggregates. Per-stage receipt latency and per-item
wall time are retained; the six item wall times total approximately 244.45 seconds,
excluding preparation and the harness pause.

## Evidence and disposition

- `cases/{G1,G2,G3,G4,G5,R16}.json`: full actual execution records and displayed text.
- `cases/*-ledger.ndjson`: hash-chained calls, production observations and requests.
- `route-summary.json`: mixed per-need routes, dates reaching generation, validated use.
- `accounting-reconciled.json`: receipts, estimates, allowances and nonadditive nested data.
- `persistence-verification.json`, `verification.json`, `preflight/`: persistence and freezes.
- `checksums.sha256`: final package checksums; `checksums.sha256.digest`: aggregate digest.

Published to the hold branch only after execution. Local Journey updated; unrelated
ADR/runner edits excluded. **No grading, main merge, Block 1 closure, fix, retry or
further live work is authorised or performed. Independent reviewer assessment is next.**
