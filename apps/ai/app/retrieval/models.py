from __future__ import annotations

from datetime import date, datetime
from enum import StrEnum
from typing import Annotated, Literal
from uuid import UUID

from pydantic import Field, StringConstraints, model_validator

from app.embedding.models import EmbeddingProfile
from app.extraction.models import ImmutableModel
from app.provider_retry import ProviderRetryDelay
from app.sparse.models import SparseEmbeddingProfile
from app.vector_store.models import SparseVectorSpace, VectorSpace

Question = Annotated[
    str, StringConstraints(strip_whitespace=True, min_length=1, max_length=8000)
]
Reference = Annotated[
    str, StringConstraints(strip_whitespace=True, min_length=1, max_length=255)
]


class TemporalMode(StrEnum):
    CURRENT = "current"
    VALID_AT_DATE = "valid_at_date"
    COMPARE = "compare"
    HISTORICAL_REFERENCE = "historical_reference"
    CLARIFICATION_REQUIRED = "clarification_required"


class TemporalReferenceKind(StrEnum):
    CALENDAR_PERIOD = "calendar_period"
    HISTORICAL_REFERENCE = "historical_reference"


class ClarificationReason(StrEnum):
    UNCLASSIFIABLE_TEMPORAL_INTENT = "unclassifiable_temporal_intent"


class RetrievalSide(StrEnum):
    PRIMARY = "primary"
    COMPARISON = "comparison"


class TemporalReference(ImmutableModel):
    kind: TemporalReferenceKind
    value: Reference


class RetrievalPlan(ImmutableModel):
    retrieval_queries: tuple[Question, ...] = Field(min_length=1, max_length=1)
    temporal_mode: TemporalMode
    explicit_date: date | None = None
    temporal_reference: TemporalReference | None = None
    location_references: tuple[Reference, ...] = Field(default=(), max_length=8)
    clarification_reason: ClarificationReason | None = None

    @model_validator(mode="after")
    def validate_mode_fields(self) -> RetrievalPlan:
        mode = self.temporal_mode
        if self.explicit_date is not None and self.temporal_reference is not None:
            raise ValueError(
                "explicit_date and temporal_reference are mutually exclusive"
            )
        if mode is TemporalMode.CURRENT and (
            self.explicit_date is not None or self.temporal_reference is not None
        ):
            raise ValueError("current forbids temporal selectors")
        if mode is TemporalMode.VALID_AT_DATE:
            if (self.explicit_date is None) == (self.temporal_reference is None):
                raise ValueError("valid_at_date requires exactly one temporal selector")
            if (
                self.temporal_reference is not None
                and self.temporal_reference.kind
                is not TemporalReferenceKind.CALENDAR_PERIOD
            ):
                raise ValueError("valid_at_date requires a calendar period reference")
        if mode is TemporalMode.HISTORICAL_REFERENCE and (
            self.explicit_date is not None
            or self.temporal_reference is None
            or self.temporal_reference.kind
            is not TemporalReferenceKind.HISTORICAL_REFERENCE
        ):
            raise ValueError("historical_reference requires its typed reference")
        if mode not in {
            TemporalMode.VALID_AT_DATE,
            TemporalMode.HISTORICAL_REFERENCE,
            TemporalMode.COMPARE,
        } and (self.explicit_date is not None or self.temporal_reference is not None):
            raise ValueError("temporal selectors are not valid for this mode")
        clarification = mode is TemporalMode.CLARIFICATION_REQUIRED
        if clarification != (self.clarification_reason is not None):
            raise ValueError("clarification mode requires its controlled reason only")
        return self


class PlanRequest(ImmutableModel):
    contract_version: Literal[1]
    request_id: UUID
    workspace_id: UUID
    question: Question
    evaluated_at: datetime


class PlannerLineage(ImmutableModel):
    provider: Reference
    model: Reference
    contract_schema_version: Reference
    prompt_version: Reference
    adapter_version: Reference
    fingerprint: str = Field(pattern=r"^[a-f0-9]{64}$")


class OperationUsage(ImmutableModel):
    stage: Reference
    provider: Reference
    model: Reference
    execution: Literal["provider_api", "local", "infrastructure", "not_executed"]
    request_count: int = Field(ge=0)
    retry_count: int = Field(ge=0)
    provider_attempt_count: int | None = Field(default=None, ge=1)
    provider_retry_count: int | None = Field(default=None, ge=0)
    outer_attempt_count: int | None = Field(default=None, ge=1)
    outer_retry_count: int | None = Field(default=None, ge=0)
    rate_limit_event_count: int | None = Field(default=None, ge=0)
    retry_delays: tuple[ProviderRetryDelay, ...] = ()
    first_provider_attempt_at: datetime | None = None
    final_provider_success_at: datetime | None = None
    provider_retry_elapsed_ms: float | None = Field(default=None, ge=0)
    input_tokens: int | None = Field(default=None, ge=0)
    cached_input_tokens: int | None = Field(default=None, ge=0)
    output_tokens: int | None = Field(default=None, ge=0)
    latency_ms: float = Field(ge=0)
    cost_basis: Literal[
        "provider_reported", "estimated", "unavailable", "zero_cost_local"
    ]
    cost_usd: float | None = Field(default=None, ge=0)
    pricing_snapshot: Reference | None = None

    @model_validator(mode="after")
    def validate_cost(self) -> OperationUsage:
        if self.cost_basis == "unavailable" and self.cost_usd is not None:
            raise ValueError("unavailable planner cost cannot be numeric")
        if self.cost_basis == "zero_cost_local" and (
            self.execution not in {"local", "infrastructure"} or self.cost_usd != 0
        ):
            raise ValueError(
                "zero-cost usage requires local/infrastructure execution and explicit zero"
            )
        if self.cost_basis == "estimated" and (
            self.cost_usd is None or self.pricing_snapshot is None
        ):
            raise ValueError("estimated planner cost requires pricing lineage")
        if self.cost_basis == "provider_reported" and self.cost_usd is None:
            raise ValueError("provider-reported planner cost requires a value")
        return self


class PlanResponse(ImmutableModel):
    contract_version: Literal[2] = 2
    request_id: UUID
    plan: RetrievalPlan
    classifier_lineage: PlannerLineage
    usage: OperationUsage


class SearchScope(ImmutableModel):
    side: RetrievalSide = RetrievalSide.PRIMARY
    eligible_document_ids: tuple[UUID, ...] = Field(min_length=1, max_length=5000)

    @model_validator(mode="after")
    def unique_documents(self) -> SearchScope:
        if len(set(self.eligible_document_ids)) != len(self.eligible_document_ids):
            raise ValueError("eligible document IDs must be unique")
        return self


class SearchRequest(ImmutableModel):
    contract_version: Literal[1]
    request_id: UUID
    workspace_id: UUID
    query: Question
    embedding_profile: EmbeddingProfile
    embedding_profile_fingerprint: str
    vector_space: VectorSpace
    workspace_corpus_generation_id: UUID
    candidate_k: int = Field(ge=1, le=1000)
    sparse_embedding_profile: SparseEmbeddingProfile | None = None
    sparse_profile_fingerprint: str | None = None
    sparse_vector_space: SparseVectorSpace | None = None
    hybrid_configuration: HybridRetrievalConfiguration | None = None
    capture_diagnostics: bool = False
    scopes: tuple[SearchScope, ...] = Field(min_length=1, max_length=2)

    @model_validator(mode="after")
    def validate_lineage(self) -> SearchRequest:
        if self.embedding_profile.fingerprint() != self.embedding_profile_fingerprint:
            raise ValueError("embedding profile fingerprint does not match profile")
        if self.vector_space.profile_fingerprint != self.embedding_profile_fingerprint:
            raise ValueError("vector space does not match embedding profile")
        if self.vector_space.dimensions != self.embedding_profile.dimensions:
            raise ValueError("vector space dimensions do not match embedding profile")
        sides = tuple(scope.side for scope in self.scopes)
        if len(set(sides)) != len(sides):
            raise ValueError("retrieval sides must be unique")
        hybrid_values = (
            self.sparse_embedding_profile,
            self.sparse_profile_fingerprint,
            self.sparse_vector_space,
            self.hybrid_configuration,
        )
        if any(value is not None for value in hybrid_values):
            if any(value is None for value in hybrid_values):
                raise ValueError("hybrid search requires complete sparse lineage")
            assert self.sparse_embedding_profile is not None
            assert self.sparse_profile_fingerprint is not None
            assert self.sparse_vector_space is not None
            assert self.hybrid_configuration is not None
            if (
                self.sparse_embedding_profile.fingerprint()
                != self.sparse_profile_fingerprint
                or self.sparse_vector_space.profile_fingerprint
                != self.sparse_profile_fingerprint
            ):
                raise ValueError("sparse profile lineage does not match")
            if self.vector_space.sparse != self.sparse_vector_space:
                raise ValueError("vector space sparse lineage does not match")
            if self.candidate_k != self.hybrid_configuration.dense_candidate_k:
                raise ValueError("candidate_k must match dense_candidate_k")
        return self

    @property
    def is_hybrid(self) -> bool:
        return self.hybrid_configuration is not None


class HybridRetrievalConfiguration(ImmutableModel):
    version: Reference
    dense_candidate_k: int = Field(ge=1, le=1000)
    sparse_candidate_k: int = Field(ge=1, le=1000)
    fusion_candidate_k: int = Field(ge=1, le=1000)
    reranker_candidate_k: int = Field(ge=1, le=1000)
    final_evidence_k: int = Field(ge=1, le=1000)
    fusion_strategy: Literal["rrf"] = "rrf"
    fusion_version: Literal["1"] = "1"
    rrf_k: int = Field(gt=0)

    @model_validator(mode="after")
    def validate_monotonic_bounds(self) -> HybridRetrievalConfiguration:
        if self.fusion_candidate_k > self.dense_candidate_k + self.sparse_candidate_k:
            raise ValueError("fusion_candidate_k exceeds possible unique candidates")
        if self.reranker_candidate_k > self.fusion_candidate_k:
            raise ValueError("reranker_candidate_k exceeds fusion_candidate_k")
        if self.final_evidence_k > self.reranker_candidate_k:
            raise ValueError("final_evidence_k exceeds reranker_candidate_k")
        return self


class RetrievalCandidate(ImmutableModel):
    chunk_id: UUID
    document_id: UUID
    workspace_corpus_generation_id: UUID
    embedding_space_generation_id: UUID
    sparse_space_generation_id: UUID | None = None
    score: float
    rank: int = Field(ge=1)
    retrieval_method: Literal["dense", "sparse", "hybrid"] = "dense"
    side: RetrievalSide
    dense_score: float | None = None
    dense_rank: int | None = Field(default=None, ge=1)
    sparse_score: float | None = None
    sparse_rank: int | None = Field(default=None, ge=1)


class SearchLineage(ImmutableModel):
    embedding_profile_fingerprint: Reference
    sparse_profile_fingerprint: Reference | None = None
    sparse_space_generation_id: UUID | None = None
    fusion_strategy: str | None = None
    fusion_version: str | None = None
    rrf_k: int | None = Field(default=None, gt=0)
    configuration_version: str | None = None


class SearchStageDiagnostics(ImmutableModel):
    side: RetrievalSide
    dense_candidates: tuple[RetrievalCandidate, ...]
    sparse_candidates: tuple[RetrievalCandidate, ...] = ()
    fused_candidates: tuple[RetrievalCandidate, ...] = ()


class SearchResponse(ImmutableModel):
    contract_version: Literal[1] = 1
    request_id: UUID
    candidates: tuple[RetrievalCandidate, ...]
    lineage: SearchLineage
    diagnostics: tuple[SearchStageDiagnostics, ...] = ()
    usage: tuple[OperationUsage, ...] = ()
