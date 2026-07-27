The ADR is in excellent shape and is very close to approval.

Please make one final refinement pass only.

Do not restructure the document.

Do not change numbering.

Do not rewrite accepted decisions.

This is a polish pass to strengthen several architectural principles before the ADR is accepted.

⸻

1. Strengthen the RLS philosophy

The ADR correctly states that PostgreSQL Row-Level Security is part of the architecture.

Please strengthen this by explicitly stating that:

* PostgreSQL RLS is a defence-in-depth containment mechanism.
* Application-layer security must remain correct even if RLS is disabled in development.
* Likewise, if an application-layer defect occurs, RLS provides an additional containment layer rather than being the only protection.

The architecture intentionally relies on multiple independent security layers, not any single mechanism.

⸻

2. Entity classification

Expand the entity classification section slightly.

Currently the ADR recognises:

* platform-global
* workspace-relationship
* workspace-owned

Please introduce a fourth conceptual category:

workspace-configurable (or workspace-overridable, whichever integrates more naturally with the existing wording).

This represents platform capabilities that are globally available but configured independently by each workspace.

Examples include:

* embedding model selection
* generation model selection
* retrieval configuration
* AI behaviour
* future provider credentials
* feature configuration

This distinction should remain conceptual rather than introducing additional implementation work.

⸻

3. Explicit tenant propagation principle

Please add one architectural invariant.

No service may derive tenant identity implicitly.

Clarify that every service boundary receives tenant identity explicitly.

Examples:

* HTTP
* Queue events
* Python AI service
* Retrieval pipeline
* Generation pipeline

Services must never depend upon hidden global state or implicit current-tenant resolution.

⸻

4. Correlation IDs

Within the auditing section, add a short future-facing statement that requests, events and downstream processing will eventually share a common correlation identifier.

The purpose is end-to-end traceability across:

* HTTP request
* Laravel
* Queue
* Python processing
* Retrieval
* Generation
* Audit records
* Logs

No implementation detail is required.

Simply record the architectural intention.

⸻

5. Event versioning

Within the cross-service propagation section, add a short future consideration that asynchronous contracts should be explicitly versioned.

The ADR does not need to define the versioning strategy.

Simply record that event contracts are expected to evolve and should therefore carry explicit version information rather than relying upon implicit compatibility.

⸻

6. AI provider abstraction

Slightly strengthen the workspace configuration section.

Separate the concepts of:

* provider
* model

Examples:

Platform catalogues may contain:

* embedding providers
* embedding models
* generation providers
* generation models

A workspace selects from those supported capabilities.

This better supports future providers such as OpenAI, Anthropic, Gemini, Bedrock, Azure OpenAI and local models without changing the architecture.

⸻

7. Trust boundaries

Please add one architectural principle.

When tenant identity crosses any service boundary, it becomes untrusted input until validated by the receiving service.

The receiving service must validate tenant identity before acting upon it.

⸻

8. Architectural philosophy

Please strengthen the concluding philosophy section by adding one sentence.

The platform deliberately optimises for correctness over convenience wherever tenant isolation is concerned.

This principle intentionally explains why the architecture prefers:

* explicit tenant context
* explicit propagation
* transaction-local context
* multiple security layers
* 404 concealment
* defence in depth

rather than hidden convenience.

⸻

Please keep these changes concise.

This should remain an ADR, not become an implementation guide.

Once these refinements are complete I expect this ADR to be considered approved.