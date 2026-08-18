# R18-S03 — Implement streaming responses

**Date:** 2026-08-18
**Outcome:** Completed provider-free
**Architecture:** ADR-0024 unchanged

## What changed

The queued conversation worker can now select the provider-neutral
`STREAMING_PARTS` capability and make one authenticated `generation.stream`
call. Python uses OpenAI's structured Responses stream, incrementally recognises
only complete answer-part objects and sends ordered NDJSON contract events back
to Laravel. The existing `generation.answer` endpoint remains the declared
complete-result-only fallback.

Laravel validates stream identity, sequence, tagged shape, prose and evidence
membership before producing any browser-visible projection. Accepted candidates
are stored as expiring, immutable delivery events with opaque run-scoped citation
references; they never share the authoritative message/answer tables. The
terminal event carries the independently complete result through the existing
whole-answer validator and atomic persistence path. Only after that transaction
commits is `AnswerCompleted` deliverable with persistent citation identities.

An authenticated workspace/conversation/run SSE endpoint replays durable events
after `Last-Event-ID`, disables proxy buffering and closes explicitly on terminal
events. Its connection never owns or cancels the run. The browser helper uses
credentialed EventSource, de-duplicates sequence numbers, closes on terminal
events and fails closed on malformed JSON. Expired delivery data is omitted from
replay and purged hourly.

## Verification

- Focused Laravel: 22 tests, 106 assertions.
- Focused Python 3.14: 34 tests; Ruff, format and Mypy passed.
- Web: Vitest 1 passed; ESLint and TypeScript passed.
- Full Python: 548 passed, 4 skipped; two immutable V3 tests require their
  specialised engineering mount. Full Mypy passed.
- Full Laravel generic host run: 239 passed, 2 skipped; remaining failures were
  pre-existing environment requirements for storage/contracts/evaluation mounts.
- Changed paths pass formatting/linting; shared JSON and `git diff --check` pass.
- No provider calls were made.

## Authority and safety

Raw provider tokens never reach Laravel or the browser. A malformed candidate
invalidates the stream. Provisional text is explicitly non-authoritative,
bounded and retractable. Only the existing whole-result validation plus atomic
Phase 17 persistence can create a completed assistant message.

## Next

R18-S04 can build the user-facing conversation list, transcript, composer,
in-progress answer, citation and retry/cancellation states on these APIs.
