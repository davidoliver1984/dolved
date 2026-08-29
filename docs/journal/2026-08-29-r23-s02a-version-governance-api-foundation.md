# R23-S02a — Version-governance API foundation

## Outcome

The provider-free ADR-0031 API foundation is complete without consuming or
pre-empting ADR-0032 structured extraction.

Implemented:

- tenant-scoped family version history with applicability lineage;
- approve, withdraw/cancel, reschedule and owner-only historical timestamp
  correction routes;
- coarse controller policy checks plus final action-level authorisation;
- durable governance commands bound by workspace, purpose, idempotency key,
  actor, target and canonical payload digest;
- completed-result replay only after fresh authentication, tenancy and
  authorisation checks;
- stable `idempotency_key_conflict` and `governance_state_conflict` responses;
- PostgreSQL composite target/result-to-workspace foreign keys;
- immutable command identity and terminal result records;
- family-first, ascending-version deterministic locking for every existing
  governance mutation touching family lineage.

The applicability-only successor mutation and clone reuse were not exposed.
They remain bound to R23-S02b after ADR-0032 supplies the canonical extraction
and materialisation identity. No placeholder route or partial mutation was
introduced.

## Verification

- Focused governance and temporal suite: 16 passed, 75 assertions.
- Full Laravel suite: 386 passed, 3 skipped, 2,063 assertions.
- Laravel Pint passed for every changed PHP file.
- The PostgreSQL migration applied successfully and the five intended routes
  were registered behind the authenticated, enabled-account, verified-email
  boundary.
- Cross-workspace target binding, member-read/admin-mutate policy, owner-only
  correction, actor/target/payload idempotency conflicts, per-purpose
  independence, completed replay and typed invalid-state behavior are covered.

No provider calls were made. Retrieval, planner, generation, threshold,
calibration, benchmark, held-out, organisation-owned applicability and
temporal-authority derivation were unchanged.

## Next

R23-S03a may implement ADR-0032's canonical structured-extraction artifact
schema and cross-language digest foundation.
