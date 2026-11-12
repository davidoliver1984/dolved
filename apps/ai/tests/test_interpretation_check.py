import json
from copy import deepcopy

import httpx
import pytest
from pydantic import SecretStr

from app.retrieval.interpretation import InterpretationCheckError, review_interpretation
from app.retrieval.needs import EvidenceNeedsPlan
from app.retrieval.needs_planner import (
    NeedsValidationFailure,
    StructuredChatNeedsPlanner,
)
from tests.test_evidence_needs import EXAMPLES, turns


def transport(reviews):
    calls = []
    pending = iter(reviews)

    def handle(request):
        body = json.loads(request.content)
        calls.append(body)
        checking = (
            body["response_format"]["json_schema"]["name"] == "interpretation_check"
        )
        content = next(pending) if checking else EXAMPLES[0]["plan"]
        if isinstance(content, Exception):
            raise content
        return httpx.Response(
            200,
            json={
                "choices": [
                    {
                        "finish_reason": "stop",
                        "message": {"content": json.dumps(content)},
                    }
                ],
                "usage": {
                    "prompt_tokens": 11 if checking else 100,
                    "completion_tokens": 3 if checking else 20,
                },
            },
        )

    return httpx.Client(transport=httpx.MockTransport(handle)), calls


@pytest.mark.parametrize("code", ["clause_omitted", "meaning_changed", "scope_added"])
@pytest.mark.parametrize("corrected", [True, False])
def test_closed_feedback_two_total_attempts_and_complete_usage(code, corrected):
    client, calls = transport(
        [{"code": code}, {"code": "accepted" if corrected else code}]
    )
    planner = StructuredChatNeedsPlanner(
        api_url="https://fake.invalid",
        api_key=SecretStr("fake"),
        provider_name="openai",
        model="gpt-5-mini",
        timeout_seconds=1,
        client=client,
    )
    if corrected:
        result = planner.plan_needs(
            turns(EXAMPLES[0]), evaluated_at="2026-11-12T12:00:00Z"
        )
        assert result.usage.request_count == 4
        assert result.usage.retry_count == 1
        assert result.usage.input_tokens == 222
        assert result.usage.output_tokens == 46
        receipts = result.usage.validation_attempts
    else:
        with pytest.raises(NeedsValidationFailure) as caught:
            planner.plan_needs(turns(EXAMPLES[0]), evaluated_at="2026-11-12T12:00:00Z")
        assert caught.value.attempt_count == 2
        assert caught.value.completion["finish_reason"] == "stop"
        assert caught.value.provider_status == 200
        receipts = caught.value.validation_attempts
    assert len(calls) == 4
    for request in (calls[1], calls[3]):
        assert set(json.loads(request["messages"][1]["content"])) == {
            "user_turns",
            "plan",
        }
        assert request["max_completion_tokens"] == 4096
    feedback = json.loads(calls[2]["messages"][1]["content"])["validation_feedback"]
    assert feedback == {"stage": "interpretation", "field": "root", "code": code}
    assert receipts[0]["interpretation_check"]["code"] == code
    assert receipts[0]["interpretation_check"]["input_tokens"] == 11
    assert receipts[0]["interpretation_check"]["latency_ms"] >= 0


@pytest.mark.parametrize(
    "bad",
    [
        {"code": "accepted", "prose": "injected"},
        {"code": "invented"},
        httpx.ConnectError("private"),
    ],
)
def test_check_has_no_retry_and_never_returns_provider_prose(bad):
    client, calls = transport([bad])
    with pytest.raises(InterpretationCheckError) as caught:
        review_interpretation(
            client=client,
            api_url="https://fake.invalid",
            api_key=SecretStr("fake"),
            model="gpt-5-mini",
            provider="openai",
            turns=turns(EXAMPLES[0]),
            plan=EvidenceNeedsPlan.model_validate(EXAMPLES[0]["plan"]),
        )
    assert len(calls) == 1
    assert caught.value.receipt["code"] == "unavailable"
    assert "private" not in str(caught.value)
    assert "prose" not in json.dumps(caught.value.receipt)


@pytest.mark.parametrize("whole_span", [False, True])
@pytest.mark.parametrize("attempt", [1, 2])
def test_four_omission_shapes_cannot_execute_when_check_rejects(whole_span, attempt):
    # Semantic detection itself is measured against these exact plans at the live
    # checkpoint; this test proves rejection cannot fall through to execution.
    from tests.test_needs_correction_feedback import invalid_plan

    question = "How often were highest-risk residents repositioned before January 2026, and what interval applies after the change?"
    plan = deepcopy(EXAMPLES[0]["plan"])
    need = deepcopy(plan["needs"][1])
    need.update(
        need_id="n1",
        supports_need_id=None,
        aspect="What interval applies after the change?",
        question_span={
            "turn_id": "u1",
            "quote": question
            if whole_span
            else "what interval applies after the change?",
        },
    )
    plan.update(needs=[need], bindings=[{**plan["bindings"][1], "need_id": "n1"}])
    from app.retrieval.needs import UserTurn

    calls = []

    def handle(request):
        body = json.loads(request.content)
        calls.append(body)
        check = body["response_format"]["json_schema"]["name"] == "interpretation_check"
        output = (
            {"code": "clause_omitted"}
            if check
            else (invalid_plan() if attempt == 2 and len(calls) == 1 else plan)
        )
        return httpx.Response(
            200,
            json={
                "choices": [
                    {
                        "finish_reason": "stop",
                        "message": {"content": json.dumps(output)},
                    }
                ]
            },
        )

    planner = StructuredChatNeedsPlanner(
        api_url="https://fake.invalid",
        api_key=SecretStr("fake"),
        provider_name="openai",
        model="gpt-5-mini",
        timeout_seconds=1,
        client=httpx.Client(transport=httpx.MockTransport(handle)),
    )
    with pytest.raises(NeedsValidationFailure) as caught:
        planner.plan_needs(
            (UserTurn(turn_id="u1", text=question),),
            evaluated_at="2026-11-12T12:00:00Z",
        )
    assert caught.value.attempt_count == 2
    assert caught.value.validation["code"] == "clause_omitted"
    assert (
        sum(
            c["response_format"]["json_schema"]["name"] == "evidence_needs_plan"
            for c in calls
        )
        == 2
    )
