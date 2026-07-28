# Session Journal: R09-S02 — Publish Ingestion Requests Reliably

## Date

2026-07-28

## Session mode

Implementation in teaching mode under accepted ADR-0008.

The session was bounded to the authenticated ingestion-request operation,
transactional outbox persistence and reliable Laravel-to-SQS publication. No
Python consumer, lifecycle claim to `PROCESSING`, parsing, extraction,
chunking, embedding or vector storage was implemented.

## Agreed implementation decisions

The human developer approved:

* `POST /api/workspaces/{workspace}/documents/{document}/ingestion-requests`;
* `202 Accepted` with the current public Document resource;
* `UPLOADED → QUEUED` creating exactly one durable outbox event;
* idempotent responses for `QUEUED` and `PROCESSING`;
* conflicts for `INDEXED`, `FAILED` and every other ineligible state;
* a valid optional `X-Correlation-ID`, otherwise server generation;
* row-locked lifecycle enforcement;
* leased publisher claims;
* terminal handling for deterministic contract defects;
* durable backoff for transient transport failures;
* a dedicated publisher process; and
* stable event identity across publication retries.

## What happened

Laravel gained a thin ingestion controller and a focused
`RequestDocumentIngestion` action. Workspace membership and Document lookup
remain explicit and fail closed. The action locks the authoritative Document
row and commits its `UPLOADED → QUEUED` transition with one immutable outbox
event in the same PostgreSQL transaction.

The outbox stores the canonical payload plus public tenant/aggregate identity,
correlation data, claim state, attempt diagnostics and publication outcome.
It intentionally does not hold foreign keys to the Workspace or Document:
publication evidence should remain diagnosable without blocking later
aggregate deletion.

A dedicated Compose `publisher` process runs the
`ingestion:publish` Artisan command. It claims one due record at a time using
PostgreSQL `FOR UPDATE SKIP LOCKED`, persists a token and lease, then releases
the database transaction before validating and sending to SQS.

Successful publication records `published_at`. Deterministically invalid
payloads receive `failed_at` and are not hot-looped. Transient transport
failures retain the original event, store a sanitised diagnostic and schedule
capped exponential backoff. Expired claims can be recovered after a publisher
crash.

## Why endpoint retries do not create duplicate work

There is no separate endpoint idempotency record.

The Document lifecycle itself is the invariant. Only a transaction that holds
the Document lock and observes `UPLOADED` may create an outbox event. That
transaction commits the state as `QUEUED`. A concurrent or later request then
observes `QUEUED` or `PROCESSING` and returns without creating anything.

A permanent unique constraint on Document identity in the outbox was avoided
because a future explicit `FAILED → QUEUED` retry should be allowed to create
a new logical event. Event identity is unique and immutable; Document identity
is not globally unique across all future ingestion attempts.

## Verification performed

* Focused R09-S02 suite: 21 passed (105 assertions).
* Full Laravel suite: 95 passed (364 assertions).
* Full web suite: 10 passed.
* Full AI suite: 10 passed.
* Pint, ESLint, Ruff, TypeScript and mypy passed.
* Composer validation passed.
* A production-only Composer dry run proved the runtime validator remains
  installed without development dependencies.
* Compose configuration passed and the dedicated publisher remained running.
* LocalStack bucket, queue, DLQ and redrive-policy verification passed.
* A disposable PostgreSQL database migrated cleanly and exposed the expected
  outbox constraints, then was removed.
* Route and command registration passed.
* `git diff --check` and tracker JSON validation passed.

The focused suite covers the lifecycle matrix, authentication, verification,
all membership roles, tenant isolation, atomic rollback, correlation handling,
event contract contents, repeated requests, unique/immutable event identity,
leased claims, transient retry, sanitised diagnostics, deterministic poison
handling, repeat publisher execution, the one-shot command and clean
migration shape.

## Live LocalStack acceptance

A disposable positive-sized text Document was created in `UPLOADED`.
Requesting ingestion committed it as `QUEUED` with one outbox event. The
dedicated process published the exact version 1 JSON payload to LocalStack
SQS, retained the supplied correlation UUID and marked the same logical event
published with one attempt.

The SQS transport message identifier appeared only as publication evidence;
the stable outbox `event_id` remained the logical identifier. The disposable
Document, outbox record and queue message were removed afterward.

## Problems and corrections

Opis JSON Schema was development-only after R09-S01, but R09-S02 makes
validation a runtime publisher responsibility. The pinned package was moved
to Composer's production dependency set.

The first formatter invocation caught a PHP parse error in a chained `match`
expression in the new test helper. It was corrected before tests ran.

Batch claiming originally leased all selected events before sending the first.
Claiming was changed to happen immediately before each event so a slow earlier
send does not unnecessarily consume later events' leases.

The long-running service can start before migrations during `make bootstrap`.
It now waits rather than crashing; one-shot operation reports the missing
migration as an error.

## Important takeaways

* Idempotency belongs at the authoritative state transition, not merely at the
  HTTP edge.
* An outbox removes lost publication intent, not duplicate delivery.
* A claim lease coordinates publisher instances; consumer idempotency remains
  the safety boundary after a send/mark crash.
* Contract validation must be a production dependency when it protects a
  production publication boundary.
* Publisher retry and SQS consumer redrive diagnose different failures and
  must remain separate.

## Next step

R09-S02 was approved for its stage commit and annotated `phase-9-s02` tag.
Proceed to the bounded Stage 9.3 architecture discussion and implementation
brief.
