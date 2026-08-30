# R24-S07 — Small-corpus onboarding and searchable-count projection

Date: 2026-08-30

## Delivered boundary

R24-S07 adds a tenant-authorised knowledge-readiness projection that reports
the number of distinct document families currently searchable in the
workspace. Searchability uses the same extracted CURRENT-authority query as
the Library: the version must be approved, indexed and attained, must not be
withdrawn, and cannot resurrect once a later approved or withdrawn successor
has attained authority. Location applicability does not inflate or globally
exclude the count because applicability is resolved for each eventual user
question.

The count endpoint deliberately returns only the count. A separate endpoint
returns up to three deterministic starter questions derived from currently
searchable family titles. The corresponding workspace panel keeps the count
visible, distinguishes upload from searchability, links zero-state users to
document upload, links ready users to the exact searchable Library filter and
can place a starter question into the real conversation composer.

The primary explanation collapses readiness into five user-facing stages. A
nested disclosure retains all ten ADR-0033 states, including staged,
unresolved, queued, processing, awaiting approval, technically incomplete,
not-yet-authoritative, searchable and failed states.

## Visual checkpoint

David reviewed and approved the live small-corpus workspace presentation on
2026-08-30. The review workspace truthfully reported one currently searchable
document family and exposed the real starter-question and Library actions.

## Verification

- focused Laravel readiness and Library regression: 12 tests, 52 assertions;
- focused web readiness, workspace and conversation regression: 3 files, 15
  tests;
- Laravel Pint, web ESLint and TypeScript: passed;
- `tasks.json` JSON validation and `git diff --check`: passed;
- no providers called.

## Next boundary

R24-S08 verifies the complete small-corpus transition and interaction journey
with Playwright. It does not change the accepted searchability definition.
