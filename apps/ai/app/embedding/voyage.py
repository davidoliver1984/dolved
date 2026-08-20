import logging
import math
import random
import time
from collections.abc import Callable
from datetime import UTC, datetime
from typing import Any

import httpx
from opentelemetry import metrics, trace
from opentelemetry.trace import SpanKind, Status, StatusCode
from pydantic import SecretStr

from app.embedding.cooldown import ProviderCooldown, voyage_embedding_cooldown
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
from app.embedding.models import (
    EmbeddedVector,
    EmbeddingRequest,
    EmbeddingResult,
)
from app.provider_retry import ProviderRetryDelay, voyage_provider_timing
from app.telemetry import metric_attributes, trace_attributes

logger = logging.getLogger("embedding.voyage")


class VoyageEmbedder:
    def __init__(
        self,
        *,
        api_key: SecretStr,
        api_url: str = "https://api.voyageai.com/v1/embeddings",
        timeout_seconds: float = 10.0,
        max_attempts: int = 4,
        initial_backoff_seconds: float = 15.0,
        max_backoff_seconds: float = 120.0,
        max_provider_cooldown_seconds: float = 120.0,
        estimated_cost_per_million_tokens_usd: float = 0.12,
        pricing_snapshot: str = "voyage-pricing-2026-08-12",
        client: httpx.Client | None = None,
        sleep: Callable[[float], None] = time.sleep,
        jitter: Callable[[], float] = random.random,
        cooldown: ProviderCooldown = voyage_embedding_cooldown,
        now: Callable[[], datetime] = lambda: datetime.now(UTC),
    ) -> None:
        if not api_key.get_secret_value().strip():
            raise EmbeddingConfigurationError("Voyage API key is required")
        if max_attempts < 1:
            raise EmbeddingConfigurationError("max_attempts must be at least one")
        if initial_backoff_seconds < 0 or max_backoff_seconds < initial_backoff_seconds:
            raise EmbeddingConfigurationError(
                "invalid embedding retry backoff configuration"
            )
        if max_provider_cooldown_seconds <= 0:
            raise EmbeddingConfigurationError(
                "max_provider_cooldown_seconds must be positive"
            )

        self._api_key = api_key
        self._api_url = api_url
        self._max_attempts = max_attempts
        self._initial_backoff_seconds = initial_backoff_seconds
        self._max_backoff_seconds = max_backoff_seconds
        self._max_provider_cooldown_seconds = max_provider_cooldown_seconds
        self._estimated_cost_per_million_tokens_usd = (
            estimated_cost_per_million_tokens_usd
        )
        self._pricing_snapshot = pricing_snapshot
        self._client = client or httpx.Client(timeout=timeout_seconds)
        self._sleep = sleep
        self._jitter = jitter
        self._cooldown = cooldown
        self._now = now

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
        meter = metrics.get_meter("dolved.python.embedding")
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

        with trace.get_tracer("dolved.python.embedding").start_as_current_span(
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
                        "event_name": "embedding.provider.completed.v1",
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
        first_failure_at: str | None = None
        total_retry_delay_seconds = 0.0
        retry_delays: list[ProviderRetryDelay] = []
        rate_limit_event_count = 0
        first_provider_attempt_at = self._now()

        for attempt in range(1, self._max_attempts + 1):
            try:
                remaining = self._cooldown.remaining_seconds()
                if remaining > 0:
                    with self._cooldown.acquire_probe():
                        remaining = self._cooldown.remaining_seconds()
                        if remaining > 0:
                            self._sleep(remaining)
                            total_retry_delay_seconds += remaining
                            retry_delays.append(
                                ProviderRetryDelay(
                                    delay_seconds=remaining,
                                    source="shared_cooldown",
                                )
                            )
                            self._cooldown.clear()
                        response = self._post(request)
                else:
                    response = self._post(request)
                error = self._response_error(response)
                if error is not None:
                    if isinstance(error, EmbeddingRateLimitError):
                        rate_limit_event_count += 1
                        cooldown = (
                            error.retry_after_seconds
                            if error.retry_after_seconds is not None
                            else self._retry_delay(attempt, error)
                        )
                        if cooldown <= self._max_provider_cooldown_seconds:
                            self._cooldown.extend(cooldown)
                        else:
                            error.retryable = False
                        logger.warning(
                            "Embedding provider rate limit response received.",
                            extra={
                                "event_name": "embedding.provider.rate_limited.v1",
                                "embedding_provider": request.profile.provider,
                                "embedding_model": request.profile.model,
                                "embedding_request_timestamp": self._now().isoformat(),
                                "embedding_item_count": len(request.items),
                                "embedding_input_tokens": None,
                                "embedding_http_status": response.status_code,
                                "embedding_retry_after_seconds": error.retry_after_seconds,
                                "embedding_provider_timing_source": error.provider_timing_source,
                            },
                        )
                    raise error
                result, input_tokens = self._parse_response(response, request)
                return (
                    result.model_copy(
                        update={
                            "provider_attempt_count": attempt,
                            "provider_retry_count": attempt - 1,
                            "rate_limit_event_count": rate_limit_event_count,
                            "retry_delays": tuple(retry_delays),
                            "first_provider_attempt_at": first_provider_attempt_at,
                            "final_provider_success_at": self._now(),
                            "provider_retry_elapsed_seconds": total_retry_delay_seconds,
                        }
                    ),
                    input_tokens,
                    attempt - 1,
                )
            except httpx.TimeoutException:
                last_error = EmbeddingTimeoutError("embedding provider timed out")
            except httpx.TransportError:
                last_error = EmbeddingProviderUnavailableError(
                    "embedding provider was unavailable"
                )
            except EmbeddingError as exception:
                last_error = exception

            failed_at = self._now().isoformat()
            first_failure_at = first_failure_at or failed_at
            if not last_error.retryable or attempt == self._max_attempts:
                last_error.attempts = attempt
                last_error.first_failure_at = first_failure_at
                last_error.final_failure_at = failed_at
                last_error.total_retry_delay_seconds = total_retry_delay_seconds
                raise last_error
            delay = self._retry_delay(attempt, last_error)
            self._sleep(delay)
            total_retry_delay_seconds += delay
            retry_delays.append(
                ProviderRetryDelay(
                    delay_seconds=delay,
                    source=(
                        last_error.provider_timing_source or "configured_fallback"
                        if isinstance(last_error, EmbeddingRateLimitError)
                        and last_error.retry_after_seconds is not None
                        else "configured_fallback"
                    ),
                )
            )
            self._cooldown.clear()

        raise RuntimeError("embedding retry loop completed unexpectedly")

    def _post(self, request: EmbeddingRequest) -> httpx.Response:
        return self._client.post(
            self._api_url,
            headers={
                "Authorization": f"Bearer {self._api_key.get_secret_value()}",
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

    def _response_error(self, response: httpx.Response) -> EmbeddingError | None:
        status = response.status_code
        if 200 <= status < 300:
            return None
        if status in {401, 403}:
            return EmbeddingAuthenticationError(
                "embedding provider rejected credentials", provider_status=status
            )
        if status == 429:
            delay, source = voyage_provider_timing(response.headers, now=self._now)
            return EmbeddingRateLimitError(
                retry_after_seconds=delay,
                provider_timing_source=source,
                provider_status=status,
            )
        if status in {408, 500, 502, 503, 504}:
            return EmbeddingProviderUnavailableError(
                "embedding provider was temporarily unavailable", provider_status=status
            )
        if status == 413:
            return EmbeddingInputTooLargeError(
                "embedding request was too large", provider_status=status
            )
        if status in {400, 422}:
            return InvalidEmbeddingInputError(
                "embedding provider rejected the input", provider_status=status
            )
        return MalformedEmbeddingResponseError(
            "embedding provider returned an unexpected status", provider_status=status
        )

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
