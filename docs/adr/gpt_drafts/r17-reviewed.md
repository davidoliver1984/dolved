Thanks — ADR-0018 is close, but I do not want to accept it yet.

Please amend only ADR-0018.

Do not modify application code.
Do not modify prior accepted ADRs.
Do not update PROJECT_ROADMAP.md, IMPLEMENTATION_GUIDE.md, tasks.json or journals yet.
Do not commit or tag anything.
Keep Status as Proposed and stop for review afterward.

There are four issues to address.

----------------------------------------------------------------------
1. Remove direct Python PostgreSQL hydration
----------------------------------------------------------------------

The current draft says the Python Retriever hydrates authoritative chunk text and provenance directly from PostgreSQL.

I do not want retrieval to introduce direct Python PostgreSQL reads.

The accepted service-boundary direction remains:

Laravel owns PostgreSQL and authoritative relational/domain data.
Python owns AI/ML mechanics and vector-store interaction.

The retrieval path should therefore be:

Python Retriever
→ performs scoped vector search
→ returns candidate chunk/document/version identities + scores + retrieval lineage

Laravel
→ batch-hydrates authoritative chunk text/provenance from PostgreSQL
→ performs the final defensive identity/eligibility check
→ assembles the application-level RetrievalResult

Please revise the contract accordingly.

The Python Retriever must not directly query PostgreSQL.

This also removes the currently-documented eligibility staleness trade-off: Laravel can re-check returned candidate identities against current authoritative eligibility while hydrating them.

Do not move eligibility logic into Python.

For future reranking, do not pre-emptively grant Python relational reads. If candidate text must cross back to Python for reranking later, Stage 16.7 can define a bounded Laravel→Python reranking contract explicitly.

----------------------------------------------------------------------
2. Add a narrow semantic applicability reference
----------------------------------------------------------------------

ADR-0017 makes Region/Site applicability a hard business-eligibility dimension.

Example:

“What is the medication procedure at Blackpool?”

The planner must be able to preserve the fact that “Blackpool” is part of the requested applicability context so Laravel can deterministically resolve it.

At present the RetrievalPlan carries temporal/comparison intent but no way to carry this semantic location reference.

Please add a deliberately narrow optional planner output such as:

applicability_reference

or equivalent.

Requirements:

- semantic reference only;
- no database IDs invented by the planner;
- no permission/access meaning;
- no free-text metadata filter pushed directly to Qdrant;
- Laravel resolves the reference against authoritative OrganisationalLocation names/aliases;
- Laravel validates the resolved location against AuthorisedKnowledgeScope;
- Laravel then applies ADR-0017’s version applicability rules deterministically.

This must not reopen the earlier mistake where prompt wording became access metadata.

For example:

“Show me the HR policy”

must NOT become department_id = HR merely because the text contains “HR”.

Location/applicability is special only because ADR-0017 explicitly defines it as an authoritative eligibility dimension.

Please decide whether the minimum contract should allow:
- one location reference in V1;
- or a bounded list if you believe multiple explicit locations are needed.

Keep it as small as possible.

----------------------------------------------------------------------
3. Correct NO_SEMANTIC_MATCH semantics
----------------------------------------------------------------------

The current retrieval-outcome taxonomy includes:

NO_SEMANTIC_MATCH

But calibrated evidence thresholds are explicitly deferred until later evaluation / hybrid / reranking work.

Plain dense retrieval with candidate_k > 0 will usually return some nearest neighbours even if they are weak.

Without a calibrated acceptance policy, ADR-0018 cannot truthfully classify low-scoring results as “no semantic match.”

Please correct this.

My preferred V1 distinction is:

NO_RETRIEVAL_CANDIDATES

meaning the scoped retriever returned zero candidates.

Then reserve semantic-quality rejection for the later evidence-selection/evaluation architecture.

Alternatively, if you strongly prefer to retain NO_SEMANTIC_MATCH as a future enum value, state explicitly that Stage 16.4 semantic retrieval must not emit it based on raw score quality until a later accepted evidence-quality policy defines what “match” means.

Do not invent a similarity threshold in ADR-0018.

Raw vector scores remain uncalibrated retrieval scores, not probabilities or quality guarantees.

----------------------------------------------------------------------
4. Strengthen rc1 transport security and replay semantics
----------------------------------------------------------------------

I agree with the new independently-versioned Laravel→Python rc1 HMAC protocol.

Keep:
- HMAC-SHA256;
- purpose-bound signing;
- method/path/body-digest binding;
- workspace binding;
- Key ID/key-ring rotation;
- freshness window;
- distinct retrieval-caller principal;
- no worker lease/event_id semantics.

However, HMAC provides authentication/integrity, not confidentiality.

Retrieval requests contain the raw user question and retrieval responses may contain protected knowledge identifiers/evidence metadata.

Please explicitly require authenticated TLS for rc1 transport.

Do not imply HMAC alone protects content in transit.

Also examine replay protection.

A timestamp freshness window alone means a captured valid request can potentially be replayed during that window.

Read-only retrieval means we do not need an ingestion-style durable idempotency ledger or lease, but replay still matters because a replayed authorised request may return protected knowledge.

Please recommend a bounded V1 replay defence, preferably something like:

- signed unique request_id / nonce;
- short freshness window;
- bounded server-side replay suppression/cache for recently accepted request IDs;
- purpose/workspace/path/body binding remains unchanged.

Keep this lighter-weight than the ingestion protocol.

No queue.
No lease.
No durable processing-attempt ledger.

But do not leave rc1 deliberately replayable within its freshness window if a simple bounded suppression mechanism closes that gap.

Please include safe behaviour for:
- duplicate identical retry caused by network uncertainty;
- replayed request with same request_id;
- retry with a fresh request_id;
- expired request;
- wrong workspace/purpose/signature.

----------------------------------------------------------------------
Everything else
----------------------------------------------------------------------

Unless one of these changes genuinely forces a consequence, preserve the rest of ADR-0018.

I continue to agree with:

- provider-neutral, LLM-backed RetrievalPlanner for V1;
- strict typed RetrievalPlan;
- CURRENT / VALID_AT_DATE / COMPARE / CLARIFICATION_REQUIRED;
- symbolic CURRENT / AT_DATE / PREVIOUS anchors;
- Laravel-owned AuthorisedKnowledgeScope;
- Laravel-owned deterministic EligibilityResolver;
- narrow-only eligibility invariant;
- CLARIFICATION_REQUIRED short-circuit;
- ADR-0016 published + INDEXED prerequisites;
- PRIMARY / COMPARISON two-sided COMPARE semantics;
- provider-neutral Retriever;
- candidate_k terminology;
- explicit semantic RetrievalResult outcomes;
- raw retrieval scores never treated as probabilities;
- evaluation, reranking and thresholds remaining out of scope;
- corrected R16-S03/R16-S04 stage split.

----------------------------------------------------------------------
Required report
----------------------------------------------------------------------

After amending, report:

1. Exact sections changed.
2. Final Laravel/Python responsibility split for candidate search, hydration and final eligibility checking.
3. Final applicability-reference contract.
4. Final zero-candidate / semantic-quality outcome semantics.
5. Final rc1 confidentiality requirement.
6. Final rc1 replay-protection model.
7. How safe network retries differ from replay.
8. Any newly exposed architectural issue.
9. Confirmation that only ADR-0018 changed.

Do not accept the ADR.
Do not modify roadmap/guide/tasks.
Do not implement.
Do not commit.
Do not tag.
Stop for review.