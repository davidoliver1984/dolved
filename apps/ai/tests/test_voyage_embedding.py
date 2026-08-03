import json
import logging
from collections.abc import Callable
from typing import Any
from uuid import uuid4

import httpx
import pytest
from pydantic import SecretStr

from app.embedding.errors import (
    EmbeddingAuthenticationError,
    EmbeddingDimensionMismatchError,
    EmbeddingError,
    EmbeddingInputTooLargeError,
    EmbeddingProfileMismatchError,
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

    adapter(handler, max_attempts=3, sleep=delays.append).embed(embedding_request())

    assert calls == 3
    assert len(delays) == 2


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
