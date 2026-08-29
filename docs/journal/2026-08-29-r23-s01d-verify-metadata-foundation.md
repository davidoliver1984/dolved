# R23-S01d — Verify metadata foundation

## Outcome

ADR-0030's document metadata foundation passed its provider-free acceptance
matrix. Stage 23.1 is complete.

The acceptance coverage proves:

- workspace members can read metadata while only owners/administrators mutate;
- cross-workspace families, categories, tags and owner identities remain
  concealed;
- owner selection is checked live across all four combinations of membership
  presence and account enablement;
- a recorded owner's later membership removal and account disablement do not
  alter document authority or retrieval eligibility;
- title, description, category, tags, owner and review date do not enter the
  eligibility resolver or change its resolved document identities;
- category/tag normalization and workspace uniqueness remain enforced;
- two contending 19-plus-1 tag sets each acquire the family-row lock and leave
  exactly 20 final assignments;
- inconsistent pending, verified and unavailable checksum shapes are rejected
  by PostgreSQL;
- existing streamed pending-to-verified and backfill pending-to-unavailable
  transitions remain covered.

## Verification

- Focused SQLite suite: 50 passed, 1 PostgreSQL-only test skipped,
  248 assertions.
- Rollback-only PostgreSQL checksum check: 4 of 4 inconsistent shapes rejected.
- Rollback-only PostgreSQL tag serialization: 2 family-row `FOR UPDATE` locks;
  final assignment count 20.
- Full Laravel suite: 381 passed, 3 skipped, 2,027 assertions.
- Laravel Pint: 485 files passed.
- No test or acceptance check changed retained application data.

No provider calls were made. Retrieval, planner, generation, threshold,
calibration, benchmark, held-out, temporal authority and organisation-owned
applicability behavior were unchanged.

## Next

R23-S02a may implement ADR-0031's version-governance API foundation that does
not depend on ADR-0032 structured extraction.
