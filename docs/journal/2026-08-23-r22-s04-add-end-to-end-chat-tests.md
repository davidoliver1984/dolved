# R22-S04 — Add end-to-end chat tests

**Date:** 2026-08-23
**Status:** Complete

## What changed

The isolated `dolved-e2e` environment now proves the complete authenticated
chat path without contacting external providers. Deterministic,
catalogue-bound contextualisation and grounded-generation adapters join the
existing deterministic planner, embedding, sparse and reranking adapters. E2E
preflight requires that complete six-adapter identity and rejects external
provider credentials, mixed profiles and unknown catalogue inputs.

The compact scenario catalogue contains one grounded answer and one follow-up
whose requested quantitative detail is not present in the evidence. The answer
adapter can cite only evidence handles supplied by Laravel. The insufficiency
adapter returns the typed controlled outcome rather than synthesising a fact.
Both remain exact-input engineering fixtures, not model-quality evidence.

The Playwright journey uses the real browser, Next.js, Laravel, PostgreSQL,
LocalStack S3/SQS, publisher, Python workers and Qdrant. It now proves:

- authenticated upload and successful or failed ingestion;
- deterministic retrieval of the expected evidence;
- conversation creation and persisted reload;
- grounded answer generation and citation expansion;
- citation navigation to the authoritative document route;
- history-aware follow-up contextualisation and controlled insufficiency;
- foreign-workspace concealment for retrieval, conversation and document APIs;
- browser deep-link concealment without leaked answer or source content; and
- native EventSource reconnect with durable event replay.

The reconnect proof uses an E2E-only Laravel setting that closes a stream after
one durable nonterminal event. It is disabled by default, does not change
production streaming, and forces the established-conversation follow-up to
reconnect with the browser-managed `Last-Event-ID`. A focused Laravel regression
also proves the first connection returns sequence 1 and the replay beginning
after `Last-Event-ID: 1` returns sequence 2.

## Verification

- Clean `make test-e2e`: 1 full-path Playwright test passed in 38.3 seconds;
  isolated containers and volumes were removed after success.
- Focused deterministic contextualisation, generation and complete-profile
  tests: 17 passed.
- Focused Laravel SSE replay tests: 2 passed, 14 assertions.
- Full repository format, lint, type, test and provider-free evaluation gates:
  passed.
- Shell syntax, JSON parsing and `git diff --check`: passed.
- External OpenAI and Voyage credentials were absent and provider calls were
  zero.

## Outcome

R22-S04 is complete. This is deterministic application-orchestration and
tenant-boundary evidence only; it does not replace live-provider retrieval or
generation quality evidence. R22-S05 is the next session, and no security-test
implementation began here.
