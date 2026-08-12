import logging
import math
import random
import time
from collections.abc import Callable
from datetime import UTC, datetime
from email.utils import parsedate_to_datetime
from typing import Any

import httpx
from opentelemetry import metrics, trace
from opentelemetry.trace import SpanKind, Status, StatusCode
from pydantic import SecretStr

from app.embedding.errors import (
    EmbeddingAuthenticationError,
    EmbeddingConfigurationError,
    EmbeddingDimensionMismatchError,
    EmbeddingError,
    EmbeddingInputTooLargeError,
    EmbeddingProfileMismatchError,
    EmbeddingProviderUnavailableError,
    EmbeddingRateLimitError,
    EmbeddingTimeoutError,
    InvalidEmbeddingInputError,
    MalformedEmbeddingResponseError,
)
from app.embedding.models import EmbeddedVector, EmbeddingRequest, EmbeddingResult
from app.telemetry import metric_attributes, trace_attributes

logger = logging.getLogger("embedding.voyage")

RATE_LIMIT_HEADER_ALLOWLIST = frozenset(
    {
        "retry-after",
        "ratelimit-limit",
        "ratelimit-remaining",
        "ratelimit-reset",
        "x-ratelimit-limit-requests",
        "x-ratelimit-remaining-requests",
        "x-ratelimit-reset-requests",
        "x-ratelimit-limit-tokens",
        "x-ratelimit-remaining-tokens",
        "x-ratelimit-reset-tokens",
    }
)


class VoyageEmbedder:
    def __init__(
        self,
        *,
        api_key: SecretStr,
        api_url: str = "https://api.voyageai.com/v1/embeddings",
        timeout_seconds: float = 10.0,
        max_attempts: int = 3,
        initial_backoff_seconds: float = 0.25,
        max_backoff_seconds: float = 2.0,
        estimated_cost_per_million_tokens_usd: float = 0.12,
        pricing_snapshot: str = "voyage-pricing-2026-08-12",
        client: httpx.Client | None = None,
        sleep: Callable[[float], None] = time.sleep,
        jitter: Callable[[], float] = random.random,
    ) -> None:
        if not api_key.get_secret_value().strip():
            raise EmbeddingConfigurationError("Voyage API key is required")
        if max_attempts < 1:
            raise EmbeddingConfigurationError("max_attempts must be at least one")
        if initial_backoff_seconds < 0 or max_backoff_seconds < initial_backoff_seconds:
            raise EmbeddingConfigurationError(
                "invalid embedding retry backoff configuration"
            )

        self._api_key = api_key
        self._api_url = api_url
        self._max_attempts = max_attempts
        self._initial_backoff_seconds = initial_backoff_seconds
        self._max_backoff_seconds = max_backoff_seconds
        self._estimated_cost_per_million_tokens_usd = (
            estimated_cost_per_million_tokens_usd
        )
        self._pricing_snapshot = pricing_snapshot
        self._client = client or httpx.Client(timeout=timeout_seconds)
        self._sleep = sleep
        self._jitter = jitter

    def embed(self, request: EmbeddingRequest) -> EmbeddingResult:
        self._validate_profile(request)
        attributes: dict[str, Any] = {
            "gen_ai.operation.name": "embeddings",
            "gen_ai.provider.name": request.profile.provider,
            "gen_ai.request.model": request.profile.model,
            "rag.correlation.id": str(request.correlation_id),
            "rag.workspace.id": str(request.workspace_id),
            "rag.document.id": (
                str(request.document_id) if request.document_id is not None else None
            ),
            "rag.embedding.item_count": len(request.items),
            "rag.embedding.profile_fingerprint": request.profile.fingerprint(),
            "rag.embedding.purpose": request.purpose.value,
        }
        meter = metrics.get_meter("maketime.python.embedding")
        request_count = meter.create_counter(
            "rag.embedding.request.count",
            unit="{request}",
            description="Count of embedding provider requests.",
        )
        request_duration = meter.create_histogram(
            "rag.embedding.request.duration",
            unit="s",
            description="Duration of embedding provider requests.",
        )
        started_at = time.perf_counter()

        with trace.get_tracer("maketime.python.embedding").start_as_current_span(
            "generate embeddings",
            kind=SpanKind.CLIENT,
            attributes=trace_attributes(attributes),
            record_exception=False,
            set_status_on_exception=False,
        ) as span:
            try:
                result, input_tokens, retry_count = self._request_with_retries(request)
                attributes.update(
                    {
                        "gen_ai.usage.input_tokens": input_tokens,
                        "rag.embedding.estimated_cost_usd": self._estimated_cost(
                            input_tokens
                        ),
                        "rag.embedding.retry_count": retry_count,
                        "rag.processing.outcome": "succeeded",
                    }
                )
                return result.model_copy(
                    update={
                        "provider_retry_count": retry_count,
                        "estimated_cost_usd": self._estimated_cost(input_tokens),
                        "pricing_snapshot": self._pricing_snapshot,
                    }
                )
            except EmbeddingError as exception:
                attributes.update(
                    {
                        "error.type": type(exception).__name__,
                        "rag.embedding.retry_count": exception.attempts - 1,
                        "rag.processing.outcome": "failed",
                    }
                )
                span.set_status(Status(StatusCode.ERROR))
                raise
            finally:
                span.set_attributes(trace_attributes(attributes))
                safe_attributes = metric_attributes(attributes)
                request_count.add(1, safe_attributes)
                request_duration.record(
                    time.perf_counter() - started_at,
                    safe_attributes,
                )
                logger.info(
                    "Embedding provider request completed.",
                    extra={
                        "correlation_id": attributes["rag.correlation.id"],
                        "document_id": attributes["rag.document.id"],
                        "workspace_id": attributes["rag.workspace.id"],
                        "embedding_provider": request.profile.provider,
                        "embedding_model": request.profile.model,
                        "embedding_purpose": request.purpose.value,
                        "embedding_item_count": len(request.items),
                        "embedding_outcome": attributes.get("rag.processing.outcome"),
                        "embedding_retry_count": attributes.get(
                            "rag.embedding.retry_count", 0
                        ),
                        "embedding_input_tokens": attributes.get(
                            "gen_ai.usage.input_tokens"
                        ),
                        "embedding_estimated_cost_usd": attributes.get(
                            "rag.embedding.estimated_cost_usd"
                        ),
                        "error_type": attributes.get("error.type"),
                    },
                )

    def _request_with_retries(
        self, request: EmbeddingRequest
    ) -> tuple[EmbeddingResult, int, int]:
        last_error: EmbeddingError | None = None

        for attempt in range(1, self._max_attempts + 1):
            try:
                response = self._client.post(
                    self._api_url,
                    headers={
                        "Authorization": (f"Bearer {self._api_key.get_secret_value()}"),
                        "Content-Type": "application/json",
                    },
                    json={
                        "input": [item.text for item in request.items],
                        "model": request.profile.model,
                        "input_type": request.profile.input_type_for(request.purpose),
                        "truncation": request.profile.truncation,
                        "output_dimension": request.profile.dimensions,
                        "output_dtype": request.profile.output_dtype,
                    },
                )
                error = self._response_error(response)
                if error is not None:
                    if isinstance(error, EmbeddingRateLimitError):
                        logger.warning(
                            "Embedding provider rate limit response received.",
                            extra={
                                "embedding_provider": request.profile.provider,
                                "embedding_model": request.profile.model,
                                "embedding_request_timestamp": datetime.now(
                                    UTC
                                ).isoformat(),
                                "embedding_item_count": len(request.items),
                                "embedding_input_tokens": None,
                                "embedding_http_status": response.status_code,
                                "embedding_retry_after_seconds": error.retry_after_seconds,
                                "embedding_rate_limit_headers": error.provider_headers,
                            },
                        )
                    raise error
                result, input_tokens = self._parse_response(response, request)
                return result, input_tokens, attempt - 1
            except httpx.TimeoutException:
                last_error = EmbeddingTimeoutError("embedding provider timed out")
            except httpx.TransportError:
                last_error = EmbeddingProviderUnavailableError(
                    "embedding provider was unavailable"
                )
            except EmbeddingError as exception:
                last_error = exception

            if not last_error.retryable or attempt == self._max_attempts:
                last_error.attempts = attempt
                raise last_error
            self._sleep(self._retry_delay(attempt, last_error))

        raise RuntimeError("embedding retry loop completed unexpectedly")

    @staticmethod
    def _validate_profile(request: EmbeddingRequest) -> None:
        profile = request.profile
        if (
            profile.provider != "voyage"
            or profile.output_dtype != "float"
            or profile.normalisation != "unit_length"
            or profile.truncation
        ):
            raise EmbeddingProfileMismatchError(
                "embedding profile is incompatible with the Voyage adapter"
            )

    def _retry_delay(self, attempt: int, error: EmbeddingError) -> float:
        if (
            isinstance(error, EmbeddingRateLimitError)
            and error.retry_after_seconds is not None
        ):
            return error.retry_after_seconds
        exponential = min(
            self._initial_backoff_seconds * (2 ** (attempt - 1)),
            self._max_backoff_seconds,
        )
        return exponential * (0.9 + (self._jitter() * 0.2))

    @staticmethod
    def _response_error(response: httpx.Response) -> EmbeddingError | None:
        status = response.status_code
        if 200 <= status < 300:
            return None
        if status in {401, 403}:
            return EmbeddingAuthenticationError(
                "embedding provider rejected credentials"
            )
        if status == 429:
            headers = {
                name.lower(): value
                for name, value in response.headers.items()
                if name.lower() in RATE_LIMIT_HEADER_ALLOWLIST
            }
            return EmbeddingRateLimitError(
                retry_after_seconds=VoyageEmbedder._retry_after_seconds(
                    headers.get("retry-after")
                ),
                provider_headers=headers,
            )
        if status in {408, 500, 502, 503, 504}:
            return EmbeddingProviderUnavailableError(
                "embedding provider was temporarily unavailable"
            )
        if status == 413:
            return EmbeddingInputTooLargeError("embedding request was too large")
        if status in {400, 422}:
            return InvalidEmbeddingInputError("embedding provider rejected the input")
        return MalformedEmbeddingResponseError(
            "embedding provider returned an unexpected status"
        )

    @staticmethod
    def _retry_after_seconds(value: str | None) -> float | None:
        if value is None:
            return None
        try:
            return max(0.0, float(value))
        except ValueError:
            try:
                retry_at = parsedate_to_datetime(value)
            except TypeError, ValueError, OverflowError:
                return None
            if retry_at.tzinfo is None:
                retry_at = retry_at.replace(tzinfo=UTC)
            return max(0.0, (retry_at - datetime.now(UTC)).total_seconds())

    @staticmethod
    def _parse_response(
        response: httpx.Response, request: EmbeddingRequest
    ) -> tuple[EmbeddingResult, int]:
        try:
            payload = response.json()
        except ValueError as exception:
            raise MalformedEmbeddingResponseError(
                "embedding provider returned invalid JSON"
            ) from exception

        if (
            not isinstance(payload, dict)
            or payload.get("model") != request.profile.model
        ):
            raise MalformedEmbeddingResponseError(
                "embedding provider returned an unexpected model"
            )
        data = payload.get("data")
        if not isinstance(data, list) or len(data) != len(request.items):
            raise MalformedEmbeddingResponseError(
                "embedding provider returned an unexpected item count"
            )

        embeddings = []
        for position, (item, entry) in enumerate(zip(request.items, data, strict=True)):
            if not isinstance(entry, dict) or entry.get("index") != position:
                raise MalformedEmbeddingResponseError(
                    "embedding provider returned unexpected ordering"
                )
            vector = entry.get("embedding")
            if (
                not isinstance(vector, list)
                or len(vector) != request.profile.dimensions
            ):
                raise EmbeddingDimensionMismatchError(
                    "embedding dimensions did not match the active profile"
                )
            if any(
                isinstance(value, bool)
                or not isinstance(value, (int, float))
                or not math.isfinite(float(value))
                for value in vector
            ):
                raise MalformedEmbeddingResponseError(
                    "embedding provider returned invalid vector values"
                )
            values = tuple(float(value) for value in vector)
            magnitude = math.sqrt(sum(value * value for value in values))
            if not math.isclose(magnitude, 1.0, rel_tol=1e-3, abs_tol=1e-3):
                raise MalformedEmbeddingResponseError(
                    "embedding provider returned a non-normalised vector"
                )
            embeddings.append(
                EmbeddedVector(
                    source_id=item.source_id,
                    values=values,
                    dimensions=request.profile.dimensions,
                )
            )

        usage = payload.get("usage")
        input_tokens = usage.get("total_tokens") if isinstance(usage, dict) else None
        if isinstance(input_tokens, bool) or not isinstance(input_tokens, int):
            raise MalformedEmbeddingResponseError(
                "embedding provider returned invalid token usage"
            )

        return (
            EmbeddingResult(
                profile=request.profile,
                profile_fingerprint=request.profile.fingerprint(),
                purpose=request.purpose,
                embeddings=tuple(embeddings),
                provider_input_tokens=input_tokens,
            ),
            input_tokens,
        )

    def _estimated_cost(self, input_tokens: int) -> float:
        return round(
            (input_tokens / 1_000_000) * self._estimated_cost_per_million_tokens_usd,
            8,
        )
