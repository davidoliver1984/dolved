# ADR 0008: Use the Transactional Outbox Pattern for Document-Ingestion Publication

## Status

Accepted

## Date

2026-07-28

## Context

Phase 8 (ADR 0007) made Laravel/PostgreSQL authoritative for a Document's
identity, workspace ownership and lifecycle state, and implemented the first
transition of that lifecycle: `CompleteDocumentUpload` moves a Document from
`Uploading` to `Uploaded` inside a single database transaction, after
`DocumentObjectStorage` has verified the object actually exists in storage
and matches the expected size. No queue message is involved in that stage —
Stage 8.3 explicitly ends with "no queue job was pushed and no Document
advanced to `QUEUED`."

Phase 9 must now take a Document the rest of the way from `Uploaded` to
`Queued` to `Processing`. Doing so requires two things to both happen for a
given Document: Laravel must transition its lifecycle state in PostgreSQL,
and Laravel must publish a versioned ingestion-request event that a separate
Python worker will eventually consume from SQS. The ingestion queue and its
dead-letter queue already exist in the local environment
(`rag-platform-ingestion-local` / `rag-platform-ingestion-dlq-local`,
provisioned with a redrive policy by ADR 0004's LocalStack initialisation
hook) — but nothing publishes to them yet, and no outbox, event table or
publisher exists anywhere in the codebase today.

### Why this is a dual-write problem

PostgreSQL and SQS are two independent systems. There is no native mechanism
that commits a Postgres transaction and sends an SQS message as one atomic
unit — `DB::transaction()` only guarantees atomicity over statements executed
against that one database connection. Once a Document's lifecycle transition
and its corresponding SQS publish are treated as two separate operations, one
can succeed while the other fails, and both orderings are unsafe:

```text
Commit first, then publish:
  Database transaction commits (Document is now QUEUED)
  ↓
  SQS publish fails or the process dies before attempting it
  ↓
  The Document is QUEUED, but no event exists to tell a worker so.
  Nothing durable records that a publish is owed.
```

```text
Publish first, then commit:
  SQS message is sent
  ↓
  The database transaction fails or rolls back
  ↓
  A worker can receive and act on an event describing a Document
  state that was never actually committed.
```

The first ordering produces a Document that is silently stuck — permanently
`Queued` with no way to know a publish never happened, unless something else
notices. The second produces a worker acting on a lifecycle transition that
does not exist. Reversing the order does not remove the hazard; it only
changes which side of it is unsafe.

### Why an ordinary transaction, observer or listener does not solve this

Wrapping the SQS call inside the same `DB::transaction()` closure as the
lifecycle transition does not make the two atomic — it only means the SQS
call happens somewhere inside the Postgres transaction's lifetime, with no
guarantee about what happens if the transaction later fails to commit for an
unrelated reason (a deadlock, a lost connection, a constraint violation
raised on commit) after the SQS call has already sent.

Dispatching the publish from an Eloquent model observer or a Laravel event
listener registered on `DB::afterCommit()` is a real improvement, and is
worth treating fairly rather than dismissing outright: an `afterCommit` hook
does correctly defer the SQS call until Laravel already knows the transaction
succeeded, which removes the "publish before commit" hazard above entirely.
What it does not remove is the durability gap between the commit succeeding
and the SQS call actually completing. If the request process crashes, SQS is
temporarily unreachable, or the worker handling the request is killed in that
window, nothing durable has recorded that a publish is still owed — the
intent to publish lived only in a listener's call stack. Recovering from that
requires either accepting silent loss or building some other mechanism to
notice and retry, which is precisely what a durable outbox record is for. An
`afterCommit` listener remains a reasonable way to *trigger* prompt
publication; it is not, by itself, a way to guarantee publication survives a
crash.

Publishing directly from an HTTP controller has the same problem again,
compounded by coupling an unrelated external system's availability (SQS) to
the latency and success of a user-facing request.

## Decision

### Adopt a PostgreSQL-backed transactional outbox for ingestion-request publication

Within one PostgreSQL transaction, Laravel will:

1. verify the Document is eligible for ingestion (workspace-scoped,
   authorised, in the expected `Uploaded` state — following the same
   authorisation and eligibility-checking shape `CompleteDocumentUpload`
   already uses for the previous transition);
2. transition the Document from `Uploaded` to `Queued`, extending the state
   machine ADR 0007 already accepted;
3. persist one outbox record containing the versioned ingestion event and a
   stable logical event identifier.

A separate outbox publisher — a dedicated process, not request-cycle code —
will:

1. locate unpublished outbox records;
2. validate each payload against the canonical, versioned event contract
   (`contracts/events/document-ingestion-requested/`, Stage 9.1's
   responsibility, not this ADR's);
3. publish the event to SQS (LocalStack locally, AWS SQS in production, per
   ADR 0004, using the ingestion queue and dead-letter queue already
   provisioned there);
4. mark the outbox record as published only once SQS confirms the send;
5. retry transient publication failures.

Publication infrastructure — the outbox table and the publisher — lives
outside the `Document` model, following the same separation
`DocumentObjectStorage` already established as an `app/Services` concern
rather than model logic. Controllers must not publish to SQS directly.

### What "transactional" means here

"Transactional" refers only to the PostgreSQL transaction pairing the
Document's lifecycle transition with the outbox record's creation — both
happen, or neither does, inside Postgres. It does not mean PostgreSQL and SQS
become one distributed transaction; SQS publication happens afterward,
asynchronously, entirely outside that transaction boundary. This is a durable
local commit followed by a best-effort, durably-retried remote publish — not
a two-phase commit protocol spanning both systems.

### Durable intent, not exactly-once delivery

The outbox guarantees that once a Document is committed as `Queued`, the
intent to publish its event is never silently lost. It does not guarantee
exactly-once delivery. A publisher can send an event successfully and then
fail before marking its outbox record published, causing the same event to
be sent again on the next sweep. SQS itself is an at-least-once delivery
mechanism regardless of the outbox. Consumers (Stage 9.3) must therefore be
idempotent using the outbox's stable logical event identifier, not the SQS
transport message identifier, since a logical event can be republished under
a different transport message identifier.

### Ownership and boundaries

- PostgreSQL owns durable publication intent — the outbox record is a
  PostgreSQL concern, not a queueing concern.
- SQS is transport only. It moves bytes between Laravel and the Python
  worker; it is never the record of whether ingestion was requested.
- Laravel remains the sole owner of Document lifecycle state, exactly as ADR
  0007 established. The outbox does not change that ownership — it is how
  Laravel durably announces a lifecycle transition it has already made
  authoritatively.
- Python consumes events but must not write to Laravel-owned tables
  directly. Any further lifecycle transition Python needs (`Queued →
  Processing`, in Stage 9.3) is requested through an authenticated internal
  application boundary. The exact authentication mechanism for that boundary
  is not decided by this ADR.
- Publication infrastructure must not live inside the `Document` model, and
  controllers must not call SQS directly — both would scatter a
  cross-cutting infrastructure concern across domain and presentation code
  that should own neither.

### Expected outbox semantics

Described conceptually; exact columns, types and migration are Stage 9.2
work, not fixed here. An outbox record needs to carry:

- a stable, logical event identifier that survives republication (distinct
  from the transport-level SQS message identifier);
- the event type and version, matching the versioned contract;
- the Document (aggregate) identifier and the owning workspace identifier;
- a correlation identifier connecting the originating request, the outbox
  record, the published event and downstream logs;
- the event payload itself;
- an occurred-at timestamp and a published-at timestamp (null until a
  confirmed send);
- enough publication-attempt information (for example a last error and an
  attempt count) to diagnose a stuck record without prescribing its exact
  shape.

Multiple publisher instances, or a restarted publisher, must not
uncontrollably double-claim the same record. PostgreSQL row-claiming (for
example `SELECT ... FOR UPDATE SKIP LOCKED`, the conventional mechanism for
concurrent outbox pollers, and consistent with `CompleteDocumentUpload`'s
existing use of row locking for a similar idempotent-transition problem) is
the natural fit for this stack. This ADR does not mandate a specific claiming
implementation, polling interval or batch size — those remain Stage 9.2
decisions.

Published records should not accumulate indefinitely. A retention or archival
policy is expected, but its schedule and mechanism are left configurable and
decided later; this ADR only commits to retention being a deliberate,
revisited concern rather than an unbounded table.

### Failure and retry behaviour

- **PostgreSQL transaction failure**: the transition and the outbox record
  are both part of the same transaction, so a rollback leaves neither in
  place. There is nothing to publish and nothing inconsistent.
- **SQS unavailable at publish time**: the outbox record stays unpublished.
  The Document remains legitimately `Queued` — that state is already
  correctly committed — and the publisher retries later. The unpublished
  record itself is the durable, observable trace of the pending work.
- **Publisher sends successfully, then fails before marking the record
  published**: the next sweep republishes the same logical event. This is an
  accepted consequence of not attempting a distributed transaction, and is
  exactly why consumer idempotency is mandatory rather than optional.
- **Duplicate publication** (for example a race between two publisher
  instances, or a retry racing an in-flight "mark published" update) is
  reduced by safe row-claiming but not eliminated as a possibility.
  Idempotent consumers are the actual safety net; publication is not assumed
  to be exactly-once anywhere in this design.
- **Duplicate SQS delivery**, independent of the above and inherent to SQS
  itself, is handled the same way: by idempotent consumers using the logical
  event identifier.
- **Poison or permanently invalid outbox payloads** (for example a payload
  that will never pass contract validation because of a defect) must not be
  retried forever. The publisher needs a way to set such a record aside for
  diagnosis, distinct from an ordinary transient failure — the exact
  mechanism (a distinct status, an attempt ceiling, or an equivalent) is a
  Stage 9.2 decision; this ADR commits only to poison payloads not producing
  an unbounded, tight retry loop.
- **Diagnostic information** — at minimum, enough to know why a record has
  not yet published (last error, attempt count or timestamp) — should be
  preservable without this ADR fixing its exact representation.
- **Avoiding infinite tight retry loops**: whatever retry strategy the
  publisher uses must not hot-loop against an unavailable SQS; some form of
  backoff is required, with exact parameters deferred.

Publisher-side retry (described above) is a different concern from SQS
consumer-side retry and dead-lettering (Stage 9.3, governed by ADR 0004's
existing redrive policy): the publisher retries because it has not yet
successfully *sent* an event; the consumer's redrive policy governs a worker
that received a message but failed to process it. Conflating the two would
misattribute a delivery problem to a processing problem, or vice versa.

## Alternatives considered

### Publish directly to SQS immediately after the database commit

Rejected. Nothing durable records that a publish is owed between the commit
succeeding and the publish call being attempted; a crash or an unavailable
queue in that window loses the event silently, with no queryable state to
recover from.

### Publish to SQS before the database transaction commits

Rejected. If the subsequent commit fails, a worker can receive and act on an
event describing a Document state that was never actually persisted, and
there is no way to retract an already-sent SQS message.

### Dispatch from an Eloquent model observer or Laravel event listener

Discussed in Context above. An `afterCommit`-dispatched listener correctly
avoids publishing before a commit is known to have succeeded, but it does not
provide a durable record surviving a crash between commit and publish —
nothing queryable exists to know a publish is still owed if the listener's
call never completes. Rejected as the sole mechanism; remains reasonable only
as a way to trigger the outbox publisher promptly, not as a replacement for
the durable record itself.

### Use only Laravel's database queue

Considered fairly, as requested, because it does genuinely solve the
dual-write problem: if the "queue" were just another PostgreSQL table
(`jobs`), inserting a job and transitioning the Document could happen in the
same transaction, the same way the outbox does. It was rejected because it
conflicts with architecture already accepted elsewhere in this platform.
`contracts/events/README.md` already states the principle directly: "Laravel
job serialization is not a cross-language contract." Phase 9's Python worker
needs a versioned, language-neutral event it can consume without depending on
PHP's internal job-serialisation format, and ADR 0004 has already provisioned
SQS and its dead-letter queue specifically for this purpose. Adopting the
database queue as the real transport would mean discarding that
already-provisioned infrastructure and inventing a way for Python to poll
PostgreSQL directly — a larger, uninvited architecture change, not a smaller
one. The pattern is legitimate; it does not fit this platform's already-
accepted service boundary between Laravel and Python.

### Distributed transaction or two-phase commit across PostgreSQL and SQS

Rejected. SQS does not support participating in an external two-phase-commit
protocol, so this is not actually available regardless of preference. Even
where a broker supports XA-style coordination, two-phase commit introduces
blocking coordination and availability coupling between the two systems,
working directly against the independent-service-boundary architecture ADR
0002 already established.

### Change-data capture instead of an application-managed outbox table

Recognised as a legitimate, well-known larger-scale evolution of the same
underlying pattern (reading the PostgreSQL write-ahead log, for example with
Debezium, instead of an application-written outbox table). Rejected for now
as disproportionate operational complexity — a CDC connector, its own
infrastructure, and schema-change sensitivity — for a platform at this stage
with a single event type and modest volume. Worth revisiting if outbox
polling overhead or latency is ever demonstrated to be an actual problem,
rather than assumed in advance.

### Periodically scan all `Queued` Documents and reconstruct missing events

Rejected. This still requires recording, somewhere, whether an equivalent
event was already published for a given Document — otherwise every scan
would need to guess whether a `Queued` Document is waiting for its first
publish or was already published and is simply still processing. That record
is the outbox by another name, but without per-attempt diagnostic
information, without a precise moment of failure to reason about, and with a
reconciliation job effectively re-deriving intent after the fact instead of
recording it durably at the moment it was known.

## Consequences

### Positive

- No silent loss of publication intent once a Document's lifecycle
  transition has committed.
- Atomic consistency between `Queued` state and durable event intent — both
  succeed together in PostgreSQL, or neither does.
- Resilience across a temporary SQS outage: retry resumes from a durable
  record, not from in-memory or in-flight state.
- Outbox records are inherently an audit and observability surface
  (unpublished count, oldest unpublished age, attempt and failure history).
- Retries are controlled and visible, rather than inferred after the fact.
- One clear producer responsibility — PostgreSQL owns intent, a dedicated
  publisher owns delivery — instead of publish calls scattered across
  controllers, observers or listeners.

### Negative

- An additional table and persistence model to design, migrate and operate.
- A separate publisher process to build, deploy and keep healthy — another
  component that can be down and must be monitored.
- Duplicate publication remains possible; this pattern makes non-delivery
  unlikely, but it does not remove the need for idempotent consumers.
- Idempotent consumers remain mandatory regardless of how reliable
  publication becomes — Stage 9.3 must still implement this properly.
- Retention and ongoing operational monitoring of the outbox itself become
  required, continuing work rather than one-time setup.
- Publication becomes eventual rather than immediate: a window, normally
  small, exists between a Document becoming `Queued` and its event actually
  reaching SQS.
- More failure modes and tests to build and maintain (transaction failure,
  SQS outage, publisher crash mid-send, poison payloads, duplicate delivery)
  than a naïve direct-publish design would appear to need on paper — even
  though that apparent simplicity is illusory given the dual-write hazard
  described in Context.

## Scope boundaries

This ADR does not define:

- the exact JSON event schema, which is Stage 9.1/contract work
  (`contracts/events/document-ingestion-requested/`);
- the outbox table's exact columns, types or migration;
- the final publisher polling interval, batch size, or row-claiming
  implementation;
- consumer processing logic (Stage 9.3);
- text extraction, chunking, embeddings, or vector indexing (Phase 10
  onward);
- Document failure semantics during actual ingestion processing, as distinct
  from the publication failure modes this ADR does cover;
- a generic, platform-wide event bus for arbitrary future domain events.

This decision applies specifically to document-ingestion request publication.
Any broader adoption of the outbox pattern for other future events should be
a deliberate future decision, not an assumption extending from this ADR.

## Operational implications

- Publisher health and backlog must be observable: unpublished-event count,
  oldest-unpublished-event age, and publication attempt/failure logging.
- Correlation identifiers must be preserved from the originating request,
  through the outbox record, into the published event and downstream logs.
- Graceful publisher shutdown must not lose track of a row it has claimed —
  the exact mechanism is deferred, but losing a claimed row's state on
  shutdown is not acceptable.
- LocalStack SQS locally, AWS SQS in production, per ADR 0004. This ADR
  introduces no new infrastructure component — no MinIO, no second queue
  emulator.
- Testing must cover, at minimum: a rolled-back PostgreSQL transaction
  produces no outbox record; an SQS outage leaves a retryable record that a
  later publisher run successfully sends; and duplicate publication is
  demonstrated not to break the producer side's own guarantees, even though
  consumer-side idempotency is out of this ADR's scope.

## References

* [`contracts/events/README.md`](../../contracts/events/README.md) — states
  that Laravel job serialisation is not a cross-language contract, directly
  informing the rejection of the Laravel database-queue alternative.
* [`docs/adr/0004-use-localstack-4-for-local-aws-emulation.md`](0004-use-localstack-4-for-local-aws-emulation.md)
  — the LocalStack/AWS SQS architecture this ADR builds on.
* [`docs/adr/0007-define-the-document-lifecycle-and-storage-model.md`](0007-define-the-document-lifecycle-and-storage-model.md)
  — the Document lifecycle this ADR extends with `Uploaded → Queued`.
