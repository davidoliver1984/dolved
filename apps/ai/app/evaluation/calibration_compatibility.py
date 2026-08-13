"""Provider-free calibration population compatibility governance."""

from __future__ import annotations

import hashlib
import json
from collections import Counter
from typing import Any, Literal

from pydantic import Field, model_validator

from app.evaluation.models import Identifier, StrictModel

Sha256 = str
PipelineFailureKind = Literal[
    "lost_evaluation_case",
    "planner_failure_before_threshold",
    "retrieval_failure_before_threshold",
    "provider_failure_before_threshold",
    "incomplete_pre_threshold_lineage",
]

PROVIDER_FAILURE_CATEGORIES = frozenset(
    {
        "provider_authentication",
        "provider_quota",
        "provider_rate_limit",
        "provider_unavailable",
        "provider_request_rejected",
        "transport_error",
    }
)


def content_digest(value: object) -> str:
    """Return the repository's deterministic canonical JSON SHA-256 digest."""

    encoded = json.dumps(
        value, ensure_ascii=False, sort_keys=True, separators=(",", ":")
    ).encode()
    return hashlib.sha256(encoded).hexdigest()


class DiversityConstraints(StrictModel):
    minimum_domain_count: int = Field(ge=1)
    minimum_document_family_count: int = Field(ge=1)
    required_benchmark_facets: tuple[Identifier, ...] = ()


class SemanticGroupRequirement(StrictModel):
    group_id: Identifier
    version: str
    labels: tuple[Identifier, ...] = Field(min_length=1)
    rationale: str = Field(min_length=1)
    protected_failure_mode: str = Field(min_length=1)
    minimum_case_count: int = Field(ge=1)
    preferred_case_count: int = Field(ge=1)
    minimum_threshold_evaluable_case_count: int = Field(ge=1)
    diversity: DiversityConstraints

    @model_validator(mode="after")
    def valid_group(self) -> SemanticGroupRequirement:
        if len(self.labels) != len(set(self.labels)):
            raise ValueError("slice-group labels must be unique")
        if self.preferred_case_count < self.minimum_case_count:
            raise ValueError("preferred case count cannot be below the minimum")
        if self.minimum_threshold_evaluable_case_count > self.minimum_case_count:
            raise ValueError(
                "threshold-evaluable minimum cannot exceed the group minimum"
            )
        return self


class ControlledOutcomeRequirement(StrictModel):
    outcome: Identifier
    minimum_case_count: int = Field(ge=1)
    preferred_case_count: int = Field(ge=1)
    rationale: str = Field(min_length=1)

    @model_validator(mode="after")
    def valid_counts(self) -> ControlledOutcomeRequirement:
        if self.preferred_case_count < self.minimum_case_count:
            raise ValueError("preferred case count cannot be below the minimum")
        return self


class ControlledRejectionRequirements(StrictModel):
    threshold_sensitive_outcomes: tuple[ControlledOutcomeRequirement, ...] = Field(
        min_length=1
    )
    acceptance_only_outcomes: tuple[Identifier, ...] = Field(min_length=1)
    minimum_threshold_sensitive_case_count: int = Field(ge=1)
    require_primary_empty_compare_case: bool
    require_comparison_empty_compare_case: bool

    @model_validator(mode="after")
    def valid_outcomes(self) -> ControlledRejectionRequirements:
        outcomes = [item.outcome for item in self.threshold_sensitive_outcomes]
        if len(outcomes) != len(set(outcomes)):
            raise ValueError("controlled-rejection outcomes must be unique")
        if len(self.acceptance_only_outcomes) != len(
            set(self.acceptance_only_outcomes)
        ):
            raise ValueError("acceptance-only outcomes must be unique")
        if set(outcomes).intersection(self.acceptance_only_outcomes):
            raise ValueError("threshold-sensitive and acceptance-only outcomes overlap")
        if self.minimum_threshold_sensitive_case_count < sum(
            item.minimum_case_count for item in self.threshold_sensitive_outcomes
        ):
            raise ValueError("threshold-sensitive total is below outcome minima")
        return self


class ThresholdExecutionRequirements(StrictModel):
    require_complete_pre_threshold_lineage: bool
    minimum_distinct_reranker_scores: int = Field(ge=2)
    minimum_total_reranked_candidates: int = Field(ge=1)


class DomainRequirement(StrictModel):
    requirement_id: Identifier
    domains: tuple[Identifier, ...] = Field(min_length=1)
    minimum_total_case_count: int = Field(ge=1)
    per_domain_minimum_case_count: dict[Identifier, int] = Field(default_factory=dict)
    rationale: str = Field(min_length=1)

    @model_validator(mode="after")
    def valid_domains(self) -> DomainRequirement:
        if len(self.domains) != len(set(self.domains)):
            raise ValueError("domain requirement domains must be unique")
        if not set(self.per_domain_minimum_case_count).issubset(self.domains):
            raise ValueError("per-domain minima reference domains outside the group")
        return self


class IndependenceRequirements(StrictModel):
    forbid_engineering_overlap: bool
    forbid_held_out_overlap: bool
    forbid_score_driven_selection: bool
    forbid_post_result_reassignment: bool
    require_semantic_clusters_together: bool
    require_population_frozen_before_provider_execution: bool


class CaseAuthoringRequirements(StrictModel):
    machine_enforced: tuple[Identifier, ...] = Field(min_length=1)
    human_reviewed: tuple[Identifier, ...] = Field(min_length=1)
    specialised_human_review: dict[Identifier, tuple[Identifier, ...]]
    require_versioned_human_review_artifact: bool


class CalibrationPopulationSpecification(StrictModel):
    schema_version: Literal["v1"] = "v1"
    population_spec_id: Identifier
    population_spec_version: Literal["v1"] = "v1"
    purpose: Literal["evidence-threshold-selection"]
    benchmark_family: Identifier
    taxonomy_baseline_version: str
    taxonomy_baseline_digest: Sha256 = Field(pattern=r"^[a-f0-9]{64}$")
    split_taxonomy_baseline_version: str
    minimum_case_count: int = Field(ge=1)
    preferred_case_count_minimum: int = Field(ge=1)
    preferred_case_count_maximum: int = Field(ge=1)
    minimum_domain_count: int = Field(ge=1)
    maximum_single_domain_share: float = Field(gt=0, le=1)
    semantic_groups: tuple[SemanticGroupRequirement, ...] = Field(min_length=1)
    controlled_rejection: ControlledRejectionRequirements
    execution_requirements: ThresholdExecutionRequirements
    domain_requirements: tuple[DomainRequirement, ...] = Field(min_length=1)
    independence: IndependenceRequirements
    case_authoring_requirements: CaseAuthoringRequirements
    exclusion_rules: tuple[str, ...] = Field(min_length=1)
    benchmark_relationship: Literal["new-immutable-benchmark-release-required"]

    @model_validator(mode="after")
    def valid_specification(self) -> CalibrationPopulationSpecification:
        if not (
            self.minimum_case_count
            <= self.preferred_case_count_minimum
            <= self.preferred_case_count_maximum
        ):
            raise ValueError("population case-count range is inconsistent")
        group_ids = [item.group_id for item in self.semantic_groups]
        if len(group_ids) != len(set(group_ids)):
            raise ValueError("semantic group identities must be unique")
        requirement_ids = [item.requirement_id for item in self.domain_requirements]
        if len(requirement_ids) != len(set(requirement_ids)):
            raise ValueError("domain requirement identities must be unique")
        return self


class PipelineFailureRequirements(StrictModel):
    invalidate_observed_pre_threshold_failures: bool
    invalidating_failure_kinds: tuple[PipelineFailureKind, ...] = Field(min_length=1)

    @model_validator(mode="after")
    def unique_failure_kinds(self) -> PipelineFailureRequirements:
        if len(self.invalidating_failure_kinds) != len(
            set(self.invalidating_failure_kinds)
        ):
            raise ValueError("invalidating pipeline failure kinds must be unique")
        return self


class CalibrationCompatibilityRequirements(StrictModel):
    schema_version: Literal["v1"] = "v1"
    compatibility_policy_id: Identifier
    compatibility_policy_version: str
    threshold_policy_id: Identifier
    threshold_policy_sha256: Sha256 = Field(pattern=r"^[a-f0-9]{64}$")
    population_spec_id: Identifier
    population_spec_version: str
    population_spec_sha256: Sha256 = Field(pattern=r"^[a-f0-9]{64}$")
    pipeline_failures: PipelineFailureRequirements


class PopulationCase(StrictModel):
    case_id: Identifier
    cluster_id: Identifier
    domain: Identifier
    document_family_ids: tuple[Identifier, ...] = Field(min_length=1)
    evaluation_facets: tuple[Identifier, ...] = ()
    variant_count: int = Field(ge=1)
    slices: tuple[Identifier, ...] = Field(min_length=1)
    expected_outcome: Identifier
    expected_evidence_unit_count: int = Field(ge=0)
    source_lineage_count: int = Field(ge=0)
    source_lineage_digest: Sha256 = Field(pattern=r"^[a-f0-9]{64}$")

    @model_validator(mode="after")
    def unique_slices(self) -> PopulationCase:
        if len(self.slices) != len(set(self.slices)):
            raise ValueError("population case slices must be unique")
        if len(self.document_family_ids) != len(set(self.document_family_ids)):
            raise ValueError("population document families must be unique")
        if len(self.evaluation_facets) != len(set(self.evaluation_facets)):
            raise ValueError("population evaluation facets must be unique")
        return self


class BenchmarkTaxonomyEvidence(StrictModel):
    intrinsic_slices_schema_version: str
    declared_intrinsic_slices: tuple[Identifier, ...] = Field(min_length=1)
    intrinsic_slices_digest: Sha256 = Field(pattern=r"^[a-f0-9]{64}$")
    evaluation_facets_schema_version: str
    declared_evaluation_facets: tuple[Identifier, ...] = Field(min_length=1)
    evaluation_facets_digest: Sha256 = Field(pattern=r"^[a-f0-9]{64}$")

    @model_validator(mode="after")
    def valid_digest(self) -> BenchmarkTaxonomyEvidence:
        if len(self.declared_intrinsic_slices) != len(
            set(self.declared_intrinsic_slices)
        ):
            raise ValueError("declared intrinsic slices must be unique")
        if self.intrinsic_slices_digest != content_digest(
            sorted(self.declared_intrinsic_slices)
        ):
            raise ValueError("intrinsic slices digest does not match declarations")
        if len(self.declared_evaluation_facets) != len(
            set(self.declared_evaluation_facets)
        ):
            raise ValueError("declared evaluation facets must be unique")
        if self.evaluation_facets_digest != content_digest(
            sorted(self.declared_evaluation_facets)
        ):
            raise ValueError("evaluation facets digest does not match declarations")
        return self


class PopulationIndependenceEvidence(StrictModel):
    calibration_case_ids_digest: Sha256 = Field(pattern=r"^[a-f0-9]{64}$")
    calibration_semantic_cluster_ids_digest: Sha256 = Field(pattern=r"^[a-f0-9]{64}$")
    engineering_case_ids_digest: Sha256 = Field(pattern=r"^[a-f0-9]{64}$")
    engineering_semantic_cluster_ids_digest: Sha256 = Field(pattern=r"^[a-f0-9]{64}$")
    held_out_case_ids_digest: Sha256 = Field(pattern=r"^[a-f0-9]{64}$")
    held_out_semantic_cluster_ids_digest: Sha256 = Field(pattern=r"^[a-f0-9]{64}$")
    comparison_method: Literal["canonical-identity-set-intersection"]
    comparison_method_version: str
    engineering_overlap_case_ids: tuple[Identifier, ...] = ()
    held_out_overlap_case_ids: tuple[Identifier, ...] = ()
    split_semantic_cluster_ids: tuple[Identifier, ...] = ()
    engineering_overlap_case_count: int = Field(ge=0)
    held_out_overlap_case_count: int = Field(ge=0)
    split_semantic_cluster_count: int = Field(ge=0)
    score_driven_selection: bool
    post_result_reassignment: bool
    population_frozen_before_provider_execution: bool

    @model_validator(mode="after")
    def valid_overlap_counts(self) -> PopulationIndependenceEvidence:
        expected = (
            (self.engineering_overlap_case_count, self.engineering_overlap_case_ids),
            (self.held_out_overlap_case_count, self.held_out_overlap_case_ids),
            (self.split_semantic_cluster_count, self.split_semantic_cluster_ids),
        )
        if any(count != len(identities) for count, identities in expected):
            raise ValueError("independence overlap counts do not match identities")
        return self


class SplitIdentityEvidence(StrictModel):
    case_ids: tuple[Identifier, ...]
    semantic_cluster_ids: tuple[Identifier, ...]

    @model_validator(mode="after")
    def unique_identities(self) -> SplitIdentityEvidence:
        if len(self.case_ids) != len(set(self.case_ids)):
            raise ValueError("split case identities must be unique")
        if len(self.semantic_cluster_ids) != len(set(self.semantic_cluster_ids)):
            raise ValueError("split semantic cluster identities must be unique")
        return self


class AuthoringReviewEvidence(StrictModel):
    review_artifact_id: Identifier
    review_artifact_version: str
    review_artifact_digest: Sha256 = Field(pattern=r"^[a-f0-9]{64}$")
    reviewed_case_ids_digest: Sha256 = Field(pattern=r"^[a-f0-9]{64}$")
    semantic_quality_reviewed: bool
    representative_coverage_reviewed: bool
    author_rationale_reviewed: bool
    governance_reviewed: bool


def build_independence_evidence(
    calibration: SplitIdentityEvidence,
    engineering: SplitIdentityEvidence,
    held_out: SplitIdentityEvidence,
    *,
    score_driven_selection: bool,
    post_result_reassignment: bool,
    population_frozen_before_provider_execution: bool,
) -> PopulationIndependenceEvidence:
    """Compare privacy-safe split identities mechanically."""

    calibration_cases = set(calibration.case_ids)
    calibration_clusters = set(calibration.semantic_cluster_ids)
    engineering_overlap = tuple(
        sorted(calibration_cases.intersection(engineering.case_ids))
    )
    held_out_overlap = tuple(sorted(calibration_cases.intersection(held_out.case_ids)))
    split_clusters = tuple(
        sorted(
            calibration_clusters.intersection(
                set(engineering.semantic_cluster_ids).union(
                    held_out.semantic_cluster_ids
                )
            )
        )
    )
    return PopulationIndependenceEvidence(
        calibration_case_ids_digest=content_digest(sorted(calibration_cases)),
        calibration_semantic_cluster_ids_digest=content_digest(
            sorted(calibration_clusters)
        ),
        engineering_case_ids_digest=content_digest(sorted(engineering.case_ids)),
        engineering_semantic_cluster_ids_digest=content_digest(
            sorted(engineering.semantic_cluster_ids)
        ),
        held_out_case_ids_digest=content_digest(sorted(held_out.case_ids)),
        held_out_semantic_cluster_ids_digest=content_digest(
            sorted(held_out.semantic_cluster_ids)
        ),
        comparison_method="canonical-identity-set-intersection",
        comparison_method_version="1",
        engineering_overlap_case_ids=engineering_overlap,
        held_out_overlap_case_ids=held_out_overlap,
        split_semantic_cluster_ids=split_clusters,
        engineering_overlap_case_count=len(engineering_overlap),
        held_out_overlap_case_count=len(held_out_overlap),
        split_semantic_cluster_count=len(split_clusters),
        score_driven_selection=score_driven_selection,
        post_result_reassignment=post_result_reassignment,
        population_frozen_before_provider_execution=(
            population_frozen_before_provider_execution
        ),
    )


class PipelineFailureCount(StrictModel):
    kind: PipelineFailureKind
    count: int = Field(ge=1)


class CalibrationPopulationManifest(StrictModel):
    schema_version: Literal["v1"] = "v1"
    population_id: Identifier
    population_digest: Sha256 = Field(pattern=r"^[a-f0-9]{64}$")
    benchmark_id: Identifier
    benchmark_version: str
    benchmark_digest: Sha256 = Field(pattern=r"^[a-f0-9]{64}$")
    split_name: Identifier
    split_version: str
    split_case_ids_digest: Sha256 = Field(pattern=r"^[a-f0-9]{64}$")
    case_count: int = Field(ge=1)
    variant_count: int = Field(ge=1)
    case_ids_digest: Sha256 = Field(pattern=r"^[a-f0-9]{64}$")
    slice_case_counts: dict[Identifier, int]
    domain_case_counts: dict[Identifier, int]
    expected_outcome_case_counts: dict[Identifier, int]
    benchmark_taxonomy: BenchmarkTaxonomyEvidence
    independence: PopulationIndependenceEvidence
    authoring_review: AuthoringReviewEvidence
    pipeline_failure_counts: tuple[PipelineFailureCount, ...] = ()
    cases: tuple[PopulationCase, ...] = Field(min_length=1)

    @model_validator(mode="after")
    def internally_consistent(self) -> CalibrationPopulationManifest:
        case_ids = [item.case_id for item in self.cases]
        if len(case_ids) != len(set(case_ids)):
            raise ValueError("population case identities must be unique")
        if self.case_count != len(self.cases):
            raise ValueError("population case count does not match cases")
        if self.variant_count != sum(item.variant_count for item in self.cases):
            raise ValueError("population variant count does not match cases")
        expected_case_digest = content_digest(sorted(case_ids))
        if self.case_ids_digest != expected_case_digest:
            raise ValueError("population case identity digest does not match cases")
        if self.split_case_ids_digest != expected_case_digest:
            raise ValueError("declared split digest does not match snapshot cases")
        expected_cluster_digest = content_digest(
            sorted({item.cluster_id for item in self.cases})
        )
        if self.independence.calibration_case_ids_digest != expected_case_digest:
            raise ValueError("independence calibration case digest does not match")
        if (
            self.independence.calibration_semantic_cluster_ids_digest
            != expected_cluster_digest
        ):
            raise ValueError("independence calibration cluster digest does not match")
        if self.authoring_review.reviewed_case_ids_digest != expected_case_digest:
            raise ValueError("authoring review case digest does not match")
        expected_slices = Counter(
            label for item in self.cases for label in set(item.slices)
        )
        if dict(sorted(expected_slices.items())) != self.slice_case_counts:
            raise ValueError("population slice counts do not match cases")
        expected_domains = Counter(item.domain for item in self.cases)
        if dict(sorted(expected_domains.items())) != self.domain_case_counts:
            raise ValueError("population domain counts do not match cases")
        expected_outcomes = Counter(item.expected_outcome for item in self.cases)
        if dict(sorted(expected_outcomes.items())) != self.expected_outcome_case_counts:
            raise ValueError("population outcome counts do not match cases")
        declared_facets = set(self.benchmark_taxonomy.declared_evaluation_facets)
        observed_facets = {
            facet for item in self.cases for facet in item.evaluation_facets
        }
        if not observed_facets.issubset(declared_facets):
            raise ValueError(
                "population contains undeclared benchmark evaluation facets"
            )
        declared_slices = set(self.benchmark_taxonomy.declared_intrinsic_slices)
        observed_slices = {label for item in self.cases for label in item.slices}
        if not observed_slices.issubset(declared_slices):
            raise ValueError("population contains undeclared intrinsic slices")
        failure_kinds = [item.kind for item in self.pipeline_failure_counts]
        if len(failure_kinds) != len(set(failure_kinds)):
            raise ValueError("pipeline failure kinds must be unique")
        identity = {
            "benchmark_id": self.benchmark_id,
            "benchmark_version": self.benchmark_version,
            "benchmark_digest": self.benchmark_digest,
            "split_name": self.split_name,
            "split_version": self.split_version,
            "split_case_ids_digest": self.split_case_ids_digest,
            "benchmark_taxonomy": self.benchmark_taxonomy.model_dump(mode="json"),
            "independence": self.independence.model_dump(mode="json"),
            "authoring_review": self.authoring_review.model_dump(mode="json"),
            "cases": [item.model_dump(mode="json") for item in self.cases],
        }
        if self.population_digest != content_digest(identity):
            raise ValueError("population digest does not match governed content")
        return self


class SliceCompatibilityResult(StrictModel):
    requirement_id: Identifier
    group_id: Identifier
    matching_labels: tuple[Identifier, ...]
    minimum_case_count: int = Field(ge=0)
    available_case_count: int = Field(ge=0)
    preferred_case_count: int = Field(ge=1)
    observed_domain_count: int = Field(ge=0)
    observed_document_family_count: int = Field(ge=0)
    required_benchmark_facets: tuple[Identifier, ...]
    observed_required_benchmark_facets: tuple[Identifier, ...]
    compatible: bool
    failure_reasons: tuple[str, ...] = ()


class ControlledRejectionCompatibilityResult(StrictModel):
    threshold_sensitive_outcomes: tuple[Identifier, ...]
    acceptance_only_outcomes: tuple[Identifier, ...]
    available_outcome_counts: dict[Identifier, int]
    minimum_threshold_sensitive_case_count: int = Field(ge=1)
    available_threshold_sensitive_case_count: int = Field(ge=0)
    primary_empty_compare_case_count: int = Field(ge=0)
    comparison_empty_compare_case_count: int = Field(ge=0)
    metric_available: bool
    compatible: bool
    failure_reasons: tuple[str, ...] = ()


class ThresholdEvaluationCaseEvidence(StrictModel):
    case_id: Identifier
    complete_pre_threshold_lineage: bool
    reranker_scores: tuple[float, ...] = ()


class ThresholdExecutionEvidence(StrictModel):
    cases: tuple[ThresholdEvaluationCaseEvidence, ...]

    @model_validator(mode="after")
    def unique_case_ids(self) -> ThresholdExecutionEvidence:
        case_ids = [item.case_id for item in self.cases]
        if len(case_ids) != len(set(case_ids)):
            raise ValueError("threshold execution case identities must be unique")
        return self


class ThresholdExecutionCompatibilityResult(StrictModel):
    evaluated: bool
    compatible: bool | None
    complete_lineage_case_count: int = Field(ge=0)
    reranked_candidate_count: int = Field(ge=0)
    distinct_reranker_score_count: int = Field(ge=0)
    group_threshold_evaluable_case_counts: dict[Identifier, int]
    failure_reasons: tuple[str, ...] = ()


class PipelineFailureCompatibilityResult(StrictModel):
    observed_counts: dict[PipelineFailureKind, int]
    invalidate_observed_pre_threshold_failures: bool
    compatible: bool
    failure_reasons: tuple[str, ...] = ()


class DomainCompatibilityResult(StrictModel):
    requirement_id: Identifier
    domains: tuple[Identifier, ...]
    available_case_count: int = Field(ge=0)
    minimum_total_case_count: int = Field(ge=1)
    per_domain_available_case_count: dict[Identifier, int]
    per_domain_minimum_case_count: dict[Identifier, int]
    compatible: bool
    failure_reasons: tuple[str, ...] = ()


class IndependenceCompatibilityResult(StrictModel):
    compatible: bool
    failure_reasons: tuple[str, ...] = ()


class CalibrationCompatibilityResult(StrictModel):
    schema_version: Literal["v1"] = "v1"
    compatibility_policy_id: Identifier
    compatibility_policy_version: str
    compatibility_policy_sha256: Sha256 = Field(pattern=r"^[a-f0-9]{64}$")
    threshold_policy_id: Identifier
    threshold_policy_sha256: Sha256 = Field(pattern=r"^[a-f0-9]{64}$")
    population_spec_id: Identifier
    population_spec_version: str
    population_spec_sha256: Sha256 = Field(pattern=r"^[a-f0-9]{64}$")
    population_id: Identifier
    population_digest: Sha256 = Field(pattern=r"^[a-f0-9]{64}$")
    benchmark_id: Identifier
    benchmark_version: str
    benchmark_digest: Sha256 = Field(pattern=r"^[a-f0-9]{64}$")
    split_name: Identifier
    split_version: str
    compatible: bool
    slice_results: tuple[SliceCompatibilityResult, ...]
    controlled_rejection: ControlledRejectionCompatibilityResult
    threshold_execution: ThresholdExecutionCompatibilityResult
    domain_results: tuple[DomainCompatibilityResult, ...]
    independence: IndependenceCompatibilityResult
    pipeline_failures: PipelineFailureCompatibilityResult
    failure_reasons: tuple[str, ...]
    result_digest: Sha256 = Field(pattern=r"^[a-f0-9]{64}$")


def build_population_manifest(
    snapshot: dict[str, Any],
    *,
    pipeline_failure_counts: dict[PipelineFailureKind, int] | None = None,
    independence: PopulationIndependenceEvidence,
    benchmark_taxonomy: BenchmarkTaxonomyEvidence,
    authoring_review: AuthoringReviewEvidence,
) -> CalibrationPopulationManifest:
    """Compile privacy-safe population metadata from one isolated snapshot."""

    benchmark = _object(snapshot.get("benchmark"), "benchmark")
    split = _object(snapshot.get("split"), "split")
    raw_cases = snapshot.get("cases")
    if not isinstance(raw_cases, list) or not raw_cases:
        raise ValueError("calibration snapshot cases are unavailable")
    compiled_cases: list[PopulationCase] = []
    for raw_case in sorted(
        (_object(item, "case") for item in raw_cases),
        key=lambda item: str(item["case_id"]),
    ):
        retrieval_expectation = raw_case.get("retrieval_expectation")
        evidence_units = (
            _list(
                _object(retrieval_expectation, "retrieval expectation").get(
                    "evidence_units"
                ),
                "evidence units",
            )
            if isinstance(retrieval_expectation, dict)
            else []
        )
        source_lineage = sorted(
            {
                str(_object(unit, "evidence unit")["source_path"])
                for unit in evidence_units
                if _object(unit, "evidence unit").get("source_path") is not None
            }
        )
        compiled_cases.append(
            PopulationCase(
                case_id=str(raw_case["case_id"]),
                cluster_id=str(raw_case["cluster_id"]),
                domain=str(raw_case["domain"]),
                document_family_ids=tuple(
                    sorted(
                        str(value)
                        for value in _list(
                            raw_case.get("document_family_ids"),
                            "document family identities",
                        )
                    )
                ),
                evaluation_facets=tuple(
                    sorted(
                        str(value)
                        for value in _list(
                            raw_case.get("evaluation_facets", []),
                            "case evaluation facets",
                        )
                    )
                ),
                variant_count=len(_list(raw_case.get("variants"), "case variants")),
                slices=tuple(
                    sorted(
                        str(value)
                        for value in _list(raw_case.get("slices"), "case slices")
                    )
                ),
                expected_outcome=str(
                    _object(raw_case.get("outcome_expectation"), "outcome expectation")[
                        "outcome"
                    ]
                ),
                expected_evidence_unit_count=len(evidence_units),
                source_lineage_count=len(source_lineage),
                source_lineage_digest=content_digest(source_lineage),
            )
        )
    cases = tuple(compiled_cases)
    case_ids = [item.case_id for item in cases]
    slice_counts = Counter(label for item in cases for label in set(item.slices))
    domain_counts = Counter(item.domain for item in cases)
    outcome_counts = Counter(item.expected_outcome for item in cases)
    failures = tuple(
        PipelineFailureCount(kind=kind, count=count)
        for kind, count in sorted((pipeline_failure_counts or {}).items())
        if count > 0
    )
    identity = {
        "benchmark_id": str(benchmark["id"]),
        "benchmark_version": str(benchmark["version"]),
        "benchmark_digest": str(benchmark["digest"]),
        "split_name": str(split["name"]),
        "split_version": str(split["version"]),
        "split_case_ids_digest": str(split["case_ids_digest"]),
        "benchmark_taxonomy": benchmark_taxonomy.model_dump(mode="json"),
        "independence": independence.model_dump(mode="json"),
        "authoring_review": authoring_review.model_dump(mode="json"),
        "cases": [item.model_dump(mode="json") for item in cases],
    }
    return CalibrationPopulationManifest(
        population_id=f"{benchmark['id']}-{split['name']}-{split['version']}",
        population_digest=content_digest(identity),
        benchmark_id=str(benchmark["id"]),
        benchmark_version=str(benchmark["version"]),
        benchmark_digest=str(benchmark["digest"]),
        split_name=str(split["name"]),
        split_version=str(split["version"]),
        split_case_ids_digest=str(split["case_ids_digest"]),
        case_count=len(cases),
        variant_count=sum(item.variant_count for item in cases),
        case_ids_digest=content_digest(sorted(case_ids)),
        slice_case_counts=dict(sorted(slice_counts.items())),
        domain_case_counts=dict(sorted(domain_counts.items())),
        expected_outcome_case_counts=dict(sorted(outcome_counts.items())),
        benchmark_taxonomy=benchmark_taxonomy,
        independence=independence,
        authoring_review=authoring_review,
        pipeline_failure_counts=failures,
        cases=cases,
    )


def evaluate_compatibility(
    requirements: CalibrationCompatibilityRequirements,
    specification: CalibrationPopulationSpecification,
    population: CalibrationPopulationManifest,
    *,
    threshold_policy_sha256: str,
    population_spec_sha256: str,
    compatibility_policy_sha256: str,
    expected_compatibility_policy_sha256: str,
    execution_evidence: ThresholdExecutionEvidence | None = None,
) -> CalibrationCompatibilityResult:
    """Evaluate a population without providers or retrieval execution."""

    top_level_failures: list[str] = []
    if compatibility_policy_sha256 != expected_compatibility_policy_sha256:
        top_level_failures.append("compatibility_policy_digest_mismatch")
    if threshold_policy_sha256 != requirements.threshold_policy_sha256:
        top_level_failures.append("threshold_policy_digest_mismatch")
    if population_spec_sha256 != requirements.population_spec_sha256:
        top_level_failures.append("population_spec_digest_mismatch")
    if specification.population_spec_id != requirements.population_spec_id:
        top_level_failures.append("population_spec_identity_mismatch")
    if specification.population_spec_version != requirements.population_spec_version:
        top_level_failures.append("population_spec_version_mismatch")
    if population.benchmark_id != specification.benchmark_family:
        top_level_failures.append("benchmark_family_mismatch")
    if population.benchmark_version == specification.taxonomy_baseline_version:
        top_level_failures.append("new_immutable_benchmark_release_required")
    if population.split_version == specification.split_taxonomy_baseline_version:
        top_level_failures.append("new_immutable_split_release_required")
    if population.case_count < specification.minimum_case_count:
        top_level_failures.append(
            "population_case_count:"
            f"required={specification.minimum_case_count}:"
            f"available={population.case_count}"
        )

    declared_labels = set(population.benchmark_taxonomy.declared_intrinsic_slices)
    required_labels = {
        label for group in specification.semantic_groups for label in group.labels
    }
    missing_required_labels = required_labels - declared_labels
    if missing_required_labels:
        top_level_failures.append(
            "benchmark_taxonomy_missing_required_labels:"
            + ",".join(sorted(missing_required_labels))
        )

    case_slices = {item.case_id: set(item.slices) for item in population.cases}
    slice_results: list[SliceCompatibilityResult] = []
    for group in specification.semantic_groups:
        labels = set(group.labels)
        matching_labels = tuple(
            sorted(
                label for label in labels if population.slice_case_counts.get(label, 0)
            )
        )
        available = sum(
            1 for values in case_slices.values() if values.intersection(labels)
        )
        matching_cases = [
            item for item in population.cases if set(item.slices).intersection(labels)
        ]
        observed_domains = {item.domain for item in matching_cases}
        observed_families = {
            family for item in matching_cases for family in item.document_family_ids
        }
        group_failures: list[str] = []
        if available < group.minimum_case_count:
            group_failures.append(
                f"slice_requirement:{group.group_id}:"
                f"required={group.minimum_case_count}:available={available}"
            )
        diversity_checks = (
            ("domains", group.diversity.minimum_domain_count, len(observed_domains)),
            (
                "document_families",
                group.diversity.minimum_document_family_count,
                len(observed_families),
            ),
        )
        for name, required, observed in diversity_checks:
            if observed < required:
                group_failures.append(
                    f"slice_diversity:{group.group_id}:{name}:"
                    f"required={required}:available={observed}"
                )
        missing_facets = set(group.diversity.required_benchmark_facets) - set(
            population.benchmark_taxonomy.declared_evaluation_facets
        )
        if missing_facets:
            group_failures.append(
                f"benchmark_taxonomy_drift:{group.group_id}:missing_facets="
                + ",".join(sorted(missing_facets))
            )
        observed_required_facets = tuple(
            sorted(
                set(group.diversity.required_benchmark_facets).intersection(
                    facet for item in matching_cases for facet in item.evaluation_facets
                )
            )
        )
        unused_facets = set(group.diversity.required_benchmark_facets) - set(
            observed_required_facets
        )
        if unused_facets:
            group_failures.append(
                f"benchmark_facet_coverage:{group.group_id}:missing_observed_facets="
                + ",".join(sorted(unused_facets))
            )
        top_level_failures.extend(group_failures)
        slice_results.append(
            SliceCompatibilityResult(
                requirement_id=group.group_id,
                group_id=group.group_id,
                matching_labels=matching_labels,
                minimum_case_count=group.minimum_case_count,
                preferred_case_count=group.preferred_case_count,
                available_case_count=available,
                observed_domain_count=len(observed_domains),
                observed_document_family_count=len(observed_families),
                required_benchmark_facets=group.diversity.required_benchmark_facets,
                observed_required_benchmark_facets=observed_required_facets,
                compatible=not group_failures,
                failure_reasons=tuple(group_failures),
            )
        )

    controlled = specification.controlled_rejection
    available_outcomes = {
        requirement.outcome: population.expected_outcome_case_counts.get(
            requirement.outcome, 0
        )
        for requirement in controlled.threshold_sensitive_outcomes
    }
    available_outcomes.update(
        {
            outcome: population.expected_outcome_case_counts.get(outcome, 0)
            for outcome in controlled.acceptance_only_outcomes
        }
    )
    threshold_sensitive_total = sum(
        available_outcomes[item.outcome]
        for item in controlled.threshold_sensitive_outcomes
    )
    controlled_failures: list[str] = []
    for outcome_requirement in controlled.threshold_sensitive_outcomes:
        available = available_outcomes[outcome_requirement.outcome]
        if available < outcome_requirement.minimum_case_count:
            controlled_failures.append(
                f"controlled_rejection_outcome:{outcome_requirement.outcome}:"
                f"required={outcome_requirement.minimum_case_count}:available={available}"
            )
    if threshold_sensitive_total < controlled.minimum_threshold_sensitive_case_count:
        controlled_failures.append(
            "controlled_rejection_threshold_sensitive_cases:"
            f"required={controlled.minimum_threshold_sensitive_case_count}:"
            f"available={threshold_sensitive_total}"
        )
    primary_empty_count = sum(
        item.expected_outcome == "COMPARISON_SCOPE_INCOMPLETE"
        and "compare-primary-empty" in item.evaluation_facets
        for item in population.cases
    )
    comparison_empty_count = sum(
        item.expected_outcome == "COMPARISON_SCOPE_INCOMPLETE"
        and "compare-comparison-empty" in item.evaluation_facets
        for item in population.cases
    )
    if controlled.require_primary_empty_compare_case and primary_empty_count == 0:
        controlled_failures.append("controlled_rejection_missing:compare-primary-empty")
    if controlled.require_comparison_empty_compare_case and comparison_empty_count == 0:
        controlled_failures.append(
            "controlled_rejection_missing:compare-comparison-empty"
        )
    top_level_failures.extend(controlled_failures)

    accepted_outcomes = {
        "EVIDENCE_FOUND",
        *(item.outcome for item in controlled.threshold_sensitive_outcomes),
        *controlled.acceptance_only_outcomes,
    }
    unknown_outcomes = {
        item.expected_outcome
        for item in population.cases
        if item.expected_outcome not in accepted_outcomes
    }
    if unknown_outcomes:
        top_level_failures.append(
            "authoring:unknown_expected_outcomes:" + ",".join(sorted(unknown_outcomes))
        )
    missing_evidence = [
        item.case_id
        for item in population.cases
        if item.expected_outcome in {"EVIDENCE_FOUND", "COMPARISON_SCOPE_INCOMPLETE"}
        and item.expected_evidence_unit_count == 0
    ]
    if missing_evidence:
        top_level_failures.append(
            f"authoring:missing_expected_evidence_units:count={len(missing_evidence)}"
        )
    missing_source_lineage = [
        item.case_id
        for item in population.cases
        if item.expected_evidence_unit_count > 0 and item.source_lineage_count == 0
    ]
    if missing_source_lineage:
        top_level_failures.append(
            f"authoring:missing_source_lineage:count={len(missing_source_lineage)}"
        )
    review = population.authoring_review
    if not all(
        (
            review.semantic_quality_reviewed,
            review.representative_coverage_reviewed,
            review.author_rationale_reviewed,
            review.governance_reviewed,
        )
    ):
        top_level_failures.append("authoring:human_review_incomplete")
    controlled_result = ControlledRejectionCompatibilityResult(
        threshold_sensitive_outcomes=tuple(
            item.outcome for item in controlled.threshold_sensitive_outcomes
        ),
        acceptance_only_outcomes=controlled.acceptance_only_outcomes,
        available_outcome_counts=available_outcomes,
        minimum_threshold_sensitive_case_count=(
            controlled.minimum_threshold_sensitive_case_count
        ),
        available_threshold_sensitive_case_count=threshold_sensitive_total,
        primary_empty_compare_case_count=primary_empty_count,
        comparison_empty_compare_case_count=comparison_empty_count,
        metric_available=threshold_sensitive_total > 0,
        compatible=not controlled_failures,
        failure_reasons=tuple(controlled_failures),
    )

    threshold_execution_result = _evaluate_threshold_execution(
        specification, population, execution_evidence
    )
    if threshold_execution_result.evaluated:
        top_level_failures.extend(threshold_execution_result.failure_reasons)

    domain_results: list[DomainCompatibilityResult] = []
    for domain_requirement in specification.domain_requirements:
        available_by_domain = {
            domain: population.domain_case_counts.get(domain, 0)
            for domain in domain_requirement.domains
        }
        available_total = sum(available_by_domain.values())
        domain_failures: list[str] = []
        if available_total < domain_requirement.minimum_total_case_count:
            domain_failures.append(
                f"domain_requirement:{domain_requirement.requirement_id}:"
                f"required={domain_requirement.minimum_total_case_count}:"
                f"available={available_total}"
            )
        for domain, minimum in domain_requirement.per_domain_minimum_case_count.items():
            available = available_by_domain[domain]
            if available < minimum:
                domain_failures.append(
                    f"domain_requirement:{domain_requirement.requirement_id}:{domain}:"
                    f"required={minimum}:available={available}"
                )
        top_level_failures.extend(domain_failures)
        domain_results.append(
            DomainCompatibilityResult(
                requirement_id=domain_requirement.requirement_id,
                domains=domain_requirement.domains,
                available_case_count=available_total,
                minimum_total_case_count=domain_requirement.minimum_total_case_count,
                per_domain_available_case_count=available_by_domain,
                per_domain_minimum_case_count=(
                    domain_requirement.per_domain_minimum_case_count
                ),
                compatible=not domain_failures,
                failure_reasons=tuple(domain_failures),
            )
        )
    if len(population.domain_case_counts) < specification.minimum_domain_count:
        top_level_failures.append(
            f"domain_count:required={specification.minimum_domain_count}:"
            f"available={len(population.domain_case_counts)}"
        )
    largest_domain_count = max(population.domain_case_counts.values())
    largest_domain_share = largest_domain_count / population.case_count
    if largest_domain_share > specification.maximum_single_domain_share:
        top_level_failures.append(
            "domain_imbalance:"
            f"maximum={specification.maximum_single_domain_share}:"
            f"observed={largest_domain_share:.6f}"
        )

    independence_failures: list[str] = []
    evidence = population.independence
    rules = specification.independence
    if rules.forbid_engineering_overlap and evidence.engineering_overlap_case_ids:
        independence_failures.append("independence:engineering_overlap")
    if rules.forbid_held_out_overlap and evidence.held_out_overlap_case_ids:
        independence_failures.append("independence:held_out_overlap")
    if rules.require_semantic_clusters_together and evidence.split_semantic_cluster_ids:
        independence_failures.append("independence:semantic_cluster_split")
    if rules.forbid_score_driven_selection and evidence.score_driven_selection:
        independence_failures.append("independence:score_driven_selection")
    if rules.forbid_post_result_reassignment and evidence.post_result_reassignment:
        independence_failures.append("independence:post_result_reassignment")
    if (
        rules.require_population_frozen_before_provider_execution
        and not evidence.population_frozen_before_provider_execution
    ):
        independence_failures.append("independence:population_not_frozen")
    top_level_failures.extend(independence_failures)
    independence_result = IndependenceCompatibilityResult(
        compatible=not independence_failures,
        failure_reasons=tuple(independence_failures),
    )

    observed_failures = {
        item.kind: item.count for item in population.pipeline_failure_counts
    }
    pipeline_failures: list[str] = []
    if requirements.pipeline_failures.invalidate_observed_pre_threshold_failures:
        for kind in requirements.pipeline_failures.invalidating_failure_kinds:
            count = observed_failures.get(kind, 0)
            if count:
                pipeline_failures.append(f"pipeline_failure:{kind}:count={count}")
    top_level_failures.extend(pipeline_failures)
    pipeline_result = PipelineFailureCompatibilityResult(
        observed_counts=observed_failures,
        invalidate_observed_pre_threshold_failures=(
            requirements.pipeline_failures.invalidate_observed_pre_threshold_failures
        ),
        compatible=not pipeline_failures,
        failure_reasons=tuple(pipeline_failures),
    )

    base = {
        "schema_version": "v1",
        "compatibility_policy_id": requirements.compatibility_policy_id,
        "compatibility_policy_version": requirements.compatibility_policy_version,
        "compatibility_policy_sha256": compatibility_policy_sha256,
        "threshold_policy_id": requirements.threshold_policy_id,
        "threshold_policy_sha256": requirements.threshold_policy_sha256,
        "population_spec_id": specification.population_spec_id,
        "population_spec_version": specification.population_spec_version,
        "population_spec_sha256": requirements.population_spec_sha256,
        "population_id": population.population_id,
        "population_digest": population.population_digest,
        "benchmark_id": population.benchmark_id,
        "benchmark_version": population.benchmark_version,
        "benchmark_digest": population.benchmark_digest,
        "split_name": population.split_name,
        "split_version": population.split_version,
        "compatible": not top_level_failures,
        "slice_results": [item.model_dump(mode="json") for item in slice_results],
        "controlled_rejection": controlled_result.model_dump(mode="json"),
        "threshold_execution": threshold_execution_result.model_dump(mode="json"),
        "domain_results": [item.model_dump(mode="json") for item in domain_results],
        "independence": independence_result.model_dump(mode="json"),
        "pipeline_failures": pipeline_result.model_dump(mode="json"),
        "failure_reasons": top_level_failures,
    }
    return CalibrationCompatibilityResult(
        compatibility_policy_id=requirements.compatibility_policy_id,
        compatibility_policy_version=requirements.compatibility_policy_version,
        compatibility_policy_sha256=compatibility_policy_sha256,
        threshold_policy_id=requirements.threshold_policy_id,
        threshold_policy_sha256=requirements.threshold_policy_sha256,
        population_spec_id=specification.population_spec_id,
        population_spec_version=specification.population_spec_version,
        population_spec_sha256=requirements.population_spec_sha256,
        population_id=population.population_id,
        population_digest=population.population_digest,
        benchmark_id=population.benchmark_id,
        benchmark_version=population.benchmark_version,
        benchmark_digest=population.benchmark_digest,
        split_name=population.split_name,
        split_version=population.split_version,
        compatible=not top_level_failures,
        slice_results=tuple(slice_results),
        controlled_rejection=controlled_result,
        threshold_execution=threshold_execution_result,
        domain_results=tuple(domain_results),
        independence=independence_result,
        pipeline_failures=pipeline_result,
        failure_reasons=tuple(top_level_failures),
        result_digest=content_digest(base),
    )


def classify_pre_threshold_failure(
    observation: dict[str, Any],
) -> PipelineFailureKind | None:
    """Classify a durable observation without exposing payload or question content."""

    planning = observation.get("planning")
    if isinstance(planning, dict) and planning.get("status") == "failed":
        category = str(planning.get("failure_category", ""))
        if category in PROVIDER_FAILURE_CATEGORIES:
            return "provider_failure_before_threshold"
        return "planner_failure_before_threshold"
    if observation.get("retrieval_executed") is True and not isinstance(
        observation.get("hybrid"), dict
    ):
        return "retrieval_failure_before_threshold"
    if isinstance(observation.get("hybrid"), dict):
        return None
    return "incomplete_pre_threshold_lineage"


def _evaluate_threshold_execution(
    specification: CalibrationPopulationSpecification,
    population: CalibrationPopulationManifest,
    evidence: ThresholdExecutionEvidence | None,
) -> ThresholdExecutionCompatibilityResult:
    if evidence is None:
        return ThresholdExecutionCompatibilityResult(
            evaluated=False,
            compatible=None,
            complete_lineage_case_count=0,
            reranked_candidate_count=0,
            distinct_reranker_score_count=0,
            group_threshold_evaluable_case_counts={},
        )

    population_cases = {item.case_id: item for item in population.cases}
    observed_case_ids = {item.case_id for item in evidence.cases}
    unexpected = observed_case_ids - set(population_cases)
    failures: list[str] = []
    if unexpected:
        failures.append("threshold_execution:unexpected_case_identities")
    acceptance_only_outcomes = set(
        specification.controlled_rejection.acceptance_only_outcomes
    )
    threshold_evaluable_population_case_ids = {
        case_id
        for case_id, item in population_cases.items()
        if item.expected_outcome not in acceptance_only_outcomes
    }
    complete = {
        item.case_id: item
        for item in evidence.cases
        if item.case_id in threshold_evaluable_population_case_ids
        and item.complete_pre_threshold_lineage
        and item.reranker_scores
    }
    if specification.execution_requirements.require_complete_pre_threshold_lineage:
        incomplete = threshold_evaluable_population_case_ids - {
            item.case_id
            for item in evidence.cases
            if item.complete_pre_threshold_lineage
        }
        if incomplete:
            failures.append(
                f"threshold_execution:incomplete_pre_threshold_lineage:count={len(incomplete)}"
            )
    all_scores = tuple(
        score for item in complete.values() for score in item.reranker_scores
    )
    distinct_scores = len(set(all_scores))
    if (
        len(all_scores)
        < specification.execution_requirements.minimum_total_reranked_candidates
    ):
        failures.append(
            "threshold_execution:reranked_candidates:"
            f"required={specification.execution_requirements.minimum_total_reranked_candidates}:"
            f"available={len(all_scores)}"
        )
    if (
        distinct_scores
        < specification.execution_requirements.minimum_distinct_reranker_scores
    ):
        failures.append(
            "threshold_execution:distinct_reranker_scores:"
            f"required={specification.execution_requirements.minimum_distinct_reranker_scores}:"
            f"available={distinct_scores}"
        )
    group_counts: dict[Identifier, int] = {}
    for group in specification.semantic_groups:
        labels = set(group.labels)
        count = sum(
            bool(set(population_cases[case_id].slices).intersection(labels))
            for case_id in complete
        )
        group_counts[group.group_id] = count
        if count < group.minimum_threshold_evaluable_case_count:
            failures.append(
                f"threshold_execution:group:{group.group_id}:"
                f"required={group.minimum_threshold_evaluable_case_count}:available={count}"
            )
    return ThresholdExecutionCompatibilityResult(
        evaluated=True,
        compatible=not failures,
        complete_lineage_case_count=len(complete),
        reranked_candidate_count=len(all_scores),
        distinct_reranker_score_count=distinct_scores,
        group_threshold_evaluable_case_counts=group_counts,
        failure_reasons=tuple(failures),
    )


def _object(value: object, name: str) -> dict[str, Any]:
    if not isinstance(value, dict):
        raise TypeError(f"{name} must be an object")
    return value


def _list(value: object, name: str) -> list[Any]:
    if not isinstance(value, list):
        raise TypeError(f"{name} must be an array")
    return value
