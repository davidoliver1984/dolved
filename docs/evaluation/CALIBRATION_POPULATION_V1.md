# Calibration Population V1

`CALIBRATION_POPULATION_V1` defines the evidence population that must exist
before Dolved may select an evidence threshold. It is evaluation governance,
not a benchmark result, calibration run or threshold decision.

The authoritative machine-readable specification is
`tests/evaluation/population-specifications/evidence-threshold-calibration/v1/specification.json`.
It is bound by SHA-256 from the compatibility requirements so its adequacy
rules cannot change silently.

## Ownership

- benchmark taxonomy owns intrinsic labels and evaluation facets,
  EvidenceUnits, expected outcomes and document relationships;
- the population specification owns threshold-selection representation,
  minimum case counts, diversity and independence requirements;
- compatibility policy owns the mechanical pass/fail decision;
- a future calibration run owns provider observations only.

Semantic cases are the statistical adequacy unit. Variants provide linguistic
diversity but never add case-count credit. Every semantic cluster remains in
one split and every case contributes at most once to each group count.

## Population shape

The specification requires at least 40 semantic cases and prefers 44–48,
across at least eight domains. No one domain may exceed 25% of the population.
It defines eleven explicit semantic groups, each with versioned benchmark
labels, a rationale, protected failure mode, minimum/preferred case counts,
minimum threshold-evaluable representation and machine-checkable diversity
constraints. Preferred counts are advisory; minimum counts are compatibility
gates.

Threshold calibration requires eight threshold-sensitive controlled cases:
four `INSUFFICIENT_EVIDENCE` and four `COMPARISON_SCOPE_INCOMPLETE`. The latter
include cases where each COMPARE side is independently empty after
thresholding. `NO_ELIGIBLE_EVIDENCE`, `CLARIFICATION_REQUIRED`,
`TEMPORAL_SCOPE_UNRESOLVED` and `NO_RETRIEVAL_CANDIDATES` remain separately
tracked system-acceptance outcomes; they do not count as threshold evidence.

Groups intentionally overlap. A case contributes once to each matching group,
never more than once within one group. The temporal-authority umbrella does not
waive VALID_AT_DATE or historical minimums, and applicability does not waive
location-alias minimums.

The specification references benchmark-owned evaluation facets; it does not
create population-private semantic tags. Benchmark V3 will need controlled,
versioned intrinsic-slice and `evaluation_facets` vocabularies before cases are
authored. Declaring a facet is insufficient: required facets must occur among
the cases matched to the relevant semantic group.

Domain requirements cover high-risk care, physical safety, regulatory
responsibility and workforce/operations. Independence evidence must prove no
engineering or held-out overlap, no split semantic cluster, no score-driven
selection or post-result reassignment, and that the population was frozen
before provider execution.

Machine enforcement covers identities, case/split/taxonomy digests,
EvidenceUnit and source-lineage presence where required, expected outcomes,
split overlap and post-provider lineage. Semantic quality, representative
coverage, author rationale and governance review are intentionally human-owned.
Those judgements are bound through a versioned review artefact for the exact
case-identity digest rather than represented as automatic validation.

## Benchmark relationship

Benchmark V2 and split V1 are immutable and cannot realise this specification:
their calibration population is the historical incompatible CAL-EXP-0001
population. The future executable population therefore requires a new
immutable benchmark release rather than a calibration-only mutation of
Benchmark V2. This document does not create that release or assign cases.

## Execution boundary

A future run must first compile a privacy-safe population manifest from its
isolated snapshot and independently produced split-overlap evidence. The
provider-free compatibility validator checks the population specification,
threshold-policy binding, semantic groups, controlled rejection, benchmark
taxonomy, domain balance and independence. Provider execution is permitted
only when this composition result is `compatible: true`.

After the single provider pass and before threshold replay, a second use of the
same validator supplies threshold-execution evidence. It requires complete
pre-threshold lineage, per-group reranker-evaluable minima, at least eight
reranked candidates and at least two distinct observed reranker scores. This
gate establishes that threshold evidence exists; it never chooses a threshold.

Ordinary cases require identity, cluster, domain, source lineage, expected
EvidenceUnits or exact controlled outcome, and split-independence review.
Temporal, applicability, adversarial and threshold-sensitive cases add only
their relevant specialised metadata and justification.
