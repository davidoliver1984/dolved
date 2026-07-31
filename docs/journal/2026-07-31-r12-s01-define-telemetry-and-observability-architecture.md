# Session Journal: R12-S01 — Define Telemetry and Observability Architecture

## Date

2026-07-31

## Session mode

Architecture and documentation only. No application instrumentation,
telemetry infrastructure or dependency was introduced.

## What happened

The project plan was deliberately changed after Phase 11 so that an
observability foundation exists before embeddings, vector storage, retrieval
and grounded generation introduce external AI-provider calls and additional
cross-service work. This inserted Observability Foundation as the new Phase
12 and moved Embeddings and the later phases forward by one number.

ADR-0012 was prepared and accepted before this session record was closed.
The accepted ADR was therefore treated as the authority rather than
reopening its decisions. The repository was inspected to reconcile the ADR
with the current roadmap, implementation guide, ADR index and `tasks.json`.
That inspection confirmed the accepted architecture matches the Stage 12.1
objective and acceptance criteria.

## Decisions recorded

ADR-0012 establishes:

* OpenTelemetry as the canonical instrumentation API in Laravel and Python,
  without a proprietary wrapper that duplicates its primitives;
* the OpenTelemetry Collector as the routing, batching, filtering, sampling
  and backend-export boundary;
* application independence from commercial observability SDKs and
  backend-specific exporters or credentials;
* trace-context propagation across Laravel HTTP, the transactional
  outbox/queue boundary, Python, outbound HTTP and supporting external
  providers;
* continued separation between the durable contract-level `correlation_id`
  and the OpenTelemetry trace ID, with cross-referencing through an
  allowlisted span attribute where practical;
* an allowlist-first privacy model that excludes document contents, prompts,
  retrieved chunks, questions, model responses, credentials and secrets by
  default;
* official OpenTelemetry semantic conventions where available, with
  narrowly scoped `rag.*` conventions only for concepts not yet covered;
* aggregate, low-cardinality metrics, with per-entity details kept in
  traces rather than metric labels;
* graceful degradation: telemetry failure must never fail the business
  operation being observed;
* tests as the eventual proof of context propagation and privacy rather
  than relying only on convention.

The ADR deliberately does not choose a local or production telemetry
backend, sampling and retention policies, dashboards, alerting thresholds,
or an opt-in full-payload debugging mechanism.

## Verification performed

* Read ADR-0012 in full and checked its status, context, decision,
  alternatives, consequences, invariants and scope boundaries.
* Checked the ADR index and confirmed ADR-0012 is linked as Accepted.
* Checked the current Phase 12 roadmap, Stage 12.1 implementation-guide
  criteria and R12-S01 tracker entry.
* Inspected commit `5b28068` and confirmed the accepted ADR and index were
  committed together with the correct repository author identity.
* Confirmed the ADR remains aligned with ADR-0006 (Workspace tenancy),
  ADR-0008 (transactional outbox) and ADR-0009 (worker authentication).
* Did not run application tests because this stage changed architecture
  documentation only and introduced no executable application or
  infrastructure behaviour.

## Problems or corrections

The implementation guide still described Stage 12.1 as unexecuted and
contained an ADR placeholder even though ADR-0012 had already been accepted
and committed. The stage record and tracker were brought back into alignment
with the repository's actual state.

No architectural contradiction or amendment to ADR-0012 was required.

## Next steps / important takeaways

* Stage 12.2 provisions the Collector and a local traces-and-metrics backend
  without instrumenting Laravel or Python yet.
* Backend selection remains an infrastructure/configuration choice behind
  the Collector. It must not introduce a backend SDK into application code.
* Privacy, cross-service propagation and graceful failure become executable
  requirements in Stages 12.3–12.5, where they must be demonstrated by
  tests rather than merely repeated in documentation.
