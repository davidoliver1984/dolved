# ADR 0016: Define Ingestion Publication and Recovery Semantics

## Status

Accepted

## Date

2026-08-06

## Relationship to prior ADRs

### Supersession of ADR-0015, in part

ADR 0015 is accepted, committed and tagged at `R15-S01`, and is not rewritten
by this document. A post-acceptance implementation review, before any Stage
15.2/15.3 code existed, found three genuine gaps that this ADR resolves by
partial supersession rather than by reopening ADR-0015 wholesale:

- **Provisional-vector visibility.** ADR-0015 allowed Python to write vectors
  directly into a workspace's active corpus generation before Laravel
  accepted final completion, with nothing distinguishing those points from
  already-published ones. This ADR replaces that with an explicit
  provisional-to-published lifecycle and a dual retrieval-visibility gate.
- **Cross-worker chunk recovery.** ADR-0015 promised that a successor worker
  could resume an abandoned attempt using a predecessor's already-submitted
  chunks, without defining how the successor would obtain them. As
  "Context" below details, that promise is not achievable by recomputation
  given how ADR-0010 and ADR-0011 derive identity — only by an explicit
  hand-off. This ADR replaces the promise with a concrete sealed-attempt
  model and a bounded, authenticated resume contract.
- **The complete purpose-scoped worker protocol.** ADR-0015 defined four
  `v2` purposes (`ingestion.claim`, `ingestion.chunks.submit`,
  `ingestion.complete`, `ingestion.fail`) but its own "Lease, visibility and
  acknowledgement coordination" section discusses lease renewal as an
  authenticated operation without ever adding it to that list — an internal
  gap, not a new requirement. This ADR completes the purpose list.
- **Any wording implying the distributed workflow is observationally atomic
  as one unit.** ADR-0015 was already careful about this for lease/visibility
  renewal (see its own "Coordinated renewal, not atomic renewal"); this ADR
  extends the same honesty to the publication saga introduced below, which
  spans PostgreSQL and Qdrant and is a recoverable saga of individually
  transactional steps, never one distributed transaction.

Everything else ADR-0015 decided — service ownership, the unchanged Document
lifecycle, the processing lease's five claim outcomes, the trust boundary,
domain-owned failure classification, the `v1`-to-`v2` migration policy, DLQ
terminal reconciliation as an eventually-consistent invariant, and
differentiated initial-generation provisioning — is unchanged and is not
duplicated here except where a cross-reference is needed for this
document's own decisions to make sense.

### Supersession of ADR-0014, in part

ADR 0014 is accepted and immutable, and this document does not reopen its
collection topology, service ownership, `VectorStore` boundary, or
embedding-space/workspace-corpus generation model — all of that stands
exactly as accepted. What this document narrowly supersedes is one
paragraph: ADR-0014's *"Minimal Qdrant payload"* section, which fixed
exactly five payload fields. Closing the provisional-vector visibility gap
requires two additional fields, `event_id` and a publication-status marker
— see "Qdrant payload amendment" below. This is not a reopening of ADR-0014's
payload-minimalism *principle* — the payload remains deliberately minimal,
chunk text and vectors are still never duplicated into it — it is an
addition required to actually deliver on ADR-0014's own stated invariant
that *"a partial write... does not qualify as `INDEXED`"* (ADR 0007) now
that this ADR makes explicit how that invariant is kept true on the Qdrant
side, not only the PostgreSQL side.

### Continuity with ADR-0009's supersession

ADR-0015 already superseded ADR-0009's claim-only authentication scope with
the purpose-scoped `v2` protocol. This document extends that same protocol
with four further purposes; it does not reach back to ADR-0009 directly, and
ADR-0009 remains superseded only to the extent ADR-0015 already established.

## Context

Phase 14 (ADR 0014) completed the storage foundation. Phase 15, Stage 15.1
(ADR 0015) defined the cross-service orchestration contract connecting
Python's ingestion pipeline to Laravel's authoritative Document lifecycle,
introducing the processing lease, the canonical chunk submission and
completion contracts, and the trust boundary between what Laravel
independently verifies and what it records as an authenticated Python
assertion. Before Stage 15.2 implementation began, a further review of that
accepted contract — not a re-litigation of its architecture, which remains
approved in principle — found three places where the contract, as written,
either creates a real exposure window or promises a capability the
platform's own accepted rules make impossible to deliver as specified.

**Why provisional vectors are a real exposure, not a theoretical one.**
Ordinary incremental ingestion writes points into the workspace's already-
`ACTIVE` corpus generation, tagged with that generation's real id — the same
id every already-published point already carries (ADR 0014). The moment a
Qdrant upsert succeeds, nothing in the accepted design distinguishes that
point from a published one to any query filtering on `workspace_id` and
`workspace_corpus_generation_id`. A crash between a successful Qdrant write
and a successful completion call — or a permanent failure discovered after
the vectors already landed — would leave content retrievable that no
authoritative source ever confirmed complete, which is exactly the class of
exposure ADR-0007 already refuses to tolerate on the PostgreSQL side: *"a
partial write... does not qualify as `INDEXED`; the Document remains
`PROCESSING`... until the complete representation is in place."* That
guarantee was never actually extended to Qdrant.

**Why cross-worker chunk recovery is not achievable by recomputation.** ADR
0010 is explicit that extraction assigns fresh identity every run: *"A new
extraction run creates new element UUIDs. Stable identity across
re-extraction is not required at this stage, and no deterministic
cross-extraction identifier scheme is implied."* ADR 0011's chunk identity
is derived in part from *"its ordered provenance spans (which source
elements, in what order, contributed to it),"* and traces through
`NormalisedElement`, itself *"a `uuid5` derived from its source element's
identity, kind and text."* Two independent extraction runs over
byte-identical source content therefore produce different `NormalisedElement`
ids, and therefore different chunk ids, regardless of chunking's own
determinism — because chunking's determinism guarantee is explicitly scoped
to *"an identical `NormalisedDocument`"* already held as a fixed value, not
to "the same document, re-extracted from scratch." A successor worker that
has to re-run extraction cannot reproduce a predecessor's chunk ids by
computation; it can only obtain them by being handed them. ADR 0011 itself
anticipated this would be someone else's problem to solve: *"whether a
produced `ChunkingResult`... [is] persisted afterward is a legitimate and
expected concern... [belonging] to later orchestration, vector-storage and
operations phases."* This document is that later phase.

**Why lease renewal needs its own purpose.** This is the narrowest of the
three: an internal gap in ADR-0015's own text, corrected in "Lease renewal"
below.

## What this ADR decides and does not decide

This ADR defines: an explicit provisional-to-published vector lifecycle and
the dual gate that makes retrieval safe during the crash window; independent
post-publication verification and evidence-bound publication authorisation,
so a partially-succeeded or uncertain publication mutation can never be
mistaken for complete; the narrow Qdrant payload amendment that lifecycle
requires; an open-versus-sealed model for chunk attempts and the resume
contract that makes sealed-attempt recovery actually deliverable; the
complete `v2` worker-protocol purpose list, including lease renewal and
resume; a unified cleanup and reconciliation invariant spanning reclaim and
DLQ exhaustion; and the recovery outcome for every crash window this saga
introduces. It does not
design Phase 16 retrieval's actual query or hydration implementation, the
exact Qdrant mutation mechanism or payload representation, the exact
cleanup/reconciliation worker mechanism, or the per-failure-category
terminal/retry classification table — each remains Stage 15.3
implementation work, constrained by the invariants fixed here.

## Decision

### Service ownership and the Document lifecycle are unchanged

Nothing here moves ownership: Laravel remains authoritative for PostgreSQL,
Document identity and lifecycle, canonical chunks and both generation
lifecycles; Python remains authoritative for extraction, normalisation,
chunking, embedding, `VectorStore` and Qdrant (ADR 0002, 0007, 0010, 0011,
0013, 0014, 0015). The Document lifecycle remains exactly
`UPLOADING → UPLOADED → QUEUED → PROCESSING → INDEXED`, with `FAILED` from
`PROCESSING`; no new public lifecycle state is introduced. Publication state
belongs on the **processing-attempt record** — the same place ADR-0015
already put the lease and its outcomes — not on the Document. From the
Document's perspective, `PROCESSING → INDEXED` remains a single atomic
transition; publication is internal machinery that has to happen before
that transition is honestly earned, not a new thing a Document's public
state exposes.

### The publication saga

An explicit provisional-to-published vector lifecycle, described honestly as
an **idempotent, recoverable saga** whose individual PostgreSQL transitions
are transactional — never as one distributed transaction spanning
PostgreSQL and Qdrant, which is not achievable and is not claimed:

```text
 1. Python extracts, normalises and chunks.
 2. Python submits bounded provisional canonical-chunk batches to Laravel.
 3. Python seals the complete event_id-scoped chunk set.
 4. Laravel validates the seal and marks the chunk set immutable.
 5. Python embeds the sealed authoritative chunks.
 6. Python writes Qdrant points as provisional for the current event_id.
 7. Python verifies the complete provisional point set.
 8. Laravel durably authorises publication, bound to the exact evidence
    it approved (see "Publication authorisation is bound to immutable
    evidence" below).
 9. Python idempotently changes the complete event_id-scoped point set
    from provisional to published.
10. Python verifies the complete published-point set against the
    authorised manifest (see "Post-publication verification" below).
11. Python reports publication completion.
12. Laravel validates the completion evidence against its durable
    authorisation and transitions the Document from PROCESSING to
    INDEXED.
```

Steps 3–4 and steps 5–12 are separated by design: sealing must happen before
embedding begins, so that a crash anywhere after step 4 lets a successor
skip straight to embedding against an already-verified, immutable chunk set
rather than repeating cheap deterministic work — see "Open versus sealed
chunk attempts" below. Steps 8 and 11 are two separate authenticated
requests, not one: step 9 (actually publishing) can only happen after step
8's authorisation is durably granted, and step 12 can only happen after step
11 confirms publication actually occurred *and verified*, so collapsing
them into a single call would require Python to publish before Laravel had
authorised it, or Laravel to finalise before publication had actually
completed and been checked. Both are unacceptable under the same "no partial
write is ever indexed" discipline this whole document exists to enforce.

**Step 10 exists because step 9 is itself a distributed, partially-failable
operation.** Publishing is a Qdrant mutation across potentially many points;
it can time out or fail after changing only some of them. Verifying the
complete provisional point set before publication (step 7) proves the
*input* to publication was complete — it says nothing about whether the
*publication mutation itself* actually reached every point. Treating step 9
as unconditionally successful merely because it was attempted would let a
Document reach `INDEXED` while some of its expected vectors are still
provisional and therefore invisible under the dual gate — precisely the
partial-write exposure this entire document exists to close, only moved one
step later in the saga rather than eliminated. Step 10 closes that specific
gap: `ingestion.complete` (step 11) is never reachable until step 10 has
independently confirmed the *result* of publication, not merely that it was
requested.

### Post-publication verification

Before Python may call `ingestion.complete`, it must independently verify,
against the authorised point manifest, that:

- every expected deterministic point identity exists;
- every expected point is marked published;
- no expected point remains provisional;
- no unexpected published point exists within the `event_id` scope;
- every point still carries the expected workspace, document, event,
  corpus-generation and embedding-space identities;
- vector name and dimensions match the embedding-space schema;
- the expected count equals the actual published count; and
- the verified point-identity digest matches the digest Laravel authorised.

Count equality alone is insufficient, exactly as ADR-0014 already requires
for its own completeness verification and as step 7's pre-publication check
already requires for the provisional set — this is the same discipline
applied a second time, after the mutation that can itself partially fail,
not a relaxation of it. A verification that finds any expected point still
provisional, missing, or mismatched must not proceed to `ingestion.complete`
— it is retried against the same idempotent publication operation (step 9),
not escalated to a Document-level failure, since nothing about processing
itself has failed; only the mutation has not yet fully landed. The exact
efficient Qdrant inspection mechanism (a single scoped listing, a batched
identity check, or an equivalent) remains Stage 15.3 implementation work;
the semantic meaning of "verified" is fixed here.

### Publication authorisation is bound to immutable evidence

Durable publication authorisation (step 8) is not a generic permission to
publish whatever currently exists for an `event_id`. It is bound to the
exact, immutable evidence Laravel actually approved at the moment it was
granted: the `event_id`; the sealed chunk-manifest digest; the expected
point-manifest digest; the expected point count; the embedding-profile
fingerprint; the embedding-space-generation identity; and the
workspace-corpus-generation identity. A successor worker recovering an
authorised-but-not-yet-published attempt may reuse the durable authorisation
only to complete publication of that identical authorised evidence — never
to publish a different or expanded point set under the same grant. If the
evidence a successor would publish differs in any respect from what was
authorised (for example, because the sealed chunk set was somehow
reprocessed, which sealing itself should already prevent, or because
verification now finds a different expected point set than the one
authorised), publication must be re-authorised against the current evidence
or rejected outright — it must never silently reuse an earlier authorisation
for a conflicting point set. This is the same no-silent-loss, no-silent-
substitution discipline this ADR and ADR-0011 already apply everywhere else
a mismatch could otherwise be quietly papered over.

Laravel's completion validation (step 12) checks the same binding in
reverse: the completion evidence Python reports must match the durable
authorisation it was granted, not merely be internally self-consistent. A
completion request whose evidence disagrees with what was actually
authorised is rejected, exactly as a chunk-manifest mismatch is already
rejected at the seal boundary.

### Retrieval visibility: the dual gate

Retrieval visibility for a chunk's vector requires **both**:

- its Qdrant point is marked **published**; and
- PostgreSQL independently confirms the owning Document is **`INDEXED`**.

Neither gate is sufficient alone, and this document treats relying on only
one as rejected, not merely unnecessary — see "Alternatives considered"
below. A provisional point must never appear in retrieval results under any
circumstance. A point published in Qdrant during the crash window before
step 12 completes must remain invisible to users and retrieval, because the
owning Document is not yet `INDEXED` — the PostgreSQL gate is what actually
closes that window; the Qdrant gate exists so retrieval's own candidate set
stays clean and does not depend on a post-search discard step to remain
correct once Phase 16 is designed. The precise retrieval query and
hydration implementation is deferred to Phase 16; this dual-gate invariant
is fixed now, before any implementation exists to get it wrong.

### Qdrant payload amendment

ADR-0014's minimal payload becomes, at minimum:

- `workspace_id`
- `document_id`
- `chunk_id`
- `workspace_corpus_generation_id`
- `embedding_space_generation_id`
- `event_id` — the attempt-scoping identity. It is what scopes provisional
  writes, publication, cleanup and reconciliation to exactly one processing
  attempt, and is retained after publication for lineage and audit, not
  discarded once a point is published.
- publication status — a controlled value distinguishing a provisional
  point from a published one. This is the field the retrieval-time Qdrant
  gate filters on.

Exact field names and value representation (a boolean, an enum, a
timestamp-presence convention) remain Stage 15.3 implementation decisions.
What is fixed is that both facts must be present on every point, and that
neither is optional.

### Payload indexes

Extending ADR-0014's own discipline — index a field because its actual
query, update or delete pattern requires it, never by habit:

- **`event_id` requires an index.** It scopes three real, recurring
  operations: Python's own pre-publication completeness verification
  (step 7), the publication update itself (step 9, which must touch every
  point for one attempt), and cleanup/reconciliation (which must find and
  remove or retire every provisional point for one abandoned attempt
  without scanning the collection). All three are exactly the filter/update
  patterns ADR-0014 already treats as index-justifying.
- **Publication status requires an index.** Every retrieval query, once
  Phase 16 exists, filters on it — this is the highest-frequency operation
  touching this field of any kind, and the entire reason the dual-gate
  design keeps Qdrant's candidate set clean depends on that filter being
  cheap. The publication update itself (step 9) does not strictly need a
  status-filtered lookup, since it is already scoped by `event_id`, but
  retrieval's dependence on this field is reason enough on its own.

No other new index is introduced. `chunk_id` and `embedding_space_generation_id`
remain exactly as ADR-0014 already scoped them — point-identity derivation
and defensive validation, not filtering — and gain no new justification from
anything decided here.

### Open versus sealed chunk attempts

**Open (incomplete) attempt.** Chunk batches submitted so far are
provisional and `event_id`-scoped. The set is mutable only through
authorised, idempotent submission (ADR-0015's existing acceptance rules,
unchanged) and is **not a valid resume source** — embedding must not begin
before the set is sealed. If the valid lease is lost before sealing, reclaim
performs an idempotent reset, scoped strictly to that `event_id`, of:
provisional chunks; provisional vectors, if any exist defensively (embedding
should not have started, but a reset must not assume that invariant held);
and attempt-local manifest/seal state. The successor restarts extraction,
normalisation and chunking from the beginning — there is nothing safe to
resume from an unsealed set, given "Context" above. A reset must never
affect another `event_id`.

**Sealed attempt.** Once Python has submitted every chunk, it submits a seal
request carrying the complete manifest summary. Laravel verifies:
deterministic chunk identities; a complete, gapless ordinal sequence; the
expected count; the canonical chunk-manifest digest; provenance and
lineage; and the chunking strategy/configuration fingerprint. If this
passes, Laravel marks the `event_id`-scoped chunk set **sealed and
immutable** — no later chunk mutation is permitted, conflicting or
otherwise. A successor worker holding a valid (reclaimed) lease may then
retrieve the sealed authoritative chunks and manifest through the resume
contract below, and continue directly from embedding without re-running
extraction or chunking.

A successor **may** optionally avoid re-embedding a chunk whose valid
provisional point already exists for this `event_id` — safe, because
deterministic point identity makes a redundant embed-and-upsert an
idempotent overwrite either way — but this is an efficiency optimisation
only. Correctness depends on final completeness verification (step 7, and
Laravel's independent recomputation at step 8), never on a successor's
partial-work discovery being complete or even attempted.

### The resume/read contract: `ingestion.attempt.resume`

The sealed-attempt model requires chunk content to flow from Laravel to
Python for the first time — every other contract in this pipeline flows the
opposite direction. This needs its own authenticated, purpose-scoped
operation; reusing an existing purpose would let a signature intended for
one operation authorise reading another attempt's content, exactly the
cross-purpose risk ADR-0015's purpose-scoping already exists to prevent.

Named `ingestion.attempt.resume` rather than `ingestion.chunks.read`. Both
were considered; `attempt.resume` is chosen because it groups this operation
conceptually with attempt-lifecycle management (`ingestion.claim`,
`ingestion.lease.renew`) rather than with content-submission operations
(`ingestion.chunks.submit`, `ingestion.chunks.seal`) — which is a more
accurate description of what it actually does. This is not a general chunk
query capability; it is a narrow, lease-gated recovery operation whose only
legitimate caller is a successor worker resuming a specific attempt it has
just reclaimed. Naming it `resume` rather than `read` signals that
constraint at the boundary itself, rather than leaving a generically-named
operation to be discovered and reused for something it was never designed
to authorise.

The contract requires: `workspace_id`, `document_id`, `event_id`, the
current valid lease token, an explicit contract version, and
timestamp/freshness validation — signed with purpose-bound `v2` HMAC
authentication exactly as every other operation. Laravel rejects a stale,
expired, or superseded lease exactly as for every other purpose. The
response is bounded — paginated or otherwise size-limited, never an
unbounded dump — and scoped to exactly the sealed canonical chunk set and
manifest for that `event_id`: no cross-workspace, cross-document, or
cross-attempt read is possible, by construction, because every other
attempt's chunks live under a different `event_id` the presented lease does
not authorise. Logs and telemetry for this operation follow the same
privacy-safe posture as every other purpose — chunk content is never logged,
only identifiers and outcomes.

### The complete `v2` purpose list

Eight purposes, all under the `v2` protocol ADR-0015 already established (no
new signature-format version — the existing six-field string-to-sign already
accommodates any purpose value):

| Purpose | Introduced by | Role |
|---|---|---|
| `ingestion.claim` | ADR 0009 (superseded), ADR 0015 | Claim or reclaim a processing attempt |
| `ingestion.lease.renew` | ADR 0016 | Renew the current processing lease |
| `ingestion.chunks.submit` | ADR 0015 | Submit a bounded, provisional chunk batch |
| `ingestion.chunks.seal` | ADR 0016 | Finalise and lock the complete chunk set |
| `ingestion.attempt.resume` | ADR 0016 | A successor reads a predecessor's sealed chunks |
| `ingestion.publication.authorise` | ADR 0016 | Request authorisation to publish verified vectors, bound to the exact evidence approved |
| `ingestion.complete` | ADR 0015 (refined) | Report publication verified complete; triggers `INDEXED` |
| `ingestion.fail` | ADR 0015 | Request a permanent `FAILED` transition |

`ingestion.complete`'s meaning is refined, not renamed: under ADR-0015 it
meant "the whole attempt is done." Under this ADR it retains exactly that
meaning, but is now reachable only after publication has been performed
*and independently verified complete* (step 10), not merely attempted —
the intervening authorisation handshake (`ingestion.publication.authorise`)
and the post-publication verification step together close the exposure
window "Context" describes, including the narrower window a partially-failed
publication mutation would otherwise leave open. Laravel's acceptance of
`ingestion.complete` additionally checks the reported evidence against the
durable authorisation it granted (see "Publication authorisation is bound to
immutable evidence" above) — a completion report is never accepted purely
on its own say-so. A signature valid for any one purpose must fail
verification against every other endpoint, exactly as ADR-0015 already
requires; this document adds four purposes to that same rule, it does not
relax it.

The following non-secret protocol test vector is normative for
`ingestion.lease.renew`, computed and verified using the same method as
ADR-0015's own vectors, confirming the six-field canonicalisation extends to
a new purpose value without modification:

```text
secret (Base64):
MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=

timestamp:
1785326400

method:
POST

request path:
/api/internal/ingestion/events/5a1e9c3e-3b3a-4e2a-9c7d-1f6b6f0a2b41/lease/renew

exact body:
{"contract_version":1,"lease_token":"c9a7b8d0-2e1f-4a3b-9c8d-7e6f5a4b3c2d"}

body SHA-256:
ca67cddd1d5c5e6547b3154d0c02320b4bffdeccb0b3c3b5b43713607cd7b8cd

event ID:
5a1e9c3e-3b3a-4e2a-9c7d-1f6b6f0a2b41

purpose:
ingestion.lease.renew

expected signature:
v2=8c182114de5b9615eaae2d6ddcc5b358432ad8b6e56c7fb378330613411b9afb
```

### Lease renewal

`ingestion.lease.renew` requires exactly the same discipline as every other
`v2` purpose: contract version, `workspace_id`, `document_id`, `event_id`,
the current lease token, timestamp/freshness validation, purpose-bound
signing, rejection of a stale, expired, or superseded lease, idempotent
handling where semantically appropriate (a retried renewal within the
freshness window is a no-op success, not an error), and privacy-safe
telemetry.

Laravel's renewal decision must not depend on any claim about SQS visibility
state. Laravel does not hold the SQS receipt handle and has no way to
independently verify a worker's assertion about it — requiring or trusting
such a claim would manufacture confidence in something Laravel fundamentally
cannot see, the same honesty ADR-0015 already applies to Qdrant evidence.
Python may emit non-authoritative telemetry noting its own
visibility-extension outcome alongside a renewal call, purely for
diagnostics; Laravel's renewal logic never reads it. Lease validity and SQS
visibility remain coordinated through timing — the shared heartbeat cadence
ADR-0015 already establishes — never through a data dependency or an
implied cross-system transaction.

### Unified cleanup and reconciliation

Reclaim's reset (open-attempt case, above) and ADR-0015's DLQ terminal
reconciliation are two different *triggers* for the same underlying
obligation, and must be implemented as one coherent policy rather than two
independently-built mechanisms that could disagree. That policy must be:

- idempotent;
- restricted to exactly one workspace, document and `event_id` per
  invocation;
- safe under duplicate invocation;
- structurally incapable of touching another processing attempt's records;
- able to remove or retire: open provisional chunks; provisional Qdrant
  points; incomplete manifest/seal state; and stale publication
  authorisations where applicable;
- auditable and observable, so a reconciliation failure is itself visible
  rather than a second, quieter version of the stuck-Document problem it
  exists to prevent;
- **prohibited from deleting sealed or published data** unless the
  authoritative attempt state actually permits it — a sealed chunk set or a
  published point set represents real, verified work, and cleanup exists to
  remove abandoned *provisional* state, not to casually discard verified
  results because a mechanism swept past them.

The exact worker, command, scheduler, or DLQ-consumer mechanism remains
Stage 15.3 implementation work, as ADR-0015 already deferred for DLQ
reconciliation specifically; this ADR extends that same deferral to cover
the reclaim-triggered case under one policy.

### Publication recovery cases

- **Partial provisional-vector write.** Remains invisible under the dual
  gate regardless of how partial. A successor may safely re-upsert the
  remaining points deterministically, or the cleanup policy may reset the
  attempt entirely; either is safe, and publication cannot be authorised
  until pre-publication completeness verification (step 7 and Laravel's
  step-8 recomputation) actually succeeds.
- **Laravel authorises publication, Python crashes before publishing.** The
  authorisation is durable and evidence-bound — it does not expire merely
  because the worker that requested it died. A successor holding a valid,
  reclaimed lease can idempotently perform publication (step 9) against the
  same authorisation without re-requesting it, provided the underlying
  evidence the successor would publish is still identical to what was
  authorised; if it is not, publication is re-authorised or rejected, never
  silently reused against different evidence.
- **The publication update itself is partial or its result is uncertain**
  (for example: of 100 expected points, 63 are confirmed changed to
  published, then the mutation fails or times out, leaving 37 provisional;
  or the mutation completes but its result cannot be confirmed from the
  response alone). This is the case post-publication verification (step 10)
  exists for. A partial or uncertain publication result **does not permit**
  `ingestion.complete` — Python's own step-10 verification must fail it,
  and even if a worker's own bookkeeping were somehow wrong, Laravel's
  step-12 evidence check provides a second, independent barrier. The
  Document remains `PROCESSING`. Already-published points among the 63
  remain invisible to retrieval regardless, because the PostgreSQL
  `INDEXED` gate has not passed. Repeating publication (step 9) is
  idempotent — the 63 already-published points are unaffected, and only
  the remaining 37 are changed — so recovery is simply retrying publication
  against the same evidence-bound authorisation until step 10 confirms the
  complete set, whether by the same worker or, after a reclaim, by a
  successor.
- **Qdrant publication is confirmed complete (step 10 passes), but the
  final report (step 11) fails to reach Laravel.** Points are fully
  published, but the Document remains `PROCESSING` and therefore not
  retrievable under the PostgreSQL gate. Retrying the completion report is
  safe and idempotent; and reconciliation, if redelivery is itself
  exhausted, can inspect the authoritative attempt state (publication
  already verified complete) and finish finalisation without redoing any
  Qdrant work.
- **Laravel commits `INDEXED`, the worker never receives the response.** A
  repeated completion request returns the already-accepted terminal result
  idempotently; the worker acknowledges its SQS message and stops; no second
  transition occurs, and no second business-audit entry is created for the
  same outcome.
- **Duplicate publication or completion requests.** Identical retries
  (same `event_id`, same evidence) are accepted idempotently with no
  duplicated effect. Conflicting requests (same `event_id`, disagreeing
  evidence — including a completion report whose evidence does not match
  the durable authorisation) fail closed as a typed error, never silently
  resolved by picking one — the same no-silent-loss discipline ADR-0010 and
  ADR-0011 already apply to their own boundaries.

### Document deletion during publication

Not solved here. ADR-0006 and ADR-0007 already treat Document and workspace
deletion as a deferred, multi-system, asynchronous orchestration concern;
this document does not design that saga. What this document requires:
publication authorisation (step 8) and completion (steps 11–12) must
revalidate the Document's authoritative state before acting, and must never
transition a Document already in a deleting, deleted, or otherwise
ineligible state to `INDEXED` — a race between deletion and a
publication/completion request resolves in favour of deletion, not
finalisation. The full deletion-and-ingestion interaction is left to the
orchestration ADR-0006 and ADR-0007 already anticipate.

### Publication audit

Publication authorisation and publication completion are recorded in the
business-audit layer ADR-0006 already established, not as telemetry alone —
extending the same treatment ADR-0015 already gives `INDEXED`/`FAILED`
transitions to the new intermediate event that actually makes content
theoretically reachable under one of the two required gates. Safe audit
facts: `workspace_id`, `document_id`, `event_id`, the corpus and
embedding-space generation identities involved, the publication status
reached, an actor/principal classification (the ingestion-worker identity,
not a user), a timestamp, and a controlled outcome code. Never recorded:
chunk text, vectors, signatures, or credentials — the same allowlist-first
posture ADR-0012 already establishes for telemetry generally, applied here
to the audit layer as well.

### Failure taxonomy

Unchanged in principle from ADR-0015: only a failure Python's own processing
domain classifies as terminal, while a valid lease is still held, may become
`ingestion.fail`. The detailed per-failure-category terminal/retry
classification table remains Stage 15.3 implementation work, derived from
the typed failure taxonomies extraction (ADR 0010), embedding (ADR 0013),
and Qdrant persistence (ADR 0014) already each independently established.
No additional ADR is required solely to produce that table.

## Roadmap clarification: Phase 15 stage structure

Recorded here as the planning correction this ADR requires, pending separate
application to `PROJECT_ROADMAP.md`, `IMPLEMENTATION_GUIDE.md` and
`tasks.json` after this document is reviewed — this ADR does not itself
modify any of them. Because Stage 15.2 implementation had not begun when
these gaps were found, Phase 15's stage structure is corrected rather than
patched around:

```text
R15-S01 — Define End-to-End Ingestion Orchestration and Worker Result Contracts
  completed; ADR-0015

R15-S02 — Define Ingestion Publication and Recovery Semantics
  architecture-only; ADR-0016 (this document)

R15-S03 — Implement End-to-End Ingestion Orchestration
  implementation of ADR-0015 and ADR-0016 together
```

R15-S01's completed record, commit, and tag are unchanged and are not
touched by this correction. R15-S02 is a newly inserted architecture stage;
the former R15-S02 ("Implement End-to-End Ingestion Orchestration") becomes
R15-S03, referencing both ADR-0015 and ADR-0016 as the settled contract it
implements.

## Alternatives considered

### Relying on PostgreSQL status gating alone at retrieval time, without a Qdrant-side publication marker

Considered seriously: since retrieval must already hydrate results from
PostgreSQL (ADR 0014's minimal-payload design), a per-candidate Document-
status check would technically close the same exposure window at no
additional Qdrant schema cost. Rejected as the sole mechanism, not because
it is unsafe on its own, but because it is a single mechanism where this
platform has repeatedly preferred defence in depth (ADR 0006, and ADR
0014's own refusal to trust dimension-matching alone): a missed check
anywhere in a future retrieval code path becomes a live data exposure with
no second layer to contain it, and an unfiltered Qdrant candidate set would
force retrieval into an over-fetch-then-discard shape once Phase 16 is
actually designed. The dual gate keeps both properties: an authoritative
PostgreSQL check that can never be bypassed by construction, and a clean
Qdrant candidate set that does not depend on it being remembered correctly
every time.

### Treating publication as a new public Document lifecycle state

Considered and rejected, consistent with ADR-0015's own rejection of an
`INDEXING` state. Publication state belongs on the processing-attempt
record, where the lease and its outcomes already live, not on the Document
— there is no benefit to a Document-facing state change here that the
attempt-level record does not already provide, and adding one would
reopen a question ADR-0015 already closed for the same reasons.

### Encoding provisional/published status by minting a new generation id per attempt

Considered as a way to avoid amending ADR-0014's payload schema, using
`workspace_corpus_generation_id` itself as the provisional/published
signal (a temporary, attempt-scoped generation id, later "promoted" by
re-pointing the workspace at it). Rejected: this would mean minting a new,
lifecycle-tracked workspace-corpus-generation entity for every single
document ingested, which directly contradicts ADR-0014's own decision that
*"ordinary ingestion adds that document's verified chunk points to the
workspace's currently active corpus generation"* rather than creating a new
one per document, and reintroduces a version of the generation-proliferation
cost ADR-0014 already rejected for a different reason. A narrow, additive
payload amendment is the smaller, more honest change.

### Combining publication authorisation and completion into a single call

Considered, since it would mean one fewer authenticated round trip.
Rejected: it is not actually achievable given the saga's own ordering —
publication (step 9) must follow authorisation (step 8), so collapsing them
would require Python to publish before being authorised, or Laravel to
authorise and finalise before publication had actually happened, either of
which reopens exactly the exposure window this document exists to close.

### Naming the resume operation `ingestion.chunks.read`

Considered and rejected in favour of `ingestion.attempt.resume` — see "The
resume/read contract" above for the reasoning: this operation is a narrow,
lease-gated recovery primitive, not a general read capability, and its name
should say so at the boundary rather than invite reuse as something more
general than it is authorised to be.

### Treating a successfully-requested publication mutation as sufficient for completion

An earlier version of this document's saga verified the provisional point
set before publication (step 7), then went directly from publishing (step 9)
to reporting completion, with no independent check that the publication
mutation itself had actually reached every point. Rejected on review: a
Qdrant payload update across many points is itself a distributed operation
that can partially succeed or time out, and pre-publication verification
says nothing about whether that specific mutation later completed. Treating
"the publish call was made" as equivalent to "every point is published"
would have moved the partial-write exposure this document exists to close
one step later in the saga rather than actually closing it. Post-publication
verification (step 10) and evidence-bound authorisation together replace
this with an explicit, independently-checked guarantee.

### A generic, reusable publication authorisation

Considered: authorising publication for an `event_id` in general, rather
than for the exact evidence approved. Rejected: this would let a successor
worker — or a delayed, redelivered request — publish a materially different
point set than the one Laravel actually validated, silently, under the
strength of an authorisation granted for something else. Binding
authorisation to immutable evidence keeps "authorised" and "validated"
synonymous, which a generic grant would not.

## Consequences

### Positive

- The exposure window between a successful Qdrant write and a confirmed
  `INDEXED` transition is closed by an explicit, honestly-described
  mechanism rather than left implicit and hoped-safe.
- Cross-worker resumption is now something the platform can actually build,
  not a promise that was architecturally impossible to keep as previously
  written.
- The `v2` purpose list is now internally consistent with ADR-0015's own
  prose, which already assumed lease renewal existed as an authenticated
  operation.
- The dual retrieval gate gives Phase 16 a clean, pre-decided invariant to
  design against, rather than a correctness question discovered mid-design.
- Publication becomes a first-class, audited event, extending rather than
  bolting onto the audit layer ADR-0006 already established.
- No new infrastructure component is introduced — the saga is built entirely
  from mechanisms this platform has already accepted (purpose-scoped HMAC,
  Postgres transactions, Qdrant payload updates, the existing lease model).
- The partial-publication exposure window — a distributed Qdrant mutation
  succeeding for some points and not others, then being mistaken for
  complete — is closed structurally, by independent verification, rather
  than assumed away as an unlikely edge case.
- Evidence-bound authorisation means a successor can never accidentally
  publish or complete against a point set that has silently drifted from
  what Laravel actually approved, even across a reclaim.

### Negative

- The worker protocol grows from four purposes to eight, and the ingestion
  pipeline gains two additional authenticated Laravel operations (seal,
  publication-authorise) beyond what ADR-0015 originally specified, plus the
  additional sequencing and verification work — post-publication
  completeness verification against Qdrant, then evidence-checked
  acceptance at the existing `ingestion.complete` call — that now sits
  between publishing and finalisation. This is real latency and
  implementation surface, accepted because the alternative is an unclosed
  exposure window and an unbuildable resumption promise. Attempts using the
  resume path add a further authenticated operation.
- The sealed-chunk model means a worker cannot begin embedding until sealing
  succeeds, a hard sequencing constraint that did not exist in ADR-0015's
  original, less precisely ordered description.
- Two payload fields and two new payload indexes are added to what was
  meant to be a deliberately minimal Qdrant schema — a real, if narrow,
  widening of ADR-0014's original footprint.
- A unified cleanup/reconciliation policy spanning two different trigger
  paths (reclaim and DLQ exhaustion) is more implementation surface than
  either path independently would have required, accepted so the two
  mechanisms cannot silently diverge.
- The publication-authorise/complete split means Stage 15.3 must design and
  test one more distinct failure mode (authorised-but-not-yet-published)
  than a single completion call would have needed.
- Post-publication verification and evidence-bound authorisation add real
  implementation surface of their own: a worker must retain (or be able to
  re-derive) the exact authorised manifest to compare against, and a
  partial-publication retry loop, however simple in principle, is one more
  case Stage 15.3 must build and test rather than assume away.

## Architectural invariants

- No provisional or partially-written Document ever becomes retrievable.
- Retrieval requires both a published Qdrant point and an authoritative
  PostgreSQL `INDEXED` confirmation; neither gate substitutes for the other.
- Qdrant publication and the PostgreSQL `INDEXED` transition are separate,
  individually transactional, recoverable steps — never claimed as one
  distributed transaction.
- A partially-succeeded or uncertain publication mutation is never treated
  as complete: `ingestion.complete` is reachable only after independent
  post-publication verification confirms every expected point is published,
  none remains provisional, and no unexpected published point exists —
  count equality alone is never sufficient.
- Durable publication authorisation is bound to the exact immutable
  evidence it approved (chunk-manifest digest, point-manifest digest,
  expected count, embedding-profile fingerprint, and the generation
  identities involved); it is never a generic permission to publish
  whatever currently exists for an `event_id`, and it is never reused
  against different or expanded evidence without re-authorisation.
- Laravel's acceptance of a completion report independently checks the
  reported evidence against its own durable authorisation before
  transitioning the Document; a completion report is never accepted purely
  on its own assertion.
- An open (unsealed) chunk attempt is never a valid resume source; embedding
  never begins before sealing succeeds.
- A sealed chunk set is immutable; no later chunk mutation is permitted
  against it, conflicting or otherwise.
- A successor worker resumes only through the authenticated
  `ingestion.attempt.resume` contract, scoped strictly to one `event_id`,
  never by recomputing a predecessor's identities.
- Every `v2` purpose — including the four this document adds — is
  independently signed and independently verified; a signature valid for
  one purpose is never accepted for another.
- Lease renewal never depends on unverifiable claims about SQS visibility
  state; the two remain coordinated by timing only.
- Reclaim reset and DLQ terminal reconciliation share one cleanup policy,
  scoped strictly to one `event_id`, and never delete sealed or published
  data outside authoritative attempt state permitting it.
- Publication authorisation and completion revalidate Document eligibility
  before acting and never finalise a Document already deleting, deleted, or
  otherwise ineligible.
- Publication authorisation and completion are recorded in the business-
  audit layer, not telemetry alone; chunk text, vectors, signatures and
  credentials are never recorded in either.
- No claim of exactly-once delivery or cross-system distributed atomicity
  is made anywhere in this design.

## Scope boundaries

This document does not define:

- Phase 16 retrieval's exact query or hydration implementation, beyond the
  dual-gate invariant it must satisfy;
- the exact Qdrant payload field names, value representation, or mutation
  mechanism used for publication;
- the exact seal, publication-authorisation, resume, and post-publication
  verification request/response schemas;
- the exact efficient mechanism for post-publication verification (a single
  scoped listing, a batched identity check, or an equivalent) and the
  exact representation of the authorised evidence a successor compares
  against — the semantic requirements are fixed above, the mechanism is not;
- the exact reclaim-reset and DLQ-reconciliation worker, command, or
  scheduler implementation;
- the per-failure-category terminal/retry classification table, derived
  from taxonomies ADR-0010, ADR-0013 and ADR-0014 already each establish;
- the full Document/workspace deletion orchestration saga, already deferred
  by ADR-0006 and ADR-0007;
- any change to service ownership, the Document lifecycle, the processing
  lease's five claim outcomes, the `v1`-to-`v2` migration policy, or
  ADR-0014's collection topology, `VectorStore` boundary, or generation
  model — all remain exactly as previously accepted.

These remain open for Stage 15.3 to decide with the context this ADR
establishes.
