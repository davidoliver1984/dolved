# Session Journal: R07-S03 — Add Workspace-Aware Web Experience

## Date

2026-07-28

## Session mode

Implementation against accepted ADR 0006. No tenancy architecture was changed.

## What happened

This session added the first workspace-aware application flow. Authenticated,
verified users can list their assigned workspaces, open one using its immutable
public UUID and switch between memberships from the Next.js interface.

The implementation began only after the Stage 7.3 objective was aligned with
ADR 0006. Ordinary users cannot create or administer workspaces in this stage.

## Implementation

Laravel now has thin list and detail endpoints backed by explicit workspace query
classes. Both queries begin with the authenticated user's memberships. The detail
query resolves the requested public UUID inside that membership boundary and returns
`404` when the user is not assigned.

An API resource exposes only the workspace public UUID, name, slug and current
user's role. Internal database identifiers are not part of the response.

Next.js uses `/app/workspaces/{workspacePublicId}` as explicit navigation context.
The workspace selector contains only server-returned memberships, marks the active
workspace and reloads the selected workspace from Laravel on navigation. The URL is
a requested context, not an authorisation decision.

The root `/app` page redirects users with memberships to their first assigned
workspace and shows a clear empty state to users without memberships.

## Development seed data

The deterministic development fixture contains two synthetic users and two
workspaces:

- the primary user owns Atlas Research and is a Beacon Operations admin;
- the secondary user owns Beacon Operations and is an Atlas Research member.

`CreateWorkspace` creates each workspace and its owner atomically when absent.
Additional memberships use `updateOrCreate`. Running `make seed` twice produced two
fixture users, two workspaces, four memberships and exactly one owner per workspace.

## Verification performed

Focused verification:

```bash
docker compose exec -T api php artisan test --filter=WorkspaceAccessTest
docker compose exec -T web npx vitest run \
  src/components/WorkspaceSwitcher.test.tsx
make typecheck-web
```

Result:

```text
WorkspaceAccessTest: 6 tests / 22 assertions passed
WorkspaceSwitcher:   1 test passed
TypeScript:          passed
```

Development command verification:

```bash
make seed
make seed
```

The repeated command retained exactly two fixture users, two workspaces, four
memberships and one owner per workspace.

Repository boundary:

```bash
make format-check lint typecheck test ps
```

Result:

```text
Web: ESLint and TypeScript passed; 4 Vitest tests passed
API: Pint passed; 36 Laravel tests / 119 assertions passed
AI: Ruff format/lint and MyPy passed; 1 Pytest test passed
All six Compose services healthy
```

Manual browser verification proved login, Atlas-to-Beacon switching, updated active
workspace content and a generic 404 for an unassigned public UUID.

The platform was then stopped with `make down`; persistent volumes were retained.

## Problems and corrections

The first seeder test expected raw strings from a role query, but the
`WorkspaceMembership` cast correctly returned enum instances. The assertion was
updated to compare enum values.

The switcher initially exposed concatenated accessible text for workspace name and
role. An explicit accessible label made the link clearer and gave the component test
a stable semantic contract.

The complete API test suite initially removed the local seed rows before browser
verification. Investigation showed that Docker's process environment overrode the
`<env>` values in `phpunit.xml`; the tests were using development PostgreSQL rather
than in-memory SQLite. The test configuration now uses forced `<server>` values,
which Laravel reads before Docker's environment. A regression assertion confirms the
`sqlite`/`:memory:` connection, and a PostgreSQL fixture marker survived both the
focused and complete API suites. The normal seeder was rerun, the synthetic password
was verified through Laravel and the full browser flow passed.

## Deferred

This session did not implement workspace creation UI, membership administration,
invitations, ownership transfer, role administration, tenant-owned documents,
PostgreSQL RLS, S3/SQS propagation, Python changes or Qdrant changes.

## Important takeaways

- A public UUID in the route makes tenant context explicit without exposing an
  internal database key.
- A route value requests a workspace; only a membership-scoped server query grants
  access.
- Returning `404` for inaccessible workspaces avoids revealing their existence.
- URL-based switching is transparent and refreshable while avoiding a hidden global
  current-workspace singleton.
- Deterministic, repeatable seed data makes multi-workspace behaviour practical to
  test locally.
- Test configuration must override container process variables explicitly; otherwise
  an apparently isolated `RefreshDatabase` suite can destroy development data.

## Commit status

The implementation and verification were approved for the Phase 7 boundary commit
and annotated `phase-7` tag.
