import random
import time
from collections.abc import Callable

import httpx
from pydantic import SecretStr

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
        client: httpx.Client | None = None,
        sleep: Callable[[float], None] = time.sleep,
        jitter: Callable[[], float] = random.random,
        monotonic: Callable[[], float] = time.monotonic,
        minimum_request_interval_seconds: float = 0,
    ) -> None:
        if not api_key.get_secret_value().strip():
            raise RerankerConfigurationError("Voyage API key is required")
        if (
            max_attempts < 1
            or initial_backoff_seconds < 0
            or max_backoff_seconds < initial_backoff_seconds
            or minimum_request_interval_seconds < 0
        ):
            raise RerankerConfigurationError("invalid reranker retry configuration")
        self._api_key = api_key
        self._api_url = api_url
        self._max_attempts = max_attempts
        self._initial_backoff_seconds = initial_backoff_seconds
        self._max_backoff_seconds = max_backoff_seconds
        self._client = client or httpx.Client(timeout=timeout_seconds)
        self._sleep = sleep
        self._jitter = jitter
        self._monotonic = monotonic
        self._minimum_request_interval_seconds = minimum_request_interval_seconds
        self._last_request_started: float | None = None

    def rerank(self, request: RerankRequest) -> RerankResult:
        if request.profile.provider != "voyage" or request.profile.truncation:
            raise RerankerConfigurationError(
                "reranker profile is incompatible with Voyage"
            )
        results: list[RerankedCandidate] = []
        total_tokens = 0
        total_retries = 0
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
            side_result = self._rerank_one(side_request)
            results.extend(side_result.candidates)
            total_tokens += side_result.provider_input_tokens or 0
            total_retries += side_result.provider_retry_count
        return RerankResult(
            request_id=request.request_id,
            profile=request.profile,
            candidates=tuple(results),
            provider_input_tokens=total_tokens,
            provider_retry_count=total_retries,
        )

    def _rerank_one(self, request: RerankRequest) -> RerankResult:
        last_error: RerankingError | None = None
        for attempt in range(1, self._max_attempts + 1):
            try:
                self._pace_request()
                response = self._client.post(
                    self._api_url,
                    headers={
                        "Authorization": f"Bearer {self._api_key.get_secret_value()}",
                        "Content-Type": "application/json",
                    },
                    json={
                        "query": request.query,
                        "documents": [
                            candidate.text for candidate in request.candidates
                        ],
                        "model": request.profile.model,
                        "top_k": request.top_k,
                        "truncation": False,
                    },
                )
                error = self._response_error(response)
                if error is not None:
                    raise error
                return self._parse_response(response, request).model_copy(
                    update={"provider_retry_count": attempt - 1}
                )
            except httpx.TimeoutException:
                last_error = RerankerTimeoutError("reranker provider timed out")
            except httpx.TransportError:
                last_error = RerankerProviderUnavailableError(
                    "reranker provider was unavailable"
                )
            except RerankingError as exception:
                last_error = exception
            if not last_error.retryable or attempt == self._max_attempts:
                last_error.attempts = attempt
                raise last_error
            delay = min(
                self._initial_backoff_seconds * (2 ** (attempt - 1)),
                self._max_backoff_seconds,
            )
            if (
                isinstance(last_error, RerankerRateLimitError)
                and last_error.retry_after_seconds is not None
            ):
                delay = min(last_error.retry_after_seconds, self._max_backoff_seconds)
            self._sleep(delay * (0.5 + (self._jitter() * 0.5)))
        raise RuntimeError("reranker retry loop completed unexpectedly")

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

    @staticmethod
    def _response_error(response: httpx.Response) -> RerankingError | None:
        status = response.status_code
        if 200 <= status < 300:
            return None
        if status in {401, 403}:
            return RerankerAuthenticationError("reranker provider rejected credentials")
        if status == 429:
            retry_after = None
            try:
                retry_after = max(0.0, float(response.headers["Retry-After"]))
            except KeyError, ValueError:
                pass
            return RerankerRateLimitError(retry_after_seconds=retry_after)
        if status in {408, 500, 502, 503, 504}:
            return RerankerProviderUnavailableError(
                "reranker provider was temporarily unavailable"
            )
        if status == 413:
            return RerankerInputTooLargeError("reranker request was too large")
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
                    "reranker input exceeds the provider token bound"
                )
            return InvalidRerankerInputError("reranker provider rejected the input")
        return MalformedRerankerResponseError(
            "reranker provider returned an unexpected status"
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
