# R26-S01 — PostgreSQL runtime roles

Date: 31 August 2026
Status: Complete

## Outcome

The PostgreSQL privilege foundation required by ADR-0035 and ADR-0036 is now
active in local and isolated E2E environments. Application schema objects are
owned by the non-login `rag_platform_owner`; migrations connect through the
one-shot, `NOINHERIT` `rag_platform_migrator` and explicitly assume the owner
role; long-running Laravel services connect only as the `NOINHERIT`
`rag_platform_app` runtime role.

## Implemented boundary

- Added an idempotent PostgreSQL 18 bootstrap that creates and reconciles the
  three protected roles, their exact attributes and the sole
  migrator-to-owner membership.
- Revoked public database connection and schema creation, assigned database,
  schema and application-object ownership to `rag_platform_owner`, and granted
  only runtime connection, schema usage, table DML and sequence usage/read.
- Reconciled owner-scoped default table and sequence privileges and globally
  revoked default public function execution for future owner-created
  functions.
- Added a default Compose bootstrap dependency and a tools-profile migrator;
  API, publisher and conversation worker now expose only the runtime database
  credential.
- Updated normal, E2E and current-retrieval migration entry points to use the
  one-shot migrator instead of an application container.
- Added static Compose credential-isolation checks and live catalog,
  ownership, effective-privilege and future-object probes.

## Fail-closed evidence

The live verification proves:

- exact protected-role attributes and membership graph;
- database, schema, table, sequence and function ownership;
- public database connection and public application-function execution are
  absent;
- runtime table DML succeeds while runtime table creation, owner-role
  assumption and execution of a newly created private function fail;
- a newly owner-created table and sequence automatically grant the intended
  runtime privileges;
- the migrator assumes `rag_platform_owner` and Laravel migration status is
  readable through both the migrator and runtime application boundaries.

Temporary verification objects are removed on success and through an exit
trap on failure.

## Verification

- Base, tools-profile and E2E Compose configurations validated.
- PostgreSQL role-topology test passed.
- Live PostgreSQL runtime-role verification passed against the retained local
  database, including repeatable bootstrap execution.
- Pint passed for 657 files.
- Complete Laravel suite passed.
- Fresh isolated E2E database created every migration through the dedicated
  migrator and completed both deterministic browser journeys in 1.5 minutes.
- The isolated E2E resources and volumes were removed after success.
- Shell syntax and `git diff --check` passed.

No external provider was called. Retrieval, planner, threshold, calibration,
held-out, benchmark and product semantics were not changed. Unrelated local
notes, drafts, assets and duplicate historical-evaluation files remain
untouched.

## Next

R26-S02 may now implement ADR-0035's bulk domain, constrained schema, frozen
membership and provider-free APIs. Its protected migrations must continue to
run only through the established migrator/owner boundary.
