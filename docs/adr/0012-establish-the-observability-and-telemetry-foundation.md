# ADR 0012: Establish the Observability and Telemetry Foundation

## Status

Accepted

## Date

2026-07-30

## Context

Phase 11 (ADR 0011) completed the deterministic chunking architecture,
implementation and evaluation. The next phases — embeddings, vector storage,
retrieval and grounded generation — introduce the platform's first calls to
external AI providers, its first vector database, and materially more
cross-service, asynchronous work than anything built so far. Each of those
phases already has its own provider-independence boundary (a parser,
chunker, embedder, retriever and LLM-provider abstraction), established or
assumed by prior ADRs. None of them has yet needed to answer a different
question: once a request enters this system, how does anyone — a developer
debugging a failure, an operator watching the platform run — actually see
what happened to it as it moves through Laravel, a queue, the Python
service, and out to an external provider and back?

This ADR answers that question before those downstream stages are built,
deliberately. Observability retrofitted after a pipeline exists tends to be
added reactively, one incident at a time, in whatever shape that incident
happened to need — producing inconsistent, incomplete instrumentation that
each later stage must work around rather than build on. Establishing the
instrumentation contract now means embeddings, vector storage, retrieval and
generation are each observable by design from their first commit, the same
way ADR 0007 established the Document lifecycle before Phase 8 built
anything against it, and ADR 0010 established the extraction contract before
Stage 10.2 picked a PDF library.

This ADR builds on architecture already established, rather than
redefining it: the three-service split (ADR 0002), the Workspace tenancy
boundary (ADR 0006), the transactional outbox and asynchronous processing
model (ADR 0008), signed internal service-to-service requests (ADR 0009),
and the immutable extraction/normalisation/chunking pipeline (ADR 0010,
ADR 0011). It also assumes, without redefining, that provider independence
for parsing, chunking, embedding, retrieval and generation is handled by its
own abstraction layer — this ADR is a cross-cutting concern that sits
alongside those boundaries, not a replacement for any of them.

The objective is not to design a commercial observability product. It is to
make the platform observable by design, while remaining vendor-neutral and
privacy-conscious — established once, as a foundation, rather than
reinvented per pipeline stage.

## Decision

### OpenTelemetry as the canonical instrumentation API

OpenTelemetry is the platform's one, canonical instrumentation API and
vendor-neutral boundary, used directly by application and framework code in
both Laravel and the Python service.

The platform does not introduce a proprietary `TelemetryProvider` or similar
wrapper abstraction that simply re-exposes tracing, metrics, events, context
propagation or exporter concepts OpenTelemetry already provides. This is the
same reasoning already applied elsewhere in this platform's architecture:
ADR 0002 rejected duplicating a service boundary that already existed
cleanly; ADR 0010 rejected adopting a third-party parser's object model as
canonical because whichever shape "won" would become a hidden dependency.
The mirror image of that mistake here would be building a bespoke
instrumentation layer that must be permanently maintained, will lag behind
OpenTelemetry's own evolution, and buys no vendor-neutrality OpenTelemetry
does not already provide on its own.

An application-owned abstraction earns its place only where OpenTelemetry
genuinely has no equivalent — concretely, AI-specific semantic conventions
(see below), not tracing, metrics or context-propagation mechanics
themselves.

### The Collector as the routing and backend boundary

Telemetry flows in one direction, through one intermediary:

```text
Application
  ↓
OpenTelemetry SDK
  ↓
OpenTelemetry Collector
  ↓
One or more backends
```

The Collector — not application code — owns routing, batching, filtering,
sampling and backend-specific export. Application code depends on the
OpenTelemetry SDK and never on any specific backend's exporter
configuration or credentials.

This keeps backend choice a Collector-configuration change, not an
application-code change, for the same reason ADR 0004 kept LocalStack and
real AWS behind one configuration switch rather than two code paths: the
application should not need to change because the infrastructure behind it
did. Locally, the Collector runs as another Docker Compose service,
consistent with this platform's container-first development model
(ADR 0003) — telemetry is inspectable locally without a live account with
any commercial provider, the same reasoning ADR 0004 already applied to
local AWS emulation.

### Vendor neutrality

The application must not depend on LangSmith, Langfuse, or any other
commercial observability or AI-observability platform's SDK. Where a future
backend is adopted, it is adopted primarily through Collector configuration,
not through instrumenting application code against that backend's API.
Vendor neutrality is a direct consequence of the two decisions above, not a
separate mechanism — it holds only as long as application code genuinely
never imports a backend-specific SDK.

### Context propagation

Trace context must propagate across every hop of one logical request:
Laravel HTTP, the queue boundary (the outbox mechanism of ADR 0008),
the Python service, outbound HTTP, and external AI providers where the
provider's own API supports it. One logical ingestion run or search request
should remain correlated end to end, regardless of how many process and
network boundaries it crosses.

This platform already has a durable, contract-level `correlation_id` —
established in the ingestion event contract and threaded through the
outbox and the signed internal claim request (ADR 0008, ADR 0009). This ADR
does not replace or redefine that identifier, and does not require unifying
it with an OpenTelemetry trace ID. The two serve different layers with
different lifecycles: `correlation_id` is a stable, versioned, durably
persisted business identifier that exists whether or not tracing
infrastructure is healthy; a trace ID is an observability-transport
concept, scoped to whatever the tracing backend retains. Where practical,
the existing `correlation_id` should be attached to spans as an attribute so
the two can be cross-referenced — but forcing them to be the same value
would risk either the event contract's stability or the tracing backend's
own ID semantics, for no real benefit.

### Privacy as a strong default

Telemetry explains how the system behaved. It must never explain what
customer data contained. Sensitive payloads are never captured by default,
including: document contents, prompts, retrieved chunks, user questions,
model responses, credentials, and secrets.

The default posture is an explicit **allowlist** of safe attributes, not a
denylist of known-sensitive ones. This is a stricter, safer default — a
denylist only protects against sensitive fields someone thought to name in
advance, while an allowlist means a new, unanticipated sensitive field is
excluded by default rather than accidentally exported. This extends the same
discipline ADR 0009 already established for HMAC-related logging ("logs may
contain the Key ID, event ID, correlation ID and verification outcome...
must never contain the HMAC secret, signature, exact signed body,
credentials or document content") to the platform's telemetry as a whole,
rather than inventing a new privacy posture from scratch.

Identifiers are not payload. `workspace_id` and `document_id` are safe,
allowlisted attributes — they identify a request's tenant and subject for
debugging and tenant-scoped operational visibility, consistent with
ADR 0006's tenancy model, without revealing what that workspace's documents
actually contain.

### Semantic conventions

Official OpenTelemetry semantic conventions are used wherever they already
exist — HTTP, database and messaging conventions are not reinvented.
Application-owned conventions are introduced only where OpenTelemetry has
no suitable equivalent: naming the platform's own RAG pipeline concepts.
Illustrative examples, not a fixed or exhaustive set:

- `rag.pipeline.stage`
- `rag.chunking.strategy`
- `rag.chunking.chunk_count`
- `rag.retrieval.result_count`
- `rag.prompt.template_version`

Where OpenTelemetry's own emerging generative-AI semantic conventions
stabilise and cover a concept this platform has named independently, the
official convention should be adopted in its place. This ADR commits to
preferring the standard over the platform-specific name as the standard
matures, not to freezing these particular attribute names permanently.

### Metrics

Metrics are used for aggregate operational signal: latency, retries,
failures, token counts, chunk counts, retrieval counts, and estimated model
cost. Metric labels must not carry unbounded or high cardinality — no raw
entity identifier (a document ID, a chunk ID) and no free text as a metric
label or dimension.

This distinction matters architecturally, not just stylistically: a metric
is aggregated across a label's possible values, and a metrics backend's
storage and query cost scale with how many distinct label combinations
exist. An unbounded-cardinality label (a UUID, a piece of user text) turns
an aggregate signal meant to answer "how many failures this week" into
something that behaves like a per-request record without the tools a trace
actually provides for inspecting one request. Per-request, per-entity
granularity belongs in traces and their span attributes; metrics stay
coarse and aggregable by design.

### Failure behaviour

A telemetry or instrumentation failure must never fail a user-facing
request. Exporter unavailability, a Collector that is temporarily
unreachable, or any other instrumentation-path failure must degrade
gracefully — telemetry is dropped or buffered according to the SDK's own
backpressure behaviour, never allowed to raise into application business
logic. Observability is a secondary concern relative to the request it is
observing; it must never become a new way for that request to fail.

### Testing

Context propagation, instrumentation presence, and the safe-attribute
allowlist are each verified by tests, not left to convention or code review
alone. Concretely: a positive test proves trace context actually propagates
across the Laravel-to-queue-to-Python boundary for one logical request, and
a negative test proves a known-sensitive value (for example, a synthetic
prompt or document fragment) never appears in exported span or metric
attributes. This is the same discipline already applied to completeness in
ADR 0011: a guarantee is only as real as the test that would fail if it
stopped holding.

## Alternatives considered

### A custom, in-house telemetry abstraction

Rejected. This would duplicate a mature, already-adopted, vendor-neutral API
that already solves tracing, metrics, events and context propagation,
permanently obligating the platform to maintain a wrapper that lags behind
OpenTelemetry's own evolution, for no additional vendor-neutrality beyond
what OpenTelemetry already provides.

### Instrumenting directly against a commercial observability SDK

Rejected. Calling a specific platform's SDK (LangSmith, Langfuse, or
similar) directly from application code creates exactly the vendor
lock-in this ADR exists to avoid — switching platforms later would mean
changing every instrumented call site, rather than a Collector
configuration change.

### No dedicated Collector; export directly from each application process

Rejected. This would couple both Laravel and Python to backend-specific
exporter configuration and credentials independently, duplicate routing,
batching and retry behaviour the Collector centralises once, and turn
adding or swapping a backend into an application-code change in two
places instead of one configuration change in one place.

### Deferring observability until embeddings, retrieval and generation exist

Rejected — this is the alternative this ADR's timing directly argues
against. Retrofitting instrumentation after a pipeline exists tends to
happen reactively, once per incident, producing inconsistent coverage each
later stage must work around. Establishing the contract now means every
later AI-pipeline stage is observable by design from its first commit.

### Capturing full request/response payloads by default for easier debugging

Rejected as a default. The diagnostic value is real, but capturing prompts,
retrieved chunks or model responses by default violates the privacy
position this ADR establishes. A deliberately scoped, explicitly opt-in
capture mechanism — with its own access controls and retention policy — is
a legitimate future capability, but designing it prematurely here would
mean deciding a real trade-off before a concrete need defines its actual
requirements, the same reasoning ADR 0010 already applied to deferring
lossy decisions to the stage with enough context to make them.

### Using raw entity identifiers as metric labels

Rejected. Per-request or per-entity granularity is what traces and their
attributes are for. Using a document or chunk identifier as a metric label
produces unbounded cardinality, which degrades or breaks typical metrics
backends and defeats the purpose of a metric as an aggregate signal.

### Unifying the contract-level `correlation_id` with the OpenTelemetry trace ID

Considered and not adopted as a requirement, though not permanently
foreclosed either. The two identifiers serve different layers with
different durability and retention expectations; treating them as
interchangeable risks compromising either the event contract's stability
guarantees or the tracing backend's own ID semantics. Attaching the
existing `correlation_id` as a span attribute achieves cross-referencing
without conflating the two.

## Consequences

### Positive

- Backend choice remains a Collector-configuration decision; the platform
  is not locked into any commercial observability vendor.
- One consistent, industry-standard instrumentation vocabulary spans
  Laravel and Python, rather than a bespoke API every contributor would
  need to learn from scratch.
- Every future AI-pipeline stage — embeddings, vector storage, retrieval,
  generation — inherits an established observability contract instead of
  each inventing ad hoc logging.
- A strong default privacy posture substantially reduces the risk of
  sensitive customer content ending up in a third-party observability
  backend by accident.
- Privacy and correlation guarantees are provable by test, not merely
  asserted by policy.

### Negative

- The Collector is another operational component to deploy, configure and
  keep healthy, including in local development, though Docker Compose keeps
  this consistent with the platform's existing container-first workflow.
- An allowlist-first privacy posture means genuinely useful debugging
  information (for example, the exact prompt that produced a bad answer) is
  unavailable by default and requires a deliberately designed, separately
  scoped opt-in capability — a real cost during early development and
  debugging.
- Two correlation identifiers (the contract-level `correlation_id` and the
  OpenTelemetry trace ID) coexist rather than being unified, requiring
  discipline to keep both meaningfully cross-referenced.
- OpenTelemetry's generative-AI semantic conventions are still evolving;
  some `rag.*` attributes adopted now may need renaming later as official
  conventions stabilise.

## Architectural invariants

- Application code instruments through OpenTelemetry APIs directly; no
  parallel proprietary telemetry abstraction duplicates OpenTelemetry's own
  tracing, metrics or context-propagation primitives.
- Application code holds no backend-specific exporter configuration or
  credentials; those belong to the Collector.
- No commercial AI-observability SDK is called directly from application
  code.
- Trace context propagates across every hop of one logical request: Laravel
  HTTP, the queue, the Python service, outbound calls, and supporting
  external providers where feasible.
- No sensitive payload — document content, prompts, retrieved chunks, user
  questions, model responses, credentials, secrets — is captured by default;
  only allowlisted attributes are recorded.
- No metric carries an unbounded-cardinality label.
- A telemetry or instrumentation failure never causes a user-facing request
  to fail.
- Context propagation and the safe-attribute allowlist are covered by
  tests, not left to convention alone.

## Scope boundaries

This ADR does not define:

- the exact `rag.*` attribute or metric names for embeddings, vector
  storage, retrieval or generation — each stage defines its own
  instrumentation against these principles when it is actually built;
- which specific observability backend(s) the Collector exports to — a
  Collector-configuration decision, not an application-architecture
  decision;
- sampling strategy or telemetry retention policy;
- dashboards, alerting thresholds, or on-call procedures — later
  operational concerns already named elsewhere in the roadmap;
- whether the contract-level `correlation_id` and the OpenTelemetry trace ID
  are ever unified — deferred, not decided against permanently;
- any future, deliberately scoped, opt-in mechanism for capturing full
  payloads for debugging.

This ADR establishes the architectural foundation only. Implementation
details — SDK wiring, Collector configuration, exact instrumentation
call sites — belong in `IMPLEMENTATION_GUIDE.md` for whichever stage
implements them.
