# ADR 0007: Define the Document Lifecycle and Storage Model

## Status

Accepted

## Date

2026-07-28

## Superseded in part by ADR-0008

ADR-0008's adoption of the Transactional Outbox Pattern supersedes only this
ADR's original definition of when a Document enters `QUEUED`.

`QUEUED` now means that the Document's lifecycle transition and its durable
publication intent have committed together in PostgreSQL. Successful SQS
publication occurs asynchronously afterward and is no longer the defining
condition for entering `QUEUED`. The original wording below is retained as
historical context; ADR-0008 is authoritative where the two decisions differ.

## Context

Phase 7 established Workspace as the platform's tenancy and isolation
boundary (`docs/adr/0006-*`). Phase 8 must now define what a **Document** is
before any upload, storage or processing code is written. Every later phase —
event-driven ingestion, text extraction, chunking, embeddings, retrieval and
administration — builds tenant-owned data on top of whatever document model is
chosen here, so getting the shape wrong is expensive to unwind later, in the
same way an incorrect tenancy model would have been.

A document touches three physically different systems with different
guarantees: Laravel/PostgreSQL (the system of record, per ADR 0002), an
S3-compatible object store reached through a provider abstraction, and — from
Phase 13–14 onward — a vector store (Qdrant). A document also has a
lifecycle: it does not exist fully formed the instant a user clicks upload; it
passes through upload, queuing, asynchronous processing, and eventually
either a searchable state or a failure. That lifecycle needs to be a single,
explicit contract that Stage 8.2 (persistence), Stage 8.3 (upload flow), and
Phase 9's ingestion pipeline can all build against, rather than something each
stage reinvents.

This ADR is architecture-and-documentation only. It defines the Document
domain concept, its lifecycle, and the separation of responsibility between
relational metadata, object storage and searchable representations. It does
not define migrations, models, endpoints, or the exact ingestion mechanics —
those are Stage 8.2, 8.3 and Phase 9 concerns.

## Decision

### What a Document represents

A **Document** is the platform's durable record of a piece of content a
workspace has chosen to make available for retrieval. It is an identity and a
lifecycle, not a file. A Document can exist — and be queried, listed, and
reasoned about — before its bytes have finished uploading, while its content
is being processed, or after its underlying storage object has been removed
as part of an authorised deletion. The Document row is what answers "does
this exist, who owns it, and what state is it in"; it is not itself the
content.

Outside of an in-progress deletion or an explicit recovery scenario, the
unexpected absence of source bytes for an otherwise-active Document is not a
valid steady state. It is inconsistent state requiring reconciliation, not a
condition the lifecycle treats as normal or self-resolving.

### Why a Document is distinct from the uploaded file

Treating "the file" and "the Document" as the same thing conflates three
things that fail, scale and change independently:

- the bytes can be re-homed (a bucket migration, a storage-tier change)
  without the document's identity, ownership or history changing;
- an upload can be interrupted, retried, or abandoned before any usable file
  exists at all, yet the platform still needs a place to track that attempt;
- future document sources (see "Future extensibility" below) will not
  originate from a browser upload at all — a connector-fetched file has no
  "uploaded file" in the traditional sense, but still needs a Document
  identity, lifecycle and ownership record.

The Document is the stable handle; the file is one (currently: the only)
underlying representation of its content.

### Ownership

A Document belongs to exactly one workspace, never to an individual user,
consistent with ADR 0006's workspace-owned entity classification: a
non-nullable workspace foreign key, resolved and authorised inside the
workspace boundary, with users reaching it only through active workspace
membership. A `created_by_user_id` may record who initiated the upload, as
provenance only — exactly as ADR 0006 treats workspace creation provenance —
and is not an ownership or authorisation mechanism.

### Separation of concerns

Three layers hold different, non-interchangeable responsibilities:

1. **Relational metadata (PostgreSQL, via Laravel)** — the system of record
   for a document's identity, workspace ownership, lifecycle state, source
   filename and media type, size, timestamps, and failure information. This is
   what listings, policies and authorisation reason about. It is
   authoritative: if the relational record says a document does not exist or
   is not yet ready, no other system's state overrides that.
2. **Object storage (S3-compatible, behind the existing provider
   abstraction)** — holds the uploaded bytes. It is addressed by a
   server-controlled storage key, is never trusted as the source of truth for
   lifecycle state, and its mere presence or absence must not be interpreted
   as "ready" or "deleted." Storage state is reconciled toward the relational
   record, not the reverse.
3. **Searchable/vector representation (chunks and embeddings, Qdrant from
   Phase 14 onward)** — a derived, disposable, rebuildable projection of a
   document's content. It is rebuildable from the authoritative source
   content through the processing pipeline, without requiring a fresh upload.
   This ADR does not assume that any intermediate extracted or normalised
   text is itself durably stored — whether such an intermediate
   representation persists, and for how long, is a processing/ingestion
   design question for a later stage. The absence of a searchable
   representation does not mean the document itself does not exist — it
   means the document has not yet reached (or has fallen out of) an indexed
   state.

Search/indexing being independent of document persistence means a defect or
data loss in the vector layer is a re-indexing problem, not a document-loss
problem — the relational metadata plus the stored source file remain the
recoverable ground truth.

### Document lifecycle

The lifecycle is a durable state machine, not a set of independent boolean
flags, consistent with the platform's stated architectural preference for
state machines over boolean-flag combinations. A small number of explicit,
mutually exclusive states is easier to reason about, test and query than
combinations of flags that can drift into inconsistent combinations.

The accepted states are:

```text
UPLOADING → UPLOADED → QUEUED → PROCESSING → INDEXED
```

with two additional terminal-or-recoverable branches:

```text
PROCESSING → FAILED
<any non-DELETED state> → DELETING → DELETED
```

- **UPLOADING** — the platform has authorised an upload and created a
  Document record, but the file is incomplete and unconfirmed. A Document in
  this state is not usable: it must not be listed as available content,
  queued for processing, or made retrievable. An interrupted or abandoned
  upload does not automatically fail or complete — it simply does not
  progress (Stage 8.3 already commits to "interrupted uploads do not become
  ready documents"). Expiring or cleaning up abandoned `UPLOADING` records is
  an operational policy for a later stage; this ADR does not treat that
  non-progression as an ambiguity to be preserved, only as a case this ADR
  does not itself resolve.
- **UPLOADED** — the authoritative source content has been confirmed present
  in object storage. This is the point at which a Document has confirmed
  source content available for downstream processing, regardless of how that
  content originated.
- **QUEUED** — an ingestion event for this Document has been successfully
  published (Phase 9) and the Document is awaiting a worker. The transition
  from `UPLOADED` to `QUEUED` occurs only once that publication succeeds; if
  publication fails, the Document remains `UPLOADED` rather than advancing on
  an unconfirmed publish. How a failed publication is subsequently retried is
  a Phase 9 concern.
- **PROCESSING** — a worker is actively extracting, chunking and embedding the
  document's content.
- **INDEXED** — the selected indexing pipeline has completed successfully and
  the approved searchable representation is fully available for
  workspace-filtered retrieval. A partial write — for example, some but not
  all chunks embedded and stored — does not qualify as `INDEXED`; the
  Document remains `PROCESSING`, or moves to `FAILED`, until the complete
  representation is in place.
- **FAILED** — processing did not complete successfully. This is a distinct,
  diagnosable state, not a variant of `DELETED` or a silently-retried
  in-between state; collapsing failure into another state would destroy the
  diagnostic value of knowing something went wrong and why.
- **DELETING** — deletion has been requested and is being carried out.
- **DELETED** — deletion has completed.

`INDEXED` was chosen over a more generic name such as `ready` because it
states precisely what completed — the document is searchable — rather than a
vague notion of readiness that later stages would have to reinterpret.

### Failure behaviour

A document reaches `FAILED` only from `PROCESSING`; upload-time problems are
represented by a Document simply never leaving `UPLOADING` rather than by an
upload-specific failure state, since nothing has been queued or processed yet
for there to fail. A `FAILED` document must retain enough information to
diagnose what went wrong (an error category and message, at minimum) without
requiring extracted content or secrets to be captured to do so. `FAILED` is
terminal from the perspective of automatic processing — it does not silently
retry itself — but it is recoverable through an explicit retry action.

### Retry behaviour

Two different kinds of retry exist at different layers, and this ADR is
concerned only with the domain-level one:

- **Transport-level retry** (SQS visibility timeouts, redrive to a
  dead-letter queue) is a Phase 9 ingestion-pipeline concern, already
  anticipated by Stage 9.3, and is not redefined here. Any bound on redelivery
  attempts before a message is dead-lettered is Phase 9 policy, not this ADR.
- **Domain-level retry** is always an explicit, authorised action — never an
  automatic behaviour performed by the platform on its own. It is the
  transition `FAILED → QUEUED` on the same Document: it re-enters the
  pipeline rather than creating a new Document, preserving the Document's
  identity across a failed attempt. It does not itself preserve, or claim to
  reconstruct, a history of prior processing attempts; whether ingestion
  attempts warrant their own durable history is a question for the ingestion
  architecture (Phase 9), not settled by this ADR.

Automatic, uncontrolled retry loops are rejected regardless of layer: a
Document must never be silently re-queued by the platform itself outside of
an explicit domain-level action or a bounded transport-level redrive policy.
Bounding transport/queue redelivery is Phase 9's responsibility; this ADR
requires only that no unbounded automatic loop can occur, without
prescribing counts, backoff or timeouts.

### Deletion lifecycle

Deletion is reachable from any non-deleted state, not only from `INDEXED` —
a workspace member may delete a document that is still uploading, queued,
processing, or has failed, and the model must not force it through a
completed state first. Deletion is asynchronous rather than an immediate row
removal, mirroring the same reasoning ADR 0006 applies to workspace deletion:
a document's content may need to be removed from object storage, from the
vector store, and from any derived artefacts, and treating that as a single
instantaneous operation would hide partial-failure cases (for example, the
storage object is removed but stale vectors remain retrievable).

`DELETING` and `DELETED` are **cancellation barriers**. Once deletion has been
requested, no in-flight or subsequent processing may return the Document to
an active state, publish new derived artefacts (chunks, embeddings, or any
other downstream output), or make it retrievable. There is no valid
transition back from `DELETING` or `DELETED` to `UPLOADING`, `UPLOADED`,
`QUEUED`, `PROCESSING` or `INDEXED`. A worker that was already processing a
Document when deletion was requested must not act as though deletion had not
happened.

`DELETED` is terminal. The relational row may be retained after deletion —
rather than being hard-removed immediately — to support reconciliation
(confirming that storage and vector cleanup actually completed) and
auditability, consistent with the business-audit expectations ADR 0006
already establishes for workspace-level lifecycle events, which explicitly
names document administration as an audited action. Whether that retention is
permanent, for how long, and any eventual hard-purge policy are data-retention
questions deferred to a later stage; this ADR commits only to `DELETED` not
implying an immediate, unconditional physical row removal.

### Versioning

Two designs were weighed:

- **True versioning**: multiple versions of a logical document, with version
  history, per-version chunks and embeddings, superseded-version handling, and
  citations that can point to a specific version.
- **No versioning**: every upload — including a re-upload of a file with an
  identical name — is treated as an entirely new, independent Document with
  its own identity, lifecycle, chunks and embeddings.

This ADR accepts **no versioning for now**. True versioning is meaningful
scope — version lineage, garbage-collecting superseded vectors, a
"latest version" concept surfaced to users, and citation semantics across
versions — introduced before any product requirement has established that
users need it. This follows the platform's stated preference to avoid
premature complexity where a simpler model provides a stable foundation.
Treating every upload as a new Document is the simpler model, and it does not
foreclose introducing real versioning later: a future ADR could add an
explicit relationship between documents (for example, a "supersedes" link)
once a genuine requirement exists, without requiring this ADR's lifecycle or
storage separation to change.

The accepted cost of deferring versioning is duplication: re-uploading a
near-identical file produces a second, independently processed and indexed
Document, with no automatic notion of which one is "current." That is judged
an acceptable, reversible cost in exchange for not designing a versioning
scheme speculatively.

### Future extensibility for additional document sources

The lifecycle and the three-layer storage separation are deliberately
source-agnostic. `UPLOADED` means "content is confirmed present in object
storage," not specifically "a browser uploaded it" — a future connector
(Google Drive, SharePoint, a fetched URL) would stage fetched content into
object storage and enter the same `UPLOADED → QUEUED → PROCESSING → INDEXED`
pipeline used today, rather than requiring a parallel lifecycle. This ADR
does not define a source-type discriminator, connector metadata, or any
connector-specific schema — those are extension points for whichever future
phase introduces the first non-upload source — but it commits to the
lifecycle and storage model not needing to be redesigned when that happens.

## Alternatives considered

### Multiple boolean flags instead of a state machine

Flags such as `is_uploaded`, `is_processing`, `is_indexed`, `is_failed` are
easy to add individually but permit inconsistent combinations (for example,
`is_indexed` and `is_failed` both true) that a single state machine makes
structurally impossible. Rejected in favour of one explicit lifecycle, per the
platform's stated architectural preference for state machines over
boolean-flag combinations.

### Storing file content directly in PostgreSQL

Storing document bytes as a database column would simplify the storage
model to one system, but discards the S3-compatible object-storage
abstraction already decided for the platform, bloats the relational database
with large binary content, and removes the ability to serve or stream content
independently of the application database. Rejected.

### Object storage as the source of truth for document existence

Deriving "does this document exist" from whether an object is present in the
bucket was considered and rejected: it would make the relational record a
cache of storage state rather than the authority, complicate authorisation
(which must be checked before any storage access), and make an interrupted or
partially-completed upload indistinguishable from a deleted document. The
relational record remains authoritative; storage is reconciled toward it.

### The vector store as the source of truth for "is this document usable"

Treating the presence of vectors in Qdrant as the signal that a document is
usable was rejected for the same reason: the vector store is a derived,
rebuildable projection. If it were authoritative, losing or rebuilding the
index would look identical to the underlying documents having disappeared.

### Automatic, unbounded retry of failed processing

Having the platform itself silently re-queue a `FAILED` document, with no
explicit action and no bound, was rejected because it can mask a systemic
failure (a consistently broken extractor, a bad file type) as an infinite
background retry loop instead of surfacing a diagnosable `FAILED` state to a
workspace member or operator. Retry from `FAILED` remains available, but only
as an explicit, authorised action, never as automatic platform behaviour.

### Immediate, synchronous deletion

Deleting a document's relational row, storage object and vectors in one
synchronous operation was rejected for the same reason ADR 0006 rejects
immediate workspace deletion: partial failure (one system succeeds, another
does not) would be invisible, and there would be no intermediate state in
which a still-in-progress deletion could be observed or retried.

### True document versioning now

Discussed above under "Versioning." Rejected for now as premature complexity
without a demonstrated product requirement; the door is left open for a
future ADR once one exists.

## Consequences

### Positive

- A single authoritative record (the relational Document) makes "does this
  exist and what state is it in" answerable without cross-referencing object
  storage or the vector store.
- The explicit state machine makes invalid combinations (e.g. indexed and
  failed simultaneously) structurally impossible rather than merely
  discouraged.
- Deletion and retry both have explicit, observable in-progress states
  (`DELETING`, `QUEUED` after retry) rather than being invisible operations.
- The model accommodates future connector-based sources without requiring the
  lifecycle or storage separation to be redesigned.
- Deferring versioning keeps the initial data model simple and avoids
  designing a versioning scheme before a real requirement defines what it
  needs to do.

### Negative

- Deferring versioning means repeated uploads of the same or near-identical
  content produce duplicate, independently indexed documents, with no
  automatic "latest version" notion until a future ADR addresses it.
- The three-layer separation requires disciplined reconciliation: relational
  state, storage state and vector state can drift apart (e.g. a storage
  object removed but a stale relational or vector record remaining) and later
  stages must handle that explicitly rather than assuming the three always
  agree.
- Requiring domain-level retry to be an explicit, authorised action (rather
  than automatic) places the burden of noticing and acting on a `FAILED`
  document on a workspace member or operator; the transport-level redrive
  policy that bounds automatic queue-delivery retries is itself left to
  Phase 9 to design.
- Asynchronous deletion requires its own orchestration across three systems,
  deferred to a later stage, rather than being solvable by a single delete
  statement.
