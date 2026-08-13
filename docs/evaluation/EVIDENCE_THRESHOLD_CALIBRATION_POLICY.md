# Evidence threshold calibration policy

This repository-owned policy freezes how the experimental evidence threshold is
selected before the calibration split is opened. It implements ADR-0019,
ADR-0020 and ADR-0021; it is not an ADR and does not alter retrieval behaviour.
The machine-readable policy is
`tests/evaluation/policies/evidence-threshold-calibration/v1/policy.json`.

## Selection rule

The factual control is exactly `0.337890625`. A candidate is ineligible if it
has any absolute security/governance failure, or regresses from the control in
controlled-rejection correctness, benchmark precision, or recall for any
predeclared load-bearing slice. Among eligible candidates that strictly improve
case-first expected-EvidenceUnit recall, select by:

1. highest case-first expected-EvidenceUnit recall;
2. higher benchmark precision;
3. fewer variants losing all evidence;
4. higher MRR;
5. higher nDCG;
6. higher threshold.

If none improves recall while satisfying every constraint, retain the control.
Maximum F1 is deliberately not part of this decision rule.

## Load-bearing slices

The list was selected from accepted architecture and existing repository policy,
before calibration access:

| Slice | Why it is protected |
| --- | --- |
| `CURRENT` | Existing quality policy protects it and it is the normal ADR-0017 authority mode. |
| `COMPARE` | ADR-0018/0021 require independent PRIMARY and COMPARISON evidence; one side cannot hide the other. |
| `VALID_AT_DATE` | ADR-0017 temporal authority makes date-scoped evidence a distinct correctness boundary. |
| `historical` | ADR-0022 historical-reference resolution must retain the intended prior authority. |
| `temporal-authority` | Prevents aggregate gains masking authority-window regressions. |
| `applicability` | Server-side applicability is an ADR-0017 eligibility boundary. |
| `location-alias` | Resolved location affects applicability scope and must not regress silently. |
| `multi-evidence` | Completeness is defined over every distinct required EvidenceUnit. |
| `multi-document` | Required evidence distributed across documents must remain recoverable. |
| `adversarial` | Existing policy protects adversarial behaviour from aggregate masking. |
| `zero-evidence` | Controlled abstention must not be traded away for recall. |

Cross-workspace, unauthorised, temporally ineligible and applicability-ineligible
evidence are stronger than slice constraints: any occurrence is an absolute
failure. Lost cases and metric non-reproducibility are also absolute failures.

## Case-first expected-EvidenceUnit recall

Coverage is calculated from distinct expected EvidenceUnit IDs; repeated chunks
covering one unit receive one credit. For each variant and side, recall is
covered distinct units divided by expected distinct units. PRIMARY and
COMPARISON are calculated independently and averaged for that variant. Variant
recalls are averaged within their semantic case, then case recalls are averaged,
so every semantic case contributes equal weight. Multi-evidence cases therefore
require every declared unit for full recall without gaining extra corpus weight.
Cases with no expected EvidenceUnits are excluded from this recall denominator
and evaluated through controlled-rejection correctness instead.

## Controlled rejection

Correctness is exact-outcome correctness, never a generic rejected/not-rejected
flag. Pre-retrieval `NO_ELIGIBLE_EVIDENCE`, `CLARIFICATION_REQUIRED`,
`TEMPORAL_SCOPE_UNRESOLVED`, and `NO_RETRIEVAL_CANDIDATES` remain their distinct
outcomes. Threshold replay produces `INSUFFICIENT_EVIDENCE` only when candidates
were reranked but none qualifies. For COMPARE, it produces
`COMPARISON_SCOPE_INCOMPLETE` when either separately evaluated side has no
qualified final evidence. A benchmark no-evidence control is correct only when
its exact declared outcome is reproduced.

## Precision terminology

`benchmark precision` means deterministic precision against annotated benchmark
EvidenceUnits, using the repository's versioned duplicate-credit and final-K
rules. An accepted candidate that covers no annotated EvidenceUnit is reported
separately as `uncredited / unannotated`. It is not called irrelevant or a false
positive because benchmark relevance is intentionally not exhaustive.
