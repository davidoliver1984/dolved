"""Bounded feedback and the explicitly retained semantic-coverage limitation."""

import json
from copy import deepcopy

import httpx
import pytest
from pydantic import SecretStr

from app.retrieval.needs import UserTurn
from app.retrieval.needs_planner import (
    NeedsValidationFailure,
    StructuredChatNeedsPlanner,
)
from app.retrieval.planner import RetrievalPlanningError
from tests.test_evidence_needs import EXAMPLES, turns


def run_outputs(outputs, user_turns=None):
    calls = []

    def handle(request):
        calls.append(json.loads(request.content))
        output = outputs[min(len(calls) - 1, len(outputs) - 1)]
        if isinstance(output, Exception):
            raise output
        return httpx.Response(
            200,
            json={
                "choices": [
                    {
                        "finish_reason": "stop",
                        "message": {"content": json.dumps(output)},
                    }
                ],
                "usage": {"prompt_tokens": 120, "completion_tokens": 90},
            },
        )

    planner = StructuredChatNeedsPlanner(
        api_url="https://not-dispatched.invalid",
        api_key=SecretStr("fake"),
        provider_name="openai",
        model="gpt-5-mini",
        timeout_seconds=1,
        client=httpx.Client(transport=httpx.MockTransport(handle)),
    )
    return planner, calls, user_turns or turns(EXAMPLES[0])


def invalid_plan():
    plan = deepcopy(EXAMPLES[0]["plan"])
    plan["needs"][0]["authority"] = {
        "kind": "period",
        "precision": "month",
        "value": "2026-01",
        "source": {"turn_id": "u1", "quote": "before January 2026"},
    }
    plan["needs"][0]["aspect"] = "REJECTED_OUTPUT_MUST_NOT_RETURN"
    return plan


def test_feedback_has_only_bounded_diagnostics_and_original_inputs():
    complete = EXAMPLES[0]["plan"]
    planner, calls, user_turns = run_outputs([invalid_plan(), complete])
    result = planner.plan_needs(user_turns, evaluated_at="2026-11-12T12:00:00Z")
    assert len(calls) == 2
    first, second = (json.loads(c["messages"][1]["content"]) for c in calls)
    assert "validation_feedback" not in first
    assert second.pop("validation_feedback") == {
        "stage": "schema",
        "field": "needs.0.authority",
        "code": "temporal_relation_grounding",
    }
    assert second == first
    assert [m["role"] for m in calls[1]["messages"]] == ["system", "user"]
    assert "REJECTED_OUTPUT_MUST_NOT_RETURN" not in json.dumps(calls[1])
    assert "Preserve every requested part" in calls[1]["messages"][0]["content"]
    assert calls[0]["response_format"] == calls[1]["response_format"]
    assert result.plan.model_dump(mode="json") == complete
    assert (
        len(result.plan.needs) == 2
    )  # Complete scripted correction, not a model guarantee.


def test_two_invalid_completed_outputs_still_exhaust_at_two():
    planner, calls, user_turns = run_outputs([invalid_plan()])
    with pytest.raises(NeedsValidationFailure) as caught:
        planner.plan_needs(user_turns, evaluated_at="2026-11-12T12:00:00Z")
    assert len(calls) == 2
    assert [r["validation"]["code"] for r in caught.value.validation_attempts] == [
        "temporal_relation_grounding",
        "temporal_relation_grounding",
    ]


def test_transport_failure_does_not_acquire_a_correction_retry():
    planner, calls, user_turns = run_outputs([httpx.ConnectError("transport")])
    with pytest.raises(RetrievalPlanningError) as caught:
        planner.plan_needs(user_turns, evaluated_at="2026-11-12T12:00:00Z")
    assert caught.value.category == "transport_error"
    assert len(calls) == 1


@pytest.mark.parametrize("whole_span", [False, True])
@pytest.mark.parametrize("after_rejection", [False, True])
def test_known_coverage_limit_applies_on_any_attempt(whole_span, after_rejection):
    """Accepted limitation: schema-valid is not a semantic coverage pass."""
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
    outputs = [invalid_plan(), plan] if after_rejection else [plan]
    planner, calls, user_turns = run_outputs(
        outputs, (UserTurn(turn_id="u1", text=question),)
    )
    result = planner.plan_needs(user_turns, evaluated_at="2026-11-12T12:00:00Z")
    assert len(calls) == (2 if after_rejection else 1)
    assert result.plan.needs[0].aspect == "What interval applies after the change?"
    # Independent checkpoint review must fail this: before-January part disappeared.
    assert not any("before" in n.aspect.lower() for n in result.plan.needs)


def test_exception_input_and_message_never_enter_feedback():
    plan = deepcopy(EXAMPLES[0]["plan"])
    plan["needs"][0]["authority"]["kind"] = "RAW_UNTRUSTED_SECRET"
    planner, calls, user_turns = run_outputs([plan, EXAMPLES[0]["plan"]])
    planner.plan_needs(user_turns, evaluated_at="2026-11-12T12:00:00Z")
    feedback = json.loads(calls[1]["messages"][1]["content"])["validation_feedback"]
    assert feedback == {
        "stage": "schema",
        "field": "needs.0.authority.kind",
        "code": "literal_error",
    }
    assert "RAW_UNTRUSTED_SECRET" not in json.dumps(calls[1])
