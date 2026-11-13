# Temporal assessment: completed experiment, HOLD — 2026-11-13

Block 1 remains HOLD. Main unchanged. Implementation/freeze commit:
`896717a30c26d3f3ea6d48df8684e1d8af95c090`. All 125 planned checker calls completed,
without planner/retrieval/generation calls. The fixed gates failed. **No live
confirmation, tuning, further attempt or main merge followed.**

## Measured result

| Fixed gate | Result | Decision |
|---|---:|---|
| Appropriate citation-verified wrong-plan rejection ≥90% | **56/65 (86.15%)**, required ≥59 | Fail |
| Correct-plan false rejection ≤5% | **10/60 (16.67%)**, allowed ≤3 | Fail |
| No completely missed temporal-error pattern | T12 conflicting selectors **0/5 caught** | Fail |
| Complete fixed execution / valid fixtures | 125/125 dispatched; 25/25 Python/PHP-valid fixtures | Complete, not a quality pass |

There were **4 unavailable checks**, separately retained: T04-correct-5,
T04-wrong-1, T05-correct-1, T05-wrong-3. All retained input/output token counts,
but no valid decision. The existing unavailable receipt does not retain the
precise malformed/incomplete/grounding/consistency exception, so its cause cannot
be reconstructed. These are not secretly counted as accepts/catches or rerun.

| Question / pattern | Correct accepts /5 | False rejections /5 | Correct unavailable /5 | Wrong appropriate verified catches /5 | Other wrong outcomes |
|---|---:|---:|---:|---:|---|
| T01 historical rule date | 5 | 0 | 0 | 5 | — |
| T02 historical rule month | 4 | 1 | 0 | 4 | 1 unverifiable flag, effective accept |
| T03 historical rule date | 5 | 0 | 0 | 5 | — |
| T04 current request, old leaving event | 2 | 2 | 1 | 4 | 1 unavailable |
| T05 current contact, old fall event | 4 | 0 | 1 | 4 | 1 unavailable |
| T06 current chair, old review event | 5 | 0 | 0 | 5 | — |
| T07 current benefit, old joining event | 5 | 0 | 0 | 4 | 1 unverifiable flag, effective accept |
| T08 same date has both roles | 4 | 1 | 0 | 5 | — |
| T09 distinct leaving/request dates | 5 | 0 | 0 | 5 | — |
| T10 historical/current comparison | 2 | 3 | 0 | 5 | — |
| T11 undated event, qualified two-rule alternative | 2 | 3 | 0 | 5 | — |
| T12 conflicting current/past selectors | 5 | 0 | 0 | **0** | 5 accepted wrong plans |
| Retained input16, development only | — | — | — | 5 | No correct-plan counterpart, per amendment |

`semantic-review.json` contains all 125 per-attempt verdicts, exact citations,
verification results, assessments, rationale, record paths/hashes, and aggregated
per-pattern counts. Review was performed by Codex against the frozen plan pairs,
PHP mapping and question/source expectations—not an independent human review or
another provider/model-judge call. A valid citation was not treated as semantic
proof. All 56 counted rejections identify the actual lost or wrongly selected
temporal lane. No frozen expectation was revised after observing outputs.

### False rejections and misses

- **T02-correct-1:** cites `authority.kind=period`; treats December2025 as fact
  context although the reviewed question asks the historical rule/role.
- **T04-correct-3/4:** cite `authority.kind=current` as added scope despite the
  request having “just” arrived; September2025 is the leaving event, not the
  applicable reference-request rule.
- **T08-correct-2:** cites “the contractor arrived 90 minutes after the urgent
  call.” as omitted. It remains in the executable request; historical authority
  and the event date both remain correctly selected. Unlike the genuine lane-loss
  probes, this fact does not require another authority lane.
- **T10-correct-1/4/5:** cite “is that still the rule today?” as omitted. The real
  PHP mapping of compare/date executes current plus dated comparison authority.
- **T11-correct-1/2/3:** cite `authority.kind=previous` as changed meaning. This
  rejects the reviewer's explicitly approved *qualified two-version alternative*,
  not a plan asserting that the undated event definitely used the previous rule.
  The frozen labels/denominator remain unchanged. Even excluding these three
  disputed-by-checker judgments would not rescue the false-rejection gate.
- **T02-wrong-4:** attempted meaning_changed with plan_quote `"current"`
  (including quotation marks); `plan_quote_not_found`, effective accepted.
- **T07-wrong-2:** attempted meaning_changed with plan_quote
  `period (authority.value: "2025-05")`; not a literal field value, effective
  accepted. Both unverifiable flags correspond to real role errors but are not
  actionable under the unchanged citation policy.
- **T12-wrong-1–5:** all accept current-only, explicitly label June2025 as
  fact_context and temporal intent preserved. The historical selector is lost.
  This is a complete tested-pattern miss, irrespective of aggregate performance.

These are **Block1 interpretation-consistency findings**. Receipt unavailability
has an unresolved subtype; no new instrumentation or fix was slipped into this
task. The 5/5 development catch is evidence on that retained plan only, not an
estimated population failure rate or proof that overall reliability improved.

### Accounting and freeze

Reservation stated before dispatch: **USD1.30183875**, including all 125 ×4096
output tokens and conservative input bounds. Recorded usage: **191,090 input /
61,215 output tokens**, **USD0.1702025 at retained rates**; no unaccounted calls.
This is token-based accounting at USD0.25/M input, USD2/M output, not a billing
invoice or fresh tariff verification. No unpriced production stages ran.

Per check: mean 1,528.72 input /489.72 output tokens; mean **5.993s**, median
**5.875s**, range **2.317–13.122s**. For the exact retained input16 plan, v3 uses
**1,537 input tokens**, +437 versus the retained v2 call's 1,100. V3 output mean
524.8 versus old87; mean latency6.429s versus old2.165s. The latter differences
are observational (five new calls versus one earlier call), not controlled causal
overhead estimates. No extra production checker call or planner attempt was added.

Before/after freeze identical: runtime aggregate
`b563abc752e71d44704eaa63d80619ec9662a5b7759e86e986bdc9b1c58a9570`.
Authorization, exclusive consumption marker, dispatch ledger, per-attempt raw
records and `checksums.sha256` are in this directory. Guard tests also verify
runtime/input drift refusal, remote SHA refusal and consumed-authorization refusal
without a second provider call. Original evidence/package checksums unchanged.

**Recommendation: HOLD Block1 and stop for David's review.** The probe's catch,
false-rejection and per-pattern gates all fail. Fresh production confirmation is
not justified by this result and was not dispatched.

Reviewer selection is unchanged (ccb35dd, SHA-256
530c05066fc7f9db6dd6fa45fef831fa9720a3463efe056f280500d6fad7b207).
Amendment 1 is unchanged (8fc2125, SHA-256
54b9d9bf7c5a9ffec5dfd934aef1605ed3a234e74f138a636ef8190a1f5b934f).
T11's immediate-risk exception and July2024 history floor match both ingested
Missing Person versions. T12's new current/June2025 conflict is observable from
its question; Staff Benefits v1/v2 in the frozen ingested snapshot support
5–10%/5–15%, effective April2025/April2026. The prior source-verification package
and its checksums are unchanged. The amendment resolves its recorded stop.

## Implementation and verification

Mandatory temporal assessment inside the existing checker, receipt v3, exact
need coverage/quote grounding/global consistency checks, bounded PHP diagnostic
retention. No authority rewrite, timeline change, extra call or attempt; 4096
completion tokens unchanged. Citation validity is not semantic correctness.
Existing unverifiable-flag handling is preserved.

Focused: 46 Python tests; 7 Laravel tests (144 assertions). Full suites: 38 AI
failures, 1325 passed, 4 skipped; 2 Laravel failures, 865 tests, 9 skipped.
`suite-comparison.json` lists every exact failure identity and JUnit SHA: no new
or resolved failures against the retained migrated baseline. Old test fakes gain
the mandatory response field; no existing assertion changed. Initial AI wrong
mount-depth collection errors and Laravel 128MB process exhaustion are retained
separately, not counted as completed suites. Final AI used `/workspace/apps/ai`;
final PHP used a 512MB test-process limit. Focused Ruff/Pint also checked.

## Frozen mechanism experiment

`probes.json`: 25 plans; `expectations.json`: separate transformations, reasoning,
source expectations and denominators. All plans pass Python schema, grounding and
authorised-catalogue checks (retained development plan keeps its original topic
binding). All 25 pass PHP validation; `php-plan-validation.json` shows actual
execution mapping. No provider call was used to construct these plans.

T01–T03 historical rule dates become fact-only/current in the wrong plans.
T04–T07 wrongly promote old event context into historical rule authority.
T08 retains the event date but wrongly loses that same date's authority role.
T09 consistently swaps the leaving/request roles; leaving predates all versions.
T10 loses the historical execution lane, even though current text might mention
the old number. T11's correct compare plan supports the reviewer's explicitly
qualified two-rule alternative, not a claim that either rule applied to the
undated event. Its wrong plan silently chooses current. T12's correct compare
executes historical and current; its wrong plan silently keeps current. The
retained input16 accepted plan is an additional labelled wrong development probe,
not reconstructed or counted as fresh confirmation.

Five calls per plan: 60 correct, 65 wrong, 125 total. Pass requires at least 59
semantically appropriate citation-verified wrong rejections; at most 3 false
correct-plan rejections; no completely missed temporal pattern; complete valid
execution. Manual assessment must check the cited defect, not merely count a
verified citation. Failure stops without tuning or live confirmation.

Budget cap was explicitly removed by David. `run_probes.py prepare` computes and
records the full 4096-output-token-per-call bound plus conservative UTF8-byte input
and framing bounds at retained rates, before execution. One-use exclusive consumed
record, exact pushed hold SHA, runtime/input hashes and per-call ledger apply.
Only question turns and plan enter the provider; expectations are never sent.
