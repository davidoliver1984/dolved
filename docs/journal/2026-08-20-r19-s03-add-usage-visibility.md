# R19-S03 — Add Usage Visibility

**Date:** 2026-08-20
**Status:** Completed; Phase 19 gate pending independent review
**Architecture:** ADR-0025 (Accepted)

## What changed

Dolved now has a tenant-scoped usage record and an initial owner/admin-only
visibility surface. Laravel owns current gauges, historical interval
aggregation, authorization and presentation semantics. Python records
provider-native usage and deterministic estimates only at the boundaries where
that information exists.

The persistence model deliberately separates current state from historical
activity. Active documents, logical uploaded source bytes and indexed chunks
are calculated as present-tense gauges. Content-free activity and usage events
survive normal source deletion so bounded operational history does not silently
shrink with the live domain tables.

Usage observations distinguish provider-reported cost, configured estimates,
unavailable pricing and genuinely local zero-cost execution. Missing tokens or
pricing remain null rather than becoming misleading zeroes. The web surface
offers 7-day, 30-day and current-month ranges, shows its freshness timestamp and
labels estimates as non-billing-grade.

## Adjacent corrections found during live review

The visual review exposed legacy branding from another project in active
configuration, mail and telemetry surfaces. Active Dolved-owned surfaces were
corrected and a regression guard now prevents recurrence while explicitly
allowing immutable historical identifiers.

The same review found two authentication-boundary defects. Signed verification
links could render a raw JSON authentication failure instead of preserving the
intended return through login. Separately, a stale login or registration page
could submit while already authenticated, causing Laravel's generic guest
middleware to redirect an API request into HTML and the browser to report an
incorrect connectivity failure. The corrected boundary retains strict session
and verification semantics while returning typed JSON for API conflicts and
routing existing sessions into the application.

## Verification

- Focused workspace-usage and authentication tests passed (19 tests, 79
  assertions), alongside 206 focused Python tests and 49 web tests.
- The broad Laravel and Python suites passed 303 and 545 tests respectively;
  their remaining failures were confined to historical evaluation tests whose
  isolated engineering files are intentionally absent from this runtime.
- Pint, Ruff, Mypy, ESLint, TypeScript and the production web build passed.
- Live browser smoke tests covered registration validation, verification,
  login, authenticated stale-page handling, workspace loading and logout.
- `git diff --check` passed.
- No provider calls were made.
- Retrieval, planner, threshold, calibration, benchmark and held-out behaviour
  did not change.

## Review note

The initial usage and membership controls remain embedded below the workspace
document list. Product-level navigation and visual information architecture are
deliberately deferred to the planned dedicated UI-design phase; this stage
closes the authoritative usage contract and functional surface only.

## Next

Independent review of the Phase 19 administration acceptance gate.
