import base64
import json
from dataclasses import replace
from pathlib import Path
from types import SimpleNamespace
from uuid import uuid4

import pytest
from fastapi.testclient import TestClient

from app.generation.errors import GenerationContextBudgetError
from app.generation.needs_output import NEEDS_RESPONSE, OpenAIGenerationNeedsOutput
from app.generation.needs_request import GenerationNeedsRequest
from app.generation.openai_adapter import (
    NEEDS_ADAPTER_VERSION,
    NEEDS_CONTRACT_VERSION,
    NEEDS_PROMPT_VERSION,
    OpenAIGenerationProfile,
    OpenAIGenerator,
)
from app.generation.routes import needs_generator_dependency
from app.main import app
from app.settings import get_settings
from tests.test_generation_contract import signed_request
from tests.test_openai_generation_adapter import FakeResponses, response


def prepared():
    request = json.loads(
        Path("/contracts/http/generation-needs/v2/request.example.json").read_text()
    )
    output = OpenAIGenerationNeedsOutput.model_validate(
        {
            "outcome": "answered",
            "answer_parts": [
                {
                    "text": "Synthetic answer.",
                    "evidence_ids": ["ev-01", "ev-02"],
                },
                {"text": "Synthetic current guidance.", "evidence_ids": ["ev-03"]},
            ],
            "unsupported_aspects": [],
            "insufficiency_reason": None,
            "aspect_links": [
                {
                    "need_id": n,
                    "answer_part_indices": [i],
                    "unsupported_aspect_indices": [],
                }
                for i, n in enumerate(["n1", "n2"])
            ],
        }
    )
    responses = FakeResponses([response(output)])
    adapter = OpenAIGenerator(
        api_key="test-only",
        client=SimpleNamespace(responses=responses),
        profile=OpenAIGenerationProfile(
            contract_version=NEEDS_CONTRACT_VERSION,
            prompt_version=NEEDS_PROMPT_VERSION,
            adapter_version=NEEDS_ADAPTER_VERSION,
        ),
    )
    return request, adapter, responses


def test_real_adapter_preserves_needs_schema_accounting_and_profile():
    raw, adapter, responses = prepared()
    result = adapter.generate_needs(GenerationNeedsRequest.model_validate(raw))
    assert result.contract_version == 2
    assert result.usage["request_count"] == 1
    assert result.usage["input_tokens"] == 321
    assert result.usage["contract_version"] == NEEDS_CONTRACT_VERSION
    sent = responses.calls[0]
    assert sent["text_format"] is OpenAIGenerationNeedsOutput
    assert "Independent requested needs" in sent["input"]
    assert "Current-only evidence" in sent["instructions"]
    assert "version-paired does NOT mean responsive" in sent["instructions"]
    assert "Do not substitute a related but different policy" in sent["instructions"]
    assert "supports_need_id" not in sent["input"]
    assert sent["store"] is False
    assert sent["truncation"] == "disabled"


def test_resolved_temporal_status_reaches_model_without_rewriting_authored_banner():
    raw, adapter, responses = prepared()
    banner = "Not yet effective; becomes authoritative on 1 September 2026."
    raw["evidence"][0]["text"] = banner
    raw["evidence"][0]["temporal_authority"].update(
        evaluated_at="2026-11-06T10:27:00+00:00",
        authority_start="2026-09-01T00:00:00+00:00",
        authoritative_at_evaluated_at=True,
    )
    adapter.generate_needs(GenerationNeedsRequest.model_validate(raw))
    sent = responses.calls[0]
    assert banner in sent["input"]
    assert '"authoritative_at_evaluated_at":true' in sent["input"]
    assert "author-time commentary" in sent["instructions"]
    assert "Do not re-resolve eligibility" in sent["instructions"]


def test_needs_schema_and_prompt_are_included_in_predispatch_token_budget():
    raw, adapter, responses = prepared()
    adapter.profile = replace(adapter.profile, context_window_tokens=1)
    with pytest.raises(GenerationContextBudgetError):
        adapter.generate_needs(GenerationNeedsRequest.model_validate(raw))
    assert responses.calls == []


def test_wrong_subject_gap_and_independent_answer_survive_real_adaptation():
    raw, adapter, responses = prepared()
    raw["evidence"][0]["temporal_authority"].update(
        document_title="Unrelated synthetic procedure.txt",
        document_family_title="Unrelated synthetic procedure",
    )
    output = OpenAIGenerationNeedsOutput.model_validate(
        {
            "outcome": "qualified",
            "answer_parts": [
                {
                    "text": "The supplied evidence cannot establish the requested comparison. The independent current guidance is available.",
                    "evidence_ids": ["ev-03"],
                }
            ],
            "unsupported_aspects": ["Changes to the specifically requested document"],
            "insufficiency_reason": None,
            "aspect_links": [
                {
                    "need_id": "n1",
                    "answer_part_indices": [],
                    "unsupported_aspect_indices": [0],
                },
                {
                    "need_id": "n2",
                    "answer_part_indices": [0],
                    "unsupported_aspect_indices": [],
                },
            ],
        }
    )
    responses.values = [response(output)]
    result = adapter.generate_needs(GenerationNeedsRequest.model_validate(raw))
    assert result.outcome.value == "qualified"
    assert result.aspect_links[0].answer_part_indices == ()
    assert "Unrelated synthetic procedure" in responses.calls[0]["input"]
    assert len(responses.calls) == 1
    # This proves data transport/adaptation, not automatic semantic detection by a fake.


def test_legacy_profile_cannot_dispatch_needs_silently():
    raw, adapter, responses = prepared()
    adapter.profile = OpenAIGenerationProfile()
    with pytest.raises(ValueError):
        adapter.generate_needs(GenerationNeedsRequest.model_validate(raw))
    assert responses.calls == []


def test_validation_retry_new_identity_crosses_real_auth_with_identical_model_input(
    monkeypatch,
):
    raw, adapter, responses = prepared()
    raw["request_id"] = str(uuid4())
    valid = responses.values[0]
    invalid = valid.output_parsed.model_dump()
    invalid["answer_parts"][0]["evidence_ids"] = ["ev-03"]
    responses.values = [response(invalid), valid]
    secret = b"n" * 32
    monkeypatch.setenv(
        "RETRIEVAL_CALLER_HMAC_KEYS",
        json.dumps({"test": base64.b64encode(secret).decode()}),
    )
    get_settings.cache_clear()
    app.dependency_overrides[needs_generator_dependency] = lambda: adapter
    path = "/api/internal/retrieval/generation/answer-needs"
    first_body, first_headers = signed_request(
        raw, secret, path, "generation.answer-needs"
    )
    second = {**raw, "request_id": str(uuid4())}
    second_body, second_headers = signed_request(
        second, secret, path, "generation.answer-needs"
    )
    try:
        client = TestClient(app)
        rejected = client.post(path, content=first_body, headers=first_headers)
        assert rejected.status_code == 200
        assert rejected.json()["error"]["validation"]["code"] == "citation_ownership"
        replay = client.post(path, content=first_body, headers=first_headers)
        assert replay.status_code == 401
        assert len(responses.calls) == 1
        accepted = client.post(path, content=second_body, headers=second_headers)
        assert accepted.status_code == 200
        assert accepted.json()["status"] == "completed"
        assert len(responses.calls) == 2
        # Actual rendered provider instructions/input/schema/settings, not just DTO equality.
        assert responses.calls[0] == responses.calls[1]
        assert first_body != second_body
        assert (
            first_headers["X-Retrieval-Caller-Signature"]
            != second_headers["X-Retrieval-Caller-Signature"]
        )
        assert (
            client.post(path, content=second_body, headers=second_headers).status_code
            == 401
        )
        assert len(responses.calls) == 2
    finally:
        app.dependency_overrides.clear()
        get_settings.cache_clear()


@pytest.mark.parametrize("invalid", [False, True])
def test_signed_route_uses_actual_adapter_and_is_purpose_isolated(monkeypatch, invalid):
    raw, adapter, responses = prepared()
    secret = b"n" * 32
    monkeypatch.setenv(
        "RETRIEVAL_CALLER_HMAC_KEYS",
        json.dumps({"test": base64.b64encode(secret).decode()}),
    )
    get_settings.cache_clear()
    app.dependency_overrides[needs_generator_dependency] = lambda: adapter
    path = "/api/internal/retrieval/generation/answer-needs"
    body, headers = signed_request(
        raw, secret, path, "generation.answer" if invalid else "generation.answer-needs"
    )
    try:
        result = TestClient(app).post(path, content=body, headers=headers)
    finally:
        app.dependency_overrides.clear()
        get_settings.cache_clear()
    if invalid:
        assert result.status_code == 401
        assert responses.calls == []
    else:
        assert result.status_code == 200
        NEEDS_RESPONSE.validate_python(result.json())
        assert result.json()["contract_version"] == 2
        assert result.json()["result"]["aspect_links"][1]["need_id"] == "n2"
        assert len(responses.calls) == 1


def test_shared_response_schema_and_tagged_failures():
    from jsonschema import Draft202012Validator

    schema = json.loads(
        Path("/contracts/http/generation-needs/v2/response.schema.json").read_text()
    )
    Draft202012Validator.check_schema(schema)
    assert schema == NEEDS_RESPONSE.json_schema()
    raw, _, _ = prepared()
    for status, key, value in [
        (
            "context_budget_exceeded",
            "failure",
            {
                "code": "GENERATION_CONTEXT_BUDGET_EXCEEDED",
                "policy_version": "whole-needs-evidence-v1",
                "proposed_units": 101,
                "maximum_units": 100,
            },
        ),
        (
            "provider_error",
            "error",
            {"category": "timeout", "attempt_count": 1, "latency_ms": 2},
        ),
    ]:
        payload = {
            "contract_version": 2,
            "request_id": raw["request_id"],
            "status": status,
            key: value,
        }
        NEEDS_RESPONSE.validate_python(payload)
        with pytest.raises(ValueError):
            NEEDS_RESPONSE.validate_python({**payload, "contract_version": 1})
        with pytest.raises(ValueError):
            NEEDS_RESPONSE.validate_python({**payload, "result": None})
