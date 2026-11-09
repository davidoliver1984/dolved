"""Provider-free checks for fail-closed topical comparison family review."""

import json
from uuid import UUID

import httpx
import pytest
from fastapi.testclient import TestClient
from pydantic import SecretStr, ValidationError

from app.main import app
from app.retrieval.compare_family import (
    CompareFamilyDecision,
    CompareFamilyRequest,
    CompareFamilyResponse,
    CompareFamilyReviewer,
    validate_selection,
)
from app.retrieval.models import OperationUsage
from app.retrieval.routes import compare_family_reviewer_dependency
from tests.test_retrieval_routes import signed_headers

WORKSPACE = "5323cd6b-8b7a-40f9-98b0-9413c75f643e"
FAMILY = "00000000-0000-4000-8000-000000000001"
CHUNK = "00000000-0000-4000-8000-000000000002"


def request() -> CompareFamilyRequest:
    return CompareFamilyRequest.model_validate(
        {
            "contract_version": 1,
            "request_id": "00000000-0000-4000-8000-000000000003",
            "workspace_id": WORKSPACE,
            "question": "How did the clothing accommodation rule change?",
            "candidates": [
                {
                    "family_id": FAMILY,
                    "chunk_id": CHUNK,
                    "title": "Clothing Guidance",
                    "text": "Religious and cultural dress accommodation is permitted.",
                }
            ],
        }
    )


def selected(**changes: str | None) -> CompareFamilyDecision:
    return CompareFamilyDecision.model_validate(
        {
            "decision": "selected",
            "family_id": FAMILY,
            "chunk_id": CHUNK,
            "quote": "Religious and cultural dress accommodation",
            **changes,
        }
    )


def test_selected_family_requires_an_authorised_current_chunk_and_exact_quote() -> None:
    source = request()
    assert validate_selection(source, selected()).family_id == UUID(FAMILY)
    for altered in (
        {"family_id": "00000000-0000-4000-8000-000000000004"},
        {"chunk_id": "00000000-0000-4000-8000-000000000004"},
        {"quote": "A fabricated topical quotation"},
        {"quote": "dress"},
    ):
        assert validate_selection(source, selected(**altered)).decision == "no_match"


def test_non_selection_cannot_smuggle_a_family_or_quote() -> None:
    with pytest.raises(ValidationError):
        CompareFamilyDecision.model_validate(
            {
                "decision": "ambiguous",
                "family_id": FAMILY,
                "chunk_id": None,
                "quote": None,
            }
        )
    assert (
        validate_selection(
            request(),
            CompareFamilyDecision(
                decision="ambiguous", family_id=None, chunk_id=None, quote=None
            ),
        ).decision
        == "ambiguous"
    )


def test_duplicate_or_oversized_candidate_set_is_rejected() -> None:
    source = request().model_dump(mode="json")
    source["candidates"].append(source["candidates"][0])
    with pytest.raises(ValidationError):
        CompareFamilyRequest.model_validate(source)
    source["candidates"] = source["candidates"] * 7
    with pytest.raises(ValidationError):
        CompareFamilyRequest.model_validate(source)


def test_provider_result_is_bounded_and_rechecked_without_a_real_call() -> None:
    reviewer = CompareFamilyReviewer(
        api_url="https://provider.invalid/chat/completions",
        api_key=SecretStr("test-only"),
        model="test-model",
        timeout_seconds=1,
    )

    def handler(http_request: httpx.Request) -> httpx.Response:
        sent = json.loads(http_request.content)
        assert sent["messages"][1]["content"]
        return httpx.Response(
            200,
            json={
                "choices": [
                    {
                        "finish_reason": "stop",
                        "message": {"content": selected().model_dump_json()},
                    }
                ],
                "usage": {"prompt_tokens": 53, "completion_tokens": 17},
            },
        )

    reviewer.client = httpx.Client(transport=httpx.MockTransport(handler))
    result = reviewer.review(request())
    assert result.selection.family_id == UUID(FAMILY)
    assert result.usage.request_count == 1
    assert result.usage.input_tokens == 53
    assert result.usage.output_tokens == 17
    assert result.usage.cost_basis == "unavailable"


def test_signed_comparison_review_route_rejects_replay() -> None:
    source = request()

    class FakeReviewer:
        def review(self, incoming: CompareFamilyRequest) -> CompareFamilyResponse:
            assert incoming.workspace_id == UUID(WORKSPACE)
            return CompareFamilyResponse(
                request_id=incoming.request_id,
                selection=validate_selection(incoming, selected()),
                usage=OperationUsage(
                    stage="compare_family_selection",
                    provider="local",
                    model="fake",
                    execution="local",
                    request_count=0,
                    retry_count=0,
                    latency_ms=0,
                    cost_basis="zero_cost_local",
                    cost_usd=0,
                ),
            )

    app.dependency_overrides[compare_family_reviewer_dependency] = FakeReviewer
    path = "/api/internal/retrieval/compare-family"
    body = source.model_dump_json().encode()
    headers = signed_headers(
        path, body, WORKSPACE, str(source.request_id), "retrieval.compare-family"
    )
    try:
        with TestClient(app) as client:
            response = client.post(path, content=body, headers=headers)
            assert response.status_code == 200
            assert response.json()["selection"]["family_id"] == FAMILY
            replay = client.post(path, content=body, headers=headers)
            assert replay.status_code == 401
    finally:
        app.dependency_overrides.clear()
