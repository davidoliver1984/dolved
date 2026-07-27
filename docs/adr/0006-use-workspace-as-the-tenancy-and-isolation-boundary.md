# ADR 0006: Use Workspace as the Tenancy and Isolation Boundary

## Status

Accepted

## Date

2026-07-27

## Context

Phase 7 must give the platform a tenant model before documents, conversations,
ingestion and retrieval become tenant-owned in later phases. Phase 6 established
global user identity (Sanctum/Fortify, see `docs/adr/0005-*`), but a Laravel user
is not yet scoped to any collaboration or isolation boundary.

The tenancy decision is foundational rather than local to Laravel: it must also
describe how tenant identity propagates through asynchronous processing (SQS
events consumed by the Python AI service), object storage (S3-compatible document
uploads), and — once Phase 12–13 introduce embeddings — vector storage (Qdrant).
Getting this wrong is expensive to reverse, since every later phase (document
storage, ingestion, chunking, embeddings, retrieval) builds tenant-owned data on
top of whatever boundary is chosen here.

This ADR is architecture-and-documentation only. No migrations, models,
middleware, policies, routes or frontend code are introduced by this decision;
implementation is deferred to Stage 7.2 and 7.3.

## Decision

### Tenancy unit and terminology

A **Workspace** is the platform's tenant, collaboration and data-isolation
boundary. No separate organisation layer sits above it at this stage — a
workspace is not a child of some other tenant concept.

### Identity and membership

- Users remain global identities (Phase 6). A user may belong to multiple
  workspaces.
- The user-to-workspace relationship is a first-class `WorkspaceMembership`
  domain model, not an anonymous pivot table — it is where role and membership
  state live, and it is the object policies and queries reason about.
- Initial membership roles are a fixed set: `owner`, `admin`, `member`. Custom
  roles and a granular permission engine are explicitly deferred.
- Every workspace has exactly one active owner membership at all times — a
  workspace may never be left ownerless.
- `created_by_user_id` records creation provenance only. It is not the source of
  current ownership authority; ownership is determined by the active owner
  membership, which can transfer.
- A membership represents a user who has joined a workspace. Invitations
  (pending, not-yet-joined state) are deliberately out of scope here and will be
  modelled in a later session.

### Tenant context resolution

- Tenant context is explicit: workspace-scoped API routes carry an immutable
  public workspace identifier, distinct from the internal primary key.
- The frontend may remember a "current workspace" as a navigation convenience.
  This is a UX preference only — it carries no authorisation weight and every
  workspace-scoped request is still validated server-side against active
  membership.
- Tenant context is established only after active membership has been
  verified for the authenticated user — never before, and never from
  client-supplied input alone.
- Tenant context is transaction-local. It must not leak between requests,
  jobs, or connections pulled from a shared pool.
- Missing tenant context and invalid tenant context both fail closed: a
  request, job, or query that cannot establish a valid, membership-checked
  workspace context does not proceed as if unscoped.

### Relational isolation strategy

- The relational tenancy model is **pooled**: one shared PostgreSQL database and
  shared tables, not database-per-tenant or schema-per-tenant.
- Every workspace-owned relational row carries a non-nullable workspace foreign
  key.
- Tenant isolation is enforced through **defence in depth**: multiple
  independent layers must each hold, rather than any single mechanism being
  trusted alone. The accepted layers are:
  1. workspace-scoped routes;
  2. the authenticated user;
  3. active workspace membership;
  4. Laravel policies;
  5. explicit tenant-scoped queries;
  6. PostgreSQL Row-Level Security (RLS);
  7. database constraints.
- PostgreSQL Row-Level Security **is** an accepted part of the tenancy
  architecture — it is no longer deferred. RLS is a defence-in-depth
  containment mechanism, not a replacement for application policies or
  membership checks: application-layer authorisation must remain correct on
  its own even if RLS is disabled, as it typically would be in some local
  development configurations. Symmetrically, if an application-layer defect
  occurs, RLS provides an additional containment layer rather than being the
  only protection. The architecture deliberately relies on multiple
  independent security layers, not any single mechanism. Its implementation
  (policy definitions, connection-context propagation, tests) is carried out
  in Stage 7.2, not this ADR.
- Hidden global Eloquent scopes are not treated as the primary security
  boundary; they may exist as one further layer of defence in depth but must
  not be the only thing standing between a query and a cross-tenant leak.
- The application runtime must connect to PostgreSQL using a restricted
  database role, distinct from the privileged role used to run migrations:
  - migrations use a privileged role capable of altering schema and RLS
    policies;
  - the application runtime role must not be a superuser and must not have
    `BYPASSRLS`;
  - tenant-owned tables should use `FORCE ROW LEVEL SECURITY` where
    appropriate, so that RLS applies even to the table owner.
- The repository must not describe RLS as active until it is genuinely
  implemented and verified in Stage 7.2.

### Cross-service tenant propagation

Workspace identity must propagate through every derived artefact and service
boundary, not just Laravel's database:

- Object storage: S3-compatible uploads use server-controlled,
  workspace-prefixed object keys. The prefix is a convenience, not an
  authorisation mechanism on its own.
- Events and queues: ingestion events published to SQS carry workspace identity.
  Asynchronous workers (the Python AI service) must not infer workspace context
  from browser sessions or process-global mutable state — it must come from the
  message itself.
- Consumers must validate that a referenced resource actually belongs to the
  workspace named in the event, rather than trusting the event's claim
  transitively.
- Extracted documents, chunks and vectors all retain immutable workspace
  identity. The final Qdrant collection and sharding strategy (single shared
  collection with a workspace filter vs. collection-per-tenant) is explicitly
  deferred to `R13-S01`, but whatever shape retrieval takes, workspace identity
  on every vector record is non-negotiable from this ADR forward.
- Asynchronous event contracts are expected to evolve and must therefore carry
  explicit version information rather than relying on implicit compatibility.
  The versioning strategy itself is not defined by this ADR.

### Workspace configuration and platform catalogues

The platform distinguishes two levels of configuration:

- **Platform-global catalogues** — options the platform itself supports. These
  are not workspace-owned and apply across the whole platform. The catalogue
  separates **provider** from **model**: for example supported embedding
  providers and their embedding models, and supported generation providers and
  their generation models. This separation is what allows future providers
  (OpenAI, Anthropic, Gemini, Bedrock, Azure OpenAI, local models) to be added
  without changing the architecture.
- **Workspace-level configuration** — choices an individual workspace makes
  from within those catalogues, including its selected embedding provider and
  model, generation provider and model, retrieval configuration, other AI
  settings, and (in future) its own provider credentials and feature
  configuration.

A workspace can only select from what the platform catalogue supports; it does
not introduce new platform-wide capabilities.

### Workspace lifecycle

- Registration (Phase 6) creates a global user identity only — it does not
  create a workspace.
- Workspace creation is explicit and atomic: it creates the workspace and its
  owner membership together, never one without the other.
- Workspace deletion is a **lifecycle**, not an immediate row delete. Deletion
  eventually orchestrates cleanup across PostgreSQL, object storage (S3),
  Qdrant, audit records, and other derived artefacts. Deletion is asynchronous
  and auditable. The concrete orchestration mechanics (ordering, retries,
  confirmation) are intentionally deferred to a future implementation session,
  but the shape of deletion as a multi-system, asynchronous, auditable
  lifecycle is accepted now.

### Entity classification

Every persistent entity in the platform must be classified as one of:
platform-global, workspace-relationship, workspace-owned, or
workspace-configurable. This classification drives which entities require the
mandatory workspace foreign key and which negative tests are required in
Stage 7.2 onward.

Workspace-configurable is a distinct, conceptual fourth category: a platform
capability that is globally available but configured independently by each
workspace — for example embedding model selection, generation model
selection, retrieval configuration, AI behaviour, and (in future) provider
credentials and feature configuration. It does not introduce implementation
work beyond this ADR; see "Workspace configuration and platform catalogues"
above.

### Auditing

The platform records three distinct, independent audit layers rather than one
undifferentiated log:

- **Business audit** — security-sensitive actions such as workspace creation,
  membership changes, role changes, ownership transfer, document
  administration, and configuration changes.
- **Search/RAG audit** — once retrieval exists, who searched, in which
  workspace, the query, retrieved documents/chunks, citations, the model used,
  latency, token usage, cost, and correlation identifiers.
- **Database audit** — infrastructure-level forensic logging (for example
  `pgAudit`), reserved for forensic and compliance use. It is not the primary
  business audit trail; the business and search/RAG audit layers serve that
  purpose.

Requests, events and downstream processing are intended to eventually share a
common correlation identifier, giving end-to-end traceability across the HTTP
request, Laravel, the queue, Python processing, retrieval, generation, audit
records and logs. No specific correlation-ID mechanism is defined by this ADR.

### Security invariants

- Authentication does not grant workspace access.
- Workspace access requires an active membership.
- Roles are scoped to a single workspace — a role in one workspace implies
  nothing about another.
- Client-supplied workspace identity is never trusted without membership
  validation against the authenticated user.
- Tenant-owned resources are resolved inside the workspace boundary before
  action authorisation runs, not after.
- Cross-workspace access fails closed.
- A workspace may never be left without an active owner.
- Tenant identity is preserved through every synchronous and asynchronous
  processing stage — HTTP, queues, events, storage, AI-service calls.
- Storage prefixes and vector payloads support scoping but never replace
  authorisation.
- Negative cross-tenant tests are mandatory for every tenant-owned feature.
- The repository must not claim PostgreSQL RLS is in effect until it is
  genuinely implemented and verified.
- Application authorisation and database isolation are independent security
  layers — neither substitutes for the other.
- Tenant context must be transaction-local.
- Tenant-owned operations attempted without tenant context fail closed.
- Cross-tenant requests return `404`, not `403`, so a workspace's existence is
  not revealed to a party without access to it.
- Every workspace-owned table will ultimately enforce both application-layer
  isolation and database-layer isolation.
- Tenant identity must propagate through PostgreSQL, S3, queues, Python
  processing and Qdrant.
- No service may derive tenant identity implicitly. Every service boundary —
  HTTP, queue events, the Python AI service, the retrieval pipeline, the
  generation pipeline — receives tenant identity explicitly; none may depend on
  hidden global state or implicit current-tenant resolution.
- When tenant identity crosses any service boundary, it becomes untrusted
  input until validated by the receiving service. The receiving service must
  validate tenant identity before acting upon it, not assume the sender
  already did.
- Hidden convenience must never carry security meaning.

## Alternatives considered

### Organisation as the primary domain term, or organisation containing multiple workspaces

An organisation → workspace hierarchy is a reasonable model for larger B2B
platforms with department-level subdivisions. It adds a second layer of
membership, roles and ownership rules before the platform has validated that a
single flat tenancy unit is insufficient. Rejected for now, not forever — this
ADR does not preclude introducing an organisation layer above workspaces later
if a genuine multi-workspace-per-customer need appears.

### One workspace per user

Simpler, but forecloses team collaboration, which is a stated goal of the
platform (`PROJECT_ROADMAP.md`). It would also make later multi-user
collaboration a breaking migration rather than an additive one.

### `workspace_id` directly on the `users` table

This conflates identity with membership and cannot represent a user belonging to
more than one workspace, which is an explicit requirement here. It also
resurfaces every time a user's workspace set changes, rather than being modelled
as its own relationship.

### Database-per-tenant or schema-per-tenant

Both give strong physical isolation, but add significant operational complexity
(per-tenant migrations, connection routing, backup/restore multiplied by tenant
count) that is disproportionate before the platform has real multi-tenant
operational experience. Pooled tenancy with disciplined scoping and mandatory
negative tests is the conventional starting point for a SaaS platform at this
stage; a stronger isolation model remains available later without discarding
this ADR's membership and role model.

### Session-only tenant selection

Treating "current workspace" purely as a session/cookie value without a
server-validated membership check on every request would let a stale or
tampered session imply access it doesn't have. Rejected as the primary
mechanism; a remembered preference is still fine as a UX convenience, per the
tenant-context-resolution decision above.

### Custom request header as the primary tenant context

A client-supplied header (e.g. `X-Workspace-Id`) trusted as authoritative
tenant context is rejected as a primary mechanism for the same reason: it is
client-controlled input and must never be trusted without membership
validation. Route-embedded identifiers, validated server-side, are preferred.

### Subdomain-based tenant resolution

Workable for many SaaS products, but adds DNS, TLS certificate and local
development complexity (wildcard domains, Sanctum stateful-domain
configuration) that isn't justified yet, and doesn't fit the existing
`app.maketime.ai` / `api.maketime.ai` host model from ADR 0005.

### Global Eloquent scopes as the primary boundary

A global scope is easy to introduce and easy to accidentally bypass (raw
queries, `withoutGlobalScope`, background jobs without the right binding).
Using it as the *only* enforcement mechanism would create a single point of
failure for tenant isolation. It remains acceptable as one layer of
defense-in-depth, not the primary boundary.

### Application-layer authorisation alone, without PostgreSQL RLS

This was the platform's original Phase 7.1 position: rely solely on
workspace-scoped routes, membership validation, resource resolution, policies
and tests, with RLS deferred indefinitely. Reconsidered in architecture review
because it leaves tenant isolation as a single layer — a defect in query
scoping or a missed policy check would fully cross tenant lines with nothing
to contain it. Defence in depth with RLS as an additional, independent layer
was accepted instead, without discarding any of the application-layer
enforcement already decided.

### PostgreSQL RLS as the sole isolation mechanism

The inverse alternative — relying on RLS alone and treating application-layer
checks as redundant — is also rejected. RLS constrains rows returned by a
correctly-scoped database session, but it cannot express business-level
authorisation such as role-based feature access, and a defect in how tenant
context is established for that session (the transaction-local GUC) would
undermine it silently. Both layers are required.

### Qdrant collection-per-tenant

Deferred to `R13-S01` when the vector storage ADR is written — that decision
depends on retrieval-performance and operational characteristics that aren't
known yet. This ADR only commits to every vector record carrying immutable
workspace identity, regardless of which physical layout is chosen later.

### Automatic personal workspace creation during registration

Auto-creating a workspace at registration would simplify onboarding, but
conflates "has an account" with "owns a workspace" and complicates the
explicit, atomic workspace-creation flow this ADR commits to. Deferred; can be
revisited as a UX decision without changing the underlying model.

### Custom roles and a granular permission engine

A full permission engine is significant scope before the platform has evidence
that three fixed roles (`owner`, `admin`, `member`) are insufficient. Deferred
rather than rejected — the `WorkspaceMembership` model is where a future
permission system would attach.

## Consequences

### Positive

- Tenant context is explicit and testable at every layer, rather than implied.
- Users can collaborate across several workspaces, matching the platform's
  collaboration goals.
- Pooled tenancy is operationally conventional for a SaaS platform at this
  stage — one database, one migration path, one backup strategy.
- A clear propagation path exists into Laravel, the Python AI service, SQS and
  S3, and (later) Qdrant, so later phases inherit a settled answer instead of
  re-deriving one.
- Leaves room for future compatibility with an organisation hierarchy and
  tiered vector isolation without requiring a breaking change to this model.
- A tenant-isolation defect in one layer (for example a missed scope in a
  query) does not automatically become a cross-tenant leak, because
  PostgreSQL RLS provides an independent containment layer beneath it.
- Three distinct audit layers give the platform both a business-facing record
  of security-sensitive actions and, once retrieval exists, a search/RAG audit
  trail, without conflating either with low-level database forensic logging.

### Negative

- Every tenant-owned query still requires disciplined scoping; RLS is an
  additional layer, not a substitute for correct application-layer scoping.
- Pooled tenancy means an isolation defect has a larger blast radius than
  database-per-tenant would, though defence in depth via RLS substantially
  reduces the consequence of a single-layer mistake.
- The single-active-owner and role invariants require careful transactional
  enforcement (e.g. transferring ownership, removing the last admin).
- Distributed workspace deletion (Postgres + object storage + vector storage +
  audit records) requires its own orchestration design, deferred to a future
  session.
- Negative cross-tenant tests become mandatory scope for every tenant-owned
  feature going forward, not optional hardening.
- RLS requires disciplined operational practice: a restricted, non-superuser,
  non-`BYPASSRLS` runtime database role, correct transaction-local tenant
  context, and its own test strategy — misconfiguring any of these silently
  disables the protection rather than failing loudly.

### Architectural philosophy

This platform intentionally combines several choices rather than picking a
single mechanism and relying on it exclusively: pooled tenancy for operational
simplicity, explicit tenant context for clarity, PostgreSQL Row-Level Security
for defence in depth, application policies for business authorisation,
comprehensive auditability across business, search/RAG and database layers,
and a distributed, asynchronous deletion lifecycle. This combination is
intentional and represents the platform's long-term architectural direction,
not a temporary or minimal starting point. Wherever tenant isolation is
concerned, the platform deliberately optimises for correctness over
convenience — this is why it prefers explicit tenant context, explicit
propagation, transaction-local context, multiple independent security layers,
`404` concealment and defence in depth, rather than any hidden convenience.
