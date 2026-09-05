import random
import time
from collections.abc import Callable
from datetime import UTC, datetime

import httpx
from opentelemetry import trace
from opentelemetry.trace import SpanKind, Status, StatusCode
from pydantic import SecretStr

from app.operational_metrics import record_dependency
from app.provider_retry import (
    ProviderCooldown,
    ProviderRetryDelay,
    voyage_provider_cooldown,
    voyage_provider_timing,
)
from app.reranking.errors import (
    InvalidRerankerInputError,
    MalformedRerankerResponseError,
    RerankerAuthenticationError,
    RerankerConfigurationError,
    RerankerInputTooLargeError,
    RerankerProviderUnavailableError,
    RerankerRateLimitError,
    RerankerTimeoutError,
    RerankingError,
)
from app.reranking.models import RerankedCandidate, RerankRequest, RerankResult
from app.telemetry import trace_attributes


class VoyageReranker:
    def __init__(
        self,
        *,
        api_key: SecretStr,
        api_url: str,
        timeout_seconds: float,
        max_attempts: int,
        initial_backoff_seconds: float,
        max_backoff_seconds: float,
        max_provider_cooldown_seconds: float = 90,
        client: httpx.Client | None = None,
        sleep: Callable[[float], None] = time.sleep,
        jitter: Callable[[], float] = random.random,
        monotonic: Callable[[], float] = time.monotonic,
        minimum_request_interval_seconds: float = 0,
        cooldown: ProviderCooldown = voyage_provider_cooldown,
        now: Callable[[], datetime] = lambda: datetime.now(UTC),
    ) -> None:
        if not api_key.get_secret_value().strip():
            raise RerankerConfigurationError("Voyage API key is required")
        if (
            max_attempts < 1
            or initial_backoff_seconds < 0
            or max_backoff_seconds < initial_backoff_seconds
            or max_provider_cooldown_seconds <= 0
            or minimum_request_interval_seconds < 0
        ):
            raise RerankerConfigurationError("invalid reranker retry configuration")
        self._api_key = api_key
        self._api_url = api_url
        self._max_attempts = max_attempts
        self._initial_backoff_seconds = initial_backoff_seconds
        self._max_backoff_seconds = max_backoff_seconds
        self._max_provider_cooldown_seconds = max_provider_cooldown_seconds
        self._client = client or httpx.Client(timeout=timeout_seconds)
        self._sleep = sleep
        self._jitter = jitter
        self._monotonic = monotonic
        self._minimum_request_interval_seconds = minimum_request_interval_seconds
        self._last_request_started: float | None = None
        self._cooldown = cooldown
        self._now = now

    def rerank(self, request: RerankRequest) -> RerankResult:
        if request.profile.provider != "voyage" or request.profile.truncation:
            raise RerankerConfigurationError(
                "reranker profile is incompatible with Voyage"
            )
        started = self._monotonic()
        tracer = trace.get_tracer("dolved.python.reranking")
        with tracer.start_as_current_span(
            "reranking.provider.call",
            kind=SpanKind.CLIENT,
            attributes=trace_attributes(
                {
                    "gen_ai.operation.name": "rerank",
                    "gen_ai.provider.name": "voyage",
                    "gen_ai.request.model": request.profile.model,
                    "rag.operation.stage": "reranking_provider",
                    "rag.retrieval.reranker.candidate_count": len(request.candidates),
                }
            ),
            record_exception=False,
            set_status_on_exception=False,
        ) as span:
            try:
                result = self._rerank(request, started)
                span.set_attributes(
                    trace_attributes(
                        {
                            "rag.operation.outcome": "completed",
                            "rag.retrieval.reranker.retry_count": result.provider_retry_count,
                        }
                    )
                )
                return result
            except Exception as exception:
                span.set_attributes(
                    trace_attributes(
                        {
                            "rag.operation.outcome": "failed",
                            "error.type": type(exception).__name__,
                        }
                    )
                )
                span.set_status(Status(StatusCode.ERROR))
                raise

    def _rerank(self, request: RerankRequest, started: float) -> RerankResult:
        results: list[RerankedCandidate] = []
        total_tokens = 0
        total_attempts = 0
        total_retries = 0
        total_rate_limits = 0
        total_retry_elapsed_seconds = 0.0
        retry_delays: list[ProviderRetryDelay] = []
        first_provider_attempt_at: datetime | None = None
        final_provider_success_at: datetime | None = None
        for side in sorted(
            {candidate.side for candidate in request.candidates},
            key=lambda item: item.value,
        ):
            side_request = request.model_copy(
                update={
                    "candidates": tuple(
                        candidate
                        for candidate in request.candidates
                        if candidate.side is side
                    ),
                    "top_k": min(
                        request.top_k,
                        sum(candidate.side is side for candidate in request.candidates),
                    ),
                }
            )
            try:
                side_result = self._rerank_one(side_request)
            except RerankingError as exception:
                exception.attempts += total_attempts
                exception.provider_retry_count += total_retries
                exception.rate_limit_event_count += total_rate_limits
                exception.retry_delays = tuple(retry_delays) + exception.retry_delays
                exception.total_retry_delay_seconds += total_retry_elapsed_seconds
                exception.provider_input_tokens = total_tokens or None
                if first_provider_attempt_at is not None:
                    exception.first_provider_attempt_at = (
                        first_provider_attempt_at.isoformat()
                    )
                record_dependency(
                    "reranking_provider",
                    False,
                    self._monotonic() - started,
                )
                raise
            results.extend(side_result.candidates)
            total_tokens += side_result.provider_input_tokens or 0
            total_attempts += side_result.provider_attempt_count
            total_retries += side_result.provider_retry_count
            total_rate_limits += side_result.rate_limit_event_count
            retry_delays.extend(side_result.retry_delays)
            total_retry_elapsed_seconds += side_result.provider_retry_elapsed_seconds
            first_provider_attempt_at = (
                first_provider_attempt_at or side_result.first_provider_attempt_at
            )
            final_provider_success_at = side_result.final_provider_success_at
        result = RerankResult(
            request_id=request.request_id,
            profile=request.profile,
            candidates=tuple(results),
            provider_input_tokens=total_tokens,
            provider_attempt_count=total_attempts,
            provider_retry_count=total_retries,
            rate_limit_event_count=total_rate_limits,
            retry_delays=tuple(retry_delays),
            first_provider_attempt_at=first_provider_attempt_at,
            final_provider_success_at=final_provider_success_at,
            provider_retry_elapsed_seconds=total_retry_elapsed_seconds,
        )
        record_dependency(
            "reranking_provider",
            True,
            self._monotonic() - started,
        )
        return result

    def _rerank_one(self, request: RerankRequest) -> RerankResult:
        last_error: RerankingError | None = None
        first_failure_at: str | None = None
        total_retry_delay_seconds = 0.0
        retry_delays: list[ProviderRetryDelay] = []
        rate_limit_event_count = 0
        first_provider_attempt_at: datetime | None = None
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
                        first_provider_attempt_at = (
                            first_provider_attempt_at or self._now()
                        )
                        response = self._post(request)
                else:
                    first_provider_attempt_at = first_provider_attempt_at or self._now()
                    response = self._post(request)
                error = self._response_error(response)
                if error is not None:
                    if isinstance(error, RerankerRateLimitError):
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
                    raise error
                return self._parse_response(response, request).model_copy(
                    update={
                        "provider_attempt_count": attempt,
                        "provider_retry_count": attempt - 1,
                        "rate_limit_event_count": rate_limit_event_count,
                        "retry_delays": tuple(retry_delays),
                        "first_provider_attempt_at": first_provider_attempt_at,
                        "final_provider_success_at": self._now(),
                        "provider_retry_elapsed_seconds": total_retry_delay_seconds,
                    }
                )
            except httpx.TimeoutException:
                last_error = RerankerTimeoutError("reranker provider timed out")
            except httpx.TransportError:
                last_error = RerankerProviderUnavailableError(
                    "reranker provider was unavailable"
                )
            except RerankingError as exception:
                last_error = exception
            failed_at = self._now().isoformat()
            first_failure_at = first_failure_at or failed_at
            if not last_error.retryable or attempt == self._max_attempts:
                last_error.attempts = attempt
                last_error.provider_retry_count = attempt - 1
                last_error.rate_limit_event_count = rate_limit_event_count
                last_error.retry_delays = tuple(retry_delays)
                last_error.first_provider_attempt_at = (
                    first_provider_attempt_at.isoformat()
                    if first_provider_attempt_at is not None
                    else None
                )
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
                        if isinstance(last_error, RerankerRateLimitError)
                        and last_error.retry_after_seconds is not None
                        else "configured_fallback"
                    ),
                )
            )
            self._cooldown.clear()
        raise RuntimeError("reranker retry loop completed unexpectedly")

    def _post(self, request: RerankRequest) -> httpx.Response:
        self._pace_request()
        return self._client.post(
            self._api_url,
            headers={
                "Authorization": f"Bearer {self._api_key.get_secret_value()}",
                "Content-Type": "application/json",
            },
            json={
                "query": request.query,
                "documents": [
                    candidate.provider_representation()
                    for candidate in request.candidates
                ],
                "model": request.profile.model,
                "top_k": request.top_k,
                "truncation": False,
            },
        )

    def _retry_delay(self, attempt: int, error: RerankingError) -> float:
        if (
            isinstance(error, RerankerRateLimitError)
            and error.retry_after_seconds is not None
        ):
            return error.retry_after_seconds
        exponential = min(
            self._initial_backoff_seconds * (2 ** (attempt - 1)),
            self._max_backoff_seconds,
        )
        return exponential * (0.9 + (self._jitter() * 0.2))

    def _pace_request(self) -> None:
        now = self._monotonic()
        if self._last_request_started is not None:
            remaining = self._minimum_request_interval_seconds - (
                now - self._last_request_started
            )
            if remaining > 0:
                self._sleep(remaining)
                now = self._monotonic()
        self._last_request_started = now

    def _response_error(self, response: httpx.Response) -> RerankingError | None:
        status = response.status_code
        if 200 <= status < 300:
            return None
        if status in {401, 403}:
            return RerankerAuthenticationError(
                "reranker provider rejected credentials", provider_status=status
            )
        if status == 429:
            retry_after, source = voyage_provider_timing(
                response.headers, now=self._now
            )
            return RerankerRateLimitError(
                retry_after_seconds=retry_after,
                provider_timing_source=source,
                provider_status=status,
            )
        if status in {408, 500, 502, 503, 504}:
            return RerankerProviderUnavailableError(
                "reranker provider was temporarily unavailable", provider_status=status
            )
        if status == 413:
            return RerankerInputTooLargeError(
                "reranker request was too large", provider_status=status
            )
        if status in {400, 422}:
            try:
                detail = str(response.json()).lower()
            except ValueError:
                detail = ""
            if any(
                marker in detail
                for marker in ("too long", "max token", "context length", "truncat")
            ):
                return RerankerInputTooLargeError(
                    "reranker input exceeds the provider token bound",
                    provider_status=status,
                )
            return InvalidRerankerInputError(
                "reranker provider rejected the input", provider_status=status
            )
        return MalformedRerankerResponseError(
            "reranker provider returned an unexpected status", provider_status=status
        )

    @staticmethod
    def _parse_response(
        response: httpx.Response, request: RerankRequest
    ) -> RerankResult:
        try:
            payload = response.json()
            if payload.get("model") != request.profile.model:
                raise ValueError("model mismatch")
            data = payload["data"]
            if not isinstance(data, list) or len(data) != request.top_k:
                raise ValueError("result count mismatch")
            seen: set[int] = set()
            candidates = []
            for rank, item in enumerate(data, start=1):
                index = item["index"]
                score = item["relevance_score"]
                if (
                    not isinstance(index, int)
                    or index < 0
                    or index >= len(request.candidates)
                    or index in seen
                ):
                    raise ValueError("candidate index mismatch")
                if not isinstance(score, int | float):
                    raise TypeError("invalid relevance score")
                seen.add(index)
                candidates.append(
                    RerankedCandidate(
                        chunk_id=request.candidates[index].chunk_id,
                        side=request.candidates[index].side,
                        score=float(score),
                        rank=rank,
                    )
                )
            usage = payload.get("usage", {})
            tokens = usage.get("total_tokens") if isinstance(usage, dict) else None
            if tokens is not None and (not isinstance(tokens, int) or tokens < 0):
                raise ValueError("invalid token usage")
            return RerankResult(
                request_id=request.request_id,
                profile=request.profile,
                candidates=tuple(candidates),
                provider_input_tokens=tokens,
            )
        except (KeyError, TypeError, ValueError) as exception:
            raise MalformedRerankerResponseError(
                "reranker provider returned a malformed response"
            ) from exception
