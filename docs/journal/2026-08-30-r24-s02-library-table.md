# R24-S02 — Library table and activity-summary projection

**Date:** 2026-08-30
**Status:** Completed

## Outcome

The workspace Library now uses a dedicated family-level projection and API. It
shows one row per document family, resolves the current attained authority state
without resurrecting a predecessor, and supports deterministic search, filters,
sorting, pagination and an explicit historical-only opt-in.

The existing technical ingestion controls remain separate and intentionally use
the older per-document administration endpoint. They are not a duplicate source
of truth for the Library: they expose version-level operational controls pending
their later ADR-0033 destinations.

## Application boundary

- Added `document_family_activity_summaries` and live maintenance from document,
  governance, ingestion and extraction producer paths.
- Added an exact, idempotent rebuild command derived from authoritative records.
- Added the tenant-authorised document-library query, request, resource,
  controller and route.
- Added the responsive, real-data web table and its loading, empty, error,
  filtering, sorting, pagination and historical states.
- Preserved current authority, applicability, tenancy and no-predecessor-
  resurrection behavior.

## Review

David reviewed the live workspace Library in the browser on 2026-08-30 and
approved the table. The family-level Library versus version-level technical
ingestion distinction was also reviewed and accepted.

## Verification

- Focused Laravel metadata/library/governance/temporal suite: 38 passed, 1
  skipped, 171 assertions.
- Web suite: 30 files, 129 tests passed.
- Pint, ESLint, TypeScript and `git diff --check`: passed.
- Broad Laravel suite: 377 passed, 3 skipped; remaining failures were confined
  to pre-existing missing evaluation inputs/contracts and document-storage
  configuration, not the R24-S02 surface.
- No external provider calls were made.

## Next

R24-S03 builds family detail and version history as a separate reviewed boundary
and stops at its mandatory visual-review checkpoint.
