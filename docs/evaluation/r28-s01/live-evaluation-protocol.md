# R28-S01 live-evaluation protocol

Status: **COMPLETED — the protocol and original V1 population freeze remain
historical evidence. Before R28-S04 execution, the accepted comparison-
compatibility correction was integrated as immutable V2 and bound exclusively
to S04. Provider execution remains separately controlled.**

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

On 2026-09-04 David approved ADR-0037 and the final corrected R28-S04 routing,
request/token/time ceilings and USD 30 controlling hard cap recorded below.
This approval does not authorise provider execution or close R28-S01.

## Governance determination

ADR-0019/0020 govern corpus ownership, immutable identity, layered metrics,
absolute failures and deliberate promotion. ADR-0029 explicitly classifies
live-provider evaluation as optional and non-gating for ordinary phase closure.
Phase 28 now requires a gating body of live evidence. That is a durable change
to accepted evaluation/release semantics, so accepted ADR-0037 records the
narrow exception before any required live run can occur. It does not silently
rewrite ADR-0029.

## Immutable execution identity

Every run requires one exact 40-character repository SHA, clean tracked files,
the frozen corpus/population digests, provider/model and prompt identities,
embedding/sparse/reranker/generation profiles, retrieval-policy and execution-
profile digests, import/extraction/chunk/index settings, container/image IDs,
seed support, deterministic-boundary declarations, and unique immutable run,
attempt and authorised-rerun IDs. Missing identity is a hard stop.

R28-S04 is bound exclusively to
`tests/evaluation/engineering-populations/dolved-care-v4/v2`, identity
`dolved-care-v4-evaluation-population-v2`, digest
`adc9aa22646fc0f131ab7aa747dce91874655b95479cebc318653c3173e40f4c`.
Missing or mismatched path, identity or digest is a hard stop. The population is
immutable; every correction requires a new population identity/version and no
later run may silently replace it. R28-S02's two legacy components remain bound
separately in `r28-s02-population-binding.md` and cannot substitute for R28-S04.
Freezing this population does not authorise provider execution.
The machine-readable execution boundary is
`docs/evaluation/r28-s01/r28-s04-population-access.json`. The independent
authoring access manifest remains unchanged because it is an input to the
approved authoring contract aggregate.

### Post-freeze comparison-compatibility correction — 2026-09-04

Provider-free R28-S04 preflight found that V1's answerable comparisons were not
compatible with ADR-0022 V1's current-as-PRIMARY and selected-history-as-
COMPARISON contract. V1 remains byte-identical historical evidence at identity
`dolved-care-v4-evaluation-population-v1`, digest
`6254188d7fc7a698641750a81d436eac97eb425244704b64b1daac0c92803161`.

The independently accepted correction checkpoint
`COMPAT-V2-20260904-R7K3M8QX` (SHA-256
`40b16d20fab1734ac9cd04e65b66cb63f8423cf864bc8f17be4537c79771d4e1`)
produced candidate `dolved-v4-independent-comparison-compat-v2`, aggregate
digest `adc9aa22646fc0f131ab7aa747dce91874655b95479cebc318653c3173e40f4c`.
The accepted verdict is
`R28_V4_COMPARISON_COMPATIBILITY_V2_CANDIDATE_ACCEPTED`. V2 changes exactly
63 side labels across 21 cases, fully replaces `v4.case.corrected-b02-09`, and
leaves the other 52 cases unchanged. Routing and ceilings below are unchanged.

## Separately reported layers

Import/ingestion, extraction/chunking, eligibility/applicability, temporal and
version authority, planning, candidates, dense, sparse, fusion, reranking,
final evidence, no-answer/clarification, grounded generation, citations,
version-conflation, tenant isolation, injection resistance and provider/
infrastructure failures are reported independently. Retrieval and answer
quality are never combined into one score.

## Predetermined thresholds

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

## Provider and cost safety

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

### Approved R28-S04 routing and ceilings — 2026-09-04

The real repository boundaries establish this execution graph. Provider-backed
planning is not part of it: plans are frozen provider-free inputs, so any planner
provider call is hidden/out-of-scope and fails. The closed routing for all 148
utterances is:

| Expected outcome | Utterances | Required route |
| --- | ---: | --- |
| `EVIDENCE_FOUND` | 86 | retrieval, reranking, generation and judging |
| `INSUFFICIENT_EVIDENCE` | 10 | retrieval and reranking, then deterministic refusal after evidence-threshold assessment |
| `NO_RETRIEVAL_CANDIDATES` | 10 | retrieval; no reranking when the actual candidate set is empty; then deterministic no-candidate outcome |
| `NO_ELIGIBLE_EVIDENCE` | 12 | deterministic eligibility/cross-tenant termination |
| `CLARIFICATION_REQUIRED` | 10 | deterministic clarification |
| `COMPARISON_SCOPE_INCOMPLETE` | 10 | deterministic incomplete-comparison termination |
| `TEMPORAL_SCOPE_UNRESOLVED` | 10 | deterministic temporal clarification |

This accounts for 148 utterances: 106 require retrieval, at most 96 reach
reranking, 86 reach generation and judging, and 62 terminate deterministically.
The 96 reranked utterances expand to 140 provider HTTP requests because comparison
cases may require the PRIMARY and COMPARISON sides to be reranked independently.
No utterance may be silently skipped or assigned its expected outcome without
executing its required route.

The current generic `run.py live-hybrid` and `run_generation.py` entry points do
not directly load the V4 authoring schema. R28-S01 freezes the population, routing
contract, thresholds, ceilings and fail-closed requirements. R28-S04 owns the
dedicated execution wrapper and actual provider-cost accounting that compose the
existing provider adapters, batching, side splitting, generation and whole-answer
judging boundaries. Missing wrapper enforcement or cost accounting blocks R28-S04
execution; their absence today does not block freezing this R28-S01 protocol
because provider execution is not authorised. Any graph change requires a revised
proposal and David's approval; it must not be improvised during execution.

- dense corpus/index embedding: one base Voyage `voyage-4-large` request over
  the frozen checkpoint chunks; at most two physical attempts; 750,000 input
  tokens per attempt and 1,500,000 total; zero output tokens; USD 0.20;
- dense query embedding: one base request containing all 106 retrieval utterances
  (the current adapter sends one all-items HTTP request and does not consume the
  general `embedding_batch_size` setting); at most two physical attempts;
  50,000 input tokens per attempt and 100,000 total; zero output; USD 0.05;
- sparse corpus/query encoding: local pinned FastEmbed SPLADE, zero provider
  requests, tokens or USD;
- Voyage `rerank-2.5`: at most 140 base HTTP requests for the 96 utterances that
  can reach reranking, including independent PRIMARY and COMPARISON requests where
  required. Each request carries at most 15 candidates. At most 280 physical
  attempts; 8,192 input tokens per attempt and 2,293,760 total; zero output;
- OpenAI `gpt-5-mini` generation: 86 base requests, at most 172 physical
  attempts; 8,192 input tokens and 4,096 output tokens per attempt, 1,409,024
  input and 704,512 output total; USD 2.00;
- OpenAI `gpt-5-mini` evaluator: one whole-answer judgement per generated case,
  86 base requests and at most 172 physical attempts; 12,288 input tokens and
  2,048 output tokens per attempt, 2,113,536 input and 352,256 output total;
  USD 1.50.

The approved whole-run ceiling is 314 base provider requests, 628 physical
attempts, 7,416,320 input tokens, 1,056,768 output tokens, USD 30, concurrency 1
and 180 minutes wall clock. It includes 106 query-embedding items, 140 base
reranker requests (280 maximum attempts), 86 generation calls and 86 judge calls.
The USD 30 R28-S04 total maximum is the single controlling monetary hard stop;
any stage-level dollar values are planning estimates only and cannot weaken,
replace or obscure it. Each logical request permits at most one retry,
only for the wrappers' existing retryable transport, timeout, 429 or 5xx classes.
A provider `Retry-After` may be honoured once only when it remains inside every
request, token, cost and wall ceiling. No selective case retry or repeated probe
is permitted. The existing live reranker pacing remains 25 seconds between
physical requests.

Before its first provider call, the R28-S04 wrapper must pass provider-free tests
proving the exact population identity, all 148 utterances accounted for, exact
route counts, request/token/time/USD admission, the one-retry maximum, actual
usage/cost capture, immediate hard stop and immutable run identity. It must enforce
the aggregate counters and refuse to start any request whose worst-case authorised
attempt could exceed the remaining request, token, time or monetary authority.
Voyage embedding usage/cost and
OpenAI generation usage/estimated cost are already captured; reranker tokens and
attempts are captured but require the approved price snapshot, and evaluator
tokens/attempts are captured but cost currently remains `null`. Execution stays
blocked until both stages record enforceable actual/estimated cost with an
approved pricing snapshot. Missing usage or cost is `null`, never zero, and
cannot pass the pilot-readiness gate.

Credential absence means `SKIP`, zero calls and no gate pass. Any exceeded
ceiling means `FAIL` and immediate hard stop.

No selective case retry is permitted. Every complete rerun requires separate
David approval and a new immutable run identity.

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
