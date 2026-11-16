import json
from copy import deepcopy
from pathlib import Path

import pytest

from app.retrieval.current_policy import UserTurn, dispositions, parse_policy_plan
from app.retrieval.current_policy_planner import policy_schema

FIXTURE = json.loads(
    Path("/contracts/http/current-policy-needs/v1/examples.json").read_text()
)


def parse(value=None, recover=False):
    return parse_policy_plan(
        value or deepcopy(FIXTURE["plan"]),
        tuple(UserTurn(**t) for t in FIXTURE["turns"]),
        (),
        "2026-11-16T12:00:00Z",
        recover=recover,
    )


def test_mixed_partition_retains_meaning_for_check_and_declines_history():
    plan = parse()
    assert plan.needs[0].authority.kind == "previous"
    assert "what changed" in plan.execution_plan().needs[0].aspect
    assert dispositions(plan)[0]["status"] == "partial_scope"
    assert (
        plan.in_question_order(tuple(UserTurn(**t) for t in FIXTURE["turns"]))
        .needs[0]
        .current_spans
    )


@pytest.mark.parametrize(
    "mutation", ["missing", "overlap", "ungrounded", "duplicate", "authority"]
)
def test_bad_annotations_retry_then_quarantine_not_current(mutation):
    value = deepcopy(FIXTURE["plan"])
    n = value["needs"][0]
    if mutation == "missing":
        del n["requested_information"]
    elif mutation == "overlap":
        n["history_spans"] = n["current_spans"]
    elif mutation == "ungrounded":
        n["current_spans"][0]["quote"] = "Invented current question"
    elif mutation == "duplicate":
        n["current_spans"] *= 2
    else:
        n.update(
            requested_information="current_policy", current_spans=[], history_spans=[]
        )
    with pytest.raises(ValueError):
        parse(value)
    result = parse(value, recover=True)
    assert result.needs[0].requested_information is None
    assert dispositions(result)[0]["status"] == "interpretation_failure"
    assert dispositions(result)[0]["affected_spans"] == [
        value["needs"][0]["question_span"]
    ]


@pytest.mark.parametrize(
    "mutation", ["binding", "base", "grounding", "cycle", "forged_disposition"]
)
def test_base_and_integrity_failures_never_recover(mutation):
    value = deepcopy(FIXTURE["plan"])
    if mutation == "binding":
        value["bindings"][0].update(
            subject_status="matched",
            family_ids=["11111111-1111-4111-8111-111111111111"],
        )
    elif mutation == "base":
        del value["needs"][0]["authority"]
    elif mutation == "grounding":
        value["needs"][0]["question_span"]["quote"] = "Not in user turn"
    elif mutation == "cycle":
        value["needs"][0]["supports_need_id"] = "n1"
    else:
        value["needs"][0]["scope_error"] = "invalid_scope"
    with pytest.raises(ValueError):
        parse(value, recover=True)


@pytest.mark.parametrize(
    "question",
    [
        "A battery failed on 5 March 2024. What inspection is required now?",
        "What does the February fire-door inspection log record?",
        "Which checks apply before my visit next week?",
    ],
)
def test_dated_current_and_event_records_remain_admitted(question):
    value = deepcopy(FIXTURE["plan"])
    value["needs"][0].update(
        question_span={"turn_id": "u1", "quote": question},
        operation="retrieve",
        authority={
            "kind": "current",
            "value": None,
            "precision": "none",
            "source": None,
        },
        requested_information="current_policy",
        current_spans=[],
        history_spans=[],
    )
    result = parse_policy_plan(
        value,
        (UserTurn(turn_id="u1", text=question),),
        (),
        "2026-11-16T12:00:00Z",
        recover=False,
    )
    assert dispositions(result)[0]["status"] == "admitted"


@pytest.mark.parametrize(
    "kind,status", [("history", "scope_declined"), ("unclear", "ambiguity")]
)
def test_non_current_dispositions_are_not_evidence_absence(kind, status):
    value = deepcopy(FIXTURE["plan"])
    value["needs"][0].update(
        requested_information=kind, current_spans=[], history_spans=[]
    )
    assert dispositions(parse(value))[0]["status"] == status
    assert dispositions(parse(value))[0]["affected_spans"] == [
        value["needs"][0]["question_span"]
    ]


def test_malformed_scope_quarantines_dependent_but_keeps_independent_base_disclosures():
    value = deepcopy(FIXTURE["plan"])
    value["needs"][0]["current_spans"] = [
        {"turn_id": "u1", "quote": "invented annotation"}
    ]
    turns = [UserTurn(**t) for t in FIXTURE["turns"]]
    for index, question in [
        (2, "Who approves that choice?"),
        (3, "Where are replacement badges requested?"),
    ]:
        turn = f"u{index}"
        n = deepcopy(value["needs"][0])
        n.update(
            need_id=f"n{index}",
            question_span={"turn_id": turn, "quote": question},
            operation="retrieve",
            authority={
                "kind": "current",
                "value": None,
                "precision": "none",
                "source": None,
            },
            supports_need_id="n1" if index == 2 else None,
            requested_information="current_policy",
            current_spans=[],
            history_spans=[],
        )
        value["needs"].append(n)
        value["bindings"].append({**value["bindings"][0], "need_id": f"n{index}"})
        turns.append(UserTurn(turn_id=turn, text=question))
    plan = parse_policy_plan(
        value, tuple(turns), (), "2026-11-16T12:00:00Z", recover=True
    )
    rows = dispositions(plan)
    assert [r["status"] for r in rows] == [
        "interpretation_failure",
        "interpretation_failure",
        "admitted",
    ]
    assert rows[1]["reason"] == "dependent_scope"
    assert rows[1]["affected_spans"] == [value["needs"][1]["question_span"]]
    assert "invented annotation" not in json.dumps(rows)


def test_provider_schema_does_not_allow_application_quarantine():
    for n in policy_schema(())["$defs"]["EvidenceNeed"]["anyOf"]:
        assert "scope_error" not in n["properties"]
        assert "requested_information" in n["required"]
        assert n["properties"]["current_spans"]["maxItems"] == 4


def test_versioned_schema_snapshots_match_models():
    from app.retrieval.current_policy import (
        CurrentPolicyPlan,
        CurrentPolicyRequest,
        CurrentPolicyResponse,
    )

    root = Path("/contracts/http/current-policy-needs/v1")
    for name, model in [
        ("plan", CurrentPolicyPlan),
        ("request", CurrentPolicyRequest),
        ("response", CurrentPolicyResponse),
    ]:
        assert (
            json.loads((root / f"{name}.schema.json").read_text())
            == model.model_json_schema()
        )


def test_real_bounded_adapter_uses_second_attempt_and_keeps_independent_need(
    monkeypatch,
):
    import httpx
    from pydantic import SecretStr

    from app.retrieval.current_policy_planner import CurrentPolicyPlanner

    value = deepcopy(FIXTURE["plan"])
    value["needs"][0]["current_spans"] = []
    second = deepcopy(value["needs"][0])
    second.update(
        need_id="n2",
        question_span={"turn_id": "u2", "quote": "Who arranges a replacement badge?"},
        operation="retrieve",
        authority={
            "kind": "current",
            "value": None,
            "precision": "none",
            "source": None,
        },
        requested_information="current_policy",
        current_spans=[],
        history_spans=[],
    )
    value["needs"].append(second)
    value["bindings"].append({**value["bindings"][0], "need_id": "n2"})
    calls = []

    def transport(request):
        calls.append(json.loads(request.content))
        return httpx.Response(
            200,
            json={
                "choices": [
                    {"finish_reason": "stop", "message": {"content": json.dumps(value)}}
                ],
                "usage": {"prompt_tokens": 50, "completion_tokens": 100},
            },
        )

    monkeypatch.setattr(
        "app.retrieval.needs_planner.review_interpretation",
        lambda **kwargs: {"code": "accepted", "input_tokens": 10, "output_tokens": 10},
    )
    planner = CurrentPolicyPlanner(
        api_url="https://provider.invalid/test",
        api_key=SecretStr("fake"),
        provider_name="openai",
        model="test-model",
        timeout_seconds=1,
        client=httpx.Client(transport=httpx.MockTransport(transport)),
    )
    turns = tuple(UserTurn(**t) for t in FIXTURE["turns"]) + (
        UserTurn(turn_id="u2", text=second["question_span"]["quote"]),
    )
    result = planner.plan_needs(turns, evaluated_at="2026-11-16T12:00:00Z")
    assert len(calls) == 2
    assert result.usage.retry_count == 1
    assert [r["status"] for r in result.usage.validation_attempts] == [
        "rejected",
        "accepted",
    ]
    assert [r["status"] for r in dispositions(result.plan)] == [
        "interpretation_failure",
        "admitted",
    ]
    assert result.lineage.contract_schema_version == "current-policy-needs-v1"


@pytest.mark.parametrize(
    "question,quote,expected",
    [
        (
            "I will inspect equipment this year. Which current checks apply?",
            "this year",
            "2026",
        ),
        (
            "An incident happened last month. What is the reporting rule now?",
            "last month",
            "2026-03",
        ),
    ],
)
def test_current_policy_adapter_canonicalizes_relative_fact_without_reinterpreting_authority(
    monkeypatch, question, quote, expected
):
    import httpx
    from pydantic import SecretStr

    from app.retrieval.current_policy_planner import CurrentPolicyPlanner

    value = deepcopy(FIXTURE["plan"])
    value["needs"][0].update(
        question_span={"turn_id": "u1", "quote": question},
        operation="retrieve",
        authority={
            "kind": "current",
            "value": None,
            "precision": "none",
            "source": None,
        },
        fact_reference={
            "value": "wrong arithmetic",
            "precision": "duration",
            "source": {"turn_id": "u1", "quote": quote},
        },
        requested_information="current_policy",
        current_spans=[],
        history_spans=[],
    )
    calls = []

    def transport(req):
        calls.append(req)
        return httpx.Response(
            200,
            json={
                "choices": [
                    {"finish_reason": "stop", "message": {"content": json.dumps(value)}}
                ],
                "usage": {"prompt_tokens": 50, "completion_tokens": 100},
            },
        )

    monkeypatch.setattr(
        "app.retrieval.needs_planner.review_interpretation",
        lambda **kwargs: {"code": "accepted", "input_tokens": 10, "output_tokens": 10},
    )
    planner = CurrentPolicyPlanner(
        api_url="https://invalid.test",
        api_key=SecretStr("fake"),
        provider_name="openai",
        model="test-model",
        timeout_seconds=1,
        client=httpx.Client(transport=httpx.MockTransport(transport)),
    )
    result = planner.plan_needs(
        (UserTurn(turn_id="u1", text=question),), evaluated_at="2026-03-31T23:30:00Z"
    )
    assert result.plan.needs[0].fact_reference.value == expected
    assert result.plan.needs[0].authority.kind == "current"
    assert result.plan.needs[0].question_span.quote == question
    assert len(calls) == 1
