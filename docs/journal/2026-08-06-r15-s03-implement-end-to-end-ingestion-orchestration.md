# Session Journal: R15-S03 — Implement End-to-End Ingestion Orchestration

## Date

2026-08-06

## What was implemented

The previously separate upload, extraction, chunking, embedding and vector-storage
foundations are now connected by the recoverable ingestion saga accepted in ADR 0015
and ADR 0016. Laravel owns an event-scoped processing attempt, expiring lease,
canonical chunks, generation state, publication authority and final Document
lifecycle. Python owns processing and the provisional-to-published Qdrant projection,
but can change Laravel state only through eight narrowly purpose-signed HMAC v2
operations.

The worker submits bounded chunk batches, seals the immutable authoritative chunk set,
writes provisional vectors through `VectorStore`, verifies the exact set, obtains
evidence-bound publication authorisation, publishes and verifies again before asking
Laravel to complete. Laravel then atomically activates the corpus generation, records
its Workspace as current and changes the Document to `INDEXED`. Permanent processing
failures follow an equivalent lease-gated path to `FAILED`.

Retries preserve `event_id` identity. Open attempts are reset and their provisional
projection cleaned after lease loss; sealed attempts are resumed without repeating
extraction or chunking. Lease renewal and SQS visibility extension are coordinated but
remain independent operations, and uncertainty in either stops authoritative work.
Only an authoritative terminal outcome acknowledges the queue message. A dedicated
one-shot DLQ mode exposes the same reconciliation policy for operational scheduling.

## Verification evidence

* Laravel: 155 tests passed with 668 assertions.
* Python: 198 tests passed; one credential-dependent live Voyage test was skipped as
  designed.
* Frontend: 26 tests passed across seven files.
* Pint, Ruff formatting/lint, MyPy, ESLint and TypeScript passed.
* The production Next.js build passed with 11 routes.
* A clean isolated PostgreSQL migration and direct schema inspection passed.
* PHP and Python passed the same canonicalisation, HMAC and deterministic-identity
  fixtures, and produced the same embedding configuration fingerprint.
* Embedding-space provisioning was idempotent.
* Compose validation, image rebuilds and all service health checks passed.
* `git diff --check` passed.

## Problems and corrections

The first frontend build inherited a nonstandard host `NODE_ENV`, causing a Next.js
internal route-generation failure. Re-running the production gate with
`NODE_ENV=production` passed without a code change. Building the complete worker also
showed that validating a Voyage credential during process startup would make a healthy
queue consumer unavailable before it had work; provider construction is therefore
deferred until embedding is actually required. Provider credential and service
failures remain operational retry conditions and are not misreported as permanent
Document failures.

## Architectural boundary held

ADR 0015 and ADR 0016 were implemented without amendment. Python gained no direct
PostgreSQL access, Laravel remained authoritative for lifecycle and generation state,
Qdrant remained isolated behind `VectorStore`, and raw vectors were not added to
PostgreSQL. No retrieval implementation or Phase 16 work began.

## Commit boundary

Approved commit: `Complete end-to-end document ingestion orchestration`

Approved annotated tags: `phase-15-s03` and `phase-15`
