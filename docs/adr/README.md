# Architecture Decision Records

Architecture Decision Records document important technical decisions made during
the development of the platform.

Write an ADR when a decision:

- establishes or changes a long-lived architectural boundary;
- selects a foundational technology or major version;
- has meaningful alternatives or trade-offs;
- would be expensive or risky to reverse;
- needs context that cannot be recovered from the final code alone.

Routine implementation details, bug fixes and commands belong in
`IMPLEMENTATION_GUIDE.md`, not in separate ADRs.

## File naming

Use a four-digit sequence followed by a concise kebab-case title:

```text
0001-use-postgresql-18.md
```

Sequence numbers record the order in which ADRs are added. A retrospective ADR may
document an earlier decision using the next available number.

## Required sections

Each ADR contains:

1. Title
2. Status
3. Date
4. Context
5. Decision
6. Alternatives considered
7. Consequences

Accepted ADRs are immutable decision records. If a decision changes, add a new ADR
that supersedes the previous record rather than rewriting its historical context.

## Index

| ADR | Status | Decision |
|---|---|---|
| [0001](0001-use-postgresql-18.md) | Accepted | Use PostgreSQL 18 as the relational database major version |
| [0002](0002-use-three-application-service-architecture.md) | Accepted (retrospective) | Separate the web, core API and AI workloads into three applications |
| [0003](0003-use-container-first-local-development.md) | Accepted (retrospective) | Use containers as the canonical local development environment |
| [0004](0004-use-localstack-4-for-local-aws-emulation.md) | Accepted | Use LocalStack 4.14 for account-free local S3 and SQS emulation |
