"""Provider-free checks for fail-closed topical comparison family review."""

import json
from uuid import UUID, uuid4

import httpx
import pytest
from fastapi.testclient import TestClient
from pydantic import SecretStr, ValidationError

from app.main import app
from app.retrieval import compare_family
from app.retrieval.compare_family import (
    CompareFamilyDecision,
    CompareFamilyRequest,
    CompareFamilyResponse,
    CompareFamilyReviewer,
    validate_selection,
)
from app.retrieval.models import OperationUsage
from app.retrieval.routes import (
    compare_family_reviewer_dependency,
    comparison_review_failure_kind,
)
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


@pytest.mark.parametrize(
    "decision,changes,effective,rejection",
    [
        ("no_match", {}, "no_match", None),
        ("ambiguous", {}, "ambiguous", None),
        ("selected", {}, "selected", None),
        ("selected", {"family_id": str(UUID(int=4))}, "no_match", "identity_mismatch"),
        ("selected", {"chunk_id": str(UUID(int=4))}, "no_match", "identity_mismatch"),
        (
            "selected",
            {"quote": "invented quotation"},
            "no_match",
            "quote_not_in_candidate",
        ),
        ("selected", {"quote": "dress"}, "no_match", "quote_length"),
    ],
)
def test_original_and_effective_decisions_survive_signed_route(
    decision, changes, effective, rejection
) -> None:
    source = request().model_copy(update={"request_id": uuid4()})
    proposed = (
        selected(**changes)
        if decision == "selected"
        else CompareFamilyDecision(
            decision=decision, family_id=None, chunk_id=None, quote=None
        )
    )
    reviewer = CompareFamilyReviewer(
        api_url="https://provider.invalid/chat/completions",
        api_key=SecretStr("test-only-secret"),
        model="test-model",
        timeout_seconds=1,
    )
    calls = []

    def handler(http_request):
        calls.append(json.loads(http_request.content))
        return httpx.Response(
            200,
            json={
                "choices": [
                    {
                        "finish_reason": "stop",
                        "message": {"content": proposed.model_dump_json()},
                    }
                ]
            },
        )

    reviewer.client = httpx.Client(transport=httpx.MockTransport(handler))
    app.dependency_overrides[compare_family_reviewer_dependency] = lambda: reviewer
    path = "/api/internal/retrieval/compare-family"
    body = source.model_dump_json().encode()
    try:
        with TestClient(app) as client:
            response = client.post(
                path,
                content=body,
                headers=signed_headers(
                    path,
                    body,
                    WORKSPACE,
                    str(source.request_id),
                    "retrieval.compare-family",
                ),
            )
        assert response.status_code == 200
        result = response.json()
        assert result["original_decision"] == decision
        assert result["selection"] == validate_selection(source, proposed).model_dump(
            mode="json"
        )
        assert result["selection"]["decision"] == effective
        assert result["validation_rejection_code"] == rejection
        assert result["candidate_identities"] == [
            {"family_id": FAMILY, "chunk_id": CHUNK}
        ]
        assert len(calls) == 1
        assert calls[0]["messages"][0]["content"] == compare_family.PROMPT
        assert "test-only-secret" not in response.text
        if rejection:
            assert result["selection"]["quote"] is None
    finally:
        app.dependency_overrides.clear()


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
        assert sent["max_completion_tokens"] == 4096
        assert sent["reasoning_effort"] == "low"
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


@pytest.mark.parametrize(
    "question",
    [
        "How did both uniform allowances change in April 2026?",
        "What was the old serious-complaint escalation window and what is it now?",
        "What extra medication-fridge call was added in April 2026?",
    ],
)
def test_larger_combined_budget_allows_output_after_old_reasoning_ceiling(
    question: str,
) -> None:
    """Provider-free transport proof, not a model-quality guarantee."""
    source = request().model_copy(update={"question": question})
    reviewer = CompareFamilyReviewer(
        api_url="https://provider.invalid/chat/completions",
        api_key=SecretStr("test-only"),
        model="test-model",
        timeout_seconds=1,
    )
    calls = []

    def handler(http_request: httpx.Request) -> httpx.Response:
        sent = json.loads(http_request.content)
        calls.append(sent)
        assert sent["max_completion_tokens"] == 4096
        return httpx.Response(
            200,
            json={
                "choices": [
                    {
                        "finish_reason": "stop",
                        "message": {"content": selected().model_dump_json()},
                    }
                ],
                "usage": {
                    "prompt_tokens": 5102,
                    "completion_tokens": 530,
                    "completion_tokens_details": {"reasoning_tokens": 400},
                },
            },
        )

    reviewer.client = httpx.Client(transport=httpx.MockTransport(handler))
    result = reviewer.review(source)
    assert result.selection.family_id == UUID(FAMILY)
    assert result.usage.output_tokens == 530
    assert len(calls) == 1


def test_length_exhaustion_still_fails_after_one_bounded_attempt() -> None:
    reviewer = CompareFamilyReviewer(
        api_url="https://provider.invalid/chat/completions",
        api_key=SecretStr("test-only"),
        model="test-model",
        timeout_seconds=1,
    )
    calls = []

    def handler(http_request: httpx.Request) -> httpx.Response:
        calls.append(json.loads(http_request.content))
        return httpx.Response(
            200,
            json={
                "choices": [{"finish_reason": "length", "message": {"content": ""}}],
                "usage": {
                    "prompt_tokens": 5102,
                    "completion_tokens": 4096,
                    "completion_tokens_details": {"reasoning_tokens": 4096},
                },
            },
        )

    reviewer.client = httpx.Client(transport=httpx.MockTransport(handler))
    with pytest.raises(ValueError, match="comparison family reviewer failed") as caught:
        reviewer.review(request())
    assert caught.value.cause_type == "ValueError"
    assert caught.value.cause_message == "comparison selection did not complete"
    assert len(calls) == 1


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


@pytest.mark.parametrize("failure", ["transport", "provider_status", "incomplete"])
def test_review_failure_persists_local_diagnostic_without_changing_503(
    failure: str, tmp_path, monkeypatch: pytest.MonkeyPatch
) -> None:
    source = request().model_copy(update={"request_id": uuid4()})
    diagnostic = tmp_path / "compare-family-failures.jsonl"
    monkeypatch.setattr(compare_family, "_DIAGNOSTIC_PATH", diagnostic)
    reviewer = CompareFamilyReviewer(
        api_url="https://provider.invalid/chat/completions",
        api_key=SecretStr("test-only-secret"),
        model="test-model",
        timeout_seconds=1,
    )
    raw_body = '{"error":{"message":"simulated provider rejection"}}'

    def handler(http_request: httpx.Request) -> httpx.Response:
        if failure == "transport":
            raise httpx.ConnectError("simulated network failure", request=http_request)
        if failure == "provider_status":
            return httpx.Response(429, text=raw_body)
        return httpx.Response(
            200,
            json={
                "choices": [
                    {
                        "finish_reason": "length",
                        "message": {"content": "incomplete reviewer output"},
                    }
                ]
            },
        )

    reviewer.client = httpx.Client(transport=httpx.MockTransport(handler))
    app.dependency_overrides[compare_family_reviewer_dependency] = lambda: reviewer
    path = "/api/internal/retrieval/compare-family"
    body = source.model_dump_json().encode()
    headers = signed_headers(
        path, body, WORKSPACE, str(source.request_id), "retrieval.compare-family"
    )
    try:
        with TestClient(app) as client:
            response = client.post(path, content=body, headers=headers)
        assert response.status_code == 503
        assert response.json() == {
            "detail": {"code": "compare_family_review_unavailable"}
        }
        assert (
            response.headers["X-Comparison-Review-Failure"]
            == {
                "transport": "connection_error",
                "provider_status": "provider_rejected",
                "incomplete": "invalid_output",
            }[failure]
        )
    finally:
        app.dependency_overrides.clear()

    record = json.loads(diagnostic.read_text().strip())
    assert record["request_id"] == str(source.request_id)
    assert record["workspace_id"] == WORKSPACE
    assert diagnostic.stat().st_mode & 0o777 == 0o600
    assert "test-only-secret" not in diagnostic.read_text()
    if failure == "transport":
        assert record["exception_type"] == "ConnectError"
        assert record["exception_message"] == "simulated network failure"
        assert record["raw_provider_response_body"] is None
        assert record["provider_status"] is None
    elif failure == "provider_status":
        assert record["exception_type"] == "HTTPStatusError"
        assert record["raw_provider_response_body"] == raw_body
        assert record["provider_status"] == 429
    else:
        assert record["exception_type"] == "ValueError"
        assert record["exception_message"] == "comparison selection did not complete"
        assert "incomplete reviewer output" in record["raw_provider_response_body"]
        assert record["provider_status"] == 200


@pytest.mark.parametrize(
    "kind", ["timeout", "connection", 401, 403, 429, 503, "invalid", "unknown"]
)
def test_optional_review_failure_attribution_is_closed_and_not_message_based(kind):
    if isinstance(kind, int):
        error = httpx.HTTPStatusError(
            "timeout budget fake text",
            request=httpx.Request("POST", "https://example.invalid"),
            response=httpx.Response(kind),
        )
        expected = (
            "authentication_failed"
            if kind in (401, 403)
            else ("service_unavailable" if kind == 503 else "provider_rejected")
        )
    else:
        error = {
            "timeout": httpx.ReadTimeout,
            "connection": httpx.ConnectError,
            "invalid": ValueError,
            "unknown": RuntimeError,
        }[kind]("service unavailable")
        expected = {
            "timeout": "timeout",
            "connection": "connection_error",
            "invalid": "invalid_output",
            "unknown": "unknown",
        }[kind]
    assert comparison_review_failure_kind(error) == expected
