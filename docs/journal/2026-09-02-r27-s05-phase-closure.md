# R27-S05 — Governance notification acceptance and Phase 27 closure

Date: 2 September 2026
Status: Complete

## Outcome

Phase 27 is complete. ADR-0036 now has durable, tenant-safe document-governance
events and projections; protected owner-change commands; actionable-work
counts; an accessible in-product inbox; scheduled reminders; user preferences;
fenced delivery attempts; and multipart email rendering with a reserved,
fail-closed tenant-branding seam.

## Product and visual acceptance

- David explicitly reviewed and approved all 15 named in-product states in
  both light and dark themes.
- David explicitly reviewed and approved all 6 email states, including mobile
  rendering.
- The product surface covers unread state, navigation, keyboard movement,
  dismissal, empty/error/loading states and responsive drawer behaviour.
- Email rendering remains provider-free at this gate. Real deliverability is
  still owned by its separate approved smoke-test boundary.

## End-to-end evidence

The isolated Phase 27 journey passed the real product path from an import that
needs attention through review and promotion, then created a review reminder
and verified inbox counts, actionable-work persistence, destination routing,
adjacent-item keyboard focus, dismissal, theme toggling and the mobile
navigation path. Mark-read completion is awaited before navigation so a route
change cannot cancel the durable update.

The existing ingestion and authorization-adoption journeys passed during the
same verification series. In the final combined invocation, the unchanged
ingestion journey reached its final answer too quickly for one transient
“Understanding your question…” visibility assertion; earlier clean invocations
of that unchanged journey passed. This is recorded as timing-sensitive test
evidence, not represented as a Phase 27 product failure.

No external provider was called.

## Verification

- Focused Laravel governance/API coverage: 37 tests, 223 assertions.
- Focused Pint verification passed.
- Focused web inbox coverage: 3 tests passed.
- TypeScript and ESLint passed for the reviewed web changes.
- PostgreSQL runtime-role reconciliation and verification passed during clean
  isolated bootstrap and migration.
- The isolated Phase 27 Playwright governance journey passed.
- `git diff --check` passed.

## Gate decision

R27-S05 and R27-GATE pass. Phase 27 is complete. R28-S01 may begin only as a
separate task; no Phase 28 provider activity is included in this closure.

## Post-closure correctness correction

An independent review after closure identified that generation-one email
preflight had applied one arbitrarily selected member's document-family
authority result to an entire digest. The bounded correction now evaluates
every sealed member independently, persists one immutable included-or-
suppressed decision per member, constructs the dispatch digest from the full
ordinal-ordered decision set and renders only included members. A digest with
no included members still becomes terminally suppressed without opening an
attempt. Retry generations continue to reuse the frozen decisions and digest
without re-resolving per-member authority, exactly as ADR-0036 requires.

Provider-free regression coverage uses genuine two-family digests for both-
included, either-family-suppressed, both-suppressed, reversed assembly order,
retry freezing and malformed cross-workspace lineage. This correction extends
the Phase 27 acceptance evidence without rewriting its original history or
changing any settled ADR-0036 behaviour.
