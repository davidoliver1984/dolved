# Contributing

## Purpose and audience

This guide is for human contributors and AI-assisted engineering sessions working
within the RAG platform's established architecture, engineering workflow and
documentation system. It explains how to make a bounded, reviewable change without
silently changing the platform's design or duplicating its existing documentation.

The documentation authorities are:

| Document | Responsibility |
|---|---|
| `PROJECT_ROADMAP.md` | What will be built and in what order |
| `IMPLEMENTATION_GUIDE.md` | How stages were implemented, verified and committed |
| `tasks.json` | Which planned engineering session is current |
| `docs/adr/` | Why durable architecture decisions were made |
| `docs/journal/` | What happened during individual engineering sessions |
| `README.md` | Repository introduction and normal operating instructions |

Do not reproduce those documents here. If an authority is missing, exists at a
different path, or contradicts another authority, stop and reconcile the conflict
with the human developer before treating either version as canonical.

## Engineering Philosophy

The objective of this repository is not merely to build a working RAG platform.

It is to build a production-quality system whose architecture, documentation,
engineering decisions and commit history demonstrate senior software engineering
practice.

Every change should optimise for:

- clarity over cleverness
- explicit architecture
- maintainability
- repeatability
- observability
- security
- teaching value

## AI Behaviour

AI tools should:

- inspect before modifying
- explain before implementing
- preserve existing conventions
- stop when architecture decisions are required
- never fabricate verification
- never silently broaden scope
- prefer improving existing code over introducing parallel patterns

## Repository structure

| Path | Responsibility |
|---|---|
| `apps/web` | Next.js and TypeScript browser interface and presentation logic |
| `apps/api` | Laravel system of record for the domain, authentication, authorisation, tenancy and relational data |
| `apps/ai` | Python and FastAPI AI workloads behind the Laravel boundary |
| `contracts` | Explicit, language-neutral HTTP and event contracts shared between services |
| `docs` | ADRs, engineering journals and supporting technical documentation |
| `infrastructure` | LocalStack, Docker-related infrastructure and future Terraform |
| `scripts` | Repeatable repository automation that does not belong in an application |
| `tests` | Cross-service and end-to-end tests; service-owned tests remain with their applications |

Preserve these responsibilities unless an agreed ADR changes them.

## Local development principles

- Prefer the repository's Docker Compose and root Make commands over host-installed
  Node.js, PHP or Python runtimes.
- Use the stable Compose service names `web`, `api`, `ai`, `postgres`, `localstack`
  and `mailpit` consistently in commands and documentation.
- Do not introduce hidden manual setup. Encode repeatable work in the Makefile,
  Compose configuration or a reviewed script.
- Keep setup and provisioning commands idempotent where practical.
- Add every new required environment variable to the appropriate `.env.example` in
  the same change, with a safe example value or an explanation.
- Never commit real secrets, tokens, credentials, personal data or populated local
  environment files.
- Use `make bootstrap` for first-time setup, `make up` to start healthy services and
  `make down` to stop them without deleting data.
- Treat `make reset` as destructive. It requires deliberate confirmation and deletes
  local Compose volumes, including PostgreSQL data.

Run `make help` for the current command list rather than maintaining a second
operations manual here.

## Engineering workflow

Planned engineering sessions follow this sequence:

1. Read the current session from `tasks.json`.
2. Discuss architecture, concepts, alternatives and trade-offs with an architecture
   reviewer.
3. Produce an agreed, bounded implementation brief.
4. Implement the agreed brief. AI assistants may be used where appropriate, but the
   human developer remains responsible for the resulting code.
5. Run the required stage-specific and repository-wide verification.
6. Complete architecture review, resolve outstanding questions and record Important
   Takeaways.
7. Apply any agreed corrections.
8. Update `IMPLEMENTATION_GUIDE.md` with the actual commands, changes and
   verification evidence.
9. Create or update an ADR only when a durable architectural decision was made.
10. Write the factual session journal entry in `docs/journal/`.
11. Commit at the agreed session or phase boundary.
12. Update `tasks.json` and advance the current session.

Minor maintenance changes, documentation corrections and other work outside a planned session do not require a new session record. They must still remain focused, reviewable and subject to the relevant verification and commit standards.

An architecture reviewer should establish concepts, architecture, alternatives and engineering trade-offs, helping define the intended solution before implementation begins.

AI implementation assistants may implement all or part of the agreed brief. The human developer remains responsible for reviewing, accepting and integrating those changes.

AI implementation assistants must not make significant architectural decisions independently. Where an architectural decision is required, they should:

- identify the decision that needs to be made;
- present the viable options and their trade-offs;
- recommend the option that best fits the existing architecture; and
- stop and obtain agreement before implementation or creating a final ADR.

The human developer remains responsible for all final architectural decisions, implementation approval, commit boundaries and repository history. AI tools may recommend, prepare or create commits only after the human developer has explicitly agreed the implementation scope and authorised the commit.

## Architecture and implementation workflow

This project deliberately separates **architecture** from **implementation**.

Architectural decisions should be discussed, reviewed and accepted before implementation begins. Avoid mixing architectural design with application code in the same commit wherever reasonably practical.

### Architecture workflow

Each architecture session follows the same lifecycle:

1. **Architecture discussion**
   - Explore options, trade-offs and recommendations.
   - Record reasoning in `docs/adr/gpt_drafts/rXX-sXX.md`.

2. **Architecture review**
   - Review the proposed design.
   - Challenge assumptions.
   - Identify security, operational and future-proofing concerns.
   - Record review notes in `docs/adr/gpt_drafts/rXX-sXX-reviewed.md`.

3. **Final amendments**
   - Apply agreed review changes.
   - Record final amendments in `docs/adr/gpt_drafts/rXX-sXX-final-amends.md`.

4. **ADR acceptance**
   - Produce the final ADR in `docs/adr/`.
   - Once accepted, the ADR becomes the canonical architectural decision.
   - Implementation should not begin until the ADR is accepted.

The draft documents intentionally capture the evolution of the decision. The ADR records the final accepted outcome.

## Commit message conventions

### Architecture & Documentation

Architecture-only commits should document accepted decisions and should **not** introduce application code.

Preferred commit subjects:

```text
Document ...
Define ...
Record ...
Clarify ...
Accept ADR-XXXX ...
```

Examples:

```text
Document workspace tenancy model (ADR-0006)

Define retrieval architecture (ADR-0007)

Record ingestion pipeline design
```

Architecture commits should typically include:

- ADRs
- implementation guide updates
- roadmap updates
- journals
- architectural documentation

Where appropriate, include:

```text
No application code changed.
```

### Implementation

Implementation commits should implement previously accepted architectural decisions and remain within the scope of the accepted ADR. Architectural redesign belongs in a separate architecture session and ADR where required.

Preferred commit subjects:

```text
Implement ...
Add ...
Refactor ...
Remove ...
Replace ...
Fix ...
```

Examples:

```text
Implement workspace persistence

Implement PostgreSQL Row-Level Security

Implement tenant middleware
```

Where an ADR exists, reference it in the commit body.

Example:

```text
Implements ADR-0006.
```

### Engineering principle

Architecture sessions produce **decisions**.

Implementation sessions produce **software**.

Keep those responsibilities separate wherever practical. This improves review quality, repository history, architectural traceability and long-term maintainability.

## Change discipline

- Keep changes inside the agreed session scope and do not silently begin the next
  stage.
- Inspect existing code, tests, contracts, ADRs and documentation before introducing
  a new pattern.
- Prefer small changes that can be reviewed and verified independently.
- Avoid unrelated cleanup, formatting churn or dependency updates in a feature
  commit.
- Preserve stable service, domain, security and data-ownership boundaries.
- Do not bypass repository commands merely to make a local check pass.
- Stop and raise contradictions between code, contracts, ADRs,
  `PROJECT_ROADMAP.md` and `IMPLEMENTATION_GUIDE.md`.
- Leave no unexplained temporary files, commented-out workarounds or disabled tests.

## Laravel application style

Follow the established NextRep separation of HTTP, application and infrastructure
concerns where it fits the RAG domain:

- Keep controllers thin. A controller should coordinate the HTTP request, invoke an
  application operation and return a response; it should not contain domain
  workflows, storage logic or complex queries.
- Use Form Requests for input validation and request-level authorisation. Pass
  validated data into the application layer rather than passing an entire request.
- Use policies and Laravel's authorisation facilities for resource access. Never
  treat a controller, route name or frontend guard as sufficient authorisation.
- Put a state-changing use case in a focused Action, grouped by domain under
  `app/Actions/`. Prefer a descriptive verb-based class name and a typed `handle()`
  method.
- Put reusable or non-trivial read logic in a Query, grouped by domain under
  `app/Queries/`. Query classes must apply tenant scope explicitly and should return
  a deliberate model, collection or paginator type.
- Use Services for external or infrastructure capabilities such as object storage,
  rather than as a generic home for unrelated business logic.
- Use Reports or similarly explicit read-model builders when a response composes
  several queries or calculations.
- Use API Resources to define stable response shapes instead of returning accidental
  model serialisation.
- Inject Actions, Queries, Services and Reports through Laravel's container rather
  than constructing them in controllers.
- Wrap multi-record state changes in a database transaction when partial completion
  would violate an invariant.
- Scope ownership and tenancy at the query boundary, even when a policy also checks
  access. Add feature tests proving that another tenant cannot list, read, change or
  delete the resource.

Do not create a layer merely to rename a one-line framework call. Apply this pattern
when it makes responsibility, reuse, testing or transaction boundaries clearer, and
inspect the surrounding RAG code before introducing a new abstraction.

## Architecture decisions

An ADR is normally appropriate when a decision:

- affects multiple services;
- establishes or changes a durable system boundary;
- has meaningful alternatives or trade-offs;
- changes security, tenancy, data ownership or operational behaviour; or
- would be difficult, risky or expensive to reverse.

Ordinary implementation details, commands, bug fixes and easily reversible choices
belong in the implementation guide or code review, not in an ADR. Architecture
decisions must be agreed with the human developer before an AI assistant prepares
the final ADR.

Follow `docs/adr/README.md` for numbering and required sections. Do not rewrite an
accepted ADR to change history; add a superseding ADR.

## Implementation evidence and learning records

`IMPLEMENTATION_GUIDE.md` records what actually happened. A completed stage entry
must include:

- its objective and rationale;
- commands actually executed;
- files or observable behaviour changed;
- verification performed and its result;
- meaningful problems and corrections; and
- the intended commit boundary.

Never record a planned command as though it succeeded. Preserve useful failure and
correction evidence without copying secrets, tokens or sensitive logs.

The three longer-lived record types have different jobs:

- A session journal in `docs/journal/` is a factual, reflective account of the work,
  lessons and next steps.
- An ADR in `docs/adr/` records a durable decision, alternatives and consequences.
- A MakeTime note is a separately edited public learning article. It may draw on a
  journal, but is not an engineering record and is not required for every session.

## Quality and verification

The root Makefile is the canonical developer interface. The current verification
commands are:

| Requirement | Command | Current coverage |
|---|---|---|
| Apply formatters | `make format` | Web ESLint fixes, Laravel Pint and Python Ruff formatting |
| Check formatting | `make format-check` | Web ESLint, Laravel Pint and Python Ruff formatting |
| Lint | `make lint` | Web ESLint, Laravel Pint and Python Ruff checks |
| Type check | `make typecheck` | TypeScript and Python MyPy; no separate Laravel static-analysis target exists |
| Automated tests | `make test` | Current web, API and AI test suites |
| Web tests | `make test-web` | Vitest |
| API tests | `make test-api` | Laravel test suite |
| AI tests | `make test-ai` | Pytest |
| Container health | `make ps` | Compose service state and health |
| Local AWS acceptance | `make aws-status` | S3 bucket, SQS queues and redrive policy |

Run the relevant service checks while iterating. Before a session or phase boundary,
run the stage-specific acceptance checks from `IMPLEMENTATION_GUIDE.md` and the
applicable repository-wide checks, normally:

```bash
make format-check
make lint
make typecheck
make test
make ps
```

Also run `make aws-status` when the change touches LocalStack, S3, SQS or their
configuration. Use any additional acceptance command named by the current stage;
do not invent a substitute or report an unrun check as passing.

All three application services currently have an automated test suite. The
`tests/end-to-end/` suite is intentionally not implemented until the first vertical
slice, so do not imply end-to-end coverage that does not yet exist.

## Database and migration rules

- Make migrations forward-safe, focused and reviewable.
- Do not edit an already-shared migration merely to hide a later schema change.
  Create a new migration that makes the transition explicit.
- Provide a safe rollback where practical, and explain an intentionally irreversible
  migration.
- Ensure tenant-owned data follows the accepted tenancy architecture, including
  keys, constraints, queries and tests.
- Consider existing data, backfills, locking and deployment order rather than
  assuming an empty database.
- Use `make migrate` for outstanding migrations and `make seed` for the configured
  development seeder.
- Keep destructive reset commands clearly named and intentionally invoked. Never
  place data deletion behind an innocent-sounding setup command.
- Fixtures, factories and seed data must use synthetic values and must never contain
  secrets or real personal information.

## Contracts and cross-service changes

Shared HTTP and event-contract changes must be explicit and version-aware where
required. A change must:

- update every affected producer and consumer;
- add or update relevant contract, producer and consumer tests;
- preserve authenticated tenant context and traceability;
- update the contract and implementation documentation;
- consider backward compatibility, queued or in-flight messages, deployment order
  and rollback; and
- preserve idempotency where an event or retryable operation requires it.

Do not use framework-specific serialisation as a language-neutral contract. The
browser does not call the AI service directly; the accepted request path and service
ownership remain authoritative unless an ADR changes them.

## Security

- Never put secrets in source control, logs, fixtures, screenshots, journals or
  copied verification output.
- Never log passwords, session values, CSRF tokens, API tokens, cookies or
  credentials.
- Authentication and authorisation remain Laravel responsibilities unless an
  accepted ADR changes that boundary.
- Next.js interface guards improve user experience but are not security boundaries.
- Enforce tenant isolation server-side on every read, write, job, event and storage
  operation.
- Add negative tests for security-sensitive changes, including unauthenticated,
  unauthorised, unverified and cross-tenant cases as applicable.
- Preserve tenant context across HTTP, queues, events, object storage and AI-service
  calls without trusting client-supplied ownership.
- Report a discovered vulnerability and gather only the evidence needed to
  demonstrate it; do not exploit it unnecessarily.

## Git and commit conventions

- Keep each commit focused, understandable and aligned with the agreed stage
  boundary.
- Run and record the required verification before committing.
- Give every completed planned stage its own commit and annotated
  `phase-N-sNN` tag, following `IMPLEMENTATION_GUIDE.md`.
- After the final stage passes the complete phase acceptance gate, also create
  the annotated `phase-N` tag at the accepted phase-completion commit.
- Push approved stage commits and their tags to the configured remote.
- Review generated files, lock files, migration output and schema or contract
  artefacts before including them.
- Inspect `git status` and the staged diff so unrelated local or ignored files are
  not committed accidentally.
- Do not impose a branch-name or Conventional Commits policy: the current repository
  does not establish either as a requirement.
- Do not rewrite shared history or accepted phase tags to conceal a correction.
- The human developer owns the repository history.
- AI tools may prepare commits, suggest commit messages or assist with Git
  operations, but commit boundaries, repository history and releases remain the
  responsibility of the human developer.
- Human-authored changes follow the same engineering, documentation and
  verification standards as AI-assisted changes.



## Definition of done

A session is complete only when:

- the agreed scope is implemented and no next-stage work has been started;
- all stage acceptance checks pass;
- all relevant repository-wide checks pass;
- the implementation remains aligned with accepted ADRs and service boundaries;
- `IMPLEMENTATION_GUIDE.md` reflects the commands, changes, problems, corrections
  and verification that actually occurred;
- any required ADR has been agreed and recorded;
- the factual session journal exists;
- the intended commit boundary is satisfied;
- `tasks.json` is updated to advance the session; and
- no unexplained temporary files, disabled tests or unfinished changes remain.
