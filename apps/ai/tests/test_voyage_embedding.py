import json
import logging
from collections.abc import Callable
from datetime import UTC, datetime
from typing import Any
from uuid import uuid4

import httpx
import pytest
from pydantic import SecretStr

from app.embedding.cooldown import ProviderCooldown
from app.embedding.errors import (
    EmbeddingAuthenticationError,
    EmbeddingDimensionMismatchError,
    EmbeddingError,
    EmbeddingInputTooLargeError,
    EmbeddingProfileMismatchError,
    EmbeddingRateLimitError,
    EmbeddingTimeoutError,
    InvalidEmbeddingInputError,
    MalformedEmbeddingResponseError,
)
from app.embedding.models import (
    V1_VOYAGE_PROFILE,
    EmbeddingInput,
    EmbeddingPurpose,
    EmbeddingRequest,
)
from app.embedding.voyage import VoyageEmbedder


def embedding_request(
    *, purpose: EmbeddingPurpose = EmbeddingPurpose.DOCUMENT
) -> EmbeddingRequest:
    return EmbeddingRequest(
        correlation_id=uuid4(),
        workspace_id=uuid4(),
        document_id=uuid4(),
        profile=V1_VOYAGE_PROFILE.model_copy(update={"dimensions": 3}),
        purpose=purpose,
        items=(
            EmbeddingInput(source_id=uuid4(), text="first private text"),
            EmbeddingInput(source_id=uuid4(), text="second private text"),
        ),
    )


def success_payload(*, dimensions: int = 3) -> dict[str, Any]:
    vector = [1.0] + ([0.0] * (dimensions - 1))
    return {
        "object": "list",
        "data": [
            {"object": "embedding", "embedding": vector, "index": 0},
            {"object": "embedding", "embedding": vector, "index": 1},
        ],
        "model": "voyage-4-large",
        "usage": {"total_tokens": 17},
    }


def adapter(
    handler: Callable[[httpx.Request], httpx.Response],
    **kwargs: Any,
) -> VoyageEmbedder:
    return VoyageEmbedder(
        api_key=SecretStr("top-secret"),
        client=httpx.Client(transport=httpx.MockTransport(handler)),
        sleep=kwargs.pop("sleep", lambda _: None),
        jitter=kwargs.pop("jitter", lambda: 0.0),
        cooldown=kwargs.pop("cooldown", ProviderCooldown()),
        **kwargs,
    )


def test_adapter_sends_only_provider_fields_and_maps_by_response_order() -> None:
    captured: dict[str, Any] = {}

    def handler(request: httpx.Request) -> httpx.Response:
        captured["headers"] = request.headers
        captured["payload"] = json.loads(request.content)
        return httpx.Response(200, json=success_payload())

    request = embedding_request()
    result = adapter(handler).embed(request)

    assert captured["headers"]["Authorization"] == "Bearer top-secret"
    assert captured["payload"] == {
        "input": ["first private text", "second private text"],
        "model": "voyage-4-large",
        "input_type": "document",
        "truncation": False,
        "output_dimension": 3,
        "output_dtype": "float",
    }
    assert tuple(vector.source_id for vector in result.embeddings) == tuple(
        item.source_id for item in request.items
    )
    assert result.provider_input_tokens == 17
    assert result.provider_retry_count == 0
    assert result.provider_attempt_count == 1
    assert result.rate_limit_event_count == 0
    assert result.retry_delays == ()
    assert result.provider_retry_elapsed_seconds == 0
    assert result.estimated_cost_usd == 0.00000204
    assert result.pricing_snapshot == "voyage-pricing-2026-08-12"


def test_query_purpose_uses_query_input_type() -> None:
    input_types: list[str] = []

    def handler(request: httpx.Request) -> httpx.Response:
        input_types.append(json.loads(request.content)["input_type"])
        return httpx.Response(200, json=success_payload())

    adapter(handler).embed(embedding_request(purpose=EmbeddingPurpose.QUERY))

    assert input_types == ["query"]


def test_adapter_rejects_an_incompatible_profile_before_network_io() -> None:
    calls = 0

    def handler(_: httpx.Request) -> httpx.Response:
        nonlocal calls
        calls += 1
        return httpx.Response(200, json=success_payload())

    request = embedding_request().model_copy(
        update={
            "profile": V1_VOYAGE_PROFILE.model_copy(
                update={"provider": "another-provider", "dimensions": 3}
            )
        }
    )

    with pytest.raises(EmbeddingProfileMismatchError):
        adapter(handler).embed(request)

    assert calls == 0


@pytest.mark.parametrize("status", [400, 401, 403, 413, 422])
def test_permanent_provider_failures_are_not_retried(status: int) -> None:
    calls = 0

    def handler(_: httpx.Request) -> httpx.Response:
        nonlocal calls
        calls += 1
        return httpx.Response(status, json={"detail": "must not be logged"})

    expected: type[EmbeddingError] = (
        EmbeddingAuthenticationError
        if status in {401, 403}
        else InvalidEmbeddingInputError
    )
    if status == 413:
        expected = EmbeddingInputTooLargeError

    with pytest.raises(expected):
        adapter(handler).embed(embedding_request())

    assert calls == 1


@pytest.mark.parametrize("status", [429, 500, 502, 503, 504])
def test_transient_provider_failures_use_bounded_retries(status: int) -> None:
    calls = 0
    delays: list[float] = []

    def handler(_: httpx.Request) -> httpx.Response:
        nonlocal calls
        calls += 1
        if calls < 3:
            return httpx.Response(status, headers={"Retry-After": "0.1"})
        return httpx.Response(200, json=success_payload())

    result = adapter(handler, max_attempts=3, sleep=delays.append).embed(
        embedding_request()
    )

    assert calls == 3
    assert len(delays) == 2
    assert result.provider_attempt_count == 3
    assert result.provider_retry_count == 2
    assert result.rate_limit_event_count == (2 if status == 429 else 0)
    assert tuple(item.delay_seconds for item in result.retry_delays) == tuple(delays)
    assert tuple(item.source for item in result.retry_delays) == (
        ("retry_after_numeric", "retry_after_numeric")
        if status == 429
        else ("configured_fallback", "configured_fallback")
    )
    assert result.first_provider_attempt_at is not None
    assert result.final_provider_success_at is not None
    assert result.provider_retry_elapsed_seconds == sum(delays)


def test_rate_limit_honours_retry_after_without_capping_and_records_safe_headers() -> (
    None
):
    delays: list[float] = []
    calls = 0

    def handler(_: httpx.Request) -> httpx.Response:
        nonlocal calls
        calls += 1
        if calls == 1:
            return httpx.Response(
                429,
                headers={
                    "Retry-After": "65",
                    "X-RateLimit-Remaining-Requests": "0",
                    "X-RateLimit-Reset-Requests": "60s",
                    "X-Unsafe-Provider-Detail": "must-not-be-retained",
                },
            )
        return httpx.Response(200, json=success_payload())

    adapter(
        handler,
        max_attempts=2,
        initial_backoff_seconds=2,
        max_backoff_seconds=2,
        sleep=delays.append,
    ).embed(embedding_request())

    assert delays == [65.0]


def test_rate_limit_error_exposes_only_normalised_provider_timing() -> None:
    with pytest.raises(EmbeddingRateLimitError) as captured:
        adapter(
            lambda _: httpx.Response(
                429,
                headers={
                    "Retry-After": "120",
                    "X-RateLimit-Remaining-Tokens": "0",
                    "Authorization": "must-not-be-retained",
                },
            ),
            max_attempts=1,
        ).embed(embedding_request())

    assert captured.value.retry_after_seconds == 120.0
    assert captured.value.provider_timing_source == "retry_after_numeric"
    assert "must-not-be-retained" not in vars(captured.value).values()


def test_rate_limit_honours_http_date_retry_after() -> None:
    delays: list[float] = []
    calls = 0

    def handler(_: httpx.Request) -> httpx.Response:
        nonlocal calls
        calls += 1
        if calls == 1:
            return httpx.Response(
                429, headers={"Retry-After": "Wed, 12 Aug 2026 12:01:05 GMT"}
            )
        return httpx.Response(200, json=success_payload())

    adapter(
        handler,
        max_attempts=2,
        sleep=delays.append,
        now=lambda: datetime(2026, 8, 12, 12, 0, 0, tzinfo=UTC),
    ).embed(embedding_request())

    assert delays == [65.0]


def test_rate_limit_uses_safe_reset_header_when_retry_after_is_absent() -> None:
    delays: list[float] = []
    calls = 0

    def handler(_: httpx.Request) -> httpx.Response:
        nonlocal calls
        calls += 1
        if calls == 1:
            return httpx.Response(429, headers={"X-RateLimit-Reset-Requests": "70s"})
        return httpx.Response(200, json=success_payload())

    adapter(handler, max_attempts=2, sleep=delays.append).embed(embedding_request())

    assert delays == [70.0]


def test_rate_limit_exhaustion_records_complete_provider_retry_history() -> None:
    delays: list[float] = []

    with pytest.raises(EmbeddingRateLimitError) as captured:
        adapter(
            lambda _: httpx.Response(429),
            max_attempts=4,
            initial_backoff_seconds=15,
            max_backoff_seconds=120,
            sleep=delays.append,
            jitter=lambda: 0.5,
        ).embed(embedding_request())

    assert delays == [15.0, 30.0, 60.0]
    assert captured.value.attempts == 4
    assert captured.value.total_retry_delay_seconds == 105.0
    assert captured.value.first_failure_at is not None
    assert captured.value.final_failure_at is not None


def test_provider_cooldown_beyond_finite_request_budget_fails_closed() -> None:
    calls = 0
    delays: list[float] = []

    def handler(_: httpx.Request) -> httpx.Response:
        nonlocal calls
        calls += 1
        return httpx.Response(429, headers={"Retry-After": "121"})

    with pytest.raises(EmbeddingRateLimitError) as captured:
        adapter(
            handler,
            max_attempts=4,
            max_provider_cooldown_seconds=120,
            sleep=delays.append,
        ).embed(embedding_request())

    assert calls == 1
    assert delays == []
    assert captured.value.retry_after_seconds == 121
    assert captured.value.attempts == 1


def test_rate_limit_activates_shared_local_cooldown_without_delaying_healthy_path() -> (
    None
):
    cooldown = ProviderCooldown(monotonic=lambda: 100.0)
    delays: list[float] = []

    adapter(
        lambda _: httpx.Response(200, json=success_payload()),
        cooldown=cooldown,
        sleep=delays.append,
    ).embed(embedding_request())
    assert delays == []

    with pytest.raises(EmbeddingRateLimitError):
        adapter(
            lambda _: httpx.Response(429, headers={"Retry-After": "65"}),
            max_attempts=1,
            cooldown=cooldown,
            sleep=delays.append,
        ).embed(embedding_request())

    adapter(
        lambda _: httpx.Response(200, json=success_payload()),
        cooldown=cooldown,
        sleep=delays.append,
    ).embed(embedding_request())
    assert delays == [65.0]


def test_operational_backoff_follows_configured_progression_with_jitter() -> None:
    delays: list[float] = []
    calls = 0

    def handler(_: httpx.Request) -> httpx.Response:
        nonlocal calls
        calls += 1
        if calls < 5:
            return httpx.Response(429)
        return httpx.Response(200, json=success_payload())

    adapter(
        handler,
        max_attempts=5,
        initial_backoff_seconds=15,
        max_backoff_seconds=120,
        sleep=delays.append,
        jitter=lambda: 0.5,
    ).embed(embedding_request())

    assert delays == [15.0, 30.0, 60.0, 120.0]


def test_transport_timeout_is_retried_and_remains_typed() -> None:
    calls = 0

    def handler(request: httpx.Request) -> httpx.Response:
        nonlocal calls
        calls += 1
        raise httpx.ReadTimeout("private transport detail", request=request)

    with pytest.raises(EmbeddingTimeoutError):
        adapter(handler, max_attempts=2).embed(embedding_request())

    assert calls == 2


@pytest.mark.parametrize(
    ("mutate", "expected"),
    [
        (
            lambda payload: payload.update(model="wrong"),
            MalformedEmbeddingResponseError,
        ),
        (lambda payload: payload["data"].pop(), MalformedEmbeddingResponseError),
        (
            lambda payload: payload["data"][0].update(index=1),
            MalformedEmbeddingResponseError,
        ),
        (
            lambda payload: payload["data"][0].update(embedding=[1.0, 0.0]),
            EmbeddingDimensionMismatchError,
        ),
        (
            lambda payload: payload["data"][0].update(embedding=[1.0, 1.0, 1.0]),
            MalformedEmbeddingResponseError,
        ),
    ],
)
def test_adapter_rejects_malformed_or_incompatible_responses(
    mutate: Callable[[dict[str, Any]], Any],
    expected: type[Exception],
) -> None:
    payload = success_payload()
    mutate(payload)

    with pytest.raises(expected):
        adapter(lambda _: httpx.Response(200, json=payload)).embed(embedding_request())


def test_logs_exclude_text_vectors_secret_and_provider_body(
    caplog: pytest.LogCaptureFixture,
) -> None:
    with caplog.at_level(logging.INFO, logger="embedding.voyage"):
        adapter(lambda _: httpx.Response(200, json=success_payload())).embed(
            embedding_request()
        )

    serialised = " ".join(
        f"{record.getMessage()} {record.__dict__}" for record in caplog.records
    )
    assert "first private text" not in serialised
    assert "top-secret" not in serialised
    assert "[1.0, 0.0, 0.0]" not in serialised
    assert "must not be logged" not in serialised
