from __future__ import annotations

from enum import StrEnum

from pydantic import Field

from app.extraction.models import ImmutableModel
from app.provider_retry import ProviderRetryDelay
from app.retrieval.models import OperationUsage


class RetrievalFailureStage(StrEnum):
    DENSE_EMBEDDING = "dense_embedding"
    QDRANT_DENSE_SEARCH = "qdrant_dense_search"
    SPARSE_ENCODING = "sparse_encoding"
    QDRANT_SPARSE_SEARCH = "qdrant_sparse_search"
    FUSION = "fusion"
    RERANKER = "reranker"
    TRANSPORT_ORCHESTRATION = "transport_orchestration"


class RetrievalFailureCategory(StrEnum):
    TIMEOUT = "timeout"
    RATE_LIMITED = "rate_limited"
    PROVIDER_HTTP_ERROR = "provider_http_error"
    CONNECTION_ERROR = "connection_error"
    INVALID_PROVIDER_RESPONSE = "invalid_provider_response"
    CONTRACT_VALIDATION_ERROR = "contract_validation_error"
    LOCAL_EXECUTION_ERROR = "local_execution_error"
    INFRASTRUCTURE_ERROR = "infrastructure_error"
    UNKNOWN = "unknown"


class RetrievalFailureObservation(ImmutableModel):
    stage: RetrievalFailureStage
    execution: str = Field(
        pattern=r"^(provider_api|local|infrastructure|orchestration)$"
    )
    provider: str | None = Field(default=None, max_length=160)
    model: str | None = Field(default=None, max_length=240)
    category: RetrievalFailureCategory
    http_status: int | None = Field(default=None, ge=100, le=599)
    retry_count: int | None = Field(default=None, ge=0)
    provider_retry_count: int | None = Field(default=None, ge=0)
    outer_retry_count: int | None = Field(default=None, ge=0)
    rate_limit_event_count: int | None = Field(default=None, ge=0)
    retry_delays: tuple[ProviderRetryDelay, ...] = ()
    request_count: int | None = Field(default=None, ge=0)
    first_failure_at: str | None = None
    final_failure_at: str | None = None
    retry_delay_ms: float | None = Field(default=None, ge=0)
    provider_retry_after_seconds: float | None = Field(default=None, ge=0)
    provider_timing_source: str | None = Field(default=None, max_length=80)
    latency_ms: float = Field(ge=0)
    usage: tuple[OperationUsage, ...] = ()
    downstream_request_attempted: bool
    candidate_lineage_produced: bool


class RetrievalExecutionError(RuntimeError):
    def __init__(self, observation: RetrievalFailureObservation) -> None:
        super().__init__("Retrieval execution failed.")
        self.observation = observation
