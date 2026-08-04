# ADR 0014: Define the Vector Storage Architecture and Qdrant Topology

## Status

Accepted

## Date

2026-08-04

## Framing: what this ADR does and does not decide

This ADR does not decide whether Qdrant is authoritative for anything. That question
was already settled by ADR 0007, which defined the searchable/vector representation
as *"a derived, disposable, rebuildable projection of a document's content... a
document's content... rebuildable from the authoritative source content through the
processing pipeline, without requiring a fresh upload,"* and which explicitly rejected
treating vector-store presence as the signal that a document is usable. Nothing in
this document reopens that position.

What this ADR does is operationalise it: this is the decision ADR 0006 originally
deferred as `R13-S01` and ADR 0013 later corrected to `R14-S01` — Qdrant's collection,
sharding and tenancy topology. It defines, concretely enough for Stage 14.2–14.4 to
build against:

- what must be persisted authoritatively in PostgreSQL for "rebuildable" to be a real,
  testable operational property rather than an aspiration;
- what a compatible vector space is, and how it maps to physical Qdrant structure;
- a lifecycle concept Phase 14 needs that no prior ADR named — a per-workspace
  corpus rebuild, distinct from an embedding-profile change;
- how activation, rollback and rebuild work as concrete operations;
- how the rest of the platform is isolated from Qdrant specifics behind a
  provider-neutral `VectorStore` boundary, mirroring the `Embedder` boundary ADR
  0013 already established one stage earlier in the pipeline.

## Context

Phase 10 (ADR 0010) through Phase 13 (ADR 0013) built an immutable, deterministic
pipeline — `ExtractedDocument → NormalisedDocument → ChunkingResult/Chunk →
EmbeddingResult` — with a consistent set of habits carried through every stage:
immutability, deterministic identity, explicit provenance, a provider-neutral
abstraction at each seam (`Element`, `ChunkingStrategy`, `Embedder`), and a firm
separation between a stage's semantic result and the operational telemetry describing
how it was produced (ADR 0011, ADR 0012, ADR 0013). Phase 14 is the next seam in that
same pipeline, and this ADR is written to extend those habits rather than invent new
ones where the existing ones already fit.

ADR 0006 established Workspace as the platform's tenancy and isolation boundary, a
pooled relational model with defence-in-depth rather than physical per-tenant
partitioning, and a standing requirement that every vector record carry immutable
workspace identity, with the physical Qdrant layout explicitly deferred to this
session. ADR 0012 established OpenTelemetry as the instrumentation boundary and an
allowlist-first privacy posture that this ADR's payload-minimalism decision extends
into the vector-storage layer. ADR 0013 established the `Embedder` boundary, the V1
Voyage profile (`voyage-4-large`, 1024 float dimensions, unit-length normalisation),
the `EmbeddingProfile` fingerprint as the sole basis for vector compatibility, and the
six-step controlled re-embedding workflow this ADR now gives physical shape to.

Two ambiguities surfaced during the architecture review that preceded this document,
and both are resolved here rather than by amending the ADRs that raised them — accepted
ADRs are immutable, and neither ambiguity turned out to require reopening either one.

**First**, ADR 0006 states that workspace-level configuration includes "its selected
embedding provider and model." Read in isolation this could suggest V1 must let each
workspace independently choose its embedding provider. Read in the context of ADR
0006's own stated purpose — the classification exists to determine *which entities
require the mandatory workspace foreign key and which negative tests are required*,
and the ADR itself is declared "architecture-and-documentation only... implementation
is deferred" — it is a data-modelling classification (this concept is workspace-owned,
not platform-global, whenever it exists), not a V1 product requirement. This ADR
records the resolution explicitly in "V1 embedding-profile scope" below, rather than
leaving the ambiguity to be rediscovered during Stage 14 implementation.

**Second**, ADR 0013 defines "vector generation" precisely: it is created by "a
consequential embedding-profile change — a new provider, a new model, a changed
dimension count, or any other change the profile snapshot would reflect." That
definition is correct and is not changed here. But Phase 14 has a second, real need
ADR 0013 had no reason to anticipate: a workspace's corpus can need rebuilding for
reasons that have nothing to do with the embedding profile — a chunking or
normalisation change, a parser correction, a targeted reindex — and treating that as
the same kind of event as a profile change would force every such rebuild through
collection-level machinery it does not need. This ADR introduces a second, explicitly
named concept for that case, precisely so the word "generation" is never ambiguous in
this document or in the code that implements it.

## Core distinction: two generation concepts

### Embedding-space generation

An **embedding-space generation** is a platform-scoped compatibility boundary defined
by exactly one immutable `EmbeddingProfile` fingerprint (ADR 0013). It is the concept
ADR 0013 already named "vector generation," carried forward here unchanged and given
a more specific name only to keep it unambiguous alongside the concept introduced
below.

It owns or determines:

- provider/model profile lineage (the `EmbeddingProfile` snapshot and fingerprint);
- vector dimensions;
- vector output data type;
- the distance metric a search against this space uses;
- the vector schema a point in this space must satisfy;
- Qdrant collection identity — see "Collection topology" below.

One embedding-space generation maps to exactly one Qdrant collection. A new
embedding-space generation is created only when a consequential embedding-profile
change produces a vector space that is no longer compatible with the previous one —
exactly ADR 0013's compatibility invariant, restated here as the trigger for a new
physical collection rather than a new abstract concept.

### Workspace corpus generation

A **workspace corpus generation** is a PostgreSQL-owned lifecycle entity representing
the current searchable build of one workspace's corpus inside one embedding-space
generation. It is new to this ADR — no prior ADR needed it, because it is a
storage-and-rebuild concept, not a pipeline-stage concept.

An active workspace corpus generation is not a single, one-shot immutable snapshot.
Ordinary, successful document ingestion extends it incrementally — see "Ordinary
ingestion versus coordinated corpus rebuild" below. A *new* workspace corpus
generation, replacing the active one wholesale, is created only for a coordinated
corpus rebuild, for reasons including:

- an embedding-profile migration (in which case it changes together with the
  embedding-space generation — see below);
- a chunking or normalisation strategy change that needs backfilling;
- a parser or data-quality correction;
- a targeted, workspace-scoped reindex requested for operational reasons;
- any other consequential corpus-rebuild trigger this platform introduces later.

Ordinary ingestion of an individual document is never such a trigger.

A workspace corpus generation does not receive its own Qdrant collection. It is
distinguished, inside whichever compatible collection it belongs to, through
mandatory payload fields — `workspace_id` and `workspace_corpus_generation_id` — not
through physical storage partitioning. Storage partitioning is reserved for the
compatibility boundary described in "Collection topology" below.

### How the two relate

A profile change moves both concepts together: a new embedding-space generation is
created (new collection), and every workspace's corpus must be rebuilt into it,
producing a new workspace corpus generation per workspace within that new collection.
A corpus-only rebuild moves only the second concept: a new workspace corpus
generation is created and populated inside the *existing* embedding-space
generation's collection, with no Qdrant collection activity at all. Every rebuild
this platform will ever need is one of these two cases, and neither requires a third
kind of event.

## Decision

### V1 embedding-profile scope

The platform may maintain a catalogue of supported embedding profiles — this is
already implied by ADR 0006's "platform-global catalogues" concept — and the
architecture must preserve a seam for a future workspace-specific override. V1 does
not build or expose that override. Concretely:

- V1 uses exactly one platform-selected embedding profile for every workspace: the
  profile ADR 0013 already accepted (Voyage, `voyage-4-large`, 1024 float dimensions,
  unit-length normalisation).
- No workspace-profile override table, configuration surface, or product feature is
  introduced in V1.
- Effective-profile resolution may be described conceptually as
  `workspace override ?? platform default`, but the override side of that expression
  is absent and unset for every workspace in V1 — there is nothing to select, and no
  code path exercises a choice.
- This is consistent with ADR 0006 read as establishing workspace-scoped *effective
  configuration* (a classification, for tenancy-FK and negative-test purposes) rather
  than mandating independent per-workspace *selection* as a V1 capability. See
  "Context" above.

Building the override now, before any workspace has a demonstrated need for a
different provider or model, would be exactly the premature-complexity pattern this
platform has repeatedly declined elsewhere — ADR 0010's core loss-minimisation
principle and ADR 0013's own reasoning for not self-hosting embeddings both argue the
same way: don't pay a cost before a concrete requirement justifies it.

### Per-workspace activation

Even with one platform-selected profile, which corpus generation is currently
searchable must be tracked per workspace, not as a single platform-wide switch. This
is required for staged rollout, not for hypothetical future flexibility:

- a new corpus generation — whether triggered by a profile migration or a
  corpus-only rebuild — can be prepared and populated while every workspace remains
  fully served by its current, active corpus generation throughout;
- each workspace is cut over independently, once its own new corpus generation is
  complete and verified against it, rather than waiting for every workspace
  (including the platform's largest tenant) to finish before anyone is cut over;
- a large or slow-to-migrate workspace never blocks a smaller one;
- rollback is a PostgreSQL pointer change back to the previous
  `workspace_corpus_generation_id`, not a data-recovery operation — the superseded
  generation's points are retained, unpurged, for a bounded confidence period after
  cutover, mirroring how ADR 0007 retains a `DELETED` Document row rather than
  hard-removing it immediately, for the same reconciliation reasoning.

### Ordinary ingestion versus coordinated corpus rebuild

An ordinary, successful document ingestion does not create a new workspace corpus
generation. It adds that document's verified chunk points to the workspace's
currently active corpus generation. This is the routine case — the overwhelming
majority of ingestion activity a workspace ever generates — and it must not require
the heavier machinery "Rebuild invariant" and "Per-workspace activation" describe for
a coordinated rebuild.

A new workspace corpus generation exists only for a coordinated corpus replacement:
the triggers already listed under "Workspace corpus generation" above (a profile
migration, a chunking/normalisation change requiring backfill, a data-quality
correction, an explicitly requested targeted reindex). Nothing about a single
document's routine upload, edit, archival or deletion rises to that threshold.

Within the active corpus generation:

- a Document becomes searchable only once every one of its expected chunk points has
  been persisted and verified complete against PostgreSQL's authoritative chunk set
  (see "Completeness verification" below) — this mirrors ADR 0007's `INDEXED` state,
  which already requires the complete approved representation to be available, not a
  partial one;
- a partial or failed vector write must never expose a partially indexed document as
  searchable — an incomplete write is a failure to surface, consistent with ADR
  0010's and ADR 0011's no-silent-loss discipline, not a partial success to accept
  quietly;
- routine document deletion or archival is a scoped delete or payload update against
  the active corpus generation through `VectorStore`'s scoped-delete operation. It
  does not require rebuilding, or even touching, any other document's points in the
  workspace's corpus.

### Migration-concurrency invariant

Ordinary ingestion, edits, archival and deletion do not pause while a candidate
workspace corpus generation is being built for a coordinated rebuild. This creates a
real correctness hazard this ADR resolves explicitly: **a candidate workspace corpus
generation must not be activated until it has incorporated every authoritative
PostgreSQL change up to an explicit cutover boundary.** A candidate that began
building before some later authoritative change — a new upload, an edit, an
archival, a deletion — and never incorporated that change is stale, and activating
it would silently regress the workspace's searchable corpus: exposing content that
should already have been removed, or omitting content that should already be
present.

This ADR does not define the mechanics that keep a candidate generation caught up
during a rebuild — dual-write, catch-up passes, scheduling, or event replay are all
legitimate approaches, and the choice belongs to Stage 14.3/14.4. What is decided
here is the invariant those mechanics must satisfy: activation is only valid against
a candidate generation that is coherent with PostgreSQL's authoritative state as of
its own cutover boundary, never against a candidate known to be missing changes that
occurred after it started building.

### Explicit propagation, never inference

The AI service must never infer, from Qdrant contents, collection names, defaults, or
its own process-global state:

- workspace identity;
- the active workspace corpus generation;
- the active embedding-space generation;
- profile compatibility.

All four are resolved explicitly in PostgreSQL and passed explicitly into every call
the AI service makes to `VectorStore`. This is a direct extension of ADR 0006's
existing invariant that "no service may derive tenant identity implicitly" and that
"when tenant identity crosses any service boundary, it becomes untrusted input until
validated by the receiving service" — this ADR applies the identical discipline to
generation identity, because mixing generations silently is exactly the failure mode
ADR 0013's compatibility invariant already exists to prevent, and an implicit default
inside the AI service would be the easiest way to reintroduce it.

### Storage ownership

PostgreSQL is authoritative for:

- Document identity and lifecycle (ADR 0007, unchanged);
- canonical, accepted chunk text — see "Rebuild invariant" below, where this ADR
  settles what ADR 0007 had deliberately left open;
- chunk identifiers, ordinals and provenance (ADR 0011, unchanged);
- the `EmbeddingProfile` snapshot and fingerprint (ADR 0013, unchanged);
- embedding-space generation metadata and lifecycle;
- workspace corpus-generation lifecycle;
- the per-workspace active corpus-generation pointer;
- rebuild and activation lineage — what was rebuilt, when, from what, and when it was
  activated or retired.

Qdrant owns:

- vectors;
- a minimal indexed search payload — see "Minimal Qdrant payload" below;
- no authoritative lifecycle decisions of any kind.

Qdrant is a disposable, rebuildable search projection, not a system of record. This
is a restatement of ADR 0007's already-accepted position, not a new claim.

### Generation lifecycle semantics

Both generation concepts are explicit state machines, not boolean flags — the same
discipline ADR 0007 already applied to the Document lifecycle. The exact state names
are an implementation detail for Stage 14.3; the states below are illustrative of the
shape required, and the invariants that follow are what this ADR actually commits to.

**Embedding-space generation**, illustratively: `BUILDING → VERIFYING → AVAILABLE →
RETIRING → RETIRED`. An embedding-space generation becomes `AVAILABLE` once its
collection exists, its schema is confirmed, and it is ready to receive workspace
corpus generations — availability is a platform-wide precondition for use, not a
statement that any particular workspace is actually searching it yet.

**Workspace corpus generation**, illustratively: `BUILDING → VERIFYING → ACTIVE →
SUPERSEDED → RETIRED`. A workspace corpus generation reaches `ACTIVE` only for the
one generation currently serving that workspace's retrieval traffic; a prior
`ACTIVE` generation that has been cut over away from becomes `SUPERSEDED`, and is
eventually `RETIRED` (its points purged) once its confidence/rollback window has
elapsed.

The following invariants hold regardless of the exact state-machine implementation:

- at most one workspace corpus generation is `ACTIVE` for a given workspace at any
  time;
- activation — the transition into `ACTIVE` — requires successful completeness and
  compatibility verification against PostgreSQL's authoritative chunk set (see
  "Completeness verification" below); a candidate is never activated on the strength
  of an assumed-successful build alone;
- an `ACTIVE` workspace corpus generation always references an `AVAILABLE`
  embedding-space generation; a workspace is never left pointing at a `RETIRING` or
  `RETIRED` embedding-space generation;
- a `BUILDING` or `VERIFYING` build — whether an embedding-space generation or a
  workspace corpus generation — that fails or is left incomplete never transitions to
  `AVAILABLE`/`ACTIVE` and never becomes searchable, consistent with the "ordinary
  ingestion" invariant above that a partial write must never be exposed;
- a `SUPERSEDED` workspace corpus generation's points remain available, unpurged,
  for a bounded rollback/confidence period after cutover, consistent with
  "Per-workspace activation" above;
- an embedding-space generation is never described as globally active. Activation is
  a per-workspace property of workspace corpus generations; an embedding-space
  generation is, at most, `AVAILABLE` for use — many workspaces may reference it, one
  may not yet, and neither is a property of the embedding-space generation itself.

### Rebuild invariant

"Rebuildable" is defined precisely, so it is a testable property rather than a
slogan. Rebuilding a Qdrant projection (a workspace corpus generation, or, in the
degenerate case of a full-platform incident, every active corpus generation) means:

```text
1. read the authoritative, persisted chunks and their lineage from PostgreSQL;
2. re-embed the persisted chunk text using the intended EmbeddingProfile;
3. recreate or ensure the compatible Qdrant collection exists for that profile;
4. upsert deterministic points into it;
5. validate completeness and profile compatibility against the source chunk set
   (see "Completeness verification" below);
6. activate the rebuilt workspace corpus generation only after verification passes.
```

This is deliberately narrower than "rerun the whole pipeline." Rebuildability does
**not** require re-running extraction or chunking from the original source file.
Requiring that would make rebuild depend on extraction being deterministic across
time — which it is not guaranteed to be, since a parser library upgrade can change
extraction output in ways ADR 0010 never promised to prevent — and would turn a
bounded, predictable recovery operation into one that could, in principle, produce a
different chunk set than the one currently indexed.

Making step 1 possible requires a decision ADR 0007 explicitly left open: *"This ADR
does not assume that any intermediate extracted or normalised text is itself durably
stored."* This ADR resolves that question for chunk text specifically: **canonical,
accepted chunk text is durably persisted in PostgreSQL.** Without that commitment,
"rebuildable from PostgreSQL" would silently mean "rebuildable by re-running
extraction and chunking against S3," which is a materially heavier and less
predictable operation than the phrase implies, and this ADR does not want to leave
that gap for Stage 14.3 to discover.

Raw vector arrays are not duplicated into PostgreSQL. That would make PostgreSQL a
second vector store rather than the source that lets a vector store be rebuilt, and
it is not needed: rebuild cost is one bounded, deterministic re-embedding pass over
already-persisted text, not a second storage system to keep synchronised.

### Completeness verification

Count equality alone does not prove a candidate workspace corpus generation is
complete — two collections, or two scopes within one collection, can agree on point
count while disagreeing on which points they actually contain. Verifying a candidate
before it can be activated (see "Generation lifecycle semantics" above) means
comparing the authoritative expected chunk/point identities read from PostgreSQL
against the identities actually persisted in Qdrant, not merely comparing totals.

At minimum, verification must establish, for the candidate's scope:

- expected count equals actual count;
- every expected deterministic point identity (derived per "Deterministic point
  identity" below) exists in Qdrant;
- no unexpected point identity exists within the candidate's scope — a point that
  should not be there is as much a completeness failure as one that is missing;
- every point carries the expected `workspace_id`, `workspace_corpus_generation_id`
  and `embedding_space_generation_id` payload values — the defensive validation
  "Minimal Qdrant payload" describes is exercised here, not left unchecked;
- vector dimensions and the configured vector name match the embedding-space
  generation's schema.

This is the same discipline ADR 0013 already applies to a batch embedding response —
a result is validated against what was expected, never trusted merely because a
count matched — carried one stage further into vector persistence. The exact,
efficient implementation (a diff strategy, a merkle-style comparison, a full listing
for smaller corpora) is a Stage 14.3 concern; the semantic meaning of "complete" is
decided here.

### Collection topology

A Qdrant collection represents exactly one compatible embedding space — one
`EmbeddingProfile` fingerprint, one embedding-space generation. This ADR rejects:

- **collection per workspace** — not required for security (ADR 0006 already
  establishes that workspace-scoped authorisation happens before the AI service is
  ever called, and that "storage prefixes and vector payloads support scoping but
  never replace authorisation"), and it reproduces, at the vector-storage layer, the
  same operational-complexity problem ADR 0006 already rejected for
  database-per-tenant and schema-per-tenant PostgreSQL isolation, at a scale bounded
  only by tenant count rather than by anything the platform controls;
- **collection per workspace corpus generation** — this would tie physical storage
  partitioning to a lifecycle event (a corpus rebuild) that is not physically forced
  by anything Qdrant requires, and would produce collection growth proportional to
  reindex events across every workspace, a worse version of the same problem;
- **one universal collection spanning multiple embedding spaces** — current Qdrant
  versions can technically host more than one named vector space inside a single
  collection (for example, adding a new named vector for a model migration,
  populating it in the background, and selecting it explicitly at search time), so
  this option is rejected as a deliberate architectural choice, not because Qdrant is
  incapable of it. A dedicated collection per embedding-space generation is
  preferred because it:
  - aligns one compatibility boundary with one physical lifecycle boundary;
  - simplifies completeness verification (see "Completeness verification" above) to
    a single collection's contents, rather than a filtered subset of a collection
    that accumulates every model generation the platform has ever used;
  - simplifies per-workspace rollout and rollback, which then operate against a
    stable, already-compatible target rather than a collection whose own schema is
    mid-migration;
  - permits whole-space retirement and deletion once a superseded embedding-space
    generation's confidence window has passed, rather than a filtered delete against
    an ever-growing shared schema;
  - avoids letting one universal collection schema accumulate successive model
    generations indefinitely, with no natural point at which an old one is fully
    removed;
  - keeps provider-specific named-vector migration mechanics — adding, populating
    and switching a named vector on an existing collection — inside the
    `VectorStore` adapter, rather than exposing them as part of the platform's
    domain model, where every consumer would otherwise need to know which named
    vector on a shared collection a given embedding-space generation corresponds to.

Multiple workspace corpus generations may temporarily coexist inside the same
compatible collection during a rebuild or a staged rollout — this is the expected,
normal state during any migration, not an edge case to be avoided. Every search and
mutation against Qdrant must be scoped explicitly by the identifiers that make that
coexistence safe. At minimum, every retrieval-time query filters on:

- `workspace_id`;
- `workspace_corpus_generation_id`.

Omitting the second field once more than one corpus generation exists for a
workspace inside a collection would let a mid-migration workspace receive mixed
results from two different builds of its own corpus — this is the one real
complexity cost of this topology, and it is treated as a mandatory invariant rather
than an implementation detail precisely because of that risk.

The collection itself supplies the embedding-space compatibility boundary; nothing
inside the payload needs to re-establish dimension or distance-metric compatibility,
because an incompatible vector cannot physically be written into the wrong
collection in the first place.

Collection topology in this ADR is scoped to a single environment's Qdrant
deployment. Separation between environments (development, staging, production)
remains an infrastructure-deployment concern — separate Qdrant instances per
environment, the same pattern already used for PostgreSQL and for LocalStack/AWS
(ADR 0004) — not a Qdrant collection-naming concern, and this ADR does not introduce
one.

### Minimal Qdrant payload

The V1 payload is deliberately minimal, sufficient for scoping a query and for
hydrating the authoritative record afterward, and nothing more:

- `workspace_id` — the mandatory tenant-isolation filter;
- `document_id` — supports document-scoped deletion and reindexing without a
  chunk-by-chunk lookup;
- `chunk_id` — identifies the source chunk for hydration and participates in
  deterministic point-identity derivation (below);
- `workspace_corpus_generation_id` — the mandatory corpus-generation filter that
  makes safe coexistence during rollout possible, and the field a filtered delete
  targets once a superseded generation's confidence window has passed;
- `embedding_space_generation_id` — included even though the containing collection
  already implies it, as a defensive, explicitly-checked field rather than an
  implicitly trusted one. This follows the same discipline ADR 0013 already applies
  to embedding results, which validate the profile fingerprint on every response
  rather than trusting that a batch returned what was asked for — a completeness or
  compatibility check should be able to assert "every point in this collection
  actually claims to belong to the generation this collection is nominally for"
  without relying on collection placement alone.

Deliberately excluded by default: chunk text, headings or other structural signal,
full provenance, and broader document metadata. All of it already lives in
PostgreSQL and stays there; a retrieved point's IDs are used to hydrate the
authoritative chunk and its provenance, not to avoid a lookup. This mirrors ADR
0007's framing of the vector layer as thin and derived, and ADR 0012's
allowlist-first posture applied one layer further into the pipeline: default to the
minimum that scoping and hydration require, and treat any widening of that default as
a decision that needs its own justification.

If a future, *measured* latency problem shows the extra PostgreSQL round-trip is a
real cost, widening the payload is a legitimate response — but it should be an
evidence-based decision made against that measurement, the same standard ADR 0013
already sets for retrieval-quality changes generally, not a default chosen now on the
assumption that it might matter.

### Payload indexes

Qdrant payload indexes are required, created before point ingestion begins, for the
fields every retrieval query filters by:

- `workspace_id`;
- `workspace_corpus_generation_id`.

`document_id` is also indexed: it is the field document-scoped deletion and
reindexing filter by (see "Storage ownership" and "Ordinary ingestion versus
coordinated corpus rebuild" above), and an unindexed filter on that field would force
a full collection scan for what is otherwise a routine, frequent operation.

No other payload field is indexed by default. `chunk_id` and
`embedding_space_generation_id` are retained for point-identity derivation and
defensive validation respectively (see above), not for filtering point volumes at
query time, and gain an index only if a real query or delete pattern is later found
to need one — consistent with this ADR's general posture of adding capability only
where a demonstrated need justifies it, not by default.

Each indexed identifier uses one stable payload type consistently across every point
that carries it — the exact type (for example, a UUID represented as a
fixed-format string, or a keyword type) is a Stage 14.3 implementation decision, but
it must not vary between points, or index and filter behaviour would silently become
unreliable.

### Deterministic point identity

A Qdrant point's identifier is derived deterministically, not generated as a fresh
random UUID per write, so that a retried or duplicated ingestion message upserts the
same point rather than creating a duplicate — directly satisfying Stage 14.3's own
"duplicate messages do not duplicate vectors" acceptance criterion, and extending the
same deterministic-identity discipline ADR 0011 already applied to `Chunk.id`.

The identity must be derived from, at minimum:

- the embedding-space generation;
- the workspace;
- the workspace corpus generation;
- the chunk identifier.

This ADR constrains the required inputs without fixing the exact derivation (hash
function, UUID namespace, field ordering) — that belongs to Stage 14.3, the same way
ADR 0011 left the equivalent choice for chunk identity to whichever stage first
implemented the chunk model.

### VectorStore boundary

The pipeline depends on an application-owned, provider-neutral `VectorStore`
abstraction; no application code outside one isolated adapter imports or depends on
Qdrant-specific concepts, including `PointStruct`, Qdrant's filter/condition DSL,
Qdrant exception classes, transport configuration, Qdrant distance-metric enums,
collection-alias implementation details, or Qdrant request/response models. This is
the same Open/Closed reasoning already applied to `Element` (ADR 0010),
`ChunkingStrategy` (ADR 0011) and `Embedder` (ADR 0013), carried to this pipeline
seam.

The contract owns platform-level operations:

- ensuring a compatible vector space exists for a given embedding-space generation,
  including the payload indexes "Payload indexes" above requires, created before
  ingestion begins;
- bounded point upsert;
- scoped search (query vector, filter, result count);
- scoped count/completeness verification;
- scoped delete;
- removal of an unused physical vector space, where the underlying provider supports
  it.

**Activation is deliberately kept out of the `VectorStore` contract.** Activation
decides which corpus generation is currently authoritative and searchable for a
workspace — that is a domain and lineage decision belonging to PostgreSQL, the same
way a Document's lifecycle transition belongs to the Document model and not to the
object-storage abstraction that happens to hold its bytes (ADR 0007). Putting
activation inside `VectorStore` would let a provider-neutral storage abstraction make
a business-lifecycle decision, inverting the dependency direction this platform has
kept consistent at every other boundary: PostgreSQL decides, and instructs the
storage layer what to do; the storage layer never decides on PostgreSQL's behalf.
Lifecycle methods are not added to `VectorStore` merely because Qdrant happens to
expose collection-management operations that resemble them.

### V1 vector configuration

The concrete V1 physical configuration, compatible with the ADR 0013 profile:

- 1024 dimensions;
- float vectors;
- cosine distance.

Cosine is the explicit, self-documenting choice given Voyage's unit-length
normalisation (ADR 0013): for unit-normalised vectors, cosine similarity and dot
product produce identical rankings, so this is not a meaningfully consequential
choice between two different retrieval behaviours — it is a choice between two
mathematically equivalent ways of expressing the same one. Cosine is preferred for
clarity; dot product remains available as a performance-motivated alternative Stage
14.2 may adopt if a measured need justifies it, without changing retrieval behaviour.

Dimensions and distance metric are recorded as **embedding-space configuration**,
belonging to a specific embedding-space generation, not as permanent platform-wide
constants — a future profile with different dimensions or a provider that does not
unit-normalise would record different values without requiring this ADR to be
revisited.

### Named vector schema

Qdrant supports multiple named vectors per point, including named sparse vectors,
and its hybrid-search capability is built on named vector configuration. Current
Qdrant versions also support adding or removing a named vector on an existing
collection — populating a newly added vector in the background and selecting it
explicitly at search time — so naming the V1 dense vector is not required to avoid a
future collection migration; Qdrant does not force that trade-off
([Qdrant: Vectors](https://qdrant.tech/documentation/manage-data/vectors/),
[Qdrant: Sparse Vectors and Inverted Indexes](https://qdrant.tech/course/essentials/day-3/sparse-vectors/)).

V1 uses one named dense vector (for example, `dense`) rather than Qdrant's legacy
unnamed default vector field, justified on its own merits rather than as a
workaround for a migration constraint that does not actually exist:

- it makes the vector's purpose explicit in the schema, rather than relying on an
  implicit, unnamed default whose meaning is only established by convention;
- it establishes the schema shape a later dense/sparse or hybrid representation would
  extend, so that shape is deliberate rather than backed into;
- it reduces the number of adapter and query-contract changes a future named-vector
  addition would require, since the query contract already names which vector it
  means;
- it costs effectively nothing now, since V1 has exactly one vector representation
  either way.

### Future-proofing

This architecture must not block, and this ADR asserts each of the following remains
achievable without redesigning the model above:

- future embedding profiles — a new embedding-space generation and collection;
- multiple embedding-space generations coexisting during a migration — the explicit
  purpose of the embedding-space/workspace-corpus split;
- workspace-specific profile overrides, later — the `workspace override ?? platform
  default` resolution seam, unpopulated but present;
- sparse or additional named vectors — the named dense vector schema decision above;
- hybrid retrieval, reranking, query decomposition — none of these affect storage
  schema; they operate on candidates `VectorStore.search` already returns;
- corpus-only reindexing — the workspace corpus generation concept exists
  specifically for this;
- controlled rollback — the retained-superseded-points-with-confidence-window
  design.

None of these capabilities is implemented by this ADR or by Phase 14. Naming them
here is what lets Phase 15 and beyond inherit a settled storage foundation instead of
re-deriving one, the same way ADR 0013's "forward architectural decisions" section
did for retrieval.

## Alternatives considered

### Collection per workspace

Rejected. Not required for security — ADR 0006 already establishes that workspace
authorisation happens before the AI service is ever invoked, and that vector payload
scoping is a defence-in-depth layer, never the primary boundary. Reproduces, at the
vector-storage layer, the same operational complexity (per-tenant migrations,
backup/restore multiplied by tenant count) ADR 0006 already rejected for
database-per-tenant and schema-per-tenant PostgreSQL isolation, at a scale bounded
only by tenant count rather than by anything under the platform's control.

### Collection per workspace corpus generation, or collection per reindex event generally

Rejected. Ties physical storage partitioning to a lifecycle event with no natural
partition boundary at the Qdrant level. Produces collection growth proportional to
reindex events across every workspace — potentially worse than collection-per-tenant
— and conflates a PostgreSQL lifecycle concept with a storage-partition concept the
two-generation model above exists specifically to keep separate.

### One universal collection spanning multiple embedding spaces

Rejected, but not because Qdrant is technically incapable of it — current Qdrant
versions support adding a new named vector to an existing collection, populating it
in the background, and selecting it explicitly at search time, so a single
collection could, in principle, host successive embedding-space generations as
additional named vectors. Rejected instead as a deliberate architectural choice: it
would let one collection's schema accumulate every model generation the platform has
ever used with no natural retirement point, complicate completeness verification
(which would need to reason about a filtered subset of a shared schema rather than a
whole collection), and push provider-specific named-vector migration mechanics into
the platform's domain model rather than containing them inside the `VectorStore`
adapter. A collection boundary already aligned to one compatibility boundary is
simpler to build rollout, rollback and retirement against — see "Collection
topology" above.

### Treating every document upload as a new workspace corpus generation

Rejected. This would make a workspace corpus generation fire on every routine
upload, contradicting its definition as a coordinated-rebuild concept, and would
require rebuilding or re-verifying an entire workspace's corpus for what is
ordinarily a single-document, incremental change. Ordinary ingestion instead extends
the currently active corpus generation directly — see "Ordinary ingestion versus
coordinated corpus rebuild" above.

### Requiring independent workspace embedding-profile selection in V1

Rejected for V1, not permanently. Re-reading ADR 0006 in light of its own stated
purpose (a tenancy-classification exercise for a still-unimplemented ADR) shows this
was never a V1 product requirement. Building a live override mechanism now, with no
workspace that has a demonstrated need for a different provider or model, is exactly
the premature-complexity pattern ADR 0010 and ADR 0013 already argue against
elsewhere in this pipeline. The seam is preserved; the feature is not built.

### Treating "vector generation" as one undifferentiated concept

Rejected. Conflating a profile-driven compatibility change with a corpus-only rebuild
would force every corpus-only rebuild through collection-level machinery it does not
need, and would misalign this ADR's vocabulary with ADR 0013's own, narrower,
already-accepted definition of "vector generation." The two-concept model keeps ADR
0013 unchanged and gives Phase 14 the second concept it actually needs.

### Persisting raw vector arrays in PostgreSQL

Rejected. This would duplicate Qdrant's own purpose into the relational database — a
pgvector-shaped scope expansion — for a cost-avoidance benefit that a bounded,
occasional re-embedding pass already provides, without a demonstrated need for
zero-cost rebuild. Vectors have exactly one owner: Qdrant.

### A fatter Qdrant payload including chunk text and full provenance

Rejected as the V1 default. Duplicating content PostgreSQL already owns into Qdrant
reintroduces a second, driftable copy of that content, contradicting ADR 0007's
framing of the vector layer as thin and derived. A measured, evidence-based latency
requirement remains a legitimate future reason to widen the payload — the same
evidentiary standard ADR 0013 already applies to retrieval-quality changes — but is
not assumed here.

### Randomly generated point identifiers

Rejected. Breaks idempotent upsert on a retried or duplicated ingestion message,
directly conflicting with Stage 14.3's own acceptance criteria and with the
deterministic-identity discipline ADR 0011 already established for `Chunk.id`.

### Folding activation into the `VectorStore` contract

Rejected. Activation is a decision about which corpus generation is currently
authoritative — a PostgreSQL/domain concern, the same way a Document's lifecycle
transition belongs to the Document model rather than to the object-storage
abstraction underneath it. Placing it inside a provider-neutral storage contract
would let that contract make a business-lifecycle decision, inverting the dependency
direction this platform has kept consistent at every other pipeline boundary.

### An unnamed default vector for V1, deferring the named-vector decision entirely

Considered and rejected in favour of naming the V1 dense vector now. The cost of
naming it is negligible — V1 has exactly one vector kind either way — and doing so
removes a specific, documented future migration question rather than leaving it to
be discovered when a sparse vector is actually needed.

## Consequences

### Positive

- Rebuildability is now a precise, testable operational property — "re-embed
  persisted chunk text and re-upsert" — rather than an aspiration carried forward
  unexamined from ADR 0007.
- Collection growth is bounded by the number of embedding spaces the platform has
  ever actually adopted, not by tenant count or by reindex frequency.
- Staged, per-workspace rollout of any future corpus rebuild is supported natively,
  without redesigning storage topology when the first real migration happens.
- `VectorStore` isolates the rest of the application from Qdrant exactly as
  `Embedder` already isolates it from Voyage, keeping the fourth AI-pipeline seam
  consistent with the first three.
- The workspace-profile-override seam is preserved architecturally at zero V1
  operational cost, consistent with the platform's repeated preference for deferring
  a capability's cost until a concrete need justifies it.
- Generation identity now propagates explicitly, the same way tenant identity
  already does, closing a specific class of implicit-default bug before Stage 14.3
  is written.
- The embedding-space/workspace-corpus split matches a pattern this pipeline has
  already used twice (chunking strategy/config vs. individual chunk; embedding
  profile vs. individual embedding result), rather than introducing a new shape of
  distinction.
- Ordinary document ingestion, deletion and archival remain cheap, incremental
  operations against the active corpus generation, never triggering a workspace-wide
  rebuild for a single document.
- Payload indexes are scoped to the fields actual query and delete patterns need
  (`workspace_id`, `workspace_corpus_generation_id`, `document_id`), avoiding
  indexing overhead on fields that do not require it.

### Negative

- PostgreSQL must now durably persist canonical chunk text — a real, ongoing storage
  and lifecycle commitment this ADR settles explicitly, where ADR 0007 had
  deliberately left it open.
- Retrieval and ingestion code must always filter on two identifiers
  (`workspace_id` and `workspace_corpus_generation_id`), not one; omitting the
  second once more than one corpus generation coexists is a real correctness risk
  this ADR treats as a mandatory invariant rather than an implementation detail.
- Two generation concepts (embedding-space and workspace-corpus) are more
  conceptual surface than a single undifferentiated "generation" would have been —
  accepted because it is what actually matches the two different triggers that
  create a new one, not adopted for its own sake.
- Per-workspace activation, rollback and confidence-window purge are real,
  non-trivial implementation work for Stage 14.3 and 14.4, not a free consequence of
  choosing this topology.
- Defensive redundancy in the payload (`embedding_space_generation_id`, checkable
  even though collection placement already implies it) is a small amount of
  additional storage and validation logic whose only payoff is catching a class of
  error that should, by construction, already be impossible.
- Keeping a candidate workspace corpus generation caught up against concurrent
  ordinary ingestion, edits, archival and deletion is real, non-trivial coordination
  work for Stage 14.3/14.4, on top of the build-and-verify work already noted — the
  migration-concurrency invariant is easy to state and non-trivial to implement
  correctly.

## Architectural invariants

- The vector layer remains a disposable, rebuildable projection; PostgreSQL remains
  authoritative for document, chunk, lineage and lifecycle data. This ADR does not
  change that position.
- "Generation" is never used unqualified where the meaning could be ambiguous: it is
  either an embedding-space generation or a workspace corpus generation.
- An embedding-space generation is created only by a consequential embedding-profile
  change; a workspace corpus generation is created by any consequential corpus
  rebuild trigger, with or without a profile change.
- One embedding-space generation maps to exactly one Qdrant collection. A workspace
  corpus generation never receives its own collection.
- V1 uses a single platform-selected embedding profile for all workspaces; the
  workspace-override resolution seam exists but is unpopulated.
- Active workspace corpus generation is tracked per workspace in PostgreSQL and
  resolved explicitly before every call into `VectorStore` — never inferred by the
  AI service from Qdrant state.
- Every Qdrant query and mutation is scoped by explicit identifiers; retrieval-time
  queries filter on at least `workspace_id` and `workspace_corpus_generation_id`.
- Qdrant payload is minimal by default (`workspace_id`, `document_id`, `chunk_id`,
  `workspace_corpus_generation_id`, `embedding_space_generation_id`); chunk text and
  broader metadata are hydrated from PostgreSQL by ID, not duplicated into Qdrant.
- Point identity is derived deterministically from embedding-space generation,
  workspace, workspace corpus generation and chunk identity — never a fresh random
  UUID per write.
- No application code outside one isolated adapter depends on Qdrant-specific types,
  exceptions, transport details or request/response models.
- Activation is a PostgreSQL/domain operation and is not exposed through
  `VectorStore`.
- Raw vector arrays are never duplicated into PostgreSQL.
- Ordinary, successful document ingestion extends the workspace's active corpus
  generation; it never creates a new workspace corpus generation on its own. A new
  workspace corpus generation exists only for a coordinated corpus rebuild.
- A Document becomes searchable only once every one of its expected chunk points has
  been persisted and verified; a partial or failed vector write is never exposed as
  searchable.
- A candidate workspace corpus generation is never activated unless it has
  incorporated every authoritative PostgreSQL change up to its own cutover boundary.
- At most one workspace corpus generation is `ACTIVE` per workspace at any time; an
  `ACTIVE` workspace corpus generation always references an `AVAILABLE`
  embedding-space generation; an embedding-space generation is never described as
  globally active.
- `workspace_id`, `workspace_corpus_generation_id` and `document_id` carry Qdrant
  payload indexes, created before point ingestion begins; no other field is indexed
  without a demonstrated query or delete need.
- Completeness verification compares expected and actual point identities, payload
  identifiers and vector schema against PostgreSQL's authoritative chunk set — count
  equality alone is never sufficient.

## Scope boundaries

This ADR does not define:

- retrieval ranking, top-k behaviour, or scoring beyond what `VectorStore.search`
  returns as ordered candidates — Phase 15;
- query planning or decomposition — Phase 15, Stage 15.2, already recorded as
  forward direction in ADR 0013;
- hybrid dense/sparse fusion or reranking — Phase 15, Stage 15.6, already recorded
  as forward direction in ADR 0013;
- evidence thresholds, calibrated abstention, or evaluation and quality-gate
  mechanics — Phase 15, Stage 15.4;
- generation (LLM answer synthesis) or citation design — Phase 16;
- the full cross-system deletion orchestration saga (PostgreSQL, object storage,
  Qdrant, audit records together) — already deferred by ADR 0006 and ADR 0007; this
  ADR supplies only the scoped-delete primitive that orchestration would call;
- data-retention and hard-purge policy — already deferred by ADR 0006 and ADR 0007;
- detailed worker scheduling, job coordination, or progress-tracking mechanics for a
  running re-embedding or corpus rebuild — Stage 14.3/14.4 implementation, not this
  ADR;
- backup and snapshot operations for Qdrant collections — an operational concern
  distinct from this ADR's rebuildability invariant, which does not depend on Qdrant
  backups existing at all.

These remain open for the phases and stages named above to decide with the context
appropriate to each.
