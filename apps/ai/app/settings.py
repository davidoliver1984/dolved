from functools import lru_cache

from pydantic import Field, SecretStr
from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    service_name: str = "rag-platform-ai"
    environment: str = "local"
    aws_default_region: str = "us-east-1"
    aws_endpoint_url: str | None = None
    ingestion_queue: str = "rag-platform-ingestion-local"
    ingestion_worker_wait_time_seconds: int = Field(default=10, ge=0, le=20)
    ingestion_worker_visibility_timeout_seconds: int = Field(
        default=30,
        ge=1,
    )
    ingestion_worker_batch_size: int = Field(default=1, ge=1, le=10)
    ingestion_worker_error_wait_seconds: float = Field(default=2.0, ge=0.1)
    ingestion_worker_api_url: str = "http://api:8000"
    ingestion_worker_api_timeout_seconds: float = Field(default=10.0, gt=0)
    ingestion_worker_hmac_key_id: str = "local-v1"
    ingestion_worker_hmac_secret: SecretStr = SecretStr("")
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
