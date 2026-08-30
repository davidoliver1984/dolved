# R24-S04 — Source viewing and extracted text

**Date:** 2026-08-30
**Status:** Completed

## Outcome

Every available indexed version now links to an authorised application source
viewer and a bounded structured extracted-text view. Neither surface exposes an
object-storage URL or reconstructs source content from retrieval chunks.

## Application boundary

- Added a same-origin web proxy over ADR-0032's existing tenant-authorised
  source-delivery endpoint, preserving byte-range and safe response headers.
- Added inline viewing for browser-safe formats and a truthful unavailable state
  for formats that require an authorised download.
- Added a cursor-paginated projection reader for ordered headings, paragraphs,
  tables and extraction warnings.
- Kept deleted, unavailable and non-indexed source entry points absent rather
  than presenting dead controls.

## Review and verification

David reviewed and approved the live source and extracted-text routes on
2026-08-30. The full web suite passed with 30 files / 129 tests; ESLint,
TypeScript and `git diff --check` also passed. No provider calls were made.

## Next

R24-S05 builds fail-closed version comparison and stops at its mandatory visual
review checkpoint.
