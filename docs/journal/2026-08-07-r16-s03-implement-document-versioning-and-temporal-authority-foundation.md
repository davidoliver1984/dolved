# Session Journal: R16-S03 — Implement Document Versioning and Temporal Authority Foundation

## Date

2026-08-07

## What was implemented

ADR-0017 now has its Laravel and PostgreSQL foundation. Every Document belongs
to a stable, workspace-owned DocumentFamily and participates in one explicit,
immutable linear lineage. New uploads create a family, first version and sealed
applicability snapshot atomically; successor creation retains the family and
copies the preceding version's applicability unless a deliberate replacement is
provided.

Governance is represented by DRAFT, APPROVED and WITHDRAWN together with the
independent effective, approval and withdrawal timestamps accepted by ADR-0017.
Application actions govern approval, withdrawal and rescheduling under database
transactions and locks. A query-time authority resolver derives CURRENT and
VALID_AT_DATE from those facts, including late approval, scheduled versions,
cancellation before attainment, lineage monotonicity and half-open authority
windows. No current flag or scheduled transition was introduced.

Ordinary governance is available to workspace owners and administrators. The
historical correction path is intentionally narrower: it is owner-only, requires
an explicit reason and records a distinct audit event with previous and corrected
values. Organisational applicability uses one generic, workspace-scoped adjacency
list with aliases and arbitrary depth. Each Document owns a sealed snapshot;
mutable family defaults are only a convenience for creating future versions.

## Verification evidence

* The focused versioning suite passed 11 scenarios; the full Laravel suite passed
  166 tests with 707 assertions.
* Repository lint, formatting, TypeScript and Python type checks passed.
* Frontend tests passed 26 tests using one Vitest worker. Python passed 198 tests;
  one credential-dependent live Voyage test was skipped as designed.
* A newly created PostgreSQL database migrated cleanly through migration 000012.
* A synthetic legacy Document was backfilled with a family, DRAFT governance,
  `effective_from = created_at`, and a sealed universal applicability snapshot.
* PostgreSQL inspection confirmed the one-root, one-successor, approved-effective-
  date and authority-start indexes.
* All health-checked Compose services were healthy and `git diff --check` passed.

## Problems and corrections

The initial aggregate `make test` run exhausted the web container's memory and
ended with exit 137. After restarting the service, the unchanged frontend suite
passed with one worker; API and AI suites then passed separately.

A second `migrate:fresh` against one reused temporary PostgreSQL database exposed
that older Phase 14 migrations leave database functions after dropping tables.
This occurs before R16-S03 is reached and is not caused by this migration. The
required clean-database path passed against a genuinely new database, so the
already-shared historical migrations were not edited during this stage.

## Architectural boundary held

ADR-0017 was implemented without amendment and ADR-0018 was not reinterpreted.
No retrieval planner, eligibility resolver, synchronous retrieval protocol,
Qdrant search, Python retrieval implementation, controllers or routes were added.
Those remain Stage 16.4 work.

## Commit boundary

Approved commit: `Implement document versioning and temporal authority foundation`

Approved annotated tag: `phase-16-s03`
