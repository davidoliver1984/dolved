import json
from uuid import uuid4

import httpx
import pytest
from pydantic import SecretStr

from app.retrieval.coverage_selection import (
    CoverageProposal,
    CoverageRequest,
    CoverageSelectionError,
    CoverageSelector,
    validate_proposal,
)


def request():
    return CoverageRequest.model_validate(
        {
            "contract_version": 1,
            "request_id": str(uuid4()),
            "workspace_id": str(uuid4()),
            "timeout_seconds": 60,
            "needs": [
                {
                    "need_id": "n1",
                    "question_span": "Who inspects and when?",
                    "context": [],
                    "operation": "retrieve",
                },
                {
                    "need_id": "n2",
                    "question_span": "Who records the result?",
                    "context": [],
                    "operation": "retrieve",
                },
            ],
            "candidates": [
                {
                    "candidate_id": "p1",
                    "text": "An engineer inspects weekly and records the result.",
                    "associations": ["n1:primary", "n2:primary"],
                }
            ],
        }
    )


def test_signed_route_requires_workspace_identity_and_rejects_replay(monkeypatch):
    from fastapi.testclient import TestClient

    from app.main import app
    from tests.test_retrieval_routes import signed_headers

    calls = []

    def controlled_select(self, incoming):
        calls.append(incoming)
        return {
            "contract_version": 1,
            "request_id": str(incoming.request_id),
            "version": "grounded-coverage-selection-v1",
            "proposal": proposal(),
            "usage": {},
        }

    monkeypatch.setattr(CoverageSelector, "select", controlled_select)
    incoming = request()
    path = "/api/internal/retrieval/coverage-selection"
    body = incoming.model_dump_json().encode()
    headers = signed_headers(
        path,
        body,
        str(incoming.workspace_id),
        str(incoming.request_id),
        "retrieval.coverage-selection",
    )
    with TestClient(app) as client:
        assert client.post(path, content=body, headers=headers).status_code == 200
        assert client.post(path, content=body, headers=headers).status_code == 401
        wrong = signed_headers(
            path, body, str(uuid4()), str(uuid4()), "retrieval.coverage-selection"
        )
        assert client.post(path, content=body, headers=wrong).status_code == 401
    assert len(calls) == 1


def test_input_capacity_refuses_before_provider_attempt():
    raw = request().model_dump(mode="json")
    raw["candidates"] = [
        {**raw["candidates"][0], "candidate_id": f"p{i}", "text": "fragment " * 11000}
        for i in range(20)
    ]
    selector = CoverageSelector(
        api_url="https://provider.invalid",
        api_key=SecretStr("controlled"),
        model="controlled",
    )

    def forbidden(_):
        pytest.fail("Input overflow must not dispatch")

    selector.client = httpx.Client(transport=httpx.MockTransport(forbidden))
    with pytest.raises(CoverageSelectionError) as caught:
        selector.select(CoverageRequest.model_validate(raw))
    assert caught.value.code == "input_capacity"
    assert caught.value.usage["request_count"] == 0


def proposal():
    return {
        "aspects": [
            {
                "need_id": "n1",
                "quote": "Who inspects",
                "unmet": "none",
                "support": [
                    {
                        "candidate_id": "p1",
                        "association": "n1:primary",
                        "quote": "An engineer inspects weekly",
                    }
                ],
            },
            {
                "need_id": "n1",
                "quote": "when",
                "unmet": "none",
                "support": [
                    {
                        "candidate_id": "p1",
                        "association": "n1:primary",
                        "quote": "weekly",
                    }
                ],
            },
            {
                "need_id": "n2",
                "quote": "Who records the result?",
                "unmet": "none",
                "support": [
                    {
                        "candidate_id": "p1",
                        "association": "n2:primary",
                        "quote": "records the result",
                    }
                ],
            },
        ]
    }


def test_shared_support_is_only_proposed_and_each_association_is_checked():
    incoming = request()
    assert (
        len(
            validate_proposal(
                incoming, CoverageProposal.model_validate(proposal())
            ).aspects
        )
        == 3
    )
    raw = proposal()
    raw["aspects"][2]["support"][0]["association"] = "n1:primary"
    with pytest.raises(ValueError, match="another need"):
        validate_proposal(incoming, CoverageProposal.model_validate(raw))


@pytest.mark.parametrize("mutation", ["omit", "invent", "quote", "aspect"])
def test_invalid_proposals_are_not_consumed(mutation):
    raw = proposal()
    if mutation == "omit":
        raw["aspects"].pop()
    elif mutation == "invent":
        raw["aspects"][0]["support"][0]["candidate_id"] = "foreign"
    elif mutation == "quote":
        raw["aspects"][0]["support"][0]["quote"] = "A manager inspects daily"
    else:
        raw["aspects"][0]["quote"] = "Which colour is it?"
    with pytest.raises(ValueError):
        validate_proposal(request(), CoverageProposal.model_validate(raw))


@pytest.mark.parametrize(
    "case,code",
    [
        ("timeout", "service_timeout"),
        ("transport", "service_unavailable"),
        (401, "provider_rejected"),
        (503, "service_unavailable"),
        (429, "provider_rejected"),
        ("truncated", "incomplete_output"),
        ("invalid", "invalid_output"),
    ],
)
def test_one_attempt_and_typed_failure_accounting(case, code):
    calls = []

    def handle(sent):
        calls.append(sent)
        if case == "timeout":
            raise httpx.ReadTimeout("private details not retained")
        if case == "transport":
            raise httpx.ConnectError("private details not retained")
        if isinstance(case, int):
            return httpx.Response(case)
        return httpx.Response(
            200,
            json={
                "choices": [
                    {
                        "finish_reason": "length" if case == "truncated" else "stop",
                        "message": {"content": "{}"},
                    }
                ],
                "usage": {"prompt_tokens": 200, "completion_tokens": 4096},
            },
        )

    selector = CoverageSelector(
        api_url="https://provider.invalid",
        api_key=SecretStr("fake"),
        model="gpt-5-mini",
    )
    selector.client = httpx.Client(transport=httpx.MockTransport(handle))
    with pytest.raises(CoverageSelectionError) as caught:
        selector.select(request())
    assert caught.value.code == code
    assert caught.value.usage["request_count"] == len(calls) == 1
    assert caught.value.usage["retry_count"] == 0
    assert "private" not in str(caught.value)


def test_legitimate_no_support_and_complete_proposal():
    raw = proposal()
    raw["aspects"][0].update(support=[], unmet="no_support")
    calls = []

    def handle(sent):
        calls.append(json.loads(sent.content))
        return httpx.Response(
            200,
            json={
                "choices": [
                    {"finish_reason": "stop", "message": {"content": json.dumps(raw)}}
                ],
                "usage": {"prompt_tokens": 200, "completion_tokens": 500},
            },
        )

    selector = CoverageSelector(
        api_url="https://provider.invalid",
        api_key=SecretStr("fake"),
        model="gpt-5-mini",
    )
    selector.client = httpx.Client(transport=httpx.MockTransport(handle))
    result = selector.select(request())
    assert result["proposal"]["aspects"][0]["unmet"] == "no_support"
    assert calls[0]["max_completion_tokens"] == 4096
    assert result["usage"]["request_count"] == 1
