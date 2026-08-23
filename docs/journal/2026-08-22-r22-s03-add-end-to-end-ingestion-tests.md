# R22-S03 — Add end-to-end ingestion tests

**Date:** 2026-08-22  
**Status:** Complete

## What changed

R22-S03 now has a disposable, provider-free Playwright environment that proves
the document journey through the real internal stack. A real browser signs in,
uploads a representative text document and a corrupt PDF, and observes both
authoritative outcomes. Laravel persists and publishes the ingestion event,
LocalStack carries it, the real Python worker parses and chunks the successful
document, deterministic dense and sparse adapters materialise it in real Qdrant,
and Laravel receives the signed completion. The same journey retrieves the
expected evidence and proves that a second workspace receives concealment rather
than access.

The deterministic adapters are selected through the normal settings/factory
seams and are permitted only in the isolated E2E and current-evaluation
environments. A complete deterministic tuple is mandatory in E2E, provider
credentials must be absent, catalogue planning is exact-question and fail-closed,
and no browser-controlled switch can change adapter behaviour.

The heavyweight sparse-model boundary is kept honest by a separate
`make test-splade-integration` target that loads the real configured SPLADE model
and performs bounded inference. The Playwright journey substitutes that model for
speed and determinism and therefore makes no claim about sparse-model quality.

The first current-retrieval implementation was rejected during its read-only
conformance audit. It derived eligibility scopes and searchable chunks from
expected EvidenceUnits, bypassed Laravel's real eligibility resolver, treated
controlled outcomes as automatically correct, and used a smaller generic
population. Its 23-case / 25-variant output is therefore diagnostic history only
and is not eligible for promotion.

The corrected current retrieval gate uses the approved 42-case / 126-variant
engineering snapshot and the independent 93-version document catalogue. A
private Laravel command, guarded to the disposable evaluation environment,
persists the real organisation, aliases, document families, authority windows,
applicability and active generations. It then invokes the production
`BuildAuthorisedKnowledgeScope` and `EligibilityResolver` for every authored
plan at the fixed evaluation clock. The typed Laravel-to-Python artefact records
all 126 resolver outcomes plus explicit isolation probes. Python builds search
chunks only from independently checksummed source documents and uses expected
EvidenceUnits only after retrieval for scoring.

The deterministic profile binds the population, planner catalogue, independent
source catalogue, chunking and retrieval configuration, eligibility mapping,
resolver source/configuration, fixed time, adapter fingerprints and harness
version. The full artefact digest preserves exact repository lineage; a separate
semantic comparability digest excludes repository identity in accordance with
the accepted comparison policy. Candidate output remains marked **CANDIDATE —
NOT PROMOTED**. Comparison fails closed unless the baseline result, promotion
record, profile digest and complete checksum manifest all agree; no command can
promote or refresh its own baseline.

## Verification

- Clean `make test-e2e`: 1 Playwright journey passed in the isolated stack; the
  stack and volumes were removed after success.
- `make test-splade-integration`: 1 passed using the real configured SPLADE model.
- Focused real-eligibility boundary: 5 Laravel tests, 23 assertions.
- Disposable Laravel-to-Python diagnostic: 42 cases, 126 variants, 93
  independent source chunks and 126 real resolver outcomes accounted for.
- Laravel: 351 passed, 2 skipped, 1,868 assertions.
- Python provider-free suite: 601 passed, 14 integration tests deselected.
- Web: 115 passed.
- Pint, Ruff lint/format, Mypy, ESLint, web and E2E TypeScript checks: passed.
- Shell syntax, Compose configuration, JSON parsing and `git diff --check`:
  passed.
- No OpenAI or Voyage calls were made.

## Baseline promotion and closure

The exact reviewed candidate at repository commit
`89d407612623acf08bef99d7871fad16a2f1984e` was explicitly accepted as the
first `deterministic-v1` orchestration-regression baseline. Its authoritative
`experiment-result.json` was promoted byte-for-byte, together with the exact
Laravel eligibility artefact that proves the foreign-workspace isolation probe.
The promotion and manual gate preserve the authenticated reviewer, decision and
provider-free scope; the baseline makes no claim about live OpenAI, Voyage,
SPLADE or reranker quality.

The approved ADR-0029 compatibility clarification treats `cross-workspace` as
a non-metric requirement for this deterministic gate only. It can be satisfied
only by the checksum-valid Laravel probe bound to the candidate's complete
deterministic profile, with execution true and zero foreign documents. Every
other protected slice remains metric-bearing and is checked against every
configured regression tolerance. Missing, unexecuted, non-zero, mismatched and
tampered probes fail closed, as do tampered baseline, promotion, candidate and
execution-profile identities.

Promotion identities:

- semantic comparison digest:
  `714a1211558dadf1f7d11483356e3c47c58cdd5e382727b1443cafd3c67bb803`;
- deterministic execution profile:
  `33450c7c9f80f741e9465c2aa8e83c7dad65e2bfb7806c3d6390df46f5f1e0c0`;
- promoted result SHA-256:
  `f73389aa4a5b88a536cbb7c6b098ee0a5e4f5ce341e02efe760e821273540ae1`;
- eligibility artefact SHA-256:
  `2e8c048b16f72bd30a16b68d72a986360841e82c648c172228d3656801ac109a`.

The promoted deterministic comparison gate passed against the reviewed
candidate. Focused baseline-governance, schema and current-retrieval input tests
passed (39 tests), the full provider-free Python suite passed (595 tests, 14
integration tests deselected), and the changed Python boundary passed Ruff,
format and Mypy in the pinned Python 3.14 environment. No provider calls were
made and the reviewed candidate was not regenerated.

The first clean E2E acceptance rerun correctly failed closed before embedding.
Laravel still provisioned historical deterministic dense v1 and sparse v1
profiles while Python executed dense v3 and sparse v4. The failure was isolated
to E2E provisioning lineage; it did not affect the reviewed candidate or
promoted baseline.

The approved bounded correction added one shared, versioned E2E contract vector.
Laravel and Python independently canonicalise the same typed identities and
agree on these fingerprints:

- dense `token-hash-unit-vector-v3` / `deterministic-v3`:
  `d5a56dffa5539ac2c7b1582fcdcc0658855399532e9484868fd2f7c97e1b8218`;
- sparse `token-hash-sparse-v4` / `deterministic-v4`:
  `d4f361438791330c05d1e8125fc4f16df00280de1db9cbb8f8fb0325c756b9d7`.

The vector also binds the isolated Qdrant collection, dense vector name,
1024 dimensions, cosine distance and sparse vector name. Production Voyage and
SPLADE profiles remain unchanged, and the worker still rejects any genuine
profile mismatch.

| Capability | Laravel E2E identity | Python effective identity | Contract boundary | Status |
| --- | --- | --- | --- | --- |
| Dense embedding | Shared v3 profile and fingerprint above | `token-hash-unit-vector-v3`, revision 3, `deterministic-v3` | Signed ingestion claim/completion | Aligned and exercised |
| Sparse encoding | Shared v4 profile and fingerprint above | `token-hash-sparse-v4`, revision 4, `deterministic-v4` | Nested signed vector-space claim/completion | Aligned and exercised |
| Qdrant space | `dolved-e2e-vectors-v1`, `dense`, 1024, cosine; `sparse` | Same request model | Signed ingestion claim | Aligned and exercised |
| Reranking | Deterministic provider/model declared for E2E | `token-overlap-v2`, `deterministic-v2` | Retrieval policy response validation, not ingestion | Aligned and exercised |
| Retrieval planning | Deterministic catalogue provider/model declared for E2E | `catalogue-retrieval-planner`, `catalogue-v1`, catalogue-bound prompt checksum | Authenticated retrieval response lineage | Aligned and exercised |
| Contextualisation | No deterministic E2E identity declared | OpenAI defaults with an empty credential | Not used by R22-S03 | Not yet used; R22-S04 boundary |
| Generation | No deterministic E2E identity declared | OpenAI defaults with an empty credential | Not used by R22-S03 | Not yet used; R22-S04 boundary |

Focused Laravel profile/provisioning tests passed (3 tests, 21 assertions) and
focused Python deterministic-profile tests passed (12). The final focused
baseline-governance, schema and input suite passed (42 tests), and the promoted
comparison gate passed again with zero metric deltas and no absolute failures.
The single subsequent clean Playwright run passed in 19.1 seconds: the
representative document reached
`INDEXED`, the corrupt document reached `FAILED`, retrieval returned the expected
evidence, and the foreign workspace received 404. The real queue, object store,
database, Python worker and Qdrant paths executed. The disposable environment
and volumes were removed. Provider credentials were absent and external provider
calls remained zero.
