# Extractive checkpoint — STOP / Block 1 HOLD, 2026-11-13

Executed commit `ea678bac64462b24a039592ab9d951bd679da397` on the approved
hold branch only. Main remains `348f09e053c8ad73676921c5c616cced812111d7`.
No main merge, tuning, behavioural correction or selective rerun followed this stop.

Raw package: `../r06-governed-evidence/R28-R07-BLOCK1-EXTRACTIVE-20261113-01/`.
It contains authorization, fixed execution manifest, dispatch ledger, eight raw
case JSON records, exact executed harness copies, before/after freezes and checksums.
The 30+2 selection was committed before the first result. One-use authorization
was consumed once; each of the eight dispatched inputs was attempted once through
the production conversation path (native planner retry remains permitted).

## Stop evidence — b01-10/v1 (checkpoint input 8)

The plan's standing criterion is **“every citation-valid wrong flag triggering a
retry on a clear question fails the zero-false-flags criterion and stops the
checkpoint”**. This happened here, despite a successful final answer.

Question: “When ‘Over the counter medicines’ became ‘Homely remedies list’, did
the rule about which remedies may be given change?” (raw record retains curly
double quotation marks exactly).

First check: `checker_code=clause_omitted`, effective `code=clause_omitted`,
`citation_verification=verified`; cited user clause:
`When “Over the counter medicines” became “Homely remedies list”`.

This clause is present in the first plan's **compiled executable aspect**, which
is the entire original question, and in its typed previous-authority selector.
It is not merely a copied provenance span. Operation is `compare`; the question's
rename and rule-change meaning is represented. The citation verifier proves the
user quote and inspected-need identities, not the correctness of the omission
judgement. The verified but wrong flag consumed the second planner attempt.

The second check accepted. **The first and second compiled executable aspect
strings are byte-identical**, proven in `record-review.json` by replay with the
production compiler. This supports the false-flag diagnosis directly; no rejected
raw model output or inferred checker explanation is needed. The retained checked
plans and citations are available for independent review. No checker change is
proposed or implemented in this pass.

## Every dispatched input

| # | Case / v1 | Route and displayed outcome | Mechanism / review |
|---|---|---|---|
| 1 | b01-02 | Compare; Facilities Lead before March → 24-hour Estates Helpdesk after. | Extractive context plus original question; two authorised version lanes; accepted checker. |
| 2 | b01-04 | Compare; 4-hour → 2-hour highest-risk repositioning interval. | Native first-attempt validation rejection, then accepted transition-period plan and both sides answered. No false checker flag. |
| 3 | b01-05 | Retrieve; outbreak response plus daily case/decision logs. | Both requested parts retained; explicit same-site/suspected-infection condition preserved in answer. Required-unit Recall@5 is 0.5, not scored as perfect. |
| 4 | b01-06 | Security workspace retrieve; approved mileage paid in next salary run. | Relative month application-grounded. Checker scope flag cites `2026-10`; unverifiable `application_grounded_period`, no retry. Manual review finds no dropped clause or added scope. |
| 5 | b01-07 | Compare; starter £40→£55, annual £25→£35. | Both amounts and both versions answered; accepted checker. |
| 6 | b01-08 | Scope fact plus retrieve; recorded Oakfield Lodge scope, explicit outreach extension, changes reported within 24 hours. | Original two parts retained; operational extension appears explicitly in authorised policy text, not inferred solely from scope fact. |
| 7 | b01-09 | Compare plan; honest insufficient-evidence message, no final reranking lane. | Existing Block 2 mechanism: reviewer `original_decision=selected`, effective `no_match`, rejection `quote_not_in_candidate`; 15 discovered/hydrated/reranked, 12 shown to reviewer. This is not a new planner clarification. |
| 8 | b01-10 | Compare; unchanged remedies rule, MAR/care-plan qualifications retained. | Successful answer after the false omission flag described above. **STOP** at review gate. |

Literal displayed text, full checked plans, all citations and recompiled requests:
`record-review.json`; final evidence and all production stages remain in raw cases.
Eight records: **7 completed / 0 clarification / 1 retrieval_no_answer**.
Nine interpretation checks: seven accepted verdicts, one unverifiable scope flag
correctly ignored, one verified-but-wrong omission flag causing retry. The accepted
retry is included in the seven accepted verdicts. No observed tenancy, citation or
authority violation caused the stop.

Inputs 9–30 and development inputs 31–32 (b01-01/b03-05) were **not dispatched**.
They are not passes, and no development outcome is claimed.

## Retrieval metrics — partial evidence, not acceptance

Existing ADR-0020 `compute_metrics.py` candidate adapter and
`metrics.py`/`matching.py`, normalised-token-coverage-v1, **K=5**, unchanged units.
One observed candidate absent from the historical 499-chunk snapshot was hydrated
from the authorised store in a read-only transaction; the added text/identity and
checksum freeze are retained. No expected material entered execution.

| Side | New attempted records | Partial Recall@5 | Same-variant attempted intersection | New / old intersection Recall@5 |
|---|---:|---:|---:|---:|
| Primary | 7 | 0.92857143 | 4 | 0.875 / 0.875 |
| Comparison | 4 | 1.0 | 2 | 1.0 / 1.0 |

Do **not** compare these partial means to the frozen full-selection baselines
(25 primary attempts / 13 comparison attempts). The full 30-case tolerance gate
is **unmeasured**, not passed. b01-09's pre-search stop is reported separately;
it is not silently averaged into attempted-search ranking. `results-a.json` and
`results-b.json` are byte-identical after independent deterministic reruns. Per-case
required-unit coverage and ranked identity/side lineage are included.

## Verification, cost and acceptance

Fixture proof: `../block1-extractive-fixtures-20261113/report.md` and raw JUnit.
Full suites exactly match **38 AI / 2 Laravel** baseline failures, zero new or
resolved baseline failures. All 15 held-candidate failures were resolved by fixture
migration, no weakened assertions. Harness suite **27/27**. Typecheck/lint were
run and their unchanged baseline issues are explicitly attributed, not called green.

Runtime aggregate before/after:
`0eab315797d6b8af9baea21b85a867386c83bf425c7719ffb16b9974bc9acdc8`.
Configuration/index snapshot before/after:
`f271c1ac843e53aaa16f6f0c70f6f3b642499d69dac2467d17e80c42f280d792`.

USD 3 was reserved before dispatch. Retained priced plus offline retained-rate
estimate subtotal: **USD 0.11196123**. This is **not a complete billed total**:
11 Voyage reranking receipts lack pricing, and 22 local Qdrant search receipts
lack infrastructure cost. Planner/checker estimates use the retained GPT-5-mini
rate, with unknown caching treated as uncached; accepted planner counters already
include checker tokens and are not counted twice. `accounting.json` preserves
receipts, estimates and every unavailable stage. No further budget was consumed
after the stop.

| Criterion | Result |
|---|---|
| No invented executable query/coverage wording | Structural tests pass; eight retained plans recompile from grounded user spans and match the checked execution view. Standard trace does not persist outbound query/coverage strings verbatim; this is deterministic-source/replay proof, not an invented wire log. |
| Recall@5 no more than 0.03 below baseline on each side | **Not established:** only 8/30 checkpoint inputs ran. Partial matched intersections unchanged. |
| No new interpretation-failure/clarification on clear questions | No final such outcomes in eight records, but one false internal omission rejection caused a retry. |
| No new AI/Laravel failures beyond 38/2 | **Met**, exact-name sets and JUnit retained. |
| Standing zero retry-triggering false flags | **Failed**, b01-10/v1. Stopped as required. |

**Recommendation: HOLD Block 1; do not merge to main.** The extractive execution
repair has provider-free proof and some live positive evidence, but the standing
interpretation-check criterion failed and the retrieval gate remains incomplete.
The b01-09 quotation rejection stays Block 2. No new fix, rerun or next-block work
is authorised by this report. David decides the disposition.
