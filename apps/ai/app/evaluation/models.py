"""Application-owned evaluation contracts from ADR-0019 and ADR-0020."""

from __future__ import annotations

from datetime import datetime
from enum import StrEnum
from typing import Annotated, Any, Literal

from pydantic import BaseModel, ConfigDict, Field, model_validator

Identifier = Annotated[
    str, Field(min_length=1, max_length=160, pattern=r"^[a-zA-Z0-9._:-]+$")
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


class OperationalObservation(StrictModel):
    latency_ms: Annotated[float, Field(ge=0)] = 0
    token_usage: Annotated[int, Field(ge=0)] = 0
    provider_cost: Annotated[float, Field(ge=0)] = 0
    request_count: Annotated[int, Field(ge=0)] = 0


class VariantObservation(StrictModel):
    case_id: Identifier
    variant_id: Identifier
    candidates: tuple[RetrievedCandidate, ...] = ()
    planner_correct: bool
    eligibility_correct: bool
    outcome_correct: bool
    hard_failures: tuple[Identifier, ...] = ()
    operational: OperationalObservation = OperationalObservation()


class MetricValues(StrictModel):
    recall_at_k: Annotated[float, Field(ge=0, le=1)]
    precision_at_k: Annotated[float, Field(ge=0, le=1)]
    mrr: Annotated[float, Field(ge=0, le=1)]
    ndcg_at_k: Annotated[float, Field(ge=0, le=1)]


class VariantResult(StrictModel):
    case_id: Identifier
    variant_id: Identifier
    metrics: MetricValues
    side_metrics: dict[str, MetricValues]
    covered_evidence_ids: tuple[Identifier, ...]
    planner_correct: bool
    eligibility_correct: bool
    outcome_correct: bool
    hard_failures: tuple[Identifier, ...]
    operational: OperationalObservation


class AggregateResult(StrictModel):
    metrics: MetricValues
    planner_accuracy: Annotated[float, Field(ge=0, le=1)]
    eligibility_accuracy: Annotated[float, Field(ge=0, le=1)]
    outcome_accuracy: Annotated[float, Field(ge=0, le=1)]
    case_count: Annotated[int, Field(ge=0)]


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
    chunking_configuration: dict[str, Any]
    retrieval_configuration: dict[str, Any]
    evaluator: dict[str, Any] | None = None
    trial_count: Annotated[int, Field(ge=1)] = 1


class ModelAssistedStatus(StrEnum):
    COMPLETED = "COMPLETED"
    FAILED = "FAILED"


class ModelAssistedMetric(StrEnum):
    CONTEXT_RELEVANCE = "CONTEXT_RELEVANCE"


class ModelAssistedEvaluationRequest(StrictModel):
    case_id: Identifier
    variant_id: Identifier
    question: str
    retrieved_evidence: tuple[RetrievedCandidate, ...]
    metrics: tuple[ModelAssistedMetric, ...] = (ModelAssistedMetric.CONTEXT_RELEVANCE,)


class ModelAssistedEvaluationResult(StrictModel):
    case_id: Identifier
    variant_id: Identifier
    status: ModelAssistedStatus
    scores: dict[ModelAssistedMetric, Annotated[float, Field(ge=0, le=1)]] = Field(
        default_factory=dict
    )
    evaluator_identity: dict[str, Any]
    failure_code: str | None = None


class ExperimentResult(StrictModel):
    schema_version: str = "v1"
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
    promoted_by: str
    promoted_at: datetime
    reason: Annotated[str, Field(min_length=1)]
