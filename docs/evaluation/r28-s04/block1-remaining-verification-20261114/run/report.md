# Block 1 remaining verification — 14 November 2026

**Recommendation: HOLD. All 18 authorised items executed once; no further run or repair.**

This is `R28-BLOCK1-REMAINING-20261114-01`, a new-runtime cohort, not a continuation or replacement of historical inputs 1–16. Execution HEAD was `c28e69c15a1ae03f900fdb60ca579aafab8cb34f` on `codex/block1-extractive-hold-20261112`. Application code is the coordinated `1f30cef2` runtime, aggregate **`cbff39c23c64c7226ca3592248816884f2af8a5dcf7d5672f125d524078ba238`**. Main and unrelated edits were not changed. No application, prompt, threshold, retry or validation changes were made for this run.

## Authority, freeze, deployment and execution

- David's latest amendment raised this run's ceiling to **USD 50**, not expected spend. The conservative reservation was **USD 47**, stated before consuming `R28-REMAINING-AUTH-20261114-01`. `reservation.json` enumerates every permitted attempt under unchanged production limits: stage subtotal USD 41.741388 plus USD 5 uncertainty, rounded up. The former USD 10 tooling stop remains historical evidence, not a result.
- Future governed ceilings are **max(USD 50, twice the stated conservative reservation)**. This future rule does not retroactively change this explicitly authorised USD 50 run.
- `authorization.json` binds the exact harness/input manifest SHA **`0f23fbff24783ef22a87c8c60af7c6c5765eabeea94ed7bc3b8b8034aa0330d0`**. Exclusive `authorization-consumed/` creation prevents another consumption. `run_once.sh` refuses existing case files and checks HEAD, branch, host and mounted runtime, input/harness digests before every item and at completion.
- The existing dirty `scripts/evaluation/run_r28_r06_governed.py` and its test were neither imported nor executed. The evidence-local executable harness was derived from committed C2 capture, frozen explicitly, and copied byte-for-byte to the isolated API. `container-harness-*.txt` verifies the executed PHP files. It is not an alternative planner and injects no plans.
- Input order was 17–32, G2, G3. Original checkpoint input SHA `ee4fb0903fc0feff93510266455a3c8a520938787d25b5c7027a680e402dd951`; released G input SHA `7603fc82fc2f95f9852f584f28cc6a3d4949a2a30e17b8ab3b56a52ce4d2899f`. No protected reviewer material was read. Frozen expectations were read only for post-execution grading, never sent to execution services.
- Isolated project `dolved-r28-s03`, connection `pgsql/postgres/dolved_e2e_r28_s03`, primary workspace `5323cd6b-8b7a-40f9-98b0-9413c75f643e`, actor `ae454ce6-4d91-4c7e-9a40-b4750b696a6b`. Membership verified. Active corpus `b24a79c2-875e-4f7c-8fa9-3681db92ae73`: 986 assigned chunks, zero foreign assignments. Corpus identity checked before every item.
- Owner-role migration-status inspection found **50 Ran, zero pending**. No migration or privilege change was made. API/AI were restarted together; mounted source/contract hashes verified. Evidence: `preflight/migration-status.txt`, `deployed.json`, deployment checks, mounted inventories, `runtime-before-*.json`.
- One new authenticated, persisted conversation and one user turn per item; real orchestrator, planner, eligibility, Qdrant/reranking, generation and persistence. Production retry limits unchanged. Start **17:56:26Z**; final record **18:08:58Z**. `dispatch.ndjson` has 18 starts/18 records, in order, no replay. The 17 generation endpoint calls include two extra validation attempts, not 17 distinct answered questions.
- Deferred grading was authorised. There was **no typed infrastructure, authorisation, integrity or budget stop**. Safe item-level rejections were retained and execution continued as instructed. This does not certify semantic authority/answer correctness.
- Provider-free pre-dispatch checks: **4 stop-classification tests**, and **5 actual orchestration/capture tests / 313 assertions**. Raw JUnit under `proof/`. PHPUnit result-cache I/O warnings are retained; tests completed successfully. No full application suite was rerun for this harness-only task. The unchanged runtime's retained 1,426 AI / 928 Laravel tests have the same **38 / 2** failure identities in `../../block1-calendar-notation-provider-free-20261114/coordination/`; no new repair or resolved-failure claim.

**Capture limitations were declared before dispatch:** rejected planner output and calendar-normalisation count are unavailable. Accepted plans, closed stage/field/code, completion receipts, routes, discovery, targets/use, allocation, answers/citations, persistence and available usage are retained. An accepted plan or correct answer is not proof normalization occurred. Later diagnostics would be new observations, not reconstruction.

## Outcomes and source-based review

Items **17–32 only**: stored status **14 completed / 1 clarification_required / 1 retrieval_no_answer**. Two completed records (29,31) display the controlled no-answer text, giving **12 substantive answers / 1 clarification / 3 no-answers** literally.

Manual implementing-agent source review, not an independent human review or paid model judge:

| Population | Correct | Qualified-correct | Wrong | No-answer (including clarification) |
|---|---:|---:|---:|---:|
| New-runtime 16 | 7 | 2 | 3 | 4 |
| Inputs 17–30 (14) | 6 | 2 | 3 | 3 |
| Development 31–32 (2) | 1 | 0 | 0 | 1 |

Full literal answers, exact source quotations, frozen expectations and i–iv attribution for **every** reviewed item are in [correctness-review.md](correctness-review.md) and [correctness-review.json](correctness-review.json). `review-chunk-snapshot.json` is a read-only, primary-workspace-scoped pull of observed ranked chunks and the frozen expected versions. Review-only sources are explicitly distinguished from passages actually supplied to generation. A copied span, accepted checker verdict or evidence ID is not proof of complete semantics.

| Input | Route actually exercised | Final chunk IDs | Verdict / principal finding |
|---|---|---|---|
| 17 b03-10 | compare; genuine reviewer no_match | none | No-answer. Correct accepted before/current request, no selected family. Block 2 topical comparison. First planner rejection also retained. |
| 18 b04-05 | C2 ambiguous → original unbound dated retrieve; guard not run | 863,911,912,862,864 | Correct higher-tier classification and retention. Not a C2 protection success. |
| 19 b04-07 | bound compare, historical_reference → ambiguous authority window | none | Clarification rather than update comparison. Shared Block 1 selector / Block 2 period route; not solely downstream. |
| 20 b04-09 | two ordinary retrieves | 757,757,758,758,872 | Correct Champion rename and unchanged responsibilities. |
| 21 b05-01 | C2 selected/current reuse + separate before-July compare | 660,660,419 | Correct IT now / Training Team before July. C2 targets same version for current/July; no extra distinct-version search. |
| 22 b05-03 | C2 selected rejected: quote_not_in_candidate → fallback | 361,360,362,442 | Correct Facilities/quarterly. Guard not run. |
| 23 b05-05 | C2 selected rejected + compare selected rejected, both quote_not_in_candidate | 857,858,860,859,48 | Qualified-correct current HR Director; prior role explicitly unconfirmed despite current-side source describing it. Block 2/3 isolation. |
| 24 b05-07 | current retrieve + April transition compare | 709,709,468,866,300 | **Wrong:** senior-carer alternative imported into mandatory manager+pharmacy breakdown instruction. Block 3 conflation; required source present. |
| 25 b05-09 | before-January compare | 693,452 | Correct ten versus six weeks. Existing retry corrected initial schema rejection. |
| 26 b06-04 | three ordinary retrieves | 849,849,674,617,269 | **Wrong:** control becomes MDM instead of Device encryption, care on-call route mixed with cyber route. Missing encryption evidence and wrong need association prevent solely-Block-3 attribution. |
| 27 b06-05 | previous-field compare + current retrieve | 692,451,274,706,672 | Qualified-correct rename and explicit missing lawful-basis value/budget disclosure. Distracting record detail and incomplete n2 antecedent context remain limitations. |
| 28 b06-06 | three ordinary retrieves | 697,697,934,935,696 | **Wrong:** site Champion title silently absent; escalation Lead substituted. Existing Block 1 role-sense finding, with evidence/assembly contribution. Finance record role is correct; renamed-from wording not required. |
| 29 b06-11 | bound C1, same-version July/current targets | 702,702 | No-answer after two temporal_target_invalid generation rejections. Version text present; unresolved site coverage and missing rejected drafts prevent sole downstream attribution. |
| 30 b06-12 | C2 selected rejected: quote_not_in_candidate → fallback | 361,360,362,124,815 | Correct same-day manager report, cause and action. Guard not run. |
| 31 b01-01 **development** | two bound retrieves, no dated augmentation | 69,323,72,70,71 | No-answer after two citation_ownership rejections. Induction binding excludes Handbook's actual access-check passage; not solely generation. |
| 32 b03-05 **development** | two ordinary retrieves | 980,981,981,957,957 | Correct three weeks and leaving steps. Service duration not misused as authority date. |

Duplicate numeric chunks here are separate need-owned evidence items, not accidental cross-need permission. Full public document/version IDs, dates, side and per-need ownership remain in raw records.

### G2/G3 — separate, ungraded known regressions

- **G2:** completed. C2 selected; current evidence reused and **one additional distinct-version search** recorded. Literal answer: “The current Alderbridge procedure requires soiled linen to be washed at 65 degrees Celsius.” It adds a temporal-basis line identifying evaluated-at and version `8393e791-8177-4568-8660-bfdd4efcfab3`, effective 2026-05-01. Raw record/targets/use are supplied for the independent reviewer. **No implementation-side correctness verdict.**
- **G3:** retrieval_no_answer after **two** `grounding / root / temporal_relation_grounding` rejections, provider status 200, systemic false; final completion `stop`. Literal answer: “I couldn't interpret this question reliably, so I haven't used a guessed interpretation to search or answer it. Please try rephrasing your question.” No accepted plan, retrieval or generation. **No correctness verdict or reconstruction of missing output.**

## Mechanisms, checks and limitations

`post-run-checks.json` proves persisted assistant text and reloaded rendering (where an answer exists) match captured display for all 18, one turn each. Each executed per-need query and generation coverage label checked against the current extractive compiler; every question/context quote checked against the actual user turn. **No invented executable query/coverage wording found.** This structural check is not coverage proof.

There were **17 interpretation checks, all accepted, zero flags of either verification status**, hence no new false-flag retry in this cohort. Retained overhead totals: **21,510 input / 1,927 output tokens; 36,411.671473 ms** checker latency. These are summed receipts, not wall-clock overhead or general false-positive rates. G3 never reached the checker. Historical b01-10's false flag remains on its historical run, not added to or erased by this cohort.

Inputs 17 and 25 each had a first-attempt `schema / needs.0.authority / temporal_relation_grounding` rejection followed by an accepted second plan. G3 had two grounding rejections. Rejected plans unavailable in all four attempts; do not call the rejection justified/unjustified from its code alone. No calendar-normalisation count is inferable.

Six C2 discoveries across all18 (18,21,22,23-n1,30,G2), each actually used **one discovery search, one reranker request and one family-review request**. Four fallbacks: one genuine ambiguous, three selected→no_match quotation rejections. They are **not protected C2 answers**. Two selected routes: 21 and ungraded G2. Separate comparison selection adds six reviewer calls. Total service endpoints: **18 planner, 37 search, 37 rerank, 12 family-review, 17 generation**; these are API/AI calls, not asserted physical provider-call totals. `final-summary.json` retains per-item work and target receipts. No lane accounting was changed: discovery remains separately measured/not charged to the four logical lanes. Observed base lanes ≤3, additional distinct-version lanes ≤1, final slots ≤5. No capacity overflow or saturated alternative-lane refusal was exercised here.

Input 27 does exercise **budget-cut disclosure**: n2 five eligible units, three selected, two cut; answer explicitly says omitted eligible evidence does not imply absence from source documents. Conversely 29/31 display “couldn't find enough available evidence” despite evidence having reached generation. They are safe from publishing rejected drafts, but this is not a truthful explanation of validation exhaustion; the existing Block 3 fallback-message obligation remains.

## Compatible baseline comparison — not the full-selection gate

Existing ADR-0020 matcher/metrics, normalized-token coverage, **K=5**, unchanged required units. Existing per-side round-robin of per-need reranked lists. One variant per case, so case-first equals variant mean. `metrics-a.json` and `metrics-b.json` are byte-identical on a second provider-free computation.

Input21 is excluded from ranked-list comparison: C2 stores the rechecked, post-selection current subset, not the old pre-threshold current reranked list. Scoring it as an equivalent original ranked list would be misleading. Input29 has one distinct version per need and its normal reranked stages remain comparable. Development31/32 are reported separately. G2/G3 excluded entirely.

| Same-case subset | n | Baseline Recall@5 | New Recall@5 | Baseline Precision@5 | New Precision@5 | Baseline MRR | New MRR | Baseline nDCG@5 | New nDCG@5 |
|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| PRIMARY, both runs attempted | 10 | .7533333333 | .8333333333 | .2 | .2 | .72 | .85 | .6882334487 | .8162705130 |
| COMPARISON, cases both runs attempted | 6 | .3333333333 | .5 | .0666666667 | .1 | .3333333333 | .5 | .3333333333 | .5 |
| PRIMARY, compatible 17–30 subset incl. pre-search zero | 13 | .7333333333 | .6666666667 | .2 | .1846153846 | .6692307692 | .6794871795 | .6251085718 | .6521810448 |
| COMPARISON, same compatible cases with required comparison units | 9 | .3333333333 | .3333333333 | .0888888889 | .0666666667 | .3333333333 | .3333333333 | .2903496881 | .3333333333 |

“Both attempted” means a case reached some reranked list in both runs, as in the frozen metric method; an absent comparison lane remains zero, not excluded. Exact denominators/IDs are in JSON. Common-attempted deltas +.08 primary / +.1666667 comparison meet the -0.03 bound **only on this limited common subset**. The 13-case primary end-to-end comparison falls .0666667. This is not a full30/32 pass, nor causal evidence that concatenated wording helped/hurt ranking: route, binding, model sampling and selected evidence differ.

Lost baseline units, with significance:

- 17: `ev-b03-10-new-scope` — applicability-critical; discovery stops before search.
- 19: `ev-b04-07-new-week1`, `new-week2`, `old-week1`, `old-week2` — both sides of the requested update; authority clarification stops search.
- 26: `ev-b06-04-encryption-new` — the requested control identity; wrong answer now substitutes a different control.
- Development31: `ev-b01-001-500`, `ev-b01-001-329` — wellbeing summary and actual induction access check; restrictive family binding excludes Handbook. Primary Recall **1→0**. Development32 primary Recall **0→1** (baseline stopped pre-search); not an attempted-search improvement estimate.

No wrong-side required-version occurrence was found in these compatible reranked records (`wrong_side: []`). This does not prove all temporal interpretation correct and does not include the excluded C2 reuse record.

## Position against Block 1 criteria

| Plan criterion (quoted) | Position / evidence |
|---|---|
| “supported shapes execute with all needs represented and sides intact” | **Not met as a complete semantic claim.** 19 cannot execute its update comparison; 28 misses required role sense; 26 has wrong evidence sense; 31 excludes required family. Extractive wording alone is insufficient. |
| “overflow is honest” | **Retained earlier proof; unexercised here.** This cohort has no overflow. No fresh claim. |
| “no new permission, authority, ownership or material correctness failure” | **Not met overall:** wrong24/26/28; shared authority interpretation19; rejected citation/target drafts29/31 safely withheld. No observed published cross-tenant/ownership violation, but not a dedicated adversarial confirmation. |
| “Budget-limited support must not be described as absent source evidence.” | **Exercised on27**, with explicit budget disclosure. Validation-exhaustion fallback29/31 remains misleading for a different reason, owned as already-recorded Block3 failure-message work. |
| “Block 1 holds on any wrong answer or no-answer caused by its interpretation or execution responsibilities” | **HOLD:** 19 shared selector/period route, 28 recorded role-sense recurrence, 29 unresolved C1 output-contract failure, 31 restrictive binding. Shared/unknown causes are not passes. |
| “(a) (a false flag ending in wrong/no-answer), (b) (more than two retry-triggering false-flag questions)” | **No new trigger in these18:** zero checker flags. Earlier results unchanged; no combined 32-question statistic. |
| “Recall@5 decline of at most 0.03 per side” | **Full-selection unverified.** Common attempted compatible subset meets it; lost authority/identity units and primary end-to-end decline must not be hidden. No all32 runtime-homogeneous claim. |
| “No executed query or coverage label contains wording absent from the user's own words and the validated typed fields.” | **Met in observed accepted execution consumers:** compiler/replay checks retained. Rejected plans unavailable. |
| “The full AI and Laravel suites show no new failures beyond the 9 already listed for Block 4 (and the 31 for Block 3).” | **Retained runtime evidence: exactly38 AI/2 Laravel.** No application change here; not rerun or claimed green. |

Still unevidenced: full-selection same-runtime non-regression; general temporal semantic correctness; reliable rescue from wrong authority where the alternative truly changes the answer across the intended breadth; overflow/capacity route in this cohort; rejected-plan interpretation and normalization incidence; independent G2/G3 assessment. No extra readiness gate is invented from the unavailable capture.

Mandatory Block3 work remains intact: historical inputs10 misleading exhaustion fallback and15 dropped agency-timescale qualifier; earlier input4 induction overclaim and input13 offered→requires strengthening; C1 F5/T12 conflicting-selector disclosure; the 26 polarity +5 live-safety test-contract updates; evidence-isolation/coverage/provenance obligations. This run adds24 contact-condition conflation,23 cross-need historical disclosure gap,26 shared wrong-sense assembly, and29/31 misleading exhaustion wording. These are obligations/findings, **not authorisation to fix or reassignment of unresolved Block1 causes**. Block2 quotation rejection/period routing remains; Block4 the seven named AI/two Laravel baseline failures, pricing/observability and readiness reconciliation remain.

## Cost and artifact verification

`accounting.json` preserves dispatch-time accounting: USD **0.30510164** recorded/retained-rate subtotal. G3's service usage was null; post-run `final-summary.json` supplements its two retained rejection receipts (37,350 input /1,769 output tokens), **USD0.01287550**, without double-counting a parent receipt. Final known/retained-rate subtotal **USD0.31797714** + conservative reranking allowance **USD0.00646270** = **USD0.32443984**; retaining USD5 uncertainty gives **USD5.32443984**, below both reservation and cap. This is **not an invoice total**. Planner/checker/family prices use retained .25/2 per-million rates; cached input unknown is treated uncached. Reranking/infrastructure receipt gaps and current invoice pricing remain unavailable. Nested checker/entailment receipts are already in parent totals, not added again.

`checksums.sha256` freezes all publication artifacts except itself and its verification/aggregate sidecars; caches excluded. `governed-inputs.sha256` is the separate pre-dispatch freeze and remains unchanged. Raw cases, ledgers, before/after runtime/corpus checks, auth consumption, JUnit, source snapshot, metrics, persistence and review are all supplied. The post-run analysis scripts are never execution inputs. No historic records, expectations or checksums were rewritten.

**Stop here:** evidence/plan publication to hold branch only, Journey local; no repair, main merge, Block1 closure or further provider calls.
