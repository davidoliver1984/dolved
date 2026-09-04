# Dolved V4 corrected independent authoring report

- Authoring run: `AUTHOR-V4-20260903-H4K9T2MC`
- Population: `dolved-v4-independent-corrected-74case-v3-r2`
- Status: pending final delta-focused independent audit and R28-S01 freeze; not approved for provider execution.
- Contract: `dolved-v4-independent-authoring-output-v3`
- Schema: `r28-independent-authoring-output-v3`
- Coverage: `dolved-v4-independent-authoring-coverage-v2`
- Aggregate: `58e4d4b3ebbde74118bbbd287240ef861fea9035aa291642e2be2a97c6ae1624`

## Audit lineage

The first final candidate `AUTHOR-V4-20260903-N8R2W6HX` failed whole-population audit on b04-02 and remains immutable. The complaint-office case was withdrawn, replaced evidence-first with the safeguarding incident-log prefix case, and the replacement passed independent audit. All 74 cases in the corrected six-batch ledger are now accepted.

## Deterministic serialization

Cases retain ledger batch and within-batch order. The final output copies case identity, scope, both variants, schema context, outcome, evidence identity/path/hash/quotation, rationale and slices byte-for-value. Only workflow-only batch/status/review fields, semantic-intent annotations, workspace bookkeeping, evidence headings and verification notes are omitted because the closed v3 schema prohibits them. No retained value is rewritten.

## Totals

- Cases: 74
- Utterances: 148
- Scopes: primary 62, foreign tenant 6, security test 6
- Outcomes: EVIDENCE_FOUND 43, INSUFFICIENT_EVIDENCE 5, CLARIFICATION_REQUIRED 5, NO_ELIGIBLE_EVIDENCE 6, NO_RETRIEVAL_CANDIDATES 5, COMPARISON_SCOPE_INCOMPLETE 5, TEMPORAL_SCOPE_UNRESOLVED 5

## Coverage

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
- `format.pdf`: 23
- `format.docx`: 23
- `format.txt`: 25
- `safety.cross_tenant`: 6
- `safety.prompt_injection`: 6

## Declaration

I independently authored this population through six source-first batches and separate semantic audits. The first v3 final candidate, AUTHOR-V4-20260903-N8R2W6HX, failed whole-population audit solely on b04-02 and remains immutable. I replaced b04-02 evidence-first, verified the foreign safeguarding-prefix fact and its absence from the complete eligible primary catalogue, and the replacement passed independent audit with verdict R28_V4_CASE_B04_02_ACCEPTED. All 74 current cases are independently accepted. This candidate uses schema r28-independent-authoring-output-v3, contract dolved-v4-independent-authoring-output-v3, aggregate 58e4d4b3ebbde74118bbbd287240ef861fea9035aa291642e2be2a97c6ae1624, coverage contract dolved-v4-independent-authoring-coverage-v2, and restricted view dolved-care-v4-question-author-view-v1 at SHA-256 8f73c9c12a843be9641698f39db60243a977e6c1c700a3f89f72dbbb890e44b9. No Dolved or provider output, calibration data, held-out data or prior result influenced the correction, and no expectation was adjusted to system behaviour. No contamination was detected and the repository was not modified. The candidate remains pending final delta-focused audit and R28-S01 freeze and is not approved for provider execution.
