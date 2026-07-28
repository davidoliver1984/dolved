# Session Journal: R07-S02 — Implement Workspaces and Memberships

## Date

2026-07-28

## Session mode

Implementation against accepted ADR 0006. The tenancy architecture was not
redesigned.

## What happened

This session implemented the first relational Workspace foundation in the Laravel
API. Before changing code, the implementation was checked against
`CONTRIBUTING.md`, `CLAUDE.md`, `IMPLEMENTATION_GUIDE.md`,
`PROJECT_ROADMAP.md`, `tasks.json`, ADR 0006 and the R07-S01 journal.

Two stale planning statements were identified:

* Stage 7.2 in the implementation guide still used the pre-ADR names `tenants`,
  `tenant_memberships` and `tenant_invitations`.
* The Stage 7.1 record said RLS would be implemented in Stage 7.2, while the
  approved R07-S02 implementation brief explicitly excluded RLS, policies and
  middleware.

The current brief and accepted ADR resolved the terminology in favour of
`Workspace` and `WorkspaceMembership`. RLS remains accepted architecture but is
not implemented or claimed active; it needs a separately scoped Phase 7 session.

The existing Actions convention was reused for the transactional application
operation. No broad Domain/Application layer, controller, route or policy was
introduced.

## Implementation

The Laravel API now contains:

* a string-backed `WorkspaceRole` enum with `owner`, `admin` and `member`;
* `workspaces` and `workspace_memberships` migrations;
* first-class `Workspace` and `WorkspaceMembership` Eloquent models;
* workspace and membership relationships on `User`;
* role and joined-at casts;
* workspace and membership factories, including owner/admin/member states;
* `CreateWorkspace`, which creates the workspace and its initial owner membership
  in one transaction;
* 14 focused persistence, relationship, transaction and database-constraint tests.

The workspace public UUID and slug are generated server-side. Public UUIDs are
unique in PostgreSQL and cannot be changed through normal Eloquent updates.
Repeated workspace names receive numeric slug suffixes. Creation provenance is
stored in `created_by_user_id`, but ownership authority comes only from the owner
membership.

`WorkspaceMembership` remains a normal Eloquent model rather than a `Pivot`
subclass. Convenience workspace/member collections use `hasManyThrough`, while
the first-class membership relationships remain available for role and lifecycle
logic.

## Database integrity

PostgreSQL now enforces:

* unique workspace public UUIDs;
* unique workspace slugs;
* valid workspace and user foreign keys;
* one membership per workspace/user pair;
* only `owner`, `admin` or `member` role values;
* at most one owner membership per workspace;
* restrictive user deletion while provenance or membership references exist;
* membership cleanup when a workspace is deleted.

The partial unique owner index enforces at most one owner. Atomic creation ensures
every newly created workspace begins with exactly one owner. Ownership transfer,
last-owner removal and distributed workspace deletion remain deferred workflows.

## Verification performed

Focused verification:

```bash
docker compose exec -T api php artisan test --filter=WorkspacePersistenceTest
make format-check-api
make lint-api
make test-api
```

Final results:

```text
WorkspacePersistenceTest: 14 tests / 44 assertions passed
Full Laravel suite:        30 tests / 97 assertions passed
Laravel Pint:              51 files passed
```

The normal local PostgreSQL database accepted both new migrations through
`make migrate`.

An isolated PostgreSQL database named `rag_platform_r07_s02_verify` was created,
migrated from zero, inspected through the PostgreSQL catalogues, rolled back,
checked to confirm both workspace tables were absent, migrated again and removed.
The inspection confirmed the intended role check, foreign keys, unique constraints,
supporting indexes and partial owner index.

After human approval, the full repository boundary ran:

```bash
make format-check lint typecheck test ps
```

Result:

```text
Web: ESLint passed, TypeScript passed, 3 Vitest tests passed
API: Pint passed, 30 Laravel tests / 97 assertions passed
AI: Ruff format/lint passed, MyPy passed, 1 Pytest test passed
All six Compose services healthy
```

The platform was stopped with `make down` afterward. Persistent data was not
deleted.

## Problems or corrections

The first focused test run had 12 passing tests and one failure in a test that
deliberately triggered a restrictive foreign-key violation and then attempted
more assertions. PostgreSQL correctly marks the current transaction aborted after
such a violation. The test was corrected by separating restricted user deletion
and cascading workspace deletion into independent cases. No production
implementation change was required.

The first service-start attempt during the implementation was blocked by sandbox
access to Docker's socket, and the next showed that Docker Desktop was stopped.
Docker Desktop was started, the same repository commands were rerun, and all
verification then completed normally.

## Deferred

The approved session deliberately did not implement:

* controllers or routes;
* workspace selection or switching;
* invitations;
* policies or tenant middleware;
* PostgreSQL RLS or restricted runtime/migration database roles;
* ownership transfer or deletion orchestration;
* business-audit logging;
* S3, SQS, Python or Qdrant tenant propagation;
* frontend changes.

These are not hidden incomplete parts of the persistence code; they require their
own agreed sessions.

## Important takeaways

* A first-class membership model gives role and lifecycle rules a concrete home
  without placing `workspace_id` on global users.
* A transaction protects aggregate creation, while database constraints protect
  integrity regardless of which application path writes the rows.
* A partial unique index is the correct PostgreSQL mechanism for “only one row
  matching this role per workspace.”
* The database can enforce at most one owner, but maintaining at least one owner
  during transfer or deletion requires a later transactional workflow.
* Hardcoding role values in the historical migration prevents future enum changes
  from silently changing clean-database history.
* RLS remains accepted defence-in-depth architecture, but the repository correctly
  does not claim it is active yet.
