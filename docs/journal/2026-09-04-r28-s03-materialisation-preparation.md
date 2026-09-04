# R28-S03 — Frozen V4 corpus materialisation preparation

## Status

R28-S03 is in progress. No corpus materialisation has run yet.

## Boundary

The active immutable run identity is
`R28-S03-V4-CORPUS-MATERIALISATION-0003`. It binds the frozen checkpoint 19
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
