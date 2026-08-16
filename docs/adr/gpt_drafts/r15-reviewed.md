Thanks — I agree with most of the review, but I want to correct one framing point before we proceed.

On document versioning:

Please do not treat this as Phase 16 accidentally reopening a rejected V1 decision.

ADR-0007 deliberately deferred versioning until a genuine requirement existed. Its own wording anticipated that a future ADR could introduce explicit document relationships once that requirement emerged.

Phase 16 is that point.

The concrete V1 requirements now driving the decision are:

- CURRENT retrieval;
- VALID_AT_DATE retrieval;
- COMPARE retrieval;
- scheduled future versions;
- preserving historical source-of-truth behaviour;
- explicit document lineage;
- ensuring future V2 features can build on recorded history without reinterpreting existing customer data.

So please frame the new decision as:

“Phase 16 resolves a document-versioning decision ADR-0007 explicitly deferred.”

The eventual ADR should therefore state that it supersedes ADR-0007 in part, narrowly and intentionally, by introducing explicit DocumentFamily / immutable version lineage and temporal-authority semantics.

That is architectural evolution, not accidental scope creep.

I also agree with your finding that our discussion had started treating “Phase 16 ADR” too broadly.

Please preserve the roadmap’s existing independent architecture stages:

- Stage 16.1 — document freshness/versioning/temporal authority;
- Stage 16.2 — retrieval contract;
- Stage 16.4 — evaluation and quality gates;
- Stage 16.6 — hybrid retrieval + reranking.

ADR-0013 already committed evaluation and hybrid/reranking to their own later ADRs, so do not bundle those into the first retrieval ADR.

Our “one primary ADR per phase” philosophy is not meant to override already-identified independent lifecycle decisions. Additional ADRs are appropriate where the decision genuinely deserves separate review, as here.

I would like the Phase 16 architecture to proceed in that existing staged shape.

A few points from your review I explicitly agree with:

1. EligibilityResolver should be Laravel-owned.

Laravel owns:
- access;
- PostgreSQL;
- Document / DocumentFamily / version metadata;
- approval;
- temporal authority;
- eligibility.

Python should receive only the already-resolved EligibleRetrievalScope for retrieval.

The intended flow should therefore be:

Authenticated user
→ Laravel resolves AuthorisedKnowledgeScope
→ Python RetrievalPlanner interprets the natural-language question
→ Laravel EligibilityResolver combines the plan with authorised scope and authoritative document/version metadata
→ Laravel passes EligibleRetrievalScope plus generation identities to Python Retriever
→ Python performs semantic retrieval only inside that resolved scope

That ownership split is important and should be explicit.

2. CLARIFICATION_REQUIRED short-circuits the pipeline.

If the planner returns CLARIFICATION_REQUIRED:
- EligibilityResolver is not invoked;
- Retriever is not invoked;
- no broader fallback retrieval occurs.

3. ADR-0016’s dual visibility gate remains unchanged.

A published Qdrant point + PostgreSQL INDEXED remains the structural visibility prerequisite.

Temporal/version eligibility is a third additive gate above that:

published
+
INDEXED
+
approved / temporally authoritative / permitted for the requested mode
=
eligible evidence

INDEXED must not be redefined to mean “currently authoritative.”

4. CURRENT should be derived at query time from authoritative data, not maintained by a fragile scheduled Boolean flip.

However, I do NOT agree that this means overlapping authoritative periods become impossible merely because the resolver selects the latest matching row.

Please preserve both principles:

- CURRENT is derived, not flipped by a midnight scheduler;
- overlapping authoritative periods for one DocumentFamily are prohibited as a real domain invariant.

A query choosing one row must not hide inconsistent historical authority.

The intended semantic rule is roughly:

CURRENT =
the approved, indexed, non-withdrawn version that is authoritative at the evaluation time according to valid, non-overlapping temporal history.

A future scheduled version does not displace the current version until it actually becomes effective.

Cancelling or rescheduling the future version leaves the current version intact.

5. Access and applicability remain separate.

Please preserve this distinction:

- AuthorisedKnowledgeScope answers: “What is this user allowed to see?”
- applicability answers: “Where / to whom does this document apply?”

For V1, access is explicit Laravel-owned permission configuration.

Site/region applicability is optional business metadata.

If no applicability restriction exists, the document is UNIVERSAL within the authorised scope.

Regions and sites are stable structured entities, never free text.

Region hierarchy should be supported so a document applying to a region applies to descendant sites.

User access to a site/region and document applicability to a site/region must remain independent checks.

6. The RetrievalPlanner remains provider-neutral and deliberately narrow.

Input:
- natural-language question;
- authoritative evaluation time.

Output:
- typed RetrievalPlan.

V1 modes:
- CURRENT;
- VALID_AT_DATE;
- COMPARE;
- CLARIFICATION_REQUIRED.

It does not receive:
- user role;
- department membership;
- permissions;
- PostgreSQL data;
- Qdrant state;
- document IDs.

It does not:
- authorise;
- retrieve;
- resolve documents;
- embed;
- rerank;
- answer.

It only interprets the language and temporal/comparison intent.

We do want natural-language inference in V1 rather than requiring users to choose a mode in the UI.

Please challenge the exact V1 implementation mechanism, but preserve the architectural capability. If you believe a deterministic implementation should precede an LLM-backed planner, explain how it can still correctly support natural-language CURRENT / VALID_AT_DATE / COMPARE semantics without reducing the design to crude keyword matching.

7. COMPARE is a genuine V1 requirement.

Please treat it as intentional, not merely future-proofing.

The user experience we want includes questions such as:

- “What changed in the latest policy?”
- “Compare the current version with the 2022 version.”
- “How has this policy changed?”

The result shape should preserve comparison sides explicitly.

Please recommend the cleanest contract for this before drafting Stage 16.2.

8. Evaluation and reranking remain later Phase 16 decisions.

Our discussion direction for those stages remains:

Evaluation:
- repository-owned, versioned evaluation corpus;
- Recall@K;
- Precision@K;
- MRR;
- nDCG;
- latency;
- cost;
- golden regression cases;
- release-quality gates based on measured regression rather than subjective judgement.

Hybrid / reranking:
- provider-neutral Reranker boundary;
- hosted V1 provider likely, but provider choice decided later;
- broad candidate retrieval → reranking → evidence selection;
- thresholds calibrated from evaluation, never guessed.

Do not include those decisions in the first ADR except as forward dependencies / already-planned later stages.

Before drafting anything, please now give me a revised recommendation for the Phase 16 stage/ADR sequence.

Specifically:

1. Confirm the final Stage 16.1 scope and recommended ADR title.
2. Confirm whether Stage 16.1 alone should own DocumentFamily/versioning/temporal authority, or whether you still believe that requires an additional stage despite the roadmap already having four architecture stages.
3. Confirm the final Stage 16.2 scope and recommended ADR title.
4. Confirm EligibilityResolver ownership and Laravel↔Python call sequence.
5. Recommend the COMPARE result/scope contract shape.
6. Identify any remaining architectural decision we genuinely still need to settle before Stage 16.1 drafting begins.
7. Flag the stale Phase/Stage cross-references you found, but do not modify them yet.

Do not modify files.
Do not draft the ADR yet.
Do not change roadmap/guide/tasks.
Do not implement anything.

Stop after the revised recommendation for review.