# R26-S03 — Bulk execution

Date: 1 September 2026

## Outcome

ADR-0035's execution boundary is complete. Confirmed frozen operations now run
through bounded Laravel queue work without reopening selection or changing the
frozen expected-state snapshot.

## Implemented

- Added generation-aware claim exclusion, attempt leases and tokens, durable
  failure/not-applied outcomes, retry ceilings and expired-attempt reclamation.
- Preserved the normative lock order: target identity first for mutation,
  attempt fencing next, item incorporation afterward, and parent-before-item
  for convergence.
- Reused the accepted single-item governance, metadata, promotion and
  applicability actions. Promotion and applicability items remain
  `waiting_on_subordinate` until their own authoritative lifecycle resolves.
- Added cancellation convergence without rewriting open attempts or committed
  subordinate work.
- Added immutable parent/item audit events and reconciled protected runtime
  grants after the general DML baseline.
- Added operator scheduling for execution, reclamation and reconciliation.

## Verification

- Focused bulk foundation, terminal-state and execution tests: 25 passed, 134
  assertions after the final promotion-subordinate regression was added.
- Focused bulk execution test: 9 passed, 65 assertions.
- Complete Laravel suite: 532 passed, 6 documented skips, 2,861 assertions.
- Pint passed for all changed PHP files.
- PostgreSQL bulk-foundation and runtime-role catalog verification passed.
- `git diff --check` passed.
- No provider calls were made.

## Next

R26-S04 owns the selection, preflight, progress/result UI, Playwright journey
and every required ADR-0035 visual checkpoint. No Phase 27 work begins before
the R26 gate closes.
