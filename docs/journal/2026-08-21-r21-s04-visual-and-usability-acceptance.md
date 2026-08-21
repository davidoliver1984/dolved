# R21-S04 — Visual and Usability Acceptance

Date: 2026-08-21
Status: Completed

## Outcome

The product experience was reviewed against the running application and real
workspace data rather than static mockups. Desktop and mobile walkthroughs
covered authentication, chat, document upload/administration, workspace
administration, usage and Platform Operations in light and dark themes.

The review corrected mobile navigation dismissal, active-state hierarchy,
brand-surface contrast, account sign-out access, invitation-link presentation,
recent-conversation spacing, compact destructive actions and Platform
Operations information hierarchy. Mail and authentication branding now use
Dolved consistently. A live hydration error exposed by the document library
was traced to host-dependent date formatting; shared UTC formatting now makes
all affected server/client timestamps deterministic.

The final destructive-action treatment keeps semantic buttons, confirmation
flows, keyboard/focus behaviour and 44-pixel interaction targets while using a
smaller visual surface and optical text size. No application authority,
retrieval, planner, threshold, calibration or benchmark behaviour changed.

## Verification

* Running application reviewed with real data at representative desktop and
  mobile sizes.
* Shared UI regression tests passed.
* ESLint passed.
* TypeScript (`tsc --noEmit`) passed.
* Next.js production build passed.
* `git diff --check` passed.

The accepted ADR-0028 defines the follow-on R21-S05 boundary for splitting
Platform Operations into route-backed sections. Its implementation was not
started during R21-S04.
