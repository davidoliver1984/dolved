# Remaining package — provider-free work in progress, 15 November 2026

Block 1 HOLD. Starting hold-branch commit `0722c6ab39b9b7edf4e0be0397d45ad96166f8e7`;
runtime `d5e0b5caf13ec428919d0020fb1bd701620b467c16c5310b13d7eabe04758279`.
Independent implementation: `94c6545c0634ef0e8dc6c78646dffdd60128e35f`.
No provider calls, sealed/reviewer access, deployment or main changes.

## Investment boundary

David activated the cap at receipt, **2026-11-15 08:50:42 Europe/London**.
35 focused hours, hard end 2026-11-20 18:00 Europe/London, whichever first.
Diagnosis, implementation, tests, review and reporting are charged together.
Earlier unmeasured work is unknown/pre-activation, not zero and not charged.
Measured continuous session through 09:22:45 is 1,923 seconds (32m03s), including
diagnosis, implementation, testing, design assessment, review and reporting—not
merely command duration. Remaining at that checkpoint: 34h27m57s. Allocation-note
work is included, not charged twice; its separate sub-duration was not measured
and is unknown, not zero. Publication/reporting after that checkpoint remains
additional chargeable work; see final handoff for the later observation.

## Retained evidence diagnosis

All item references below are `../run/cases/NN.json`; prior records are unchanged.

| Item | Earliest evidenced contribution and downstream effect |
|---|---|
| 17 | Accepted compare need reached topical family discovery; retained original/effective reviewer decision is `no_match`, with no validation rejection. No final generation. This is separate from the first attempt's corrected leading-period representation. A missing semantic reason must not be invented. |
| 19 | Historical selector `February 2026 update` was sent to `HistoricalReferenceResolver::resolve`, which extracts 2026 and calls `TemporalPeriodResolver` on the whole year. Eligibility reports `ambiguous_authority_window_for_period`; no search/generation. Preserving month precision alone would still not establish whether a transition pair or historical-vs-current pair was intended. Existing transition routing requires the explicit transition authority type. |
| 24 | Correct fridge-failure passage was supplied, together with passages about a temperature warning and a power outage. Generation substituted other contacts into the mandatory conjunction. The existing entailment review ran once and marked both answer parts supported. This is a confirmed wrong semantic judgement, not an omitted check. The part-wide binary receipt does not reveal claim-level reasoning. |
| 26 | n1 combined control name and classifier. Its final per-need candidate rank 5, `c172d391-404f-524f-b645-00deeb087486`, explicitly says “'Laptop encryption' renamed to 'Device encryption'”. Global allocation discarded it. Cyber-route chunk `9aa4ac30-490d-5237-93ba-271c1f07f55a` was supplied to n1/n2, but absent from n3's reranked list and not owned by n3. Generation substituted MDM and care on-call guidance. These are allocation, retrieval/association and fidelity contributions, not one missing-corpus fact. |
| 28 | n3's per-need final rank 3, `afd325b1-df26-51f5-bbc2-fe2e69b23c1c`, names both the site Safeguarding Champion and organisation-level Safeguarding Lead. Global allocation retained two multi-agency passages for n3 instead. The required role passage was retrieved but not supplied to generation. Review-only Champion Guidance is separate evidence and must not be described as supplied. |
| 29 | The retained failure was part-keyed temporal-use coverage for a paragraph serving two needs. The already-committed per-(part, need) correction is preserved; provider-free, not live proof. |
| G3 | The retained after/day proposals still fail the grounding invariant under v12. Improved fixed feedback is live-unverified; no earlier missing output is reconstructed. |

For 24, supplied evidence handles are n1 ev-01/04/05 (chunks 709/866/300),
n2 ev-02/03 (709/468). Current breakdown text requires **both** on-call manager
and supplying pharmacy. Chunk 866's senior-carer route concerns a temperature
warning, not permission to substitute that contact for the breakdown obligation.
The exact reviewer wire body is not retained; `review_input()` deterministically
constructs it from these cited passages, the question and requested aspects.
The two retained `supported` verdicts establish acceptance, not its reasoning.

## Independent implementation and its limits

The existing review call now has a source-anchored internal receipt contract.
Each supported claim names its need, exact answer/source quotations and evidence
handle, and separately judges same-situation support and preservation of the
requirement. Deterministic validation checks quotations, association ownership,
text coverage and explicit rejection judgements. It does **not** prove semantic
equivalence; a well-formed but incorrect reviewer judgement can still pass, which
is explicitly covered by a limitation test. No extra call, retry or provider
configuration is introduced. Historical answer receipts remain unchanged.

Producer and consumer pins advance together: needs prompt v13/adapter v5,
temporal prompt/adapter v4, execution v21. Public answer/temporal receipt shapes
remain unchanged. The Python-produced controlled adapter fixture is compared by
Python and consumed by PHP; historical saved answers are not rewritten. The
1024-token review output ceiling is unchanged; whether the richer receipt fits
reliably is unmeasured. No regression is declared repaired from controlled reviews.

`HistoricalReferenceResolver` now preserves the unique inventoried calendar
selector's day/month precision instead of reducing it to a year. Conflicting
references remain unresolved. Existing version/relative-selector handling,
timeline, eligibility and applicability checks remain. This does **not** decide
that a named historical selector means a transition: item19's planner-routing
contribution remains unresolved. The existing planner already instructs calendar
changes to use transition authority; another example-specific prompt is not added.

The evidence-allocation dependency is separate: the current allocator reserves
one unit per need, then fills by rank, counting duplicate passages separately.
Safe ownership reuse alone cannot ensure the encryption/role passages survive.
A semantic coverage-selection call would be an additional production-call
decision. David authorised assessment only, now in `allocation-design.md`;
allocation and cross-need evidence ownership remain unchanged pending approval.

## Verification

All application checks used one-off containers in **dolved-r28-s03**, not the
development stack. PHP used PHPUnit's forced SQLite `:memory:` connection. AI
provider keys were blank; all provider boundaries controlled. Qdrant tests use
unique synthetic collections; the storage integration uses the isolated versioned
test bucket and random synthetic workspace keys. No production workspace was queried
or mutated. No application service was deployed.

| Check | Result |
|---|---|
| Focused support, entailment, transport, cross-service, temporal associations | 56 passed, `focused.xml` |
| Complete AI | 1,485: 1,443 passed, 38 failed, 4 skipped, `ai-complete.xml` |
| Complete Laravel | 933: 2 failures, 9 skipped; 5,674 assertions, `api-complete.xml` |
| Exact retained failure identities | Zero new and zero absent in both; `suite-comparison.json` |
| Intermittent inode-substitution test | Reproduced; not claimed fixed |
| Scoped Ruff/format, Mypy, PHP Pint | Passed |
| Retained item24 text replay | Old supported receipts accept; controlled new `different/changed` rejects with `entailment_uncertain`; controlled wrong `established` still accepts |

The replay uses actual request/text/citations with explicitly synthetic aspect
links and reviewer receipts. It is not original-output reconstruction or a live
judgement. New unfamiliar fixtures cover situation substitution, conjunction
preservation, quote/ownership/coverage errors, legitimate answers and independently
owned shared temporal associations. Existing assertions remain intact; transport
fakes now emit the new internal reviewer shape. Missing support still follows the
existing exhausted-entailment outcome and unchanged attempt ceilings.

Initial full-suite setup errors are retained, not hidden: Qdrant was stopped
(`ai-full.xml`); PHP initially inherited isolated localhost/Sanctum settings and
the wrong test bucket (`api-full.xml`, `api-full-final.xml`). After starting only
isolated dependencies and selecting their existing versioned test bucket, the
same tests produced the baseline sets. No code/assertion was changed to conceal
those setup errors. Intermediate complete runs are also retained.

Runtime freeze (963 tracked-or-explicitly-scoped runtime/contract files):
`07038b22886571b1058b8bb70a713b1c691a60b2a1b6523ada3c2c68d8279330`.
`verify.py` reproduces the failure-set comparison and runtime manifest. The
unrelated ADR and governed-runner edits are excluded; Journey is local only.

## Completion matrix — not ready for the decision run

| Regression | Position | Remaining uncertainty/obligation |
|---|---|---|
| 19 | Partially addressed | Calendar precision corrected; transition-vs-historical selection unresolved. A different honest no-answer would not satisfy the frozen answer criterion. |
| 24 | Partially addressed, model-unverified | Source-anchored situation/requirement receipt enforced; real semantic accuracy and output-budget sufficiency unmeasured. |
| 26 | Unresolved selection/association; fidelity partial | Encryption allocation loss and absent n3 cyber retrieval distinguished. Allocation design awaits approval; no source pooling or new call implemented. |
| 28 | Unresolved allocation; fidelity partial | Eligible role evidence was discarded. Design awaits approval; not relabelled a corpus gap. |
| 29 | Addressed provider-free, live-unverified | Existing per-association guards preserved and rechecked; no live demonstration claimed. |
| G3 | Unverified | Existing v12 feedback/canonicalisation preserved; no further temporal interpretation change or replay claimed. |

Item17's separate genuine reviewer `no_match` remains unresolved, with no invented
reason. Four-mechanism summary: shared-paragraph ownership preserved/provider-free;
temporal interpretation partial; evidence selection/association design pending;
answer fidelity partial/model-dependent. The full package is **not ready to freeze
for the decision run**. This is an approval boundary for allocation, not cap
exhaustion or a claim of Block 1 closure. The 24 fresh/4 control/6 regression sealed
set is untouched. No new live run is authorised by this report.
