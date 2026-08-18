# dolved — RAG Platform Roadmap

| Field | Value |
|---|---|
| Project status | In Progress — Phase 13 of 23 (Embeddings) |
| Version | 0.1 |
| Owner | David Oliver |

---

## Vision

Build a production-quality, AI-powered Retrieval Augmented Generation (RAG)
platform that demonstrates modern software architecture, cloud-native
engineering, AI integration and scalable system design.

The platform is engineered to professional software engineering standards
throughout — architecture, testing, security and operability — not merely
assembled to demonstrate AI features.

### Core objectives

- Multi-tenant by design
- Cloud-native architecture
- Event-driven ingestion pipeline
- Production-quality codebase
- Excellent developer experience
- Fully containerised local development
- Infrastructure as Code
- Comprehensive testing
- Observable and maintainable
- Built to be run and maintained as genuine, ongoing production software

---

## Guiding principles

Every architectural decision should favour:

- Simplicity before cleverness
- Maintainability before optimisation
- Explicitness over magic
- Strong typing where practical
- Automation over manual processes
- Incremental delivery
- Production-first thinking
- Clear separation of responsibilities

---

## Technology stack

### Frontend

- Next.js
- React
- TypeScript
- App Router

---

### Backend

- Laravel
- PHP
- REST API
- Queues
- Events
- Policies

---

### AI

- Python
- FastAPI
- LangChain (where appropriate)
- OpenAI
- Local models (future)

---

### Data

- PostgreSQL
- Qdrant

---

### Infrastructure

- Docker
- Docker Compose
- LocalStack
- Redis
- Mailpit
- Laravel Reverb

---

### Cloud (future)

- AWS ECS
- S3
- SQS
- CloudWatch
- Secrets Manager
- IAM
- Terraform

---

## Repository milestones

---

## Phase 0 — Repository Foundation

### Objectives

Create the monorepo contract before generating applications.

### Tasks

- Create repository
- Initialise Git
- Create root folders
- Create README
- Create LICENSE
- Create Makefile
- Create `.editorconfig`
- Create `.gitignore`
- Create `.env.example`
- Initial commit

### Deliverable

A clean, version-controlled monorepo ready for development.

---

## Phase 1 — Application Scaffolding

### Next.js

- Generate application
- Configure App Router
- Configure TypeScript
- Configure ESLint
- Create route groups
- Create feature folders
- Health page

---

### Laravel

- Generate application
- Configure PostgreSQL
- Create API health endpoint
- Verify routing
- Verify migrations

---

### Python

- Create package
- Configure `pyproject.toml`
- Configure Ruff
- Configure MyPy
- Configure Pytest
- Create settings module

### Deliverable

Three independently runnable applications.

---

## Phase 2 — Independent Containerisation

### Objectives

Create an independently buildable development image for each application
before connecting them through Docker Compose.

### Tasks

- Containerise Next.js Web Application
- Containerise Laravel API Application
- Containerise Python AI Service

### Deliverable

Each image builds independently.

---

## Phase 3 — Docker Compose Platform

### Objectives

Connect the independently working application containers through the root
`compose.yaml`.

### Tasks

- Compose Application Services
- Add PostgreSQL
- Integrate Laravel with PostgreSQL
- Platform Health Verification

### Deliverable

Entire platform starts using one command.

---

## Phase 4 — Developer Interface

### Objectives

Expose a stable, memorable repository interface through the root Makefile.

### Tasks

- Add Core Make Targets
- Add Quality and Test Targets
- Add Bootstrap and Reset Targets

### Deliverable

Developers interact with the repository through Make.

---

## Phase 5 — Local AWS Development

### Objectives

Introduce local AWS-compatible infrastructure without requiring real cloud
resources during routine development.

### Tasks

- Add LocalStack
- Provision Local AWS Resources

### Deliverable

Local AWS-compatible environment.

---

## Phase 6 — Authentication and Identity

### Objectives

Establish secure user identity before introducing tenant-owned documents and conversations.

### Tasks

- Define Authentication Architecture
- Implement Laravel Authentication
- Implement Next.js Authentication UI

### Deliverable

Secure authenticated platform.

---

## Phase 7 — Multi-Tenancy

### Objectives

Ensure every tenant-owned resource is isolated by design rather than filtered as an afterthought.

### Tasks

- Define Tenant Model
- Implement Workspaces and Memberships
- Add Workspace-Aware Web Experience

### Deliverable

Workspace-aware application architecture.

---

## Phase 8 — Document Domain and Storage

### Objectives

Model tenant-owned documents and store source files safely before asynchronous ingestion begins.

### Tasks

- Define Document Lifecycle
- Implement Document Persistence
- Implement Direct Upload Flow

### Deliverable

Reliable document storage.

---

## Phase 9 — Event-Driven Ingestion

### Objectives

Decouple document upload from document processing through a durable,
tenant-aware and versioned ingestion workflow.

### Tasks

- Define the Ingestion Architecture and Event Contract
- Publish Ingestion Requests Reliably
- Consume and Claim Ingestion Requests

### Deliverable

Robust asynchronous ingestion.

---

## Phase 10 — Text Extraction and Normalisation

### Objectives

Convert supported source documents into a consistent internal representation
with traceable source metadata.

### Tasks

- Define Extracted Document Contract
- Implement Plain Text Extraction
- Implement PDF Extraction
- Implement DOCX Extraction
- Normalise Extracted Content

### Deliverable

Normalised document content.

---

## Phase 11 — Chunking

### Objectives

Split normalised documents into retrieval units while preserving enough
context and source metadata for accurate answers and citations.

### Tasks

- Define Chunk Contract
- Implement Baseline Chunker
- Evaluate Chunking Quality

### Deliverable

Consistent document chunks.

---

## Phase 12 — Observability Foundation

### Objectives

Make the platform observable by design, using OpenTelemetry as a
vendor-neutral instrumentation and correlation foundation, before
embeddings, retrieval and generation introduce the platform's first calls
to external AI providers.

### Tasks

- Define Telemetry and Observability Architecture
- Establish Local Telemetry Infrastructure
- Instrument Laravel with OpenTelemetry
- Instrument the Python AI Service with OpenTelemetry
- Verify Cross-Service Trace Propagation and the Privacy Allowlist

### Deliverable

A vendor-neutral, privacy-conscious observability foundation that every
later AI-pipeline phase builds on.

---

## Phase 13 — Embeddings

### Objectives

Generate reproducible vector representations while keeping model providers replaceable.

### Tasks

- Define Embedding Provider Boundary
- Implement Embedding Generation

### Deliverable

Searchable vector data.

---

## Phase 14 — Vector Storage

### Objectives

Persist tenant-isolated chunk vectors and metadata in a dedicated vector database.

### Tasks

- Define Vector Database Architecture
- Add Qdrant Development Service
- Persist Chunk Vectors
- Verify and Close the Vector Storage Foundation

### Deliverable

Working vector search.

---

## Phase 15 — Ingestion Orchestration

### Objectives

Decide and implement the authenticated, idempotent cross-service contract that
carries a Document from its existing processing claim through canonical chunk
persistence, embedding and vector persistence to an authoritative INDEXED or
FAILED outcome.

### Tasks

- Define End-to-End Ingestion Orchestration and Worker Result Contracts
- Define Ingestion Publication and Recovery Semantics
- Implement End-to-End Ingestion Orchestration

### Deliverable

A working, authenticated, idempotent, end-to-end ingestion pipeline: an
uploaded document reliably reaches INDEXED or a diagnosable FAILED state.

---

## Phase 16 — Retrieval

**Status:** Complete for the current engineering phase. EXP-0008 recorded
`0.9667` engineering Recall@K, `1.0000` clean-upstream Recall@K and retained all
36 correctly scoped expected EvidenceUnits through every downstream retrieval
stage. The planner is accepted with two documented residual content/event-time
versus authority-time risks. The calibrated threshold remains exact-lineage,
unpromoted and not sealed-held-out accepted.

### Objectives

Retrieve relevant, tenant-safe source chunks for a user query.

### Tasks

- Define Document Versioning and Temporal Authority
- Define Retrieval Planning, Eligibility and the Retriever Contract
- Implement Document Versioning and Temporal Authority Foundation
- Implement Semantic Retrieval
- Define Retrieval Evaluation and Quality Gates
- Implement Retrieval Evaluation
- Define Hybrid Retrieval and Reranking Architecture
- Implement Hybrid Retrieval and Reranking

### Deliverable

Accurate retrieval pipeline, with a repository-owned evaluation harness and
quality gate established before hybrid retrieval and reranking are built on
top of it.

---

### Design constraint — Query decomposition and the retrieval pipeline shape

Recorded 2026-08-03, arising from Phase 13 (ADR-0013)'s embedding/retrieval
boundary discussion, formalised in Phase 16's Retrieval Planning,
Eligibility and Retriever Contract ADR (Stage 16.2, ADR-0018, accepted
2026-08-07): `RetrievalPlan.retrieval_queries` is the query-planning
boundary this constraint describes, exercised for V1 only by an
identity/no-op planner exactly as anticipated here.

The retrieval architecture must be shaped so query decomposition can be
enabled later without rewriting the retrieval pipeline:

```text
User query
    -> query-planning boundary
    -> one or more bounded retrieval queries
    -> retrieve each query
    -> merge and deduplicate evidence
    -> rerank against the original user question
    -> generate one grounded answer
```

For V1, the query-planning boundary exists in the contract but is not
exercised: an identity/no-op planner returns only the original query, and
model-assisted decomposition remains disabled by default. Enabling it later
is an additive change to the planner, not a retrieval-pipeline rewrite — but
it must not be turned on until the repository-owned evaluation harness
(Stage 16.5) demonstrates that the quality gain justifies the added latency,
cost and complexity of a second model call per query.

This is intentionally deferred rather than actioned now — no Phase 16
implementation exists yet for this constraint to apply to.

---

## Phase 17 — Grounded Generation

**Status:** Complete on 2026-08-17. R17-S01 through R17-S04 and the Phase 17
acceptance gate are closed. The next session is R18-S01 — Define Conversation
Domain.

### Objectives

Generate answers that are constrained by retrieved evidence and accompanied by verifiable citations.

### Tasks

- Define Generation Provider Boundary
- Build Grounded Prompt Assembly
- Generate Answers with Citations
- Add Answer Evaluation

### Deliverable

Reliable RAG responses.

**Completion evidence:** ADR-0023's provider-neutral generation architecture
is implemented through deterministic request assembly, typed grounded outcomes,
citation-bound AnswerParts, durable evidence snapshots, the OpenAI/gpt-5-mini
adapter and bounded answer evaluation. GEN-EXP-0001 preserves the original
provider-backed baseline; GEN-EXP-0002 preserves the evaluator-only corrected
replay with zero generation calls. The corrected bounded population recorded
13/13 deterministic outcome correctness and complete advisory semantic metric
coverage, with the same-model evaluator limitation retained explicitly.

---

### Design constraint — Citations and re-extraction

Recorded 2026-07-30, arising from Phase 10 (ADR-0010) and its extracted-element
identity model.

Extracted element UUIDs are intentionally scoped to a single immutable
extraction run. If the platform later supports re-extracting an already-processed
document, any citation, chunk or embedding linked directly to elements from the
previous extraction may no longer reference the active extraction.

Before Phase 17 citation and answer-generation work begins implementation, the
citation and re-extraction design must explicitly decide:

- whether an extraction is permanently retained once referenced;
- whether re-extraction creates a new version alongside the previous one;
- whether chunking, embeddings and citations are rebuilt atomically;
- whether citations reference raw element UUIDs or a separate evidence identity;
- how historical answers continue to resolve their original evidence.

This is intentionally deferred until retrieval and answer generation provide
enough context to define the actual citation requirements. No Phase 10
implementation change is required.

**Resolved 2026-08-16 by ADR-0023** (Stage 17.1): extraction runs remain
permanently immutable per ADR-0010 (unchanged); citations reference a
durable, per-answer `EvidenceSnapshot` — a separate evidence identity, not
the raw element UUID directly — that stores the cited text verbatim
alongside its canonical lineage, so a historical answer resolves its own
evidence independently of any later re-extraction. See ADR-0023 for the
full design.

---

### Design constraint — Quality lineage across the pipeline

Recorded 2026-08-03, arising from Phase 13 (ADR-0013)'s embedding-profile
lineage requirement.

Every answer or evaluation result should eventually be attributable to the
quality-affecting configuration that produced it, including — where each
becomes relevant — extraction/parser identity, normaliser version, chunking
strategy/profile, embedding profile, vector generation, retrieval
configuration, sparse retrieval configuration, fusion method/configuration,
reranker provider/model/profile, query-planner identity/configuration where
used, prompt-template version, and generation provider/model/configuration.

No single ADR owns this whole chain. ADR-0013 owns the embedding-profile
link; the Phase 14 vector-storage ADR owns the vector-generation link; the
Phase 16 retrieval and hybrid-retrieval/reranking ADRs own the retrieval,
fusion and reranker links; the Phase 17 generation ADR owns the
prompt-template and generation-configuration links. Each future ADR is
expected to record its own piece of this chain explicitly, consistent with
how it stores and exposes that configuration, rather than this constraint
prescribing a single cross-cutting lineage schema in advance of any of them
existing.

This is intentionally deferred rather than actioned now — no Phase 13
implementation change is required, and each named ADR records its own
lineage fields when it is actually written.

**Phase 17 link recorded 2026-08-16 by ADR-0023** (Stage 17.1): a versioned
`generation_fingerprint`, covering provider, model, generation contract
version, prompt version, adapter version and an explicitly defined
quality-affecting configuration set, persisted with every generated answer.
See ADR-0023 for the full design; the chain's other links remain owned by
their respective ADRs as already recorded above.

---

## Phase 18 — Conversation and Streaming

**Status:** In progress. R18-S01 and R18-S02 completed on 2026-08-18;
R18-S03 incremental delivery is next.

### Objectives

Expose the RAG workflow as a persistent, streaming conversational experience.

### Tasks

- Define Conversation Domain
- Implement Chat Orchestration API
- Implement Streaming Responses
- Build Chat Interface

### Deliverable

Production-quality chat interface.

**Architecture boundary recorded 2026-08-18 by ADR-0024** (Stage 18.1):
tenant-owned conversations and visible messages are separated from durable,
connection-independent generation runs; Laravel retains tenancy,
authorisation, retrieval, orchestration, validation, persistence and SSE
projection; Python provides authenticated provider-neutral contextualisation
and generation capabilities but never retrieves. The ADR fixes the complete
retrieval-outcome-to-conversation mapping, authoritative versus provisional
streaming persistence, retry/cancellation/idempotency semantics and the
`generation.stream`/SSE transport boundary. R18-S02 implements orchestration;
R18-S03 implements incremental delivery; R18-S04 builds the chat interface.

**Application boundary implemented 2026-08-18** (Stage 18.2): Laravel now
persists tenant-scoped conversations, immutable messages, durable queued runs,
contextualisation and retrieval snapshots, controlled assistant outcomes and
the authoritative Phase 17 generated-answer graph. Submission/retry are
idempotent, cancellation and abandoned-worker timeout reconciliation fail
closed, and only `EVIDENCE_FOUND` reaches generation. Python exposes a bounded,
authenticated contextualisation capability but does not retrieve or authorise.
Browser streaming was deliberately not started and remains R18-S03.

---

## Phase 19 — Administration

### Objectives

Provide operational visibility and safe tenant-level controls.

### Tasks

- Build Document Administration
- Build Tenant and Membership Administration
- Add Usage Visibility

### Deliverable

Complete administration tools.

---

## Phase 20 — Observability and Operations

### Objectives

Make failures, latency and cross-service behaviour diagnosable.

### Tasks

- Standardise Structured Logging
- Add Metrics
- Add Distributed Tracing
- Define Operational Alerts

### Deliverable

Observable platform.

---

### Design constraint — Phase 20 should operationalise, not rebuild, observability

Recorded 2026-07-30, arising from Phase 12 (ADR-0012) and its OpenTelemetry
observability foundation.

Phase 12 already establishes OpenTelemetry as the platform's canonical
instrumentation API, the Collector as the backend-routing boundary, and the
metrics/tracing principles (privacy allowlist, cardinality discipline,
context propagation) that a distributed-tracing and metrics stage would
otherwise need to invent from scratch. This phase's Tasks list — in
particular "Add Metrics" and "Add Distributed Tracing" — predates that
decision and, read literally, now substantially duplicates it.

When Phase 20 is eventually reviewed, before implementation begins, its
scope should shift from *building* observability to *operationalising* it:
assume the OpenTelemetry foundation from Phase 12 is already in place, and
focus this phase on what that foundation does not itself provide —
operational dashboards, alerting, SLOs, production diagnostics and
runbooks. "Standardise Structured Logging" remains a distinct, legitimate
concern of its own (logging is not something Phase 12 establishes), as does
"Define Operational Alerts" (already an operational-layer concern, not a
foundational one).

This is intentionally deferred rather than actioned now — no change to this
phase's Tasks, Objectives or Deliverable has been made yet.

---

## Phase 21 — Testing and Quality Strategy

### Objectives

Create a layered test strategy that catches regressions without requiring
every check to be an expensive end-to-end test.

### Tasks

- Establish Test Taxonomy
- Add Contract Tests
- Add End-to-End Ingestion Tests
- Add End-to-End Chat Tests
- Add Security-Focused Tests

### Deliverable

Comprehensive test suite.

---

## Phase 22 — CI/CD and Production Readiness

### Objectives

Make the platform reproducibly testable, buildable, deployable and operable
outside a developer laptop.

### Tasks

- Add Continuous Integration
- Create Production Container Builds
- Add Infrastructure as Code
- Configure Secrets and Environment Management
- Add Database Backup and Recovery
- Define Vector Index Recovery
- Perform Security Hardening
- Create Staging Deployment
- Production Readiness Review

### Deliverable

Production-ready platform.

---

## Phase 23 — Documentation and Demonstration Readiness

### Objectives

Document the platform clearly and provide a reproducible way to demonstrate
its capabilities, without letting presentation work substitute for
engineering substance.

### Tasks

- Write Architecture Documentation
- Create Demonstration Dataset and Scenario
- Finalise Repository README

### Deliverable

A clearly documented platform with a reproducible demonstration of its core
capabilities.

---

## Future enhancements

- OCR
- Image understanding
- Audio transcription
- Video ingestion
- Hybrid search
- Re-ranking
- Multiple LLM providers
- Local LLM support
- Agent workflows
- Knowledge graphs
- Fine-tuning
- Analytics dashboard
- Billing
- API keys
- Webhooks
- Plugin architecture

---

## Definition of done

A phase is complete only when:

- All tasks are complete.
- Code is committed.
- Tests pass.
- Documentation is updated.
- ADRs are written where appropriate.
- The application runs locally.
- No known critical defects remain.

---

## Progress

| Phase | Status |
|---|---|
| Repository Foundation | ✅ Complete |
| Application Scaffolding | ✅ Complete |
| Independent Containerisation | ✅ Complete |
| Docker Compose Platform | ✅ Complete |
| Developer Interface | ✅ Complete |
| Local AWS Development | ✅ Complete |
| Authentication and Identity | ✅ Complete |
| Multi-Tenancy | ✅ Complete |
| Document Domain and Storage | ✅ Complete |
| Event-Driven Ingestion | ✅ Complete |
| Text Extraction and Normalisation | ✅ Complete |
| Chunking | ✅ Complete |
| Observability Foundation | ✅ Complete |
| Embeddings | ✅ Complete |
| Vector Storage | ✅ Complete |
| Ingestion Orchestration | ⬜ Not Started |
| Retrieval | ⬜ Not Started |
| Grounded Generation | ⬜ Not Started |
| Conversation and Streaming | ⬜ Not Started |
| Administration | ⬜ Not Started |
| Observability and Operations | ⬜ Not Started |
| Testing and Quality Strategy | ⬜ Not Started |
| CI/CD and Production Readiness | ⬜ Not Started |
| Documentation and Demonstration Readiness | ⬜ Not Started |

---

> **Remember:** This roadmap is a living document. As the platform evolves,
> update it to reflect architectural decisions, completed milestones and new
> priorities. The goal is not merely to finish the checklist, but to build a
> platform that demonstrates thoughtful engineering, clear architecture and
> production-ready practices.
