# R21-S03 — Implement Complete Interface States

Date: 2026-08-21  
Status: Completed

## Outcome

The production web components now represent the non-success states already
present in Dolved's application contracts rather than leaving blank or legacy
fallback regions.

Chat uses the R21-S01 component language for loading, empty, streaming,
grounded answers, controlled/insufficient answers, cancellation, failure and
retry. Durable answer outcomes and unsupported aspects remain visible. A
mid-stream authorization-revocation event clears provisional content, explains
the access loss and disables further submission for the mounted session.

The document upload queue now gives each file a legible waiting, initialising,
uploading, verifying, completed or failed state, with accessible progress and
independent retry. Existing document administration continues to show failed
ingestion and asynchronous deletion truthfully.

Invitation acceptance now presents a bounded safe failure explanation covering
expired, revoked, already-resolved and wrong-account outcomes. Usage surfaces
retain explicit partial and unavailable values and never substitute zero for
unknown provider data.

## Verification

* Vitest: 24 files, 73 tests passed.
* A new regression proves authorization loss disables both composer and send
  action after clearing provisional delivery.
* ESLint: passed.
* TypeScript (`tsc --noEmit`): passed.
* Next.js production build: passed.
* `git diff --check`: passed.

No API contract, backend behaviour, planner, retrieval, threshold, calibration
or benchmark behaviour changed.
