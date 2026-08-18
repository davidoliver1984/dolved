# R18-S04 — Build Chat Interface

**Date:** 2026-08-18
**Status:** Completed
**Architecture:** ADR-0024 (Accepted)

## What changed

The authenticated workspace now makes the completed Phase 18 conversation
boundary usable. Members can create and revisit tenant-scoped conversations,
send questions with an idempotency key, follow replayable progress, see
validated provisional answer parts, inspect the final answer's durable source
citations, cancel an active run and retry an eligible failure. The conversation
resource exposes the already-authoritative answer parts and tenant-scoped
evidence snapshots, including cited text and source provenance, so inspection
continues to work after navigation or refresh.

The workspace remains honest about authority. Chat searches all documents that
the existing retrieval pipeline determines are eligible for the workspace and
question. R18-S04 did not add browser-owned filtering, temporal resolution or
applicability logic. The previous document-upload UI remains available as a
secondary workspace tool.

## Important correctness details

Provisional parts are visually marked and never inserted into the durable
message history. A completion event causes the client to reload the persisted
conversation before presenting it as authoritative. A delivery interruption
does not delete prior messages or enable a competing run; the interface reports
that EventSource is reconnecting. Execution failures remain saved and offer the
server-approved retry action.

The composer supports Enter to submit and Shift+Enter for a newline. Native
buttons, labels, live regions, alert roles, focus-visible states and responsive
layouts provide the first accessibility boundary; the Phase 18 acceptance gate
will verify the complete phase rather than silently folding that gate into this
stage.

## Verification

- `npm test`: 9 test files and 30 tests passed.
- `npm run lint`: passed.
- `./node_modules/.bin/tsc --noEmit`: passed.
- `npm run build`: passed with the existing non-blocking multiple-lockfile root
  warning.
- `php artisan test --filter=ConversationOrchestrationTest`: 8 tests and 71
  assertions passed.
- focused Pint check for the changed API and test paths: passed.
- full `php artisan test`: 239 tests passed; the remaining 6 failures and 43
  errors require evaluation/contract mounts or the document-storage bucket that
  are absent from the host test environment. The focused R18 conversation suite
  remains green.
- `git diff --check`: passed.
- No provider calls were made.

## Next

Run the Phase 18 Conversation and Streaming acceptance gate. Phase 19 must not
begin until that separate gate is reviewed and closed.
