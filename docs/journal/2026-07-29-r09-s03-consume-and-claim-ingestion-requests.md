# Session Journal: R09-S03 — Consume and Claim Ingestion Requests

## Date

2026-07-29

## Session mode

Implementation in teaching mode under accepted ADR-0008 and ADR-0009.

The session was bounded to SQS receipt, canonical event validation, the signed
internal Laravel claim boundary and the durable `QUEUED → PROCESSING`
transition. It did not implement extraction, source-object processing,
chunking, embeddings, vector storage or later lifecycle transitions.

## Agreed implementation decisions

The human developer approved:

* HMAC-signed service-to-service authentication for only the internal AI
  worker lifecycle endpoint;
* a dedicated worker identity and environment-managed secret;
* timestamp replay protection and constant-time signature comparison;
* Key IDs and overlapping secrets for rotation;
* the exact ADR-0009 canonical string;
* HTTPS and private networking in production;
* Laravel as the authority that validates and atomically performs
  `QUEUED → PROCESSING`; and
* a durable claim keyed by the logical event ID rather than the SQS transport
  identifier.

## What happened

Laravel gained a dedicated HMAC middleware, request authenticator, internal
claim controller and `ClaimDocumentIngestion` action. It independently
validates the shared version 1 event contract, locks the Workspace-scoped
Document row and commits a durable claim with the lifecycle transition.

The claim stores public tenant and aggregate identities, correlation context,
claim time and the exact event-payload digest. An identical event can be
acknowledged idempotently after any process restart. Reusing an event ID for a
different payload fails closed.

Python gained a separate ingestion worker process. It long-polls SQS,
validates the shared schema, signs the exact raw body and asks Laravel to
claim processing. Only `claimed`, `already_claimed` and safe `stale_event`
outcomes delete a message. Poison and retryable outcomes remain available to
SQS visibility and redrive handling.

The process logs structured event and transport context, supports bounded
`--once` execution and handles `SIGTERM` and `SIGINT` without acknowledging an
unfinished claim. Compose, example environments, Make commands and README
operations were updated for the worker.

## Why idempotency is durable

SQS delivery identifiers are transport details and can change when the same
logical event is republished. The claim therefore uses the contract's
`event_id` as its unique identity.

Laravel owns the durable decision. The worker does not keep an in-memory
processed set and does not access PostgreSQL directly. The combination of a
unique event ID, exact payload digest, locked Document row and atomic claim
plus lifecycle transaction distinguishes:

* an identical duplicate, which is safe to acknowledge;
* identity reuse with changed content, which is poison;
* a stale event after lifecycle progress, which is safe to acknowledge; and
* an ineligible event, which remains for redrive and investigation.

## Verification performed

* Focused Laravel suite: 23 tests passed with 127 assertions.
* Full Laravel suite: 118 tests passed with 491 assertions.
* Focused Python worker suite: 32 tests passed.
* Full Python suite: 42 tests passed.
* Full Next.js suite: 10 tests passed.
* Repository formatting, linting, type checking, tests and process checks
  passed.
* Composer strict validation, production Python dependency resolution,
  Compose configuration and the registered internal route passed.
* A disposable PostgreSQL database migrated cleanly; claim constraints and
  indexes were inspected before the database was removed.
* LocalStack bucket, queue, DLQ and redrive settings passed inspection.
* `git diff --check` and tracker JSON validation passed.

## Live LocalStack acceptance

A synthetic uploaded Document followed the complete Phase 9 path: Laravel
committed its outbox and `QUEUED` state, the publisher sent the canonical
event to LocalStack, the Python worker validated and signed it, Laravel
committed one claim and `PROCESSING`, and the worker acknowledged the message.

Republishing the same logical event with a new SQS message ID produced
`already_claimed` and no second claim. A temporary bad Laravel URL left the
message available for retry; the restored worker later acknowledged it. A
malformed event was received three times and reached the configured DLQ.
Publisher, worker and Laravel logs preserved correlation context.

The worker also handled a termination signal cleanly. No acceptance path
marked a Document `INDEXED`. Synthetic database rows were removed and both
queues were purged afterward.

## Problems and corrections

The worker requires HTTP and JSON Schema libraries at runtime, so `httpx` and
`jsonschema` were moved from development-only dependencies.

The claim record was strengthened with the canonical payload digest to make
logical event identity reuse distinguishable from an identical duplicate.

The known local HMAC credential remains explicit in example and Compose-local
configuration but was removed from application defaults. Missing deployed
secret configuration therefore fails closed.

Final review identified an inaccurate test fixture for Laravel's
success-shaped `ineligible_state` response. The fixture was separated from
exception-shaped poison responses and now documents the real wire contract.
Malformed SQS receive entries also gained a safe warning and regression test;
they remain unacknowledged for visibility-timeout retry without logging their
body or receipt handle.

No visibility heartbeat was added because this stage performs only one
bounded HTTP claim and the configured visibility timeout exceeds the HTTP
timeout. Long-running processing must revisit visibility extension in its own
stage.

## Important takeaways

* Durable consumer idempotency belongs at the authoritative application
  boundary, not in worker memory.
* A logical event ID and an SQS message ID answer different questions.
* Authenticating a request does not remove the need for independent contract,
  tenant and lifecycle validation.
* Messages should be deleted only after a response whose semantics make
  redelivery unnecessary.
* DLQ acceptance is part of proving poison-message handling, not merely an
  infrastructure setting.

## Next step

R09-S03 was approved after final review. Create the focused stage commit,
annotated `phase-9-s03` tag and Phase 9 completion tag, then begin the R10-S01
architecture session.
