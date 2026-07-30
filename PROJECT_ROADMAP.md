# dolved — RAG Platform Roadmap

| Field | Value |
|---|---|
| Project status | In Progress — Phase 12 of 22 (Embeddings) |
| Version | 0.1 |
| Owner | David Oliver |

---

## Vision

Build a production-quality, AI-powered Retrieval Augmented Generation (RAG)
platform that demonstrates modern software architecture, cloud-native
engineering, AI integration and scalable system design.

The platform is intended to showcase professional software engineering
practices rather than simply demonstrate AI features.

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
- Suitable as a flagship portfolio project

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

## Phase 12 — Embeddings

### Objectives

Generate reproducible vector representations while keeping model providers replaceable.

### Tasks

- Define Embedding Provider Boundary
- Implement Embedding Generation

### Deliverable

Searchable vector data.

---

## Phase 13 — Vector Storage

### Objectives

Persist tenant-isolated chunk vectors and metadata in a dedicated vector database.

### Tasks

- Define Vector Database Architecture
- Add Qdrant Development Service
- Persist Chunk Vectors
- Complete Ingestion Pipeline

### Deliverable

Working vector search.

---

## Phase 14 — Retrieval

### Objectives

Retrieve relevant, tenant-safe source chunks for a user query.

### Tasks

- Define Retrieval Contract
- Implement Semantic Retrieval
- Add Retrieval Evaluation
- Introduce Retrieval Enhancements

### Deliverable

Accurate retrieval pipeline.

---

## Phase 15 — Grounded Generation

### Objectives

Generate answers that are constrained by retrieved evidence and accompanied by verifiable citations.

### Tasks

- Define Generation Provider Boundary
- Build Grounded Prompt Assembly
- Generate Answers with Citations
- Add Answer Evaluation

### Deliverable

Reliable RAG responses.

---

### Design constraint — Citations and re-extraction

Recorded 2026-07-30, arising from Phase 10 (ADR-0010) and its extracted-element
identity model.

Extracted element UUIDs are intentionally scoped to a single immutable
extraction run. If the platform later supports re-extracting an already-processed
document, any citation, chunk or embedding linked directly to elements from the
previous extraction may no longer reference the active extraction.

Before Phase 15 citation and answer-generation work begins implementation, the
citation and re-extraction design must explicitly decide:

- whether an extraction is permanently retained once referenced;
- whether re-extraction creates a new version alongside the previous one;
- whether chunking, embeddings and citations are rebuilt atomically;
- whether citations reference raw element UUIDs or a separate evidence identity;
- how historical answers continue to resolve their original evidence.

This is intentionally deferred until retrieval and answer generation provide
enough context to define the actual citation requirements. No Phase 10
implementation change is required.

---

## Phase 16 — Conversation and Streaming

### Objectives

Expose the RAG workflow as a persistent, streaming conversational experience.

### Tasks

- Define Conversation Domain
- Implement Chat Orchestration API
- Implement Streaming Responses
- Build Chat Interface

### Deliverable

Production-quality chat interface.

---

## Phase 17 — Administration

### Objectives

Provide operational visibility and safe tenant-level controls.

### Tasks

- Build Document Administration
- Build Tenant and Membership Administration
- Add Usage Visibility

### Deliverable

Complete administration tools.

---

## Phase 18 — Observability and Operations

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

## Phase 19 — Testing and Quality Strategy

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

## Phase 20 — CI/CD and Production Readiness

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

## Phase 21 — Portfolio and Demonstration Readiness

### Objectives

Present the platform as a credible 2027 engineering portfolio project without
allowing presentation work to replace engineering substance.

### Tasks

- Write Architecture Documentation
- Create Demonstration Dataset and Scenario
- Finalise Repository README

### Deliverable

A polished, demonstrable, portfolio-ready platform.

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
| Embeddings | ⏳ In Progress |
| Vector Storage | ⬜ Not Started |
| Retrieval | ⬜ Not Started |
| Grounded Generation | ⬜ Not Started |
| Conversation and Streaming | ⬜ Not Started |
| Administration | ⬜ Not Started |
| Observability and Operations | ⬜ Not Started |
| Testing and Quality Strategy | ⬜ Not Started |
| CI/CD and Production Readiness | ⬜ Not Started |
| Portfolio and Demonstration Readiness | ⬜ Not Started |

---

> **Remember:** This roadmap is a living document. As the platform evolves,
> update it to reflect architectural decisions, completed milestones and new
> priorities. The goal is not merely to finish the checklist, but to build a
> platform that demonstrates thoughtful engineering, clear architecture and
> production-ready practices.
