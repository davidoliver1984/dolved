import json
from types import SimpleNamespace
from uuid import uuid4

import httpx
import openai
import pytest
from pydantic import SecretStr

from app.generation.current_policy import (
    FRAMING,
    MAX_REQUEST,
    CurrentRequest,
    Output,
    generate,
    render,
    request_units,
)
from app.settings import Settings


def request(text="Inspect weekly."):
    return CurrentRequest.model_validate(
        {
            "request_id": str(uuid4()),
            "workspace_id": str(uuid4()),
            "question": "What checks apply now?",
            "admitted_requests": ["What checks apply now?"],
            "limitations": [],
            "sources": [
                {
                    "evidence_id": "ev-01",
                    "chunk_id": str(uuid4()),
                    "document_id": str(uuid4()),
                    "family_id": str(uuid4()),
                    "title": "Equipment",
                    "text": text,
                    "effective_from": "2026-01-01",
                    "authority_start": "2026-01-01",
                    "authority_end_exclusive": None,
                    "status": "approved",
                    "applicability": {"scope": "universal", "locations": []},
                    "associations": [
                        {
                            "need_id": "n1",
                            "authority": "current",
                            "evaluated_at": "2026-11-16",
                            "location_id": None,
                        }
                    ],
                }
            ],
        }
    )


def settings():
    return Settings().model_copy(
        update={
            "generation_provider": "openai",
            "generation_openai_api_key": SecretStr("test-only"),
        }
    )


def output():
    return {
        "outcome": "answered",
        "answer_parts": [{"text": "Inspect weekly.", "evidence_ids": ["ev-01"]}],
        "unsupported_aspects": [],
    }


def client_for(result=None, status="completed", error=None):
    class Client:
        def __init__(self):
            self.responses = self
            self.calls = []

        def create(self, **kwargs):
            self.calls.append(kwargs)
            if error:
                raise error
            return SimpleNamespace(
                status=status,
                output_text=json.dumps(result or output()),
                usage=SimpleNamespace(
                    input_tokens=100, output_tokens=30, input_tokens_details=None
                ),
            )

    return Client()


def test_one_call_no_reviewer_question_pool_citation_membership():
    r = request()
    c = client_for()
    response = generate(r, settings(), client=c)
    assert response["status"] == "completed"
    assert len(c.calls) == 1
    assert c.calls[0]["max_output_tokens"] == 4096
    assert response["usage"]["request_count"] == 1
    assert response["usage"]["retry_count"] == 0
    assert response["usage"]["cost_usd"] > 0
    assert "full question is context" in c.calls[0]["instructions"]
    assert "untrusted data" in c.calls[0]["instructions"]
    assert "effective dates alone" in c.calls[0]["instructions"]
    assert "latest without evidence" in c.calls[0]["instructions"]


@pytest.mark.parametrize(
    "mode", ["truncated", "foreign_citation", "inconsistent", "transport"]
)
def test_failure_is_recorded_and_never_retried(mode):
    o = output()
    if mode == "foreign_citation":
        o["answer_parts"][0]["evidence_ids"] = ["ev-02"]
    if mode == "inconsistent":
        o["outcome"] = "qualified"
    c = client_for(
        o,
        status="incomplete" if mode == "truncated" else "completed",
        error=openai.APIConnectionError(
            request=httpx.Request("POST", "https://invalid.test")
        )
        if mode == "transport"
        else None,
    )
    response = generate(request(), settings(), client=c)
    assert response["status"] == "failed"
    assert len(c.calls) == 1
    assert response["usage"]["request_count"] == 1
    assert response["usage"]["retry_count"] == 0


def test_exact_source_limit_and_one_character_over_never_truncate():
    r = request("a" * 60000)
    c = client_for()
    assert generate(r, settings(), client=c)["status"] == "completed"
    assert json.loads(c.calls[0]["input"])["sources"][0]["text"] == "a" * 60000
    c = client_for()
    assert generate(request("a" * 60001), settings(), client=c)["status"] == "capacity"
    assert c.calls == []


def test_complete_request_byte_boundary_includes_schema_escaping_and_framing():
    r = request('"\\\n🧭')
    payload = render(r, settings())
    assert (
        request_units(payload)
        == len(json.dumps(payload, ensure_ascii=False, separators=(",", ":")).encode())
        + FRAMING
    )
    data = r.model_dump(mode="json")
    data["sources"][0]["title"] += "a" * (MAX_REQUEST - request_units(payload))
    exact = CurrentRequest.model_validate(data)
    assert request_units(render(exact, settings())) == MAX_REQUEST
    c = client_for()
    assert generate(exact, settings(), client=c)["status"] == "completed"
    data["sources"][0]["title"] += "x"
    c = client_for()
    assert (
        generate(CurrentRequest.model_validate(data), settings(), client=c)["status"]
        == "capacity"
    )
    assert not c.calls


def test_sdk_retries_disabled_and_timeout_bounded(monkeypatch):
    c = client_for()
    c.close = lambda: None
    captured = {}

    def factory(**kwargs):
        captured.update(kwargs)
        return c

    monkeypatch.setattr(openai, "OpenAI", factory)
    generate(
        request(),
        settings().model_copy(update={"generation_timeout_seconds": 999}),
        client=None,
    )
    assert captured["max_retries"] == 0
    assert captured["timeout"] == 120
    assert len(c.calls) == 1


def test_contract_snapshots_and_duplicate_physical_sources():
    from pathlib import Path

    for name, model in [("request", CurrentRequest), ("output", Output)]:
        assert (
            json.loads(
                Path(
                    f"/contracts/http/current-policy-answer/v1/{name}.schema.json"
                ).read_text()
            )
            == model.model_json_schema()
        )
    data = request().model_dump(mode="json")
    data["sources"].append({**data["sources"][0], "evidence_id": "ev-02"})
    with pytest.raises(ValueError, match="duplicate physical"):
        CurrentRequest.model_validate(data)


def test_signed_route_identity_replay_and_purpose_are_enforced(monkeypatch):
    import base64

    from fastapi.testclient import TestClient

    from app.main import app
    from app.settings import get_settings
    from tests.test_generation_contract import signed_request

    secret = b"p" * 32
    monkeypatch.setenv(
        "RETRIEVAL_CALLER_HMAC_KEYS",
        json.dumps({"test": base64.b64encode(secret).decode()}),
    )
    get_settings.cache_clear()
    calls = []

    def fake(incoming, configuration):
        calls.append(incoming)
        return {
            "status": "completed",
            "request_id": str(incoming.request_id),
            "result": output(),
        }

    monkeypatch.setattr("app.generation.current_policy.generate", fake)
    path = "/api/internal/retrieval/generation/current-policy-answer"
    raw = request().model_dump(mode="json")
    body, headers = signed_request(
        raw, secret, path, "generation.current-policy-answer"
    )
    try:
        client = TestClient(app)
        assert client.post(path, content=body, headers=headers).status_code == 200
        assert client.post(path, content=body, headers=headers).status_code == 401
        raw["request_id"] = str(uuid4())
        body, headers = signed_request(raw, secret, path, "generation.answer")
        assert client.post(path, content=body, headers=headers).status_code == 401
        assert len(calls) == 1
    finally:
        get_settings.cache_clear()
