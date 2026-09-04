# R28-S03 — Frozen V4 corpus materialisation preparation

## Status

R28-S03 is in progress. No corpus materialisation has run yet.

## Boundary

The immutable run identity is
`R28-S03-V4-CORPUS-MATERIALISATION-0001`. It binds the frozen checkpoint 19
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
