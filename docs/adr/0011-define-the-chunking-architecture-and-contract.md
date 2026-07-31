# ADR 0011: Define the Chunking Architecture and Chunk Contract

## Status

Accepted

## Date

2026-07-30

## Context

Phase 10 (ADR 0010) established a canonical, immutable pipeline:
`ExtractedDocument`, produced by a format-specific extractor, is transformed by
a structural normaliser into an immutable `NormalisedDocument` — already a
self-describing domain model carrying its own `workspace_id` and
`document_id`, a typed `Element` model, source provenance and deterministic
derived identifiers.

Phase 11 must take that `NormalisedDocument` and produce chunks: the
retrieval-sized units that Phase 13 will embed, that Phase 15 will retrieve,
and that Phase 16 will eventually cite. Before any of that can happen, the
platform needs an architectural contract chunking conforms to — the same way
ADR 0010 defined what every extractor must produce before Stage 10.2–10.4
picked specific parsing libraries.

This ADR defines that contract: the boundary a `ChunkingStrategy`
implementation must respect, what a `ChunkingResult` represents, and what
guarantees a chunk carries. It does not choose chunk sizes, overlap
behaviour, or any specific chunking algorithm — those are Stage 11.2
concerns, exercised against the contract this ADR defines.

## Decision

### Chunking is a deterministic, versioned transformation

Chunking is a single-responsibility pipeline stage: it transforms one
immutable `NormalisedDocument` into one immutable `ChunkingResult`, and does
nothing else. It does not re-extract, does not re-normalise, and does not
call embeddings or vector storage. Like extraction and normalisation before
it, its entire job is to produce one well-defined, versioned representation
from the representation the previous stage produced.

### The `ChunkingStrategy` boundary: a document in, a result out — no context wrapper

```text
ChunkingStrategy.chunk(document: NormalisedDocument) -> ChunkingResult
```

No `ChunkingContext` or `ChunkingInput` wrapper is introduced. This is a
direct application of a pattern the codebase already establishes, not a new
one invented for this ADR.

`ExtractionContext` exists in Stage 10.2–10.4's extractors
(`extract(source: bytes, *, context: ExtractionContext)`) for a specific
reason: a raw byte stream has no identity of its own. Extraction needs
somewhere external to inject `workspace_id` and `document_id` into a process
whose input carries none. A context object earns its place only when the
artefact being processed cannot intrinsically carry the identity a consumer
needs.

`NormalisedDocument` is not in that position. It already carries its own
`workspace_id` and `document_id` as required fields — it is a self-describing
domain model, not a bare payload. Wrapping it in a `ChunkingContext` would not
add missing identity; it would duplicate identity the document already has,
creating two places that could in principle disagree about which workspace or
document is being processed. The architectural principle is: inject identity
externally only where the input cannot carry it itself; once an artefact is
self-describing, let it describe itself, and pass it as-is.

### A pluggable strategy boundary

The pipeline depends on the `ChunkingStrategy` abstraction, not on a concrete
chunker. This is the same Open/Closed reasoning ADR 0010 already applied to
the `Element` model: the set of chunking strategies is open for extension, but
everything that calls `ChunkingStrategy` and everything that consumes
`ChunkingResult` is closed for modification when a new strategy is added.

Phase 11's initial implementation (Stage 11.2) is one deterministic,
structure-aware baseline strategy. Future strategies — semantic, contextual,
model-assisted, or hybrid — can be introduced later without changing the
pipeline code that invokes chunking or consumes its output.

### Determinism

Given an identical `NormalisedDocument`, an identical strategy, an identical
strategy version, and identical *consequential* configuration, chunking must
produce the same semantic chunk set every time.

"Consequential" is deliberate: not every configuration value necessarily
changes what chunks are produced. A logging-verbosity setting, for example,
has no bearing on chunk boundaries. Determinism is scoped to whatever
configuration actually participates in deciding chunk content and
boundaries — but exactly because that scope must be unambiguous, the
configuration that does participate is captured as part of the result (see
"`ChunkingResult`" below), so a later chunk set can always be traced back to
precisely what produced it.

This extends, rather than introduces, an architectural value ADR 0007 and
ADR 0010 already established: replayability and deterministic processing are
treated as first-class properties of this pipeline, not incidental
conveniences. Determinism is also what makes Stage 11.3's evaluation work
possible at all — comparing chunk-size distributions or checking for text
loss only means something if re-running chunking against the same input is
guaranteed to reproduce the same result.

An identical `NormalisedDocument` means an equivalent immutable input
value — the same identity-bearing fields, the same ordered elements, the
same provenance, and the same content — not merely the same object instance
in a given process. Two separately produced `NormalisedDocument` values that
carry the same content and identity are the same input for the purpose of
this guarantee.

Determinism must also survive the arrival of a model-assisted strategy, and
that is not automatic. Sending the same request to an external model does
not, by itself, guarantee the same response — providers can change model
behaviour, sampling can be non-deterministic, and infrastructure can vary a
response even under nominally fixed parameters. A model-assisted strategy
satisfies this contract only if the concrete model identity and version, the
consequential parameters, and the model-produced decisions that shaped the
chunk set are sufficiently fixed, retained or cached to reproduce that same
semantic chunk set on demand. A strategy that cannot provide this must not
claim conformance to the deterministic `ChunkingStrategy` contract — it is
not a lesser implementation of this ADR, it is an implementation that has
not met it.

### Immutability

`NormalisedDocument` is already immutable (ADR 0010). `ChunkingResult` and
`Chunk` are immutable in the same sense: once produced, nothing overwrites
them, and the chunker never mutates the `NormalisedDocument` it was given.
This carries forward the same benefits ADR 0010 already argued for
extraction's output — data integrity, auditability, replayability,
deterministic processing, debugging, and a foundation for future
event-driven processing — rather than re-deriving them independently for
chunking. A pipeline stage that mutated its input in place would make it
impossible to compare what normalisation actually produced against what
chunking did with it, undermining exactly the auditability this pipeline is
built around.

### Chunk provenance

A chunk is a retrieval unit, not a bare substring. It preserves provenance
back to the normalised content it was built from. A single chunk may span
multiple source elements — several short paragraphs combined into one chunk,
or, in principle, one element's content divided across chunks — and in every
case, each contribution to a chunk remains attributable back to the
originating `NormalisedElement` it came from. This mirrors the same
traceability `NormalisedElement` already provides back to `Element`
(`source_element_ids`): the chain of custody from a chunk back to a source
location is meant to hold all the way through the pipeline, not just at one
stage boundary.

This provenance exists to support future citation, inspection and
traceability. It is deliberately not this ADR's job to design what a citation
actually looks like, how it is presented, or how it survives re-extraction —
that is Phase 16's work, and the citation/re-extraction design constraint
already recorded in `PROJECT_ROADMAP.md`. This ADR's obligation is narrower:
ensure chunking does not discard the information a future citation feature
would need, consistent with ADR 0010's core principle that information
discarded early can never be recreated later.

### Completeness: a successful result accounts for all content

A warning does not make an incomplete result acceptable. A successful
`ChunkingResult` must account for all content that the canonical normalised
model classifies as chunkable. This is deliberately not phrased as "every
part of the input a chunker can represent" — that framing would let an
implementation define ordinary content out of scope simply by declining to
represent it. Any structural or metadata field that is intentionally
non-chunkable must be excluded through an explicit, documented rule, not by
an implementation silently choosing not to handle it. Content may be
repeated across chunks only through deliberate, recorded overlap or
contextual enrichment, never as an accidental side effect of how a strategy
assembled its chunks. If content that is classified as chunkable cannot be
represented safely without loss, chunking must fail with a typed error
rather than return an incomplete result dressed up as a success.

This is a stricter requirement than "warn and continue." A warning remains
the right tool for a recoverable compromise the strategy has actually
handled — splitting an oversized element across chunk boundaries, missing a
preferred target size, a table-specific layout compromise, or an explicit,
recorded fallback for content the primary approach could not handle
cleanly. A warning is not the right tool for content the strategy simply
could not place anywhere; that is a failure, not a footnote, and must be
surfaced as one.

### Chunk identity: deterministically derived, not randomly generated

A chunk's identifier is derived deterministically from the semantic inputs
that define the chunk, rather than generated as a fresh random UUID per run:

- the normalised document's own identity material;
- the strategy's identity and version;
- the consequential configuration fingerprint;
- the chunk's ordinal within the document;
- its ordered provenance spans (which source elements, in what order,
  contributed to it); and
- the chunk's final semantic content, or a canonical digest of it.

Including the final content — not only the inputs that were meant to
produce it — means identity reflects what a chunk actually is, not merely
what was asked for. Two runs given the same document, strategy, version and
configuration are expected to produce identical content and therefore
identical identities; if they ever diverged, that divergence itself is
worth surfacing rather than being masked by an identity computed only from
the inputs. The exact derivation scheme — hashing approach, UUID
namespace, field ordering — is an implementation detail this ADR constrains
but does not fix; it belongs to whichever stage first implements the chunk
model.

This mirrors `NormalisedElement`'s own approach (a `uuid5` derived from its
source element's identity, kind and text) and exists for the same reason:
re-running the same deterministic chunking operation over the same
normalised document must reproduce the same chunk identities, not merely
the same chunk text. A randomly generated identity per run would silently
break that guarantee at the identity layer even if every chunk's boundaries
and content were unchanged, and would make two runs of the same operation
impossible to compare or diff.

This is a deliberate contrast with `ExtractedElement`, which is assigned a
fresh identity on every extraction run rather than a deterministic one. That
is not an inconsistency — each choice fits the stage it belongs to. An
extraction run's whole purpose (established when reviewing ADR 0010) is to be
one immutable snapshot among potentially several independent snapshots of the
same document, so a fresh identity per run is exactly what "a new snapshot"
should mean. Chunking's input, by contrast, is not itself in question the way
"which extraction run" was — a `NormalisedDocument` is a fixed, complete
value, and running the same deterministic operation over that same fixed
value should yield the same identities. There is no ambiguity here for a
deterministic identity to preserve, unlike the fresh-extraction case, so a
derived identity costs nothing and buys comparability that a random one
would not.

### No persistent extraction identity — chunking operates on a complete value

Extraction is treated as a transient transformation rather than a durable
domain entity: there is no persistent extraction identifier anywhere in this
pipeline, by design. The consequence for chunking is architectural, not
incidental: `ChunkingStrategy` operates directly on the `NormalisedDocument`
value it is given, not on a reference to "the extraction that produced it."
The `NormalisedDocument` is a complete, self-contained representation —
chunking has everything it needs from that value alone, with no separate
extraction record to look up or depend on existing. This keeps every stage
in the pipeline consistent with the one before it: each stage receives a
complete, immutable value from its predecessor and produces a complete,
immutable value in turn, rather than a key into some other persisted
resource it would need to resolve first.

This is narrower than a blanket rejection of ever persisting a chunking
result, and it is a statement about the invocation, not about a strategy's
implementation. A `ChunkingStrategy` may still be constructed with typed
configuration, a tokenizer, or other implementation dependencies — supplying
those is ordinary dependency injection, not a violation of this principle.
What this ADR rejects is narrower and specific: the *invocation* itself —
`chunk(document: NormalisedDocument)` — must operate directly on the
supplied document without requiring a reference to, or a lookup of, a
persisted extraction or chunking-run entity. Configuration and dependencies
are supplied independently of the call and do not depend on resolving any
such record either. Whether a produced `ChunkingResult`, a chunk set,
individual processing attempts, active-versus-historical generations,
embedding lineage, or operational audit records are themselves persisted
afterward is a legitimate and expected concern — just one that belongs to
later orchestration, vector-storage and operations phases, not to the
architecture of the `ChunkingStrategy` boundary itself.

### Token counting

Token counts must be reproducible: the same content processed with the same
tokenizer always yields the same count. Tokenizer identity is recorded once,
as part of the chunking configuration, rather than repeated on every
individual chunk — the tokenizer used is a single fact about the operation
as a whole, the same way `NormalisedDocument` records one `source_extractor`
and one `normaliser` identity rather than repeating them per element. Each
individual chunk still records its own token count, since that genuinely
does vary chunk to chunk, unlike the tokenizer identity that produced it.

Recorded tokenizer identity must be precise enough to resolve the exact
tokenisation behaviour actually used — a bare name is not sufficient if that
name's behaviour can change underneath it. Where applicable, this includes
the vocabulary or model revision and the implementation or library version
that performed the tokenisation. This ADR does not prescribe the exact
representation of that identity; it requires only that whatever is recorded
is sufficient to reproduce the same token counts later.

### `ChunkingResult`

`ChunkingResult` represents the outcome of one deterministic chunking
operation — the same way `NormalisedDocument` represents the outcome of one
deterministic normalisation operation. It draws a firm line between the
semantic outcome and everything operational that happened while producing
it, and only the former belongs to the result itself.

The semantic outcome — what `ChunkingResult` conceptually contains — is:

- the strategy's identity and version;
- the consequential configuration, retained in two complementary forms: a
  typed, canonical **snapshot** and a deterministic **fingerprint** derived
  from it;
- the produced chunks and their provenance;
- semantic warnings (the recoverable compromises described above); and
- for a model-assisted strategy, the concrete model identity/version and
  the consequential parameters that were actually used — because these can
  change what chunk set is produced, they are part of what a chunk set is
  attributable to, not incidental detail about how it was produced.

The snapshot and fingerprint serve different purposes and neither
substitutes for the other. The snapshot is what supports inspection and
replay — a human or a later process can read it and know exactly what
configuration produced this result, and can re-run chunking with it. The
fingerprint is what supports comparison and identity derivation — a short,
deterministic value that two results can be checked against without
comparing every configuration field individually, and the value chunk
identity (above) actually incorporates.

Operational usage and execution information is kept separate from that
semantic outcome, not folded into it: wall-clock duration, a
model-assisted strategy's call count, its input/output token usage,
estimated cost, runtime or host details, and tracing or processing-attempt
data. These may be recorded by orchestration or instrumentation wrapped
around the strategy, rather than being constructed as part of the semantic
`ChunkingResult` itself. The distinction is not about which fields sound
like "usage" — a model's identity and the parameters it was actually run
with belong to the semantic outcome because they can change the chunk set;
how long that call took, or what it cost, cannot. The semantic outcome
(what chunks were produced, from what document, by which strategy,
configuration and, where applicable, model) is what must be reproducible
and comparable across runs; execution telemetry varies run to run for
reasons that have nothing to do with the chunking decision itself, such as
machine load. Folding the two together would make the semantic outcome look
non-deterministic when only its incidental execution detail varied — the
same instinct ADR 0008 already applied when it kept an outbox record's
durable intent separate from its publication-attempt telemetry.

### Architectural invariants

- No mutation of input: neither the `NormalisedDocument` nor any produced
  `Chunk` is altered after creation.
- Deterministic output: an identical document, strategy, version and
  consequential configuration produce an identical chunk set and identical
  chunk identities, and a model-assisted strategy is conformant only if it
  can actually reproduce that guarantee.
- Completeness over silent loss: a successful result accounts for all
  chunkable content, with repetition only through deliberate, recorded
  overlap or enrichment; content chunking cannot safely represent causes a
  typed failure, not an incomplete success — consistent with ADR 0007's
  treatment of extraction warnings and ADR 0010's loss-minimisation
  principle, but stricter where completeness itself is at stake.
- Source provenance is preserved: every chunk traces back to the
  `NormalisedElement`(s) it was built from.
- Chunk ordering is preserved: chunks reflect the reading order already
  established by `NormalisedDocument`.
- Strategy identity, strategy version, and both the configuration snapshot
  and its fingerprint, are retained on the result, so any chunk set is
  traceable to exactly what produced it.
- Semantic result and execution telemetry remain conceptually separate,
  regardless of which component constructs each.
- Future extensibility is achieved through `ChunkingStrategy`: a new strategy
  is additive, not a change to existing consumers.
- No dependency on a persistent extraction or chunking "run" record: the
  invocation contract accepts only a `NormalisedDocument`; strategy
  configuration and implementation dependencies are supplied independently
  and must not require resolving a persisted pipeline-run entity.

## Alternatives considered

### Treating unplaceable content as a warning-only, best-effort result

Rejected as the response to content chunking cannot safely represent. A
warning is the right tool for a compromise the strategy actually handled;
using it for content that was simply dropped would let a "successful" result
silently be incomplete, which is a stronger and more dangerous failure mode
than an outright error — a caller has no reason to distrust a result that
claims success. A typed failure makes the gap visible where it occurs
instead of letting it surface later as an inexplicable retrieval gap.

### A `ChunkingContext`/`ChunkingInput` wrapper alongside `NormalisedDocument`

Rejected. `NormalisedDocument` already carries the `workspace_id` and
`document_id` a consumer needs. A wrapper would duplicate that identity
rather than supply identity the document lacks, creating two places that
could disagree rather than one source of truth. `ExtractionContext` exists
because raw bytes have no identity to begin with — that condition does not
hold here.

### Randomly generated chunk identifiers

Rejected. A fresh identity per chunking run — mirroring how `ExtractedElement`
identities are fresh per extraction run — would break the ability to compare
or diff two runs of the same deterministic operation over the same document,
undermining the determinism guarantee at the identity layer even when chunk
content is unchanged. Unlike extraction, chunking's input is already a fixed,
complete value with no "which run is this" ambiguity for a fresh identity to
preserve.

### A concrete chunker used directly, with no `ChunkingStrategy` abstraction

Rejected. This would couple every downstream consumer to one specific
chunking algorithm's implementation — the same coupling problem ADR 0010
already solved for extractors. A future semantic or model-assisted strategy
would then require changing consumer code instead of being an additive
implementation of an existing abstraction.

### Recording tokenizer identity per chunk rather than per configuration

Rejected. The tokenizer used is a constant fact about one chunking operation,
not something that varies chunk to chunk. Repeating it on every chunk adds
redundancy without adding information, and makes changing tokenizer a
per-chunk concern instead of a single configuration change.

### Folding execution telemetry into the semantic `ChunkingResult`

Rejected. Timing, call counts, token usage, cost and similar operational
detail vary for reasons unrelated to which chunks were produced. Combining
them with the semantic result would make incidental execution variance look
like it affects determinism, when only the operational telemetry, not the
chunk set, actually varies. This is distinct from a model-assisted
strategy's model identity and the parameters it actually ran with, which do
belong to the semantic outcome, because those can change the chunk set
itself rather than merely describe how expensively it was produced.

### Making a persistent "run" record a dependency of the `ChunkingStrategy` contract

Rejected in that narrow sense only — not as a rejection of ever persisting a
chunking result. Making the pure contract depend on a persistent extraction
or chunking "run" entity would reopen a question already settled for
extraction: whether a pipeline stage's output is a durable entity to be
looked up, or a complete value passed directly to the next stage.
`NormalisedDocument` is already the complete input chunking needs, so the
contract itself must not presuppose anything more. Persisting a
`ChunkingResult`, a chunk set, processing attempts, or embedding lineage
afterward remains a legitimate concern — just one for later orchestration,
vector-storage and operations phases, not for this boundary.

## Consequences

### Positive

- Chunking joins the pipeline the same way extraction and normalisation do:
  a single-responsibility, swappable stage operating on an immutable,
  self-describing input.
- Workspace and document context always come from exactly one place — the
  `NormalisedDocument` itself — with no redundant wrapper that could drift
  out of sync with it.
- Deterministic chunk identity makes re-chunking runs comparable and
  diffable, directly enabling Stage 11.3's evaluation work.
- A clear separation between the semantic outcome and operational/execution
  information keeps "is this deterministic" unambiguous, without excluding
  a model-assisted strategy's actual model identity and parameters from the
  semantic outcome they belong to.
- Future strategies (semantic, model-assisted, hybrid) are additive changes,
  matching the Open/Closed precedent ADR 0010 already established.
- Provenance chains cleanly from a chunk back through `NormalisedElement` to
  its original source location, without this ADR having to design the
  citation system that will eventually consume it.
- A completeness failure surfaces as a typed error at the point content
  cannot be safely represented, rather than as a silent gap discovered later
  through a missing retrieval result.
- Separating telemetry ownership from the semantic result lets orchestration
  or instrumentation own execution detail without that choice affecting the
  strategy contract itself.

### Negative

- Deterministic identity derivation requires the "consequential"
  configuration fields to be chosen deliberately; getting this wrong could
  misclassify two configurations as equivalent when they are not, or vice
  versa.
- This ADR can require determinism architecturally, but cannot itself
  guarantee that every future strategy implementation honours it — a poorly
  built model-assisted strategy could still violate the contract, and a
  model-assisted strategy that cannot fix, retain or cache enough of its own
  behaviour to reproduce a result cannot honestly claim conformance at all.
- Provenance spanning multiple source elements per chunk adds bookkeeping
  every strategy implementation must get right, rather than a simpler but
  lossier "chunk is a slice of flat text" model.
- Keeping operational/execution information separate from the semantic
  outcome means two things for a chunker implementation (or its surrounding
  orchestration) to construct and keep straight, rather than one combined
  object, and drawing that line requires care for a model-assisted
  strategy where identity/parameters are semantic but usage and cost are
  not.
- Requiring a typed failure for unrepresentable content, rather than a
  warning, means every strategy implementation must define and raise a real
  failure path, not just accumulate warnings and return whatever it managed
  to produce.
- Retaining both a configuration snapshot and a separate fingerprint is more
  than retaining either alone; a strategy must keep the two consistent with
  each other rather than deriving one casually from the other after the
  fact.

## Scope boundaries

This ADR does not define:

- baseline chunk sizes;
- the overlap algorithm;
- semantic chunking;
- AI-assisted or model-driven chunking;
- contextual summarisation;
- the exact `Chunk`/`ChunkingResult` class definitions, schema, or
  serialisation format;
- the exact chunk-identity derivation scheme (hash function, namespace,
  field ordering);
- embedding generation (Phase 13);
- vector database persistence (Phase 14);
- retrieval (Phase 15);
- the citation lifecycle (Phase 16, and the citation/re-extraction design
  constraint already recorded in `PROJECT_ROADMAP.md`);
- the re-extraction lifecycle;
- whether and how a `ChunkingResult` or chunk set is persisted, including
  processing attempts, active-versus-historical generations, embedding
  lineage, or operational audit records — deferred to later orchestration,
  vector-storage and operations phases, not rejected by this ADR.

These remain open for Stage 11.2 (the baseline strategy) and later phases to
decide with the context appropriate to each.
