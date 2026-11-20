# Documentation Classification

This map records the reader-facing classification of material originally stored under `docs/adr/`. Reclassification changes navigation, not the recorded decision status or historical facts. Former ADR paths remain forwarding records for reclassified specifications.

## Classification map

| Original path/ID | Classification | Recorded status | Canonical location | Forwarder | Reason |
|---|---|---|---|---|---|
| `docs/adr/0001-*` / ADR-0001 | Architectural decision | Accepted | `docs/adr/0001-use-postgresql-18.md` | No | Selects a foundational database major version after alternatives. |
| `docs/adr/0002-*` / ADR-0002 | Architectural decision | Accepted (retrospective) | `docs/adr/0002-use-three-application-service-architecture.md` | No | Records the application/service boundary and rejected alternatives. |
| `docs/adr/0003-*` / ADR-0003 | Architectural decision | Accepted (retrospective) | `docs/adr/0003-use-container-first-local-development.md` | No | Selects the canonical development environment. |
| `docs/adr/0004-*` / ADR-0004 | Architectural decision | Accepted | `docs/adr/0004-use-localstack-4-for-local-aws-emulation.md` | No | Selects a local AWS-emulation technology and version. |
| `docs/adr/0005-*` / ADR-0005 | Architectural decision | Accepted | `docs/adr/0005-use-sanctum-and-fortify-for-first-party-spa-authentication.md` | No | Selects the authentication mechanism and security boundary. |
| `docs/adr/0006-*` / ADR-0006 | Architectural decision | Accepted | `docs/adr/0006-use-workspace-as-the-tenancy-and-isolation-boundary.md` | No | Establishes the long-lived tenancy and isolation boundary. |
| `docs/adr/0007-*` / ADR-0007 | Architectural decision | Accepted; superseded in part | `docs/adr/0007-define-the-document-lifecycle-and-storage-model.md` | No | Establishes source-of-truth and lifecycle separation choices. |
| `docs/adr/0008-*` / ADR-0008 | Architectural decision | Accepted | `docs/adr/0008-use-the-transactional-outbox-pattern.md` | No | Selects the transactional outbox pattern. |
| `docs/adr/0009-*` / ADR-0009 | Architectural decision | Accepted; superseded in part | `docs/adr/0009-use-hmac-authentication-for-ai-worker-lifecycle-requests.md` | No | Selects the internal service-authentication protocol. |
| `docs/adr/0010-*` / ADR-0010 | Data/API contract specification | Accepted | `docs/specs/data-api-contracts/canonical-extracted-document.md` | Yes | Primarily defines the canonical extraction data contract and invariants. |
| `docs/adr/0011-*` / ADR-0011 | Data/API contract specification | Accepted | `docs/specs/data-api-contracts/chunking-contract.md` | Yes | Primarily defines deterministic chunking inputs, outputs and invariants. |
| `docs/adr/0012-*` / ADR-0012 | Architectural decision | Accepted | `docs/adr/0012-establish-the-observability-and-telemetry-foundation.md` | No | Selects OpenTelemetry and the Collector boundary. |
| `docs/adr/0013-*` / ADR-0013 | Mixed-purpose architectural decision | Accepted | `docs/adr/0013-define-the-provider-neutral-embedding-architecture-and-contract.md` | No | The provider selection and compatibility architecture dominate the embedded contract detail. |
| `docs/adr/0014-*` / ADR-0014 | Architectural decision | Accepted; superseded in part | `docs/adr/0014-define-the-vector-storage-architecture-and-qdrant-topology.md` | No | Selects Qdrant topology, ownership and generation strategy. |
| `docs/adr/0015-*` / ADR-0015 | Data/API contract specification | Accepted; superseded in part | `docs/specs/data-api-contracts/ingestion-orchestration-and-worker-results.md` | Yes | Primarily specifies worker protocol, leases and result contracts. |
| `docs/adr/0016-*` / ADR-0016 | Data/API contract specification | Accepted | `docs/specs/data-api-contracts/ingestion-publication-and-recovery.md` | Yes | Primarily specifies publication, recovery and protocol semantics. |
| `docs/adr/0017-*` / ADR-0017 | Data/API contract specification | Accepted | `docs/specs/data-api-contracts/document-versioning-and-temporal-authority.md` | Yes | Primarily defines the versioning domain and temporal-authority rules. |
| `docs/adr/0018-*` / ADR-0018 | Data/API contract specification | Accepted | `docs/specs/data-api-contracts/retrieval-planning-eligibility-and-retriever.md` | Yes | Primarily defines retrieval boundaries, types and wire contracts. |
| `docs/adr/0019-*` / ADR-0019 | Quality specification | Accepted | `docs/specs/quality/retrieval-evaluation-and-quality-gates.md` | Yes | Primarily specifies corpus, metric, baseline and release-gate behaviour. |
| `docs/adr/0020-*` / ADR-0020 | Quality specification | Accepted | `docs/specs/quality/retrieval-evaluation-evidence-and-model-assisted-semantics.md` | Yes | Clarifies the evaluation specification's evidence and evaluator semantics. |
| `docs/adr/0021-*` / ADR-0021 | Architectural decision | Accepted | `docs/adr/0021-define-hybrid-retrieval-and-reranking-architecture.md` | No | Selects the hybrid retrieval pipeline, fusion and reranking architecture. |
| `docs/adr/0022-*` / ADR-0022 | Data/API contract specification | Accepted | `docs/specs/data-api-contracts/retrieval-planning-temporal-and-location-reference.md` | Yes | Primarily refines fields and deterministic resolution rules in a cross-service contract. |
| `docs/adr/0023-*` / ADR-0023 | Data/API contract specification | Accepted | `docs/specs/data-api-contracts/grounded-generation.md` | Yes | Primarily defines generation inputs, outputs, evidence and protocol behaviour. |
| `docs/adr/0024-*` / ADR-0024 | Product/feature specification | Accepted | `docs/specs/product-features/conversations-and-streaming.md` | Yes | Primarily defines conversation, persistence and streaming behaviour. |
| `docs/adr/0025-*` / ADR-0025 | Product/feature specification | Accepted | `docs/specs/product-features/administration-and-tenant-control.md` | Yes | Primarily defines administrative capabilities and tenant-control behaviour. |
| `docs/adr/0026-*` / ADR-0026 | Mixed-purpose architectural decision | Accepted | `docs/adr/0026-operationalise-platform-observability-and-incident-response.md` | No | Makes platform administration, telemetry ownership, sampling and SLO architecture choices; runbooks remain under operations. |
| `docs/adr/0027-*` / ADR-0027 | Product/feature specification | Accepted | `docs/specs/product-features/product-interface-and-design-system.md` | Yes | Primarily defines user-interface behaviour, tokens and acceptance requirements. |
| `docs/adr/0028-*` / ADR-0028 | Architectural decision | Accepted | `docs/adr/0028-split-platform-operations-into-route-backed-sections.md` | No | Records the consequential route and page-ownership split. |
| `docs/adr/0029-*` / ADR-0029 | Architectural decision | Accepted | `docs/adr/0029-define-the-platform-testing-and-contract-verification-strategy.md` | No | Selects the platform test taxonomy and verification boundaries. |
| `docs/adr/0030-*` / ADR-0030 | Data/API contract specification | Accepted | `docs/specs/data-api-contracts/document-family-and-version-metadata.md` | Yes | Primarily defines metadata fields, constraints and ownership. |
| `docs/adr/0031-*` / ADR-0031 | Product/feature specification | Accepted | `docs/specs/product-features/version-governance-content-reuse-and-family-deletion.md` | Yes | Primarily specifies governance actions, reuse and deletion behaviour; sequencing is retained in the document. |
| `docs/adr/0032-*` / ADR-0032 | Architectural decision | Accepted | `docs/adr/0032-persist-a-structured-extraction-representation-and-authorise-source-delivery.md` | No | Chooses a persisted structured representation and source-delivery boundary. |
| `docs/adr/0033-*` / ADR-0033 | Product/feature specification | Accepted | `docs/specs/product-features/knowledge-library-navigation.md` | Yes | Primarily defines routes, read models and user-facing knowledge-library behaviour. |
| `docs/adr/0034-*` / ADR-0034 | Product/feature specification | Accepted | `docs/specs/product-features/import-staging-and-promotion.md` | Yes | Primarily defines import workflow states, matching and promotion behaviour. |
| `docs/adr/0035-*` / ADR-0035 | Product/feature specification | Accepted | `docs/specs/product-features/bulk-document-operations.md` | Yes | Primarily defines bulk-operation domain, workflow and UX requirements. |
| `docs/adr/0036-*` / ADR-0036 | Product/feature specification | Accepted | `docs/specs/product-features/document-governance-notifications-and-reminders.md` | Yes | Primarily defines notification, reminder, email and dashboard behaviour. |
| `docs/adr/0037-*` / ADR-0037 | Architectural decision | Accepted | `docs/adr/0037-require-live-provider-evidence-for-phase-28-pilot-readiness.md` | No | Records the decision to require a particular class of acceptance evidence. |
| `docs/adr/0038-*` / ADR-0038 | Architectural decision | Accepted | `docs/adr/0038-define-bounded-evidence-needs-for-conversational-retrieval.md` | No | Records a bounded retrieval-policy choice and alternatives. |
| `docs/adr/0039-*` / ADR-0039 | Architectural decision | Accepted | `docs/adr/0039-define-authorised-applicability-scope-facts.md` | No | Records an authorised-evidence mechanism and an explicitly accepted limitation. |
| `docs/adr/0040-*` / ADR-0040 | Product/feature specification | Accepted | `docs/specs/product-features/current-policy-question-pool.md` | Yes | Primarily defines profile selection and conversation presentation behaviour. |
| `docs/adr/0041-*` / ADR-0041 | Architectural decision | Accepted | `docs/adr/0041-use-stock-self-hosted-dify-for-single-client-delivery.md` | No | Selects stock self-hosted Dify for the bounded pilot and distinguishes the legacy custom RAG system. |

## Reference audit

Counts below are case-insensitive occurrences of the legacy identifier, former path or former linked filename in the repository before reclassification. Current navigation is updated to canonical descriptive titles and paths. Historical delivery statements, journals, source comments, tests, contracts, evaluation evidence, frozen/checksummed material and the forwarding records retain legacy identifiers where changing them would alter provenance or evidence.

| Legacy ID | Occurrences before | Current references updated | Historical references retained | Frozen references untouched | Remaining occurrences and reason |
|---|---:|---:|---|---|---|
| ADR-0010 | 111 | 13 | Yes | Yes | 102 — provenance records and historical implementation, journal, source, test, contract and evaluation references. |
| ADR-0011 | 88 | 13 | Yes | Yes | 79 — provenance records and historical implementation, journal, source, test, contract and evaluation references. |
| ADR-0015 | 102 | 4 | Yes | Yes | 102 — provenance records and historical ingestion delivery/evidence references. |
| ADR-0016 | 82 | 3 | Yes | Yes | 83 — provenance records and historical ingestion delivery/evidence references. |
| ADR-0017 | 231 | 3 | Yes | Yes | 232 — provenance records and historical temporal-authority implementation/evidence references. |
| ADR-0018 | 234 | 5 | Yes | Yes | 233 — provenance records and historical retrieval implementation/evidence references. |
| ADR-0019 | 169 | 4 | Yes | Yes | 169 — provenance records and historical evaluation policy, implementation and evidence references. |
| ADR-0020 | 69 | 1 | Yes | Yes | 72 — provenance records and historical evaluation clarification/evidence references. |
| ADR-0022 | 1,793 | 1 | Yes | Yes | 1,796 — predominantly immutable evaluation run identifiers and evidence, plus provenance records. |
| ADR-0023 | 144 | 4 | Yes | Yes | 144 — provenance records and historical generation implementation/evidence references. |
| ADR-0024 | 97 | 2 | Yes | Yes | 99 — provenance records and historical conversation/streaming delivery references. |
| ADR-0025 | 134 | 4 | Yes | Yes | 134 — provenance records and historical administration delivery references. |
| ADR-0027 | 86 | 15 | Yes | Yes | 75 — provenance records and historical product-design acceptance references. |
| ADR-0030 | 133 | 3 | Yes | Yes | 134 — provenance records and historical metadata delivery references. |
| ADR-0031 | 135 | 1 | Yes | Yes | 138 — provenance records and historical governance delivery references. |
| ADR-0033 | 47 | 2 | Yes | Yes | 49 — provenance records and historical knowledge-library delivery references. |
| ADR-0034 | 118 | 3 | Yes | Yes | 119 — provenance records and historical import delivery/acceptance references. |
| ADR-0035 | 88 | 2 | Yes | Yes | 90 — provenance records and historical bulk-operation audit/acceptance references. |
| ADR-0036 | 39 | 3 | Yes | Yes | 40 — provenance records and historical notification audit/acceptance references. |
| ADR-0040 | 7 | 1 | Yes | Yes | 10 — provenance records and historical implementation references. |

## Protected material and reconciliation

- No files under evaluation evidence, tests, contracts, application source, infrastructure or `docs/adr/gpt_drafts/` were moved or edited for reclassification.
- The baseline pilot worktree had uncommitted changes to `PROJECT_ROADMAP.md`, `tasks.json`, `docs/journal/2026-11-19-dolved-dify-delivery.md` and `docs/operations/dolved-dify/DOLVED-DIFY-DELIVERY.md`. Current-reference edits to the first two are independent but require reconciliation with that worktree before merge; the journal and delivery-plan working copies were not copied into this branch.
- The accepted Dify pilot decision and legacy custom RAG recovery documentation remain distinct. Reclassification does not declare the legacy system superseded beyond what recorded decisions state.
