import json
from copy import deepcopy

import httpx
import pytest
from pydantic import SecretStr

from app.retrieval.needs_planner import (
    NeedsValidationFailure,
    StructuredChatNeedsPlanner,
)
from tests.test_evidence_needs import EXAMPLES, turns


@pytest.mark.parametrize("second_valid", [True, False])
def test_one_identical_validation_retry_with_both_receipts(second_valid):
    good = deepcopy(EXAMPLES[0]["plan"])
    bad = deepcopy(good)
    bad["bindings"] = []
    requests = []

    def handle(request):
        requests.append(request.content)
        plan = good if len(requests) == 2 and second_valid else bad
        return httpx.Response(
            200,
            json={
                "choices": [
                    {"finish_reason": "stop", "message": {"content": json.dumps(plan)}}
                ],
                "usage": {"prompt_tokens": 100, "completion_tokens": 20},
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
    if second_valid:
        result = planner.plan_needs(
            turns(EXAMPLES[0]), evaluated_at="2026-09-07T12:00:00Z"
        )
        assert result.usage.request_count == 2
        assert result.usage.retry_count == 1
        assert result.usage.input_tokens == 200
        assert result.usage.output_tokens == 40
        assert len(result.usage.validation_attempts) == 2
    else:
        with pytest.raises(NeedsValidationFailure) as caught:
            planner.plan_needs(turns(EXAMPLES[0]), evaluated_at="2026-09-07T12:00:00Z")
        assert caught.value.attempt_count == 2
        assert len(caught.value.validation_attempts) == 2
    assert len(requests) == 2
    assert requests[0] == requests[1]


def test_correct_clarification_is_not_retried():
    from tests.test_evidence_needs import adapter

    calls = []
    result = adapter(
        {
            "schema_version": "evidence-needs-plan-v2",
            "decision": "clarify",
            "clarification": "Which period?",
            "needs": [],
            "bindings": [],
        },
        calls,
    ).plan_needs(turns(EXAMPLES[0]), evaluated_at="2026-09-07T12:00:00Z")
    assert result.plan.decision == "clarify"
    assert len(calls) == 1


@pytest.mark.parametrize(
    "category,status,stage,code,field,reason,expected",
    [
        (
            "invalid_grounded_needs_plan",
            200,
            "catalogue",
            "binding_coverage",
            "root",
            "stop",
            2,
        ),
        (
            "invalid_grounded_needs_plan",
            200,
            "schema",
            "json_invalid",
            "root",
            "stop",
            2,
        ),
        (
            "invalid_grounded_needs_plan",
            200,
            "grounding",
            "reference_grounding",
            "root",
            "stop",
            1,
        ),
        (
            "invalid_grounded_needs_plan",
            200,
            "schema",
            "fact_date_precision",
            "needs.0.fact_reference",
            "stop",
            1,
        ),
        (
            "invalid_grounded_needs_plan",
            200,
            "completion",
            "output_truncated",
            "root",
            "length",
            1,
        ),
        (
            "invalid_grounded_needs_plan",
            200,
            "envelope",
            "invalid_value",
            "root",
            "unknown",
            1,
        ),
        ("transport_error", None, None, None, None, None, 1),
        ("rate_limit", 429, None, None, None, None, 1),
    ],
)
def test_retry_trigger_excludes_non_output_and_graceful_failures(
    monkeypatch, category, status, stage, code, field, reason, expected
):
    from app.retrieval.planner import RetrievalPlanningError

    planner = StructuredChatNeedsPlanner(
        api_url="https://not-dispatched.invalid",
        api_key=SecretStr("fake"),
        provider_name="openai",
        model="gpt-5-mini",
        timeout_seconds=1,
    )
    calls = []

    def fail(*args, **kwargs):
        calls.append((args, kwargs))
        error = (
            NeedsValidationFailure(stage, ValueError())
            if stage
            else RetrievalPlanningError(
                "synthetic", category=category, provider_status=status
            )
        )
        if stage:
            error.validation = {"stage": stage, "field": field, "code": code}
            error.completion = {
                "finish_reason": reason,
                "input_tokens": 100,
                "output_tokens": 20,
            }
        raise error

    monkeypatch.setattr(planner, "_plan_needs_once", fail)
    with pytest.raises(RetrievalPlanningError) as caught:
        planner.plan_needs(turns(EXAMPLES[0]), evaluated_at="2026-09-07T12:00:00Z")
    assert len(calls) == expected
    assert len(caught.value.validation_attempts) == expected
    if expected == 2:
        assert calls[0] == calls[1]


def test_second_transport_failure_preserves_first_validation_receipt(monkeypatch):
    from app.retrieval.planner import RetrievalPlanningError

    planner = StructuredChatNeedsPlanner(
        api_url="https://not-dispatched.invalid",
        api_key=SecretStr("fake"),
        provider_name="openai",
        model="gpt-5-mini",
        timeout_seconds=1,
    )
    calls = []

    def fail(*args, **kwargs):
        calls.append(1)
        if len(calls) == 1:
            error = NeedsValidationFailure(
                "catalogue",
                ValueError("every need requires exactly one catalogue binding"),
            )
            error.completion = {
                "finish_reason": "stop",
                "input_tokens": 100,
                "output_tokens": 20,
            }
            raise error
        raise RetrievalPlanningError("synthetic transport", category="transport_error")

    monkeypatch.setattr(planner, "_plan_needs_once", fail)
    with pytest.raises(RetrievalPlanningError) as caught:
        planner.plan_needs(turns(EXAMPLES[0]), evaluated_at="2026-09-07T12:00:00Z")
    assert len(calls) == 2
    assert (
        caught.value.validation_attempts[0]["validation"]["code"] == "binding_coverage"
    )
    assert caught.value.validation_attempts[0]["input_tokens"] == 100
    assert caught.value.validation_attempts[1]["input_tokens"] is None
