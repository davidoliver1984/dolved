We are completing R09-S01 — Define the Ingestion Architecture and Event Contract.

Claude has already created the contract artefacts under:

contracts/events/document-ingestion-requested/

Expected current contents:

- v1.schema.json
- v1.example.json
- fixtures/
  - invalid payload missing workspace_id
  - invalid payload using unsupported event_version: 2
  - invalid payload containing unexpected presigned_url
- README.md

ADR 0008 has also been drafted for the Transactional Outbox Pattern, but this task is only to complete the contract-validation implementation required by R09-S01.

Before changing anything, read:

1. CLAUDE.md
2. CONTRIBUTING.md
3. docs/IMPLEMENTATION_GUIDE.md
4. tasks.json
5. docs/adr/0004-use-localstack-4-for-local-aws-emulation.md
6. docs/adr/0006-define-workspace-tenancy-and-membership.md
7. docs/adr/0007-define-the-document-lifecycle-and-storage-model.md
8. docs/adr/0008-use-the-transactional-outbox-pattern-for-document-ingestion-publication.md
9. contracts/events/README.md
10. every file under:
    contracts/events/document-ingestion-requested/
11. the existing Laravel and Python test conventions in:
    apps/api
    apps/ai

Do not redesign the contract unless you find a concrete defect.

The current contract decisions are deliberate:

- `additionalProperties: false`
- additive fields require a new event version
- `event_version` is fixed to `1`
- `event_id` is the stable logical event identifier used for idempotency
- `correlation_id` traces the wider causal chain
- `workspace_id` and `document_id` use the platform’s immutable public identifiers
- `storage_key` matches the existing Document terminology
- credentials, presigned URLs and unbounded metadata are excluded
- delivery semantics are at least once
- consumers must reject unsupported versions
- consumers must be idempotent using `event_id`, not the SQS message ID

The README now explicitly documents the strict versioning trade-off. Preserve that decision.

## Objective

Add real contract-validation tests in both Laravel and Python so both applications validate against the exact same canonical schema and shared fixtures.

## Laravel work

Use a maintained JSON Schema validation library appropriate for the current Laravel/PHP version.

Claude suggested:

opis/json-schema

Confirm compatibility with the repository’s current PHP and Laravel versions before installing it.

Then:

1. add the dependency through Composer;
2. create focused contract-validation tests;
3. load the canonical schema from:
   contracts/events/document-ingestion-requested/v1.schema.json
4. validate:
   - v1.example.json succeeds;
   - each invalid fixture fails;
   - the unsupported version fixture fails because `event_version` is not `1`;
   - the unexpected `presigned_url` fixture fails because additional properties are forbidden;
   - the missing workspace fixture fails because `workspace_id` is required;
5. avoid copying the schema or fixtures into apps/api;
6. resolve repository-root paths robustly from the Laravel test environment;
7. keep this as contract testing, not queue publication or ingestion implementation.

Use the repository’s existing test structure and naming conventions.

## Python work

Use the standard maintained Python library:

jsonschema

Confirm compatibility with the current Python version and the existing uv project before adding it.

Then:

1. add the dependency using the project’s existing uv workflow;
2. create focused pytest contract-validation tests;
3. load the same canonical schema from:
   contracts/events/document-ingestion-requested/v1.schema.json
4. validate the same example and invalid fixtures;
5. do not duplicate schema or fixture files inside apps/ai;
6. resolve repository-root paths robustly;
7. keep this as contract testing only.

Use the repository’s existing typing, Ruff, mypy and pytest conventions.

## Cross-language requirement

Both test suites must validate the same files from the shared contract directory.

Do not allow PHP and Python to maintain separate versions of:

- the schema;
- the valid example;
- invalid fixtures;
- event-version expectations.

The contract directory is canonical.

## Verification

Run all relevant checks, including at minimum:

### Laravel

- the new focused contract tests;
- the full Laravel test suite;
- any formatting or static-analysis command required by repository conventions.

### Python

- the new focused pytest tests;
- the full Python test suite;
- Ruff;
- mypy;
- any other existing repository verification command.

Also manually confirm:

- the valid example succeeds in both languages;
- every invalid fixture fails in both languages;
- failures occur for the intended contract reason rather than a broken path or malformed test harness.

## Scope restrictions

Do not implement:

- the transactional outbox;
- database migrations for outbox records;
- SQS publication;
- queue provisioning;
- the Python ingestion worker;
- lifecycle transitions to `QUEUED` or `PROCESSING`;
- service-to-service authentication;
- parsing;
- chunking;
- embeddings;
- vector indexing.

Do not modify the schema merely to make a library easier to use.

Do not commit unrelated changes.

There may already be unrelated uncommitted files, including CLAUDE.md. Preserve them and do not stage or alter them unless this task genuinely requires it.

## Documentation alignment

Claude flagged that tasks.json may still use older R09-S02/R09-S03 names while docs/IMPLEMENTATION_GUIDE.md uses:

- Publish Ingestion Requests Reliably
- Consume and Claim Ingestion Requests

Do not silently reconcile that as part of this task unless R09-S01 itself is inaccurate.

Report the drift at the end, but keep this implementation focused on R09-S01 contract validation.

## Completion report

After implementation, report:

1. files changed;
2. dependencies added and exact versions resolved;
3. tests added;
4. commands run;
5. results of each command;
6. confirmation that Laravel and Python used the same schema and fixtures;
7. any defects found in the contract;
8. any unresolved questions;
9. whether R09-S01 now satisfies its acceptance criteria;
10. the exact files that should be staged for the R09-S01 commit.

Do not create the commit unless explicitly asked.