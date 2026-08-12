import math
from datetime import datetime
from typing import Annotated, Literal
from uuid import UUID

from pydantic import Field, StringConstraints, field_validator, model_validator

from app.extraction.models import ImmutableModel
from app.provider_retry import ProviderRetryDelay
from app.retrieval.models import RetrievalSide

NonEmptyString = Annotated[str, StringConstraints(strip_whitespace=True, min_length=1)]


class RerankerProfile(ImmutableModel):
    provider: NonEmptyString
    model: NonEmptyString
    adapter_version: NonEmptyString
    truncation: bool = False


class RerankCandidate(ImmutableModel):
    chunk_id: UUID
    document_id: UUID
    document_family_id: UUID
    version_position: int | None = Field(default=None, ge=1)
    side: RetrievalSide
    text: NonEmptyString
    fused_score: float
    fused_rank: int = Field(ge=1)

    @field_validator("fused_score")
    @classmethod
    def finite_fused_score(cls, value: float) -> float:
        if not math.isfinite(value):
            raise ValueError("fused score must be finite")
        return value


class RerankRequest(ImmutableModel):
    contract_version: Literal[1] = 1
    request_id: UUID
    workspace_id: UUID
    query: NonEmptyString
    profile: RerankerProfile
    candidates: tuple[RerankCandidate, ...] = Field(min_length=1, max_length=1000)
    top_k: int = Field(ge=1, le=1000)

    @model_validator(mode="after")
    def validate_candidates(self) -> RerankRequest:
        identities = tuple(
            (candidate.side, candidate.chunk_id) for candidate in self.candidates
        )
        if len(set(identities)) != len(identities):
            raise ValueError("rerank candidate side/chunk identities must be unique")
        return self


class RerankedCandidate(ImmutableModel):
    chunk_id: UUID
    side: RetrievalSide
    score: float
    rank: int = Field(ge=1)

    @field_validator("score")
    @classmethod
    def finite_score(cls, value: float) -> float:
        if not math.isfinite(value):
            raise ValueError("reranker score must be finite")
        return value


class RerankResult(ImmutableModel):
    contract_version: Literal[1] = 1
    request_id: UUID
    profile: RerankerProfile
    candidates: tuple[RerankedCandidate, ...] = Field(min_length=1)
    provider_input_tokens: int | None = Field(default=None, ge=0)
    provider_attempt_count: int = Field(default=1, ge=1)
    provider_retry_count: int = Field(default=0, ge=0)
    rate_limit_event_count: int = Field(default=0, ge=0)
    retry_delays: tuple[ProviderRetryDelay, ...] = ()
    first_provider_attempt_at: datetime | None = None
    final_provider_success_at: datetime | None = None
    provider_retry_elapsed_seconds: float = Field(default=0, ge=0)

    @model_validator(mode="after")
    def validate_result(self) -> RerankResult:
        identities = tuple(
            (candidate.side, candidate.chunk_id) for candidate in self.candidates
        )
        if len(set(identities)) != len(identities):
            raise ValueError("reranked side/chunk identities must be unique")
        for side in {candidate.side for candidate in self.candidates}:
            ranks = tuple(
                candidate.rank
                for candidate in self.candidates
                if candidate.side is side
            )
            if ranks != tuple(range(1, len(ranks) + 1)):
                raise ValueError(
                    "reranker ranks must be contiguous and 1-based within each side"
                )
        return self
