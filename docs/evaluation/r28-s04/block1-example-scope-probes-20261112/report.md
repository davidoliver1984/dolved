# Block 1 decisive example-scope probes — 2026-11-12

**Result: 0/2 caught. HOLD Block 1.** Both retained plans were accepted by the
current production interpretation check. No citation was returned, so neither
produced a verified rejection. This fails David's criterion fixed before dispatch:
each must return scope_added or meaning_changed with a verified citation, regardless
of whether the added examples helped search. No retries, tuning or further calls.

## Execution and unchanged inputs

- Runtime/origin-main: `82b3dd653689b9b95a01d31ad836e579893f3d31`.
- Runtime-source aggregate before/after:
  `227e3f7068f74daf71ede100214e940cce2de1c8a421bbced3d8f60f7dbec751`.
- Production checker: `interpretation-check-v2`, introduced by `737776d`;
  file SHA-256 `ba4d8069e42d52c35fe03037e8742982b30d150ff56df790ab11bbe495634348`.
- Existing `scripts/evaluation/block1_interpretation_probe.py` transport, same as
  omission probes: model/schema/grounding validation, direct production
  `review_interpretation`, production configuration, no planner invocation.
  Dependency construction does not call the planner. No catalogue or expectations
  enter either request. One call each, original user-1 turns.
- Retained plans come from closing-tail `01-v4.case.corrected-b01-01-v1.json` and
  `02-v4.case.corrected-b03-05-v1.json`. Inputs retain source paths/checksums.
  The trace omits the schema envelope; restore only `schema_version` for model
  validation. Normal schema serialization supplies empty default `bindings`.
  Every need/aspect/span and original user wording is unchanged.
- `authorization.json` records the direct scoped authority, frozen inputs and
  fixed acceptance criterion. `consumed.json` is created exclusively before calls;
  the runner cannot dispatch the same package again. Dispatch records and exact
  worker are retained. No application changes or full-conversation execution.

## Every result

| Plan | Checker verdict / effective verdict | Citation | Verification | Criterion |
|---|---|---|---|---|
| b01-01/v1, wellbeing/induction | accepted / accepted | null | not_applicable; reason null | **Fail** |
| b03-05/v1, resignation/exit | accepted / accepted | null | not_applicable; reason null | **Fail** |

The first reviewed n2 retains “for example induction checklist items, buddy scheme
checks, demonstrations, signposting, confirmations or recorded competencies”.
The second retains “for example returning equipment, completing handover notes,
final payroll/pension steps, IT access removal, and any exit interview”.
These phrases do not occur in the respective user turns. This was a semantic
acceptance, **not** a citation that failed verification and was consequently ignored.
The earlier live reference-binding checkpoint's verified contact/escalation catch
remains a successful individual catch, not proof this mechanism is resolved.
One sample per plan measures these observations, not a population miss rate.

## Usage and cap

| Plan | Input tokens | Output tokens | Latency ms | Estimated USD |
|---|---:|---:|---:|---:|
| b01-01/v1 | 1,159 | 87 | 3,822.657 | 0.00046375 |
| b03-05/v1 | 1,213 | 87 | 2,588.444 | 0.00047725 |
| Total | 2,372 | 174 | 6,411.101 | **0.00094100** |

Provider receipts do not supply billed cost (`cost_basis: unavailable`); estimates
use the retained GPT-5-mini USD 0.25/M input and USD 2/M output rates. No price
lookup/provider call was made. The frozen conservative reservation was USD 0.066384
(100k input tokens plus the full 4,096 completion ceiling per request), below
USD 0.50. No retrieval/reranker/generation stages exist in this check-only run.
This is a token-based estimate, not an invoice.

## One general repair proposal — approval required, not implemented

**Make executable retrieval wording extractive and application-assembled rather
than allowing a model-written aspect to introduce concepts.** A versioned need
would declare its requested clause and any necessary context as exact source
spans with turn IDs. The application validates those spans and compiles the
retrieval request from that source text plus existing typed, grounded temporal,
claim and applicability fields. A model-authored paraphrase can remain diagnostic,
but cannot supply search/reranking/family-review constraints or requested-aspect
coverage labels. This makes invented examples unable to influence execution even
when a semantic checker incorrectly accepts them; no keyword list or example-
specific prompt fix is involved.

Current responsible boundaries are `EvidenceNeedsPlan::retrievalPlan()` (query
starts from aspect), `RetrieveWorkspaceEvidence::handleNeeds()` (passes aspect to
execute), topical discovery/reranking/reviewer consumers of that query, and the
producer/consumer need contract. Requested-aspect labels and generation must use
the same grounded request, preserving existing question-span primacy and all
evidence-ownership/authority/citation rules. Do not merely remove parentheses or
keep an untrusted paraphrase in another executable field.

Trade-off: faithful standalone paraphrases currently help disambiguate anaphora
and retrieval vocabulary. An extractive request may reduce recall or introduce
irrelevant context if context spans are too broad. Exact grounding does **not**
prove semantic coverage or correct context selection; keep the existing bounded
interpretation check and independent coverage assessment. It adds no model call
but changes a shared query/coverage contract and needs scoped verification, not
a claim of risk-free string replacement. Do not infer authority from context or
change permissions, ranking algorithms, thresholds, lane counts or retry ceilings.

Before implementation approval: agree how explicit context spans compose the
query and migrate old persisted needs without rewriting historical runs. Tests
should prove that arbitrary extra examples cannot reach any execution consumer,
while user-supplied examples, cross-turn pronouns, independent clauses, comparisons,
relative dates and claim-plus-site requests retain their grounded meaning. Compare
known-good retrieval and unfamiliar wording under a separately authorised bounded
checkpoint. This is one proposed mechanism, not authority to build it now.

## Evidence integrity

Raw receipts, original inputs, one-use consumption, worker and before/after freezes
are included in `checksums.sha256`. Existing source records remain untouched.
Only this evidence package and the living repair plan are committed; unrelated
ADR/runner edits are preserved. No further provider calls or implementation.
