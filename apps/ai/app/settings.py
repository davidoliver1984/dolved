from functools import lru_cache

from pydantic import AliasChoices, Field, SecretStr, model_validator
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
    embedding_max_attempts: int = Field(default=4, ge=1, le=10)
    embedding_initial_backoff_seconds: float = Field(default=15.0, ge=0)
    embedding_max_backoff_seconds: float = Field(default=120.0, ge=0)
    embedding_max_provider_cooldown_seconds: float = Field(default=120.0, gt=0)
    embedding_estimated_cost_per_million_tokens_usd: float = Field(
        default=0.12,
        ge=0,
    )
    embedding_pricing_snapshot: str = "voyage-pricing-2026-08-12"
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
    sparse_embedding_model: str = "prithivida/Splade_PP_en_v1"
    sparse_embedding_source_repository: str = "Qdrant/Splade_PP_en_v1"
    sparse_embedding_tokenizer: str = "bert-base-uncased"
    sparse_embedding_tokenizer_revision: str | None = None
    sparse_embedding_model_revision: str = "efcd182bc7eb351e81a9445752d4388c2bab500b"
    sparse_embedding_cache_dir: str = "/opt/fastembed-cache"
    sparse_embedding_max_input_tokens: int = Field(default=512, ge=1)
    sparse_embedding_batch_size: int = Field(default=64, ge=1, le=1000)
    voyage_rerank_api_url: str = "https://api.voyageai.com/v1/rerank"
    reranker_model: str = "rerank-2.5"
    reranker_timeout_seconds: float = Field(default=10.0, gt=0)
    reranker_max_attempts: int = Field(default=3, ge=1, le=10)
    reranker_initial_backoff_seconds: float = Field(default=15.0, ge=0)
    reranker_max_backoff_seconds: float = Field(default=90.0, ge=0)
    reranker_max_provider_cooldown_seconds: float = Field(default=90.0, gt=0)
    retrieval_planner_api_url: str = "https://api.openai.com/v1/chat/completions"
    retrieval_planner_api_key: SecretStr = SecretStr("")
    retrieval_planner_provider: str = "openai"
    retrieval_planner_model: str = "gpt-5-mini"
    retrieval_planner_timeout_seconds: float = Field(default=60.0, gt=0)
    contextualiser_api_url: str = "https://api.openai.com/v1/chat/completions"
    contextualiser_api_key: SecretStr = Field(
        default=SecretStr(""),
        validation_alias=AliasChoices(
            "CONTEXTUALISER_API_KEY", "OPENAI_API_KEY", "RETRIEVAL_PLANNER_API_KEY"
        ),
    )
    contextualiser_provider: str = "openai"
    contextualiser_model: str = "gpt-5-mini"
    contextualiser_contract_version: str = "conversation-contextualisation-v1"
    contextualiser_prompt_version: str = "conversation-contextualisation-v1"
    contextualiser_adapter_version: str = "structured-chat-v1"
    contextualiser_timeout_seconds: float = Field(default=60.0, gt=0)
    contextualiser_max_attempts: int = Field(default=3, ge=1, le=10)
    generation_openai_api_key: SecretStr = Field(
        default=SecretStr(""),
        validation_alias=AliasChoices(
            "GENERATION_OPENAI_API_KEY", "OPENAI_API_KEY", "RETRIEVAL_PLANNER_API_KEY"
        ),
    )
    generation_provider: str = "openai"
    generation_model: str = "gpt-5-mini"
    generation_prompt_version: str = "grounded-generation-v2"
    generation_contract_version: str = "generation-result-v1"
    generation_adapter_version: str = "openai-responses-v1"
    generation_reasoning_effort: str = "low"
    generation_max_output_tokens: int = Field(default=4096, ge=1, le=128_000)
    generation_context_window_tokens: int = Field(default=400_000, ge=1)
    generation_timeout_seconds: float = Field(default=120.0, gt=0)
    generation_max_attempts: int = Field(default=3, ge=1, le=10)
    generation_initial_backoff_seconds: float = Field(default=2.0, ge=0)
    generation_max_backoff_seconds: float = Field(default=30.0, ge=0)
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

    @model_validator(mode="after")
    def retry_backoff_fits_finite_provider_envelope(self) -> Settings:
        if (
            self.embedding_max_backoff_seconds
            > self.embedding_max_provider_cooldown_seconds
        ):
            raise ValueError(
                "embedding backoff exceeds the finite provider cooldown bound"
            )
        if (
            self.reranker_max_backoff_seconds
            > self.reranker_max_provider_cooldown_seconds
        ):
            raise ValueError(
                "reranker backoff exceeds the finite provider cooldown bound"
            )
        if (
            self.generation_max_backoff_seconds
            < self.generation_initial_backoff_seconds
        ):
            raise ValueError("generation maximum backoff precedes initial backoff")
        if self.generation_max_output_tokens >= self.generation_context_window_tokens:
            raise ValueError(
                "generation output budget must fit within the context window"
            )
        return self


@lru_cache
def get_settings() -> Settings:
    return Settings()
