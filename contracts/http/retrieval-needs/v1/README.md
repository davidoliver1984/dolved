# Evidence-needs planning contract

ADR-0038 implementation. `POST /api/internal/retrieval/plan-needs` uses existing
rc1 authentication with the distinct purpose `retrieval.plan-needs`. The signed
workspace/request IDs must equal the body; purpose substitution and replay fail.
The original `retrieval.plan` endpoint and its response contract are unchanged.

The request contains authorised **user turns only**, never documents, expected
answers or relevance labels. Laravel owns authentication, conversation membership,
workspace scope, authority and applicability. Python interprets those supplied
turns; it cannot select document or location IDs. Strict model output is grounded
again in Laravel. No invalid-result fallback to the legacy planner is permitted.

`plan.schema.json`, `request.schema.json` and `response.schema.json` are generated
from the corresponding production Pydantic models and checked for drift. PHP uses
the same schemas. `examples.json` copies the accepted semantic examples, not model
observations. `invalid-mutations.json` runs against both semantic validators.

Semantic checks include exact quote provenance, sequential need IDs and question
order, nonempty central needs, reference cycles, current/reference separation,
calendar validity, supported quote-to-date conversion, comparison selectors and
named/relational applicability provenance. Calendar conversion recognises explicit
ISO and English calendar notation; unresolved notation is rejected, not guessed.
These checks do not establish language accuracy or factual entailment.

Planner identities: `evidence-needs-plan-v1`, `bounded-evidence-needs-v7`,
`structured-chat-needs-v1`. Provider/model are the existing configured planner
identities; the adapter fingerprints its exact identity tuple and reports observed
tokens. Its transport makes one attempt; Laravel retains its existing bounded
outer transport retries. A typed invalid grounded plan is not retried.

This boundary is implemented but not enabled in conversation orchestration yet.
Per-need eligibility/retrieval, generation v2 and persistence integration, explicit
workspace activation and the fixed live slice remain R01P work. Contract tests are
not a live-readiness or section-completion claim.
