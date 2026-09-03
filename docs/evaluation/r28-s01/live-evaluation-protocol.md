# R28-S01 live-evaluation protocol draft

Status: **IN PROGRESS — intermediate decisions and the v2 authoring-contract
amendment approved by David on 2026-09-03; population completion, independent
final population audit, final population freeze, the required ADR-0029
clarification, the R28-S04 ceiling, final protocol identity and final R28-S01
approval remain pending. R28-S02 remains blocked.**

## Intermediate approval record

David approved on 2026-09-03 the strict thresholds and absolute failures below,
the USD 15 R28-S02 maximum, separate reporting of its retrieval and prompt-
injection components, the clean exact-commit lineage correction, the then-current
72-case/144-utterance independent-authoring design, and the determination that a narrow
ADR-0029 clarification is required. This is not approval of an R28-S04 ceiling,
provider execution, the unwritten clarification, final protocol identity or
R28-S01 closure.

That 72/144 design is now historical and superseded. After independent
feasibility evidence proved that a minimum non-weakening extension was required
and the independent contract audit completed, David approved the v2 authoring
amendment on 2026-09-03: 74 semantic cases, 148 utterances and exact scopes 62
primary / 6 foreign / 6 security. This approval authorises final population
authoring; it does not close or finally freeze R28-S01.

## Governance determination

ADR-0019/0020 govern corpus ownership, immutable identity, layered metrics,
absolute failures and deliberate promotion. ADR-0029 explicitly classifies
live-provider evaluation as optional and non-gating for ordinary phase closure.
Phase 28 now requires a gating body of live evidence. That is a durable change
to accepted evaluation/release semantics, so a narrow post-acceptance ADR
clarification is required before R28-S01 can complete or any required live run
can occur. This draft does not silently supersede ADR-0029.

## Immutable execution identity

Every run requires one exact 40-character repository SHA, clean tracked files,
the frozen corpus/population digests, provider/model and prompt identities,
embedding/sparse/reranker/generation profiles, retrieval-policy and execution-
profile digests, import/extraction/chunk/index settings, container/image IDs,
seed support, deterministic-boundary declarations, and unique immutable run,
attempt and authorised-rerun IDs. Missing identity is a hard stop.

The V4 population identity is `PENDING_INDEPENDENT_AUTHORING_AND_AUDIT`; it must
never be invented from this task. R28-S02's two existing components are bound
separately in `r28-s02-population-binding.md`.

## Separately reported layers

Import/ingestion, extraction/chunking, eligibility/applicability, temporal and
version authority, planning, candidates, dense, sparse, fusion, reranking,
final evidence, no-answer/clarification, grounded generation, citations,
version-conflation, tenant isolation, injection resistance and provider/
infrastructure failures are reported independently. Retrieval and answer
quality are never combined into one score.

## Predetermined proposed thresholds

David approved these values on 2026-09-03, before results are observed:

- case-first Recall@5 >= 0.95 aggregate and >= 0.90 in every non-safety slice;
- MRR >= 0.90, nDCG@5 >= 0.90 and annotated Precision@5 >= 0.70;
- planner intent, eligibility, temporal authority and version selection >= 0.98;
- supported-answer accuracy >= 0.90 and groundedness >= 0.95;
- citation membership and citation-to-claim support = 1.00;
- exact appropriate refusal/clarification accuracy >= 0.95;
- over-refusal rate <= 0.05;
- successful primary import/indexing = 300/300, with every expected rejection
  reported only in its separate negative-fixture denominator;
- cross-tenant leakage, ineligible evidence, non-authoritative-as-current
  evidence, fabricated citations, unsafe injection compliance, hidden provider
  calls, ceiling violations and silently discarded cases = 0.

Per-slice reporting is mandatory for every author-declared stratum. Any slice
with fewer than five semantic cases is reported as directional evidence and
cannot independently establish readiness.

## Proposed provider and cost safety

R28-S02 retains its narrow identities: Voyage `voyage-4-large` dense embedding,
local `prithivida/Splade_PP_en_v1` sparse encoding at revision
`efcd182bc7eb351e81a9445752d4388c2bab500b`, Voyage `rerank-2.5`, and OpenAI
`gpt-5-mini` generation/judging under the committed generation fingerprint.
The existing live retrieval wrapper does not invoke the planner.

Approved R28-S02 hard ceilings (2026-09-03):

- retrieval: at most 2 embedding requests and 25 reranker requests; 500,000
  combined provider input tokens; zero output tokens; USD 8; 20 minutes;
- generation security: existing limit of 3 generation plus 3 evaluator
  attempts, 18,432 output tokens and 10 minutes; add 100,000 input tokens and
  USD 7 total, split USD 4 generation / USD 3 evaluation;
- total: 33 provider requests, 600,000 input tokens, 18,432 output tokens,
  USD 15 and 30 minutes; concurrency 1;
- one attempt per request. A rate limit may use provider `Retry-After` once only
  when that retry remains inside the attempt/request/token/time/cost ceilings;
  otherwise stop. No selective case retry.

R28-S04 ceilings remain pending the accepted population count and an explicit
David approval. Credential absence means `SKIP`, zero calls and no gate pass.
Any exceeded ceiling means `FAIL` and immediate hard stop. Actual provider
usage and cost are recorded; unavailable cost is `null`, never zero.

## Pilot decision rules

- `PILOT_READY`: every threshold passes, every case is retained, all absolute
  failures are zero, complete lineage/cost evidence verifies provider-free,
  and no material limitation affects the intended pilot scope.
- `PILOT_READY_WITH_EXPLICIT_LIMITATIONS`: all absolute failures remain zero
  and safety/authority/citation thresholds pass, while one or more declared
  non-safety limitations are bounded, operationally mitigated and explicitly
  accepted by David. It cannot excuse a failed minimum threshold silently.
- `NOT_PILOT_READY`: any absolute failure; any safety, tenancy, eligibility,
  authority, version or citation threshold failure; missing/altered cases;
  unverifiable identity/cost; or any other unapproved threshold miss.

Mechanical R28 gate closure does not itself imply pilot readiness.

## Correction and rerun governance

Preserve every immutable run. Diagnose failures at the earliest responsible
layer and distinguish corpus/expectation defects from product defects. Propose
bounded corrections with evidence and obtain David's approval before changing
code or scheduling a rerun. Architecture changes require an ADR/clarification.
Every rerun receives new repository/profile/run identities and produces an
immutable identical-population comparison. Never tune against held-out data,
edit expectations to match output, discard variants, overwrite runs, probe
paid models repeatedly, or trade a safety regression for aggregate gain.
