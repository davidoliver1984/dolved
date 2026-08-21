# R21-S05 — Split Platform Operations into Route-Backed Sections

Date: 2026-08-21
Status: Completed

## Outcome

Accepted ADR-0028 is implemented as four independently addressable Platform
Operations destinations: Overview, Active alerts, Global telemetry and
Operational policy. The shared adaptive shell now presents a route-derived
platform contextual-navigation region while retaining one stable,
capability-gated primary destination.

The implementation preserves ADR-0026 operational ownership. Laravel remains
the platform-administrator authority, concealed denial maps to `404`, login
return accepts only the exact four platform routes and policy mutation fails
closed if authority is lost after page load. The overview contains summaries
and links; complete alert, telemetry and policy information remains on its
owning route.

The final gate also completed the five ADR-0027 conformance corrections found
by independent review. Live product surfaces no longer consume the withdrawn
identity tokens. Laravel now constructs and authorises citation presentation,
including safe document metadata and an optional source route. Historical
evidence remains after deletion without exposing a dead, inaccessible,
cross-workspace or unsupported source. Conversation deep links are validated
server-side, typed timeout/retraction/failure states remain distinct and chat
uses one bounded live-status region instead of announcing transcript growth.

No retrieval, planner, threshold, calibration, benchmark or provider behaviour
changed.

## Verification

* Web: 28 files / 115 tests passed.
* Laravel: 332 passed / 2 skipped / 1,634 assertions.
* Python: 562 passed / 4 skipped.
* Focused conversation/citation tests: 10 passed / 96 assertions.
* ESLint, TypeScript, production Next.js build, Pint, Ruff format/lint and Mypy
  passed.
* The pinned Collector component/configuration guard passed.
* Repository sweeps found no withdrawn production token consumer, raw
  provenance dump or transcript-wide live region.
* `git diff --check` passed.
* Direct visual review covered all four Platform Operations routes, desktop
  and 390-pixel mobile layouts, both themes, mobile drawer dismissal and
  tenant-safe invalid-conversation presentation without browser errors.

Claude's final independent gate review reported no new problems and confirmed
that the implementation is conformant. Phase 21 can therefore close and the
tracker can advance to R22-S01.
