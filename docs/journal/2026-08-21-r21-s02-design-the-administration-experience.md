# R21-S02 — Design the Administration Experience

Date: 2026-08-21  
Status: Completed

## Outcome

The Phase 19 administration capabilities now have a route-backed interface
that uses the shared R21-S01 design language and states authority truthfully.
People and invitation administration are separate destinations, document
lifecycle state is legible, and workspace usage distinguishes provider-
reported, estimated, local zero-cost and unavailable values.

## Capability and safety boundary

The interface continues to treat Laravel's capability objects as authoritative.
Owners can manage administrators and transfer ownership; administrators are
shown that owner and administrator accounts remain owner-managed. Ordinary
members receive no administration navigation or route access.

Document deletion, member removal, role changes, ownership transfer and
workspace departure now use accessible confirmation dialogs. Deletion copy
states that cleanup is asynchronous and that evidence already preserved with
completed conversations remains available.

Invitation validity is separate from delivery status. The one-time invitation
link is described as non-replayable, and pending, accepted, revoked and expired
records have distinct states. Usage never converts unavailable pricing or token
data into zero.

## Visual review

`/design-system/administration` provides representative, development/test-only
document, people, invitation and usage states. It is a component review surface;
the authenticated product routes remain inside ADR-0027's persistent desktop
left sidebar and mobile drawer. The product owner accepted the information
architecture and asked to reconsider destructive-button padding in the final
in-context Stage 21.4 visual pass.

## Verification

* Vitest: 24 files, 72 tests passed.
* ESLint: passed.
* TypeScript (`tsc --noEmit`): passed.
* Next.js production build: passed.
* Live browser inspection: document confirmation and representative admin
  states verified.
* `git diff --check`: passed.

No planner, retrieval, threshold, calibration, benchmark, tenancy or backend
application behaviour changed.
