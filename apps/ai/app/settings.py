from functools import lru_cache

from pydantic import Field, SecretStr
from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    service_name: str = "rag-platform-ai"
    environment: str = "local"
    aws_default_region: str = "us-east-1"
    aws_endpoint_url: str | None = None
    ingestion_queue: str = "rag-platform-ingestion-local"
    ingestion_dlq: str = "rag-platform-ingestion-dlq-local"
    ingestion_worker_wait_time_seconds: int = Field(default=10, ge=0, le=20)
    ingestion_worker_visibility_timeout_seconds: int = Field(
        default=30,
        ge=1,
    )
    ingestion_worker_batch_size: int = Field(default=1, ge=1, le=10)
    ingestion_worker_error_wait_seconds: float = Field(default=2.0, ge=0.1)
    ingestion_worker_heartbeat_seconds: float = Field(default=30.0, ge=1)
    ingestion_chunk_batch_size: int = Field(default=50, ge=1, le=100)
    ingestion_resume_page_size: int = Field(default=50, ge=1, le=100)
    ingestion_worker_api_url: str = "http://api:8000"
    ingestion_worker_api_timeout_seconds: float = Field(default=10.0, gt=0)
    ingestion_worker_callback_max_attempts: int = Field(default=3, ge=1, le=10)
    ingestion_worker_callback_backoff_seconds: float = Field(default=0.25, ge=0)
    ingestion_worker_hmac_key_id: str = "local-v1"
    ingestion_worker_hmac_secret: SecretStr = SecretStr("")
    voyage_api_key: SecretStr = SecretStr("")
    voyage_api_url: str = "https://api.voyageai.com/v1/embeddings"
    embedding_model: str = "voyage-4-large"
    embedding_dimensions: int = Field(default=1024, gt=0)
    embedding_batch_size: int = Field(default=64, ge=1, le=1000)
    embedding_timeout_seconds: float = Field(default=10.0, gt=0)
    embedding_max_attempts: int = Field(default=3, ge=1, le=10)
    embedding_initial_backoff_seconds: float = Field(default=0.25, ge=0)
    embedding_max_backoff_seconds: float = Field(default=2.0, ge=0)
    embedding_estimated_cost_per_million_tokens_usd: float = Field(
        default=0.12,
        ge=0,
    )
    qdrant_url: str = "http://qdrant:6333"
    qdrant_api_key: SecretStr = SecretStr("")
    qdrant_timeout_seconds: int = Field(default=10, gt=0)
    retrieval_caller_hmac_keys: dict[str, SecretStr] = Field(
        default_factory=lambda: {
            "local-rc1": SecretStr("MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=")
        }
    )
    retrieval_caller_max_clock_skew_seconds: int = Field(default=300, ge=1)
    retrieval_max_body_bytes: int = Field(default=262_144, ge=1)
    retrieval_max_eligible_documents: int = Field(default=500, ge=1, le=5000)
    retrieval_candidate_k_max: int = Field(default=100, ge=1, le=1000)
    retrieval_planner_api_url: str = "https://api.openai.com/v1/chat/completions"
    retrieval_planner_api_key: SecretStr = SecretStr("")
    retrieval_planner_provider: str = "openai"
    retrieval_planner_model: str = "gpt-5-mini"
    retrieval_planner_timeout_seconds: float = Field(default=10.0, gt=0)
    otel_exporter_otlp_endpoint: str = "http://otel-collector:4318"
    otel_exporter_otlp_protocol: str = "http/protobuf"
    otel_exporter_otlp_timeout: int = Field(default=250, ge=1)
    otel_exporter_otlp_metrics_temporality_preference: str = "cumulative"
    otel_sdk_disabled: bool = False
    model_config = SettingsConfigDict(
        env_file=".env",
        env_file_encoding="utf-8",
        extra="ignore",
    )


@lru_cache
def get_settings() -> Settings:
    return Settings()
