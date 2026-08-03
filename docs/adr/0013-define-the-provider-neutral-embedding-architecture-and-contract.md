# ADR 0013: Define the Provider-Neutral Embedding Architecture and Embedding Contract

## Status

Accepted

## Date

2026-08-03

## Correction to ADR-0006's forward reference

ADR-0006's reference to `R13-S01` as the session that would decide Qdrant's
collection/sharding topology was accurate under the roadmap sequencing that
existed when ADR-0006 was accepted. The subsequent insertion of Phase 12
(Observability Foundation, ADR-0012) shifted every later phase number by one:
embeddings moved from what would have been Phase 12 to Phase 13, and vector
storage moved from Phase 13 to Phase 14. `R13-S01` is now this document —
Phase 13's embedding-provider boundary — not the vector-storage decision.
The Qdrant collection/generation topology ADR-0006 anticipated remains
correctly deferred, but to `R14-S01`, not `R13-S01`.

This is a citation correction, not a reversal of anything ADR-0006 decided.
ADR-0006's own text is left unchanged, consistent with this project's rule
that an accepted ADR is not rewritten to change history. This note exists so
a reader who follows ADR-0006's forward reference lands in the right place.

## Context

Phase 11 (ADR-0011) completed the deterministic chunking pipeline: every
document is reduced to an immutable `ChunkingResult` carrying ordered,
provenance-tracked `Chunk` values, each with a stable identity, its own token
count, and a strategy identity/version plus a configuration snapshot and
fingerprint that make the whole result attributable and comparable across
runs. Phase 12 (ADR-0012) established OpenTelemetry as the platform's one
instrumentation API, an allowlist-first privacy posture, and — specifically
relevant here — the architectural habit of separating a pipeline stage's
semantic result from the operational telemetry describing how it was
produced, before this platform's first calls to an external AI provider were
built.

Phase 13 must take that immutable chunk set and produce vectors: the
representation Phase 14 (vector storage) will persist, Phase 15 (retrieval)
will search, and Phase 16 (generation) will eventually cite through. This is
the platform's first outbound call to a third-party AI provider, and the
first artefact in this pipeline whose exact reproducibility this platform
does not fully control — unlike extraction, normalisation and chunking, all
of which run entirely inside code this platform owns, embedding depends on a
provider's model behaviour, which can change in ways this platform can
observe and version but cannot dictate.

This document defines the architectural embedding boundary and records the
initial V1 provider choice. It deliberately does not define vector
persistence or Qdrant collection/generation topology (Phase 14), retrieval,
hybrid search, reranking, or evaluation implementation (Phase 15), or
prompt construction, generation or citation design (Phase 16) — each of
those is named explicitly in "Scope boundaries" below, and several are
recorded as agreed forward direction, without being decided by this
document, in "Forward architectural decisions recorded now, not
implemented."

## What an embedding represents

An embedding is not another representation of a document, the way
`NormalisedDocument` or a `Chunk` still are. It is a semantic representation:
a fixed-dimension numerical vector whose only purpose is to place similar
meaning near similar meaning in a vector space, so that retrieval can find
relevant content by proximity rather than by keyword match. An embedding
does not preserve the document — it cannot be read, rendered, or
reconstructed back into text, and it is not a compressed copy of the chunk
it came from. It is a derived, lossy, purpose-built signal, produced from a
chunk's text but no longer usable as a substitute for it.

This matters architecturally, not just definitionally. Every stage before
this one — extraction, normalisation, chunking — has been document
processing: each stage produced an immutable, inspectable representation of
the same underlying content, progressively reshaped but always
recognisable as the document. Embedding is the point at which this platform
crosses from document processing into semantic search: the artefact this
stage produces is no longer "the document, reshaped" but a coordinate in a
semantic space whose geometry is defined by the embedding model — the
model shapes the space itself; the content determines only where within it
a given chunk's vector falls. This is precisely why
`EmbeddingProfile` compatibility (below) matters as much as it does — a
vector is only meaningful relative to the space it was placed in, and that
space is defined by the model that produced it, not by this platform's own
data model the way every earlier stage's output was.

## Decision

### The `Embedder` protocol boundary

The pipeline depends on an application-owned, provider-neutral `Embedder`
abstraction, never on the Voyage SDK or API directly. This is the same
Open/Closed reasoning ADR-0010 applied to the `Element` model and ADR-0011
applied to `ChunkingStrategy`: the set of `Embedder` implementations is open
for extension, but everything that calls `Embedder` and everything that
consumes an `EmbeddingResult` is closed for modification when a new
implementation is added.

```text
Embedder.embed(request: EmbeddingRequest) -> EmbeddingResult
```

The contract is designed to support, additively and without redesign: another
hosted embedding provider; Amazon Bedrock/Titan; a locally hosted embedding
model; and a deterministic fake used by tests. None of these is implemented
by this document — Stage 13.2 implements the V1 `Embedder` (Voyage) and the
test double; the others are named here only to confirm the abstraction does
not foreclose them.

This architecture must not be read as implying that switching provider is
operationally free. Changing provider, model, output dimensions, or any
other consequential embedding configuration produces vectors that are not
compatible with vectors already produced under the previous configuration,
and requires controlled re-embedding (see "Controlled re-embedding" below).
The `Embedder` abstraction makes switching *possible without rewriting the
pipeline*; it does not make switching *free*, and this document does not
pretend otherwise.

### Voyage as the initial V1 provider

Voyage is the initial hosted V1 embedding provider, reached exclusively
through the `Embedder` contract above.

### Why Voyage was selected for V1

This is a deliberate quality-first engineering trade-off, not a default or an
absence of a decision, and it is worth being explicit about what was and was
not weighed.

Retrieval accuracy and embedding quality are the platform's primary
objective at this stage. A retrieval-augmented system is only as good as the
evidence it can find; a mediocre embedding space silently caps the ceiling
of everything built on top of it — reranking, evaluation, and generation
can only work with the candidates retrieval actually surfaces. V1
deliberately prioritises a high-quality managed embedding provider over
self-hosting a model, because the cost of a weaker embedding space is paid
by every later phase, while the cost of not yet operating embedding
infrastructure is paid once, now, and is fully recoverable later.

Avoiding operational complexity, infrastructure maintenance and model
lifecycle management for V1 is an intentional engineering decision, not a
technical limitation this platform is settling for. Self-hosting an
embedding model is a real, ongoing responsibility: capacity planning, GPU or
inference infrastructure, model version upgrades, monitoring for quality
regressions, and on-call ownership of a service this platform would then be
the operator of, in addition to being its consumer. None of that
responsibility currently buys this platform anything it needs — there is no
demonstrated latency, cost-at-scale, data-residency, or compliance
requirement today that a well-chosen managed provider fails to meet. Taking
on that infrastructure now, before any such requirement exists, would be
exactly the kind of premature complexity this platform's stated engineering
philosophy already argues against elsewhere in this pipeline (ADR-0010's
core principle applies the same way here: don't pay a cost before there is a
concrete reason to).

This decision is independent of cloud-provider preference. It is not driven
by AWS alignment, and Bedrock/Titan is not the default merely because this
platform already uses AWS-compatible infrastructure elsewhere (S3-compatible
storage, SQS-compatible queues). Voyage was evaluated and chosen on
retrieval-quality and API-fit grounds specific to embeddings, independent of
which cloud the rest of the platform happens to run on. Provider lock-in to
any single vendor — AWS-affiliated or otherwise — is exactly what the
`Embedder` contract exists to prevent.

The platform remains provider-neutral through that same `Embedder` contract.
Nothing about choosing Voyage for V1 is special-cased into the pipeline —
Phase 14, 15 and 16 all consume `EmbeddingResult` values and an
`EmbeddingProfile`, never a Voyage-specific type. A future migration to
another managed provider, or to a self-hosted model once a genuine
operational or commercial reason exists to own that infrastructure, is an
additive `Embedder` implementation plus a controlled re-embedding (see
below) — not a pipeline redesign.

Local/self-hosted embeddings remain a valid future deployment option. This
document does not rule them out; it declines to build them now, for the
reasons above, and leaves the door open through the same abstraction it
requires Voyage to go through today.

Voyage is selected because it currently best satisfies this platform's
priorities around retrieval quality and operational simplicity — this is not
treated as an irreversible architectural commitment. The repository-owned
evaluation harness planned for Phase 15 (see "Forward architectural
decisions" below) exists specifically so that a future embedding provider
can be compared against the active one objectively, on this platform's own
retrieval-quality metrics, before any migration decision is made. Provider
replacement therefore remains an engineering decision supported by
measurable evidence, not a judgement call made on vendor reputation or
opinion — the same evidentiary standard this document expects of every
later retrieval-quality change.

### Embedding timing and workflow

Embedding happens asynchronously, as a stage of ingestion, after extraction,
structural normalisation and chunking have already produced an immutable
chunk set — it is not a synchronous step of the browser upload. The browser
never calls the embedding provider, directly or indirectly; only the
already-authenticated, already-tenant-scoped ingestion worker does, in the
same asynchronous processing context ADR-0008 and ADR-0009 already
establish for this pipeline.

The provider receives only what it needs to embed: chunk text, and the
minimum semantic configuration required to do so (for example, which task
mode applies — see below). It must not receive the original uploaded file,
workspace or storage credentials, unrelated user details, or provenance
metadata it has no use for. This is a minimum-necessary-input principle, not
an incidental implementation detail — every additional thing a third-party
provider receives is something this platform would have to explain, audit
and justify if that provider were ever compromised, and most of what a
chunk's provenance carries (source element IDs, character offsets, workspace
identity) is meaningless to an embedding call and must not cross that
boundary just because it happens to be nearby in the data model.

At search time, the user's query text is embedded through the same
`Embedder` contract, using the workspace's active compatible embedding
profile (see "Embedding profile and lineage" below), before vector
retrieval runs. Stored document vectors are reused as-is; a document is
never re-embedded merely because a question was asked against it. Query
embedding and document/chunk embedding are the same mechanism through the
same contract, distinguished only by which embedding purpose (below) the
request declares.

### Explicit embedding purposes

The request contract distinguishes, at minimum, between document/chunk
embedding and query embedding. Where the underlying provider exposes
task-specific input modes — Voyage does, through an explicit input-type
parameter — that mode is an explicit, named part of the request, not an
implementation detail buried inside the `Embedder`'s Voyage-specific code.
A provider's task-mode distinction exists because document and query text
are asymmetric in retrieval — treating them identically discards a real,
provider-exposed retrieval-quality signal, and because it is consequential
to the resulting vector space, it is retained as part of the
`EmbeddingProfile` (below), not silently defaulted.

### Embedding profile and lineage

An immutable, typed `EmbeddingProfile` records every consequential fact that
determines vector compatibility and behaviour: provider identity; model
identity; the model's revision or version, where the provider exposes one;
output dimensions; vector data type; normalisation behaviour; which
document/query task mode applies; the adapter's own version (this
platform's `Embedder` implementation, independent of the provider's own
versioning); and any other provider parameter that changes what vector a
given input produces.

Illustrative example only, not a schema — the exact fields, types and
serialisation are a Stage 13.2 concern, and a real profile also carries
`model_revision` (where the provider exposes one), vector data type and
normalisation behaviour alongside the fields shown below:

```text
EmbeddingProfile

provider: voyage
model: voyage-3-large
dimensions: 1024
task_mode: document
adapter_version: 1
fingerprint: 5c2d...
```

The fingerprint is derived deterministically from every field in the
profile snapshot — this is what makes two `EmbeddingResult`s comparable
without re-inspecting every field individually each time.

Like `ChunkingResult`'s configuration (ADR-0011), the profile is retained in
two complementary forms: a canonical, inspectable **snapshot** — a human or
a later process can read exactly what produced a given vector — and a
deterministic **fingerprint** derived from that snapshot, the same
`sha256`-of-canonical-JSON pattern this codebase already uses for
`BaselineChunkingConfiguration.fingerprint()`. The fingerprint is retained
with every produced `EmbeddingResult` and is what "vector compatibility"
(below) is actually checked against — two vectors are comparable only if
their fingerprints match, never merely because their dimensions happen to.

This document is honest about the limits of hosted-provider reproducibility.
Exact attribution — knowing precisely which provider, model, revision and
parameters produced a given vector — is required, and the fingerprint
mechanism exists to make that attribution cheap to check. Permanent
bit-for-bit reproducibility of the vector itself is not promised where the
provider does not expose an immutable model revision — a hosted provider can
change model behaviour behind a stable model name in ways this platform can
detect (through evaluation regressions, see "Forward architectural
decisions" below) but cannot prevent. A bare, mutable model name is not
sufficient lineage precisely because of this: if the platform recorded only
`"voyage-3"` and the provider silently updated what that name serves, two
vectors recorded under the identical profile snapshot could, in principle,
no longer be reproducible from the same input — the fingerprint identifies
what was asked for and received at the time, not a permanent guarantee about
what asking again later will return. This mirrors ADR-0011's own honesty
about model-assisted chunking determinism: a guarantee is only as real as
what can actually be verified, and this document does not promise more than
that.

### Compatibility invariant

Vectors produced under incompatible embedding profiles must never be treated
as belonging to the same searchable vector generation. Matching output
dimensions alone does not make two embedding spaces compatible — two
different models can both produce 1024-dimensional vectors that are not
comparable to each other in any meaningful sense. A query may search a
vector generation only using that generation's compatible embedding
profile.

The exact Qdrant collection/generation topology that enforces this
invariant physically belongs to Phase 14 (see "Correction to ADR-0006's
forward reference" above) — this document does not decide collection
layout, sharding, or point-identifier schemes. What this document commits
to now, so Phase 14 inherits a settled answer rather than re-deriving one,
is the invariant itself: compatibility is a property of the embedding
profile's fingerprint, not of dimension count, and no later phase may treat
dimension-matching as sufficient grounds to mix vector generations.

### Controlled re-embedding

A consequential embedding-profile change — a new provider, a new model, a
changed dimension count, or any other change the profile snapshot would
reflect — creates a new vector generation and triggers controlled
re-embedding of the relevant active chunks. An active corpus is never
updated piecemeal in place while mixing old and new vector spaces under one
searchable generation.

The eventual workflow this document expects Phase 14 to implement is:

```text
1. the existing generation remains active and searchable throughout;
2. a new generation is created for the new profile;
3. all required chunks are re-embedded into that new generation;
4. completeness and dimensions are verified against the source chunk set;
5. the new generation is atomically activated;
6. the previous generation is retired afterward.
```

The persistence and activation mechanics behind this workflow — how a
generation is represented, how atomic activation is achieved, how retirement
is scheduled — are Phase 14 concerns. This document commits only to the
shape of the workflow and to the invariant it protects: no consumer of
retrieval ever silently searches a mix of two incompatible vector spaces
under one label.

### Batch contract

The `Embedder` boundary accepts bounded batches of chunks rather than
requiring one provider request per chunk. Batching exists for a concrete
operational reason — it reduces per-request overhead and materially
improves ingestion throughput compared with one round-trip per chunk — but
that operational benefit is only acceptable alongside an equally concrete
architectural guarantee: batching must never weaken the ability to say,
for any single vector, exactly which chunk produced it. Each requested item
retains its source chunk identity (in practice, the `Chunk.id` already
established by ADR-0011) through the call, and the result preserves a
strict one-to-one association between requested chunk identities and
returned vectors — a batch response is never trusted merely because it
returned the expected count of vectors; it is validated.

The implementation must validate, for every batch response: the response
item count against the request; each vector's dimensions against the
embedding profile; that every returned numeric value is finite (a
provider-side numerical fault must never silently enter this platform's
vector space as `NaN` or `Infinity`); item association and order against
what was requested; and profile compatibility of the result as a whole.
Batch size limits are configurable and are constrained by the provider's own
limits — maximum input count per request, aggregate token or input size, and
maximum payload size — rather than an arbitrary constant chosen without
reference to what the provider actually accepts.

### Semantic result versus operational telemetry

The semantic `EmbeddingResult` contains exactly the facts required to
understand and persist the produced vectors: the embedding profile snapshot
and fingerprint; the source chunk identity; the produced vector; its
dimensions; and any consequential provider-returned semantic metadata (for
example, a provider-reported truncation of an oversized input). This is the
same distinction ADR-0011 drew for `ChunkingResult`, and ADR-0012's
allowlist-first posture applies to it identically: operational telemetry —
provider name, model classification, item count, token usage, request
duration, provider call count, retry count, estimated cost, and controlled
failure classification — is recorded separately, by orchestration or
instrumentation wrapped around the `Embedder`, never folded into the
semantic result. Duration and cost vary run to run for reasons that have
nothing to do with which vector was produced; folding them together would
make the semantic result look non-deterministic when only its incidental
execution detail varied.

Raw chunk text, raw query text, and the produced vectors themselves are not
exported to telemetry by default, consistent with ADR-0012's privacy
posture: telemetry explains how the system behaved, not what the
platform's — or a workspace's — content was. A vector is a derived
representation of exactly that content and is treated with the same
default caution as the text it came from, not exempted merely because it is
numeric rather than textual.

### Typed failures and retries

Provider-specific exceptions are translated into typed platform failures,
differentiated at minimum into: invalid or empty input; input too large;
authentication or configuration failure; rate limiting; timeout; temporary
provider unavailability; malformed provider response; dimension mismatch;
and profile mismatch.

Only genuinely transient failures are retried — rate limiting, timeout, and
temporary provider unavailability — using bounded retries with capped
exponential backoff and jitter. Permanent validation, credential, dimension
and compatibility failures are never retried as though they were transient:
retrying a request that is permanently wrong (a workspace's Voyage
credential is missing; a batch was assembled against the wrong profile)
provides no value and only delays the moment someone finds out something
needs correcting, the same reasoning ADR-0010 already applied to
transient-versus-permanent extraction failures. Failures and retries are
visible in safe telemetry and, where operationally significant, in the
platform's existing audit layers (ADR-0006), not silently swallowed.

### Testing

Ordinary automated tests never require live internet access, Voyage
credentials, or a paid provider call. A deterministic fake `Embedder`
produces the same fixed-dimension vector for the same input and profile,
preserves item identity through batch operations, supports the same batch
behaviour as the real implementation, can simulate each typed failure
category above, and supports completeness and validation tests against the
batch contract. The fake tests contract correctness — association, ordering,
validation, failure handling — not semantic retrieval quality; whether
Voyage's actual embeddings are any good is an evaluation-harness question
(see "Forward architectural decisions" below), not a unit-test question.

Real Voyage integration tests are isolated, opt-in, and clearly
distinguishable from the normal test suite, mirroring the same
separation this platform already applies to any external-provider
dependency it does not want ordinary CI runs quietly depending on.

### Secrets

Provider credentials — the Voyage API key — never appear in domain models,
logs, telemetry, or persisted semantic results. They belong to
environment/secret-management configuration exclusively, consistent with
how this platform already treats the HMAC secrets in ADR-0009 and the
privacy posture in ADR-0012.

## Forward architectural decisions recorded now, not implemented

The following were agreed during Phase 13 planning as V1's intended
direction. None of them is decided, designed, or implemented by this
document — each is recorded here so it is not lost, and each will receive
its own ADR (or, where noted, its own dedicated decision within an existing
future ADR) before implementation begins.

- **Reranking is part of the planned V1 architecture** and will receive its
  own decision before implementation — bundled, by prior agreement, into the
  same ADR as hybrid retrieval (Phase 15, Stage 15.6), rather than a
  standalone reranking ADR, because dense retrieval, sparse retrieval,
  fusion, reranking and evidence thresholds form one candidate-selection
  pipeline.
- **Query decomposition is designed into the retrieval pipeline from the
  outset**, as an explicit query-planning boundary accepting one or more
  bounded retrieval queries, initially satisfied only by an identity/no-op
  planner that returns the original query unchanged. Model-assisted
  decomposition is not enabled until the evaluation harness below
  demonstrates a quality improvement that justifies its added latency, cost
  and complexity. This is formalised in Phase 15's Retrieval Contract ADR
  (Stage 15.2), not this document.
- **Repository-owned evaluation datasets, evaluation (including
  Ragas-based metrics where suitable) and quality-release gates are
  first-class architectural concerns**, not optional testing bolted on
  afterward — this becomes its own ADR at Phase 15, Stage 15.4 (Define
  Evaluation and Quality-Gate Architecture), extended by generation-specific
  metrics at Phase 16, Stage 16.4, per the "Design constraint — Quality
  lineage across the pipeline" already recorded in `PROJECT_ROADMAP.md`.
- **Future retrieval architecture supports dense retrieval first, then
  hybrid retrieval and reranking, without redesign** — the sequencing
  already reflected in Phase 15's restructured stages (15.2–15.3 dense and
  its contract, 15.4–15.5 evaluation of that baseline, 15.6–15.7 hybrid and
  reranking built and evaluated against it), not a decision this document
  makes but a trajectory this document's `Embedder`/`EmbeddingProfile`
  contract is deliberately compatible with.
- **All retrieval-quality improvements remain measurable through the common
  evaluation harness**, rather than adopted on subjective judgement — every
  future retrieval-affecting ADR is expected to be justified against the
  Phase 15 evaluation harness's baseline, not against intuition about
  whether a change "seems" better.

## Alternatives considered

### Depend on the Voyage SDK/client directly throughout the pipeline

Rejected. This would couple every future consumer of embeddings —
ingestion, retrieval, any future re-embedding tooling — to one vendor's
client library and request shape, recreating exactly the coupling problem
ADR-0010 already solved for extraction and ADR-0011 already solved for
chunking, one stage further into the pipeline.

### Amazon Bedrock/Titan, or a local model, as the V1 provider

Rejected for V1, not permanently. Bedrock/Titan was considered specifically
because this platform already runs on AWS-compatible infrastructure
elsewhere, but was set aside because that infrastructure-alignment argument
is not a retrieval-quality argument, and retrieval quality is what V1
optimises for (see "Why Voyage was selected for V1"). A local model was
considered and rejected for the same infrastructure-responsibility reasons —
capacity planning, model lifecycle ownership, and operational maintenance
this platform has no demonstrated need to take on yet. Both remain
available as additive future `Embedder` implementations once a concrete
requirement — cost at scale, data residency, or a demonstrated quality gap —
actually motivates them.

### Treat document and query embedding identically, with no task-mode distinction

Rejected. Where a provider exposes a document/query task-mode distinction,
ignoring it discards a real, provider-exposed retrieval-quality signal for
no benefit, and silently defaulting it inside provider-specific code would
hide a consequential configuration choice from the profile that is supposed
to fully describe what produced a given vector.

### A bare, mutable model name as embedding lineage

Rejected. A model name a provider can silently redefine behind the scenes is
not sufficient to protect vector compatibility or answer "what actually
produced this vector" — the canonical snapshot and fingerprint exist
precisely because compatibility must be checked against more than a name
that can drift underneath it.

### Piecemeal, in-place re-embedding of an active corpus

Rejected. Updating an active corpus chunk-by-chunk while old and new vector
spaces coexist under one searchable generation would make retrieval
silently inconsistent — some results compatible with the new profile, some
not — with no observable point at which the corpus was actually safe to
search. The generation-based workflow exists specifically to make this
transition atomic and observable instead.

### One provider request per chunk

Rejected. This forecloses the batch-level validation this document requires
(count, association, order) and is needlessly wasteful of both provider
request overhead and this platform's own request budget, for no benefit
over a bounded batch contract with equivalent per-item guarantees.

### Folding operational telemetry into the semantic `EmbeddingResult`

Rejected, for the same reason ADR-0011 rejected it for `ChunkingResult`:
duration, cost, retry count and call count vary for reasons unrelated to
which vector was produced. Combining them with the semantic result would
make incidental execution variance look like it affects the embedding
outcome, when only the operational telemetry does.

### Automatic, unbounded retry of any provider failure

Rejected. Retrying a permanently invalid request (bad credentials, a
malformed batch, a profile mismatch) as though it might eventually succeed
can mask a systemic problem as a background retry loop instead of surfacing
a diagnosable failure — the same reasoning ADR-0010 already applied to
extraction failures and ADR-0007 already applied to document processing
retries generally.

## Consequences

### Positive

- Embedding provider, model and configuration remain fully replaceable
  through one abstraction, without requiring Phase 14, 15 or 16 to change
  when V1's Voyage choice is eventually revisited.
- Vector compatibility is protected structurally (profile fingerprint), not
  by convention or by trusting that dimension-matching is enough.
- Controlled re-embedding gives the platform an explicit, observable
  workflow for a provider or model change, rather than an ad hoc migration
  invented under pressure the first time one is needed.
- The semantic-result/telemetry separation and the privacy-conscious
  telemetry posture extend ADR-0012's foundation into Phase 13 exactly as
  ADR-0012 intended every later AI-pipeline phase to inherit it.
- A deterministic fake `Embedder` keeps the ordinary test suite fast,
  free, and independent of Voyage's availability, while still exercising
  real batch, validation and failure-handling behaviour.
- Recording reranking, query decomposition, evaluation/quality-gates and the
  dense-then-hybrid retrieval trajectory now — without deciding their
  details here — means Phase 15 and 16 inherit an already-agreed direction
  instead of re-litigating it from scratch.

### Negative

- Hosted-provider reproducibility is honestly limited: this platform can
  detect and version a provider-side model change, but cannot prevent one,
  and cannot promise permanent bit-for-bit vector reproducibility the way
  ADR-0011's deterministic chunking contract can for source-controlled code.
- Controlled re-embedding is real, recurring operational work every time a
  consequential profile change is adopted — this document commits the
  platform to that discipline rather than allowing a cheaper, riskier
  in-place update.
- Choosing a managed provider for V1 means this platform is dependent on
  Voyage's availability, pricing and API stability for its first
  retrieval-quality-critical dependency, accepted deliberately in exchange
  for not owning embedding infrastructure prematurely.
- The batch contract's validation obligations (count, dimensions, finite
  values, association, profile compatibility) are real implementation
  surface Stage 13.2 must build carefully, not a free consequence of
  choosing to batch.

## Architectural invariants

- The pipeline depends only on the `Embedder` abstraction; no application
  code depends on the Voyage SDK or API directly outside the V1 `Embedder`
  implementation itself.
- Switching provider, model, dimensions, or any other consequential
  configuration is never treated as operationally free — it always produces
  a new embedding profile and requires controlled re-embedding.
- The browser never calls, directly or indirectly, an embedding provider.
- A provider receives only chunk/query text and the minimum semantic
  configuration required to embed it — never the original file, storage or
  workspace credentials, unrelated user details, or unneeded provenance.
- Every `EmbeddingResult` carries the embedding profile's fingerprint;
  vectors are never treated as compatible on the basis of matching
  dimensions alone.
- A consequential profile change creates a new vector generation; an active
  corpus is never updated piecemeal while mixing incompatible vector
  spaces under one searchable generation.
- Batch requests preserve source chunk identity and a strict one-to-one
  request/response association, validated on every response.
- The semantic `EmbeddingResult` and operational telemetry are constructed
  and retained separately; raw chunk/query text and produced vectors are
  not exported to telemetry by default.
- Only transient failure categories are retried, with bounded, jittered
  backoff; permanent failures are never retried as though transient.
- Provider credentials exist only in environment/secret-management
  configuration — never in domain models, logs, telemetry, or persisted
  results.
- Ordinary automated tests require no live internet access, no Voyage
  credentials, and no paid provider call.

## Scope boundaries

This document does not define:

- the exact `Embedder`, `EmbeddingRequest`, `EmbeddingResult` or
  `EmbeddingProfile` class definitions, schemas, or serialisation format —
  Stage 13.2 concerns;
- Qdrant collection or vector-generation topology, or how a generation is
  physically persisted, activated, or retired — Phase 14 (`R14-S01`, per
  the correction above);
- dense retrieval settings, sparse/keyword retrieval, hybrid candidate
  fusion, or reranking — Phase 15, Stages 15.2–15.7;
- retrieval thresholds, calibrated abstention, or query decomposition's
  concrete design — recorded as forward direction above, formalised in
  Phase 15;
- prompt construction, grounded generation, or citation design — Phase 16;
- evaluation implementation, the evaluation corpus schema, metrics
  catalogue, or quality-gate mechanics — recorded as forward direction
  above, formalised in Phase 15, Stage 15.4, and extended in Phase 16,
  Stage 16.4.

These remain open for the phases named above to decide with the context
appropriate to each.
