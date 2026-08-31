# dolved — RAG Platform Roadmap

| Field | Value |
|---|---|
| Project status | In Progress — Phase 25 of 30 (Import Staging and Promotion) |
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

**Status:** Completed on 2026-08-18. R18-S01 through R18-S04 and the Phase 18
acceptance gate are closed.

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
Browser streaming was deliberately not started in R18-S02.

**Incremental delivery implemented 2026-08-18** (Stage 18.3): the configured
streaming generation profile now uses a distinct authenticated
`generation.stream` NDJSON response. Laravel validates complete answer-part
candidates before storing bounded, non-authoritative delivery projections and
serves only application-owned events through tenant-scoped, replayable SSE.
Terminal answer persistence remains atomic and authoritative; provisional text
is retractable, expires, and never enters conversation history. A credentialed
browser client now handles ordered replay and terminal closure, ready for the
R18-S04 interface.

**Chat interface implemented 2026-08-18** (Stage 18.4): the authenticated
workspace now exposes tenant-scoped conversation history, new-chat and message
submission, visible replayable progress and provisional answer parts,
authoritative terminal replacement, citation-reference inspection,
cancellation/retry controls and non-destructive error handling. Keyboard and
responsive behaviour are covered by the web implementation and its critical
interaction tests. The interface searches all documents made eligible by the
existing authority/applicability pipeline; it does not invent a selectable
document-filter API. Durable citation inspection uses the already-persisted,
tenant-scoped answer-part and evidence-snapshot graph rather than trusting
provisional delivery data.

**Acceptance gate closed 2026-08-18:** an independent two-pass full-stack
review found no blockers and verified the ADR-0024 ownership, streaming,
failure, persistence, tenancy and citation invariants. The visual gate also
removed legacy branding from another project and bound all shared web shells to
the Dolved identity. Phase 19 administration is next.

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

**Architecture boundary accepted 2026-08-19 through ADR-0025:** the fixed
owner/admin/member capability model governs administrative actions; Laravel owns
tenant authority and durable transitions while Python performs only authenticated,
Laravel-scoped provider-native work. Document deletion is asynchronous and waits
for ingestion quiescence before verified cleanup, preserving historical
`EvidenceSnapshot` records independently of deleted source chunks. Historical
usage is backed by content-free, deletion-independent activity records and is a
tenant-facing estimate/projection rather than billing-grade accounting.

**Document administration implemented 2026-08-19** (Stage 19.1): active
workspace members can inspect tenant-scoped document state, metadata, warnings
and safe failures. Owner/admin retry is idempotent. Owner/admin deletion is a
durable asynchronous operation that establishes ingestion quiescence before
Laravel-scoped Python vector cleanup, then removes source objects and chunks
without erasing historical citation snapshots.

**Tenant and membership administration implemented 2026-08-19** (Stage 19.2):
owners and admins can inspect current membership and administer only the roles
permitted by ADR-0025. Invitations are digest-backed, verified-email-bound,
one-time-link and independently valid from their delivery attempt. Role changes,
member removal, voluntary leave and row-locked ownership transfer are durable,
idempotent and audited. Existing SSE connections now reauthorize membership on
a bounded interval and terminate safely after revocation.

**Usage visibility implemented 2026-08-20** (Stage 19.3): Laravel persists
content-free, deletion-independent workspace activity and normalised usage
records rather than deriving history from live content rows. Current
document, logical-source-byte and indexed-chunk gauges are calculated
separately from bounded 7-day, 30-day and current-month historical activity.
Ingestion, retrieval and generation usage record stage-aware token, latency
and cost lineage without storing source text, questions, answers or provider
payloads. Provider-reported, estimated, unavailable and genuinely local
zero-cost values remain distinct, and the surface is owner/admin only.

**Acceptance gate closed 2026-08-20:** a full repository-boundary sweep
(`make format-check lint typecheck test ps`, `make aws-status`, and each
service's suite individually where the chain stopped) found and fixed four
genuine Phase 19 gaps invisible to any individual session's focused
verification — an over-broad orchestrator type dependency narrowed to two
purpose-built Protocols, a missing whole-repository mount that had silently
prevented Stage 19.3's own branding regression test from ever running
inside its container, a stale build-cache artefact, and pre-existing
unrelated lint debt blocking the lint chain entirely. It also identified,
and deliberately left unfixed as out of scope, technical debt predating
Phase 19: unrelated Mypy errors in five Phase 16/17-era evaluation test
files and nine Laravel/Python test failures (both services) caused by a
missing `/evaluation/engineering/` fixture corpus. Phase 20 observability
is next.

---

## Phase 20 — Observability and Operations

### Objectives

Make failures, latency and cross-service behaviour diagnosable.

### Tasks

- Standardise Structured Logging
- Build Operational Metrics, Platform Operations Foundation and Dashboard
- Complete Trace Coverage, Collector Sampling and Operational-Policy Reconciliation
- Define SLOs, Alerts and Runbooks

### Deliverable

Observable platform.

---

### Accepted architecture — operationalise, do not rebuild, observability

Recorded 2026-07-30, arising from Phase 12 (ADR-0012) and its OpenTelemetry
observability foundation.

Phase 12 already establishes OpenTelemetry as the platform's canonical
instrumentation API, the Collector as the backend-routing boundary, and the
metrics/tracing principles (privacy allowlist, cardinality discipline,
context propagation) that a distributed-tracing and metrics stage would
otherwise need to invent from scratch. This phase's Tasks list — in
particular "Add Metrics" and "Add Distributed Tracing" — predates that
decision and, read literally, now substantially duplicates it.

Accepted 2026-08-20 through
[ADR-0026](docs/adr/0026-operationalise-platform-observability-and-incident-response.md).
Phase 20 assumes the OpenTelemetry foundation from Phase 12 is already in
place. It adds the missing operational layer: a shared privacy-safe logging
boundary; complete operational metrics and a separately-authorised platform
operations surface; coherent cross-service traces with Collector-owned
sampling and explicit desired-policy reconciliation; and calibrated SLOs,
alerts and runbooks. Business audit, tenant usage and operational telemetry
remain separate systems with separate authority and retention lifecycles.

**Stage 20.1 completed 2026-08-20:** Laravel, Python HTTP/worker processes and
Next.js server code now emit one central, allowlisted JSON vocabulary. Stable
event names and bounded trace/correlation identifiers remain useful while
arbitrary context and exception messages are excluded, and formatter failures
cannot fail ordinary application work.

**Stage 20.2 completed 2026-08-20:** Dolved now has a separately authorised
platform-operations plane. Platform administrators are independent of tenant
roles, are granted only through an authenticated non-browser command with
atomic audit history and last-administrator protection, and are checked live.
Bounded operational metrics now cover the core application, provider,
dependency and queue surfaces. A curated server-rendered health dashboard uses
only fixed backend queries, filters response labels and reports unavailable
signals truthfully without affecting ordinary use.

**Stage 20.3 completed 2026-08-20:** One W3C trace context now crosses
Laravel-to-Python rc1 calls, retry/deletion outbox work and queued generation,
with missing provider and administration spans constrained by shared privacy
allowlists. The Collector is the sole probabilistic sampling owner.
Operational settings are immutable desired-policy versions whose status is
derived only from authenticated, append-only, per-target reconciliation
evidence; stale, conflicting and replayed acknowledgements fail closed. A
real local Collector target reached `ACTIVE` and a Tempo trace joined Laravel,
publisher and Python worker spans without exposing the synthetic privacy
marker.

**Stage 20.4 completed 2026-08-20:** Prometheus now evaluates repository-owned
SLI recording and operational alert rules, with Alertmanager owning firing
state, grouping, acknowledgement and silencing. Dolved exposes only bounded,
read-only SLO and active-alert summaries to platform administrators and links
to specialist consoles for diagnosis. The provisional 99% objectives reuse
ADR-0024 terminal-outcome semantics, report missing evidence truthfully and do
not claim calibrated production latency. Every enabled alert has impact,
severity, response expectations and a runbook; capacity, telemetry-absence,
multi-window burn and final latency alerts remain deferred until production
signals can justify them.

**Acceptance gate closed 2026-08-21:** an independent review confirmed all
nine ADR-0026 implementation areas. A standing `make test` guard now proves
the pinned Collector retains its probabilistic-sampling processor and validates
the committed pipeline, and ADR-0026 now contains a dated factual clarification
of the existing three credential families without changing any trust boundary.
The verification debt recorded at the Phase 19 gate was cleared separately in
commit `fb294ed`. The final full repository chain passed with Laravel 330
passed / 2 skipped / 1,606 assertions and Python 562 passed / 4 skipped;
formatting, lint, Mypy, TypeScript, container health and local AWS checks were
all clean. Phase 21 product experience and interface design is next.

---

## Phase 21 — Product Experience and Interface Design

### Objectives

Give the product's chat, document and administration surfaces one coherent,
accessible, production-quality interface built on a shared design system,
with particular emphasis on the administration surface Phase 19 built
without a design pass — but not limited to it, since redesigning only
administration would make it read as a different application from chat,
documents and the shared shell.

ADR-0027 is the accepted product-wide architecture boundary: one adaptive, route-backed
shell; repository-owned design tokens and components; dark-default explicit
theming; a WCAG 2.2 AA baseline; and a bounded Laravel-owned citation/source
presentation contract. The accepted route tree includes durable conversation
URLs, workspace administration sections and `/app/platform/operations`.
ADR-0028 narrowly extends that boundary for Platform Operations with four
route-backed sections, a platform contextual-navigation region and the
ADR-0026-conformant concealed authorization response.

### Tasks

- Define the Product Design System
- Design the Administration Experience
- Implement Complete Interface States
- Visual and Usability Acceptance
- Split Platform Operations into Route-Backed Sections

**Design-system foundation completed 2026-08-21** (Stage 21.1): Tailwind v4,
repository-owned shadcn/Radix primitives, explicit dark/light themes, semantic
tokens and contrast guards now underpin one adaptive route-backed shell. The
development/test-only component reference was visually accepted in both themes;
Stage 21.2 can now apply this shared language to administration without
inventing a separate interface system.

**Phase 21 completed 2026-08-21:** the design system is now applied across
public, authentication, chat, documents, workspace administration and Platform
Operations. The final gate completed ADR-0027's safe citation/source contract,
tenant-safe deep-link concealment, typed timeout/retraction states and bounded
streaming announcements, then implemented ADR-0028's four route-backed Platform
Operations sections. Independent review and the complete repository suite found
no remaining conformance blocker. Phase 22 can now define the platform-wide
testing taxonomy against a stable product interface.

### Deliverable

A product-wide, accessible design system applied consistently across chat,
documents and administration, with an explicit visual and usability
acceptance gate.

---

## Phase 22 — Testing and Quality Strategy

### Objectives

Create a layered test strategy that catches regressions without requiring
every check to be an expensive end-to-end test.

### Tasks

- Establish Test Taxonomy
- Add Contract Tests
- Add End-to-End Ingestion Tests
- Add End-to-End Chat Tests
- Add Security-Focused Tests

**Testing taxonomy completed 2026-08-22** (Stage 22.1): accepted ADR-0029
now defines fifteen truthful test categories, the shared-schema contract
boundary, isolated deterministic E2E topology and separate historical,
current-orchestration and live-quality evaluation evidence. The operational
reference records fixture ownership, cleanup, flake/quarantine policy and the
required-versus-optional Phase 22 gate. Stage 22.2 can now add the missing
cross-language contract schemas and verification without inventing another
contract authority.

**Shared contract verification completed 2026-08-22** (Stage 22.2): all
twelve signed worker operations now have versioned request/response contracts,
shared positive and negative fixtures, and cross-language signing proof. The
Retrieval rc1 surface is exercised from one shared fixture inventory in both
languages. Provider-free retrieval-policy and generation-evidence gates are
now executable Make targets: historical V1 evidence remains immutable and is
loaded through its accepted comparison adapter, while deterministic generation
checks are reproduced without silently regenerating semantic model scores.
Stage 22.3 can now build the isolated full-path ingestion E2E layer on these
contract boundaries.

**Deterministic ingestion regression completed 2026-08-22** (Stage 22.3): the
isolated browser journey now proves upload, real queue processing, parsing,
chunking, deterministic dense/sparse materialisation, Qdrant retrieval,
observable corruption failure and tenant concealment. Its independently
reviewed 42-case / 126-variant current-retrieval result is promoted only as a
deterministic orchestration-regression baseline, not as live-provider quality
evidence.

**End-to-end chat verification completed 2026-08-23** (Stage 22.4): the same
disposable environment now crosses authenticated conversation creation,
contextualisation, planning, retrieval, grounded generation, citations, source
navigation, persistence, controlled insufficiency and native SSE replay. A
foreign workspace cannot retrieve the evidence or open the conversation or
source route. All provider-facing components are deterministic and fail closed;
no external provider call or protected evaluation input is involved.

**Security regression verification completed 2026-08-23** (Stage 22.5):
provider-free checks now cover the highest-value tenant, upload, storage,
authentication, queue-envelope and generation-authority boundaries. The
optional bounded live prompt-injection evaluator remains explicitly separate
from deterministic proof and was not invoked at closure.

**Phase 22 completed 2026-08-23**: the full fast tier, cross-language
contracts, isolated browser journey, real-SPLADE integration and all three
required provider-free evaluation gates passed. The browser journey now
asserts visible progress before forced SSE reconnection and terminal grounded
completion. Optional live-provider evaluation remains non-gating and no live
provider calls were made during closure.

### Deliverable

Comprehensive test suite.

---

## Phase 23 — Document Metadata, Governance and Structured Content

### Objectives

Implement the accepted ADR-0030–0032 foundation in its binding dependency
order: document-family/version metadata first, then the governance primitives
that do not depend on structured extraction, then structured extraction and
source delivery, and finally governance paths that require that extraction
identity.

### Sessions

- R23-S01a–S01d: ADR-0030 migration/domain model, policies/API, backfill and tests.
- R23-S02a: ADR-0031 governance routes, resources and policies that can safely
  precede structured extraction.
- R23-S03a–S03e: ADR-0032 schema/digests, worker acknowledgement, projection
  publication, source/extracted-text delivery and acceptance evidence.
- R23-S02b–S02d: ADR-0031 clone orchestration, family deletion/tombstones and
  full governance tests after ADR-0032 is available.

Deletion must retain a tested no-op export-hold seam at the reserved
`documents.id` coordination point. ADR-0037 alone will define the eventual
export/interchange contract; no export behavior is part of this phase.

**Structured-extraction foundation verified 2026-08-29**: R23-S03a–S03e are
complete with cross-language digests, bounded artifact/projection processing,
atomic publication, tenant-authorised source/extracted-text delivery and
provider-free acceptance measurements. R23-S02b may now implement clone
orchestration against that verified identity.

**Phase 23 completed 2026-08-29**: ADR-0030 metadata, ADR-0032 structured
extraction and ADR-0031 version governance now pass the complete provider-free
acceptance matrix. Clone publication and fallback cleanup are target-lineage
bound, family deletion converges through existing child deletion, and the
reserved export-hold boundary remains a tested no-op seam. No providers were
called and no retrieval, generation, calibration, threshold or benchmark
policy changed.

### Deliverable

Verified metadata, governance and structured-content foundations for the
knowledge library and import lifecycle.

---

## Phase 24 — Knowledge Library Product Interface

### Objectives

Implement ADR-0033's route-backed knowledge-library experience after the Phase
23 data and delivery contracts exist.

### Sessions

R24-S01 through R24-S07 and R24-S09 implement the contextual shell, library
table, family detail, source viewing, comparison, saved views/category
settings, small-corpus onboarding and deleted/history presentation. ADR-0033's
import-through-grounded-answer Playwright journey remains mandatory but, under
its dated implementation-sequencing clarification, executes at R25-S07 after
the real ADR-0034 import workflow exists. No legacy-upload substitute is
accepted.

Every user-facing session stops at its staged light/dark, desktop/mobile,
keyboard/focus and state/error visual checkpoint for David's explicit review
before closure. Export UI remains reserved for ADR-0037.

### Deliverable

A reviewed, accessible knowledge-library interface built on authoritative
family, version and extraction data. This phase gate accepts the implemented
library surfaces; final import-flow acceptance remains at the mandatory Phase
25 gate. Completed on 2026-08-30 with the deferred ADR-0033 journey retained
unchanged as a binding Phase 25 acceptance requirement.

---

## Phase 25 — Import Staging and Promotion

### Objectives

Implement ADR-0034's isolated staging, deterministic preflight/matching and
atomic promotion lifecycle after the library foundation is available.

### Sessions

R25-S01 through R25-S07 implement schema/privacy, preflight, matching,
promotion, legacy cutover/drain, workflow/progress UI, and provider-free plus
Playwright verification in the binding ADR order. R25-S07 also owns ADR-0033's
unchanged nine-step import-through-grounded-answer journey; the Phase 25 gate
cannot pass until that journey proves genuine readiness through the real
`ImportBatch` flow.

The user-facing workflow session requires every ADR-0034 visual checkpoint and
David's approval before it closes.

### Deliverable

A tenant-safe, recoverable import-staging and promotion workflow.

**Completed 2026-08-31.** The real `ImportBatch` workflow passed its mandatory
provider-free browser acceptance: ten representative documents were staged,
resumed, reviewed, promoted, indexed and approved into ten genuinely searchable
families before a corpus-supported question produced a grounded answer with a
valid source citation. The same gate also proved controlled insufficiency and
stream reconnection, tenant concealment, exact-duplicate correction through
same-batch replacement lineage, and explicit conflict adoption by a different
authorised actor. No legacy-upload substitute or provider call was used.

---

## Phase 26 — Frozen Bulk Document Operations

### Objectives

Implement ADR-0035 only after ADR-0034's `ImportItem` and promotion primitives
exist.

### Sessions

- R26-S01: PostgreSQL owner/migrator/runtime role foundation, default
  privileges and complete existing-application verification under
  `rag_platform_app`; this precedes every protected migration in ADR-0035 and
  ADR-0036.
- R26-S02: bulk domain, constrained schema, frozen membership and provider-free
  APIs.
- R26-S03: queue execution, locking, retry, cancellation and audit.
- R26-S04: selection, preflight, progress/result UI and Playwright journey.

R26-S04 includes all ADR-0035 visual checkpoints and requires David's explicit
review before closure.

### Deliverable

Frozen, auditable and recoverable bulk operations with a reviewed product
surface.

---

## Phase 27 — Document Governance Notifications and Reminders

### Objectives

Implement ADR-0036 after ADR-0030–0035 have supplied their event-producing
lifecycles and after R26-S01 has established the PostgreSQL privilege model.

### Sessions

- R27-S01: schema, event vocabulary, producers, outbox/projector, owner-change
  controls and provider-free API tests.
- R27-S02: in-product inbox and actionable-work/dashboard projections.
- R27-S03: schedules, reminders, preferences, envelopes and delivery.
- R27-S04: email templates and the reserved tenant-branding seam.
- R27-S05: the ordered product/email visual and accessibility checkpoints plus
  Playwright verification.

R27-S05 requires David's explicit review at every named ADR-0036 checkpoint.

### Deliverable

Durable document-governance notifications, reminders and controlled email
delivery with reviewed in-product and email presentation.

---

## Phase 28 — CI/CD and Production Readiness

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

## Phase 29 — Documentation and Demonstration Readiness

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
| Ingestion Orchestration | ✅ Complete |
| Retrieval | ✅ Complete |
| Grounded Generation | ✅ Complete |
| Conversation and Streaming | ✅ Complete |
| Administration | ✅ Complete |
| Observability and Operations | ✅ Complete |
| Product Experience and Interface Design | ✅ Complete |
| Testing and Quality Strategy | ✅ Complete |
| Document Metadata, Governance and Structured Content | 🟨 In Progress |
| Knowledge Library Product Interface | ⬜ Not Started |
| Import Staging and Promotion | ⬜ Not Started |
| Frozen Bulk Document Operations | ⬜ Not Started |
| Document Governance Notifications and Reminders | ⬜ Not Started |
| CI/CD and Production Readiness | ⬜ Not Started |
| Documentation and Demonstration Readiness | ⬜ Not Started |

---

> **Remember:** This roadmap is a living document. As the platform evolves,
> update it to reflect architectural decisions, completed milestones and new
> priorities. The goal is not merely to finish the checklist, but to build a
> platform that demonstrates thoughtful engineering, clear architecture and
> production-ready practices.
