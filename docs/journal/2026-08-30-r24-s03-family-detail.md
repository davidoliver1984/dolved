# R24-S03 — Family detail and version history

**Date:** 2026-08-30
**Status:** Completed

## Outcome

The workspace Library now has a tenant-authorised family detail experience. It
presents family-owned metadata separately from immutable version history,
resolves the attained current authority through the accepted temporal model and
exposes only the governance operations Laravel authorises for the signed-in
user.

## Application boundary

- Extended the existing family metadata and version-history APIs with current
  authority, extraction state, location choices and server-owned capabilities.
- Added the responsive family summary, metadata editor, governance actions and
  complete version-history presentation.
- Kept metadata ownership, temporal authority, applicability and governance
  validation in Laravel; the web client renders capabilities and typed errors.
- Preserved tenant concealment, immutable version identity, no predecessor
  resurrection and the existing governance command paths.

## Review

David reviewed the live family detail presentation on 2026-08-30. A suitable
indexed draft was approved through the normal governance action and independently
verified as the family's current authority, allowing the approved/current state
to be reviewed truthfully. David approved the visual boundary.

## Verification

- Focused Laravel metadata and governance suite: 12 tests, 87 assertions.
- Web suite: 30 files, 129 tests passed.
- Pint, ESLint, TypeScript and `git diff --check`: passed.
- No external provider calls were made.

## Next

R24-S04 builds source viewing and extracted-text presentation as a separate
reviewed boundary and stops at its mandatory visual-review checkpoint.
