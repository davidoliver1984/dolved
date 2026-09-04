# Dolved V4 comparison compatibility V2 candidate report

- Authoring run: `AUTHOR-V4-20260904-C9W2P6LX`
- Population: `dolved-v4-independent-comparison-compat-v2`
- Status: pending final delta audit; not approved for provider execution
- Acceptance verdict: `R28_V4_COMPARISON_COMPATIBILITY_CORRECTION_ACCEPTED`
- Acceptance date: `2026-09-04`
- Schema: `r28-independent-authoring-output-v3`
- Output contract: `dolved-v4-independent-authoring-output-v3`
- Coverage contract: `r28-authoring-coverage-contract-v2`

## Lineage

This candidate was serialized from accepted checkpoint `COMPAT-V2-20260904-R7K3M8QX`, whose `checkpoint.sha256` digest is `40b16d20fab1734ac9cd04e65b66cb63f8423cf864bc8f17be4537c79771d4e1`. Its parent is immutable frozen population `dolved-care-v4-evaluation-population-v1` with frozen digest `6254188d7fc7a698641750a81d436eac97eb425244704b64b1daac0c92803161`. V1 was not modified, copied over or replaced.

## Acceptance transition

Exactly these 22 correction cases transitioned from `independent_review_required` to `batch_accepted`; the transition changed no semantic population field:

- `v4.case.corrected-b01-02`
- `v4.case.corrected-b01-04`
- `v4.case.corrected-b01-07`
- `v4.case.corrected-b01-09`
- `v4.case.corrected-b01-10`
- `v4.case.corrected-b02-07`
- `v4.case.corrected-b02-09`
- `v4.case.corrected-b03-03`
- `v4.case.corrected-b03-10`
- `v4.case.corrected-b04-03`
- `v4.case.corrected-b04-07`
- `v4.case.corrected-b04-09`
- `v4.case.corrected-b05-01`
- `v4.case.corrected-b05-05`
- `v4.case.corrected-b05-07`
- `v4.case.corrected-b05-09`
- `v4.case.corrected-b06-01`
- `v4.case.corrected-b06-02`
- `v4.case.corrected-b06-03`
- `v4.case.corrected-b06-04`
- `v4.case.corrected-b06-05`
- `v4.case.corrected-b06-06`

All 74 candidate cases are accepted.

## Deterministic serialization

The cases are copied byte-for-value from `case-ledger.json.case_records[].case` in ledger order. Workflow status and correction metadata are omitted because they are outside the closed V3 population schema. The canonical corrected case-array SHA-256 remains `442a92d6003738805b38d6a959a4ce44675344130d64ba307e5ddeeb265c9d42`.

## Totals

- Cases: 74
- Utterances: 148, all globally distinct after normative normalization
- Scopes: primary 62, foreign tenant 6, security test 6
- Outcomes: EVIDENCE_FOUND 43, INSUFFICIENT_EVIDENCE 5, CLARIFICATION_REQUIRED 5, NO_ELIGIBLE_EVIDENCE 6, NO_RETRIEVAL_CANDIDATES 5, COMPARISON_SCOPE_INCOMPLETE 5, TEMPORAL_SCOPE_UNRESOLVED 5
- Evidence objects: 127; all paths, hashes and exact normalized visible-text quotations verified
- Answerable comparison cases: 22; PRIMARY is current and COMPARISON is historical in every EvidenceUnit; scheduled-future comparison sides: 0

## Coverage

All 39 closed slices pass:

- `scope.primary`: 62
- `scope.foreign_tenant`: 6
- `scope.security_test`: 6
- `wording.ordinary_employee`: 39
- `wording.concise_vague`: 12
- `wording.typo_alias`: 8
- `wording.colloquial`: 8
- `wording.multi_part`: 22
- `temporal.current`: 26
- `temporal.historical`: 8
- `temporal.valid_at_date`: 8
- `temporal.comparison`: 27
- `change.contact`: 5
- `change.number`: 5
- `change.date`: 5
- `change.responsibility`: 5
- `change.escalation`: 5
- `change.addition_removal`: 7
- `change.rename`: 5
- `change.reorder`: 5
- `applicability.global`: 36
- `applicability.local`: 10
- `applicability.inherited`: 8
- `outcome.EVIDENCE_FOUND`: 43
- `outcome.INSUFFICIENT_EVIDENCE`: 5
- `outcome.CLARIFICATION_REQUIRED`: 5
- `outcome.NO_ELIGIBLE_EVIDENCE`: 6
- `outcome.NO_RETRIEVAL_CANDIDATES`: 5
- `outcome.COMPARISON_SCOPE_INCOMPLETE`: 5
- `outcome.TEMPORAL_SCOPE_UNRESOLVED`: 5
- `competition.near_duplicate`: 24
- `competition.competing_document`: 9
- `structure.long_document`: 8
- `structure.boundary_spanning_evidence`: 8
- `format.pdf`: 24
- `format.docx`: 22
- `format.txt`: 25
- `safety.cross_tenant`: 6
- `safety.prompt_injection`: 6

## Routing and ceilings

- Retrieval: 106 utterances
- Reranking: up to 96 utterances / 140 HTTP requests
- Generation: 86 utterances
- Judging: 86 utterances
- Deterministic terminations: 62
- Base provider requests: 314
- Maximum attempts: 628
- Input-token ceiling: 7,416,320
- Output-token ceiling: 1,056,768
- Hard cap: USD 30

All routing values are unchanged from V1; no provider was called.

## Declaration

I independently authored the source population and serialized this immutable V2 candidate from the accepted comparison-compatibility correction checkpoint COMPAT-V2-20260904-R7K3M8QX at SHA-256 40b16d20fab1734ac9cd04e65b66cb63f8423cf864bc8f17be4537c79771d4e1. The independent audit verdict R28_V4_COMPARISON_COMPATIBILITY_CORRECTION_ACCEPTED was recorded on 2026-09-04. Exactly 22 corrected comparison cases transitioned from independent_review_required to batch_accepted without any semantic-field change; all 74 cases are accepted. This candidate descends from frozen identity dolved-care-v4-evaluation-population-v1 at frozen digest 6254188d7fc7a698641750a81d436eac97eb425244704b64b1daac0c92803161; V1 remains immutable and was not replaced. The candidate uses schema r28-independent-authoring-output-v3, output contract dolved-v4-independent-authoring-output-v3, coverage contract r28-authoring-coverage-contract-v2 and restricted view SHA-256 8f73c9c12a843be9641698f39db60243a977e6c1c700a3f89f72dbbb890e44b9. No Dolved or provider output, calibration data, held-out material or scored result influenced serialization. No provider, network service or AWS resource was accessed, no contamination was detected, and the repository was not modified. The candidate remains pending final delta audit and is not approved for provider execution.
