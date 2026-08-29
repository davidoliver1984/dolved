# R24-S01 — Library navigation shell

## Outcome

The ADR-0033 contextual knowledge-library navigation and route-scaffolding
boundary is complete and visually approved.

The workspace shell now provides route-backed destinations for Library,
Scheduled, Needs attention, Deleted history and Categories. Segment-aware
matching keeps exactly one contextual destination active for document-family,
version and saved-view descendants. Imports and export remain absent because
their owning ADR stages have not begun.

Workspace and document-family identities are independently authorised at each
route boundary. Missing, deleted or cross-workspace family identities fail
closed. Saved-view scaffolding also reauthorises the workspace but returns a
truthful not-found state until the saved-view domain is implemented in
R24-S06.

## Visual review

David reviewed and approved the staged interface on 2026-08-29.

The browser pass covered:

- desktop and mobile layouts;
- light and dark themes;
- direct routes and deep-link reloads;
- loading, empty, not-found and error scaffolds;
- exactly one contextual active destination;
- mobile navigation dismissal after route selection;
- absence of horizontal overflow and browser-console errors.

The empty Scheduled and Needs attention views are intentionally truthful. Their
data-backed controls belong to R24-S02 and later owning sessions and were not
mocked into this shell boundary.

## Verification

- Full web suite: 29 test files, 127 tests passed.
- Focused AppShell suite: 14 tests passed.
- Focused family-route suite: 4 tests passed.
- ESLint: passed.
- TypeScript: passed.
- `git diff --check`: passed.
- No providers were called.

## Next

R24-S02 may now implement the authoritative library table and activity-summary
projection. It retains its own mandatory visual-review boundary.
