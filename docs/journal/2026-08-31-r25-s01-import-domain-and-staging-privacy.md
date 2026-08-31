# R25-S01 — Import domain and staging privacy

**Date:** 2026-08-31
**Status:** Complete

## Delivered boundary

R25-S01 establishes ADR-0034's import identities without beginning import
execution. The schema contains import batches and items, immutable decision
snapshots, promotion-attempt identities and durable failure observations, plus
the permanent workspace/checksum reservation row consumed later by matching
and promotion.

PostgreSQL structurally binds:

- each item to its batch and workspace;
- each current decision pointer to a snapshot owned by that same item;
- each promotion attempt to both its item/workspace and its own item's
  decision snapshot;
- replacement lineage to the same batch and workspace.

Nullable pre-decision and unreplaced states remain valid. Self replacement,
cross-scope lineage, multiple open attempts, duplicate attempt ordinals and
duplicate actor-scoped idempotency identities fail closed. Human/system actor
identity is a generated database value. Promotion failures are unique per
lease generation and their displayed count is derived from the durable rows.

## Staging acceptance

The existing upload bucket is reused. Its implementation-time verification
proved:

- all four S3 public-access controls are enabled;
- default AES-256 server-side encryption is enabled;
- browser operations are purpose-, content-type-, time- and exact-key scoped;
- keys contain workspace and item public identities, not user filenames;
- cross-workspace or substituted keys fail before filesystem access;
- the application exposes exact-key cleanup and no listing/prefix operation.

The staging disk remains outside retrieval/search. Retention defaults to seven
days through bounded configuration.

## Verification

- focused import domain/staging suite: 13 tests, 46 assertions;
- complete Laravel suite: 471 passed, 3 skipped, 2,536 assertions;
- migration applied successfully to PostgreSQL 18;
- PostgreSQL catalog inspection confirmed all composite foreign keys, checks,
  partial unique index, generated actor expression and update/count triggers;
- rolled-back PostgreSQL runtime proof confirmed generated human actor identity,
  current-snapshot binding and trigger-derived failure count;
- LocalStack provisioning and verification: private access, AES-256, CORS and
  queue/DLQ checks passed;
- Laravel Pint, shell syntax, JSON validation and `git diff --check`: passed;
- no providers called.

## Excluded

R25-S02 owns preflight events, leases and callbacks. R25-S03 owns deterministic
matching and checksum-lock acquisition behavior. R25-S04 owns promotion
execution. Legacy cutover and import UI remain R25-S05 and R25-S06. No legacy
direct-upload path was treated as the new import flow.

## Next

R25-S02 may implement the asynchronous, outbox/worker-consistent preflight
contract and its fail-closed lease reconciliation.
