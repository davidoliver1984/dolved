# Session Journal: R08-S03 — Implement the Document Upload Workflow

## Date

2026-07-28

## Session mode

Implementation in teaching mode. Work was bounded to direct browser-to-S3
upload initialisation, byte transfer, storage verification and the
`UPLOADING → UPLOADED` lifecycle transition.

No ingestion event, queue publication, parsing, extraction, chunking,
embedding, vector storage, retrieval, deletion, versioning, S3 multipart
upload or PostgreSQL RLS was implemented.

## Pre-implementation decisions

The implementation brief described MinIO as the local object store, which
contradicted accepted ADR-0004 and the repository's LocalStack-based Compose,
provisioning and verification workflow. Work paused before editing.

The human developer accepted:

* retaining LocalStack 4.14 for local S3 emulation;
* treating MinIO references as generic S3-compatible requirements;
* permitting all active workspace roles—owner, admin and member—to upload;
* deferring granular document permissions; and
* leaving PostgreSQL RLS outside R08-S03.

These resolutions preserve ADR-0004, ADR-0006 and ADR-0007. No new ADR was
required.

## What happened

Laravel gained three authenticated, verified, workspace-scoped operations:

* read central upload configuration;
* initialise an upload and receive a short-lived presigned PUT request; and
* complete an upload after server-side object verification.

Thin HTTP coordination uses a Form Request, membership-scoped queries,
Workspace/Document policies, focused Actions and an isolated object-storage
Service. Server-generated workspace/document UUID paths never include the
original filename. Completion confirms existence and exact size before a
row-locked `UPLOADING → UPLOADED` transition. Repeated completion is
idempotent.

The browser now supports multiple selection, drag-and-drop, a waiting queue,
duplicate prevention, removal, three-at-a-time bounded uploads, real
XMLHttpRequest byte progress, a distinct verification state, accessible
progress, independent errors and individual retry.

## LocalStack and AWS crossover

The local workflow uses two endpoint views of the same bucket:

* `http://localstack:4566` for Laravel's storage verification;
* `http://localhost:4566` when signing URLs the host browser must reach.

LocalStack's idempotent ready hook applies the required bucket CORS policy.
Production uses the same S3 APIs without local endpoint overrides.

## Verification performed

* Focused Laravel document tests: 31 passed (129 assertions).
* Full Laravel suite: 68 passed (249 assertions).
* Focused frontend upload tests: 6 passed.
* Full web suite: 10 passed across 4 files.
* AI suite: 1 passed.
* Pint, ESLint, Ruff formatting/linting, TypeScript and mypy passed.
* Compose configuration validation and `git diff --check` passed.
* `make aws-status` verified bucket CORS, bucket existence, queues and redrive
  policy.
* All six services reported healthy.
* A disposable authenticated smoke test successfully performed CORS preflight,
  direct PUT to the signed LocalStack URL, server-side HEAD/size verification,
  and completion to `uploaded`. The returned resource exposed only safe public
  fields. The synthetic object and database row were then removed.

## Problems and corrections

The first backend test attempted to assert that no event of any kind was
dispatched. Laravel necessarily emits authentication, policy, request and
Eloquent events. The assertion was narrowed to the architectural concern for
this stage: no ingestion work was pushed to the queue.

The initial component test run revealed that this repository's Vitest setup
does not globally clean React Testing Library renders. Explicit cleanup was
added so test DOM does not leak between cases.

The first HTTP smoke attempt did not include the frontend Origin, and the next
attempt reused CSRF state after login. The smoke was corrected to match
`apiFetch`: send the frontend Origin and refresh the Sanctum CSRF cookie before
each unsafe request.

The in-app automation browser blocks localhost under its URL policy. No bypass
or alternate automation surface was used. Component tests and the live
HTTP/LocalStack flow are verified; visible multi-file interaction is awaiting
human review.

The human developer subsequently exercised the live multi-file interface,
confirmed that it looked and behaved correctly, and approved the Phase 8
commit and tag boundary.

## Important takeaways

* A presigned URL is a narrow temporary capability, not a substitute for
  Laravel authorisation or completion verification.
* Docker-internal and browser-reachable service names are different trust and
  network contexts; signing must use the host the browser will actually send.
* The database transaction can protect Document creation plus URL generation,
  but it cannot make the later browser PUT atomic. The lifecycle explicitly
  represents that distributed gap.
* `VERIFYING` is a frontend workflow state, not a new Document status.
* This stage stops at `UPLOADED`; Phase 9 owns event publication and the
  transition to `QUEUED`.
