RAG Platform Roadmap

Project Status: Planning / Repository Scaffold
Version: 0.1
Owner: David Oliver

⸻

Vision

Build a production-quality, AI-powered Retrieval Augmented Generation (RAG) platform that demonstrates modern software architecture, cloud-native engineering, AI integration, and scalable system design.

The platform is intended to showcase professional software engineering practices rather than simply demonstrate AI features.

Core objectives:

* Multi-tenant by design
* Cloud-native architecture
* Event-driven ingestion pipeline
* Production-quality codebase
* Excellent developer experience
* Fully containerised local development
* Infrastructure as Code
* Comprehensive testing
* Observable and maintainable
* Suitable as a flagship portfolio project

⸻

Guiding Principles

Every architectural decision should favour:

* Simplicity before cleverness
* Maintainability before optimisation
* Explicitness over magic
* Strong typing where practical
* Automation over manual processes
* Incremental delivery
* Production-first thinking
* Clear separation of responsibilities

⸻

Technology Stack

Frontend

* Next.js
* React
* TypeScript
* App Router

⸻

Backend

* Laravel
* PHP
* REST API
* Queues
* Events
* Policies

⸻

AI

* Python
* FastAPI
* LangChain (where appropriate)
* OpenAI
* Local models (future)

⸻

Data

* PostgreSQL
* Qdrant

⸻

Infrastructure

* Docker
* Docker Compose
* LocalStack
* Redis
* Mailpit
* Laravel Reverb

⸻

Cloud (Future)

* AWS ECS
* S3
* SQS
* CloudWatch
* Secrets Manager
* IAM
* Terraform

⸻

Repository Milestones

⸻

Phase 0 — Repository Foundation

Objectives

Establish the repository contract.

Tasks

* Create repository
* Initialise Git
* Create root folders
* Create README
* Create LICENSE
* Create Makefile
* Create .editorconfig
* Create .gitignore
* Create .env.example
* Initial commit

Deliverable

A clean, version-controlled monorepo ready for development.

⸻

Phase 1 — Application Scaffolding

Next.js

* Generate application
* Configure App Router
* Configure TypeScript
* Configure ESLint
* Create route groups
* Create feature folders
* Health page

⸻

Laravel

* Generate application
* Configure PostgreSQL
* Create API health endpoint
* Verify routing
* Verify migrations

⸻

Python

* Create package
* Configure pyproject
* Configure Ruff
* Configure MyPy
* Configure Pytest
* Create settings module

Deliverable

Three independently runnable applications.

⸻

Phase 2 — Docker Development Environment

Objectives

Each application runs inside Docker.

Tasks

* Web Dockerfile
* API Dockerfile
* AI Dockerfile
* Build verification
* Development volumes
* Health checks

Deliverable

Each image builds independently.

⸻

Phase 3 — Docker Compose

Objectives

Bring the platform together.

Tasks

* PostgreSQL
* API
* Web
* AI
* Docker network
* Named volumes
* Environment variables
* Bootstrap script

Deliverable

Entire platform starts using one command.

⸻

Phase 4 — Developer Experience

Objectives

Improve the daily workflow.

Tasks

* Root Makefile
* Bootstrap automation
* Lint command
* Test command
* Format command
* Logs
* Shell helpers

Deliverable

Developers interact with the repository through Make.

⸻

Phase 5 — Local AWS

Objectives

Replace mocked infrastructure with realistic local services.

Tasks

* LocalStack
* S3
* SQS
* Dead Letter Queue
* IAM configuration
* Queue verification

Deliverable

Local AWS-compatible environment.

⸻

Phase 6 — Authentication

Objectives

Secure the platform.

Tasks

* User registration
* Login
* Sanctum
* Session management
* Password reset
* API authentication

Deliverable

Secure authenticated platform.

⸻

Phase 7 — Multi-Tenancy

Objectives

Support multiple organisations.

Tasks

* Tenant model
* Organisations
* Memberships
* Roles
* Permissions
* Tenant middleware
* Tenant isolation

Deliverable

Tenant-aware application architecture.

⸻

Phase 8 — Document Storage

Objectives

Store uploaded documents.

Tasks

* Upload endpoint
* Storage abstraction
* S3 integration
* Metadata
* Validation
* Virus scanning (future)

Deliverable

Reliable document storage.

⸻

Phase 9 — Ingestion Pipeline

Objectives

Asynchronous document processing.

Tasks

* Upload event
* Queue message
* Python worker
* Status tracking
* Retry strategy
* Dead Letter Queue support

Deliverable

Robust asynchronous ingestion.

⸻

Phase 10 — Document Processing

Objectives

Extract useful text.

Tasks

* PDF parsing
* DOCX parsing
* TXT parsing
* Markdown parsing
* Metadata extraction
* Error handling

Deliverable

Normalised document content.

⸻

Phase 11 — Chunking

Objectives

Prepare text for embedding.

Tasks

* Chunk strategy
* Overlap
* Metadata
* Chunk IDs
* Tenant awareness

Deliverable

Consistent document chunks.

⸻

Phase 12 — Embeddings

Objectives

Generate vector representations.

Tasks

* Embedding service
* OpenAI integration
* Batch processing
* Retry logic
* Cost tracking

Deliverable

Searchable vector data.

⸻

Phase 13 — Vector Database

Objectives

Semantic search.

Tasks

* Qdrant
* Collections
* Metadata filters
* Similarity search
* Collection management

Deliverable

Working vector search.

⸻

Phase 14 — Retrieval

Objectives

Retrieve relevant context.

Tasks

* Similarity search
* Metadata filtering
* Ranking
* Context assembly

Deliverable

Accurate retrieval pipeline.

⸻

Phase 15 — AI Responses

Objectives

Generate grounded answers.

Tasks

* Prompt templates
* Context injection
* Token budgeting
* Citations
* Streaming responses

Deliverable

Reliable RAG responses.

⸻

Design Constraint — Citations and Re-extraction

Recorded 2026-07-30, arising from Phase 10 (ADR-0010) and its extracted-element
identity model.

Extracted element UUIDs are intentionally scoped to a single immutable
extraction run. If the platform later supports re-extracting an already-processed
document, any citation, chunk or embedding linked directly to elements from the
previous extraction may no longer reference the active extraction.

Before Phase 15 citation and answer-generation work begins implementation, the
citation and re-extraction design must explicitly decide:

* whether an extraction is permanently retained once referenced;
* whether re-extraction creates a new version alongside the previous one;
* whether chunking, embeddings and citations are rebuilt atomically;
* whether citations reference raw element UUIDs or a separate evidence identity;
* how historical answers continue to resolve their original evidence.

This is intentionally deferred until retrieval and answer generation provide
enough context to define the actual citation requirements. No Phase 10
implementation change is required.

⸻

Phase 16 — Chat Experience

Objectives

Excellent user interaction.

Tasks

* Conversations
* Message history
* Streaming UI
* Typing indicators
* Markdown rendering
* Source references

Deliverable

Production-quality chat interface.

⸻

Phase 17 — Administration

Objectives

Manage the platform.

Tasks

* Tenant dashboard
* User management
* Usage metrics
* Document management
* Queue monitoring

Deliverable

Complete administration tools.

⸻

Phase 18 — Observability

Objectives

Understand system behaviour.

Tasks

* Structured logging
* Metrics
* Health endpoints
* Correlation IDs
* Request tracing

Deliverable

Observable platform.

⸻

Phase 19 — Testing

Objectives

High confidence deployments.

Tasks

* Unit tests
* Integration tests
* API tests
* End-to-end tests
* Performance tests

Deliverable

Comprehensive test suite.

⸻

Phase 20 — Production Readiness

Objectives

Prepare for deployment.

Tasks

* Docker optimisation
* Secrets management
* Environment validation
* CI/CD
* Release process
* Backup strategy
* Disaster recovery

Deliverable

Production-ready platform.

⸻

Future Enhancements

* OCR
* Image understanding
* Audio transcription
* Video ingestion
* Hybrid search
* Re-ranking
* Multiple LLM providers
* Local LLM support
* Agent workflows
* Knowledge graphs
* Fine-tuning
* Analytics dashboard
* Billing
* API keys
* Webhooks
* Plugin architecture

⸻

Definition of Done

A phase is complete only when:

* All tasks are complete.
* Code is committed.
* Tests pass.
* Documentation is updated.
* ADRs are written where appropriate.
* The application runs locally.
* No known critical defects remain.

⸻

Progress

Phase	Status
Repository Foundation	⏳ In Progress
Application Scaffolding	⬜ Not Started
Docker Development	⬜ Not Started
Docker Compose	⬜ Not Started
Developer Experience	⬜ Not Started
Local AWS	⬜ Not Started
Authentication	⬜ Not Started
Multi-Tenancy	⬜ Not Started
Storage	⬜ Not Started
Ingestion Pipeline	⬜ Not Started
Processing	⬜ Not Started
Chunking	⬜ Not Started
Embeddings	⬜ Not Started
Vector Database	⬜ Not Started
Retrieval	⬜ Not Started
AI Responses	⬜ Not Started
Chat Experience	⬜ Not Started
Administration	⬜ Not Started
Observability	⬜ Not Started
Testing	⬜ Not Started
Production Readiness	⬜ Not Started

⸻

Remember: This roadmap is a living document. As the platform evolves, update it to reflect architectural decisions, completed milestones, and new priorities. The goal is not merely to finish the checklist, but to build a platform that demonstrates thoughtful engineering, clear architecture, and production-ready practices.