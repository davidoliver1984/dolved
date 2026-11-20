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

Detailed product behaviour and data/API contracts belong in the
[specification index](../specs/README.md). Former ADR paths for reclassified
specifications remain as forwarding records; the
[classification map](../DOCUMENTATION_CLASSIFICATION.md) preserves their legacy
identities and recorded status.

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
| [0041](0041-use-stock-self-hosted-dify-for-single-client-delivery.md) | Accepted | Use stock self-hosted Dify behind Dolved's existing frontend and security/governance boundary for the bounded single-client pilot |
| [0001](0001-use-postgresql-18.md) | Accepted | Use PostgreSQL 18 as the relational database major version |
| [0002](0002-use-three-application-service-architecture.md) | Accepted (retrospective) | Separate the web, core API and AI workloads into three applications |
| [0003](0003-use-container-first-local-development.md) | Accepted (retrospective) | Use containers as the canonical local development environment |
| [0004](0004-use-localstack-4-for-local-aws-emulation.md) | Accepted | Use LocalStack 4.14 for account-free local S3 and SQS emulation |
| [0005](0005-use-sanctum-and-fortify-for-first-party-spa-authentication.md) | Accepted | Use Sanctum stateful sessions and Fortify mechanics for first-party SPA authentication |
| [0006](0006-use-workspace-as-the-tenancy-and-isolation-boundary.md) | Accepted | Use Workspace as the platform's tenancy, collaboration and data-isolation boundary |
| [0007](0007-define-the-document-lifecycle-and-storage-model.md) | Accepted | Define the Document lifecycle, ownership and relational/storage/vector separation model |
| [0008](0008-use-the-transactional-outbox-pattern.md) | Accepted | Use a PostgreSQL-backed transactional outbox for document-ingestion event publication |
| [0009](0009-use-hmac-authentication-for-ai-worker-lifecycle-requests.md) | Accepted | Authenticate the AI ingestion worker's internal lifecycle requests with a rotatable HMAC protocol |
| [0012](0012-establish-the-observability-and-telemetry-foundation.md) | Accepted | Establish OpenTelemetry as the platform's vendor-neutral observability foundation |
| [0013](0013-define-the-provider-neutral-embedding-architecture-and-contract.md) | Accepted | Define the provider-neutral Embedder boundary, Voyage as the initial V1 provider, and the embedding profile/lineage contract |
| [0014](0014-define-the-vector-storage-architecture-and-qdrant-topology.md) | Accepted | Define the Qdrant vector storage architecture: the embedding-space/workspace-corpus generation model, collection topology, and the VectorStore boundary |
| [0021](0021-define-hybrid-retrieval-and-reranking-architecture.md) | Accepted | Define the hybrid retrieval pipeline: provider-neutral SparseEncoder (SPLADE++), application-owned deterministic RRF FusionStrategy, provider-neutral Reranker (Voyage rerank-2.5), Laravel-owned EvidenceThresholdPolicy, and the extended rc1/generation-rollout model |
| [0026](0026-operationalise-platform-observability-and-incident-response.md) | Accepted | Operationalise platform observability and incident response: privacy-safe structured logging, operational metrics and platform administration, Collector-owned sampling and trace coverage, and actionable SLOs, alerts and runbooks |
| [0028](0028-split-platform-operations-into-route-backed-sections.md) | Accepted | Split Platform Operations into route-backed Overview, Alerts, Telemetry and Policy sections with contextual navigation and fail-closed platform-authority concealment |
| [0029](0029-define-the-platform-testing-and-contract-verification-strategy.md) | Accepted | Define the layered platform test taxonomy, shared contract verification, isolated deterministic E2E boundary, and separate integrity, orchestration and live-quality evaluation evidence |
| [0032](0032-persist-a-structured-extraction-representation-and-authorise-source-delivery.md) | Accepted | Persist a structured extraction representation and authorise tenant-safe source and extracted-text delivery |
| [0037](0037-require-live-provider-evidence-for-phase-28-pilot-readiness.md) | Accepted | Require identity-bound live-provider evidence specifically for the Phase 28 pilot-readiness decision |
| [0038](0038-define-bounded-evidence-needs-for-conversational-retrieval.md) | Accepted | Bound conversational evidence needs while retaining production authority and generation ownership; initial R01P slice gates wider repair |
| [0039](0039-define-authorised-applicability-scope-facts.md) | Accepted | Add authorised snapshot-only scope facts with deterministic safety-limited rendering, separate citations and an explicitly accepted planner family-binding limitation; operational eligibility remains unchanged |
