# ADR 0021: Define Hybrid Retrieval and Reranking Architecture

## Status

Accepted

## Date

2026-08-08

## Relationship to prior ADRs

### Extends ADR-0014's vector storage model; does not reopen it

ADR-0014 already anticipated exactly this extension and named it as a
capability its model must not block: *"sparse or additional named
vectors — the named dense vector schema decision above"* and *"Qdrant
supports multiple named vectors per point, including named sparse
vectors... adding or removing a named vector on an existing collection...
populating a newly added vector in the background and selecting it
explicitly at search time."* This document exercises that anticipated
seam. It does not reopen ADR-0014's collection topology, the embedding-
space/workspace-corpus generation split, the `VectorStore` boundary, or
the minimal-payload principle — it adds one new, symmetric compatibility
axis (a sparse profile and its own generation concept) alongside the
existing dense one, reusing the exact rebuild/activation/rollback
machinery ADR-0014 already built rather than inventing new machinery.

### Extends ADR-0018's pipeline and outcome taxonomy; consumes the rest unchanged

ADR-0018's `AuthorisedKnowledgeScope` → `RetrievalPlanner` →
`EligibilityResolver` → `EligibleRetrievalScope` → `Retriever` →
`RetrievalResult` pipeline, its Laravel/Python ownership boundary, its
`rc1` protocol, and its `COMPARE` two-sided model are all treated as
settled and consumed unchanged. Two additive extensions are made, both
already named as legitimate by ADR-0018 itself: `EligibleRetrievalScope`
gains an explicit sparse-generation identity (ADR-0018's own reranking
forward-compatibility note promised the outcome taxonomy and scope shape
would not need to change *for reranking alone* — this addition is a
consequence of sparse retrieval, a distinct capability, not a broken
promise); and the `rc1` protocol gains a new purpose, `retrieval.rerank`,
exactly as ADR-0018 already anticipated: *"Future extensions (for example,
a reranking call once `R16-S07` is designed) add a new purpose to this
same protocol without requiring a new signature format."*

### Fulfils a deferral ADR-0018 made explicitly to this session

ADR-0018's outcome taxonomy is explicit that *"V1 does not, and must not,
reject a non-empty candidate set on raw score quality... without a
calibrated acceptance policy — explicitly out of this document's scope,
deferred to the hybrid retrieval/reranking and evaluation architecture
(`R16-S05`, `R16-S07`)."* This document is that deferred decision. It adds
one new outcome value to the taxonomy ADR-0018 established — this is the
one place this document does add to the outcome taxonomy, narrowly, for
exactly the reason ADR-0018 named and deferred, not a reopening of
anything ADR-0018 considered settled.

### Consumes ADR-0017, ADR-0019 and ADR-0020 without reopening them

ADR-0017's temporal-authority and applicability model, and ADR-0019/
ADR-0020's evaluation architecture (repository-owned corpus, `EvidenceUnit`
ground truth, layered metrics, accepted baselines, the
`ModelAssistedEvaluator` boundary), are treated as settled. This document
populates lineage seams ADR-0019 already named and left unpopulated
(*"Fields anticipating hybrid retrieval, reranking, and generation
(reranker provider/model, sparse/hybrid configuration...) are named as
seams the schema must not block, not populated now"*) — it does not
redesign the evaluation architecture that consumes them.

## Context

Phase 16 built dense-only semantic retrieval (ADR-0018) and a repository-
owned evaluation harness that already measures it (ADR-0019, clarified by
ADR-0020). `R16-S06` implemented that harness and recorded the platform's
first measured baseline. Codex's implementation-driven recommendation,
informed by that baseline and by hands-on evaluation-harness experience,
is that hybrid retrieval — dense and sparse candidate generation, fused
and reranked before a calibrated evidence threshold — is the next
retrieval-quality improvement worth making, and specifies a concrete V1
shape (FastEmbed/SPLADE++ for sparse encoding, application-owned RRF
fusion, Voyage `rerank-2.5`, and an illustrative candidate-count
progression). This document evaluates that recommendation architecturally
rather than transcribing it: every concrete choice below is kept, but
justified independently, and nothing here overrides the platform's
existing evaluation-first, provider-neutral discipline in the name of
convenience.

## Philosophy

The purpose of hybrid retrieval is not to retrieve more evidence. Its
purpose is to increase the likelihood that the highest-quality *eligible*
evidence reaches the LLM, while preserving everything Phase 16 has already
committed to: deterministic eligibility (ADR-0017, ADR-0018), provider
neutrality (ADR-0013, ADR-0014, ADR-0018), reproducible evaluation
(ADR-0019, ADR-0020), and measurable improvement over the accepted
baseline rather than adopted improvement on subjective judgement. No
architectural decision in this document depends on a subjective quality
judgement; every quantitative choice below is named as versioned
configuration, calibrated by `R16-S08` against ADR-0019's evaluation
harness, never invented here as an architectural constant.

## What this ADR decides and does not decide

This ADR defines: the V1 hybrid retrieval pipeline as a sequence of
narrowing stages, each independently configured; a provider-neutral
`SparseEncoder` boundary with a concrete FastEmbed/SPLADE++ implementation,
and why a learned sparse encoder is required (not merely preferred) by
this platform's shared-collection topology; sparse-vector storage as an
additional named vector on existing Qdrant points, with its own
generation/compatibility concept extending ADR-0014's model; an
application-owned `FusionStrategy` boundary with Reciprocal Rank Fusion as
its V1 implementation, and why fusion stays application-owned rather than
Qdrant-native; the candidate pipeline as a sequence of independently
versioned, distinct optimisation problems, not one global `candidate_k`; a
provider-neutral `Reranker` boundary with Voyage `rerank-2.5` as its V1
implementation; the extended Laravel-hydration sequence spanning two
round trips; a versioned `EvidenceThresholdPolicy` that finally exercises
the calibrated-acceptance seam ADR-0018 deferred here, and the one new
outcome value it requires; the cost/latency shape of each stage, without
pricing; the rollout model, reusing ADR-0014's existing generation
lifecycle; and how every configurable stage integrates with ADR-0019's
evaluation harness. It does not decide: the exact numerical candidate
counts or threshold value for production (`R16-S08`, calibrated against
ADR-0019's evaluation harness); workspace billing, commercial pricing, or
rate/budget/enablement controls; answer generation, prompt construction,
or citation generation; or agent workflows.

## Decision

### The V1 hybrid pipeline: a sequence of narrowing stages

```text
Laravel: AuthorisedKnowledgeScope
  -> RetrievalPlanner (rc1: retrieval.plan)
  -> EligibilityResolver -> EligibleRetrievalScope
  -> Retriever (rc1: retrieval.search): dense retrieval + sparse retrieval
  -> Retriever: application-owned RRF fusion
  -> Laravel: batch hydration + eligibility recheck (existing ADR-0018 step)
  -> Reranker (rc1: retrieval.rerank), given hydrated text
  -> Laravel: final eligibility recheck on reranked identities
  -> EvidenceThresholdPolicy
  -> Final evidence set -> generation (Phase 17)
```

Every stage narrows; **no stage may widen the eligible evidence universe
`EligibilityResolver` established**, the same invariant ADR-0018 already
fixed for its own pipeline, extended here to every new stage this document
adds. A stage that cannot determine relevance or quality safely produces a
narrower result or a controlled failure, never a wider one.

### `SparseEncoder`: a provider-neutral boundary, FastEmbed/SPLADE++ as V1

A provider-neutral `SparseEncoder` abstraction, mirroring `Embedder`'s
exact Open/Closed shape (ADR-0013): `SparseEncoder.encode(request) ->
SparseEncodingResult`, with document and query encoding as two explicit
purposes carried on one immutable profile — directly mirroring ADR-0013's
"Explicit embedding purposes" two-level model (profile-level task-mode
pair, per-operation purpose record), not a new pattern invented for
sparse. The V1 implementation is FastEmbed running SPLADE++
(`prithivida/Splade_PP_en_v1`), evaluated and accepted below.

An immutable `SparseEmbeddingProfile`, fingerprinted exactly as
`EmbeddingProfile` already is (ADR-0013): encoder provider, model
identity, tokenizer/vocabulary version, output representation (sparse
index/weight format), adapter version, **the supported input-length bound
the underlying tokenizer/model actually enforces**, and any other
parameter that changes what sparse vector a given input produces. Profile
compatibility is checked identically to dense compatibility — a
`sha256`-of-canonical-snapshot fingerprint, never dimension- or
shape-matching alone.

**No silent truncation — the same discipline ADR-0013 already requires of
dense embedding, applied here without exception.** `SparseEncoder`
validates input length before encoding, against the bound recorded on its
`SparseEmbeddingProfile`. If the selected SPLADE++/FastEmbed
implementation cannot safely encode the complete canonical chunk text —
because it exceeds the tokenizer's supported input length — the adapter
returns a typed `input too large` / unsupported-input failure. It never
silently truncates the input and never emits a sparse vector that is then
presented as representing the complete chunk; a vector computed from a
truncated fragment is not a sparse representation of the chunk it claims
to describe, exactly the reasoning ADR-0013 already gives for disabling
Voyage's own provider-side truncation. Whatever tokenizer/model input
limit the underlying library actually enforces is recorded as part of the
immutable sparse-profile/configuration lineage above, not left as an
undocumented implementation detail Stage 16.8 might otherwise discover the
hard way.

**Ownership and deployment differ meaningfully from dense embedding, and
this document records that difference rather than glossing over it.**
Voyage (dense) is a hosted, external API call with its own credentials,
rate limits, and per-call cost. FastEmbed/SPLADE++ is a self-hosted,
ONNX-runtime model running inside the Python AI service itself — no
external network dependency, no credentials, no per-call provider cost.
This is a genuine operational difference, not merely an implementation
detail: sparse encoding's cost and failure modes are local-compute
characteristics (CPU/latency-bound), not external-dependency
characteristics (availability/rate-limit-bound). The `SparseEncoder`
boundary remains provider-neutral regardless — a future hosted sparse
provider, or a different self-hosted model, is an additive implementation
— but V1's concrete choice is deliberately self-hosted, and the adapter
implementation, not the contract, is where that operational profile lives.

### Why a learned, corpus-independent sparse encoder, not BM25, in this platform's shared-collection topology

This is an architectural requirement, not a retrieval-quality preference,
and follows directly from a decision ADR-0014 already made: Qdrant
collections are shared across workspaces (*"collection per workspace...
rejected"*), with tenant isolation enforced by payload filtering, not
physical partitioning.

**BM25's term weights are corpus-statistical, not per-document.** Its
IDF component is computed from document-frequency statistics over
whatever corpus scope it is computed against. In this platform's shared-
collection topology, that scope is either the whole collection (spanning
every workspace physically stored in it) or would have to be recomputed
per workspace to stay tenant-isolated. Neither is acceptable:

- **Collection-wide BM25 statistics** would make one workspace's term
  weights, and therefore its retrieval ranking, silently depend on the
  term-frequency distribution of *other workspaces'* documents — a rare
  term in Workspace A that happens to be common in Workspace B's unrelated
  documents would be weighted as though it were common for Workspace A
  too. This is a genuine cross-tenant coupling of ranking behaviour, not a
  content leak, but it is exactly the kind of hidden inter-tenant
  dependency ADR-0006's tenancy model has consistently designed against
  (*"no service may derive tenant identity implicitly"*; every layer
  explicit, never a shared hidden default).
- **Per-workspace BM25 statistics**, recomputed to stay tenant-isolated,
  would mean a chunk's sparse vector is not a stable function of its own
  text — it depends on the rest of that workspace's corpus at the moment
  of computation, and must be recomputed whenever *any* other document in
  the same workspace is added, edited, or removed. This directly violates
  ADR-0014's own load-bearing invariant that *"ordinary, successful
  document ingestion... adds that document's verified chunk points to the
  workspace's currently active corpus generation"* as a cheap, incremental
  operation, never one requiring a workspace-wide rebuild. Per-workspace
  BM25 would turn every single-document ingestion into an implicit
  workspace-wide re-weighting operation.

**The architectural requirement, stated precisely, is tenant-independent,
corpus-independent sparse encoding** — a method whose output for one chunk
never depends on any other document, in the same workspace or any other,
and therefore keeps ordinary incremental ingestion cheap (ADR-0014) and
tenant isolation uncompromised (ADR-0006), regardless of which specific
model computes it. **A learned sparse encoder satisfies this requirement;
classic BM25 does not, for the reasons above.** SPLADE++, via FastEmbed, is
the V1 implementation selected against this requirement — evaluated,
accepted, and fingerprinted exactly as `EmbeddingProfile` already is — but
it is not claimed to be the only implementation that could ever satisfy it.
A future `SparseEncoder` implementation (a different learned sparse model,
a different self-hosted or hosted encoder) remains available, additively,
behind the same provider-neutral boundary, provided it satisfies the same
corpus-independence requirement; **BM25 specifically remains rejected for
V1**, not because no alternative to SPLADE++ could ever be accepted, but
because its corpus-statistical foundation is structurally incompatible
with this platform's shared-collection tenancy model and cheap-incremental-
ingestion invariant, for the reasons already documented above.

### Sparse storage: an additional named vector, no new collection, extending ADR-0014's generation model

Sparse vectors live as an additional named vector (for example, `sparse`)
on the **same Qdrant points** dense vectors already occupy — never a
separate collection, never duplicated canonical text, never anything
outside Qdrant. This is exactly the extension ADR-0014's "Named vector
schema" section already named as the reason V1 named its dense vector in
the first place.

A **sparse-space generation** is introduced, symmetric to ADR-0014's
existing embedding-space generation: a platform-scoped compatibility
boundary defined by exactly one immutable `SparseEmbeddingProfile`
fingerprint, mapped to exactly one named sparse-vector definition added to
an existing embedding-space generation's collection (via Qdrant's
existing "add a named vector to an existing collection, populate in the
background" capability — no new *Qdrant collection* is required for this
step). A workspace corpus generation that is hybrid-enabled records
**both** its embedding-space-generation reference (dense) **and** its
sparse-space-generation reference (sparse) — two independent compatibility
axes on one workspace-corpus-generation record, mirroring exactly how
ADR-0014 already keeps embedding-space generation and workspace-corpus
generation as two independent, explicitly-tracked facts rather than one
conflated concept.

**Enabling hybrid retrieval for a workspace is a coordinated corpus
rebuild that must produce a complete new point set — not a claim that
existing points are silently upgraded in place.** ADR-0014's deterministic
point-identity derivation includes the workspace-corpus-generation
identity itself as an input; a new workspace corpus generation therefore
means every one of its points has a **different, newly-derived identity**
from the superseded generation's points, dense content included. "The
dense vector is reused" cannot mean the old Qdrant point continues to
exist under a new label — it means the new generation's build process must
still produce, for every expected point: a dense vector value, a sparse
vector value, the required payload, and correct generation/profile
lineage, under the new generation's own point identities. Two legitimate
strategies for the dense side, left as a Stage 16.8 implementation choice
rather than fixed here:

- **recompute** dense embeddings for the new generation alongside the new
  sparse vectors, the simpler option, at the cost of a full re-embedding
  pass even though the dense embedding profile has not changed; or
- **re-materialise** the already-computed dense vector *values* from the
  superseded generation's points into the new generation's newly-derived
  point identities (valid specifically because the dense embedding
  profile is unchanged, so the value itself is still correct — only the
  point identity and payload differ), computing only the new sparse
  vectors fresh.

Whichever strategy is chosen, the new generation is verified for
completeness — ADR-0014's existing "expected identity set, not count
alone" discipline, checked against **both** axes (every expected point
present, carrying a valid dense vector, a valid sparse vector, correct
payload, and correct generation/profile lineage) — before it is activated
via the existing per-workspace activation model. Adding sparse
configuration to the platform does **not** automatically populate any
existing or newly-created point with a sparse vector; population only
happens through this explicit, verified rebuild. The currently-active,
dense-only generation remains untouched and fully usable throughout —
ordinary retrieval, ingestion, deletion and archival continue against it
exactly as today — until the hybrid generation has been built, verified,
and explicitly activated. No new activation or verification *semantics*
are introduced; this document reuses ADR-0014's existing model exactly,
extended to a second compatibility axis it already anticipated, with the
completeness bar raised to cover both axes together.

**This is not claimed to require no relational migration.** If sparse-
profile and sparse-space-generation identity and lineage are persisted in
PostgreSQL — and they must be, for the same reason embedding-space and
workspace-corpus generation identity already are (ADR-0014: PostgreSQL is
authoritative for generation metadata and lifecycle) — then `R16-S08`
necessarily includes Laravel migrations, models, and relationships for
them, alongside every other implementation task this document assigns it.
This document fixes the relational *concepts* and invariants a migration
must satisfy (a sparse-space-generation identity and fingerprint, a
hybrid-enabled workspace-corpus-generation's dual generation references,
the lifecycle states both axes must satisfy together before activation);
it does not fix table names, column shapes, or migration syntax — that is
squarely `R16-S08`'s job, exactly as Stage 14.3 implementation was always
`R16-S08`'s predecessor's job for the equivalent dense-only concepts.

### `EligibleRetrievalScope` gains an explicit sparse-generation identity

A small, additive extension, consuming ADR-0018 without contradicting its
promise that reranking alone would not require `EligibleRetrievalScope` to
change — this change is a consequence of sparse retrieval, not reranking.
Exactly as ADR-0014's "Explicit propagation, never inference" invariant
already requires for the embedding-space and workspace-corpus generation
identities, the active sparse-space-generation identity (where the
workspace's active corpus generation is hybrid-enabled) is resolved
explicitly by Laravel and passed explicitly into `EligibleRetrievalScope`
— never inferred by Python. Where a workspace's active generation is not
yet hybrid-enabled, this field is absent, and the `Retriever` performs
dense-only retrieval for that request — a normal, expected state during
staged per-workspace rollout, never an error.

### `FusionStrategy`: application-owned, RRF as V1

Fusion combines dense and sparse candidate lists into one ranked list
**inside application code** (the Python `Retriever`), never inside a
Qdrant-native server-side fusion query. This was evaluated against
Qdrant's own native fusion capability and rejected for that role, for
reasons distinct from, and stronger than, a general preference for
in-house logic:

- **Provider neutrality.** `VectorStore` (ADR-0014) already isolates every
  Qdrant-specific concept behind one adapter — *"no application code
  outside one isolated adapter... depends on Qdrant-specific concepts."*
  Fusion expressed as Qdrant-native query syntax would embed a business
  decision (how to combine two ranked lists) inside a provider-specific
  query shape, exactly the inversion ADR-0014 already rejected for
  activation logic for the same reason: a provider-neutral storage
  abstraction should never be where a domain decision lives.
- **Deterministic testing.** An application-owned `FusionStrategy` is
  unit-testable against a fixed, fabricated dense/sparse candidate-list
  fixture, no live Qdrant instance required — the same deterministic-
  fake discipline already applied to `Embedder`, `RetrievalPlanner`, and
  `ModelAssistedEvaluator`. Qdrant-native fusion could only be tested
  against a real or emulated Qdrant server.
- **Lineage and evaluation.** ADR-0019 requires dense candidate count,
  sparse candidate count, and fusion size to be independently measurable.
  Application-owned fusion naturally exposes each stage's own output for
  lineage and layered evaluation; a server-side native fusion call would
  return only the final merged list, discarding exactly the per-stage
  signal ADR-0019's layered evaluation model depends on.
- **Portability.** `VectorStore.search` already has a settled, minimal
  contract (query vector, filter, result count) that this document does
  not need to extend with fusion-specific query DSL, keeping the adapter's
  surface exactly as narrow as ADR-0014 already fixed it.

### RRF: the V1 `FusionStrategy` implementation, made fully deterministic

`FusionStrategy` is introduced as its own provider-neutral(-in-spirit)
boundary — algorithm-neutral, following the identical Open/Closed
reasoning already applied throughout this pipeline — with Reciprocal Rank
Fusion as its V1 implementation, specified precisely enough that
**identical ranked inputs always produce identical fused ordering**, never
dependent on Qdrant's or any provider's own return order to break a tie:

- **Ranks are 1-based.** The first-ranked candidate in a list has rank 1,
  not 0.
- **The RRF formula uses a versioned `rrf_k` constant**: for a candidate
  present in ranked list `L` at (1-based) rank `r`, its contribution from
  that list is `1 / (rrf_k + r)`. `rrf_k` is recorded as part of the same
  versioned `HybridRetrievalConfiguration` the candidate-pipeline
  parameters belong to (illustrative value only, not fixed by this
  document) — never an unversioned literal buried in implementation code.
- **Canonical chunk identity is the deduplication identity.** A candidate
  appearing in both the dense and sparse lists is one fused candidate, not
  two, identified by its canonical chunk identity.
- **Each candidate receives at most one contribution from each retrieval
  list.** A candidate cannot appear twice in the same list's ranking and
  contribute twice from it; its fused score is the sum of at most one
  dense-list contribution and at most one sparse-list contribution.
- **Dense and sparse original rank and score are preserved as lineage** —
  distinct, individually recorded fields, never collapsed into the fused
  score alone, exactly as already stated.
- **Ties in fused score are broken deterministically**, by this ordered
  sequence, never by whatever order a provider happened to return
  candidates in:
  1. fused score, descending;
  2. best (lowest-numbered) source rank across the lists the candidate
     appeared in, ascending — a candidate that was ranked 1st in either
     dense or sparse retrieval outranks one whose best placement was 5th,
     even at equal fused score;
  3. canonical chunk identity, ascending — a final, always-available,
     always-unique tie-break that guarantees a strict total order exists
     for any input, however unlikely a triple tie is in practice.

Fusion computes the fused ranking from the preserved per-list ranks using
exactly this procedure, and for `COMPARE`, fuses each side (`PRIMARY`,
`COMPARISON`) **independently** — dense and sparse candidates for one side
are never fused against the other side's candidates, exactly extending
ADR-0018's existing never-merge invariant for `COMPARE` to this new stage.

Future fusion algorithms (a weighted linear combination, a learned fusion
model, Qdrant's own DBSF if a genuine need for it appears) belong behind
the same `FusionStrategy` contract, additive implementations exactly as
RRF is here, each expected to define its own equally precise, deterministic
tie-break behaviour rather than inheriting RRF's specifically — this
document names that seam without needing to design anything beyond it now.

### The candidate pipeline: six independent optimisation problems, not one global `candidate_k`

Six named, independently-versioned parameters — `dense_candidate_k`,
`sparse_candidate_k`, `fusion_candidate_k`, `reranker_candidate_k`,
`evidence_threshold`, `final_evidence_k` — each answering a different
optimisation question. Codex's recommended starting values (`dense_candidate_k`
40, `sparse_candidate_k` 40, `fusion_candidate_k` 15, `reranker_candidate_k`
15, `evidence_threshold` ≈0.80, `final_evidence_k` ≤5) are retained as the
**initial experimental configuration only**, not encoded as an
architectural constant anywhere in this document, and not implying any
relationship between parameters beyond what each is separately defined to
do:

- **`dense_candidate_k`** trades ANN search recall against latency/cost of
  a larger nearest-neighbour search — optimising for "don't miss relevant
  evidence dense embeddings can find," cheap per additional candidate.
  Influences **semantic recall**.
- **`sparse_candidate_k`** is the same recall-versus-cost trade-off for a
  complementary retrieval method — lexical/exact-term matches (rare terms,
  codes, numbers) dense embeddings can under-weight. Influences **lexical
  recall**. This complementarity is the entire reason hybrid retrieval
  exists; sizing this stage independently of `dense_candidate_k` is what
  lets each method's own recall characteristics be tuned on their own
  terms.
- **`fusion_candidate_k`** determines how many unique, fused candidates
  proceed to reranking — trading reranking cost against fusion-stage
  recall loss, since reranking (below) is the most expensive per-candidate
  operation in the pipeline before generation.
- **`reranker_candidate_k`** determines how many reranked candidates
  continue to threshold evaluation — trading prompt-context economics
  against precision, independent of whether any of them will actually
  clear the quality bar next.
- **`evidence_threshold`** (`EvidenceThresholdPolicy`, below) governs
  evidence **acceptance**, not candidate count — it asks "is this candidate
  good enough to trust as evidence," never "how many candidates do we
  have." This is a categorically different kind of parameter from the four
  `_candidate_k` values above, which is exactly why threshold and count
  configuration are versioned separately below.
- **`final_evidence_k`** governs prompt/context size and downstream LLM
  generation cost — trading generation-prompt economics and attention
  dilution against evidence completeness, a downstream generation-
  quality/cost concern adjacent to, but distinct from, every retrieval-
  quality optimisation above it.

**These six parameters are architecturally independent, and no wording in
this document should be read as implying otherwise.** `fusion_candidate_k`
and `reranker_candidate_k` sharing the same initial experimental value (15)
is a coincidence of the starting configuration, not a structural
relationship — nothing about this architecture requires them to move
together, and future calibration against ADR-0019's evaluation harness may
legitimately settle on configurations such as `40 / 40 / 30 / 15 / 0.82 /
5` or `60 / 60 / 25 / 10 / 0.86 / 4` (dense/sparse/fusion/reranker/
threshold/final) without implying any architectural inconsistency. The
architecture is deliberately built to encourage independent calibration of
each parameter through ADR-0019's evaluation framework, never a single
"magic" `candidate_k` tuned as one number.

All six are recorded as one independently-versioned `HybridRetrievalConfiguration`
artefact (illustrative name), never hard-coded constants scattered through
implementation code, and every value in it is subject to `R16-S08`'s
evaluation-driven calibration against ADR-0019's harness — this document
fixes the *shape* of that configuration and the reasoning each field
serves, not any production number.

**Independence does not mean every combination of values is valid — a
downstream stage can never be configured to manufacture candidates an
upstream stage did not actually produce.** The six parameters are not
semantically coupled (above), but they are **structurally bounded** by the
pipeline's own data flow, and configuration validation rejects a
structurally impossible value wherever it can be known statically:

- `dense_candidate_k` and `sparse_candidate_k` each independently bound
  only their own retrieval branch — neither constrains the other.
- `fusion_candidate_k` cannot exceed the number of unique, deduplicated
  fused candidates actually produced by combining that request's dense and
  sparse result sets — it can be smaller (narrowing further), never
  larger.
- `reranker_candidate_k` cannot exceed the number of candidates
  `fusion_candidate_k` actually supplied to reranking that request.
- `final_evidence_k` cannot exceed the number of threshold-qualified
  candidates `EvidenceThresholdPolicy` actually accepted as evidence.

These are structural (data-flow) bounds, not optimisation guidance —
distinct in kind from the independent optimisation reasoning above. A
configuration where, for example, `reranker_candidate_k` is set larger
than `fusion_candidate_k` is rejected outright at configuration-validation
time, wherever the relationship is statically knowable from the
configuration values alone. Where a bound can only be evaluated at request
time (an upstream stage returning genuinely fewer candidates than its own
`_candidate_k` allows for, because fewer existed), the downstream stage
ordinarily bounds itself to however many candidates actually exist —
exactly the normal, expected meaning of "top-K when fewer than K items are
available," not a failure to hide — but a configuration is never permitted
to be interpreted as license to invent, duplicate, or otherwise manufacture
candidates that were never actually retrieved, fused, or reranked.

### `Reranker`: a provider-neutral boundary, Voyage `rerank-2.5` as V1

A provider-neutral `Reranker` abstraction, mirroring `Embedder`'s exact
shape and disciplines (ADR-0013): `Reranker.rerank(request) ->
RerankResult`, never a direct Voyage SDK call from anywhere outside one
isolated adapter. The request carries the original query text and the
candidate set (chunk text, hydrated by Laravel — see below); the result
carries, per candidate, a reranker score, rank, and the same chunk/
`Document`/`DocumentFamily`/version identity already threaded through
`RetrievalResult`. Reranker lineage (provider, model, adapter version) is
recorded exactly as embedding-profile lineage already is.

Voyage `rerank-2.5` is the V1 implementation, evaluated and accepted on
the same basis ADR-0013 already accepted Voyage for dense embedding —
quality-first, with the same disciplines: **no silent truncation** — an
over-length candidate or query is a typed failure, never a silently
truncated comparison, mirroring ADR-0013's identical rule for embedding
input; a **typed failure taxonomy** (invalid input, input too large,
authentication/configuration failure, rate limiting, timeout, temporary
unavailability, malformed response), with only genuinely transient
categories retried, exactly as ADR-0013 already establishes; a
**deterministic fake** `Reranker` for ordinary tests, requiring no live
credentials or network access; **opt-in, credential-dependent, isolated
live-provider tests only**, excluded from ordinary CI, the same posture
ADR-0013 requires for Voyage and ADR-0020 now requires for Ragas; and an
**injected, configuration-owned client** — the Voyage reranking client is
never instantiated implicitly inside the adapter, the identical discipline
ADR-0020 just established for `RagasEvaluator`, applied here for
consistency across every model-calling adapter this platform now has.
**Python owns reranking; Laravel owns canonical text** — the Reranker
never reads PostgreSQL, extending the exact ownership boundary ADR-0018
already fixed for the `Retriever`, to this new stage. **Python owns
computing and returning reranker scores; it does not decide whether those
scores are "good enough"** — that is `EvidenceThresholdPolicy`'s decision,
owned by Laravel, below. A reranker score is evidence-policy *input*, never
an authoritative application-level acceptance decision made inside Python.

**Strict provider-response validation, before any result is returned as an
application-owned `RerankResult`.** The Voyage adapter validates, at
minimum: every returned result maps to a candidate identity actually
present in the request — no unknown or duplicate candidate identity is
accepted; ranks are bounded (within the requested candidate count) and
structurally valid (1-based, no gaps the provider's own contract does not
explain); every score value is finite (never `NaN`/`Infinity` silently
entering the pipeline, the same discipline ADR-0013 already requires of
Voyage embedding responses); required provider/model lineage is present
on the response; the response's candidate count obeys the requested bound;
and, where `truncation=false` was required, that no truncation actually
occurred. A malformed, partial, or internally-inconsistent provider
response produces a typed reranker/provider failure — it is never coerced
into a best-effort `RerankResult` and passed onward as though it were
trustworthy.

### Laravel hydration, extended: two round trips, not one

ADR-0018 already established that Python never reads PostgreSQL directly;
Laravel batch-hydrates chunk text and rechecks eligibility after the
`Retriever` returns candidate identities. Reranking needs actual chunk
*text* to score relevance (Voyage's reranker takes query/passage text
pairs), which means this hydration step now has to happen **before**
reranking, not only after it:

```text
Python Retriever -> candidate identities (post-fusion)
  -> Laravel: canonical text hydration + eligibility recheck
  -> Python Reranker (given hydrated text) -> reranked identities + scores
  -> Laravel: final eligibility recheck
  -> LLM (Phase 17)
```

Two Laravel round trips, not one: the first hydrates and rechecks the
fused candidate set before it is sent to Python for reranking (Python
receives text, but only text Laravel has already confirmed is still
eligible); the second rechecks the narrower, reranked set one final time
before it becomes evidence, closing the same narrow staleness window
ADR-0018 already closes for the dense-only path, now applied a second time
around the reranking round trip. The Reranker itself still never touches
PostgreSQL — it receives text Laravel already hydrated and sent to it, the
same "Python receives what it needs, nothing it could look up itself"
discipline ADR-0013 already applies to embedding calls.

**Why this remains preferable to storing canonical text inside Qdrant.**
ADR-0014 already rejected a fatter Qdrant payload including chunk text,
on grounds of duplication and drift; reranking adds a second, sharper
reason. Eligibility is not static — a chunk's governance or temporal
state (ADR-0017) can change between when it was indexed and when it is
reranked. Text stored in Qdrant would have no way to reflect that; text
hydrated fresh from PostgreSQL, immediately before and after the
reranking call, is automatically the current authoritative version, and
the accompanying recheck is what actually enforces it. Storing text in
Qdrant wouldn't just duplicate content — it would let the reranker score
content whose eligibility had already lapsed, with nothing to catch it.

**Laravel additionally validates the Reranker's response before acting on
it** — the same "never trust a response merely because it returned the
expected shape" discipline this platform applies at every boundary a
provider or another service crosses: candidate identities in the
`RerankResult` are checked to be a subset of what Laravel itself sent for
reranking, never a set the response could use to introduce evidence
outside the already-eligible candidate set; the reported reranker
provider/model/adapter lineage is checked against the
`EvidenceThresholdPolicy` currently bound to it (below) before the
returned scores are used for anything; and no response, however it is
shaped, can cause Laravel to treat a candidate as evidence that was not
already part of the eligibility-rechecked set it sent to Python.

### The `retrieval.rerank` `rc1` purpose: the same security and privacy discipline, extended

Reranking is a second, distinct Laravel-to-Python synchronous call, and it
inherits ADR-0018's `rc1` protocol discipline in full — it does not get a
lighter-weight or bespoke protocol merely because it is the second call in
one logical request. A new purpose, `retrieval.rerank`, is added to the
existing `rc1` protocol, exactly as ADR-0018 already anticipated (*"a
reranking call once `R16-S07` is designed"*) and exactly as `rc1`'s own
purpose-scoping already guarantees: **a signature valid for
`retrieval.search` never verifies for `retrieval.rerank`, and vice versa**
— no new signature format is required, the same six-field-plus-`request_id`
shape ADR-0018 already established carries the new purpose value without
modification. `retrieval.rerank` requires, identically to every other
`rc1` purpose: mandatory authenticated TLS; purpose-scoped HMAC signing;
explicit request/contract versioning; mandatory `workspace_id` binding;
a signed, unique `request_id` and the same bounded replay-suppression
cache ADR-0018 already requires; freshness validation against the same
clock-skew window; method/path/body-digest binding; Key ID/key-ring
rotation; and OpenTelemetry trace/correlation propagation across the call.

**Privacy, under ADR-0012, applied explicitly to this call because it
genuinely is more sensitive than `retrieval.search`'s response.**
`retrieval.rerank`'s request body necessarily carries canonical candidate
*text* — this is the one call in the pipeline that sends real document
content across the Laravel-to-Python boundary, not just identifiers and
scores. Raw question text and chunk text are never logged by default;
HMAC signatures, secrets, and provider request/response bodies are never
logged; telemetry for this call is limited to identities, counts, sizes,
controlled outcome classifications, and lineage — exactly ADR-0012's
existing allowlist-first posture, restated here because this specific
call's payload sensitivity makes it the one place in the retrieval
pipeline where getting this wrong would actually leak content, not merely
metadata. Voyage's own provider credentials remain a Python-side secret,
injected into the `Reranker` adapter's configuration; Laravel never holds
or transmits them.

### `EvidenceThresholdPolicy`: the calibrated-acceptance policy ADR-0018 deferred here — owned by Laravel, applied to Python's scores

A versioned `EvidenceThresholdPolicy` artefact — distinct from, and
composed with, the candidate-count configuration above, never conflated
with it. It defines: the reranker-score threshold below which a candidate
is not trusted as evidence; how abstention is triggered when nothing
clears that bar; and how `COMPARE` completeness interacts with it. The
current ~0.80 working value is recorded as an **experimental starting
point only** — this document does not invent the production threshold;
calibrating it is explicitly `R16-S08`'s job, using ADR-0019's evaluation
harness against the corpus's `EvidenceUnit` ground truth, exactly as
`R16-S08` also calibrates the candidate-pipeline configuration above.

**Ownership is pinned explicitly, so it is never ambiguous which service
decides what.** Python owns dense retrieval, sparse retrieval, fusion, and
reranking, and returns their scores, ranks, and provider lineage as
information — Python never independently decides that evidence is "good
enough." **Laravel owns**: `EvidenceThresholdPolicy`'s persistence and
resolution; validating that the reranker identity/configuration a response
actually used matches the policy it is being evaluated against (below);
applying the threshold itself; enforcing `final_evidence_k`; selecting the
authoritative retrieval outcome, including `INSUFFICIENT_EVIDENCE` and a
post-threshold `COMPARISON_SCOPE_INCOMPLETE`; the final eligibility
recheck; and assembling the evidence set that actually proceeds toward
generation. A reranker score is `EvidenceThresholdPolicy`'s *input*; the
acceptance decision itself is always Laravel's, never Python's, exactly
mirroring the ownership split ADR-0018 already fixed between
`EligibilityResolver` (Laravel, deterministic) and the `Retriever`
(Python, never authorises).

**A threshold is valid only for the exact configuration it was calibrated
against, and this binding is checked, not assumed.** `EvidenceThresholdPolicy`
is immutably bound, at minimum, to: the reranker provider/model identity
and adapter version it was calibrated against; the sparse-profile
fingerprint, where sparse retrieval was part of the calibrated pipeline;
the dense embedding-profile fingerprint; the fusion strategy/version and
its configuration (including `rrf_k`); the relevant candidate-stage
configuration (`dense_candidate_k`, `sparse_candidate_k`,
`fusion_candidate_k`, `reranker_candidate_k`); the calibration corpus
version and its content digest (ADR-0020); the threshold value itself; and
`final_evidence_k`. Before applying a policy, Laravel rejects — rather than
silently applying — a policy whose binding does not match the actual
reranker/retrieval lineage a response reports; a threshold calibrated
against one reranker model, one fusion configuration, or one candidate-
pipeline shape is never silently reused against a materially different
one. This is the same "a threshold is meaningless outside the exact
context it was measured in" discipline ADR-0019/ADR-0020 already apply to
corpus and policy content digests, applied here to the evidence-threshold
policy specifically.

**Calibration and acceptance evaluation are separate corpus splits, and
this document requires that separation without inventing its numbers.**
`R16-S08` selects `evidence_threshold` (and, where practical, candidate/
fusion configuration being claimed as an improvement) using a
calibration/tuning split of ADR-0019's repository-owned corpus; it then
assesses the *selected* configuration against a separate, held-out
acceptance split that played no part in selecting it. The held-out
acceptance cases must never influence threshold or configuration
selection — a configuration tuned and then declared successful on the same
cases it was tuned against provides no genuine evidence of generalisation,
only of having fit that specific sample. ADR-0019's repository-owned
evaluation model remains fully authoritative for both splits and every
metric computed against them; this document adds the calibration/held-out
separation as a requirement of *how* that model is used for threshold and
configuration selection, not a new evaluation architecture. The exact
split proportions, sampling method, and re-splitting cadence are not fixed
here — `R16-S08` implementation work, informed by whatever corpus size and
case-family balance actually exists once the corpus is built.

**This is the calibrated acceptance policy ADR-0018 explicitly deferred to
this session** (*"deferred to the hybrid retrieval/reranking and
evaluation architecture (`R16-S05`, `R16-S07`)"*), and it requires one new,
narrow addition to ADR-0018's outcome taxonomy: **`INSUFFICIENT_EVIDENCE`**
— candidates existed, were fused and reranked, but none cleared
`EvidenceThresholdPolicy`'s calibrated bar. This is distinct from
`NO_RETRIEVAL_CANDIDATES` (zero candidates returned at all, a purely
count-based fact) and from `EVIDENCE_FOUND` (at least one candidate
cleared the bar): the taxonomy needs to distinguish "nothing came back"
from "things came back but none were good enough to trust," and ADR-0018's
existing values cannot express the second case, precisely because
ADR-0018 deliberately had no calibrated policy yet to define it against.
`INSUFFICIENT_EVIDENCE` is Laravel's determination, made by applying
`EvidenceThresholdPolicy` to Python's returned scores, never Python's own
classification. For `COMPARE`, a side whose post-threshold evidence is
empty is treated exactly as ADR-0018 already treats an unresolvable side —
the whole request produces `COMPARISON_SCOPE_INCOMPLETE`, reusing that
existing outcome rather than inventing a `COMPARE`-specific variant of the
new one.

### Cost and operational behaviour, without pricing

Each stage has a materially different cost/latency shape, relevant to
sizing decisions above even though this document fixes no price: dense
retrieval is an ANN search, sub-linear and cheap per query; sparse
retrieval, self-hosted via FastEmbed, is local CPU/ONNX inference with no
external call at all; fusion is in-process arithmetic over already-
returned candidates, effectively free; reranking is a paid, per-candidate-
pair external API call — the single most expensive per-item operation in
the retrieval path before generation itself; and generation cost/latency
scales with prompt size, which scales with final evidence count. Narrowing
the candidate set at every stage before reranking and before generation
therefore reduces both latency and cost at the two most expensive points
in the pipeline: fewer candidates into a per-item-priced reranker call
costs and takes less; fewer, higher-quality chunks into generation means a
shorter, cheaper, faster prompt — this is the direct operational
justification for treating each narrowing stage as a real optimisation
problem rather than retrieving as much as possible at every step.

### Rollout: reusing ADR-0014's generation model; no silent downgrade

Enabling hybrid retrieval is a coordinated corpus rebuild (above), rolled
out per workspace exactly as ADR-0014's existing model already supports:
sparse-profile completeness verified against the same "expected identity
set, not count alone" discipline ADR-0014 already requires; the new
workspace corpus generation activated only after both its dense and sparse
axes verify complete.

**Rollback means configuration rollback — defined precisely enough to
actually respect ADR-0014's generation lifecycle, not merely gesture at
it.** ADR-0014's illustrative lifecycle (`BUILDING → VERIFYING → ACTIVE →
SUPERSEDED → RETIRED`) and its invariant that *"at most one workspace
corpus generation is `ACTIVE` for a given workspace at any time"* mean a
rollback cannot simply "repoint" to a generation whose persisted state is
already `SUPERSEDED` — that would either leave two generations claiming
`ACTIVE` simultaneously or require silently mutating a generation's
lifecycle state outside its own state machine, either of which this
document rejects. Rollback is instead its own explicit, controlled
operation, supporting the transition `SUPERSEDED → ACTIVE`, and only
through that operation:

1. verify the target (previously-superseded) generation is still within
   its retention/confidence window — that is, it has not yet reached
   `RETIRED` and had its points purged; a generation already `RETIRED` is
   not a valid rollback target, and reinstating it requires a fresh
   rebuild, not a rollback;
2. re-verify the target generation's completeness and compatibility
   (unchanged since its own original activation, but checked again rather
   than assumed still true);
3. **atomically** — one transaction — demote the currently `ACTIVE`
   generation back to `SUPERSEDED` and promote the target generation to
   `ACTIVE`, so the "at most one `ACTIVE` generation" invariant holds
   continuously, with no window in which zero or two generations are
   `ACTIVE`;
4. record the rollback as its own audited event (generation demoted,
   generation promoted, actor, timestamp, reason), the same business-audit
   treatment ADR-0016 already gives publication and completion events —
   not telemetry alone.

There is still exactly one `ACTIVE` workspace corpus generation immediately
after rollback, exactly as before it. Rollback never mutates a generation's
own identifier, never bypasses the lifecycle state machine by writing to
an `is_active`-style flag disconnected from it, and is never achieved by
anything other than this explicit, atomic, audited operation.

**Rollback is a deliberate, out-of-request configuration operation — never
a request-time, undeclared fallback to dense-only behaviour.** If sparse
retrieval or reranking fails mid-request, the request returns the
appropriate controlled failure (`RETRIEVAL_FAILED`, ADR-0018's existing
outcome) — it never silently proceeds as dense-only and presents the
result as though it were the full hybrid pipeline's output. Silently
downgrading architecture mid-request would misrepresent what evidence was
actually considered, and could silently and invisibly degrade production
quality with no signal to anyone that it happened; failing closed and
visibly is the same discipline this platform has applied at every other
boundary where a partial result could otherwise masquerade as a complete
one (ADR-0014's, ADR-0015's, and ADR-0016's completeness-verification
requirements are the direct precedent).

### Evaluation integration: populating seams ADR-0019 already named

Every configurable value this document introduces — dense candidate count,
sparse candidate count, fusion size, reranked count, the evidence
threshold, and final evidence count — is recorded as experiment
configuration and lineage, independently measurable through ADR-0019's
already-layered evaluation model, populating exactly the fields ADR-0019's
"Experiment lineage" section already named and left empty: sparse-profile
fingerprint, fusion algorithm/configuration, reranker provider/model, and
threshold-policy version. This lets `R16-S08` measure each stage's
contribution independently — dense-only recall, dense+sparse fused recall,
post-rerank precision, post-threshold precision/abstention rate — rather
than only a single end-to-end number, so the architecture supports
genuine optimisation rather than assuming any one configuration is
correct in advance.

### Workspace governance: not designed, not blocked

Billing, commercial pricing, and workspace-level rate/usage/budget/AI-
enablement controls are not designed here. Nothing in this pipeline
prevents Laravel from layering such controls in front of it later — the
natural integration point is exactly where `AuthorisedKnowledgeScope` is
already resolved, the first gate in the pipeline, before `RetrievalPlanner`
is ever called, consistent with the existing "narrow only, never widen"
discipline this document inherits from ADR-0018.

## Alternatives considered

### BM25 instead of a learned sparse encoder

Rejected — see "Why a learned sparse encoder, not BM25" above. Not a
quality preference: BM25's corpus-statistical term weights are
structurally incompatible with this platform's shared-collection topology
without either compromising tenant isolation (shared statistics) or
breaking the cheap-incremental-ingestion invariant ADR-0014 already fixed
(per-workspace statistics, recomputed on every ingestion).

### Qdrant-native fusion instead of application-owned `FusionStrategy`

Rejected — see "`FusionStrategy`" above. Would embed a business decision
inside provider-specific query syntax, break the deterministic-fake
testing pattern this platform applies everywhere else, and discard the
per-stage candidate visibility ADR-0019's layered evaluation model
requires.

### A separate Qdrant collection for sparse vectors

Rejected. Would reintroduce the exact "collection growth proportional to
reindex events" and "physical partition tied to a lifecycle event, not a
Qdrant-forced boundary" problems ADR-0014 already rejected for collection-
per-workspace and collection-per-corpus-generation, for a capability
(named sparse vectors on existing points) Qdrant already supports without
a new collection.

### Storing canonical chunk text in Qdrant so the Reranker can read it directly

Considered, since it would remove one hydration round trip. Rejected — see
"Why this remains preferable to storing canonical text inside Qdrant"
above. Beyond ADR-0014's existing duplication/drift objection, it would
let the reranker score content whose eligibility had changed since
indexing, with no recheck to catch it — a correctness regression, not
merely an efficiency trade-off.

### Silent request-time fallback to dense-only retrieval on sparse/reranker failure

Rejected. Would misrepresent what evidence was actually considered for a
given answer, and could silently degrade production quality with no
operational signal. A controlled `RETRIEVAL_FAILED` outcome is the correct
failure mode, matching this platform's existing fail-closed, no-silent-
substitution discipline.

### Hard-coding the candidate-count progression and threshold as architectural constants

Considered, since Codex's recommended values are concrete and immediately
usable. Rejected: none of them have been calibrated against ADR-0019's
evaluation harness yet, and this platform has consistently declined to
invent numerical tolerances before measured evidence justifies them
(ADR-0018's and ADR-0019's identical refusal to invent a release threshold
before a first baseline exists). Recorded instead as the initial
experimental configuration for `R16-S08` to calibrate.

### Folding `EvidenceThresholdPolicy` into the `Reranker` contract

Considered, since both concern reranker scores. Rejected: the `Reranker`
returns scores; deciding what score counts as "good enough" is a policy
decision independent of which provider computed the score, and keeping it
a separate, independently-versioned artefact means the threshold can be
recalibrated without touching the reranker adapter, and a future
reranker-provider change does not implicitly change acceptance policy as
a side effect.

### A single global `candidate_k` governing every stage

Rejected — see "The candidate pipeline" above. Each stage answers a
different optimisation question (recall, cost-to-rerank, precision,
prompt economics); one shared number would force stages with genuinely
different concerns to move together for no architectural reason.

### Leaving the six candidate-stage parameters entirely unbounded relative to each other

Considered, as the purest expression of "independent configuration."
Rejected: independence of *optimisation purpose* does not mean every
numeric combination is meaningful — a `reranker_candidate_k` larger than
`fusion_candidate_k` cannot be satisfied by any real data, and treating it
as a valid configuration would defer a knowable configuration error to
confusing runtime behaviour instead of a validation-time rejection. The
six parameters remain semantically uncoupled while still being subject to
the pipeline's own structural (data-flow) bounds — the two are different
kinds of constraint, not a contradiction.

### Depending on Qdrant's or the provider's own return order to break RRF ties

Considered, as the simplest possible implementation. Rejected: provider
return order is not a documented, guaranteed contract, and depending on it
would make fused ranking non-reproducible across otherwise-identical runs
— directly undermining the "identical ranked inputs always produce
identical fused ordering" requirement this document fixes, and ADR-0019's
deterministic-metric-reproducibility requirement generally.

### Claiming a hybrid-enabled generation's dense vectors are "reused unchanged" without addressing point-identity change

This was the first full draft's wording, and is corrected here — see
"Sparse storage" above. ADR-0014's point identity is derived in part from
the workspace-corpus-generation identity itself, so a new generation
necessarily has newly-derived point identities regardless of whether the
underlying dense vector *value* is recomputed or re-materialised; "reused
unchanged" without that clarification would have implied the old points
simply continue to exist under the new generation, which is not how
ADR-0014's identity derivation works.

### Claiming hybrid retrieval requires no relational migration

This was an implication of the first full draft's phrasing, corrected
here — see "Sparse storage" above. Sparse-profile and sparse-space-
generation identity and lineage must be persisted in PostgreSQL for the
same reason dense embedding-space and workspace-corpus generation identity
already are; claiming otherwise would have been dishonest about `R16-S08`'s
actual scope. This document fixes the relational concepts a migration must
satisfy, not the migration itself.

### Letting Python decide evidence is "good enough"

Considered, since Python already computes the reranker score the decision
would be based on. Rejected: this would move an authoritative application
decision into the AI service, contradicting ADR-0002's and ADR-0018's
foundational position that Laravel is the security/authority boundary and
Python's role is limited to computing and returning information, never
independently authorising or accepting evidence on the platform's behalf.

### Silently reusing an `EvidenceThresholdPolicy` regardless of which reranker/retrieval configuration actually produced the scores it is applied to

Considered, since re-checking the binding on every application adds real
work. Rejected: a threshold's meaning is entirely a function of the
configuration it was calibrated against; applying it to a materially
different reranker, fusion, or candidate-pipeline configuration would
silently misrepresent a number as meaningful when it has never actually
been measured against what it is now being used to judge.

### Selecting `evidence_threshold` and declaring it successful using the same evaluation cases

Considered, as the simpler evaluation workflow. Rejected: a threshold
tuned and validated against the same cases provides no evidence of
generalising beyond the specific sample it was fitted to — the calibration/
held-out split exists specifically to prevent this document's threshold-
selection process from quietly validating itself.

### Solving rollback by mutating a generation's identifier or bypassing its lifecycle state machine

Considered, as a shortcut to avoid defining an explicit rollback operation.
Rejected — see "Rollout" above. Either approach would violate ADR-0014's
"at most one `ACTIVE` generation" invariant at some point during the
transition, or would make a generation's recorded lifecycle state
untrustworthy as a source of truth; an explicit, atomic, audited
`SUPERSEDED -> ACTIVE` operation preserves both.

## Consequences

### Positive

- Hybrid retrieval is measurable end-to-end and stage-by-stage through
  ADR-0019's existing harness from the moment it exists, never requiring a
  parallel evaluation mechanism.
- SPLADE++'s self-hosted, per-chunk-deterministic computation keeps
  ordinary ingestion cheap and tenant-isolated, exactly preserving two
  invariants ADR-0006 and ADR-0014 already fixed as load-bearing.
- Sparse storage and its generation model reuse ADR-0014's existing
  activation and verification machinery exactly, extended to a second
  compatibility axis; only rollback required a new, explicit operation,
  and it is now defined precisely rather than left to be improvised.
- The candidate pipeline's six independently-versioned stages let
  `R16-S08` optimise recall, cost, and precision separately, rather than
  discovering after the fact that one blended number hid a bad trade-off
  — now with explicit structural bounds preventing a downstream stage from
  being misconfigured to expect candidates no upstream stage produced.
- `EvidenceThresholdPolicy` finally lets the platform truthfully
  distinguish "no good evidence" from "no evidence at all," closing a gap
  ADR-0018 explicitly and knowingly left open until this session, with an
  explicit ownership split that keeps the acceptance decision itself
  authoritative and Laravel-owned regardless of which service computed the
  underlying score.
- `Reranker` and `SparseEncoder` both reuse patterns (injected model
  ownership, deterministic fakes, opt-in live tests, typed failures, strict
  response validation) this platform has now applied consistently across
  `Embedder`, `RagasEvaluator`, and these two new adapters — no new testing
  or provider-isolation philosophy is introduced.
- Fail-closed behaviour on sparse/reranker failure means a degraded
  pipeline is always visible as `RETRIEVAL_FAILED`, never mistaken for a
  complete result.
- RRF's fully specified tie-break means fused ranking is reproducible
  across identical inputs, closing a gap that would otherwise have made
  ADR-0019's deterministic-reproducibility requirement unsatisfiable for
  the fusion stage specifically.
- The calibration/held-out split gives `R16-S08`'s eventual threshold and
  configuration selection genuine evidentiary weight, rather than a number
  that only ever proved it could fit the cases used to choose it.
- `retrieval.rerank`'s explicit `rc1` security definition means the second
  Laravel-to-Python call is exactly as rigorously specified as the first,
  rather than an implicit "presumably the same rules apply."

### Negative

- Two new provider-neutral abstractions (`SparseEncoder`, `Reranker`) plus
  `FusionStrategy` and `EvidenceThresholdPolicy` are substantial new
  implementation surface for `R16-S08`, beyond the dense-only pipeline
  `R16-S06` already built.
- A second Laravel↔Python round trip (hydrate-then-rerank, recheck-after)
  adds real latency to every hybrid-enabled request, on top of the
  planning and dense/sparse retrieval calls already required.
- Sparse-profile rollout is a real, non-trivial per-workspace coordinated
  rebuild, not a configuration flag — every workspace must be migrated
  through ADR-0014's existing (non-trivial) generation-rebuild machinery
  before it can use hybrid retrieval at all.
- Reranking introduces the pipeline's first paid, per-candidate-pair
  external cost beyond dense embedding — a new, ongoing operational
  expense whose actual magnitude is unknown until `R16-S08` measures it.
- `INSUFFICIENT_EVIDENCE` is one more outcome every downstream consumer
  (eventually, generation) must handle distinctly from `NO_RETRIEVAL_CANDIDATES`
  and `NO_ELIGIBLE_EVIDENCE` — real, if narrow, additional complexity in
  outcome handling.
- The candidate pipeline's six independently-versioned parameters are more
  configuration-management surface than one global number would have
  been — accepted because collapsing them would hide which stage actually
  changed when a regression appears.
- Sparse-profile and sparse-space-generation identity and lineage require
  real Laravel migrations, models, and relationships — this document does
  not, and cannot honestly, claim hybrid retrieval is a purely `apps/ai`
  addition.
- A hybrid-enabled generation rebuild must produce a complete new point set
  under newly-derived point identities regardless of strategy — recomputing
  dense vectors costs a full re-embedding pass; re-materialising them
  avoids that cost but adds its own implementation and verification
  surface to get right.
- `EvidenceThresholdPolicy`'s identity binding means every application of
  a threshold now requires a lineage-match check before use, and a policy
  becomes unusable the moment its bound configuration changes anywhere
  upstream — real, ongoing operational discipline, not a one-time cost.
- Rollback is now a defined, but non-trivial, atomic multi-step operation
  (verify retention window, re-verify completeness, demote-and-promote,
  audit) rather than a simple pointer flip — more implementation and
  testing surface than an unspecified "repoint" would have suggested.
- The calibration/held-out split means `R16-S08` needs a larger, more
  carefully partitioned corpus than a single-split evaluation would have
  required, and re-splitting discipline as the corpus grows over time.
- Strict reranker response validation, on both the Python and Laravel
  sides, and `SparseEncoder`'s input-length validation, are additional
  defensive-programming surface beyond the "happy path" adapter
  implementations alone would have needed.

## Architectural invariants

- No stage in the hybrid pipeline may widen the eligible evidence universe
  `EligibilityResolver` established; every stage narrows or leaves it
  unchanged.
- `SparseEncoder` is provider-neutral; no application code outside one
  isolated adapter depends on FastEmbed- or SPLADE-specific types.
  `SparseEmbeddingProfile` is immutable and fingerprinted exactly as
  `EmbeddingProfile` already is.
- Sparse vectors are stored as an additional named vector on existing
  Qdrant points; no separate collection, no duplicated canonical text.
- A sparse-space generation is a platform-scoped compatibility boundary,
  symmetric to embedding-space generation; a hybrid-enabled workspace
  corpus generation records both its embedding-space and sparse-space
  generation references explicitly, never inferred by Python.
- Sparse retrieval is never computed against collection-wide or globally-
  shared term statistics; a chunk's sparse vector is a deterministic
  function of its own text alone.
- `SparseEncoder` validates input length against `SparseEmbeddingProfile`'s
  recorded bound before encoding; an over-length input produces a typed
  failure, never a silently truncated sparse vector presented as complete.
- A hybrid-enabled workspace corpus generation rebuild must produce a
  complete new point set under newly-derived point identities (dense
  vector, sparse vector, payload, and generation/profile lineage for every
  expected point), verified complete against both the dense and sparse
  axes before activation; adding sparse configuration never implicitly
  populates any existing or newly-created point.
- Sparse-profile and sparse-space-generation identity and lineage are
  persisted in PostgreSQL and require real Laravel migrations, models, and
  relationships; this is never claimed to be unnecessary.
- `FusionStrategy` is application-owned; no fusion logic is expressed as
  Qdrant-native query syntax. RRF is the V1 implementation; future
  algorithms are additive implementations behind the same contract, each
  required to define its own equally deterministic tie-break behaviour.
- Fusion uses 1-based ranks, a versioned `rrf_k`, preserves per-list rank
  and original score for every candidate, gives each candidate at most one
  contribution per retrieval list, deduplicates by canonical chunk
  identity, breaks ties deterministically (fused score descending, then
  best source rank ascending, then canonical chunk identity ascending —
  never provider return order), and fuses `COMPARE`'s `PRIMARY`/
  `COMPARISON` sides independently, never merged.
- `dense_candidate_k`, `sparse_candidate_k`, `fusion_candidate_k`,
  `reranker_candidate_k`, `evidence_threshold`, and `final_evidence_k` are
  each independently versioned configuration; none is hard-coded as an
  architectural constant, and no numerical production value is fixed by
  this document. They are semantically independent but structurally
  bounded by pipeline data flow: `fusion_candidate_k` cannot exceed the
  unique fused candidates actually produced, `reranker_candidate_k` cannot
  exceed what fusion actually supplied, and `final_evidence_k` cannot
  exceed the threshold-qualified evidence actually available; a
  statically-knowable violation of these bounds is rejected at
  configuration-validation time.
- `Reranker` is provider-neutral; no application code outside one isolated
  adapter depends on Voyage-specific types. The reranker client is
  injected and configuration-owned, never instantiated implicitly.
  Ordinary tests require no credentials or network calls; live-provider
  tests are explicit, opt-in, and excluded from ordinary CI.
- The Voyage adapter validates every `RerankResult` before returning it —
  candidate identities map to the request, ranks and scores are
  structurally valid and finite, required lineage is present, and
  requested truncation behaviour was honoured — producing a typed failure
  for any malformed or inconsistent response rather than passing it
  onward. Laravel independently validates that reranked candidate
  identities are a subset of what it sent, and that reported reranker
  lineage matches the bound `EvidenceThresholdPolicy`, before using either.
- The Reranker never reads PostgreSQL; Laravel hydrates canonical text and
  rechecks eligibility both before sending candidates to the Reranker and
  after receiving reranked results back, before any candidate becomes
  final evidence.
- `retrieval.rerank` is a new `rc1` purpose, inheriting every existing
  `rc1` requirement (TLS, purpose-scoped HMAC, versioning, workspace
  binding, signed `request_id`/replay suppression, freshness, method/path/
  body-digest binding, key rotation, trace propagation) in full; a
  signature valid for `retrieval.search` never verifies for
  `retrieval.rerank`. Its request carries canonical chunk text, so ADR-0012's
  privacy allowlist is applied to it explicitly, not assumed.
- Python owns computing dense retrieval, sparse retrieval, fusion, and
  reranking, and returns scores/ranks/lineage as information; it never
  independently decides evidence is "good enough." `EvidenceThresholdPolicy`
  persistence, resolution, application, `final_evidence_k` enforcement,
  authoritative outcome selection (including `INSUFFICIENT_EVIDENCE` and
  post-threshold `COMPARISON_SCOPE_INCOMPLETE`), the final eligibility
  recheck, and the evidence set passed toward generation are all Laravel's.
- `EvidenceThresholdPolicy` is versioned, distinct from candidate-count
  configuration, and immutably bound to the exact reranker, sparse-profile,
  embedding-profile, fusion, candidate-configuration, and calibration-
  corpus lineage it was calibrated against; Laravel rejects applying a
  policy to reranker/retrieval lineage that does not match its binding. No
  production threshold value is fixed by this document, deferred to
  `R16-S08`'s evaluation-driven calibration.
- `R16-S08` selects `evidence_threshold` and any configuration claimed as
  an improvement using a calibration/tuning split of ADR-0019's corpus,
  and assesses the selected configuration against a separate held-out
  acceptance split that never influenced selection.
- `INSUFFICIENT_EVIDENCE` extends ADR-0018's outcome taxonomy for the case
  where candidates existed and were reranked but none cleared
  `EvidenceThresholdPolicy`; it is distinct from `NO_RETRIEVAL_CANDIDATES`,
  and is Laravel's determination, never Python's. A `COMPARE` side left
  empty after thresholding produces `COMPARISON_SCOPE_INCOMPLETE`, reusing
  the existing outcome.
- A sparse-retrieval or reranking failure mid-request produces
  `RETRIEVAL_FAILED`; the pipeline never silently falls back to a narrower
  configuration and presents it as a complete result.
- Rollback is the explicit, atomic, audited `SUPERSEDED -> ACTIVE`
  operation defined in "Rollout" above (retention-window check,
  completeness re-verification, atomic demote-and-promote, audit record);
  it is never a direct pointer or identifier mutation, and exactly one
  workspace corpus generation is `ACTIVE` at any moment before and after
  it. Rollback is always a deliberate, out-of-request configuration
  operation, never triggered by, or substituting for, a request-time
  failure.
- Every configurable stage's value is recorded in experiment lineage and
  independently measurable through ADR-0019's evaluation harness.

## Scope boundaries

**`R16-S08`'s implementation boundary is broad, and this document says so
explicitly rather than leaving it to be inferred from an out-of-date
stub.** Everything this ADR decides has real implementation weight across
both services and the data they own, not only `apps/ai`. `R16-S08`
legitimately spans: `apps/ai` (`SparseEncoder`, `FusionStrategy`,
`Reranker` adapters); `apps/api` (`EvidenceThresholdPolicy` persistence
and resolution, the eligibility rechecks, outcome selection); `contracts`
(the `retrieval.rerank` `rc1` purpose's request/response shape); PostgreSQL
migrations, models, and relationships for sparse-profile and sparse-space-
generation identity and lineage (see "Sparse storage" above — this is not
claimed to require no relational migration); ingestion- and generation-
completeness changes needed to verify a hybrid-enabled workspace corpus
generation across both its dense and sparse axes; Qdrant collection/named-
vector configuration; cross-service tests spanning both `apps/ai` and
`apps/api`; the evaluation and calibration artefacts ADR-0019's harness
needs (the calibration/held-out split, threshold-policy binding records);
configuration and dependency files (FastEmbed/SPLADE++, Voyage reranking
client); and the factual `IMPLEMENTATION_GUIDE.md`/`tasks.json`/journal
updates every implementation session already produces. The previous,
narrower "`apps/ai` plus tests" framing implied by earlier planning-
document stubs is obsolete and is not the boundary this ADR establishes.
Wherever this document's own acceptance later touches planning-document
text, any remaining generic "freshness"/"archival" terminology predating
ADR-0017 should be corrected to ADR-0017/ADR-0018's actual temporal-
authority and eligibility vocabulary where encountered — a planning-
document correction, not a new decision this ADR makes.

This document does not define:

- the production values for any candidate count or the evidence threshold
  — `R16-S08`, calibrated against ADR-0019's evaluation harness;
- the exact `SparseEncoder`, `FusionStrategy`, `Reranker`,
  `EvidenceThresholdPolicy` class definitions, schemas, or the `rc1`
  `retrieval.rerank` purpose's exact request/response shape — `R16-S08`
  implementation work, constrained by the invariants fixed here;
- workspace billing, commercial pricing, or rate/usage/budget/AI-
  enablement controls;
- answer generation, prompt construction, or citation generation — Phase
  17;
- agent workflows of any kind;
- the exact sparse-profile rebuild scheduling, worker, or command
  mechanism — Stage 16.8 implementation work, constrained by ADR-0014's
  already-established rebuild invariants.

These remain open for the stages named above to decide with the context
this document establishes.
