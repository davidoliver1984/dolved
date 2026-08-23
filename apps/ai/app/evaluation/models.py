"""Application-owned evaluation contracts from ADR-0019 and ADR-0020."""

from __future__ import annotations

from datetime import datetime
from enum import StrEnum
from typing import Annotated, Any, Literal
from uuid import UUID

from pydantic import BaseModel, ConfigDict, Field, model_validator

Identifier = Annotated[
    str, Field(min_length=1, max_length=160, pattern=r"^[a-zA-Z0-9._:-]+$")
]
ProviderModelIdentity = Annotated[
    str, Field(min_length=1, max_length=240, pattern=r"^[a-zA-Z0-9._:/-]+$")
]


class StrictModel(BaseModel):
    model_config = ConfigDict(extra="forbid", frozen=True)


class EvidenceUnit(StrictModel):
    evidence_id: Identifier
    document_family_id: Identifier
    document_version_id: Identifier
    side: Literal["PRIMARY", "COMPARISON"] = "PRIMARY"
    source_path: Annotated[str, Field(min_length=1)]
    canonical_excerpts: tuple[Annotated[str, Field(min_length=1)], ...] = Field(
        min_length=1
    )
    relevance_grade: Annotated[int, Field(ge=1, le=2)] = 2
    minimum_token_coverage: Annotated[float, Field(gt=0, le=1)] = 1.0
    notes: str | None = None


class QuestionVariant(StrictModel):
    variant_id: Identifier
    question: Annotated[str, Field(min_length=1, max_length=4000)]


class EvaluationCase(StrictModel):
    case_id: Identifier
    variants: tuple[QuestionVariant, ...] = Field(min_length=1)
    slices: tuple[Identifier, ...] = Field(min_length=1)
    evidence_units: tuple[EvidenceUnit, ...] = ()
    expected_temporal_mode: str | None = None
    expected_outcome: str | None = None

    @model_validator(mode="after")
    def unique_identifiers(self) -> EvaluationCase:
        variant_ids = [variant.variant_id for variant in self.variants]
        evidence_ids = [unit.evidence_id for unit in self.evidence_units]
        if len(variant_ids) != len(set(variant_ids)):
            raise ValueError("variant_id values must be unique within a case")
        if len(evidence_ids) != len(set(evidence_ids)):
            raise ValueError("evidence_id values must be unique within a case")
        return self


class EvaluationCorpus(StrictModel):
    schema_version: Annotated[str, Field(pattern=r"^v[1-9][0-9]*$")]
    corpus_version: Annotated[str, Field(pattern=r"^[1-9][0-9]*$")]
    title: Annotated[str, Field(min_length=1)]
    matching_algorithm: Identifier
    cases: tuple[EvaluationCase, ...] = Field(min_length=1)

    @model_validator(mode="after")
    def unique_cases(self) -> EvaluationCorpus:
        case_ids = [case.case_id for case in self.cases]
        if len(case_ids) != len(set(case_ids)):
            raise ValueError("case_id values must be unique")
        return self


class RetrievedCandidate(StrictModel):
    candidate_id: Identifier
    document_family_id: Identifier
    document_version_id: Identifier
    rank: Annotated[int, Field(ge=1)]
    text: str
    side: Literal["PRIMARY", "COMPARISON"] = "PRIMARY"


class CostBasis(StrEnum):
    PROVIDER_REPORTED = "PROVIDER_REPORTED"
    ESTIMATED = "ESTIMATED"
    UNAVAILABLE = "UNAVAILABLE"
    ZERO_COST_LOCAL = "ZERO_COST_LOCAL"


class ProviderRetryDelayObservation(StrictModel):
    delay_seconds: Annotated[float, Field(ge=0)]
    source: Literal[
        "retry_after_numeric",
        "retry_after_http_date",
        "ratelimit_reset",
        "x_ratelimit_reset_requests",
        "x_ratelimit_reset_tokens",
        "configured_fallback",
        "shared_cooldown",
    ]


class StageUsageObservation(StrictModel):
    stage: Identifier
    provider: ProviderModelIdentity | None = None
    model: ProviderModelIdentity | None = None
    execution: Literal["PROVIDER_API", "LOCAL", "INFRASTRUCTURE", "NOT_EXECUTED"]
    request_count: Annotated[int, Field(ge=0)] = 0
    retry_count: Annotated[int, Field(ge=0)] | None = None
    provider_attempt_count: Annotated[int, Field(ge=1)] | None = None
    provider_retry_count: Annotated[int, Field(ge=0)] | None = None
    outer_attempt_count: Annotated[int, Field(ge=1)] | None = None
    outer_retry_count: Annotated[int, Field(ge=0)] | None = None
    rate_limit_event_count: Annotated[int, Field(ge=0)] | None = None
    retry_delays: tuple[ProviderRetryDelayObservation, ...] = ()
    first_provider_attempt_at: datetime | None = None
    final_provider_success_at: datetime | None = None
    provider_retry_elapsed_ms: Annotated[float, Field(ge=0)] | None = None
    input_tokens: Annotated[int, Field(ge=0)] | None = None
    cached_input_tokens: Annotated[int, Field(ge=0)] | None = None
    output_tokens: Annotated[int, Field(ge=0)] | None = None
    latency_ms: Annotated[float, Field(ge=0)] | None = None
    cost_basis: CostBasis
    cost_usd: Annotated[float, Field(ge=0)] | None = None
    pricing_snapshot: Identifier | None = None

    @model_validator(mode="after")
    def validate_cost_semantics(self) -> StageUsageObservation:
        if self.cost_basis is CostBasis.UNAVAILABLE and self.cost_usd is not None:
            raise ValueError("unavailable cost cannot carry a numeric value")
        if self.cost_basis is CostBasis.ZERO_COST_LOCAL and (
            self.execution not in {"LOCAL", "INFRASTRUCTURE"} or self.cost_usd != 0
        ):
            raise ValueError("zero-cost observations must be local and explicitly zero")
        if self.cost_basis is CostBasis.ESTIMATED and (
            self.cost_usd is None or self.pricing_snapshot is None
        ):
            raise ValueError("estimated cost requires a value and pricing snapshot")
        if self.cost_basis is CostBasis.PROVIDER_REPORTED and self.cost_usd is None:
            raise ValueError("provider-reported cost requires a value")
        if self.execution == "NOT_EXECUTED" and self.request_count != 0:
            raise ValueError("a non-executed stage cannot report requests")
        return self


class OperationalObservation(StrictModel):
    latency_ms: Annotated[float, Field(ge=0)] = 0
    token_usage: Annotated[int, Field(ge=0)] = 0
    provider_cost: Annotated[float, Field(ge=0)] | None = None
    request_count: Annotated[int, Field(ge=0)] = 0
    stage_usage: tuple[StageUsageObservation, ...] = ()


class EvaluationTextCaptureMode(StrEnum):
    DISABLED = "DISABLED"
    REDACTED = "REDACTED"
    BENCHMARK_TEXT = "BENCHMARK_TEXT"


class PlanningStatus(StrEnum):
    SUCCEEDED = "SUCCEEDED"
    FAILED = "FAILED"


class PlannerFailureObservation(StrictModel):
    provider: ProviderModelIdentity
    model: ProviderModelIdentity
    category: Identifier
    provider_status: Annotated[int, Field(ge=100, le=599)] | None = None
    attempt_count: Annotated[int, Field(ge=1)]
    occurred_at: datetime


class RetrievalFailureObservation(StrictModel):
    stage: Literal[
        "dense_embedding",
        "qdrant_dense_search",
        "sparse_encoding",
        "qdrant_sparse_search",
        "fusion",
        "reranker",
        "threshold",
        "final_eligibility",
        "transport_orchestration",
    ]
    execution: Literal["provider_api", "local", "infrastructure", "orchestration"]
    provider: ProviderModelIdentity | None = None
    model: ProviderModelIdentity | None = None
    category: Literal[
        "timeout",
        "rate_limited",
        "provider_http_error",
        "connection_error",
        "invalid_provider_response",
        "contract_validation_error",
        "local_execution_error",
        "infrastructure_error",
        "unknown",
    ]
    http_status: Annotated[int, Field(ge=100, le=599)] | None = None
    retry_count: Annotated[int, Field(ge=0)] | None = None
    provider_retry_count: Annotated[int, Field(ge=0)] | None = None
    outer_retry_count: Annotated[int, Field(ge=0)] | None = None
    rate_limit_event_count: Annotated[int, Field(ge=0)] | None = None
    retry_delays: tuple[ProviderRetryDelayObservation, ...] = ()
    request_count: Annotated[int, Field(ge=0)] | None = None
    first_failure_at: datetime | None = None
    final_failure_at: datetime | None = None
    retry_delay_ms: Annotated[float, Field(ge=0)] | None = None
    provider_retry_after_seconds: Annotated[float, Field(ge=0)] | None = None
    provider_timing_source: Identifier | None = None
    latency_ms: Annotated[float, Field(ge=0)]
    usage: tuple[StageUsageObservation, ...] = ()
    downstream_request_attempted: bool
    candidate_lineage_produced: bool


class PlannerFieldDifference(StrictModel):
    field: Identifier
    expected: Any
    actual: Any
    classification: Literal[
        "SEMANTIC_AFTER_NORMALISATION",
        "POTENTIAL_ALIAS_OR_REPRESENTATION_MISMATCH",
    ]


class PlannerEvaluationObservation(StrictModel):
    expected_contract: dict[str, Any]
    actual_plan: dict[str, Any] | None
    differences: tuple[PlannerFieldDifference, ...] = ()
    correct: bool


class ExpectedEvidenceIdentity(StrictModel):
    evidence_unit_id: Identifier
    document_family_id: Identifier
    document_version_id: Identifier
    side: Literal["PRIMARY", "COMPARISON"] = "PRIMARY"
    source_path: str | None = None


class CandidateStageLineage(StrictModel):
    candidate_id: Identifier
    chunk_id: UUID
    document_family_id: Identifier
    document_version_id: Identifier
    side: Literal["PRIMARY", "COMPARISON"] = "PRIMARY"
    dense_rank: Annotated[int, Field(ge=1)] | None = None
    dense_score: float | None = None
    sparse_rank: Annotated[int, Field(ge=1)] | None = None
    sparse_score: float | None = None
    fused_rank: Annotated[int, Field(ge=1)] | None = None
    fused_score: float | None = None
    reranker_rank: Annotated[int, Field(ge=1)] | None = None
    reranker_score: float | None = None
    passed_evidence_threshold: bool | None = None
    included_in_final_evidence: bool = False
    covered_evidence_unit_ids: tuple[Identifier, ...] = ()


class CandidateFunnel(StrictModel):
    side: Literal["PRIMARY", "COMPARISON"] = "PRIMARY"
    dense_candidate_count: Annotated[int, Field(ge=0)] | None = None
    sparse_candidate_count: Annotated[int, Field(ge=0)] | None = None
    unique_post_fusion_count: Annotated[int, Field(ge=0)] | None = None
    candidates_sent_to_reranker: Annotated[int, Field(ge=0)] | None = None
    candidates_surviving_threshold: Annotated[int, Field(ge=0)] | None = None
    final_evidence_count: Annotated[int, Field(ge=0)] | None = None


class VariantObservation(StrictModel):
    case_id: Identifier
    variant_id: Identifier
    candidates: tuple[RetrievedCandidate, ...] = ()
    planner_correct: bool
    eligibility_correct: bool | None
    outcome_correct: bool | None
    hard_failures: tuple[Identifier, ...] = ()
    operational: OperationalObservation = OperationalObservation()
    text_capture_mode: EvaluationTextCaptureMode = EvaluationTextCaptureMode.DISABLED
    question: Annotated[str, Field(min_length=1, max_length=4000)] | None = None
    expected_evidence: tuple[ExpectedEvidenceIdentity, ...] = ()
    expected_outcome: str | None = None
    candidate_lineage: tuple[CandidateStageLineage, ...] = ()
    candidate_funnel: tuple[CandidateFunnel, ...] = ()
    planning_status: PlanningStatus = PlanningStatus.SUCCEEDED
    retrieval_executed: bool = True
    contributes_retrieval_metrics: bool = True
    planner_failure: PlannerFailureObservation | None = None
    planner_evaluation: PlannerEvaluationObservation | None = None
    retrieval_failure: RetrievalFailureObservation | None = None

    @model_validator(mode="after")
    def protect_question_text(self) -> VariantObservation:
        if self.text_capture_mode is EvaluationTextCaptureMode.DISABLED:
            if self.question is not None:
                raise ValueError(
                    "question must be omitted when text capture is disabled"
                )
        elif self.text_capture_mode is EvaluationTextCaptureMode.REDACTED:
            if self.question not in (None, "[REDACTED]"):
                raise ValueError("redacted text capture cannot retain the raw question")
        elif self.question is None:
            raise ValueError("benchmark text capture requires the question")
        if (
            self.text_capture_mode is not EvaluationTextCaptureMode.BENCHMARK_TEXT
            and any(item.source_path is not None for item in self.expected_evidence)
        ):
            raise ValueError(
                "expected source paths require explicit benchmark text capture"
            )
        if (
            self.text_capture_mode is not EvaluationTextCaptureMode.BENCHMARK_TEXT
            and self.planner_evaluation is not None
        ):
            raise ValueError(
                "planner evaluation detail requires explicit benchmark text capture"
            )
        if (
            self.planner_evaluation is not None
            and self.planner_evaluation.correct is not self.planner_correct
        ):
            raise ValueError("planner evaluation detail must match planner correctness")
        if self.planning_status is PlanningStatus.FAILED:
            if (
                self.planner_failure is None
                or self.planner_correct
                or self.eligibility_correct is not None
                or self.outcome_correct is not None
                or self.retrieval_executed
                or self.contributes_retrieval_metrics
                or self.candidates
                or self.candidate_lineage
                or self.candidate_funnel
            ):
                raise ValueError(
                    "failed planning observations cannot fabricate retrieval evidence"
                )
        elif self.planner_failure is not None or not self.retrieval_executed:
            raise ValueError(
                "successful planning observations require executed retrieval"
            )
        if self.retrieval_failure is not None and self.contributes_retrieval_metrics:
            raise ValueError(
                "failed retrieval cannot contribute fabricated retrieval metrics"
            )
        return self


class MetricValues(StrictModel):
    recall_at_k: Annotated[float, Field(ge=0, le=1)]
    precision_at_k: Annotated[float, Field(ge=0, le=1)]
    mrr: Annotated[float, Field(ge=0, le=1)]
    ndcg_at_k: Annotated[float, Field(ge=0, le=1)]


class VariantResult(StrictModel):
    case_id: Identifier
    variant_id: Identifier
    metrics: MetricValues | None
    side_metrics: dict[str, MetricValues]
    covered_evidence_ids: tuple[Identifier, ...]
    planner_correct: bool
    eligibility_correct: bool | None
    outcome_correct: bool | None
    hard_failures: tuple[Identifier, ...]
    operational: OperationalObservation
    text_capture_mode: EvaluationTextCaptureMode = EvaluationTextCaptureMode.DISABLED
    question: Annotated[str, Field(min_length=1, max_length=4000)] | None = None
    expected_evidence: tuple[ExpectedEvidenceIdentity, ...] = ()
    expected_outcome: str | None = None
    candidate_lineage: tuple[CandidateStageLineage, ...] = ()
    candidate_funnel: tuple[CandidateFunnel, ...] = ()
    planning_status: PlanningStatus = PlanningStatus.SUCCEEDED
    retrieval_executed: bool = True
    contributes_retrieval_metrics: bool = True
    planner_failure: PlannerFailureObservation | None = None
    planner_evaluation: PlannerEvaluationObservation | None = None
    retrieval_failure: RetrievalFailureObservation | None = None

    @model_validator(mode="after")
    def protect_question_text(self) -> VariantResult:
        if self.text_capture_mode is EvaluationTextCaptureMode.DISABLED:
            if self.question is not None:
                raise ValueError(
                    "question must be omitted when text capture is disabled"
                )
        elif self.text_capture_mode is EvaluationTextCaptureMode.REDACTED:
            if self.question not in (None, "[REDACTED]"):
                raise ValueError("redacted text capture cannot retain the raw question")
        elif self.question is None:
            raise ValueError("benchmark text capture requires the question")
        if (
            self.text_capture_mode is not EvaluationTextCaptureMode.BENCHMARK_TEXT
            and any(item.source_path is not None for item in self.expected_evidence)
        ):
            raise ValueError(
                "expected source paths require explicit benchmark text capture"
            )
        if (
            self.text_capture_mode is not EvaluationTextCaptureMode.BENCHMARK_TEXT
            and self.planner_evaluation is not None
        ):
            raise ValueError(
                "planner evaluation detail requires explicit benchmark text capture"
            )
        if (
            self.planner_evaluation is not None
            and self.planner_evaluation.correct is not self.planner_correct
        ):
            raise ValueError("planner evaluation detail must match planner correctness")
        return self


class AggregateResult(StrictModel):
    metrics: MetricValues | None
    planner_accuracy: Annotated[float, Field(ge=0, le=1)]
    eligibility_accuracy: Annotated[float, Field(ge=0, le=1)] | None
    outcome_accuracy: Annotated[float, Field(ge=0, le=1)] | None
    case_count: Annotated[int, Field(ge=0)]
    variant_count: Annotated[int, Field(ge=0)] = 0
    retrieval_metric_variant_count: Annotated[int, Field(ge=0)] = 0
    planner_success_count: Annotated[int, Field(ge=0)] = 0
    planner_failure_count: Annotated[int, Field(ge=0)] = 0
    planner_reliability: Annotated[float, Field(ge=0, le=1)] = 0
    planner_failure_categories: dict[Identifier, Annotated[int, Field(ge=1)]] = Field(
        default_factory=dict
    )


class ExperimentLineage(StrictModel):
    repository_commit: str
    corpus_version: str
    corpus_digest: str
    policy_version: str
    policy_digest: str
    harness_version: str
    matching_algorithm: str
    planner: dict[str, Any]
    embedding_profile_fingerprint: str
    sparse_profile_fingerprint: str | None = None
    reranker_profile_fingerprint: str | None = None
    plan_catalogue_checksum: str | None = None
    eligibility_artifact_contract: str | None = None
    eligibility_artifact_digest: str | None = None
    eligibility_comparability_digest: str | None = None
    eligibility_catalogue_version: str | None = None
    eligibility_catalogue_digest: str | None = None
    eligibility_resolver_source_digest: str | None = None
    eligibility_configuration_digest: str | None = None
    eligibility_evaluated_at: str | None = None
    eligibility_document_mapping_digest: str | None = None
    deterministic_profile_digest: str | None = None
    chunking_configuration: dict[str, Any]
    retrieval_configuration: dict[str, Any]
    evaluator: dict[str, Any] | None = None
    trial_count: Annotated[int, Field(ge=1)] = 1


class ModelAssistedStatus(StrEnum):
    COMPLETED = "COMPLETED"
    FAILED = "FAILED"


class ModelAssistedMetric(StrEnum):
    CONTEXT_RELEVANCE = "CONTEXT_RELEVANCE"
    ANSWER_PART_GROUNDEDNESS = "ANSWER_PART_GROUNDEDNESS"
    ANSWER_FACTUAL_PRECISION = "ANSWER_FACTUAL_PRECISION"
    ANSWER_COMPLETENESS = "ANSWER_COMPLETENESS"
    QUALIFIED_USEFULNESS = "QUALIFIED_USEFULNESS"
    INSUFFICIENCY_CORRECTNESS = "INSUFFICIENCY_CORRECTNESS"


class ModelAssistedAnswerPart(StrictModel):
    part_index: Annotated[int, Field(ge=1)]
    text: Annotated[str, Field(min_length=1)]
    evidence_ids: tuple[Identifier, ...] = Field(min_length=1)


class ModelAssistedGenerationResult(StrictModel):
    outcome: Literal["answered", "qualified", "insufficient_evidence"]
    answer_parts: tuple[ModelAssistedAnswerPart, ...] = ()
    unsupported_aspects: tuple[Annotated[str, Field(min_length=1)], ...] = ()
    insufficiency_reason: str | None = None

    @model_validator(mode="after")
    def validate_outcome_shape(self) -> ModelAssistedGenerationResult:
        if self.outcome == "answered" and (
            not self.answer_parts
            or self.unsupported_aspects
            or self.insufficiency_reason is not None
        ):
            raise ValueError("answered evaluator input has an invalid result shape")
        if self.outcome == "qualified" and (
            not self.answer_parts
            or not self.unsupported_aspects
            or self.insufficiency_reason is not None
        ):
            raise ValueError("qualified evaluator input has an invalid result shape")
        if self.outcome == "insufficient_evidence" and (
            self.answer_parts
            or not self.unsupported_aspects
            or not self.insufficiency_reason
        ):
            raise ValueError("insufficient evaluator input has an invalid result shape")
        return self


class ModelAssistedMetricObservation(StrictModel):
    metric: ModelAssistedMetric
    status: ModelAssistedStatus
    answer_part_indices: tuple[Annotated[int, Field(ge=1)], ...] = ()
    latency_ms: Annotated[float, Field(ge=0)] | None = None
    failure_code: Identifier | None = None
    retry_count: Annotated[int, Field(ge=0)] | None = None
    input_tokens: Annotated[int, Field(ge=0)] | None = None
    output_tokens: Annotated[int, Field(ge=0)] | None = None
    cost_usd: Annotated[float, Field(ge=0)] | None = None
    provider_status: Annotated[int, Field(ge=100, le=599)] | None = None


class ModelAssistedEvaluationRequest(StrictModel):
    case_id: Identifier
    variant_id: Identifier
    question: str
    retrieved_evidence: tuple[RetrievedCandidate, ...]
    metrics: tuple[ModelAssistedMetric, ...] = (ModelAssistedMetric.CONTEXT_RELEVANCE,)
    generated_answer: str | None = None
    reference_answer: str | None = None
    generated_result: ModelAssistedGenerationResult | None = None
    reference_unsupported_aspects: tuple[Annotated[str, Field(min_length=1)], ...] = ()

    @model_validator(mode="after")
    def validate_answer_metric_inputs(self) -> ModelAssistedEvaluationRequest:
        answer_metrics = set(self.metrics) - {ModelAssistedMetric.CONTEXT_RELEVANCE}
        if answer_metrics and not (self.generated_answer or self.generated_result):
            raise ValueError(
                "answer-dependent metrics require generated_answer or generated_result"
            )
        reference_metrics = answer_metrics.intersection(
            {
                ModelAssistedMetric.ANSWER_FACTUAL_PRECISION,
                ModelAssistedMetric.ANSWER_COMPLETENESS,
            }
        )
        if reference_metrics and not self.reference_answer:
            raise ValueError("answer comparison metrics require reference_answer")
        return self


class ModelAssistedEvaluationResult(StrictModel):
    case_id: Identifier
    variant_id: Identifier
    status: ModelAssistedStatus
    scores: dict[ModelAssistedMetric, Annotated[float, Field(ge=0, le=1)]] = Field(
        default_factory=dict
    )
    evaluator_identity: dict[str, Any]
    failure_code: str | None = None
    metric_observations: tuple[ModelAssistedMetricObservation, ...] = ()
    details: dict[str, Any] = Field(default_factory=dict)
    latency_ms: Annotated[float, Field(ge=0)] | None = None
    retry_count: Annotated[int, Field(ge=0)] | None = None
    input_tokens: Annotated[int, Field(ge=0)] | None = None
    output_tokens: Annotated[int, Field(ge=0)] | None = None
    cost_usd: Annotated[float, Field(ge=0)] | None = None


class ExperimentResult(StrictModel):
    schema_version: Literal["v2"] = "v2"
    experiment_id: Identifier
    executed_at: datetime
    candidate_k: Annotated[int, Field(ge=1)]
    lineage: ExperimentLineage
    aggregate: AggregateResult
    slices: dict[str, AggregateResult]
    variants: tuple[VariantResult, ...]
    hard_failures: tuple[Identifier, ...]
    model_assisted: tuple[ModelAssistedEvaluationResult, ...] = ()


class QualityGatePolicy(StrictModel):
    schema_version: str = "v1"
    policy_version: str
    absolute_failures: tuple[Identifier, ...]
    load_bearing_slices: tuple[Identifier, ...]
    allowed_regressions: dict[str, float]
    advisory_metrics: tuple[Identifier, ...]


class GateDecision(StrEnum):
    ACCEPTED = "ACCEPTED"
    REJECTED = "REJECTED"
    WAIVED = "WAIVED"


class ManualGateRecord(StrictModel):
    schema_version: str = "v1"
    experiment_id: Identifier
    decision: GateDecision
    reviewer: str
    decided_at: datetime
    reason: Annotated[str, Field(min_length=1)]
    waiver_expires_at: datetime | None = None

    @model_validator(mode="after")
    def waiver_has_expiry(self) -> ManualGateRecord:
        if self.decision is GateDecision.WAIVED and self.waiver_expires_at is None:
            raise ValueError("waiver_expires_at is required for a waiver")
        if (
            self.decision is not GateDecision.WAIVED
            and self.waiver_expires_at is not None
        ):
            raise ValueError("waiver_expires_at is only valid for a waiver")
        return self


class BaselinePromotion(StrictModel):
    schema_version: str = "v1"
    experiment_id: Identifier
    corpus_version: str
    corpus_digest: str
    policy_version: str
    policy_digest: str
    deterministic_profile_digest: str | None = None
    repository_commit: str | None = None
    semantic_comparison_digest: str | None = None
    promoted_by: str
    promoted_at: datetime
    reason: Annotated[str, Field(min_length=1)]
