# R25-S02 — Import preflight contract and lease reconciliation

**Date:** 2026-08-31
**Status:** Complete

## Delivered boundary

R25-S02 implements ADR-0034's asynchronous import-preflight boundary through
the existing outbox and SQS worker topology. Laravel creates an immutable
`ImportPreflightAttempt` and matching `import.preflight.requested` outbox row in
one transaction. The event binds the public workspace, batch and item
identities, exact staged-object key, short-lived read URL, declared media type,
event identity, lease token and lease generation.

Python consumes only that new event type and reads only the exact signed URL
supplied by Laravel. Its provider-free inspection reports one bounded result:
readable, password protected, encrypted, corrupt structure or MIME mismatch.
Only a readable result contains the verified SHA-256, detected media type and
byte size. Python produces no extraction artefact, chunks, vectors or provider
calls.

## Callback and recovery authority

Complete and fail callbacks use separate versioned JSON contracts and the
existing worker HMAC protocol with exact purpose strings. Laravel validates
the signed body, locks the attempt, and requires the event, workspace, item,
staged key, lease token and lease generation to match. Identical terminal
replays are idempotent. Conflicting, stale, expired or cross-identity callbacks
fail closed without changing the item.

PostgreSQL enforces:

- one immutable event identity and one generation per item;
- at most one open attempt per item;
- exact status/result/diagnostic shapes;
- immutable attempt and lease identity;
- immutable terminal observations;
- outbox subject separation between document and import-item events.

Laravel alone rejects empty or oversized staged objects and maps technical
preflight facts to the item state. A scheduled reconciler terminalises an
expired attempt before creating a successor generation. If the successor
cannot be dispatched temporarily, the latest expired attempt remains
discoverable and a later reconciliation pass can create the successor; no item
is stranded between those steps.

## Verification

- focused Laravel preflight boundary: 8 tests, 40 assertions;
- focused Python import-preflight and worker routing: 15 tests passed;
- complete Laravel suite: 480 passed, 3 skipped, 2,581 assertions;
- complete Python suite: 654 passed, 4 skipped;
- PostgreSQL 18 migration applied and catalog inspection confirmed the five
  required constraints and both generation/open-attempt unique indexes;
- all four new Draft 2020-12 JSON schemas validated;
- Ruff lint and format, Mypy, Laravel Pint and `git diff --check`: passed;
- no providers called.

The local migration-status command emitted the known unavailable local
OpenTelemetry collector export warning after completing successfully; it did
not affect schema application or verification.

## Excluded

No deterministic matching, checksum serialization, promotion, legacy cutover
or import UI was implemented. Those remain R25-S03 through R25-S06. No
calibration, retrieval, planner, threshold or held-out behavior changed.

## Next

R25-S03 may implement ADR-0034's deterministic exact-duplicate and bounded
family-title matching rules, including the shared workspace/checksum
serialization boundary, without beginning promotion.
