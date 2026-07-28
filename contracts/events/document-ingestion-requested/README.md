# Document Ingestion Requested

A request that a previously uploaded Document be ingested (extracted,
chunked, embedded and indexed in later phases). Published by Laravel and
consumed by the Python ingestion worker.

## Files

| File | Purpose |
|---|---|
| `v1.schema.json` | The canonical JSON Schema for version 1 of this event. Laravel and Python both validate against this same file — neither language owns its own copy of the contract. |
| `v1.example.json` | A complete, valid example payload. |
| `fixtures/` | Deliberately invalid payloads, one defect each, for negative validation tests shared by both languages. |

## Producer and consumer

* **Producer**: Laravel, via the transactional outbox described in
  [ADR-0008](../../../docs/adr/0008-use-the-transactional-outbox-pattern.md).
  An outbox publisher — not a controller, not a model observer — sends this
  event to SQS only after the Document's `UPLOADED → QUEUED` transition (see
  [ADR-0007](../../../docs/adr/0007-define-the-document-lifecycle-and-storage-model.md))
  has committed in PostgreSQL.
* **Consumer**: a dedicated Python worker process (Stage 9.3), separate from
  the FastAPI HTTP application. The worker requests the Document's further
  `QUEUED → PROCESSING` transition through an authenticated internal Laravel
  boundary — it does not write to Laravel's tables directly.

Laravel remains the sole authority for Document lifecycle state at every
point. This event is a request to act, not a record that the action has
happened.

## Delivery semantics

* Delivery is **at least once**. The same event may be received more than
  once, under the same or a different SQS message identifier.
* Message ordering is **not guaranteed**.
* Consumers must be **idempotent**, keyed on `event_id` — the logical event
  identifier — never on the SQS transport message identifier alone, since a
  republished logical event can arrive under a new transport identifier.
* An unsupported `event_version` must be rejected safely, not guessed at.
* A malformed or contract-invalid event must not be processed.
* A message is acknowledged only once the responsibility it represents
  (claiming the Document for processing) has completed durably.
* Repeated terminal failures are routed to the ingestion dead-letter queue
  established by [ADR-0004](../../../docs/adr/0004-use-localstack-4-for-local-aws-emulation.md).

## Versioning policy

`event_type` is a fixed discriminator (`document.ingestion.requested`);
`event_version` is a plain integer. A breaking change to the contract
introduces a new integer version and a new `vN.schema.json` file alongside
this one — this schema is never edited in place to add or remove a required
field. A consumer must treat an `event_version` it does not recognise as
unsupported, not attempt partial or best-effort compatibility.

**This schema also treats a purely additive change — a new optional field
that no existing consumer needs — as requiring a new version.**
`additionalProperties: false` means any field not listed here fails
validation, not just a field of the wrong shape. This is a deliberate
trade-off, not an oversight: it is consistent with this platform's general
preference for strict, fail-closed contracts over lenient ones (for example,
ADR-0006's tenant context failing closed, and ADR-0007's partial writes not
qualifying as `INDEXED`), and it is cheaper here than it would be for a
public, multi-consumer API — this event currently has exactly one producer
(Laravel) and one consumer (the Python worker), maintained in the same
monorepo, so a version bump means updating two things that already move
together rather than coordinating independent deployments. Revisit this
policy if a second, independently-deployed consumer of this event ever
appears.

## What this contract deliberately excludes

* Storage credentials or presigned URLs — the worker reaches storage through
  its own configured access, not a credential embedded in the event.
* User-facing secrets or complete user records — this event concerns a
  Document and its owning workspace, not identity data.
* Unbounded or arbitrary metadata — every field is explicit and required;
  `additionalProperties` is `false` in the schema so an unexpected field
  fails validation immediately rather than being silently carried along.

## `event_id` vs. `correlation_id`

These are easy to conflate and serve different purposes:

* `event_id` identifies **this logical ingestion request**. It is what
  idempotency is keyed on, and it stays the same across a publication retry
  or a duplicate delivery of the same request.
* `correlation_id` traces **one causal chain** — the originating HTTP
  request, the outbox record, this event, and the logs it produces
  downstream — without implying anything about duplicate delivery.

## Validating a payload locally

```bash
# Laravel
docker compose exec -T api php artisan test \
  tests/Unit/DocumentIngestionRequestedContractTest.php

# Python
docker compose exec -T ai uv run pytest \
  tests/test_document_ingestion_requested_contract.py -v
```

Both suites read this directory through the repository's read-only
`/contracts` container mount. The schema, example and invalid fixtures are not
copied into either application.
