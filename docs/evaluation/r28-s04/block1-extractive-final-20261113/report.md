# Block 1 bounded continuation — 2026-11-13: HOLD

## Outcome and preservation

Harness commit **dc36f377dcc7a3b02a9c0eaa57f16bc3f63737eb** was pushed only to `codex/block1-extractive-hold-20261112`. Its fixed `block1_extractive_final` stage pins the exact pushed commit, unchanged runtime, frozen selection and both prior ledgers/inventories; refuses any prior identity; and retains one-use authorization and existing-stage main restrictions. **46 harness tests passed**, including duplicate/pin/freeze/authorization/main guards (`harness-tests.log`). No application, prompt, assertion, validator, retry, threshold or expectation changes.

Fresh authorization `R28-R07-AUTH-BLOCK1-EXTRACTIVE-FINAL-20261113-01` dispatched **input 16 once**, then stopped before 17. No input 1–15 was redispatched. Inputs 17–32 are explicitly unattempted, including development inputs 31/32. The original checkpoint remains FAILED at input 15 under its original criterion; nothing here relabels that history. Both preceding raw packages, their verdicts and inventories remain unchanged. Runtime before/after is **0eab315797d6b8af9baea21b85a867386c83bf425c7719ffb16b9974bc9acdc8**. The manual gate's exit 1 is the deliberate stop, not an application operational failure. Main was not merged.

## Input 16: dated supplier routed to current authority

Question: “Who provided the daily cleaning at Harbour View on 1 February 2026?”

Displayed: “I couldn't find enough available evidence to answer that question.” Raw status `completed`; literal verdict **no-answer**. One planner attempt/check accepted, no interpretation flag. The compiled request preserves the words, but `authority.kind=current`, `fact_reference.value=2026-02-01`; `need.n1.plan.temporal_mode=current`, `explicit_date=null`. This is not proof of temporal coverage merely because the date appears in the query.

The read-only frozen `authority-source-snapshot.json` establishes:

- v1, document `95327181-051d-4dc9-bae4-2a9bdc06e626`, effective **2025-06-01**, source SHA `ae84a141b7721d0da26d7454c380f3ab3910c618aa3ef75ef30a5ac878329ac5`: “Harbour View\tCleanCo South West Ltd\tDaily”. Required chunk `ef285503-119f-500d-91b8-0a3f597872a9` never entered eligibility or generation.
- Retrieved v2, document `b8e7b8df-cbd5-482b-a294-be5022b06d54`, effective **2026-06-01**: “All sites, all regions\tCleanCo National Ltd (successor to CleanCo South West Ltd's framework)\tDaily”. Chunk `42cc2beb-792a-5415-bbda-9565483900e9` is rank 1 and present in `generation_input`.

Earliest evidenced mechanism: planner date-role classification accepted by the interpretation check; execution faithfully used current authority. `EvidenceNeedsPlan.php:99–123` maps current to Current, only date/period/before/after to ValidAtDate, and carries factDate separately. `needs_planner.py:80–88` explicitly distinguishes those roles. This is **Block 1 authority/interpretation**, not a quotation-only issue. Generation then exhausted two `entailment_uncertain` rejections. Drafts are unavailable: neither bad drafts nor overcautious review is established. Correcting the authority is not proven sufficient to repair generation. The no-answer has a confirmed upstream error and unresolved contributing generation behaviour; it cannot be assigned solely to Block 3. The authority-critical lost unit independently holds the block.

## All 32 review positions

`correctness-review.json` contains all 32 input identities: for every attempted record, literal displayed text, frozen expectation, full exact selected source text, compiler replay, checker receipts/plans, citations and verdict. The 16 unattempted records have no fabricated verdict. No provider judge was used. Original 1–15 verdict categories remain unchanged; the new report explicitly corrects the description of input 10's misleading fallback without editing its historical artifact.

| Input | Case | Verdict / responsible mechanism |
|---|---|---|
| 1 | b01-02 | Correct; dated gas-reporting contacts |
| 2 | b01-04 | Correct; both repositioning intervals with risk qualifier |
| 3 | b01-05 | Correct answer; existing partial EvidenceUnit coverage |
| 4 | b01-06 | Correct; application-grounded period flag unverifiable, no retry |
| 5 | b01-07 | Correct; both allowances and versions |
| 6 | b01-08 | Correct; scope and explicit outreach extension support answer |
| 7 | b01-09 | No-answer; Block 2 selected quotation rejected |
| 8 | b01-10 | Correct after one verified-but-wrong omission flag |
| 9 | b02-01 | Correct; controls and records |
| 10 | b02-03 | No-answer; Block 3 exhausted uncertainty, misleading evidence-absence message |
| 11 | b02-06 | Correct; sector-wide guidance/debrief |
| 12 | b02-07 | Correct; ordering changed, requirements unchanged |
| 13 | b02-08 | Correct; existing DSE-user condition retained |
| 14 | b02-09 | Correct for explicitly named previous version; older-version ambiguity remains Block 2 |
| 15 | b03-01 | Wrong; Block 3 omitted agency-timescale condition; original checkpoint failure |
| 16 | b03-07 | No-answer; Block 1 current-authority misrouting, generation contribution unresolved |
| 17–30 | b03-10, b04-05, b04-07, b04-09, b05-01, b05-03, b05-05, b05-07, b05-09, b06-04, b06-05, b06-06, b06-11, b06-12 | Not dispatched after hold |
| 31–32 | b01-01, b03-05 | Development cases, not dispatched; not unseen confirmation |

Totals: **12 correct / 1 wrong / 3 no-answer / 16 unattempted**. Literal answered 13 (including the wrong one); raw completed 15, retrieval_no_answer 1. Each wrong/no-answer has explicit (i)–(iv) attribution in the JSON. Input 7 never reached generation, so source adequacy there is not claimed. Input 10 retains both requested clauses, correct dated v1 and owned passages. Input 15 retains the full question/current scope and the source condition in generation input; the answer drops that condition. Input 16 fails authority and required-source checks.

Evidence limitation for (iii)/(iv): traces retain generation evidence identities, not a full request-body dump. Whole-passage forwarding, need ownership and instruction linkage are established from those IDs, frozen source text and pinned `AssembleGenerationRequest.php:65–130`, `GenerateGroundedAnswer.php`, `needs_request.py:68–85` and `openai_adapter.py` (`grounded-generation-needs-v12`). The assembly passes complete selected chunk text and per-need authority/question_span/compiled request; the prompt gives the user's span primacy. This is code-and-record linkage, not a claimed retained HTTP payload. It does not excuse the current-authority mismatch.

## Recall@5 and lost evidence

Existing ADR-0020 matcher, `normalised-token-coverage-v1`, K=5. One variant per selected case, arithmetic case mean = variant mean here. Primary/comparison remain separate. Two offline computations are byte-identical (`results-a.json`, `results-b.json`).

| Population | Primary baseline → candidate | Comparison baseline → candidate |
|---|---|---|
| Frozen full 30 baseline attempted population (candidate incomplete) | 25 cases: **0.8813333333** → unavailable | 13 cases: **0.4615384615** → unavailable |
| Candidate attempted among observed 16 | baseline population differs → 15 cases **0.9000000000** | baseline population differs → 6 cases **0.8333333333** |
| Exact same-case attempted intersection among observed 16 | 12 cases **0.9583333333 → 0.8750000000**, delta **−0.0833333333** | 4 cases **0.7500000000 → 0.7500000000**, delta 0 |

The full-selection ≤0.03 criterion is **unverified**, not a pass. The partial primary intersection already falls more than 0.03 and loses authority-critical evidence. Different attempted populations cannot establish an improvement. Development cases are excluded and unattempted cases are not scored as retrieval zeroes.

`missing-and-lost-units.json` records every missing required unit with its full frozen definition. Exactly **one newly lost baseline-covered unit**: `ev-b03-07-harbour-cleaning`, primary, input16: historical supplier identity unavailable after current-version selection. This is authority-critical, not a harmless lexical miss. Other missing units are existing, not newly lost: input3 primary `ev-b01-005-127`; input7 primary `ev-b01-009-108`, `ev-b01-009-103` and comparison `ev-b01-009-101`; input14 comparison `ev-b02-09-historical-overtime-rate` (v1 expectation versus explicit previous v2 reading). No evidence supports blaming awkward concatenated query wording for this observed loss: the relevant historical document was excluded before ranking. No general recall-cost conclusion about concatenation can be drawn from this incomplete run.

## Flags, costs and acceptance

One false-retry question: **input8**, citation “When ‘Over the counter medicines’ became ‘Homely remedies list’” (exact curly-quote text in JSON), `clause_omitted`, verified, final correct. Overhead **2 calls, 19,830 input / 1,235 output tokens, 14,609.41734 ms**, retained-rate estimate **USD 0.0074275**. Input4's `scope_added` citation on app-computed `2026-10` was unverifiable `application_grounded_period`, no retry; full plan manually reviewed, no missed omission/scope change. Input16 check accepted. No additional false flag. Observed 1/16, against ceiling 2/32; incomplete population, not proof for unattempted questions.

Reservation before dispatch: prior subtotal **0.23752892**, conservative reranking allowance **0.00665295**, additional unpriced uncertainty reserve **0.50**; committed allowance **0.74418187**, available after reserve **2.25581813**, not guaranteed invoice balance. Required next-case headroom **0.50**. After input16: subtotal **0.25950571**, reranking allowance **0.00700440**, reserve **0.50**, conservative committed **0.76651011**; available after reserve **2.23348989**. Incremental priced/estimated subtotal **0.02197679**. Complete billed cost unavailable: local infrastructure and missing provider receipts remain explicit, not zero. Retained-rate estimates and physical-attempt rerank allowance are detailed in `accounting.json`; no duplicate checker/entailment aggregate counting. Stop was correctness, not budget exhaustion.

| Plan criterion / fixed checkpoint condition | Position |
|---|---|
| “supported shapes execute with all needs represented and sides intact” | **Not met:** dated supplier question executes current authority; lexical inclusion is insufficient. |
| “overflow is honest” | **Met on retained capacity-overflow proof**, not re-exercised here. |
| “no new permission, authority, ownership or material correctness failure” | **Not met:** input16 wrong authority route; no observed tenant/ownership leak. |
| “Budget-limited support must not be described as absent source evidence” | **Met on retained exercised budget-cut branch**; unrelated exhausted-entailment messaging remains mandatory Block 3, not waived. |
| “Material allocation/design/safety failures hold this block” | **Met as procedure:** hold and stop applied. |
| “Unrelated live failures are preserved and assigned, not patched automatically” | **Met:** no application repair. |
| False flag ending wrong/no-answer; more than 2/32 false-retry questions | **No observed breach; full selection unverified:** one ends correct. Old zero-false-flags criterion superseded by David. |
| Stage-specific no wrong/no-answer from Block 1 interpretation/execution | **Not met:** input16; original any-cause criterion remains FAILED at15. |
| “the four retained omission counterexample plans through the real check” | **Met on retained 4/4 proof**, no fresh replay claimed. |
| “Record check tokens and latency per question” | **Met** for every observed attempt. |
| “There remain at most two planner attempts in total” | **Met** on observed records and retained tests; no extra attempt. |
| “Every dropped flag must be manually reviewed for missed real omission/scope change” | **Met:** input4 reviewed; none new. |
| “Unexercised routes remain unverified” | **Met in reporting:** 17–32 explicitly unattempted. |
| No invented executable wording | **Met for 16 observed compiler replays**, full selection unverified; diagnostic aspects remain non-executable. Typed-authority accuracy separately fails. |
| Recall@5 decline ≤0.03 per side, full same selection | **Unverified full gate; partial primary fails**; authority-critical lost unit independently blocks. |
| Suites exactly 38 AI / 2 Laravel, no new failures | **Retained full-suite evidence applies:** runtime unchanged since `block1-extractive-fixtures-20261113/suite-comparison.json`; no new app test run claimed. Harness 46 pass. Baseline failures remain assigned, not readiness-waived. |
| No new interpretation-failure/clarification on clear questions | **No new explicit clarification observed**, but semantic authority failure holds; full32 unverified. |

## Mandatory Block 3 acceptance list

Not optional backlog and not repaired here: (1) update 26 polarity and 5 live-generation safety baseline tests to current contract without weakening assertions; (2) authorised cross-need support/ownership and coverage assembly without false absence or evidence pooling; (3) source-condition/obligation strengthening, retaining the induction conclusion, offered→requires and input15 agency-timescale omission as explicit acceptance examples; (4) categorical lead contradicting unresolved coverage, including the Oakfield allowance finding; (5) input10 exhausted entailment uncertainty: determine draft versus reviewer fault without guessing from receipts; (6) misleading evidence-absence fallback when sufficient evidence reached generation. Existing historical-source/side integrity and live/persisted rendering obligations remain. Input16 is **not** reassigned to this list as solely Block3: its upstream temporal error remains Block1, with generation contribution unresolved.

**Recommendation: HOLD Block 1; do not merge.** Earliest confirmed blocker is dated supplier authority classification, accepted by the check. No fix or further dispatch is authorised by this result. Client readiness additionally requires the agreed absolute thresholds, zero observed wrong answers and no unresolved known material defects. Finite checks are not a universal guarantee.
