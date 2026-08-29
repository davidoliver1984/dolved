# R23-S01a — Build metadata migration and domain model

**Date:** 2026-08-29
**Status:** Complete

## What changed

R23-S01a implemented only ADR-0030's persistence and domain-model foundation.
Workspace-scoped document categories and tags now have stable public identities,
canonical display-name normalization and database-backed uniqueness. Document
families gained description, category, recorded owner, review-due date and tag
relationships. New document families record the creating user as owner without
granting that metadata any authority over retrieval, applicability or version
status.

Version-scoped source metadata now includes publisher label, source URL and an
honestly stateful checksum identity: `pending`, `verified` or `unavailable` with
a bounded unavailable reason. PostgreSQL checks enforce valid checksum shapes,
and verified checksum identity is immutable. Existing immutable source guards
also cover publisher label and source URL.

The governance audit model now binds every event to its document family, permits
family- or version-scoped targets, and records an exact human-or-system actor
shape. PostgreSQL composite foreign keys prevent a version event from naming a
document in the wrong family or workspace. Existing audit events are migrated
as truthful human, version-scoped events before the final constraints are
installed.

R23-S01b policies, mutation actions, resources, streamed upload checksum
verification and tag-limit locking were not started. Legacy checksum, owner and
metadata backfill remains allocated to R23-S01c.

## Verification

- The migration completed against the local PostgreSQL runtime.
- PostgreSQL catalog inspection confirmed the checksum, category, audit-shape,
  actor-shape and composite tenancy constraints.
- New metadata foundation tests: 8 passed, 24 assertions.
- Focused document regression suites: 61 passed, 257 assertions.
- Full Laravel suite: 365 passed, 2 skipped, 1,930 assertions.
- Laravel Pint: 463 files passed.
- `git diff --check`: passed.

No provider calls were made. Retrieval, planner, generation, threshold,
calibration, benchmark and held-out behaviour were unchanged.

## Next

R23-S01b may implement the authorised metadata read and mutation boundary on
top of this committed persistence foundation.
