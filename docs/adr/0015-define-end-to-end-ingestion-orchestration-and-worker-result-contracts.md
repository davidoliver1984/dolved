# ADR 0015: Define End-to-End Ingestion Orchestration and Worker Result Contracts

## Status

Accepted

## Date

2026-08-06

## Relationship to prior ADRs

### Supersession of ADR-0009, in part

ADR 0009 authenticated exactly one internal request — the Python ingestion
worker's `QUEUED → PROCESSING` claim — and said so deliberately: *"Use
HMAC-SHA256 authentication only for the internal endpoint through which the
Python ingestion worker requests the Document processing claim,"* closing
with its own instruction that *"if more internal principals or permission
scopes appear, this narrow protocol should be replaced or superseded rather
than expanded into an improvised general identity system."* This document is
that supersession, not an improvisation.

**Carried forward unchanged**: HMAC-SHA256 as the signing algorithm; the
key-ring model (non-secret Key ID, Base64-encoded secret decoding to at least
32 bytes, the dedicated `ingestion-worker` identity, overlapping enabled keys
during rotation); the configurable clock-skew freshness window; constant-time
signature comparison; the safe-logging allowlist (Key ID, event ID,
correlation ID, verification outcome — never the secret, signature, exact
signed body, credentials or document content); the requirement that Python
never writes to Laravel's database directly; and the principle that
authentication alone does not solve at-least-once delivery, so durable,
`event_id`-keyed idempotency remains mandatory regardless of transport
authentication.

**Superseded**: the claim-only scope restriction, replaced by the
purpose-scoped protocol in "Purpose-scoped authenticated worker protocol"
below; and the claim's own idempotency model, refined by "The processing
lease" below to account for a claimant that crashes after claiming, which
ADR 0009 did not address.

ADR 0009 is not rewritten and remains part of the historical record for the
claim endpoint's original design. This document does not modify it.

### Roadmap clarification for ADR-0013 and ADR-0014

ADR 0013 and ADR 0014 are accepted, immutable, and not rewritten by this
document. Both predate the insertion of Phase 15 (Ingestion Orchestration)
into the roadmap, recorded in `IMPLEMENTATION_GUIDE.md`'s "Phase 15 insertion
note." Their existing references to "Phase 15" retrieval work now resolve to
**Phase 16**; their references to later phases (generation, and so on) shift
by one in the same way. This is a citation correction only — neither ADR's
underlying architectural decision changes as a result, and this document
makes no claim otherwise.

## Context

Phase 14 (ADR 0014) completed the storage foundation: PostgreSQL durably owns
canonical chunk text, embedding-profile lineage, and both the embedding-space
and workspace-corpus generation lifecycles; Qdrant is a disposable,
rebuildable vector projection reached only through the provider-neutral
`VectorStore` boundary; point identity is deterministic; completeness is
verified by identity and schema, never by count alone. Phase 14 deliberately
stopped there. Its own Stage 14.4 record says so explicitly: *"R14-S03 does
not connect these foundations to the ingestion worker or Document
lifecycle."*

What remains undecided is not a storage question. It is a distributed-systems
question this platform has not yet had to answer anywhere else: **how do two
independently deployed, independently failing services cooperate to produce
one authoritative outcome?** Laravel owns the Document lifecycle absolutely
(ADR 0007) and Python owns everything that happens to a Document's content
while it is `PROCESSING` (ADR 0002, ADR 0010, ADR 0011, ADR 0013, ADR 0014).
Between those two facts sits a real seam: Python computes a result Laravel
alone is authorised to act on, across a boundary that can partially fail in
ways neither service can single-handedly observe or repair — a crashed
worker, a redelivered message, a network partition between a completed
Qdrant write and the call that would have reported it.

ADR 0008 already named this seam without resolving it: *"Any further
lifecycle transition Python needs... is requested through an authenticated
internal application boundary. The exact authentication mechanism for that
boundary is not decided by this ADR."* ADR 0009 resolved exactly one such
transition — the claim — and explicitly deferred the rest. This document
resolves the rest: canonical chunk transfer, successful completion, permanent
failure, the authenticated protocol carrying all of them, the trust boundary
between the two services, and the queue and reconciliation semantics that
keep a Document from becoming permanently stuck.

## What this ADR decides and does not decide

This ADR defines the **contract** — service ownership, authoritative state
transitions, what each service asserts versus independently verifies, the
shape of the canonical-chunk and completion payloads, failure classification,
queue acknowledgement and reconciliation semantics — not primarily an
implementation mechanism. Authenticated HTTP is the mechanism chosen to carry
that contract (see "Communication mechanism" below), exactly as HTTP already
carries ADR 0009's claim; it exists to satisfy the contract, not to define
it. Exact route paths, header names, the lease token's physical
representation, its expiry duration, the digest canonicalisation library, and
the DLQ-reconciliation mechanism are Stage 15.2 implementation decisions this
ADR constrains but does not fix.

## Decision

### Service ownership is unchanged

Laravel remains the sole owner of PostgreSQL, Document identity and
lifecycle, canonical chunk text, and both generation lifecycles (ADR 0007,
ADR 0014). Python remains the sole owner of extraction, normalisation,
chunking, embedding, `VectorStore`, Qdrant, and vector-side completeness
verification (ADR 0002, ADR 0010, ADR 0011, ADR 0013, ADR 0014). Nothing in
this ADR moves any of that. The gap this ADR closes is the protocol by which
Python's computed results cross into Laravel's domain so Laravel — and only
Laravel — can perform the writes and transitions it alone is authorised to
make. Direct Python writes to Laravel-owned tables remain rejected, exactly
as ADR 0009's alternatives-considered section already rejected them.

### Document lifecycle is unchanged — no new state

`UPLOADING → UPLOADED → QUEUED → PROCESSING → INDEXED`, with `FAILED` reached
only from `PROCESSING`, remains sufficient. `PROCESSING` continues to cover
the entire internal pipeline — claim, extraction, normalisation, chunking,
embedding, vector persistence, and result reporting — as one
observationally-atomic state, exactly as ADR 0007 already requires: *"A
partial write... does not qualify as `INDEXED`; the Document remains
`PROCESSING`, or moves to `FAILED`, until the complete representation is in
place."* This ADR's completion contract (below) is designed as a single
atomic Laravel transaction specifically so that requirement continues to
hold without qualification. No intermediate state (`INDEXING` or equivalent)
is introduced: under a single-transaction completion, there is no durable
midpoint for such a state to describe — it would either be decorative or
would force the completion into multiple Laravel-visible steps for no
identified benefit. Progress *within* `PROCESSING` — which stage a worker is
currently in, how many chunks have been submitted so far — is a telemetry
and processing-attempt concern (see "Observability" below), not a Document
lifecycle concern.

### The processing lease

`event_id` remains the durable, at-least-once, retry-spanning identity for
one logical processing attempt, exactly as ADR 0008 and ADR 0009 established.
It is not enough on its own for this phase, because it says nothing about
*which currently-live worker*, if any, is entitled to act on that attempt
right now — and a worker can crash after successfully claiming. This ADR
introduces a **processing lease**: a Laravel-owned, time-bounded grant of
current ownership over one `event_id`'s in-progress work, layered on top of
the durable `event_id` rather than replacing it.

A claim request resolves to exactly one of:

- **proceed** — no live lease exists (fresh claim, or the prior lease has
  expired); Laravel issues a fresh lease and the requested generation
  identities (see "Generation resolution at claim time" below), and
  transitions `QUEUED → PROCESSING` if this is the first successful claim for
  this `event_id`;
- **owned by another live worker** — a lease for this `event_id` is currently
  held and has not expired; the requesting worker must stand down without
  reprocessing, rather than duplicate expensive provider work;
- **already completed** — this `event_id` already reached `INDEXED`; the
  worker acks and stops;
- **permanently failed** — this `event_id` already reached `FAILED`; the
  worker acks and stops;
- **reclaimable** — a lease exists but has expired (its holder is presumed
  dead); Laravel issues a fresh lease, exactly as in the fresh-claim case,
  so a crashed claimant never permanently strands the Document in
  `PROCESSING`.

Every subsequent call for this `event_id` — canonical chunk submission,
completion, or failure reporting — must present the **currently valid lease**
issued by the most recent successful claim outcome, not merely the
`event_id`. Laravel rejects any such call whose presented lease does not
match the currently active one, or which has since expired: a stale worker —
one that has been superseded by a reclaim — has lost the *authority* to
submit chunks, complete, or fail this attempt, even though chunks it already
durably persisted remain part of the attempt's record (see "Canonical chunk
submission contract" below for how ownership and authority are kept
distinct). A worker that already holds a valid lease from its own successful
claim does not need to re-claim before making further calls.

This is the mechanism that gives duplicate SQS delivery a safe outcome in
every case: a second delivery either finds the first attempt still live (and
stands down), finds it already terminal (and acks without rework), or finds
it abandoned (and safely takes over) — never silent duplicate provider work,
and never a permanently stuck Document.

The lease's physical representation (a dedicated table, columns added to the
existing durable event/claim record, or an equivalent) and its token format
are Stage 15.2 decisions. Its expiry duration and renewal mechanics are not
independent implementation details — they are governed by the coordinated
timing model in "Lease, visibility and acknowledgement coordination" below,
because a lease that can silently drift out of step with SQS's own delivery
timing reopens exactly the hazard this ADR exists to close. This ADR fixes
the five outcomes above, the invariant that every subsequent call must prove
a currently-valid lease, and — below — how lease validity and queue
visibility stay coordinated rather than becoming two unrelated
configuration values.

### Purpose-scoped authenticated worker protocol

Every authenticated worker request — claim, chunk submission, completion,
failure — carries an explicit, signed **purpose**, so that authorisation
granted for one operation can never be replayed or reused as authorisation
for another, even if request routing changes later or two operations happen
to share a body shape. Path-binding alone (already present in ADR 0009's
string-to-sign) is not treated as sufficient on its own: an explicit signed
purpose is a second, independent check, consistent with this platform's
established preference for defence in depth over trusting any single
mechanism (ADR 0006).

The version-1 canonical string-to-sign ADR 0009 defined —
`<timestamp>\n<method>\n<request-path>\n<body-sha256>\n<event-id>` — is
extended to a version-2 form carrying purpose as its sixth field:

```text
<timestamp>\n<method>\n<request-path>\n<body-sha256>\n<event-id>\n<purpose>
```

signed identically (HMAC-SHA256 over the exact byte sequence, using the
strictly-decoded key-ring secret) and presented with a `v2=` signature
prefix. At minimum, four purposes are defined:

- `ingestion.claim`
- `ingestion.chunks.submit`
- `ingestion.complete`
- `ingestion.fail`

Laravel verifies that the signed purpose is both cryptographically valid
*and* consistent with the endpoint actually invoked — a request signed for
one purpose must fail verification if presented anywhere else, not merely
fail routing. Exact header names and the exact route paths per purpose are
Stage 15.2 decisions.

#### `v1`-to-`v2` migration policy

ADR 0009's underlying cryptographic primitives — HMAC-SHA256, the key-ring
model, freshness/replay handling, constant-time comparison — are unchanged
and shared by both signature versions; `v1` and `v2` differ only in the
string-to-sign shape (five fields versus six) and in scope. That scope
difference is bounded, not open-ended:

- `ingestion.chunks.submit`, `ingestion.complete` and `ingestion.fail` are
  new operations with no prior existence under ADR 0009. They require `v2`
  from their first implementation. `v1` must never authorise any of them,
  under any circumstance, including during migration.
- `v1` may continue to be accepted **only** for `ingestion.claim`, and only
  for a bounded deployment/migration window while previously-deployed
  workers that do not yet speak `v2` are being replaced. Laravel may verify
  `v1` and `v2` claim signatures concurrently during that window; this is a
  temporary accommodation for rollout, not a standing feature.
- Once every deployed worker supports `v2`, `ingestion.claim` also requires
  it, and `v1` acceptance is removed from Laravel entirely. Permanent
  dual-protocol support is explicitly not the target architecture — the
  window exists to make a clean cutover possible, not to avoid deciding one.
- Removing temporary `v1` compatibility is not an incidental cleanup: Stage
  15.2 must track it as an explicit, tested piece of work (a negative test
  proving a `v1`-signed request is rejected once removal ships), not
  something left to happen whenever convenient.

The exact duration of the migration window, and whether removal is a single
cutover or a staged rollout, are Stage 15.2 decisions; that the window is
bounded, claim-only, and ends in `v1`'s complete removal is not.

The following non-secret protocol test vector is **normative** for
cross-language `v2` signing conformance — both the Laravel and Python
implementations are expected to reproduce it exactly as part of Stage 15.2's
test suite, in the same spirit as ADR 0009's own `v1` vector (computed and
independently re-verified against ADR 0009's published vector while drafting
this document, to confirm the canonicalisation method is consistent):

```text
secret (Base64):
MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=

timestamp:
1785326400

method:
POST

request path:
/api/internal/ingestion/events/5a1e9c3e-3b3a-4e2a-9c7d-1f6b6f0a2b41/complete

exact body:
{"contract_version":1,"expected_chunk_count":0}

body SHA-256:
f31918f6d20ae2153a3888e4a06f899ddffdb346889b10a5622c5cf4e0716af5

event ID:
5a1e9c3e-3b3a-4e2a-9c7d-1f6b6f0a2b41

purpose:
ingestion.complete

expected signature:
v2=3ed660fd462b535fc169849ffcd4383324ae05c52921b3bb08b748e55aa4bc97
```

### Generation resolution at claim time

Python must know which embedding-space generation and which workspace corpus
generation to write against *before* it embeds or upserts a single vector,
since both identities are part of every Qdrant point's deterministic
identity and payload (ADR 0014). Resolving them cannot wait until
completion. A successful claim response therefore also returns:

- the platform's current `AVAILABLE` embedding-space-generation identity;
- the workspace's current workspace-corpus-generation identity to write
  against.

If the workspace has no workspace corpus generation yet, Laravel creates one,
in its initial lifecycle state, atomically as part of resolving the claim,
and returns its identity — this is the lazy provisioning described under
"Initial provisioning" below. If no `AVAILABLE` embedding-space generation
exists at all, the claim fails closed with a controlled operational error
rather than silently creating one (also under "Initial provisioning"). This
ADR does not redefine ADR 0014's generation lifecycle or activation rules —
it only fixes the moment at which a worker learns which generations apply,
so nothing is written to Qdrant against an identity Laravel has not already
authorised.

### Canonical chunk submission contract

A bounded, repeatable contract, distinct from completion, for transferring
chunk text and provenance from Python to Laravel — never chunk vectors. A
Document with more chunks than one bounded batch requires multiple
submission calls; each is independently authenticated and idempotent.

**Ownership versus authority.** Persisted chunks belong durably to the
**processing attempt identified by `event_id`**, not to whichever lease
happened to be current at the moment they were submitted. The **currently
valid lease** is a separate thing: the authority for a worker to submit or
mutate chunks *right now*. A lease expiring and being reclaimed does not
orphan or invalidate chunks already durably persisted for that `event_id` —
it only changes which worker is currently authorised to keep adding to or
completing that same attempt. Chunks remain **provisional** in a different,
unrelated sense throughout: associated with their `event_id`'s attempt, not
yet with any workspace corpus generation, and their existence alone must
never make a Document searchable or imply successful completion — that
promotion happens only at completion (below), scoped to the complete chunk
set recorded for that `event_id`, not merely whatever one worker instance
personally submitted.

Each submission call carries:

- an explicit contract version;
- `workspace_id`, `document_id`, `event_id`, and the current lease token —
  `event_id` is the durable ownership key chunks are recorded against; the
  lease token authorises this specific call to act on that attempt now;
- a bounded item count and a bounded body size (exact bounds are a Stage
  15.2 decision, informed by the provider batch limits ADR 0013 already
  requires `Embedder` to respect);
- for each chunk: its deterministic chunk identity (ADR 0011), ordinal,
  token count, source provenance, canonical content, and a digest computed
  over that content and provenance;
- no raw vectors, ever.

Laravel's acceptance behaviour:

- a submission presenting a stale or expired lease is rejected outright,
  before anything else is evaluated, regardless of payload validity;
- given a currently valid lease, an identical chunk already durably
  persisted for this `event_id` — same chunk identity, same content and
  provenance digest, and the same relevant semantic fields — is accepted
  idempotently, with no duplicate row and no error, **even when it was
  originally submitted under a prior, now-superseded lease for the same
  `event_id`**. This is what lets a successor worker resume an abandoned
  attempt without retransmitting or duplicating chunks a predecessor already
  successfully persisted;
- a conflicting submission for the same `event_id` and chunk identity — an
  identical identity paired with a *different* digest — is rejected as a
  typed conflict and never silently overwritten, regardless of which lease
  submitted the original: the same no-silent-loss discipline ADR 0010 and
  ADR 0011 already apply to their own boundaries;
- a chunk is never implicitly adopted across a different `event_id`.
  Deterministic chunk identity (ADR 0011) can coincide across two
  independent attempts at the same underlying content — for example, an
  explicit retry that creates a fresh `event_id` per ADR 0007 — and
  `event_id`, not chunk identity alone, is the durable scope idempotency and
  conflict checks are evaluated within.

Provisional chunks belonging to an attempt that ultimately fails, is
superseded, or is abandoned are not left to accumulate indefinitely or to
become searchable by accident; their eventual cleanup or bounded retention is
governed by the same reconciliation policy described in "DLQ terminal
reconciliation" below, which already requires every such attempt to resolve
to an authoritative, recorded outcome. The exact schema and cleanup
mechanism remain Stage 15.2 decisions.

### Final completion contract

Small and referential — evidence and identities, never chunk text or
vectors. A completion request carries:

- an explicit contract version, `workspace_id`, `document_id`, `event_id`,
  and the current lease token;
- the authoritative expected chunk count and a deterministic
  chunk-manifest digest, computed by Python over the complete chunk set
  durably recorded for this `event_id` — not merely whichever chunks the
  completing worker itself happened to submit, since a successor worker may
  be completing an attempt a predecessor partly populated;
- the verified vector-point count and a deterministic point-identity
  manifest digest, computed by Python per ADR 0014's completeness
  verification;
- the embedding-profile fingerprint and embedding-space-generation
  identity used;
- the workspace-corpus-generation identity the points were written against;
- a Qdrant completeness/compatibility verification status;
- a bounded, structured warning summary (count- and size-capped; codes and
  brief messages only, never chunk text or vectors) for recoverable
  compromises worth recording but not worth failing over — the same
  semantic-warning discipline ADR 0010 and ADR 0011 already use.

Laravel's acceptance is a single atomic transaction: it independently
**recomputes** the chunk count and the chunk-manifest digest from what it has
actually persisted for this `event_id`'s attempt as a whole — every chunk
durably recorded against it, regardless of which lease originally submitted
each one — and rejects the completion if either disagrees with what Python
asserts — Python's chunk
summary is never trusted blindly, because Laravel already holds the ground
truth needed to check it. Only if that check passes does Laravel promote the
provisional chunks into workspace-corpus-generation membership, apply ADR
0014's activation rules to the referenced generation where applicable, and
transition the Document `PROCESSING → INDEXED`, all within one transaction.
Both sides must compute the chunk-manifest digest via the identical,
specified canonicalisation (an ordered, deterministic encoding over chunk
identity and content digest, defined once as a shared contract artefact
rather than independently reimplemented in each language and hoped to
agree). The specification's exact repository location is a Stage 15.2
decision; that it is language-neutral, versioned, and exercised by
conformance tests in both the PHP and Python codebases — so a future change
to either implementation cannot silently drift from the other — is not. The
exact algorithm is likewise a Stage 15.2 decision, but its existence as
one shared, versioned specification is not optional.

### Trust boundary

Stated plainly, because it is a real correctness and security trade-off, not
a formality:

- **Laravel independently verifies**: request authenticity, freshness and
  purpose (the HMAC protocol above); that the presented lease is currently
  valid for this `event_id`; that the referenced embedding-space generation
  exists, is `AVAILABLE`, and its fingerprint matches Laravel's own record;
  that the submitted chunk manifest is internally consistent (sequential
  ordinals, non-blank content, digests that match the content they
  accompany); that the requested lifecycle and generation-state transitions
  are structurally legal — enforced in part by the database constraints
  Stage 14.3 already built (active-corpus-references-only-`AVAILABLE`,
  dimension matching); and, by independent recomputation, that the
  chunk-manifest digest agrees with what Laravel itself persisted.
- **Python asserts, authenticated but not independently re-derived by
  Laravel**: that chunk content is faithful to the source it extracted;
  that embedding and Qdrant persistence completed and were verified by
  identity, payload and schema per `VectorStore`'s own completeness check
  (ADR 0014); and, when reporting failure, its classification of that
  failure as transient or permanent.
- **What Laravel can never independently prove**: that the vectors actually
  exist correctly in Qdrant. Laravel has no Qdrant access and does not
  acquire any here — that would duplicate `VectorStore`'s role and puncture
  the service boundary ADR 0014 just finished establishing. Laravel records
  Python's authenticated Qdrant-side evidence honestly, as an authenticated
  assertion from a purpose-scoped, verified caller — it does not claim to
  have independently inspected Qdrant, and no consumer of this record should
  be allowed to assume otherwise.

### Failure semantics and permanent-failure reporting

Classification follows domain ownership. Python classifies failures within
its own domain — extraction, chunking, provider, Qdrant — using the same
transient/permanent taxonomy ADR 0010 and ADR 0013 already established.
Laravel classifies failures in the request itself: a malformed manifest, a
generation mismatch, an invalid transition, a stale lease. **Only Laravel
ever performs a `FAILED` transition.** Python requests it, authenticated and
purpose-scoped (`ingestion.fail`), exactly as it already requests the claim
— it never asserts the transition directly.

A callback, infrastructure, or transient processing failure is not a
processing failure of the Document — the work may have succeeded or be
retryable, only the report or an intermediate step did not land. It must not,
by itself, cost the Document a `FAILED` verdict; it is retried, bounded, with
backoff, the same way ADR 0008 already requires for outbox publication and
ADR 0009 already assumes for the claim.

**When retry exhaustion may become `ingestion.fail`.** Exhausting Python's
own bounded retry budget may be reported as a controlled permanent
processing failure only when *all* of the following hold: the failure
occurred inside Python's owned processing domain (extraction, chunking,
embedding, or a Qdrant operation); the current attempt genuinely cannot
complete as a result — no successful semantic result exists to build on;
the worker still holds a currently valid lease at the moment it reports;
and the failure taxonomy for that domain classifies retry exhaustion as
terminal for the attempt (for example, an embedding provider's rate limiting
that never clears within the retry budget, or a Qdrant write that keeps
failing validation). Reported this way, with a failure code distinguishing
"retries exhausted" from an outright permanent cause, this extends ADR
0008's and ADR 0010's existing avoid-infinite-retry-loop principle to this
boundary rather than looping forever on work that will never succeed. A
permanent failure carries both a machine-readable failure code and a
human-readable explanation, mirroring ADR 0010's existing requirement for
extraction failures.

**What retry exhaustion must never automatically become a Document
processing failure.** The following are exhaustion of a *different* kind —
of the reporting path, or of the worker's own authority, not of processing
itself — and must not, merely because a local retry budget ran out,
translate into `ingestion.fail`:

- inability to reach Laravel's callback endpoint at all;
- a completion-report transport failure occurring *after* processing itself
  already succeeded;
- inability to renew the processing lease;
- inability to extend SQS visibility;
- genuine uncertainty over whether Laravel actually accepted a prior
  callback.

None of these is evidence that processing failed — several of them are
evidence that it may have *succeeded* but could not yet be authoritatively
reported, and `ingestion.fail` requires a currently valid lease to report at
all (see "When retry exhaustion may become `ingestion.fail`" above), which a
worker in one of these states cannot reliably claim to hold. For all of
these cases: the worker does not acknowledge the SQS message; it does not
report `ingestion.fail` once it has lost, or cannot confirm, its lease
(consistent with "Lease, visibility and acknowledgement coordination"
below); and it lets redelivery, a fresh claim's authoritative status
discovery, and — if redelivery is itself exhausted — DLQ terminal
reconciliation resolve the attempt instead. This preserves a distinction
this ADR treats as load-bearing, not cosmetic: **"processing failed"** and
**"the authoritative result could not yet be delivered"** are different
facts, and only the first may ever be reported as `ingestion.fail`.

### Lease, visibility and acknowledgement coordination

The processing lease and SQS message visibility are not two unrelated
configuration values. Treating them as independent is precisely what would
let one expire while the other is still assumed valid, reopening the
duplicate-execution hazard the lease exists to close. This ADR requires one
coordinated timing and ownership model, not two separately-tuned settings
that happen to coexist.

**Headline guarantee.** At any point in time, at most one worker may hold a
currently-valid lease for a given `event_id`. A worker is authorised to
perform expensive processing (extraction, embedding, Qdrant writes) and to
make any authoritative callback — chunk submission, completion, or failure
reporting — only while it holds that currently-valid, Laravel-issued lease.
Laravel enforces this on every such call by checking lease validity, exactly
as "The processing lease" above already requires; the coordination described
here is what keeps that check meaningful over the full, possibly long,
duration of real processing, rather than only at the moment of claim.

**Coordinated renewal, not atomic renewal.** Lease renewal and SQS visibility
extension are governed by one shared heartbeat cycle and timing budget, not
tuned as two independent settings — but they are not, and cannot be treated
as, one atomic distributed transaction. Laravel owns and grants the
processing lease; the Python worker holding the SQS receipt handle owns
extending that message's visibility; these are two independent systems, and
nothing links their two operations into a single all-or-nothing commit.
Either a heartbeat cycle *attempts* both together, or two separately-issued
extensions are driven by one shared timing budget that keeps them from
drifting apart in the ordinary case — either satisfies this ADR, but both
must be designed on the assumption that one call can succeed while the other
fails, because that is a real, expected outcome, not an edge case to define
away. The renewal interval must be a meaningful fraction of both the lease
duration and the visibility-extension duration, with enough margin that one
delayed or missed heartbeat does not, by itself, cause an actively-working
lease to lapse before a retried heartbeat can succeed. Both durations must
include explicit safety margin for transient network latency and for the
clock skew ADR 0009 already tolerates for signature freshness (production
hosts are already required to maintain reasonably synchronised clocks; this
coordination depends on that same assumption holding).

This ADR does not claim, and no implementation of it may claim, that lease
renewal and visibility extension always succeed or fail together. It fixes
what must happen when they do not:

#### Partial success: lease renewal succeeds, visibility extension fails

- the worker must stop authoritative processing and callbacks all the same
  — a live lease is necessary for authority, but this ADR does not treat it
  as sufficient once the worker knows its own renewal cycle is unhealthy;
- because Laravel's lease is still live, a concurrently redelivered second
  worker that attempts to claim receives the "owned by another live worker"
  outcome and stands down — it cannot reclaim while the original lease has
  not expired, regardless of the visibility-extension failure;
- recovery proceeds through the lease's own eventual expiry and authoritative
  reclaim, not through a second worker racing to complete concurrently;
- this is a safe, if suboptimal, outcome: no two workers can believe
  themselves authorised at once, only a delay until the lease naturally
  lapses.

#### Partial success: visibility extension succeeds, lease renewal fails

- the worker must stop authoritative processing and callbacks — a live SQS
  visibility window is not, by itself, authority to act; only a currently
  valid Laravel lease is;
- the message may remain invisible to other consumers until the extended
  visibility period elapses, which may delay how quickly a fresh claim
  attempt can occur;
- this delay is an accepted cost, not a correctness gap: it must never be
  treated as license to permit stale completion, and a worker in this state
  must not submit chunks, complete, or fail the attempt on the strength of
  its still-extended visibility;
- once visibility does expire, redelivery may reclaim the attempt only after
  Laravel itself authoritatively determines the prior lease has expired,
  exactly as in the ordinary reclaim case.

**Failure or uncertainty in either renewal is authoritative.** A worker that
cannot confirm both its lease and its SQS visibility are currently healthy —
whether because Laravel rejected a renewal, because Laravel was unreachable,
because the SQS extension call failed, or because it cannot tell which of
these happened — must stop making authoritative callbacks immediately. It
must not submit chunks, report completion, or report failure on the belief
that its work is valid, because by definition it no longer knows that with
authority: either it has genuinely been superseded, or its status is
unknown pending Laravel's own record. The safe action is to stop and let the
message follow its natural course — redelivered to a fresh claim attempt,
which authoritatively resolves the worker's true status via the outcomes
"The processing lease" already defines (still owned by a live lease if
Laravel's side of renewal actually succeeded, reclaimable if it did not).
The coordinated timing budget above minimises how often partial success
occurs; it does not, and cannot, eliminate it, and this ADR does not pretend
otherwise.

**Reclaim is Laravel-authoritative, not client-computed.** A second worker
may take over an attempt only once Laravel itself determines, server-side,
that the prior lease has expired — never because a worker locally computed
that enough time had passed. This is what "reclaimable" in "The processing
lease" above already means; it is restated here because it is the property
that makes coordinated renewal meaningful: a worker's only way to remain
authorised is to keep its lease valid *by Laravel's own record*, not by its
own clock.

**Acknowledgement.** The worker acknowledges the ingestion SQS message only
after Laravel durably accepts `INDEXED`, or durably accepts a permanent
`FAILED` result — extending, not replacing, ADR 0009's existing rule that
the worker acknowledges only after Laravel confirms the claim. Transient
processing, callback, or infrastructure failures leave the message
unacknowledged for bounded redelivery, consistent with ADR 0008's and ADR
0004's existing at-least-once and redrive model, and consistent with
"failure or uncertainty in either renewal is authoritative" above: an
unacknowledged message is safe to redeliver precisely because a worker that
has lost, or cannot confirm, its lease has already stopped acting on it.

Because full processing (extraction through embedding through Qdrant through
reporting) can materially exceed the shorter duration a claim-only design
would have assumed, visibility must be maintained for at least as long as
the lease remains valid and work is legitimately progressing — satisfied
either by a visibility timeout provably bounded to the platform's maximum
processing duration for a document at its configured size and chunk limits,
or by the coordinated heartbeat extension described above. Silent duplicate
execution caused by an expired visibility window racing a
still-legitimately-processing worker is not acceptable as steady-state
behaviour. The exact durations, renewal interval, and implementation
mechanism are Stage 15.2 decisions; their coordination, the safety margins,
and the guarantee that no two workers can simultaneously believe themselves
authorised to complete the same attempt are not.

### DLQ terminal reconciliation

Arrival in the dead-letter queue does not, by itself, change any PostgreSQL
state — a DLQ is a transport-layer fact, not a domain fact, and this ADR does
not conflate them, consistent with ADR 0008's own separation between
publisher-side retry and consumer-side redelivery. The invariant this ADR
does commit to: **every processing attempt whose message is exhausted into
the DLQ must eventually be reconciled into an authoritative Laravel outcome
— no Document may remain indefinitely `PROCESSING` solely because queue
redelivery ended.**

The reconciliation mechanism — a dedicated DLQ consumer, a scheduled sweep,
or an alarm-driven operational command — is a Stage 15.2 decision. Whatever
is chosen must be: idempotent; keyed by `event_id`; validated against the
correct workspace and Document before acting; recorded with a controlled
terminal classification distinct from an ordinary permanent failure (for
example `delivery_exhausted`), so a stuck-and-reconciled Document remains
diagnosable as such; and itself observable and alertable if reconciliation
fails, so a broken reconciliation path does not silently reproduce the exact
stuck-Document problem it exists to prevent.

### Initial provisioning

The two generation types are provisioned differently because they are
different in kind:

- The **platform embedding-space generation** is provisioned explicitly and
  idempotently during setup or deployment — a deliberate, rare, platform-
  level operation tied to ADR 0013's V1 profile decision, not a side effect
  of whichever document happens to upload first. If claim resolution finds
  no suitable `AVAILABLE` embedding-space generation, ingestion fails
  closed with a controlled operational error; it does not implicitly create
  one.
- A **workspace's first corpus generation** is created lazily, under
  Laravel's authority, during that workspace's first valid indexing
  workflow — resolved at claim time as described above. It becomes `ACTIVE`
  only after successful persistence and verification, per ADR 0014's
  existing lifecycle and completeness rules, which this ADR does not
  redefine.

### Observability

This hop is already covered by ADR 0012's existing invariant that trace
context propagates across *"every hop of one logical request: Laravel HTTP,
the queue boundary..., the Python service, outbound HTTP"* — chunk
submission, completion and failure calls are outbound HTTP calls from
Python, and nothing new needs deciding about propagation itself. The
existing contract-level `correlation_id` threads through claim, submission,
and completion/failure exactly as it already threads through the inbound
direction. This ADR adds a small set of `rag.*` attributes specific to this
boundary — completion status, chunk count, processing duration as a metric,
never chunk text or vectors, consistent with ADR 0012's allowlist-first
posture and ADR 0013's existing embedding-telemetry privacy rules — and
routes `INDEXED`/`FAILED` transitions into the business-audit layer ADR 0006
already established, which already names document administration as an
audited action category, rather than treating them as telemetry-only.

### Communication mechanism: authenticated synchronous HTTP, not exactly-once

Authenticated HTTP, extending ADR 0009's protocol as described above, is the
V1 mechanism for all four purposes. It is chosen over three alternatives
considered and rejected below. This ADR is explicit about what the resulting
guarantee actually is, because it is not exactly-once delivery and should
never be described as such: the guarantee is **at-least-once queue
delivery, deterministic and idempotent processing at every pipeline stage,
purpose-authenticated idempotent callbacks, authoritative all-or-nothing
Laravel transactions, and acknowledgement only after durable final
acceptance** — composed together. No single one of those properties would be
sufficient alone; together they make duplicate delivery, a crashed worker,
and a retried call all safe without requiring exactly-once semantics
anywhere in the design.

## Alternatives considered

### A second, response-direction SQS queue

Rejected. This recreates the exact dual-write hazard ADR 0008 solved for
Laravel's outbound direction, except Python has no transactional relational
store to pair a queue-publish with the way Laravel pairs its outbox insert
with a Postgres transaction — a response queue would be a *weaker*
durability guarantee wearing the same shape. It also requires an entirely
new queue, its own DLQ and redrive policy, and a new consumer role for
Laravel it does not have today, doubling the authenticated-worker pattern
across both services instead of resolving it once.

### EventBridge or a general event bus

Rejected for V1, not permanently. Genuinely useful if multiple independent
downstream consumers of "document indexed" exist someday, but that need is
not demonstrated today — building for it now is the same premature-
infrastructure pattern ADR 0010 and ADR 0013 already argue against
elsewhere in this pipeline. Nothing in the HTTP design forecloses adding one
later; a future `document.indexed` domain event, once other consumers
exist, is additive.

### A literal reverse outbox on the Python side

Not actually available: Python has no relational store of its own to pair a
durable outbox record with, the way Laravel's Postgres transaction pairs
its lifecycle transition with its outbox insert (ADR 0008). The durability
property an outbox would provide is instead achieved by not acknowledging
the inbound SQS message until Laravel durably confirms the final outcome —
reusing ADR 0008's already-accepted at-least-once-plus-idempotent-consumer
pattern for the return trip rather than inventing a second, weaker
apparatus for it.

### One unbounded completion payload carrying full chunk text

Rejected. Mixes two different concerns — bounded, retryable content
transfer and small, referential completion evidence — into one unbounded
request, with no natural batching story for a large document and a much
larger blast radius for any single failed call. Splitting them, as this ADR
does, keeps the completion payload small and cheap to verify regardless of
document size.

### "Only the original claimant may ever proceed"

Rejected as too rigid: a worker can crash after claiming, and a rule with no
reclaim path permanently strands the Document in `PROCESSING`. The
processing lease exists specifically to replace this with a model that
tolerates a dead claimant without permitting two live claimants to duplicate
expensive provider work concurrently.

### Independent, uncoordinated lease and visibility timeouts

An earlier draft of this document specified the processing lease and SQS
visibility as two separately-satisfiable requirements — "the lease has an
expiry" and "visibility is extended, somehow" — without requiring they be
governed by one policy. Rejected on review: two independently-tuned timing
values can drift apart in exactly the way that reopens duplicate processing
(visibility expires while a lease is still valid and work is genuinely
progressing) or premature reclaim (a lease expires well before redelivery
would occur, inviting a second worker to take over work that was never
actually abandoned). "Lease, visibility and acknowledgement coordination"
above replaces this with one coordinated timing and ownership model instead
of two values that happen to coexist.

### Open-ended, permanently-available `v1` signatures

An earlier draft left `v1` acceptance for the claim endpoint open-ended —
"remaining valid... if Stage 15.2 judges one necessary" — with no defined
end. Rejected on review: an authentication protocol this platform has
already deliberately narrowed once (ADR 0009's own instruction to supersede
rather than improvise) should not be reopened into a second, informally
permanent scope by omission. The bounded migration policy in "`v1`-to-`v2`
migration policy" above replaces this with an explicit, claim-only,
time-boxed accommodation that ends in `v1`'s complete removal, tracked as
real Stage 15.2 work rather than left to happen eventually.

### Relying on request-path binding alone for purpose separation

Considered, since ADR 0009's existing string-to-sign already binds the exact
path and would, on its own, prevent a signature computed for one endpoint
from verifying against another. Rejected as the sole mechanism: path
binding is incidental to routing, not a declared architectural fact, and a
future route rename or restructuring could quietly weaken a protection that
was never actually decided as a protection. An explicit, signed purpose
field is a second, independent, auditable check — consistent with this
platform's general preference for defence in depth over relying on any
single mechanism holding by convention.

### Laravel independently re-verifying Qdrant state

Rejected. This would duplicate `VectorStore`'s completeness-verification
role and reintroduce a Qdrant dependency into Laravel, directly against the
service boundary ADR 0014 just finished establishing. Laravel's structural
validation of Python's authenticated, falsifiable claim — plus its own
database constraints as a defence-in-depth layer — is the accepted trust
model instead; see "Trust boundary" above.

### DLQ arrival directly mutating Document or generation state

Rejected. A transport-layer fact (redelivery exhausted) is not the same
kind of fact as a domain outcome, and conflating them would let queue
infrastructure make a decision that belongs to an authoritative,
`event_id`-keyed, workspace/document-validated reconciliation step instead.
See "DLQ terminal reconciliation" above.

## Consequences

### Positive

- The seam ADR 0008 and ADR 0009 deliberately left open is now closed with
  an explicit contract, not an improvised extension of a protocol that
  warned against exactly that.
- A crashed worker no longer permanently strands a Document — the
  processing lease provides a bounded, safe reclaim path.
- Concurrent duplicate delivery no longer costs duplicate expensive provider
  work in the common case, only in the case where a lease has genuinely
  expired.
- The completion contract's independent recomputation of chunk count and
  digest means a corrupted or dishonest chunk summary is caught
  structurally, not merely trusted.
- Purpose-scoped signing closes a class of cross-endpoint replay risk before
  a second worker endpoint ever existed to make it concrete.
- No new infrastructure component is introduced — the design reuses SQS
  redelivery, HMAC authentication, and Postgres transactions already
  accepted elsewhere in this platform.
- The trust boundary is stated honestly rather than implied: Laravel's
  authority over Qdrant facts is explicitly bounded, the same intellectual
  honesty ADR 0013 already applies to hosted-provider reproducibility.
- A successor worker resuming a reclaimed attempt never retransmits or
  duplicates chunks a predecessor already durably persisted, because
  ownership (`event_id`) and authority (the lease) are kept separate rather
  than conflated.
- A control-plane or reporting problem — an unreachable callback endpoint, a
  lease or visibility renewal failure, uncertainty over whether a call
  landed — can never masquerade as a Document processing failure, because
  `ingestion.fail` is reserved for failures Python's own processing domain
  actually classifies as terminal.

### Negative

- The processing lease is new, real implementation surface — expiry,
  renewal, and reclaim logic that did not exist before this ADR — layered on
  top of the claim mechanism ADR 0009 already shipped.
- Splitting chunk submission from completion means two request shapes and
  two round trips to design, test and version, instead of one.
- Coordinating lease renewal with SQS visibility extension as one timing
  budget, rather than tuning each independently, is a real operational
  design problem this ADR requires be solved, not merely a suggestion —
  Stage 15.2 carries that obligation, not just a number to pick.
- DLQ reconciliation is a new, mandatory operational surface — something
  must run, be monitored, and be alertable, not merely exist as an idle
  queue.
- Purpose-scoped `v2` signing means both services must implement and keep
  synchronised a slightly larger canonicalisation surface than `v1`
  required.
- The bounded `v1`-to-`v2` migration window is temporary real work, not a
  one-time decision: it must be actively tracked, and its removal must be
  tested, rather than left as a protocol quietly accepted forever because
  removing it was never scheduled.
- Laravel's inability to independently verify Qdrant state remains a real,
  stated limit of this design, not a solved problem — it is accepted
  deliberately rather than papered over, but it is still a limit.
- Distinguishing a chunk's durable ownership (`event_id`) from a worker's
  current submission authority (the lease) is additional conceptual and
  implementation nuance beyond a simpler lease-scoped model — necessary
  because it is what makes safe resumption after reclaim possible at all,
  but real surface nonetheless.
- Narrowing which retry-exhaustion cases may become `ingestion.fail` means a
  worker must carry more precise failure-domain classification than a single
  transient/permanent split would require. Getting this boundary wrong in
  either direction has a real cost: too broad risks failing an attempt whose
  work actually succeeded; too narrow risks leaving a genuinely-failed
  attempt stuck pending DLQ reconciliation instead of failing promptly.
- Partial success within the coordinated renewal cycle — a lease renewed
  without a matching visibility extension, or the reverse — is accepted and
  designed for rather than assumed away, but recovery in that case is
  necessarily slower than the ordinary fully-successful renewal path, since
  it waits on the lease's natural expiry rather than an immediate handoff.

## Architectural invariants

- Service ownership is unchanged: Laravel owns Postgres, Documents,
  lifecycle, canonical chunks and generation lifecycles; Python owns
  extraction, normalisation, chunking, embedding, `VectorStore` and Qdrant.
- The Document lifecycle remains exactly `UPLOADING → UPLOADED → QUEUED →
  PROCESSING → INDEXED`, with `FAILED` from `PROCESSING`; no new public
  lifecycle state is introduced.
- `event_id` remains the durable, at-least-once processing-attempt identity;
  a processing lease, layered on top, governs which currently-live worker
  may act on it right now.
- Every authenticated worker request is signed for one explicit,
  Laravel-verified purpose; a signature valid for one purpose is never
  accepted for another.
- A request presenting a stale or expired lease is rejected for every
  purpose except a fresh or reclaiming claim.
- At most one worker may hold a currently-valid lease for a given
  `event_id` at any time; a worker may perform expensive processing or make
  an authoritative callback only while it holds that lease, and must stop
  making authoritative callbacks the moment it cannot renew it.
- Lease renewal and SQS visibility extension are governed by one
  coordinated timing policy, with explicit safety margin for latency and
  clock skew, never tuned as two independent values — but they are two
  independently-owned operations, not one atomic distributed transaction;
  either can succeed while the other fails, and a worker that cannot confirm
  both are healthy must stop making authoritative callbacks regardless of
  which one failed.
- A live Laravel lease alone, without confirmed SQS visibility, still
  prevents a second worker from reclaiming; confirmed SQS visibility alone,
  without a live Laravel lease, is never sufficient authority to submit,
  complete, or fail an attempt.
- A lease is reclaimed only once Laravel itself, server-side, determines the
  prior lease has expired — never because a worker locally computed that
  enough time had passed.
- `v1` signatures authorise only `ingestion.claim`, only temporarily, only
  during a bounded migration window; they never authorise chunk submission,
  completion, or failure reporting; permanent dual-protocol support is not
  the target architecture, and removing `v1` is tracked, tested Stage 15.2
  work, not an open-ended possibility.
- Chunk submission never carries vectors; the completion contract never
  carries chunk text or vectors.
- Persisted chunks are provisional and attempt-scoped until completion
  promotes them into workspace-corpus-generation membership; their
  existence alone never implies searchability or success.
- Chunk ownership and submission authority are distinct: a chunk belongs
  durably to the `event_id` it was recorded against, for the life of that
  attempt, regardless of which lease submitted it; only the currently valid
  lease authorises submitting or mutating chunks right now. A reclaim
  changes who may act next; it never orphans what a predecessor already
  durably persisted.
- Laravel independently recomputes and checks the chunk count and
  chunk-manifest digest against what it has actually persisted; it never
  trusts Python's chunk summary blindly.
- Laravel records Python's Qdrant-side verification evidence as an
  authenticated assertion; it never claims to have independently inspected
  Qdrant.
- Only Laravel performs `INDEXED` or `FAILED` transitions; Python requests
  them.
- The worker acknowledges its SQS message only after Laravel durably
  accepts a final outcome, success or failure; transient failures remain
  unacknowledged for bounded redelivery.
- Every Document exhausted into the DLQ is eventually reconciled to an
  authoritative Laravel outcome; none remains indefinitely `PROCESSING`
  solely because redelivery ended.
- The platform embedding-space generation is provisioned explicitly and
  idempotently, never as a side effect of the first upload; a workspace's
  first corpus generation is provisioned lazily, under Laravel's authority,
  and activated only after verification.
- This design does not claim exactly-once delivery anywhere; its safety
  comes from the composition of at-least-once delivery, determinism,
  idempotency, authenticated purpose-scoped callbacks, and atomic Laravel
  transactions.

## Scope boundaries

This document does not define:

- whether the lease extends the existing durable event/claim record (ADR
  0009, Stage 9.3) or uses a dedicated relational entity — either satisfies
  this ADR, provided the five claim outcomes and coordinated-timing
  invariants above hold;
- the lease token's physical representation;
- exact route paths, header names, or the exact duration of the bounded
  `v1`-to-`v2` migration window — that the window is bounded, claim-only,
  and ends in `v1`'s complete removal is fixed above, not deferred;
- the exact bounded batch size and body-size limits for chunk submission;
- the exact chunk-manifest and point-identity digest canonicalisation
  algorithm and its repository location — provided the specification is
  language-neutral, versioned, and exercised by PHP/Python conformance
  tests, as required above;
- the exact DLQ-reconciliation mechanism (consumer, scheduled sweep, or
  alarm-driven command);
- the exact lease duration, renewal interval, and visibility-extension
  duration — that they form one coordinated timing budget with explicit
  safety margin, rather than independent values, is fixed above, not
  deferred;
- the exact `rag.*` attribute and metric names for this boundary;
- retrieval, evaluation, reranking, or generation — all remain downstream of
  `INDEXED` and unaffected by this ADR;
- document versioning — this ADR keys everything off Document identity plus
  `event_id`/lease, which does not foreclose a future versioning ADR, but
  does not design one.

These remain open for Stage 15.2 to decide with the context this ADR
establishes.
