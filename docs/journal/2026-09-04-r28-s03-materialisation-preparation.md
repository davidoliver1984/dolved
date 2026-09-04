# R28-S03 — Frozen V4 corpus materialisation preparation

## Status

R28-S03 is in progress. Nine immutable attempts have run; each failed closed
and remains recorded below. Attempt `0010` is prepared but has not run.

## Boundary

The active immutable run identity is
`R28-S03-V4-CORPUS-MATERIALISATION-0010`. It binds the frozen checkpoint 19
archive and its four governed scopes to a dedicated `dolved-r28-s03` local
runtime. The primary, foreign-tenant and separate prompt-injection documents
must pass through the authenticated ADR-0034 ImportBatch workflow. The 13
negative fixtures must remain outside ordinary searchable promotion and be
reported through their validation, preflight, matching, interruption and
replacement paths.

The runtime uses the repository's reviewed deterministic dense and sparse E2E
profile. OpenAI and Voyage credentials are explicitly absent, AWS access is
not permitted, and S04's separately approved live Voyage corpus/index
materialisation allowance remains unused. This session establishes real
product-path ingestion and isolation evidence, not live-provider quality.

## Preparation verification

- frozen archive SHA-256:
  `6fa6602935efe8379cc2a7de4ba85af17aa8d8827082ae6de8df959f6e19a06e`;
- deterministic retrieval-profile SHA-256:
  `d8beb2324318a18f0a6de62bcc41e83c097439d177a520f3a7a9a3df1b246c24`;
- provider-free static tests cover the four-scope definition, governed media
  mapping and use of import/promotion/governance APIs rather than the retired
  direct-upload route;
- the isolated runtime uses distinct database, queue, bucket, ports and Docker
  project identities and exposes no calibration, held-out or broad evaluation
  mount.

The preparation boundary must be committed and pushed before its clean exact
commit is executed. A failed materialisation remains evidence; there is no
selective retry-to-success.

## Attempt 0001 outcome

Attempt `R28-S03-V4-CORPUS-MATERIALISATION-0001` failed before corpus
materialisation. The valid foreign-tenant organisation manifest has no
aliases, while the isolated provisioner incorrectly required an `aliases`
array. Read-only database inspection after failure confirmed three isolated
workspaces, zero ImportBatch rows and zero documents. No provider or AWS call
occurred and no corpus item was attempted. The provisioner correction accepts
an omitted aliases member as an empty list while continuing to validate any
present aliases array. Attempt `0002` therefore has a new immutable identity.

## Attempt 0002 outcome

Attempt `R28-S03-V4-CORPUS-MATERIALISATION-0002` failed before corpus
materialisation. A valid primary manifest entry explicitly records a null
effective date, while the harness applied the governed `2026-01-01` default
only to an omitted field. Read-only database inspection again confirmed three
isolated workspaces, zero ImportBatch rows and zero documents. No provider or
AWS call occurred and no corpus item was attempted. The correction applies the
same existing default to null and omitted values and derives the persisted run
identity from the immutable run definition. Attempt `0003` therefore has a new
immutable identity.

## Attempt 0003 outcome

Attempt `R28-S03-V4-CORPUS-MATERIALISATION-0003` reached the first authenticated
ImportBatch and created 25 staging items, then failed before any upload or
document promotion. PHP serialised the empty associative signed-upload header
map as an empty JSON list, while the Python harness accepted only a map.
Read-only inspection confirmed three workspaces, one ImportBatch, 25 ImportItem
rows and zero documents. No provider or AWS call occurred and no selective item
rerun was made. The correction accepts only the empty-list wire representation;
non-empty upload headers remain required to be a named map. Attempt `0004`
therefore has a new immutable identity and a fresh isolated runtime.

## Attempt 0004 outcome

Attempt `R28-S03-V4-CORPUS-MATERIALISATION-0004` created nine ImportBatch rows,
210 ImportItem rows and 209 indexed documents before failing during the first
promotion of `e-bike-pool-proposal-v2.txt`. The frozen e-bike v1 and v2 entries
both omit an effective date, so the harness assigned both `2026-01-01`. The
persisted v1 predecessor therefore had that date and the application correctly
rejected v2 under its strict forward-only version chronology. The promotion
recorded three failures and terminated as `technical_exhaustion`; no selective
rerun occurred. No provider or AWS call occurred. Attempt `0005` uses the
general manifest-derived rule that a null-dated version with a declared
supersession date receives the preceding calendar day; null-dated entries with
no supersession date retain the existing deterministic default.

## Attempt 0005 outcome

Attempt `R28-S03-V4-CORPUS-MATERIALISATION-0005` created 14 ImportBatch rows,
300 ImportItem rows, 300 indexed primary documents and 982 canonical chunks.
It then failed before any governance transition or separate-scope
materialisation because the harness indexed the canonical document
administration response by the import-workflow field name `filename`; the
response exposes `source_filename`. All 300 documents remained draft. No
provider or AWS call occurred and no selective item rerun was made. Attempt
`0006` uses the canonical response field and a fresh isolated runtime.

## Attempt 0006 outcome

Attempt `R28-S03-V4-CORPUS-MATERIALISATION-0006` created 14 ImportBatch rows,
300 ImportItem rows, 300 indexed primary documents and 982 canonical chunks.
The application then rejected the first governance transition with HTTP 422
because the harness supplied a prefixed idempotency value rather than the UUID
required by `DocumentGovernanceCommandRequest`. All documents remained draft
and neither separate tenant had begun. No provider or AWS call occurred and no
selective item rerun was made. Attempt `0007` supplies a plain UUID from a
fresh isolated runtime.

## Attempt 0007 outcome

Attempt `R28-S03-V4-CORPUS-MATERIALISATION-0007` created 14 ImportBatch rows,
300 ImportItem rows, 300 indexed primary documents and 982 canonical chunks.
It transitioned 93 documents to approved and three to withdrawn before the
application rejected a duplicate historical authority start; 204 documents
remained draft and neither separate tenant had begun. The harness had applied
real governance actions at the current wall clock, causing past-effective
versions in one family to share an authority start. No selective rerun,
provider call or AWS call occurred. Attempt `0008` applies the real governance
actions through an E2E-only command at each frozen effective/supersession date,
then begins from a fresh isolated runtime.

## Attempt 0008 outcome

Attempt `R28-S03-V4-CORPUS-MATERIALISATION-0008` materialised and indexed all
318 searchable documents across the three isolated workspaces and produced
1,000 canonical chunks. It then failed closed before governance replay because
the negative-fixture harness keyed the oversized-file validation result by the
simulated request filename rather than the governed manifest fixture filename.
Read-only inspection recorded 19 ImportBatch rows, 331 ImportItem rows, 329
verified and two rejected preflight items, 318 indexed documents and all 318
documents still in draft. No selective rerun, provider call or AWS call
occurred. Attempt `0009` preserves the request filename as evidence but records
the result under the exact manifest identity in a fresh isolated runtime.

## Attempt 0009 outcome

Attempt `R28-S03-V4-CORPUS-MATERIALISATION-0009` completed all four governed
scope workflows, leaving 318 indexed searchable documents, 1,000 canonical
chunks and 13 exact negative-fixture outcomes. Governance replay produced the
frozen primary distribution of 9 draft, 224 approved and 67 withdrawn; the 12
foreign and six security documents were approved only in their separate
workspaces. Post-run verification then found a materialisation blocker: the
deterministic sparse space existed, but none of the 318 ingestion claims or
three active workspace corpus generations was bound to it, and Qdrant held only
1,000 dense points. Attempt `0010` invokes the existing verified hybrid rebuild
and activation path for each workspace after dense ingestion. No selective
rerun, provider call or AWS call occurred.
The immutable result, governance summaries, runtime inventory and checksums are
preserved in
`docs/evaluation/r28-s03/failed-runs/R28-S03-V4-CORPUS-MATERIALISATION-0009/`.
