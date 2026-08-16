Thanks — this revised split is much cleaner and I agree with almost all of it.

Please proceed toward Stage 16.1 / ADR-0017 with the following decisions confirmed.

1. Stage 16.1

Confirmed title:

ADR 0017: Define Document Versioning and Temporal Authority

Confirmed framing:

Phase 16 resolves a document-versioning decision ADR-0007 explicitly deferred.

ADR-0017 should explicitly supersede ADR-0007 in part, narrowly for the prior deferred/no-versioning position, while preserving ADR-0007's technical Document lifecycle, storage ownership and deletion semantics.

Stage 16.1 owns:

- DocumentFamily;
- immutable Document versions;
- explicit lineage;
- temporal authority;
- scheduled future versions;
- cancellation/rescheduling semantics;
- CURRENT derivation;
- prohibition of overlapping authoritative periods;
- governance/approval eligibility as a concern distinct from technical processing;
- Region/Site applicability domain shape.

It does not own retrieval mechanics.

2. Every Document belongs to exactly one DocumentFamily

Confirmed.

A family with only one version is the normal single-version case.

Do not make DocumentFamily optional and do not require later migration from "ordinary documents" into "versioned documents."

This gives V1 one uniform model from first customer data onward.

3. CURRENT remains derived

Confirmed.

Do not introduce a midnight job whose correctness is required to flip a mutable is_current flag.

CURRENT is derived from authoritative temporal/governance facts at the evaluation time.

However, also preserve the separate invariant that overlapping authoritative periods within one DocumentFamily are invalid.

A latest-match query returning one row must never be used to hide inconsistent temporal history.

A future scheduled version does not displace the existing authoritative version until the future version actually becomes effective.

Cancelling or rescheduling the future version leaves the existing authoritative version intact.

4. Approval/governance state

Confirmed as orthogonal to ADR-0007's technical processing lifecycle.

The important architectural distinction is:

technical processing state answers:
"Is this version successfully processed/indexed?"

governance state answers:
"Is this version authorised to become authoritative knowledge?"

Do not collapse those into one state machine or a loose boolean.

However, keep V1 deliberately bounded.

Do not design a large approval-workflow engine in ADR-0017.

Please recommend the minimum governance-state model needed to support authoritative retrieval, while leaving richer approval workflows/UI/administration to the later Administration phase.

5. Region/Site applicability

Confirmed.

Stage 16.1 should define only the domain shape required for durable historical data and retrieval eligibility:

- structured Region entities;
- structured Site entities;
- stable IDs, never free text;
- hierarchy (Region -> descendant Sites);
- optional applicability;
- UNIVERSAL when no location restriction applies;
- Region applicability includes descendant Sites;
- Site/Region applicability is independent from user authorisation.

Administrative CRUD, hierarchy-management UI and configuration UX remain for Phase 19.

Please consider future hierarchy extension without overengineering V1; e.g. do not hard-code business logic that makes Region -> Site the only hierarchy the platform could ever represent, if a simple typed parent/child organisational-location model gives us the same V1 behaviour cleanly.

6. RetrievalPlanner implementation posture

This is the one point where I do not want to adopt your recommendation unchanged.

The architectural boundary is confirmed:

provider-neutral RetrievalPlanner

Input:
- natural-language question;
- authoritative evaluation time.

Output:
- typed RetrievalPlan:
  - CURRENT
  - VALID_AT_DATE
  - COMPARE
  - CLARIFICATION_REQUIRED

The planner performs no authorisation, storage lookup, retrieval, embedding or document resolution.

For production V1, I want natural-language inference to be the intended implementation, not a keyword/regex classifier as the primary planner.

The user experience deliberately requires questions such as:

"What was the policy when..."
"What changed in..."
"How is the new policy different?"
"What is our absence policy?"

to be semantically interpreted without UI mode selection.

My preferred V1 posture is therefore:

- provider-neutral LLM-backed planner;
- strict structured output/schema validation;
- deterministic date normalisation/validation where appropriate;
- deterministic safe fallbacks;
- CLARIFICATION_REQUIRED rather than guessing unresolved historical/comparison intent;
- a deterministic/fake planner for tests;
- repository-owned evaluation cases proving temporal-intent accuracy.

The LLM is being used only for a bounded language-understanding problem.

Everything security-, authority-, version- and retrieval-related after that remains deterministic application logic.

If you still believe a rule-based production planner is architecturally superior, challenge this explicitly before drafting ADR-0018, but do not quietly substitute one for the natural-language V1 capability we have agreed.

This planner belongs to Stage 16.2 / ADR-0018, not ADR-0017, so ADR-0017 only needs to preserve the temporal semantics the future planner will target.

7. EligibilityResolver

Confirmed as Laravel-owned.

Final intended sequence:

authenticated user
→ Laravel resolves AuthorisedKnowledgeScope
→ Python RetrievalPlanner returns RetrievalPlan
→ Laravel EligibilityResolver combines:
    - RetrievalPlan
    - AuthorisedKnowledgeScope
    - authoritative DocumentFamily/version/governance/temporal data
→ Laravel produces EligibleRetrievalScope
→ Laravel invokes Python Retriever with:
    - original query
    - EligibleRetrievalScope
    - authoritative generation identities
→ Python retrieves only inside that resolved scope

EligibilityResolver may only narrow AuthorisedKnowledgeScope, never expand it.

CLARIFICATION_REQUIRED short-circuits before EligibilityResolver and Retriever.

8. COMPARE

Confirmed as a genuine V1 requirement.

I agree with your proposed symbolic anchors:

- CURRENT
- AT_DATE(date)
- PREVIOUS

The planner expresses semantic anchors only.

Laravel's EligibilityResolver resolves each side independently into concrete, authorised DocumentVersion identities.

The resulting comparison scope must preserve two distinct sides.

Retriever searches each side independently and returns evidence grouped by side rather than merging both versions into one ranked candidate pool.

If either side cannot be resolved safely:

COMPARISON_SCOPE_INCOMPLETE

No silent substitute version.

9. ADR-0016 visibility

Confirmed unchanged.

Published Qdrant point
+
PostgreSQL INDEXED
remain structural visibility prerequisites.

ADR-0017 adds temporal/governance eligibility above those gates.

INDEXED must never be redefined to mean CURRENT or authoritative.

10. Laravel -> Python synchronous authentication

Agreed that this is a genuine unresolved architecture question.

However, do not block ADR-0017 on it.

ADR-0017 is a PostgreSQL/Document-domain decision and does not require Laravel to call Python synchronously.

Record this as a required decision for Stage 16.2 / ADR-0018 before retrieval implementation begins.

ADR-0018 must decide the authenticated, bounded, synchronous Laravel -> Python planning/retrieval call contract without silently reusing the ingestion-worker HMAC model where its principal/direction/failure semantics do not fit.

11. Phase 16 ADR sequence

Proceed with the existing roadmap shape:

R16-S01
ADR-0017 — Define Document Versioning and Temporal Authority

R16-S02
ADR-0018 — Define Retrieval Planning, Eligibility and the Retriever Contract

R16-S03
Implement Semantic Retrieval

R16-S04
Evaluation / quality-gate ADR

R16-S05
Implement Retrieval Evaluation

R16-S06
Hybrid retrieval + Reranker ADR

R16-S07
Implement Hybrid Retrieval + Reranking

Do not bundle evaluation or reranking decisions into ADR-0017/0018 beyond forward references.

12. Stale cross-references

Keep the three stale Phase 16 cross-references you found flagged for correction as part of the appropriate living-document update.

Do not modify accepted ADR bodies merely to renumber historical forward references.

Now please draft ADR-0017 only.

Before changing PROJECT_ROADMAP.md, IMPLEMENTATION_GUIDE.md or tasks.json:

- write the ADR draft as Proposed;
- report any newly exposed architectural issue;
- identify any place where the decisions above produce a conflict with an accepted ADR;
- stop for review.

Do not implement application code.
Do not accept the ADR.
Do not commit or tag anything.
