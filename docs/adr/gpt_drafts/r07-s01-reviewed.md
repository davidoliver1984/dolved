Please update the existing ADR based on the following architecture review.

Do not rewrite the ADR from scratch.

Preserve:

* ADR numbering
* structure
* formatting
* style
* existing rationale where it remains correct

Only modify the sections necessary to reflect these accepted architectural decisions.

⸻

1. PostgreSQL Row-Level Security

This is no longer a deferred consideration.

Update the ADR to make PostgreSQL Row-Level Security (RLS) an accepted part of the tenancy architecture.

The architecture now uses defence in depth.

Tenant isolation is enforced by multiple independent layers:

* workspace-scoped routes
* authenticated user
* active workspace membership
* Laravel policies
* explicit tenant-scoped queries
* PostgreSQL Row-Level Security
* database constraints

Make it clear that application-layer authorisation remains mandatory.

RLS is an additional containment layer, not a replacement for policies or membership checks.

⸻

2. Runtime database roles

Document that:

* migrations use a privileged database role
* the application runtime must use a restricted role
* the runtime role must not be a superuser
* the runtime role must not have BYPASSRLS
* tenant-owned tables should use FORCE ROW LEVEL SECURITY where appropriate

⸻

3. Tenant context

Document the following invariants:

* tenant context is established only after membership has been verified
* tenant context is transaction-local
* tenant context must never leak between requests
* missing tenant context must fail closed
* invalid tenant context must fail closed

⸻

4. Workspace configuration

Refine the “platform configuration” section.

The platform may contain global catalogues such as:

* supported embedding models
* supported LLM providers

However, each workspace may independently configure:

* embedding model
* generation model
* retrieval configuration
* AI settings
* future provider credentials
* future feature configuration

Reflect this distinction clearly.

⸻

5. Deletion

Replace any suggestion of immediate deletion.

The accepted architecture is:

Workspace deletion is a lifecycle.

Deletion will eventually orchestrate cleanup across:

* PostgreSQL
* S3/object storage
* Qdrant
* audit records
* derived artefacts

Deletion is asynchronous, auditable and intentionally deferred.

⸻

6. Auditing

Expand the auditing consequences.

Record three distinct audit layers.

Business audit

Security-sensitive actions:

* workspace creation
* membership changes
* role changes
* ownership transfer
* document administration
* configuration changes

Search/RAG audit

Eventually record:

* who searched
* workspace
* query
* retrieved documents/chunks
* citations
* model used
* latency
* token usage
* cost
* correlation IDs

Database audit

Reserve database auditing (e.g. pgAudit) as infrastructure-level forensic logging rather than the primary business audit trail.

⸻

7. Security invariants

Add the following accepted invariants.

* Application authorisation and database isolation are independent security layers.
* Tenant context must be transaction-local.
* Tenant-owned operations without tenant context fail closed.
* Cross-tenant requests return 404.
* Every workspace-owned table will ultimately enforce both application isolation and database isolation.
* Tenant identity must propagate through PostgreSQL, S3, queues, Python processing and Qdrant.
* Hidden convenience must never carry security meaning.

Include that final sentence exactly as an architectural principle.

⸻

8. Architecture philosophy

Please strengthen the ADR conclusion.

The platform intentionally chooses:

* pooled tenancy for operational simplicity
* explicit tenant context for clarity
* PostgreSQL Row-Level Security for defence in depth
* application policies for business authorisation
* comprehensive auditability
* distributed deletion lifecycle

This combination is intentional and represents the long-term architectural direction of the platform.

⸻

Do not introduce new implementation work.

Do not create migrations.

Do not create code.

Do not modify implementation documents.

Only update the ADR so that it accurately reflects these accepted architectural decisions.
