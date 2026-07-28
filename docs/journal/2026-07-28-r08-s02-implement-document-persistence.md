# Session Journal: R08-S02 — Implement Document Persistence

## Date

2026-07-28

## Session mode

Implementation in teaching mode, bounded to the relational persistence layer
defined by ADR-0007. No upload, object-storage integration, queue,
processing, chunking, embedding, retrieval, versioning, policy, route or
frontend behaviour was implemented.

## What happened

The Laravel API gained the authoritative relational representation of a
Document. A migration, lifecycle enum, Eloquent model, relationships, factory,
creation action, workspace-scoped query and safe API resource were added.
Focused persistence tests cover identity, ownership, provenance, lifecycle,
constraints, scoping and clean migration behaviour.

The implementation follows the existing repository's Actions/Queries
convention without introducing a broader architectural layer.
`CreateDocument` creates only an `uploading` relational record. It generates
the public UUID and storage key on the server, but deliberately performs no
storage or queue operation.

## Persistence model

The `documents` table stores:

* an internal primary key and immutable unique public UUID;
* mandatory `workspace_id` ownership;
* separate `created_by_user_id` provenance;
* the ADR-0007 lifecycle status, defaulting to `uploading`;
* intrinsic source filename, media type and byte size;
* a unique, server-controlled storage key;
* nullable failure category and failure message;
* timestamps.

The storage key is derived from the workspace and document public UUIDs, never
from the user-provided filename. Foreign keys restrict deletion of referenced
workspaces and creators. PostgreSQL constraints reject negative sizes and
require useful diagnostic values when a document is in `failed`.

## Decisions and boundaries

No new architecture decision was made and ADR-0007 was not changed.

The model treats the workspace foreign key as ownership and the creator
foreign key only as provenance. Both, along with the public UUID, are guarded
against mutation after creation. A workspace-scoped query demonstrates the
required fail-closed lookup boundary without adding a controller or policy.

No seeder was added. An active seeded Document without authoritative source
content would contradict ADR-0007, while source-content creation belongs to a
later stage.

Lifecycle transition validation was not introduced because this session
defines persistence, not the future upload, processing, retry or deletion
operations that own those transitions.

## Verification performed

* `make up`
* `make format-api`
* `make migrate`
* Focused `DocumentPersistenceTest`: 17 passed (45 assertions).
* Focused `DocumentStatusTest`: 1 passed (1 assertion).
* `make format-check-api lint-api test-api`
* `make format-check lint typecheck test ps`
  * Laravel: 54 passed (165 assertions).
  * Web: 4 passed across 2 test files.
  * AI: 1 passed.
  * Pint, ESLint, Ruff formatting/linting, TypeScript and mypy passed.
  * Every Docker Compose service reported healthy.
* A temporary PostgreSQL database was migrated from zero, inspected for
  document columns/indexes/foreign keys/check constraints, rolled back,
  migrated again and dropped.
* `git diff --check`

## Problems or corrections

The brief named `docs/IMPLEMENTATION_GUIDE.md`, but this repository has only
the canonical root `IMPLEMENTATION_GUIDE.md`; the root authority was used.

During implementation review, explicit immutability guards and negative tests
were added for the public UUID, workspace ownership and creator provenance.
No architecture question or contradiction requiring a stop was found.

## Next steps / important takeaways

* The implementation was approved, committed and tagged at the R08-S02
  boundary; the tracker now points to R08-S03.
* Stage 8.3 will own direct-upload behaviour and must not treat the existence
  of this relational record as proof that source content exists.
* Later lifecycle operations must enforce ADR-0007 transitions explicitly;
  this stage intentionally provides the states without inventing those
  workflows.
