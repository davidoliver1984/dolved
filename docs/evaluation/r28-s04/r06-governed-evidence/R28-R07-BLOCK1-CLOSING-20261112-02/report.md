# Block 1 grounded-span correction checkpoint — HOLD

Execution: `83fdf18a4751526a5584f4c10d1a41169112276c` at origin/main.
Fresh one-use authority: `R28-R07-AUTH-BLOCK1-CLOSING-20261112-02`.
Four probes ran once; production input 1 ran once. **Stopped at case 1 review gate;
inputs 2–14 not dispatched.** Runner exit 1 is the deliberate gate stop, not an
application operational failure. No further repair/rerun or Block 2 work.

## Diagnosis and correction

All four diagnosis points confirmed, with one qualification: generation previously
received the full question, but not each need's grounded span, so it was not literally
shown only the paraphrase. n3's rank-1 selected passage already contained both site
Champion and organisation Lead. The aspect added an organisation-level qualifier;
the checker accepted it. See `../../block1-question-span-20261112/diagnosis.md`.

Assembly now forwards question_span unchanged. Generation v11 defines user wording
as the request and aspect as retrieval guidance only; existing entailment input
uses the same wording. Mixed scope/operational composition uses operational user
quotes instead of aspect text. Compatible additive v2 field; no output-schema,
retrieval, ranking, threshold, validator, attempt-ceiling or checker-prompt change.
Block 1 owns this work across the Block 3 generation-contract boundary.

## Verification

Full raw JUnit in `../../block1-question-span-20261112/verification/`:
AI 1,309→1,312 tests, **38 unchanged failures**, 4 skipped; Laravel 854→857,
**2 unchanged failures**, 9 skipped. Zero new/resolved failing names; three new
passing tests per service. Exact lists and reproducible comparison retained.
Focused Python 68 pass; PHP foundation 80 pass/363 assertions; harness 17 pass.
Scoped Ruff/Pint pass. Targeted mypy has three existing aspect_links annotation
errors (not silently claimed clean). Initial fixture/assertion failures retained.

## Live result and independent review

The development answer now distinguishes Lead from site Champion, unlike the prior
answer. It also gives Finance record support and necessary/proportionate external
sharing. However, n2's aspect changes “what part does Finance keep” into a portion
of recovered/discrepant money. The check accepts it; the answer additionally talks
about funds, appointeeship retention and executor/death handling. That is accepted
meaning drift/additional scope under the frozen criterion. The answer also does
not explicitly state the renamed-from title. This is **not a complete acceptance
pass** despite the improved role distinction. Full literal answer is in raw case
and case-inventory.json. See clause-coverage-review.md for part-by-part reasoning.

No claim that the drift deterministically caused retrieval or that the improved
role wording was solely caused by the repair: the planner also sampled differently.
The grounded-span route executed with v11; raw generation_input retains only IDs,
not full request payload. Exact span transmission is established by the unchanged
runtime path and provider-free assembly/actual-adapter tests, not a falsely claimed
full live request capture.

| Input | Route / evidence / answer | Mechanism exercised |
|---|---|---|
| Four omission probes | Real checker, each clause_omitted | 4/4 caught; no production retries |
| 1 b06-06/v1, development | Three current retrieve needs, topic bindings, generic applicability. Five owned primary evidence slots (1/2/2), four distinct chunks in two documents. Completed/answered, unsupported_aspects empty. Lead escalation; Finance records/audit plus added funds-retention prose; site Champion support; external sharing. | Grounded-span generation and checker invoked; interpretation consistency fails on n2. Both roles named, explicit renaming not explained. |
| 2 b01-01/v1 | Not dispatched; no evidence/answer | Unexercised |
| 3 b03-05/v1 | Not dispatched; no evidence/answer | Unexercised |
| 4 b05-01/v1 | Not dispatched; no evidence/answer | Unexercised |
| 5 b01-04/v1 | Not dispatched; no evidence/answer | Unexercised |
| 6 b01-06/v1 | Not dispatched; original injection-workspace scope retained | Unexercised |
| 7 unfamiliar three-part | Not dispatched; no evidence/answer | Unexercised |
| 8 unfamiliar relative-date | Not dispatched; no evidence/answer | Unexercised |
| 9 unfamiliar after-period | Not dispatched; no evidence/answer | Unexercised |
| 10 unfamiliar mixed date/event | Not dispatched; no evidence/answer | Unexercised |
| 11 unfamiliar added-scope temptation | Not dispatched; no evidence/answer | Unexercised |
| 12 budget-disclosure one | Not dispatched; no evidence/answer | Unexercised |
| 13 budget-disclosure two | Not dispatched; no evidence/answer | Unexercised |
| 14 working control | Not dispatched; no evidence/answer | Unexercised |

The one production record has needs_pipeline_exercised true, authenticated primary
membership, full production trace and persisted answer. Final identities are in
case-inventory.json. Allocation cuts 4/0/3, unsupported_aspects empty: **budget-cut
disclosure not exercised**. One accepted generation attempt plus existing entailment
call; aggregate request_count=2 is not two generation attempts.

## Completion criteria and ownership

| Plan criterion | Status |
|---|---|
| “supported shapes execute with all needs represented and sides intact” | **Not met for meaning:** literal coverage retained, n2 drift accepted. No comparison closing input exercised. |
| “overflow is honest” | Retained earlier live proof, no new overflow test claimed. |
| “no new permission, authority, ownership or material correctness failure” | No permission/authority/ownership failure observed; interpretation target fails. Full checkpoint not completed. |
| “Budget-limited support must not be described as absent source evidence.” | Provider-free proof retained; live gap branch still unexercised. |
| “Material allocation/design/safety failures hold this block” | Hold applied, no tuning. |

Question-span propagation implemented and provider-free verified; interpretation
consistency and closing confirmation not complete. Relative calendars/after-period
and budget-disclosure live checks remain Block 1. The n2 Finance reading and checker
miss remain **Block 1**; nothing moved later. Existing quote_not_in_candidate is
Block 2, unsupported induction conclusion Block 3, pricing completeness Block 4.
**Recommendation: keep Block 1 on HOLD for David's decision.** This was the one
authorised correction cycle; no new implementation or dispatch follows.

## Checker and cost

Four probes: 570/87, 585/87, 570/87, 585/87 input/output tokens, latencies
2,792.74 / 1,829.85 / 2,500.75 / 1,579.67 ms, all clause_omitted.
Production checker: **980 input / 83 output, 2,014.15 ms**, accepted. No false
positive observed in one production invocation, not a full clear-question result;
the n2 miss is explicit. No second-attempt plan, so correction success rate undefined.

14 physical provider requests including probes. Known priced receipts USD
**0.01532293**; with retained-repository-rate OpenAI estimates, **0.02370893**.
This is not a complete invoice: three Voyage reranking receipts and Qdrant
infrastructure pricing remain unavailable. The USD 5 operating envelope was not
approached on observable estimates; no fabricated complete cost. Checker tokens
are included in parent totals, not added again. Full accounting.json retained.

Runtime before/after: `38471251369aa0b4b317ca5ef93023ae3dcb692fe406514b07404812e98c8cdc`.
Corpus/configuration snapshot before/after:
`f271c1ac843e53aaa16f6f0c70f6f3b642499d69dac2467d17e80c42f280d792`.
Original stop checksums preserved separately; final checksums cover added report,
accounting, authorization, freeze, probes and raw case without changing them.
