Codex performed the pre-implementation review of ADR-0019 and found several
implementation-significant issues.

I agree with the findings below.

Because ADR-0019 has not yet been committed or tagged and R16-S06 has not begun,
please treat this as a final pre-commit acceptance review rather than creating a
follow-up ADR.

Temporarily return ADR-0019 to Proposed while making these bounded amendments.
Do not modify prior accepted ADRs.
Do not implement R16-S06.
Do not commit or tag anything.
Do not modify unrelated files.

Please amend ADR-0019 only first, then stop for review before re-running the
acceptance workflow.

----------------------------------------------------------------------
1. Correct adversarial-case ownership
----------------------------------------------------------------------

The current ADR implies malicious document content can corrupt RetrievalPlanner.

That contradicts ADR-0018.

RetrievalPlanner receives:
- the user question;
- authoritative evaluation time;

and does NOT receive retrieved documents.

Please split the adversarial case families correctly:

Planner robustness:
- adversarial/malicious user questions;
- attempts to manipulate temporal-mode/applicability interpretation;
- ambiguous or misleading natural-language requests.

Retrieval robustness:
- adversarial document content;
- prompt-like instructions embedded in documents;
- misleading passages;
- conflicting evidence;
- content intended to distort embedding/ranking behaviour.

Generation robustness, later:
- retrieved document content attempting to influence generated answers;
- instruction-following/prompt-injection effects on generation.

The last category belongs to Phase 17 generation evaluation, not RetrievalPlanner
evaluation.

This is a correction preserving ADR-0018, not a new architectural decision.

----------------------------------------------------------------------
2. Define the repository-owned evidence unit
----------------------------------------------------------------------

The source-content anchoring direction is correct, but the metric unit of truth
must be made explicit.

Introduce a repository-owned stable evidence unit, conceptually:

EvidenceExpectation / EvidenceUnit

- evidence_id;
- authoritative Document / DocumentFamily / version identity;
- one or more corpus-authored canonical source excerpts;
- relevance grade where applicable;
- deterministic coverage/matching requirement;
- optional semantic notes useful to corpus authors, but not required by metric
  execution.

The evidence unit is the stable ground-truth concept.

Generated chunks are merely one representation that may cover it.

Please establish these architectural semantics:

Recall@K:
- denominator = distinct expected evidence units;
- numerator = distinct expected evidence units covered within top K;
- multiple retrieved chunks covering the same evidence_id never increase recall
  more than once.

Precision@K:
- ranked chunks receive deterministic relevance through the evidence unit(s)
  they cover;
- duplicate chunks covering the same evidence must not gain artificial repeated
  credit;
- define the semantic requirement but leave the exact duplicate-credit formula
  to R16-S06 if appropriate.

MRR:
- based on the rank of the first retrieved candidate satisfying a required
  evidence unit, according to the case's declared relevance expectations.

nDCG:
- relevance is assigned through matched evidence units and their repository-owned
  relevance grades;
- duplicate treatment must be deterministic;
- the ADR need not mandate one numerical grading scale beyond requiring a stable
  repository-owned definition.

Multi-evidence cases:
- declare the complete required evidence-unit set;
- completeness is measured by coverage of those distinct units.

Evidence spanning multiple chunks:
- may be satisfied through deterministic combined coverage;
- must not require one individual chunk to contain an entire canonical excerpt;
- the exact normalisation/fuzzy/coverage algorithm remains R16-S06 implementation
  work, but it must be deterministic, versioned, and recorded as evaluation
  configuration.

The architectural invariant is:

Ground truth measures semantic source evidence, never accidental chunk boundaries.

Please retain the existing decision not to use ExtractedElement,
NormalisedElement, or Chunk identity as the stable cross-run ground-truth anchor,
because ADR-0010/0011 do not guarantee those identities across independent
reprocessing/chunking configurations.

----------------------------------------------------------------------
3. Make evaluator-model ownership explicit for RAGAS
----------------------------------------------------------------------

We deliberately want a concrete RAGAS integration in V1 behind the accepted
framework-neutral boundary.

Please make the dependency structure explicit:

ModelAssistedEvaluator
    -> RagasEvaluator

RagasEvaluator receives an injected, configuration-owned evaluator model/client.

Do not allow RagasEvaluator to instantiate an implicit provider/model globally
inside the adapter.

Experiment lineage for model-assisted evaluation must record at least:

- evaluator implementation;
- RAGAS version;
- judge provider;
- judge model;
- relevant evaluator prompt/settings/configuration;
- temperature/seed where supported;
- token usage;
- request/call count;
- latency;
- estimated cost.

No RAGAS/provider-specific type may escape the RagasEvaluator adapter.

----------------------------------------------------------------------
4. Define offline and live-test behaviour
----------------------------------------------------------------------

Ordinary repository tests must not require credentials or paid network calls.

Require:

- deterministic fake ModelAssistedEvaluator for application/harness tests;
- fake evaluator-model/client for RagasEvaluator translation/adapter tests;
- deterministic tests for application-owned result mapping and failure handling;
- a genuine RAGAS + provider integration test only as:
  - explicit;
  - credential-dependent;
  - opt-in;
  - excluded from ordinary repository/CI quality gates unless deliberately
    enabled.

A failure of model-assisted evaluation must produce a controlled advisory
evaluation failure.

It must NOT:
- erase deterministic metric results;
- turn deterministic hard-gate success/failure into unknown;
- cause an otherwise reproducible deterministic experiment result to disappear.

Record the model-assisted failure in experiment/report lineage.

Codex reports that a non-mutating resolution of RAGAS 0.4.3 succeeds under the
repository's Python 3.14 environment but introduces a substantial transitive
dependency set and proposes dependency changes.

Do not encode those exact package-resolution details as architectural invariants,
but record the consequence that adding the concrete RAGAS dependency requires a
full repository regression/dependency verification during R16-S06.

----------------------------------------------------------------------
5. Make the V1 ModelAssistedEvaluationRequest retrieval-specific
----------------------------------------------------------------------

There is currently a contradiction:

one section permits a reference answer in the V1 request,
while another correctly states that Phase 17 will define the answer/reference
fields required for generation evaluation.

Resolve this in favour of the narrower V1 contract.

Phase 16 ModelAssistedEvaluationRequest should contain only what retrieval-time
context evaluation genuinely needs, conceptually:

- question;
- retrieved contexts/evidence;
- metric identity;
- evaluator configuration/lineage as appropriate.

Do NOT include generated_answer or reference_answer merely for future-proofing.

Phase 17 extends/versions the application-owned contract when answer-dependent
metrics become real requirements.

Do not design today's request as a nullable superset of hypothetical future
evaluation modes unless there is a concrete architectural reason.

----------------------------------------------------------------------
6. Correct RAGAS ContextRelevance semantics
----------------------------------------------------------------------

Do not describe the V1 RAGAS context-relevance signal as identifying the exact
retrieved chunk responsible for disagreement.

Per Codex's review of the documented metric, ContextRelevance evaluates the
supplied retrieved-context set as an aggregate.

Therefore:

- it may provide an advisory model-assisted judgement over the retrieved context
  set;
- it may disagree with deterministic evidence-unit metrics and flag the case for
  investigation;
- it must not be described as deterministically identifying which exact candidate
  is relevant or irrelevant unless a future selected metric actually provides
  that property.

Preserve deterministic evidence matching as the authoritative candidate-level
evaluation mechanism.

----------------------------------------------------------------------
7. Align access/isolation cases with the actual V1 permission model
----------------------------------------------------------------------

Do not make R16-S06 invent document-level granular permissions.

The mandatory V1 security evaluation corpus should cover what the platform
actually implements:

- active workspace membership;
- non-member denial;
- cross-workspace isolation;
- workspace concealment / fail-closed behaviour;
- membership-based authorisation.

Granular per-document/access-group permission cases become mandatory only when
such a permission model is implemented.

Keep the schema/case taxonomy extensible for them, but do not manufacture a
permission model merely to satisfy the benchmark.

----------------------------------------------------------------------
8. Add content digests to immutable lineage
----------------------------------------------------------------------

A version label alone cannot prove an allegedly immutable corpus or policy was
not edited in place.

Experiment and accepted-baseline lineage must include at least:

Evaluation corpus:
- schema version;
- corpus version;
- canonical corpus-content digest.

Quality policy:
- policy version;
- canonical policy-content digest.

Harness:
- implementation version and/or repository commit sufficient to identify the
  executable metric implementation.

Baseline promotion must reject or explicitly treat as a different artefact any
case where an already-baselined corpus_version or policy_version now resolves to
a different content digest.

The same named immutable version must never silently mean different content.

----------------------------------------------------------------------
9. Define phrasing-variant weighting
----------------------------------------------------------------------

Stable semantic cases may contain multiple question variants.

Use case-first aggregation for V1.

Conceptually:

- evaluate every phrasing variant;
- aggregate variant results within the semantic case;
- then give semantic cases equal weight at the corpus/slice aggregation level
  unless an explicitly versioned future quality policy says otherwise.

Adding five paraphrases must not make one business information need six times as
important as a case with one phrasing.

Per-variant results should remain available in machine-readable reports for
diagnosis.

----------------------------------------------------------------------
10. Clarify stochastic reproducibility
----------------------------------------------------------------------

Keep the invariant that deterministic metric implementations must reproduce
exactly for identical recorded metric inputs.

Do NOT imply that live LLM planner calls or live RAGAS judge calls must themselves
return byte-identical outputs.

For stochastic/model-assisted experiment lineage, record where applicable:

- trial count;
- provider/model;
- evaluator/planner configuration;
- temperature;
- seed where supported;
- per-trial results;
- aggregate statistics;
- variance/dispersion.

The distinction should be explicit:

deterministic metric reproducibility
!=
live model-output determinism.

Quality policy may later decide how many trials or what variance is acceptable;
do not invent those numerical requirements in ADR-0019.

----------------------------------------------------------------------
11. Preserve the existing RAGAS architectural decision
----------------------------------------------------------------------

Do NOT revert the previous amendment.

V1 still includes:

- application-owned deterministic metrics;
- provider/framework-neutral ModelAssistedEvaluator;
- concrete RagasEvaluator;
- an appropriate retrieval-time context-relevance-style advisory metric in
  Phase 16;
- later Phase 17 extension of the same evaluator seam for faithfulness,
  answer-relevancy, answer-correctness and other genuinely answer-dependent
  metrics.

RAGAS remains additional information, not the source of deterministic truth.

A model-assisted metric may become a comparative/baseline-tracked quality metric
if later justified.

It may never replace the deterministic hard gates for:
- tenancy;
- authorisation;
- temporal authority;
- applicability;
- other exactly testable safety/correctness properties.

----------------------------------------------------------------------
12. R16-S06 implementation-boundary consequence
----------------------------------------------------------------------

Codex correctly identified that the current R16-S06 Implementation Guide stub is
now stale relative to accepted ADR-0019.

Do NOT modify the guide/tasks during this amendment round.

But confirm in the ADR/report that the final R16-S06 implementation boundary
must include the accepted responsibilities:

- repository-owned corpus/schema;
- evidence units and deterministic matching;
- Recall@K;
- Precision@K;
- MRR;
- nDCG;
- slicing;
- experiment/result schema;
- corpus/policy digesting;
- accepted baselines;
- baseline promotion;
- comparison/report generation;
- manual gate records;
- ModelAssistedEvaluator;
- RagasEvaluator;
- evaluator-model configuration/injection;
- offline/fake tests;
- optional live integration test;
- relevant dependency/configuration work.

Its eventual commit boundary may therefore legitimately include, as applicable:

- apps/ai;
- evaluation contracts/schema/data;
- tests;
- scripts;
- reports;
- configuration/dependency files;
- documentation/tracker changes.

Do not begin any of this implementation until ADR-0019 is re-reviewed and
accepted.

----------------------------------------------------------------------
Everything else
----------------------------------------------------------------------

Preserve the rest of ADR-0019 unless these changes genuinely force a consequence.

In particular retain:

- evaluation as a first-class architectural capability;
- repository ownership;
- immutable/versioned corpus;
- stable semantic case identity;
- layered planner/eligibility/retrieval/operational evaluation;
- first-class slices;
- hard failures versus comparative quality;
- candidate run != accepted baseline;
- deliberate baseline promotion;
- initial human-reviewed quality gate;
- framework-neutral architecture;
- RAGAS as a replaceable concrete V1 implementation;
- evaluation/generation separation;
- no invented numerical tolerances before the first measured baseline.

----------------------------------------------------------------------
Required report
----------------------------------------------------------------------

After amending ADR-0019, report:

1. Exact sections changed.
2. Final evidence-unit contract.
3. Final Recall/Precision/MRR/nDCG semantics over evidence units.
4. Final model-assisted evaluator/model ownership.
5. Final offline versus live RAGAS test policy.
6. Final Phase-16 RAGAS ContextRelevance semantics.
7. Final V1 access/isolation case scope.
8. Final corpus/policy digest and immutable-baseline rule.
9. Final semantic-case variant weighting.
10. Final deterministic-vs-stochastic reproducibility distinction.
11. Any newly exposed architectural issue.
12. Whether R16-S06 remains the correct implementation owner and its corrected
    implementation boundary.
13. Confirmation that only ADR-0019 changed during the amendment round.

Keep ADR-0019 Proposed for review.
Do not implement.
Do not commit.
Do not tag.
Stop for review.