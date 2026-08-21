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
| [0005](0005-use-sanctum-and-fortify-for-first-party-spa-authentication.md) | Accepted | Use Sanctum stateful sessions and Fortify mechanics for first-party SPA authentication |
| [0006](0006-use-workspace-as-the-tenancy-and-isolation-boundary.md) | Accepted | Use Workspace as the platform's tenancy, collaboration and data-isolation boundary |
| [0007](0007-define-the-document-lifecycle-and-storage-model.md) | Accepted | Define the Document lifecycle, ownership and relational/storage/vector separation model |
| [0008](0008-use-the-transactional-outbox-pattern.md) | Accepted | Use a PostgreSQL-backed transactional outbox for document-ingestion event publication |
| [0009](0009-use-hmac-authentication-for-ai-worker-lifecycle-requests.md) | Accepted | Authenticate the AI ingestion worker's internal lifecycle requests with a rotatable HMAC protocol |
| [0010](0010-define-the-canonical-extracted-document-contract.md) | Accepted | Define the immutable ExtractedDocument and NormalisedDocument processing boundaries |
| [0011](0011-define-the-chunking-architecture-and-contract.md) | Accepted | Define the deterministic, immutable ChunkingStrategy/ChunkingResult contract |
| [0012](0012-establish-the-observability-and-telemetry-foundation.md) | Accepted | Establish OpenTelemetry as the platform's vendor-neutral observability foundation |
| [0013](0013-define-the-provider-neutral-embedding-architecture-and-contract.md) | Accepted | Define the provider-neutral Embedder boundary, Voyage as the initial V1 provider, and the embedding profile/lineage contract |
| [0014](0014-define-the-vector-storage-architecture-and-qdrant-topology.md) | Accepted | Define the Qdrant vector storage architecture: the embedding-space/workspace-corpus generation model, collection topology, and the VectorStore boundary |
| [0015](0015-define-end-to-end-ingestion-orchestration-and-worker-result-contracts.md) | Accepted | Define the authenticated worker-result contracts, recoverable processing lease and purpose-scoped protocol connecting Python's ingestion pipeline to Laravel's authoritative Document lifecycle; supersedes ADR-0009 in part |
| [0016](0016-define-ingestion-publication-and-recovery-semantics.md) | Accepted | Define the provisional-to-published vector lifecycle, dual retrieval-visibility gate, sealed-attempt chunk recovery and complete v2 worker-protocol purpose list; supersedes ADR-0014 in part (minimal Qdrant payload) and ADR-0015 in part (publication visibility, cross-worker recovery, purpose list, saga semantics) |
| [0017](0017-define-document-versioning-and-temporal-authority.md) | Accepted | Define DocumentFamily, explicit linear version lineage, and the derived CURRENT/VALID_AT_DATE temporal-authority model with governance state and per-version location applicability; supersedes ADR-0007 in part (resolves its deferred versioning decision) |
| [0018](0018-define-retrieval-planning-eligibility-and-the-retriever-contract.md) | Accepted | Define the RetrievalPlanner/RetrievalPlan planning boundary, the Laravel-owned EligibilityResolver/EligibleRetrievalScope, the provider-neutral Retriever, the retrieval outcome taxonomy, and the new rc1 synchronous Laravel-to-Python protocol |
| [0019](0019-define-retrieval-evaluation-and-quality-gates.md) | Accepted | Define the repository-owned evaluation corpus/schema/baselines/quality-gate policy, layered planner/eligibility/retrieval/operational metrics, source-anchored relevance ground truth, and the provider-neutral ModelAssistedEvaluator boundary with a concrete RagasEvaluator adapter in V1 |
| [0020](0020-clarify-retrieval-evaluation-evidence-and-model-assisted-evaluation-semantics.md) | Accepted | Clarify EvidenceUnit metric semantics, adversarial-case ownership, model-assisted evaluation isolation, corpus/policy digests, aggregation and reproducibility for ADR-0019 |
| [0021](0021-define-hybrid-retrieval-and-reranking-architecture.md) | Accepted | Define the hybrid retrieval pipeline: provider-neutral SparseEncoder (SPLADE++), application-owned deterministic RRF FusionStrategy, provider-neutral Reranker (Voyage rerank-2.5), Laravel-owned EvidenceThresholdPolicy, and the extended rc1/generation-rollout model |
| [0022](0022-refine-the-retrieval-planning-temporal-and-location-reference-contract.md) | Accepted | Refine the RetrievalPlan typed contract to four temporal modes (CURRENT/COMPARE/VALID_AT_DATE/HISTORICAL_REFERENCE) with a typed calendar-period/historical-reference distinction, plural location references, and enum-typed clarification reasons; extends ADR-0018 without reopening its LLM/application ownership boundary |
| [0023](0023-define-the-provider-neutral-grounded-generation-architecture-and-contract.md) | Accepted | Define the provider-neutral Generator boundary, OpenAI/gpt-5-mini as the initial V1 adapter, the ANSWERED/QUALIFIED/INSUFFICIENT_EVIDENCE outcome taxonomy, answer_parts[] as the sole authoritative generated representation with application-owned identity, durable per-answer EvidenceSnapshots, deterministic context-packing ownership and the GENERATION_CONTEXT_BUDGET_EXCEEDED failure, and the rc1 generation.answer protocol extension |
| [0024](0024-define-the-conversation-and-streaming-domain.md) | Accepted | Define the tenant-owned conversation/message/run domain, controlled retrieval-outcome handoff, provider-neutral contextualisation and streaming boundaries, connection-independent execution, authoritative final persistence, provisional delivery and resumable SSE projection |
| [0025](0025-define-the-administration-and-tenant-control-plane.md) | Accepted | Define the administration and tenant control plane: role-gated document and membership administration, asynchronous document deletion with ingestion quiescence, durable historical evidence, and tenant-scoped usage visibility |
| [0026](0026-operationalise-platform-observability-and-incident-response.md) | Accepted | Operationalise platform observability and incident response: privacy-safe structured logging, operational metrics and platform administration, Collector-owned sampling and trace coverage, and actionable SLOs, alerts and runbooks |
| [0027](0027-define-the-product-interface-and-design-system.md) | Accepted | Define Dolved's product interface and design system: repository-owned tokens and components, dark-default theming, one adaptive route-backed shell, a bounded citation presentation contract, and a WCAG 2.2 AA accessibility baseline |
| [0028](0028-split-platform-operations-into-route-backed-sections.md) | Accepted | Split Platform Operations into route-backed Overview, Alerts, Telemetry and Policy sections with contextual navigation and fail-closed platform-authority concealment |
