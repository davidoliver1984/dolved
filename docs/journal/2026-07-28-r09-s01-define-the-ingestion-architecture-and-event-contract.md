# Session Journal: R09-S01 — Define the Ingestion Architecture and Event Contract

## Date

2026-07-28

## Session mode

Architecture review followed by bounded contract-validation implementation in
teaching mode.

No outbox migration, queue publication, lifecycle transition implementation,
consumer, parsing, chunking, embedding or vector indexing was added.

## Architecture decisions and clarification

ADR-0008 accepts the Transactional Outbox Pattern: Laravel will commit a
Document's transition to `QUEUED` and a durable publication intent in the same
PostgreSQL transaction, then publish to SQS asynchronously.

That decision exposed a contradiction with ADR-0007's original wording, which
defined `QUEUED` by successful queue publication. Work paused before
implementation. The human developer approved a narrow supersession notice in
ADR-0007 while preserving its original text as historical context. No new
lifecycle state was introduced.

The existing contract decisions were retained: a strict version 1 schema,
`additionalProperties: false`, explicit Workspace context, distinct event and
correlation identifiers, at-least-once delivery and `event_id`-based consumer
idempotency.

## What happened

The canonical `document.ingestion.requested` contract now contains one Draft
2020-12 schema, one valid example, four negative fixtures and documentation.
The negative fixtures isolate a missing required Workspace identifier, an
unsupported version, an unexpected presigned URL and a zero-byte source.

Laravel uses Opis JSON Schema and Python uses `jsonschema`. Both test suites
load the same files from the repository contract directory. The directory is
mounted read-only at `/contracts` in both containers so application-local
copies cannot drift.

The PHP suite checks every fixture against its intended leaf validation
keyword. The Python suite performs the same shared-fixture check and adds
explicit assertions for the rejected value or field.

## Dependencies

Composer resolved:

* `opis/json-schema` 2.6.0;
* `opis/string` 2.1.0; and
* `opis/uri` 1.1.0.

uv resolved `jsonschema` 4.26.0 with its non-GPL format-validation extras.
`types-jsonschema` 4.26.0.20260518 was added for the repository's strict mypy
workflow.

## Verification performed

* Focused Laravel contract suite: 6 passed (10 assertions).
* Focused Python contract suite: 9 passed.
* Full Laravel suite: 74 passed (259 assertions).
* Full web suite: 10 passed across 4 files.
* Full AI suite: 10 passed.
* Pint, ESLint, Ruff formatting/linting, TypeScript and mypy passed.
* Composer validation and uv lock validation passed.
* Compose configuration validation and all six container health checks passed.
* `git diff --check` passed.
* The tracker, schema, example and every JSON fixture parsed successfully.

The valid example passed in both languages. The shared invalid fixtures failed
in both languages for `required`, `const` and `additionalProperties`
respectively—not because of an inaccessible path or malformed harness.

## Problems and corrections

The first repository-wide gate failed at mypy because `jsonschema` does not
ship the typing metadata mypy expects. Runtime validation and Ruff already
passed. Adding the maintained `types-jsonschema` development stub resolved the
static-analysis failure, and the complete gate was rerun successfully.

The existing Compose mounts exposed only `/app` to each service, so neither
container could reach a repository-root canonical contract. Both services now
receive the same `/contracts` read-only mount.

Final review compared the event's `byte_size` rule with the implemented upload
pipeline. The browser rejects empty files, Laravel requires a declared size of
at least one byte, and completion verifies the stored object against that
positive size. There is no legitimate path from a zero-byte object to an
accepted upload, and an empty document has no ingestion value. The schema was
therefore tightened from `minimum: 0` to `minimum: 1`; a fourth shared invalid
fixture proves that both languages reject the impossible producer state.

The R09-S02 and R09-S03 titles in `tasks.json` already match the current
implementation guide. The previously reported tracker title drift is not
present and required no reconciliation in this session.

## Important takeaways

* A shared contract is only genuinely shared when every producer and consumer
  test reads the same physical files.
* Invalid examples should assert why they fail; an accidental path error can
  otherwise masquerade as a successful negative test.
* Runtime compatibility does not guarantee static-analysis compatibility.
  Typing stubs can be a legitimate development dependency without becoming
  production application code.
* The outbox commit—not eventual SQS success—is the durable boundary that now
  defines `QUEUED`.

## Next step

The human developer approved the verified R09-S01 implementation and commit
boundary. Commit the session and advance to R09-S02.
