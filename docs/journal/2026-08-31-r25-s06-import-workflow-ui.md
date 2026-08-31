# R25-S06 — Import workflow and progress UI

Date: 31 August 2026
Status: Complete

## Outcome

The workspace knowledge library now presents the real ADR-0034 import
workflow. A user can stage files, run preflight, inspect deterministic matches,
review immutable import decisions, promote accepted items and follow truthful
coarse processing state through to `Indexed`. The legacy direct-upload path is
not used as a substitute.

All required visual checkpoints were reviewed in the running application and
explicitly approved by David after the promoted document became visible in the
library and document-detail surface.

## Implementation boundary

- Added browser-facing ImportBatch creation, staging, preflight, matching,
  decision, promotion and status APIs.
- Added the workspace import route and the seven-step import workflow UI.
- Added import navigation and replaced the old direct-upload entry point.
- Preserved ADR-0034's existing promotion state machine and immutable decision
  lineage.
- Kept progress honest after promotion: the UI reports only
  `Promoted/queued`, `Processing` and `Indexed`.
- Recorded promotion-dispatch failures durably and cleared the active lease so
  the existing recovery path can proceed safely.
- Aligned API and conversation-worker extraction-artifact access with the
  existing S3 contract.
- Corrected the ingestion acknowledgement route to call the existing
  controller action; this did not change the HTTP contract.

## Live acceptance evidence

- Import batch: `00b3c353-ee16-44bd-a39d-409fc0eb30ba`
- Import item: `2cb32bcf-4c62-4a49-8576-a7e25773edcc`
- Promotion: `50589300-846d-44d5-81a5-7833dacf00e2`
- Indexed document: `98c0a4ec-c996-4dca-9f01-83837707dcbd`
- Extraction artifact: 1,351 bytes, SHA-256
  `482976030faa3fcad837a4251dbc56867779a968250fcf7e79a0748aace11a67`
- Canonical materialisation: one chunk and one active hybrid point
- Point-manifest SHA-256:
  `117af2848ef612404022e0d27dc71df55fe7381319d5707d957ddc9c78571634`
- Queue and DLQ: empty after completion

The existing ingestion path made one Voyage embedding request for 30 tokens
with no retry. No OpenAI request was made. This was runtime acceptance evidence
for the approved UI flow, not a retrieval-quality experiment.

## Verification

- Complete Laravel suite: 507 passed, 6 skipped, 2,724 assertions.
- Complete web suite: 145 passed across 37 files.
- Focused import workflow, promotion recovery and ingestion acknowledgement
  regressions passed.
- Pint, web lint, TypeScript and `git diff --check` passed.
- The running web service returned HTTP 200 after restart and the imported
  document was visually confirmed.

## Next

R25-S07 must now run the mandatory provider-free and Playwright acceptance
journeys through the real ImportBatch path. Phase 25 cannot close until the
unchanged ADR-0033 small-corpus journey proves genuine searchable readiness and
a grounded answer with valid evidence/citation behaviour. The legacy upload
path remains inadmissible as a substitute.
