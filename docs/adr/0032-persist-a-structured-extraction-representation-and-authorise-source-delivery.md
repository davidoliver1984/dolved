# ADR 0032: Persist a Structured Extraction Representation and Authorise Source Delivery

## Status

Accepted

## Date

2026-08-28

## Relationship to prior ADRs

### Extends ADR-0007's deliberately deferred question

ADR-0007 explicitly declined to assume "that any intermediate extracted or
normalised text is itself durably stored... a processing/ingestion design
question for a later stage." This ADR is that later stage — a durable,
user-facing, readable representation of a version's content — independent
of ADR-0007's three-layer separation and technical processing lifecycle.

### Extends, without changing, ADR-0010's `NormalisedDocument` boundary

ADR-0010 defines the immutable `ExtractedDocument` and `NormalisedDocument`
processing boundaries. **This ADR persists `NormalisedDocument` itself —
verified directly against `apps/ai/app/normalisation/models.py` — not a
simplified restatement of `ExtractedDocument`, and not two parallel
raw/normalised artefacts.** `NormalisedDocument` is already what chunking
(ADR-0011) consumes today; this ADR adds one additional, new persistence
step over the same already-produced object, and neither redefines nor
duplicates ADR-0010's contract.

### Does not change retrieval chunk semantics

`document_chunks` — its schema, its `configuration_fingerprint`-based
uniqueness, its provenance shape, and its role in embedding, retrieval and
reranking (ADR-0011, ADR-0013, ADR-0014, ADR-0018, ADR-0021) — is entirely
unchanged.

### Extends the existing worker-request protocol family without reopening
### it

This ADR adds new purpose-scoped request types to the same HMAC-
authenticated family ADR-0009/0015/0016 already established, following the
same discipline `ingestion-complete-request-v1` already demonstrates:
digests, counts, and references only — never bulk content in a signed
acknowledgement.

### Supplies the foundation ADR-0031 consumes — corrected dependency
### direction

**An earlier working draft of this decomposition stated this ADR was
"drafted after ADR-0031, which it depends on."  That was backwards, and is
withdrawn here.** ADR-0032 depends only on ADR-0030 (metadata
classification, and `source_checksum_sha256` as the content identity every
artefact is anchored to) and on ADR-0010/0011 (unchanged, consumed as
given). **ADR-0031's clone mechanism consumes this ADR's artefact, digest
algorithm, and upload/projection machinery — this ADR does not consume
anything from ADR-0031.** The correct implementation order (binding,
restated in full in ADR-0031's "Implementation order") is: ADR-0030's
schema and checksum foundation first; this ADR's canonical schema,
artefact record, upload, and projection machinery second; ADR-0031's
governance routes may proceed in parallel with this ADR where genuinely
independent, but its clone orchestration cannot begin until this ADR's
artefact/digest foundation exists to be cloned.

### Consumed by ADR-0031, ADR-0033, ADR-0037

ADR-0031 clones this ADR's artefact and projection as two of its six
independently-owned layers, and extends its clone compatibility proof with
this ADR's normaliser identity and artefact/projection/warning digests.
ADR-0033 builds comparison and source-viewing UI against this ADR's
deterministic representation. ADR-0037 exports this ADR's artefact and
projection as a comprehensive export source.

## Context

Verified directly against `apps/ai/app/normalisation/models.py`:
`NormalisedDocument` — the object chunking already consumes today — is a
real, rich, already-existing Pydantic model, not a hypothetical shape this
ADR must invent. It carries ordered `NormalisedElement`s (paragraph,
heading, table, unknown variants), each with a stable per-element UUID
identity, source-element back-references, a tuple of discriminated
`SourceLocation`s, character offsets, and optional confidence; document-
level `extraction_warnings` and `changes` (normalisation-applied
corrections); `source_extractor`/`normaliser` identity; and
`ExtractedDocumentMetadata` (title/author/subject/keywords/creator/
producer/creation and modification dates). It also carries `workspace_id`
and `document_id` directly on the model — Python's processing context,
not a fact this ADR's canonical, checksummed artefact should include (see
"Canonical bytes exclude ownership" below).

Separately verified, unchanged from the prior drafting pass: chunk
provenance (`ingestion-chunks-submit-request-v1.schema.json`) already
carries `normalised_element_id`, `source_element_ids`, and discriminated
`source_locations[]` per chunk, with a `role: "primary" | "overlap"` field
proving chunk boundaries can deliberately overlap — chunk concatenation is
therefore not a faithful document reconstruction, and this ADR's canonical
artefact and chunk provenance's `normalised_element_id` field are
**intentionally the same identity space** — a normalised element referenced
by a chunk's provenance is the same element this ADR's artefact persists,
not a second, independently-numbered one.

Also verified: `IngestionWorkerRequestAuthenticator` signs the whole
request body in one call; `DOCUMENT_MAX_UPLOAD_MB` defaults to 25MB
(`apps/api/config/documents.php`); no request-body-size configuration
exists in this repository today.

## Decision

### Why chunks are insufficient (recorded, not re-argued each time)

Unchanged: chunk text may deliberately overlap; chunk boundaries are
retrieval-oriented; chunk provenance is fragmented and does not represent a
standalone heading or a document-level warning; concatenating chunk text is
not a faithful readable document.

### `DocumentExtractionArtifact`: the canonical, versioned `NormalisedDocument`

**`DocumentExtractionArtifact` is the canonical, versioned serialisation of
the actual `NormalisedDocument` Python already produces and hands to
chunking — not a simplified restatement of `ExtractedDocument`, and not a
second, parallel raw/normalised pair.** The original uploaded source file
remains the one raw/original record (ADR-0007, ADR-0030's checksum); the
extracted-text UI (below) is clearly labelled as Dolved's **normalised
search representation**, honestly distinct from the original.

**Canonical bytes preserve every safe, required field from the real
`NormalisedDocument`/`NormalisedElement` models:**

- `source_extractor` (`ExtractorIdentity`: name, version, optional
  parser name/version).
- `normaliser` (`NormaliserIdentity`: name, version) — **newly required by
  ADR-0031's extended clone compatibility proof**, since content produced
  by two different normaliser versions cannot be proved equivalent for
  reuse.
- `source_media_type`, `source_byte_size` — content facts, unchanged by
  cloning, safe to include.
- The document's normalised `text` (the same full text `NormalisedElement`
  character offsets index into).
- Ordered `elements`, each preserving: its real `id` (the normaliser's own
  stable UUID, assigned once and immutable thereafter — **the same
  identity space as chunk provenance's existing `normalised_element_id`
  field**, not a second, independently-numbered one); an explicit ordinal
  (the element's position in document order, since `elements` is an
  ordered tuple but ordinal must be explicit and persisted, not merely
  implied by array position, for stable pagination and diffing); `kind`
  (`paragraph`/`heading`/`table`/`unknown`); `source_element_ids` (the
  raw-extraction element(s) this normalised element derives from);
  `source_locations` — the **complete discriminated union**, preserved
  exactly as `ExtractedDocument`'s own `TextSourceLocation`/
  `PdfSourceLocation`/`DocxSourceLocation` variants already define them
  (character/line ranges for text; page number/dimensions/bounding
  box/rotation for PDF; body-block/table-row/table-column indices for
  DOCX) — never flattened or simplified; `start_character`/`end_character`
  offsets into the document's normalised text; and `confidence` where the
  normaliser genuinely supplied one (nullable, never fabricated).
- Per element kind: `NormalisedHeadingElement.level` (1–9) for headings;
  `NormalisedTableElement.rows` — each row's cells, preserving per-cell
  text, row/column index, and per-cell source location, reusing the
  extraction layer's own `TableRow`/`TableCell` shape unchanged, not a
  simplified table representation; `NormalisedUnknownElement.original_kind`
  and `preserved_payload_json` — a conservative, safely preserved payload
  for an element kind this platform does not yet specifically recognise,
  never discarded.
- Complete `extraction_warnings` (code, message, optional element/location
  reference), carried verbatim.
- Complete `changes` (`NormalisationChange`: code, message, the source
  element IDs it affected) — the record of what normalisation altered,
  needed to honestly explain or reproduce the search input, not merely to
  display it.
- `metadata` (`ExtractedDocumentMetadata`: title, author, subject,
  keywords, creator, producer, creation/modification dates) — safe,
  document-authored metadata already produced by extraction, not
  Dolved-authored metadata (which is ADR-0030's exclusive concern).

**Canonical bytes exclude ownership — this is load-bearing, not
incidental:** `workspace_id`, `document_id` (both present on the in-memory
`NormalisedDocument` as Python's processing context), any Laravel
document/family identity, any storage key, and any other tenant-ownership
field are **never included in the bytes that are canonicalised and
checksummed.** Any such value's presence would mean a byte-identical
applicability-only clone — the same content, a different `document_id` —
would require *changing* the canonical artefact merely to reflect new
ownership, defeating the entire premise of a provider-free, content-
identical clone. **Laravel binds ownership externally**: the
`DocumentExtractionArtifact` record itself (workspace/document identity,
the exact authorised object key) carries ownership; the canonical bytes
inside the object it points to never do.

Stored in the existing S3-compatible object storage, via the same
provider-abstracted mechanism `DocumentObjectStorage` already uses.

### Canonical serialisation and digests

**Selected: UTF-8, RFC 8785 (JSON Canonicalisation Scheme) canonical JSON,
SHA-256 over the exact resulting canonical bytes.** This is not an
arbitrary choice — JCS is a published, deterministic, cross-language
specification (object keys sorted, no insignificant whitespace, a fixed
number serialisation), which is exactly what a Python-produced, PHP-
verified canonical form requires without either language's own default
JSON serialiser being trusted to agree with the other's.

- **Non-finite numbers (`NaN`, `Infinity`, `-Infinity`) are rejected
  outright** at canonicalisation time — JCS itself has no representation
  for them, and this platform's models already constrain `confidence` to
  `[0, 1]`, so a non-finite value here indicates a genuine defect, never a
  value to silently coerce.
- **Explicit null-versus-absence rules**: a field that is genuinely absent
  (e.g., a TXT source location has no `page_number` at all — the concept
  does not apply) is omitted from the canonical object entirely; a field
  that is present but has no value in this instance is never conflated
  with absence. This directly serves the "never fabricate structure"
  principle: an omitted key means "this kind of fact does not apply here,"
  never "unknown."
- **Fixed discriminators**: every polymorphic value — `SourceLocation`'s
  `kind` (`text`/`pdf`/`docx`), `NormalisedElement`'s `kind`
  (`paragraph`/`heading`/`table`/`unknown`) — is a required, literal string
  field in the canonical form, never inferred from which optional fields
  happen to be present.
- **Stable ordering**: `elements` are canonicalised in their persisted
  ordinal order; JCS's own key-sorting rule handles object-key ordering
  within each element; array ordering (elements, table rows, table cells,
  warnings, changes) is always the document's own natural order, never
  re-sorted.

**UUID canonical form, defined exactly, per Codex's second Tier-1 audit
finding this was previously left implicit:**
- Every UUID field (element `id`, `source_element_ids`, and any other UUID
  this artefact carries) **serialises as the lowercase, hyphenated
  canonical UUID string form** (RFC 4122's canonical textual
  representation) — never uppercase, never the bare 32-hex-digit form
  without hyphens.
- **Parsing accepts only valid UUID values**; canonical serialisation
  **always emits the one selected lowercase form**, regardless of how the
  value was originally represented in memory on either side — this is what
  makes the value byte-identical across Python and PHP regardless of each
  language's own default UUID string casing.

**Unicode text rule, defined exactly, and deliberately not what a naive
"always normalise Unicode" instinct would do:**
- **The artefact preserves the exact Unicode code-point sequence already
  held by `NormalisedDocument`** — the normaliser's own output is already
  the authoritative search input, and this ADR's canonicalisation step
  **does not apply any additional NFC/NFKC normalisation of its own.**
  Re-normalising at serialisation time would risk silently altering
  search-relevant text (for example, collapsing distinct-but-canonically-
  equivalent representations of accented characters, or altering ligatures
  and fullwidth forms) — a change to the *content* being canonicalised, not
  merely to its serialised form, and therefore out of scope for a step that
  exists only to make an already-decided value byte-identical across
  languages.
- Text is encoded as valid UTF-8; **invalid or unpaired surrogate data is
  rejected outright** as a genuine defect, never silently repaired or
  replaced with a substitution character.
- **RFC 8785/JCS's own escaping and ordering rules apply after, and only
  after, these domain-level string rules** — JCS governs how a given,
  already-decided string is represented as JSON; it does not decide what
  that string's content is.

**Shared Python/PHP canonicalisation test vectors are required, not
optional**, covering: canonical UUIDs (confirming both languages emit the
identical lowercase hyphenated form); non-ASCII text; a pair of
canonically-equivalent but code-point-distinct strings (confirming both
survive **distinctly**, proving no incidental re-normalisation occurs);
combining marks; emoji and other supplementary-plane characters
(confirming correct UTF-8 output despite Python's internal string
representation and PHP's own string-handling differences); the null-
versus-absence distinction; a multi-row/multi-column table; each of the
three source-location variants; an unknown element with a non-trivial
preserved payload; numeric values at each `confidence` boundary (0, 1, and
absent) and confirmation that a non-finite value is rejected; a non-empty
`extraction_warnings` and `changes` list. Both languages must independently
canonicalise every vector to byte-identical output — verified by test, not
by inspection — before this ADR's implementing session is accepted.

**The projection-manifest and warning-manifest digests (below) consume
these same canonical rules** — one shared canonicalisation contract, not
two independently-reasoned ones.

### Immutable, lease-bound artefact upload

Replacing the prior draft's looser "authorise a key, verify what comes
back" description with a durable, lease-bound record that makes the whole
sequence safe against overwrite, staleness, and partial completion:

A durable **upload-authorisation record** binds together: workspace and
document identity; the owning ingestion event/claim identity; a purpose
scope (`extraction_artifact_upload`); the exact object key Python may write
to; a unique attempt/key identity; a **lease generation** (mirroring
`IngestionEventClaim`'s existing lease shape); the expected schema/contract
version; and an expiry.

- **A conditional-create (or equivalent object-version binding, where the
  object storage backend supports it) prevents overwriting a previously
  verified artefact** at the same key — an already-verified artefact is
  immutable in fact, not merely by policy; the write path itself refuses a
  second write once one has already been accepted.
- **Python may write only to the exact authorised key** — verified by
  Laravel, never trusted from Python's report.
- **Laravel independently observes object size and computes a streamed
  SHA-256** over the object as actually stored — never accepting Python's
  self-reported checksum as sufficient on its own.
- **The object's version identifier or ETag, where the storage backend
  provides one, is recorded as an additional, secondary fact — never
  substituted for the SHA-256 checksum**, which remains the one identity
  every downstream consumer (ADR-0031's clone proof, ADR-0037's export
  manifest) actually relies on. An ETag is storage-backend-specific and
  is not guaranteed to be a content hash on every provider; SHA-256,
  computed by Laravel itself, is.
- **Only the current lease generation may acknowledge** — an
  acknowledgement referencing a stale, superseded lease generation is
  rejected outright, exactly the discipline ADR-0025's own lease-renewal
  gating already establishes for ingestion.
- **A conflicting acknowledgement (a different checksum reported for an
  already-verified key, or an acknowledgement against a lease generation
  that has already been superseded) fails closed** — never silently
  accepted as an update.
- **An incomplete upload can never become "verified"** — verification
  requires the object to genuinely exist at the authorised key with a
  computed checksum, size, and schema version all within bounds; a
  reported-but-not-actually-present object simply fails this check.

### Artefact orphan sweep — defined here, not assumed to already exist

**Withdrawn: every earlier statement in this decomposition that a suitable
general orphan sweep "already exists" for artefact uploads.** Verified on
review that no such mechanism exists anywhere in this codebase today —
only the *concept* of bounded cleanup exists (staged-object retention,
ADR-0034), not a mechanism this ADR could actually point to and reuse
unmodified. **This ADR therefore defines a new, database-led, bounded
cleanup mechanism**, built directly on the upload-authorisation record
above rather than on any assumed pre-existing sweep.

**Every upload-authorisation record already carries, per "Immutable,
lease-bound artefact upload" above, everything the sweep needs**:
workspace/document/event/claim identity; the exact object key; the
attempt/lease generation; created time; expiry; verification/publication
state. This ADR adds two further fields to the same record: **cleanup
state** (e.g. `not_needed` / `eligible` / `claimed` / `deleted` /
`failed`) and **retry metadata** (attempt count, last attempt time).

**The sweep:**

- Selects expired or stale authorisation rows — those past their expiry
  with a verification/publication state that never reached success, or
  belonging to an ingestion/clone attempt that itself terminally failed or
  was cancelled — in **bounded batches**, never an unbounded scan.
- **Claims/locks each selected row idempotently** before acting on it (the
  same claim-then-act discipline every other bounded sweep in this
  decomposition already uses), so two concurrent sweep runs cannot both
  attempt to delete the same object.
- **Deletes only the exact object key recorded on that authorisation
  row** — never a prefix scan, a bucket listing, or any operation broader
  than "delete this one, already-known key."
- **Never deletes an object still referenced by an active, published
  `DocumentExtractionArtifact` record** — before deleting, the sweep
  re-checks that no current, verified `DocumentExtractionArtifact` points
  at this exact key; a key that has since become the active artefact for
  its version (the ordinary, successful path) is never touched, regardless
  of what the authorisation row's own state says, as a final safety check
  against acting on stale information.
- **Coordinates with the live lease generation**: a row whose lease
  generation is still current and unexpired is never selected, regardless
  of how long ago it was created — the sweep only ever acts on genuinely
  lapsed attempts, never a still-active upload in progress.
- **Records success or failure of the deletion itself**, retries bounded
  failures (a fixed, small number of attempts, not indefinitely), and
  **surfaces a row that remains stuck after exhausting its retries** in the
  same administrative "visibly stuck" read model this decomposition
  already uses elsewhere for stuck operations — never a silently-abandoned
  cleanup attempt.
- **Covers every case that leaves an artefact object without a durable
  owner**: an upload Python never acknowledged; an acknowledgement that
  failed verification; and an attempt that was explicitly cancelled or
  superseded by a fallback to ordinary ingestion.

### Atomic projection publication

**Readers must never observe a partial or mid-rebuild projection.**
Replacing the prior draft's implicit "just insert the rows" description:

- Every projection build targets a new, **inactive generation/build
  identity** — analogous in shape to `workspace_corpus_generation`'s own
  active/inactive lifecycle (ADR-0014), reused as a pattern, not a shared
  table.
- The artefact is parsed with a **streamed reader**, never loaded whole
  into memory before parsing (the same memory-safety requirement already
  fixed for checksum computation, applied to parsing as well).
- Rows are inserted in **batches**, not one statement per element.
- **Expected counts and the projection/warning-manifest digests** (computed
  from the artefact directly, per "Canonical serialisation and digests"
  above) are known before insertion begins.
- **Verification precedes publication**: once the new generation's rows are
  fully inserted, Laravel recomputes both digests from the persisted rows
  and compares them to the expected values computed from the artefact — a
  mismatch aborts publication of this generation entirely, leaving the
  previously-published generation (if any) untouched and still the one
  readers see.
- **An atomic switch** — a single, transactional update of "which
  generation is active for this version" — is the only step that changes
  what a reader observes; there is no window in which a reader could see a
  half-inserted generation.
- **A failed build's rows remain invisible** (never activated) **and the
  build is retryable** — retrying targets a fresh generation identity, never
  resumes a partially-inserted one, so a retry can never produce a mixed
  partial-plus-fresh result.
- **Rebuild is idempotent**: rebuilding from the same artefact, any number
  of times, produces a new generation whose digests match the same expected
  values every time.
- **The old generation's rows are cleaned up only after the new one is
  successfully published** — never eagerly torn down in a way that could
  leave a reader with neither.

**Pagination uses deterministic `(ordinal, id)` ordering** — the explicit
persisted ordinal, tie-broken by the element's own stable UUID identity, so
that paginated reading and diffing never depend on an implicit, storage-
engine-dependent row order.

**Search-within-document uses a document-scoped PostgreSQL full-text
index** — a generated `tsvector` column plus a GIN index, scoped to one
version's active projection generation — **without altering retrieval
behaviour in any way**: this is a Postgres-native text search over the
*display* projection, entirely separate from, and never consulted by,
ADR-0018/0021's embedding-based retrieval pipeline.

### Reliable proxied source delivery

**`DocumentObjectStorage` (or the accepted storage abstraction) is
extended, not replaced, with three new capabilities**: metadata/stat (size,
content type, existence, without fetching the body); a bounded stream read;
and a single-range byte read.

**The browser-facing route supports both `GET` and `HEAD`**, and behaves as
a genuine, if minimal, HTTP range server, with a completely specified
grammar and semantics rather than a general description:

**Accepted `Range` grammar — exactly one range per request, of exactly
three forms:**
- `bytes=N-M` — start at byte `N`, end at `min(M, length - 1)`; **rejected
  as invalid** if `N > M`.
- `bytes=N-` — start at byte `N` through `length - 1`.
- `bytes=-S` — the final `S` bytes of the object; **rejected as invalid**
  if `S` is zero or otherwise malformed; if `S` is larger than the object's
  full length, it is **clamped to the full length** (the whole object is
  served as the "final S bytes," rather than treated as an error).
- All byte positions are **inclusive**.
- **A start position at or beyond the object's length is unsatisfiable.**
- **Multiple, comma-separated ranges are unsupported** — a request naming
  more than one range receives the same typed unsatisfiable (`416`)
  response as an invalid range, never a `multipart/byteranges` body.

**Responses, specified per method and outcome:**
- `GET` without a `Range` header → `200`, the full object, full
  `Content-Length`.
- `GET` with a **satisfiable** `Range` → `206`, with
  `Content-Range: bytes <start>-<end>/<length>`, `Content-Length` equal to
  the selected byte count, and `Accept-Ranges: bytes` present.
- `GET` with an **unsatisfiable or invalid** `Range` → `416`, with
  `Content-Range: bytes */<length>` and no body beyond the safe standard
  error representation this route already returns for other rejected
  requests.
- `HEAD` without `Range` → the **same status and headers** `GET` would
  return for that request, with no body.
- `HEAD` with a satisfiable `Range` → `206` and the same range headers
  `GET` would return, with no body.
- `HEAD` with an unsatisfiable `Range` → `416` and
  `Content-Range: bytes */<length>`, with no body.
- **`Accept-Ranges: bytes` is present on every response**, `GET` or
  `HEAD`, range or not, so a client always knows range support is
  available.
- **Zero-length objects are handled explicitly**: any `Range` request
  against a zero-length object is unsatisfiable (`416`,
  `Content-Range: bytes */0`); an ordinary `GET`/`HEAD` returns `200` with
  `Content-Length: 0`.
- **`If-Range` is explicitly unsupported and ignored in V1** — stated
  outright rather than left ambiguous. This platform's source objects are
  immutable once a version reaches `INDEXED` (ADR-0007/ADR-0017), so
  `If-Range`'s purpose (avoiding a mismatched partial response after the
  underlying resource has changed) does not apply to a resource that
  cannot change once retrievable; implementing it would add real
  conditional-request logic for a case this route's own object model makes
  moot.
- **No multipart ranges in V1**, per the single-range grammar above.
- **Authorisation and tenant concealment are resolved before any metadata
  or range disclosure** — an unauthorised or concealed request never
  reaches the point of learning the object's length or whether a range
  would be satisfiable.
- `Content-Type` is derived from validated, stored metadata — never a
  user-supplied filename or extension.
- `Content-Disposition` is deterministic and RFC 5987-safely encoded for
  any non-ASCII filename component; `inline` for formats rendered in the
  browser, `attachment` for formats offered only as a download.
- `X-Content-Type-Options: nosniff` is present on every response, without
  exception.
- **The stream closes cleanly on client disconnect**, and the route reads
  and forwards bytes under bounded memory (a fixed-size buffer per chunk,
  never the whole object materialised before forwarding) — genuine
  backpressure, not an unbounded read racing an unbounded write.
- **Reauthorisation and tenant concealment happen on every request**,
  `GET` or `HEAD`, range or not — there is no cheaper, less-checked path
  for a `HEAD` or a range request.
- **No presigned URL is ever issued to the browser for this route** — the
  route itself is the one and only access mechanism, eliminating the
  header-ownership contradiction an earlier draft of this decision left
  unresolved (Laravel cannot apply headers to a response served by a
  redirect target it does not control).
- **Telemetry logs only**: stable workspace/document public identity,
  result status (`200`/`206`/`404`/`416`/etc.), the requested range (if
  any), and the byte count served — **never** source bytes, any filename-
  derived value that has not been through the same validation this route
  itself applies, the source URL (ADR-0030), or extracted content. This is
  the same allowlist-over-blocklist discipline ADR-0026 already requires
  for platform telemetry generally, applied here specifically.
- **TXT content crosses an explicit escaping boundary before any HTML
  rendering** — raw TXT bytes are never interpolated into a browser-
  rendered page without HTML-entity escaping applied first, closing an
  otherwise-real stored-content injection surface.
- **DOCX remains attachment-only in V1** — download, plus the extracted-
  text view, never an inline rendered preview — unless a separately
  accepted, genuinely safe conversion pipeline exists, which this ADR does
  not design.
- A deleted or otherwise inaccessible source presents no dead "View
  source" action at all.
- An identifier for another workspace, or an invalid identifier, resolves
  through the same tenant-safe concealment behaviour every other route in
  this codebase already uses.

### Extracted-text presentation contract

The active projection generation is exposed, read-only, through its own
authorised route:

- Clearly labelled, verbatim: "Text Dolved extracted for search" — honestly
  identifying it as Dolved's **normalised search representation**, distinct
  from the original source (above).
- Presents ordered, readable elements — headings, paragraphs, tables — with
  source locations shown where genuinely available.
- Surfaces extraction warnings and, where useful to a reader, normalisation
  changes.
- Supports search within the document's own text, via the document-scoped
  full-text index above.
- Paginated using the deterministic `(ordinal, id)` ordering above — never
  a single unbounded payload.
- Never exposes raw internal JSON, embeddings, or provider/model reasoning.
- States plainly that extracted text may not preserve the source's visual
  layout or table structure exactly.

### Deletion and clone interaction

- Ordinary deletion (ADR-0025, extended by ADR-0031 for families) removes
  the artefact, **every projection generation** (not only the currently
  active one — an orphaned inactive generation from an aborted rebuild must
  not survive its version's deletion), and warnings, alongside source,
  chunks, and vectors, at the same trigger point.
- Applicability cloning (ADR-0031) creates an independently owned copy of
  the artefact and an independently built projection for the target
  version — never a shared reference.
- Failed-ingestion and orphan cleanup includes artefacts and inactive
  projection generations belonging to an attempt that never completed.
- No retained artefact may become cross-workspace accessible.

### Version comparison and export seams

Unchanged in substance: this ADR supplies the deterministic, ordered
representation ADR-0033's comparison UI and ADR-0037's export both consume,
without designing either.

### Contract surface — unchanged versus new

**Unchanged:** `document_chunks` and every chunking/embedding/retrieval
contract built on it; every existing schema under
`contracts/http/ingestion-worker/v1/`.

**New, introduced by this ADR:**
- The `DocumentExtractionArtifact` canonical schema (the actual
  `NormalisedDocument` fields listed above, RFC 8785-canonicalised, with
  explicit UUID and Unicode canonical-form rules).
- The upload-authorisation record, its lease-bound protocol, and its
  dedicated orphan-sweep mechanism.
- A new, small, typed artefact-submission acknowledgement schema.
- The projection-manifest and warning-manifest digest algorithms, shared
  with ADR-0031's clone verification.
- The atomic projection-generation publication mechanism.
- Two new Laravel-facing browser HTTP contracts: authorised, range-capable
  source delivery, and authorised extracted-text delivery.
- Shared Python/PHP canonicalisation test vectors.

## Alternatives considered

### Extending `document_chunks` with heading/page-display fields

Rejected — chunk boundaries can deliberately overlap and are
retrieval-tuned, not reading-order-tuned.

### A simplified restatement of `ExtractedDocument`, rather than the actual
### `NormalisedDocument`

This was closer to the prior draft's shape, and is corrected here.
`ExtractedDocument` is the raw-extraction layer; `NormalisedDocument` is
what chunking already consumes, already carries richer structure
(normaliser identity, `changes`, per-element `confidence`), and is the
object this platform has already decided is the right level of
abstraction for downstream consumption. Building this ADR's artefact from
a simplified `ExtractedDocument` restatement instead would have created a
second, competing normalisation-adjacent concept with no reason to exist
alongside the one already established.

### Including `workspace_id`/`document_id` in the canonicalised bytes

Considered — they are, after all, real fields on `NormalisedDocument` — and
rejected specifically because it would break byte-identical clone reuse
(ADR-0031): a clone changes ownership but not content, and canonical bytes
that encode ownership cannot remain unchanged across that operation.
Ownership is bound externally, on the `DocumentExtractionArtifact` record
and its object key, never inside the checksummed bytes.

### A single immutable JSON blob artefact, with no relational projection

Rejected as the sole mechanism, retained as the canonical half of the
hybrid — see the prior drafting pass's reasoning, unchanged.

### A relational-only representation, with no canonical artefact

Rejected — without a canonical artefact, a failed projection build has
nothing durable and provider-free to retry from.

### Splitting transport into an inline-small and object-storage-large path

Withdrawn in the prior correction pass, remains withdrawn.

### Presigned-URL redirect for source delivery

Rejected in the prior correction pass for the header-ownership
contradiction it created; this revision goes further and adds the
Range/HEAD requirements a genuine proxy must support to be a real
replacement, not merely a "does not redirect" fix.

### Multi-range byte requests in V1

Considered, and rejected as unnecessary scope: single-range requests
already satisfy browser PDF viewers' actual seeking behaviour; multi-range
support adds real response-construction complexity (`multipart/byteranges`)
for a case no identified V1 client needs.

### Leaving canonicalisation unspecified, trusting each language's default
### JSON serialiser to agree

Considered, and rejected outright: default JSON serialisation in most
languages, including PHP's and Python's, does not guarantee stable key
ordering or number formatting across implementations or versions — exactly
the ambiguity a cross-language checksum cannot tolerate. RFC 8785 exists
specifically to close this gap and is adopted rather than reinvented.

### Applying additional Unicode normalisation (NFC/NFKC) during artefact
### canonicalisation

Considered, and rejected: `NormalisedDocument`'s text is already the
authoritative search input the normaliser produced; re-normalising it
again at the artefact-serialisation boundary would silently change content
(not merely its serialised form) in ways a future reader has no way to
detect or reverse. Canonicalisation here governs how an already-decided
value is represented as bytes, never what that value is.

### Assuming a general orphan sweep already exists for artefact uploads

This was asserted in an earlier working draft of this decomposition and is
withdrawn here on review: no such mechanism exists in this codebase today.
The artefact orphan sweep above is a new mechanism, built on the upload-
authorisation record this ADR already introduces, not a reuse of anything
pre-existing.

## Consequences

### Positive

- The canonical artefact is now provably the actual object chunking already
  consumes, not a parallel invention — eliminating an entire category of
  future "which one is authoritative" confusion.
- Excluding ownership from canonicalised bytes is what actually makes
  ADR-0031's provider-free clone possible — a structural enabler, not
  incidental.
- RFC 8785 canonicalisation with shared test vectors removes an entire
  class of "it worked in Python but not PHP" defects before they can occur.
- Atomic projection publication means a reader can never observe a
  half-built document, and a failed rebuild costs nothing but a discarded
  generation.
- Genuine Range/HEAD support makes source viewing behave like a real
  document server for PDF viewers that depend on it, rather than an
  approximation that happens to work for small files.

### Negative

- The canonicalisation and digest machinery is genuinely more work than a
  bare checksum comparison — real, shared, cross-language logic that must
  be kept in sync between Python and PHP.
- The lease-bound upload-authorisation record and atomic projection
  publication are both new, durable, stateful mechanisms, not simple
  request/response calls — more moving parts than the prior draft's looser
  description implied.
- Range-serving correctness (`206`/`416`/`Accept-Ranges`/`Content-Range`)
  is a real, testable surface with genuine edge cases (unsatisfiable
  ranges, disconnects mid-stream) that must be covered by tests, not
  assumed correct by construction.
- A version's content is now durably stored in three shapes (chunks,
  artefact, projection) rather than two — an accepted, explicit storage
  cost.

## Scope boundaries

This ADR does not define:

- Any change to `document_chunks`, chunking, embedding, or retrieval
  semantics.
- The comparison UI or diff-presentation design — R24, under ADR-0033.
- The comprehensive export package format or snapshot/cutoff mechanism —
  ADR-0037.
- The exact numeric values of any configured cap — R23 implementation
  measurement.
- A DOCX inline-conversion pipeline.
- Multi-range HTTP requests.

## Implementation and session allocation (R23)

Illustrative, not binding on Codex's eventual session boundaries:

- **R23-S03a — Canonical schema and digests.** `DocumentExtractionArtifact`
  schema (the real `NormalisedDocument` field set); RFC 8785
  canonicalisation in both Python and PHP; shared cross-language test
  vectors; projection/warning-manifest digest algorithms.
- **R23-S03b — Upload authorisation, worker acknowledgement, and orphan
  sweep.** The lease-bound upload-authorisation record (with cleanup state
  and retry metadata); Python-side artefact construction and upload; the
  small, typed acknowledgement schema; the new database-led, batch-bounded
  orphan sweep, including its active-artefact and live-lease safety
  checks.
- **R23-S03c — Atomic projection publication.** Generation/build identity;
  streamed parsing; batched insertion; verify-then-switch publication;
  old-generation cleanup; the document-scoped full-text index.
- **R23-S03d — Source and extracted-text routes.** Range/HEAD-capable
  proxied streaming delivery; format-specific rendering rules; the
  extracted-text presentation surface; the allowlisted telemetry shape.
- **R23-S03e — Tests and acceptance evidence.** Cross-language
  canonicalisation test vectors (including UUID canonical form and the
  canonically-equivalent-but-distinct-code-point pair); lease/conflict/
  staleness fail-closed behaviour; the orphan sweep's active-artefact and
  live-lease safety checks; atomic-publication race and failure tests; the
  complete Range grammar (`N-M`, `N-`, `-S`, zero-length, `If-Range`
  ignored) and HEAD-parity correctness (`206`, `416`, disconnect handling);
  deletion/orphan cleanup across all three stored shapes; the complete
  "R23 acceptance evidence" requirement below — this session is not
  accepted as complete without it.

**R23 acceptance evidence, required before this implementing work is
accepted as complete — technical values selected through measured testing,
never a product-owner decision:**

- Selected numeric values for every configured cap (artefact bytes, element
  count, per-element text length, warning count, schema version set,
  processing/projection timeout), with reasoning for each, and an explicit
  justification against the existing, verified `DOCUMENT_MAX_UPLOAD_MB` =
  25MB source-file ceiling.
- The largest representative PDF, DOCX, and TXT document actually tested,
  with real element/warning counts and resulting artefact sizes.
- Peak Laravel memory during streamed checksum verification and streamed
  artefact parsing for that largest tested case.
- Peak Python memory during artefact creation for that largest tested case.
- Batched-insertion projection-build duration for that largest tested case.
- PDF range-request load-test results (repeated partial reads against a
  large PDF, confirming correct `206` behaviour and bounded memory under
  concurrent range requests).
- Aborted-download behaviour (a client disconnecting mid-stream) confirmed
  to release resources cleanly, with no leaked stream or unbounded buffer.
- The observed failure behaviour for a document immediately above each cap
  — a clean, typed rejection, never a silent truncation.
