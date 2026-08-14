import json
from collections.abc import Callable
from typing import Any

import httpx
import pytest
from jsonschema import Draft202012Validator
from pydantic import SecretStr

from app.retrieval.models import RetrievalPlan
from app.retrieval.planner import (
    RetrievalPlanningError,
    StructuredChatRetrievalPlanner,
    _provider_plan_schema,
    _strict_response_schema,
)


def provider_envelope(response_plan: dict[str, object]) -> dict[str, object]:
    if "intent" in response_plan:
        return response_plan
    intent_keys = {
        "temporal_mode",
        "explicit_date",
        "temporal_reference",
        "clarification_reason",
    }
    return {
        **(
            {"retrieval_queries": response_plan["retrieval_queries"]}
            if "retrieval_queries" in response_plan
            else {}
        ),
        "intent": {
            key: response_plan[key] for key in intent_keys if key in response_plan
        },
        **(
            {"location_references": response_plan["location_references"]}
            if "location_references" in response_plan
            else {}
        ),
    }


def planner_with_handler(
    handler: Callable[[httpx.Request], httpx.Response],
) -> StructuredChatRetrievalPlanner:
    return StructuredChatRetrievalPlanner(
        api_url="https://planner.invalid/v1/chat/completions",
        api_key=SecretStr("test-key"),
        provider_name="test-provider",
        model="test-model",
        timeout_seconds=1,
        client=httpx.Client(transport=httpx.MockTransport(handler)),
    )


def planner(response_plan: dict[str, object]) -> StructuredChatRetrievalPlanner:
    def handler(request: httpx.Request) -> httpx.Response:
        request_payload = json.loads(request.content)
        assert request_payload["response_format"]["type"] == "json_schema"
        return httpx.Response(
            200,
            json={
                "choices": [
                    {
                        "message": {
                            "content": json.dumps(provider_envelope(response_plan))
                        }
                    }
                ],
                "usage": {
                    "prompt_tokens": 31,
                    "prompt_tokens_details": {"cached_tokens": 7},
                    "completion_tokens": 13,
                },
            },
        )

    return planner_with_handler(handler)


def test_structured_planner_validates_typed_output_and_preserves_query() -> None:
    question = "What was valid on 1 January 2026?"
    plan = planner(
        {
            "retrieval_queries": [question],
            "temporal_mode": "valid_at_date",
            "explicit_date": "2026-01-01",
            "temporal_reference": None,
            "location_references": [],
            "clarification_reason": None,
        }
    ).plan(question, evaluated_at="2026-08-07T12:00:00Z")

    assert str(plan.explicit_date) == "2026-01-01"
    assert plan.retrieval_queries == (question,)


@pytest.mark.parametrize(
    ("question", "explicit_date"),
    [
        ("Which notification timescale applied on 1 December 2024?", "2024-12-01"),
        (
            "On 15 June 2024, what did staff have to record when a medicine was not given?",
            "2024-06-15",
        ),
    ],
)
def test_cal_exp_0002_exact_date_plan_shapes_are_valid(
    question: str, explicit_date: str
) -> None:
    result = planner(
        {
            "retrieval_queries": [question],
            "temporal_mode": "valid_at_date",
            "explicit_date": explicit_date,
            "temporal_reference": None,
            "location_references": [],
            "clarification_reason": None,
        }
    ).plan(question, evaluated_at="2026-08-14T12:00:00Z")

    assert result.temporal_mode.value == "valid_at_date"
    assert str(result.explicit_date) == explicit_date


def test_structured_planner_rejects_malformed_or_changed_query_output() -> None:
    with pytest.raises(RetrievalPlanningError) as malformed:
        planner({"temporal_mode": "valid_at_date"}).plan(
            "Question", evaluated_at="2026-08-07T12:00:00Z"
        )
    assert malformed.value.category == "schema_validation_failure"

    with pytest.raises(RetrievalPlanningError, match="altered"):
        planner(
            {
                "retrieval_queries": ["Changed"],
                "temporal_mode": "current",
                "explicit_date": None,
                "temporal_reference": None,
                "location_references": [],
                "clarification_reason": None,
            }
        ).plan("Original", evaluated_at="2026-08-07T12:00:00Z")


def test_provider_schema_requires_every_nullable_property_without_defaults() -> None:
    schema = _strict_response_schema(RetrievalPlan.model_json_schema())

    assert schema["required"] == list(schema["properties"])
    reference = schema["$defs"]["TemporalReference"]
    assert reference["required"] == list(reference["properties"])

    def assert_no_defaults(value: object) -> None:
        if isinstance(value, dict):
            assert "default" not in value
            for item in value.values():
                assert_no_defaults(item)
        elif isinstance(value, list):
            for item in value:
                assert_no_defaults(item)

    assert_no_defaults(schema)


def test_provider_schema_encodes_every_valid_temporal_combination() -> None:
    schema = _provider_plan_schema()
    validator = Draft202012Validator(schema)
    question = "Question"

    valid = [
        provider_envelope(
            {
                "retrieval_queries": [question],
                "temporal_mode": "current",
                "explicit_date": None,
                "temporal_reference": None,
                "location_references": [],
                "clarification_reason": None,
            }
        ),
        provider_envelope(
            {
                "retrieval_queries": [question],
                "temporal_mode": "valid_at_date",
                "explicit_date": "2026-01-01",
                "temporal_reference": None,
                "location_references": [],
                "clarification_reason": None,
            }
        ),
        provider_envelope(
            {
                "retrieval_queries": [question],
                "temporal_mode": "valid_at_date",
                "explicit_date": None,
                "temporal_reference": {
                    "kind": "calendar_period",
                    "value": "January 2026",
                },
                "location_references": [],
                "clarification_reason": None,
            }
        ),
    ]

    assert all(validator.is_valid(value) for value in valid)


@pytest.mark.parametrize(
    "value",
    [
        {
            "retrieval_queries": ["Question"],
            "temporal_mode": "valid_at_date",
            "explicit_date": None,
            "temporal_reference": None,
            "location_references": [],
            "clarification_reason": None,
        },
        {
            "retrieval_queries": ["Question"],
            "temporal_mode": "valid_at_date",
            "explicit_date": "2026-01-01",
            "temporal_reference": {
                "kind": "calendar_period",
                "value": "January 2026",
            },
            "location_references": [],
            "clarification_reason": None,
        },
        {
            "retrieval_queries": ["Question"],
            "temporal_mode": "current",
            "explicit_date": "2026-01-01",
            "temporal_reference": None,
            "location_references": [],
            "clarification_reason": None,
        },
    ],
)
def test_provider_schema_rejects_invalid_temporal_combinations(
    value: dict[str, object],
) -> None:
    assert not Draft202012Validator(_provider_plan_schema()).is_valid(
        provider_envelope(value)
    )


def test_planner_lineage_is_stable_and_excludes_credentials() -> None:
    adapter = planner({})
    lineage = adapter.lineage()

    assert lineage.provider == "test-provider"
    assert lineage.contract_schema_version == "plan-response-v2"
    assert lineage.prompt_version == "adr-0022-v2"
    assert lineage.adapter_version == "structured-chat-v3"
    assert len(lineage.fingerprint) == 64
    assert "test-key" not in lineage.model_dump_json()


@pytest.mark.parametrize(
    ("field", "value"),
    [
        ("provider_name", "another-provider"),
        ("model", "another-model"),
        ("contract_schema_version", "plan-response-v3"),
        ("prompt_version", "adr-0022-v3"),
        ("adapter_version", "structured-chat-v4"),
    ],
)
def test_planner_lineage_fingerprint_changes_for_each_semantic_component(
    field: str, value: str
) -> None:
    common: dict[str, Any] = {
        "api_url": "https://planner.invalid/v1/chat/completions",
        "api_key": SecretStr("test-key"),
        "provider_name": "provider",
        "model": "model",
        "timeout_seconds": 1,
        "client": httpx.Client(
            transport=httpx.MockTransport(lambda _: httpx.Response(200))
        ),
    }
    baseline = StructuredChatRetrievalPlanner(**common).lineage().fingerprint
    changed = (
        StructuredChatRetrievalPlanner(**(common | {field: value}))
        .lineage()
        .fingerprint
    )

    assert changed != baseline


@pytest.mark.parametrize(
    ("mode", "selector"),
    [
        ("current", {}),
        ("compare", {}),
        ("valid_at_date", {"explicit_date": "2026-01-01"}),
        (
            "valid_at_date",
            {
                "temporal_reference": {
                    "kind": "calendar_period",
                    "value": "January 2026",
                }
            },
        ),
        (
            "historical_reference",
            {
                "temporal_reference": {
                    "kind": "historical_reference",
                    "value": "version 1",
                }
            },
        ),
        (
            "clarification_required",
            {"clarification_reason": "unclassifiable_temporal_intent"},
        ),
    ],
)
def test_structured_planner_accepts_every_adr_0022_mode(
    mode: str, selector: dict[str, object]
) -> None:
    question = "Question"
    value = {
        "retrieval_queries": [question],
        "temporal_mode": mode,
        "explicit_date": None,
        "temporal_reference": None,
        "location_references": [],
        "clarification_reason": None,
        **selector,
    }

    result = planner(value).plan_with_observation(
        question, evaluated_at="2026-08-07T12:00:00Z"
    )

    assert result.plan.temporal_mode.value == mode
    assert result.usage.request_count == 1
    assert result.usage.input_tokens == 31
    assert result.usage.cached_input_tokens == 7
    assert result.usage.output_tokens == 13
    assert result.usage.cost_basis == "unavailable"


def test_prompt_keeps_ordinary_contrast_current_and_forbids_manufactured_dates() -> (
    None
):
    from app.retrieval.planner import _planner_prompt

    prompt = _planner_prompt("2026-08-07T12:00:00Z")
    assert "current values, options" in prompt
    assert "not COMPARE" in prompt
    assert "Never manufacture a day" in prompt
    assert "second model" not in prompt.lower()


def test_prompt_keeps_equipment_out_of_location_references() -> None:
    from app.retrieval.planner import _planner_prompt

    prompt = _planner_prompt("2026-08-14T12:00:00Z")

    assert "equipment, storage" in prompt
    assert "including a fridge" in prompt
    assert "not location references" in prompt


def test_prompt_treats_predecessor_resurrection_question_as_current() -> None:
    from app.retrieval.planner import _planner_prompt

    prompt = _planner_prompt("2026-08-14T12:00:00Z")

    assert "became current again" in prompt
    assert "asks about current authority" in prompt
    assert "explicitly asks to compare the content" in prompt


def test_planner_classifies_response_shape_failure_without_retaining_payload() -> None:
    private_value = "private-provider-value"
    adapter = planner_with_handler(
        lambda _: httpx.Response(200, json={"unexpected": private_value})
    )

    with pytest.raises(RetrievalPlanningError) as captured:
        adapter.plan("Question", evaluated_at="2026-08-07T12:00:00Z")

    assert captured.value.category == "response_shape_failure"
    assert private_value not in str(captured.value)
    assert captured.value.__cause__ is None


def test_planner_classifies_json_decode_failure_without_retaining_content() -> None:
    private_value = "{private-invalid-json"
    adapter = planner_with_handler(
        lambda _: httpx.Response(
            200,
            json={"choices": [{"message": {"content": private_value}}]},
        )
    )

    with pytest.raises(RetrievalPlanningError) as captured:
        adapter.plan("Question", evaluated_at="2026-08-07T12:00:00Z")

    assert captured.value.category == "json_decode_failure"
    assert private_value not in str(captured.value)
    assert captured.value.__cause__ is None


def test_planner_classifies_schema_validation_failure() -> None:
    with pytest.raises(RetrievalPlanningError) as captured:
        planner({"temporal_mode": "valid_at_date"}).plan(
            "Question", evaluated_at="2026-08-07T12:00:00Z"
        )

    assert captured.value.category == "schema_validation_failure"


def test_planner_classifies_field_validation_failure() -> None:
    with pytest.raises(RetrievalPlanningError) as captured:
        planner(
            {
                "retrieval_queries": ["Question"],
                "temporal_mode": "unsupported",
                "explicit_date": None,
                "temporal_reference": None,
                "location_references": [],
                "clarification_reason": None,
            }
        ).plan("Question", evaluated_at="2026-08-07T12:00:00Z")

    assert captured.value.category == "field_validation_failure"


def test_planner_classifies_cross_field_validation_failure() -> None:
    with pytest.raises(RetrievalPlanningError) as captured:
        planner(
            {
                "retrieval_queries": ["Question"],
                "temporal_mode": "valid_at_date",
                "explicit_date": "2026-01-01",
                "temporal_reference": {
                    "kind": "calendar_period",
                    "value": "January 2026",
                },
                "location_references": [],
                "clarification_reason": None,
            }
        ).plan("Question", evaluated_at="2026-08-07T12:00:00Z")

    assert captured.value.category == "cross_field_validation_failure"
    assert captured.value.__cause__ is None


def test_planner_classifies_quota_as_systemic_without_leaking_provider_body() -> None:
    def handler(request: httpx.Request) -> httpx.Response:
        return httpx.Response(
            429,
            json={
                "error": {
                    "code": "credit_balance_exhausted",
                    "message": "private provider body",
                }
            },
            request=request,
        )

    adapter = StructuredChatRetrievalPlanner(
        api_url="https://planner.invalid/v1/chat/completions",
        api_key=SecretStr("test-key"),
        provider_name="test-provider",
        model="test-model",
        timeout_seconds=1,
        client=httpx.Client(transport=httpx.MockTransport(handler)),
    )

    with pytest.raises(RetrievalPlanningError) as captured:
        adapter.plan("Question", evaluated_at="2026-08-07T12:00:00Z")

    assert captured.value.category == "provider_quota"
    assert captured.value.provider_status == 429
    assert captured.value.systemic is True
    assert "private provider body" not in str(captured.value)
