# ADR 0010: Define the Canonical Extracted Document Contract

## Status

Accepted

## Date

2026-07-29

## Context

Phase 9 ends with a Document durably claimed and in `PROCESSING` (ADR 0007,
ADR 0008, ADR 0009). Phase 10 begins the work that actually happens during
`PROCESSING`: turning the uploaded source bytes into content the rest of the
pipeline — chunking (Phase 11), embeddings (Phase 13), retrieval (Phase 15)
and citation generation — can work with.

The platform will support multiple source formats: plain text and PDF and
DOCX initially (Stages 10.2–10.4), with HTML, Markdown and others named as
future work in `PROJECT_ROADMAP.md`. Each format requires its own extractor,
and every underlying parsing library has its own, unrelated output shape — a
PDF library's page and text-run objects share almost nothing structurally
with a DOCX library's paragraph and style objects, which share nothing with
a plain-text file's raw lines. If chunking, embeddings and retrieval were
built against any one extractor's native output, every later stage would
depend on that specific parser's data model, and adding a new source format
would mean changing downstream code that has nothing to do with the new
format. This is the same shape of coupling problem ADR 0002 already solved
at the service level (each application owns a bounded concern, communicating
through explicit contracts) and ADR 0008 solved at the transport level (a
versioned event decouples a publisher from a consumer it never needs to know
about directly). Phase 10 needs the same discipline applied one level
further in: at the seam between extraction and everything that consumes its
output.

This ADR is architecture-only. It defines what every extractor must produce
and why, not the exact classes, schemas, database tables, or which parsing
library each extractor uses — those are Stage 10.2, 10.3, 10.4 and 10.5
concerns.

## Core architectural principle

> Preserve as much semantic information as possible during extraction. Defer
> any lossy transformation until a later stage where there is sufficient
> context to make an informed decision.

> Extraction is a loss-minimisation stage, not a simplification stage.
>
> The responsibility of extraction is to preserve the document's semantic
> structure as faithfully as practical. Decisions that intentionally discard
> information belong to later pipeline stages where there is sufficient
> context to evaluate the trade-offs.

An extractor has no idea what the platform will eventually do with a
heading, a table, or a footnote — whether chunking will treat a table as one
indivisible unit or split it by row, whether a future retrieval strategy
will weight headings more heavily than body text, or whether a citation
needs to point at a specific table cell rather than a whole page. Chunking,
embedding and retrieval each have progressively more context about the
actual downstream use of the content than extraction ever will. An
architecture that lets extraction discard information early is an
architecture that has permanently foreclosed every later stage's ability to
make a better-informed choice about that same information. An architecture
that preserves information until a stage with real context exists to decide
what to do with it keeps every future option open, at the cost of carrying
more structure through the pipeline than the simplest possible extractor
would.

This is not a preference for complexity for its own sake. It is a direct
consequence of one observation:

> Information can always be discarded later.
> Information cannot be recreated later.

A chunking strategy that turns out to want table-aware splitting, or a
retrieval feature that turns out to need page-level citations, can be built
against structure that extraction already preserved. Neither can be built
against structure that extraction already threw away — recovering it means
re-extracting the original file, which may not even produce the same result
if the extractor has since changed. This is why the principle materially
improves long-term maintainability (later stages can evolve independently,
without extraction needing to be revisited every time a new consumer need
appears), retrieval quality (structural signal such as headings, tables and
reading order is available to chunking and retrieval rather than flattened
away), extensibility (a new element type or a new consumer can be added
without renegotiating what "extraction" means) and downstream AI processing
(a generation step can reason differently about content that came from a
table versus body text, or trust a passage differently based on extraction
confidence — none of which is possible once that distinction has been
erased).

## Decision

### A canonical `ExtractedDocument` contract

Every extractor — regardless of source format — produces exactly one
canonical representation, referred to here as `ExtractedDocument`. Every
downstream component at the extraction boundary depends only on this contract
and never on parser-specific output or intermediate library objects.
Parser-specific models are private implementation details owned by their
extractor and must not escape it. Each extractor is responsible for mapping
those private objects into `ExtractedDocument`.

The document-level contract preserves the immutable public Workspace and
Document identities that establish tenant context. Source format may also
remain available as provenance. A new source format means writing a new
extractor that produces this same contract; it does not mean exposing a new
parser model to normalisation, chunking, embeddings, retrieval or citation
code. This is the architectural boundary the rest of this ADR exists to
protect.

### Separation of responsibilities

Three pipeline stages each own exactly one responsibility, and each is
deliberately narrow:

- **Extraction** reads a source document, extracts its content, preserves
  its semantic structure as faithfully as practical, and records provenance
  for what it extracted and from where. It owns and contains every
  parser-specific model, maps that private representation into the canonical
  immutable `ExtractedDocument`, and exposes only that output. It does not
  decide what to keep and what to discard beyond what is unavoidable to
  represent the source at all, and it has no awareness of chunk sizes,
  embedding models, or retrieval strategy. Keeping it this narrow means an
  extractor never needs to change because a downstream stage changed how it
  uses the content.
- **Normalisation** consumes `ExtractedDocument`, performs deterministic
  *structural* normalisation only and produces a new immutable
  `NormalisedDocument`. It does not receive parser-specific objects, mutate
  the extracted representation or chunk. It must not discard meaningful
  semantic information, but may remove or reconcile semantically empty,
  duplicated or parser-generated structural noise under explicit
  deterministic rules. Those rules preserve provenance and traceability to
  the extraction output.
- **Chunking** operates exclusively on `NormalisedDocument` and remains
  independent of source format. Source format may remain present as
  provenance for citations, debugging and auditing, but chunking must not
  branch its behaviour on that format. This allows chunking strategy to be
  iterated on without becoming coupled to each parser implementation.

Each stage having a single, narrow responsibility means a change to one
(a new source format, a new chunking strategy, a new normalisation rule) is
isolated to that stage rather than rippling through the others.

### A canonical, extensible `Element` model

`ExtractedDocument` is composed of semantic elements, not an undifferentiated
block of text. The initial contract supports, at minimum:

- `HeadingElement`
- `ParagraphElement`
- `ListElement`
- `TableElement`
- `CodeBlockElement`
- `QuoteElement`
- `HyperlinkElement`
- `ImageCaptionElement`
- `HorizontalRuleElement`
- `FootnoteElement`

The contract must remain open to new element types — for a future format, or
for structure an initial extractor does not yet populate — without requiring
downstream architectural change. This is a direct application of the
Open/Closed Principle: the set of element types is open for extension (a new
`Element` subtype can be added when a new structural need appears) but the
contract itself, and everything already built against it, is closed for
modification (existing consumers do not need to change to accommodate the
new type, and a consumer that does not yet understand a new element type can
reasonably treat it as an unrecognised block rather than failing outright).
Without this, every new element type discovered in the wild would require
renegotiating the shape of the contract itself, which is exactly the
coupling this ADR exists to prevent.

Every consumer must implement a deliberate safe fallback for future or
currently unrecognised `Element` types. A consumer may preserve, pass through
or handle an unknown element conservatively according to its responsibility,
but it must not fail unexpectedly merely because the canonical model has
gained an element type it does not yet interpret.

### Preserve semantic structure wherever practical

Extraction should preserve, wherever the source format makes it practical to
do so:

- reading order;
- document hierarchy;
- headings;
- paragraphs;
- lists;
- tables;
- hyperlinks;
- image captions;
- source locations;
- page numbering;
- extraction confidence;
- document metadata.

"Wherever practical" is deliberate: some source formats will not offer all
of this (a plain-text file has no native heading concept), and an extractor
should not fabricate structure that is not really there. But where the
information exists in the source, discarding it at extraction time is a
one-way decision the core principle exists to prevent. A later stage that
turns out not to need page numbers can simply ignore them; a later stage
that needs page numbers extraction never recorded has no way to get them
back without re-processing the original file.

### Source provenance

Every semantic element retains enough provenance to support citations,
debugging, auditing, replay and future explainability — the ability to
answer, later, "where in the source document did this come from, and which
extractor produced it, and when." This ADR treats provenance as an
architectural concern rather than a fixed field list: the exact shape of
that provenance (offsets, page identifiers, extractor name and version,
timestamps, or whatever else a given format can support) is an implementation
decision for the stage that builds the contract, not a decision this ADR
needs to fix in advance. What is architectural is the requirement that
provenance exists at all, consistently, for every element the contract
produces — without it, a citation feature or a debugging session has nothing
to point back to.

### Stable identifiers

Every semantic element has a stable identifier represented as a UUID. The
identifier remains stable for the lifetime of its immutable representation
and lets citations, logs, debugging and later derived artefacts refer
unambiguously to one specific piece of content. Derived representations must
preserve traceability to the elements from which they were produced.

A new extraction run creates new element UUIDs. Stable identity across
re-extraction is not required at this stage, and no deterministic
cross-extraction identifier scheme is implied. The exact UUID generation and
derived-element linkage strategy remains an implementation decision for the
model stage.

### Immutability

`ExtractedDocument`, once produced, is immutable. No later pipeline stage
modifies it. Normalisation produces a new immutable `NormalisedDocument`
derived from extraction's output; chunking produces a new representation
derived only from normalisation's output. Nothing overwrites what an earlier
stage produced.

This has several architectural benefits beyond tidiness:

- **Data integrity** — a stage can trust that the representation it received
  has not been altered by anything downstream of where it was produced.
- **Auditability** — what extraction actually produced remains inspectable
  after normalisation and chunking have both run, rather than being
  overwritten in place.
- **Replayability** — a pipeline stage can be re-run against the same
  immutable input to reproduce or debug an issue, without needing to
  re-extract from the original source file.
- **Deterministic processing** — a stage's output depends only on its
  immutable input, not on shared mutable state that another stage might
  have altered concurrently or out of order.
- **Debugging** — the state at each stage boundary is a fixed, inspectable
  artefact rather than a moving target.
- **Security** — immutability prevents later pipeline stages from altering
  earlier representations through normal application behaviour and reduces
  accidental or unauthorised mutation within the pipeline. It complements
  rather than replaces storage access controls, integrity checks and
  auditing.
- **Future event-driven processing** — an immutable, versioned representation
  is exactly the shape of thing that can later be published, cached, or
  passed between processing stages as a durable artefact, in the same way
  ADR 0008 already treats an event as a durable, non-mutated record of
  intent rather than something a consumer edits in place.

### Extraction failures

Extraction failures are differentiated as transient or permanent. A
transient failure (for example, a temporary storage read failure) is worth
retrying; a permanent failure (for example, a corrupt or password-protected
file the extractor cannot parse at all) is not — retrying a permanent
failure provides little value and simply delays the moment someone finds out
something needs correcting. A permanent failure carries both a
machine-readable failure code, so the platform can reason about it
programmatically, and a human-readable explanation suitable for an end user,
so a workspace member has enough information to take corrective action (for
example, re-exporting a corrupt file, or removing a password) rather than
being told only that "processing failed." All extraction failures, transient
or permanent, are audited — consistent with ADR 0006's existing business-audit
expectations for security- and operationally-significant platform events.

Non-fatal extraction warnings are retained on the immutable extraction
output. A warning must not silently disappear, and it must not be promoted to
a failure merely because the current pipeline has no immediate consumer for
it. This preserves diagnostic evidence without preventing usable content from
continuing through normalisation.

### Confidence

Where an extractor can support it, semantic elements may carry an extraction
confidence value. This ADR does not require every extractor to populate it,
and does not mandate what a consumer does with it yet — it establishes the
concept now so that a future quality-control feature (for example, flagging
low-confidence extractions for review, or weighting retrieval differently
for uncertain content) has somewhere to attach itself without requiring the
contract to be redesigned retroactively. This mirrors the same
forward-compatibility reasoning behind the open element-type model: the
capability is architected for now, without forcing its consumption before
there is a concrete need for it.

### Future extensibility

Preserving semantic richness now — rather than simplifying early — is what
lets the following become additive future work rather than architectural
rework: improved chunking strategies, better retrieval, richer citations,
OCR integration, diagram extraction, formula support, broader document
intelligence, and the ability to experiment with alternative chunking
strategies against the same extracted structure. Each of these consumes
information the canonical contract already preserves (structure, provenance,
confidence, stable identifiers); none of them requires revisiting how
extraction itself works. That is the direct, intended payoff of treating
extraction as a loss-minimisation stage rather than a simplification stage.

## Alternatives considered

### Let each extractor produce its own ad hoc structure

Rejected. Chunking, embeddings and retrieval would each need to special-case
every source format's native output, coupling every downstream stage to
every upstream parser. Adding a new format would mean changing N unrelated
downstream files instead of adding one new extractor — the opposite of the
service-boundary discipline ADR 0002 already established at a coarser
grain.

### Normalise directly to plain text at extraction time

Rejected. This is the "simplification stage" the core principle explicitly
argues against: collapsing headings, tables and lists into flat text at
extraction time discards retrieval-quality and citation-precision
information that later stages cannot recover, in exchange for a marginally
simpler extractor today.

### Adopt an existing third-party document model as the canonical form

Using a specific parser library's own object model (or an external format
such as an OOXML or HTML DOM tree) as the canonical representation was
considered. Rejected: whichever library's shape "wins" becomes a hidden
dependency for every other extractor and for every downstream consumer,
recreating exactly the coupling problem this ADR exists to prevent, and
tying the platform's canonical model to that library's own versioning and
design choices rather than the platform's own needs.

### Mutable, in-place pipeline objects

Rejected in favour of immutability. Mutating one shared document object as
it passes through extraction, normalisation and chunking would make it
impossible to inspect what an earlier stage actually produced once a later
stage has overwritten it — undermining auditability, replayability and
debugging, all of which depend on being able to compare a stage's input to
its output independently.

### A single generic block type with a free-form "kind" field

An untyped `Block { kind: string, attributes: dict }` shape was considered as
a way to stay "open" without maintaining an explicit list of element types.
Rejected: this pushes correctness into ad hoc string comparisons scattered
across every consumer instead of a typed, enumerable set of element kinds,
undermining rather than serving the Open/Closed goal — a typed set of
`Element` subtypes that can be extended with new cases gives both safety
today and room to grow later, which an untyped bag of attributes does not.

### Defer provenance until citations are actually built

Rejected. Provenance — source offsets, page identifiers, extractor identity
— is exactly the category of information the core principle says cannot be
recreated once discarded. If extraction does not record where content came
from today, a future citation feature cannot retroactively recover it
without re-extracting the original file, which may no longer even produce
identical results.

### Combine extraction and chunking into one step per format

Rejected. This would couple chunking strategy — which is expected to be
iterated on far more than extraction itself, per "Future extensibility" —
to parser-specific code, meaning every chunking-strategy experiment would
need reimplementing once per source format instead of once, against the
canonical representation.

## Consequences

### Positive

- Loose coupling between extraction, normalisation, chunking and everything
  downstream of them.
- Extensibility: new source formats and new element types are additive,
  not disruptive, changes.
- Maintainability: each stage's responsibility is narrow enough to reason
  about, test and change independently.
- Better AI retrieval and generation quality, because structural signal
  (headings, tables, hierarchy) survives into chunking and retrieval instead
  of being flattened away early.
- A future-proof architecture for capabilities not yet built (OCR, diagrams,
  formulas, alternative chunking strategies) without requiring extraction
  itself to be redesigned when they arrive.
- Improved observability, since provenance and stable identifiers make it
  possible to trace a piece of content back to its source.
- Replayability and deterministic processing, both direct consequences of
  immutability.

### Negative

- Increased implementation complexity relative to a flat-text extraction
  pipeline.
- A richer object model to design, document and keep consistent across
  every extractor.
- Additional testing requirements: each element type and each extractor's
  mapping onto the canonical contract needs its own coverage.
- Slightly increased memory consumption, since a structurally rich
  representation carries more than a flat string would.

These costs are accepted because they are paid once, at the architectural
boundary this ADR defines, in exchange for every later stage — chunking,
embeddings, retrieval, citations, and whatever future capability this
platform adds — never having to pay the much larger cost of recovering
information extraction already discarded, or of coupling itself to a
particular source format's parsing library.

## Scope boundaries

This ADR does not define:

- the exact `ExtractedDocument` or `Element` class definitions, schemas, or
  serialisation format;
- the exact `NormalisedDocument` class definition or derived-element linkage
  strategy;
- the exact provenance fields any given extractor populates;
- the element UUID generation strategy beyond the requirement that each new
  extraction run creates new UUIDs;
- which parsing library each extractor (Stage 10.2 plain text, Stage 10.3
  PDF, Stage 10.4 DOCX) uses;
- the specific structural-normalisation rules Stage 10.5 implements;
- chunking strategy itself (Phase 11);
- embedding model or retrieval design (Phase 13 onward);
- whether this contract is expressed as a language-internal Python model, a
  shared schema artefact under `contracts/`, or both — that decision belongs
  to whichever stage first implements it, informed by whether the contract
  ever needs to cross a language boundary the way the ingestion event
  (ADR 0008) does. Today, extraction, normalisation and chunking are all
  Python-owned (ADR 0002), so this contract does not yet have the
  cross-language pressure that motivated a schema-validated artefact for the
  ingestion event.
