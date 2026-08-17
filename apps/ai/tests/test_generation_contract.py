import base64
import hashlib
import hmac
import json
import time
from typing import cast
from uuid import uuid4

import pytest
from fastapi.testclient import TestClient
from pydantic import ValidationError

from app.generation.fake import DeterministicGenerator
from app.generation.models import (
    AnswerPart,
    GenerationContextBudgetExceeded,
    GenerationOutcome,
    GenerationProviderError,
    GenerationRequest,
    GenerationResponse,
    GenerationResult,
)
from app.generation.routes import generator_dependency
from app.main import app
from app.settings import get_settings


def request_payload() -> dict[str, object]:
    return {
        "contract_version": 1,
        "request_id": str(uuid4()),
        "workspace_id": str(uuid4()),
        "question": "What is the current procedure?",
        "evidence": [
            {
                "evidence_id": "ev-01",
                "text": "Use the current procedure.",
                "document_chunk_id": 1,
                "document_id": 2,
                "ingestion_event_claim_id": 3,
                "source_provenance": [{"kind": "text"}],
                "temporal_authority": {"mode": "current"},
                "applicability_context": {"scope": "universal"},
                "side": "primary",
            }
        ],
        "constraints": {
            "context_policy_version": "whole-evidence-v1",
            "max_context_characters": 1000,
            "required_sides": ["primary"],
        },
    }


@pytest.mark.parametrize(
    ("outcome", "parts", "unsupported", "reason"),
    [
        ("answered", [{"text": "Answer.", "evidence_ids": ["ev-01"]}], [], None),
        (
            "qualified",
            [{"text": "Partial.", "evidence_ids": ["ev-01"]}],
            ["duration"],
            None,
        ),
        (
            "insufficient_evidence",
            [],
            ["duration"],
            "The supplied evidence does not address the duration.",
        ),
    ],
)
def test_generation_outcome_invariants(
    outcome: str,
    parts: list[dict[str, object]],
    unsupported: list[str],
    reason: str | None,
) -> None:
    result = GenerationResult.model_validate(
        {
            "outcome": outcome,
            "answer_parts": parts,
            "unsupported_aspects": unsupported,
            "insufficiency_reason": reason,
        }
    )
    assert result.outcome.value == outcome


def test_invalid_outcome_and_unknown_citation_fail_closed() -> None:
    invalid_results = [
        {
            "outcome": "answered",
            "answer_parts": [{"text": "Answer.", "evidence_ids": ["ev-01"]}],
            "unsupported_aspects": ["duration"],
            "insufficiency_reason": None,
        },
        {
            "outcome": "qualified",
            "answer_parts": [{"text": "Partial.", "evidence_ids": ["ev-01"]}],
            "unsupported_aspects": [],
            "insufficiency_reason": None,
        },
        {
            "outcome": "insufficient_evidence",
            "answer_parts": [],
            "unsupported_aspects": ["duration"],
            "insufficiency_reason": None,
        },
    ]
    for invalid in invalid_results:
        with pytest.raises(ValidationError):
            GenerationResult.model_validate(invalid)

    with pytest.raises(ValidationError):
        AnswerPart(text="Grounded prose.", evidence_ids=())
    with pytest.raises(ValidationError):
        AnswerPart(text="   ", evidence_ids=("ev-01",))

    request = GenerationRequest.model_validate(request_payload())
    result = GenerationResult(
        outcome=GenerationOutcome.ANSWERED,
        answer_parts=(AnswerPart(text="No.", evidence_ids=("ev-99",)),),
    )
    with pytest.raises(ValueError, match="outside"):
        result.validate_against(request)


def test_answer_part_can_cite_multiple_authorised_evidence_items() -> None:
    payload = request_payload()
    evidence = cast(list[dict[str, object]], payload["evidence"])
    second = dict(evidence[0])
    second["evidence_id"] = "ev-02"
    second["document_chunk_id"] = 4
    payload["evidence"] = [evidence[0], second]
    request = GenerationRequest.model_validate(payload)
    result = GenerationResult(
        outcome=GenerationOutcome.ANSWERED,
        answer_parts=(
            AnswerPart(text="Synthesised answer.", evidence_ids=("ev-01", "ev-02")),
        ),
    )
    assert result.validate_against(request) is result


def test_response_envelope_is_exactly_one_shape() -> None:
    request = GenerationRequest.model_validate(request_payload())
    result = DeterministicGenerator().generate(request)
    assert (
        GenerationResponse(
            request_id=request.request_id, status="completed", result=result
        ).result
        == result
    )
    budget: dict[str, object] = {
        "policy_version": "whole-evidence-v1",
        "proposed_units": 101,
        "maximum_units": 100,
    }
    error: dict[str, object] = {
        "category": "timeout",
        "attempt_count": 1,
        "latency_ms": 1,
    }
    invalid_envelopes: list[dict[str, object]] = [
        {"status": "completed", "result": result, "error": error},
        {"status": "completed", "result": result, "failure": budget},
        {"status": "context_budget_exceeded", "failure": budget, "error": error},
        {"status": "unknown_fourth_shape", "result": result},
        {"result": result},
        {"status": "completed", "result": result, "smuggled": {}},
    ]
    for invalid in invalid_envelopes:
        with pytest.raises(ValidationError):
            GenerationResponse.model_validate(
                {"request_id": request.request_id, **invalid}
            )


def test_typed_failure_envelopes_are_distinct_from_business_outcomes() -> None:
    request = GenerationRequest.model_validate(request_payload())
    budget = GenerationResponse(
        request_id=request.request_id,
        status="context_budget_exceeded",
        failure=GenerationContextBudgetExceeded(
            policy_version="whole-evidence-v1", proposed_units=101, maximum_units=100
        ),
    )
    provider = GenerationResponse(
        request_id=request.request_id,
        status="provider_error",
        error=GenerationProviderError(
            category="timeout", provider=None, model=None, attempt_count=1, latency_ms=2
        ),
    )
    assert budget.failure is not None and budget.result is None
    assert provider.error is not None and provider.result is None


def test_generation_route_uses_rc1_authentication_and_deterministic_fake(
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    secret = b"g" * 32
    monkeypatch.setenv(
        "RETRIEVAL_CALLER_HMAC_KEYS",
        json.dumps({"test": base64.b64encode(secret).decode()}),
    )
    get_settings.cache_clear()
    payload = request_payload()
    path = "/api/internal/retrieval/generation/answer"
    body, headers = signed_request(payload, secret, path)
    app.dependency_overrides[generator_dependency] = DeterministicGenerator
    try:
        response = TestClient(app).post(
            path,
            content=body,
            headers=headers,
        )
    finally:
        app.dependency_overrides.clear()
        get_settings.cache_clear()
    assert response.status_code == 200
    assert response.json()["status"] == "completed"
    assert response.json()["result"]["answer_parts"][0]["evidence_ids"] == ["ev-01"]


def test_generation_route_fails_closed_when_no_provider_adapter_is_configured(
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    secret = b"u" * 32
    monkeypatch.setenv(
        "RETRIEVAL_CALLER_HMAC_KEYS",
        json.dumps({"test": base64.b64encode(secret).decode()}),
    )
    get_settings.cache_clear()
    payload = request_payload()
    path = "/api/internal/retrieval/generation/answer"
    body, headers = signed_request(payload, secret, path)
    try:
        response = TestClient(app).post(path, content=body, headers=headers)
    finally:
        get_settings.cache_clear()

    assert response.status_code == 200
    assert response.json()["status"] == "provider_error"
    assert response.json()["error"] == {
        "category": "contract_validation_failure",
        "provider": None,
        "model": None,
        "http_status": None,
        "attempt_count": 1,
        "latency_ms": 0.0,
    }


def signed_request(
    payload: dict[str, object], secret: bytes, path: str
) -> tuple[bytes, dict[str, str]]:
    body = json.dumps(payload, separators=(",", ":")).encode()
    timestamp = str(int(time.time()))
    canonical = f"{timestamp}\nPOST\n{path}\n{hashlib.sha256(body).hexdigest()}\n{payload['workspace_id']}\ngeneration.answer\n{payload['request_id']}"
    signature = hmac.new(secret, canonical.encode(), hashlib.sha256).hexdigest()
    return body, {
        "X-Retrieval-Caller-Key-ID": "test",
        "X-Retrieval-Caller-Timestamp": timestamp,
        "X-Retrieval-Caller-Workspace-ID": str(payload["workspace_id"]),
        "X-Retrieval-Caller-Request-ID": str(payload["request_id"]),
        "X-Retrieval-Caller-Purpose": "generation.answer",
        "X-Retrieval-Caller-Signature": f"rc1={signature}",
    }
