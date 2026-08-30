# R24-S06 — Saved views and category settings

Date: 2026-08-30

## Delivered boundary

R24-S06 adds the complete ADR-0033 saved-view lifecycle. A saved view belongs
to one user and workspace, has a stable public UUID and normalized unique name,
and stores only a bounded, versioned definition made from the Library's
enumerated query fields. Unknown fields and values fail on write. If a stored
definition later contains an unsupported field, opening it drops that field
with a visible notice. Opening always reruns the definition against current
Library data; no result membership or governance authority is stored.

Only the owning user can view, rename or delete a saved view. Same-workspace
cross-user and cross-workspace identifiers are concealed. Views remain inert
for a disabled account and are removed when the owning workspace membership
ends. Create, rename and delete are safely audited.

The Categories route now presents active and archived categories separately.
Workspace owners and administrators can create, rename and archive categories;
members can inspect the catalogue without mutation controls. Create, rename
and archive are audited. Archived categories remain available for historical
display but are already excluded by the existing family-assignment query.
Tags remain deliberately freeform with no settings surface.

## Visual checkpoint

David reviewed and approved the live Knowledge Library saved-view panel and
the route-backed Categories settings surface on 2026-08-30.

## Verification

- focused saved-view, metadata and membership-administration regression: 20
  Laravel tests, 133 assertions;
- complete web suite: 33 files, 134 tests;
- Laravel Pint, web ESLint and TypeScript: passed;
- `tasks.json` JSON validation and `git diff --check`: passed;
- local schema migration applied successfully;
- no providers called.
