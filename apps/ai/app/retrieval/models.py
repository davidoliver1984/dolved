from datetime import datetime
from enum import StrEnum
from typing import Annotated, Literal
from uuid import UUID

from pydantic import Field, StringConstraints, model_validator

from app.embedding.models import EmbeddingProfile
from app.extraction.models import ImmutableModel
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
    CLARIFICATION_REQUIRED = "clarification_required"


class TemporalAnchorKind(StrEnum):
    CURRENT = "current"
    AT_DATE = "at_date"
    PREVIOUS = "previous"


class ClarificationReason(StrEnum):
    AMBIGUOUS_TEMPORAL_REFERENCE = "ambiguous_temporal_reference"
    MISSING_COMPARISON_ANCHOR = "missing_comparison_anchor"


class RetrievalSide(StrEnum):
    PRIMARY = "primary"
    COMPARISON = "comparison"


class TemporalAnchor(ImmutableModel):
    kind: TemporalAnchorKind
    at: datetime | None = None

    @model_validator(mode="after")
    def validate_date(self) -> TemporalAnchor:
        if (self.kind is TemporalAnchorKind.AT_DATE) != (self.at is not None):
            raise ValueError("at is required only for an at_date anchor")
        return self


class RetrievalPlan(ImmutableModel):
    retrieval_queries: tuple[Question, ...] = Field(min_length=1, max_length=1)
    temporal_mode: TemporalMode
    valid_at: datetime | None = None
    primary_anchor: TemporalAnchor | None = None
    comparison_anchor: TemporalAnchor | None = None
    applicability_reference: Reference | None = None
    clarification_reason: ClarificationReason | None = None

    @model_validator(mode="after")
    def validate_mode_fields(self) -> RetrievalPlan:
        if self.temporal_mode is TemporalMode.VALID_AT_DATE:
            if self.valid_at is None:
                raise ValueError("valid_at_date requires valid_at")
        elif self.valid_at is not None:
            raise ValueError("valid_at is only valid for valid_at_date")

        compare = self.temporal_mode is TemporalMode.COMPARE
        has_two_anchors = (
            self.primary_anchor is not None and self.comparison_anchor is not None
        )
        if compare != has_two_anchors:
            raise ValueError("compare requires exactly two anchors")
        if not compare and (
            self.primary_anchor is not None or self.comparison_anchor is not None
        ):
            raise ValueError("anchors are only valid for compare")

        clarification = self.temporal_mode is TemporalMode.CLARIFICATION_REQUIRED
        if clarification != (self.clarification_reason is not None):
            raise ValueError("clarification mode requires a controlled reason")
        return self


class PlanRequest(ImmutableModel):
    contract_version: Literal[1]
    request_id: UUID
    workspace_id: UUID
    question: Question
    evaluated_at: datetime


class PlanResponse(ImmutableModel):
    contract_version: Literal[1] = 1
    request_id: UUID
    plan: RetrievalPlan


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


class SearchResponse(ImmutableModel):
    contract_version: Literal[1] = 1
    request_id: UUID
    candidates: tuple[RetrievalCandidate, ...]
    lineage: SearchLineage
