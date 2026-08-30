# R24-S09 / R24-GATE — Deleted history and knowledge-library closure

**Date:** 2026-08-30
**Status:** Complete

## Delivered boundary

R24-S09 presents completed ADR-0031 document-family deletion tombstones through
a tenant-scoped owner/administrator API and route. It preserves the deletion
operation identity, completion time, requester, removed-version count and
governance audit reference. Where historical evidence contains no deletion
reason, the interface says so rather than manufacturing one. Ordinary members
do not receive the navigation item and remain forbidden at the API boundary.

The Stage 24.5 design handover is also complete. Version comparison now uses a
bounded deterministic section-aware alignment instead of equal ordinal
pairing. It classifies additions, removals, modifications and moved content,
reports change counts and distinguishes reliable, partial and unavailable
alignment. Word-level marks are derived only after the backend has aligned a
modified pair. Formatting-only changes remain explicitly unavailable because
the accepted ADR-0032 projection does not retain those signals.

## Visual checkpoint

Development-only fixtures use the production comparison and deleted-history
components without claiming workspace evidence. David reviewed and approved
both on 2026-08-30. The comparison supports side-by-side and inline modes,
filters, collapsed unchanged context, change navigation and responsive cards.

This approval covers the implemented Phase 24 knowledge-library surfaces. It
does not represent final visual or functional acceptance of Phase 25 import,
staging, matching, review, recovery or promotion.

## Verification

- focused Laravel comparison, tombstone and governance regression: 13 tests,
  89 assertions;
- complete Laravel suite: 458 passed, 3 skipped, 2,490 assertions;
- complete web suite: 36 files, 142 tests;
- Laravel Pint, web ESLint and TypeScript: passed;
- live development fixtures rendered without console errors and their layout,
  filter and mode interactions were exercised;
- `tasks.json` JSON validation and `git diff --check`: passed;
- no providers called.

The production web build reached only the external Google Fonts fetch and was
unable to complete in the restricted network environment; TypeScript, lint and
the complete web test suite were clean.

## Corrected dependency boundary

R24-GATE accepts the independent ADR-0033 library surfaces. The complete
nine-step import-through-grounded-answer Playwright journey was not executed,
skipped or passed. It remains unchanged and mandatory at R25-S07 after the real
ADR-0034 ImportBatch workflow exists. The legacy direct-upload path was not
used as a substitute.

## Next

R25-S01 may begin the ADR-0034 import domain, schema and staging-privacy
boundary. Phase 25 cannot close until the deferred journey passes through the
real import workflow.
