# Session Journal: R16-S08 — Implement Hybrid Retrieval and Reranking

## Date

2026-08-08

## Session mode

Implementation in teaching mode against accepted ADR-0021. No architecture
was redesigned and no accepted ADR was modified.

## What happened

The retrieval foundation was extended from dense semantic retrieval to the
full ADR-0021 hybrid pipeline. Python gained provider-neutral sparse encoding,
deterministic fusion and reranking boundaries. FastEmbed/SPLADE++ is isolated
behind `SparseEncoder`; Voyage `rerank-2.5` is isolated behind `Reranker`; and
ordinary tests use deterministic fakes. RRF is application-owned and
deterministic, with each `COMPARE` side kept independent.

The existing Qdrant collection now carries a second named sparse vector on the
same deterministic points. Sparse operations require explicit workspace and
corpus-generation scope, and exact completeness verification covers identities,
payload lineage and both vector schemas. Canonical text and lifecycle state
remain authoritative in PostgreSQL; Python still has no PostgreSQL access.

Laravel gained immutable sparse-profile and sparse-space-generation lineage,
lineage-bound evidence policies, coordinated hybrid rebuild, atomic activation
and audited lifecycle-correct rollback. Retrieval now performs Laravel-owned
hydration and eligibility checks before reranking and again afterwards, validates
the returned identities and lineage, then applies the evidence policy and final
evidence bound. Missing or mismatched policy, sparse or reranker failures fail
closed rather than silently reverting to dense-only retrieval.

The purpose-scoped HMAC protocol was extended with explicit rerank, corpus
rebuild and corpus verification operations. Reranking sends only canonical text
Laravel has already deemed eligible, and telemetry records only allowlisted
counts, timings and lineage — never query or chunk content.

## Important implementation decisions

* FastEmbed 0.7.4 was selected by the locked dependency solve for ADR-0021's
  approved 0.7 release line. Its SPLADE++ adapter checks the model token count
  before encoding and never relies on silent truncation.
* The V1 sparse profile fingerprint is derived from its canonical immutable
  provider/model/tokenizer/vector/max-input configuration, rather than stored as
  an arbitrary label. The underlying model snapshot is pinned to
  `Qdrant/Splade_PP_en_v1` revision
  `efcd182bc7eb351e81a9445752d4388c2bab500b`, not a mutable repository head.
* Rebuild is explicit, bounded and resumable. It copies authoritative canonical
  chunk assignments into a new generation, computes both vector axes, verifies
  the complete point manifest and activates only after verification. The old
  generation remains active until the atomic switch.
* Rollback is an audited `SUPERSEDED -> ACTIVE` transition after live
  completeness verification, never a pointer mutation or request-time fallback.
* One active evidence policy is permitted per exact dense/sparse profile
  lineage. This allows independently calibrated vector lineages to coexist
  while preventing ambiguous policy resolution for one lineage.
* The offline calibration fixture uses a deterministic fake only to prove
  calibration/held-out split isolation and harness mechanics. Live calibration
  uses source-anchored positive and eligible-but-irrelevant passages, retains
  candidate-level observations and enforces isolation at case level.
* `COMPARE` sides remain independent through reranking. The same chunk may be
  eligible on both temporal sides, so request/result identity is the pair
  `(side, chunk_id)`, Voyage is invoked once per side and ranks restart at one
  within each side.

## Verification performed

* Python: 253 passed, 3 credential-gated live tests skipped.
* Laravel: 188 passed, 786 assertions.
* Frontend: 26 passed.
* Ruff lint/format, Mypy (130 files), Pint (209 files), ESLint and TypeScript
  all passed.
* The Next.js production build passed with `NODE_ENV=production`. The first
  attempt inherited `NODE_ENV=development` from the running development
  container and failed during framework prerendering; the correctly scoped
  production build passed without source changes.
* Every migration ran cleanly on a disposable PostgreSQL database. Catalogue
  inspection confirmed the hybrid lifecycle/check constraints, per-lineage
  active-policy index and sparse-space protection triggers. The disposable
  database was removed afterwards.
* Qdrant integration tests covered idempotent named-vector and payload-index
  provisioning, dense+sparse writes/search and exact dual-axis completeness.
* Shared JSON Schemas, purpose-scoped HMAC validation, locked dependencies and
  `git diff --check` passed.

## Problems and corrections

Qdrant completeness initially requested only the dense named vector while the
new verifier also needed to inspect sparse values. The adapter was corrected to
request both names, and the focused and complete integration suites passed.

The first frontend build attempt used the development container's deliberately
non-production `NODE_ENV`; rerunning the same build with the production value
passed. This was an execution-environment issue, not a frontend defect.

The first multi-case Voyage calibration attempt reached the provider rate limit.
It produced no result and was not partially accepted. The command was corrected
to pace calls explicitly; the retry used bounded provider backoff and completed
all cases. The preliminary live result scored 17 passages across four
calibration and four untouched held-out cases (276 input tokens), proposing an
evidence threshold of `0.337890625`. Calibration and held-out precision, recall
and F1 were each `1.0`; the lowest calibration positive was `0.337890625`, the
highest calibration negative was `0.2890625`, the lowest held-out positive was
`0.5` and the highest held-out negative was `0.26953125`.

That result records revision `4444db5-dirty`, so it is preliminary verification
evidence rather than an accepted production policy. No fake-backed or
uncommitted `EvidenceThresholdPolicy` was activated.

The live result also exposed that the initial `numeric(8,7)` policy column could
not preserve Voyage's selected `0.337890625` boundary exactly. Before any policy
was written, the uncommitted migration was corrected to `numeric(12,10)` and a
focused persistence test was added. The focused Laravel suite passed 8 tests
with 34 assertions; every migration then passed on a new disposable PostgreSQL
database and catalogue inspection confirmed precision/scale `12,10`. The
disposable database was removed.

The first complete live pipeline run then exposed an incorrect global candidate
identity assumption for `COMPARE`: one eligible distractor appeared on both
independently resolved sides. ADR-0018 and ADR-0021 already require those sides
never to be merged, so this was an implementation defect rather than a missing
architecture decision. Reranking was corrected to use side-qualified identity,
invoke the provider independently per side and validate contiguous per-side
ranks. A regression test covers the same chunk on both sides.

The next run reached Voyage but was rate-limited because the harness paced
between cases while a two-sided `COMPARE` case made two immediate calls. Pacing
was moved to the provider-call boundary, covering every side and retry. The
complete rerun succeeded and its disposable Qdrant collection was removed.

The live pipeline configuration was dense `40`, sparse `40`, fusion `15`,
reranker `15`, evidence threshold `0.337890625`, final evidence `5` and RRF
`60`. It used Voyage `voyage-4-large`, Voyage `rerank-2.5`, and the pinned
SPLADE++ profile fingerprint
`e7bc2e4760b30c129c4d948ff3b34e1c89193ffc57cc072391cd5a75f98b615d`.
The provisional policy fingerprint was
`e1c7acfcb3d54e37a536e1f63d9490600cd6ccf57e0b06fc1c794a843cabd22f`.

Dense-only at K=3 scored recall `0.9565217391`, precision `0.1739130435`,
MRR `0.5` and nDCG `0.9404752067`. Hybrid at final K=5 scored recall `1.0`,
precision `0.1130434783`, MRR `0.5014492754` and nDCG `0.9516022960`.
Both had zero hard failures and perfect planner, eligibility and outcome
accuracy. Hybrid recall was `1.0` for `CURRENT`, `VALID_AT_DATE`, `COMPARE`,
applicability, adversarial and all four held-out cases. The precision values are
not a like-for-like regression because the configured K differs, but the result
still revealed that all held-out evidence cases returned five accepted
candidates (precision `0.2`). The threshold therefore remained recall-safe but
was not sufficiently selective on candidates produced by the actual pipeline.
The accepted recorded V2 dense baseline remains recall `1.0`, precision
`0.1884057971`, MRR `0.5543478261` and nDCG `0.9919767338`; it is preserved for
lineage but not presented as a direct provider comparison because it used
recorded observations rather than this live source-anchored Qdrant run.

The run used 246 embedding input tokens (configured estimate `$0.00002952`) and
2,207 reranker input tokens. Search latency was 403.5 ms mean, 417.1 ms p50 and
767.2 ms p95. Rerank wall time included deliberate 25-second rate-limit pacing,
so its 24.6-second mean is not presented as provider latency. No reranker price
was configured and no cost was invented.

## Current status and next step

The implementation and first truthful live dense-versus-hybrid result are ready
for review, but R16-S08 is not yet marked complete and the provisional policy
must not be promoted. The exact `0.337890625` value remains the current
experimental threshold only for its source-anchored calibration lineage. The
safe sequence is to commit the reviewed implementation, regenerate
actual-pipeline calibration observations from that exact commit using only the
calibration cases, select the threshold without consulting held-out cases, then
run the untouched held-out acceptance once. Only an accepted result may be
persisted and activated. `tasks.json` therefore remains on R16-S08 with
`completed_through` unchanged at R16-S07. No commit or tag was created.
