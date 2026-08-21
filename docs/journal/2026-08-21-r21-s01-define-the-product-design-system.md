# R21-S01 — Define the product design system

Date: 2026-08-21
Outcome: Completed

## What changed

Accepted ADR-0027 now has an executable frontend foundation. The web app uses
Tailwind CSS v4 with repository-owned shadcn/Radix components, Lucide icons,
Source Sans 3 interface typography, the Sora lowercase wordmark and an explicit
dark-default two-state theme. Semantic tokens rather than page-owned colours
now define the shared visual vocabulary.

The authenticated product uses one adaptive route-backed shell. Its desktop
sidebar becomes a mobile drawer; recent conversations, documents,
administration and platform operations are real destinations whose visibility
continues to follow Laravel-owned authority. Canonical conversation URLs now
preserve browser navigation without moving conversation ownership into the
client.

The development/test-only `/design-system` route records shared component and
state patterns for visual review. It is deliberately a component catalogue,
not a proposed customer page, and resolves through ordinary not-found behavior
in production.

## Review corrections

Human visual review found two genuine presentation defects. An unlayered
legacy reset was overriding Tailwind spacing utilities, making the reference
surface appear edge-to-edge and unstructured. Removing that competing reset
restored the intended card padding and section hierarchy. The authentication
story panel also inherited white text onto a light surface in dark mode; it now
uses the semantic brand/brand-foreground pair. The reference gained explicit
section navigation and copy explaining its purpose. Both corrections were
verified live before acceptance.

## Important boundaries

This stage introduced no new application authority, retrieval behavior,
planner semantics, threshold, calibration data or benchmark behavior. Shared
components render states the application already owns. The later
administration and complete-state sessions remain responsible for applying the
foundation to all detailed product surfaces.

The unrelated local ADR notes, draft files and journey assets were not added,
modified or removed.

## Verification

The web suite passed 24 files / 72 tests. ESLint and TypeScript passed. The
production Next.js build completed successfully. Semantic-token tests prove
that both themes define every bridged colour token, required text/status pairs
meet their contrast baseline and new components contain no raw colour literals
or legacy identity variables. Shell tests cover canonical links, active route
state and authority-gated destinations. `git diff --check` passed.

The local runtime reported healthy web, API, AI, PostgreSQL and Qdrant
services. The landing and design-system routes returned HTTP 200. Browser
inspection confirmed restored component padding, a bounded reference layout,
the structured section hierarchy and readable white-on-emerald authentication
story content. No provider calls were made.

## Next

Begin R21-S02 — Design the Administration Experience.
