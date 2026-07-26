# ADR 0002: Use a Three-Application Service Architecture

## Status

Accepted (retrospective)

## Date

2026-07-26

## Original decision context

Phase 1 — Application Scaffolding

## Context

The platform combines a browser application, a transactional business domain and
specialist AI workloads. These areas have different runtime requirements, dependency
ecosystems and scaling characteristics:

* the browser experience benefits from React, TypeScript and server-rendering support;
* identity, tenancy, authorization and relational workflows require a reliable system
  of record;
* document processing, embedding, retrieval and model integration benefit from the
  Python AI ecosystem.

The applications were scaffolded separately during Phase 1. The architectural
boundary and ownership rules need to be explicit so that functionality is not placed
in whichever application is most convenient at the time.

This ADR is retrospective: it records a decision already embodied in the Phase 1
scaffold rather than claiming that the ADR existed when the applications were
created.

## Decision

Use three independently buildable applications in one monorepo:

* **Next.js web application** — owns the browser interface, presentation logic and
  browser-facing interaction state.
* **Laravel API application** — owns the core domain, relational persistence,
  authentication, authorization, tenancy and the public application API. Laravel is
  the system of record.
* **Python FastAPI AI application** — owns AI-specific processing, model-provider
  integrations, document transformation, embeddings and retrieval orchestration. It
  does not become a second system of record for core business entities.

The applications communicate through explicit network contracts. Synchronous
communication initially uses HTTP. Long-running ingestion work will use asynchronous
events and queues when that infrastructure is introduced.

The applications remain independently buildable and deployable even though their
source is managed in one repository. Cross-application contracts belong under
`contracts/` rather than being inferred from another application's implementation.

## Alternatives considered

### A single Laravel application

Laravel could render the user interface and run AI integrations directly. This would
reduce the initial number of services, but it would constrain the frontend and AI
ecosystems, mix long-running AI work with the transactional API and make independent
scaling harder.

### A single Python application

Python could host both the business API and AI workloads. This would simplify
language count, but it would discard the selected Laravel strengths around policies,
queues, events and application-domain workflows and would still leave the browser
application as a separate concern.

### Next.js as a full-stack application

Next.js route handlers could own the domain API as well as the frontend. This would
reduce the initial service count, but would combine presentation and system-of-record
responsibilities and provide a less natural boundary for the planned Laravel domain
and Python processing services.

### Separate repositories

Each application could live in its own repository. This would strengthen repository
isolation, but would add coordination overhead while contracts, local infrastructure
and end-to-end behaviour are evolving together.

## Consequences

### Positive

* Each responsibility uses an ecosystem suited to its workload.
* Domain authority remains explicit in Laravel.
* AI dependencies and compute can evolve and scale independently.
* The browser application is not coupled to model-provider implementation details.
* Shared contracts and cross-service changes can be reviewed atomically in the
  monorepo.

### Negative

* Developers must work across Node.js, PHP and Python ecosystems.
* Network calls introduce failure modes that in-process calls do not have.
* Contract versioning, integration tests and service observability become essential.
* Local development and deployment require orchestration of multiple processes.
* Ownership boundaries require discipline to prevent duplicated business logic.
