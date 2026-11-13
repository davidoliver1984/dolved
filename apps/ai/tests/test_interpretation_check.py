import json
from copy import deepcopy
from pathlib import Path

import httpx
import pytest
from pydantic import SecretStr

from app.retrieval.interpretation import (
    InterpretationCheckError,
    InterpretationDecision,
    review_interpretation,
    verify_citation,
)
from app.retrieval.needs import EvidenceNeedsPlan, UserTurn
from app.retrieval.needs_planner import (
    NeedsValidationFailure,
    StructuredChatNeedsPlanner,
)
from tests.test_evidence_needs import EXAMPLES, turns


def citation(**values):
    return {
        "user_turn_id": None,
        "user_quote": None,
        "need_id": None,
        "plan_field": None,
        "plan_quote": None,
        "inspected_need_ids": [],
    } | values


def decision(code):
    # These tests prove orchestration/citation mechanics, not model semantics.
    value = None
    if code == "scope_added":
        # A typed execution field remains citable; the model paraphrase does not.
        value = citation(
            need_id="n1", plan_field="authority.value", plan_quote="previous"
        )
    if code == "meaning_changed":
        value = citation(
            user_turn_id="u1",
            user_quote="What changed in the ROPA",
            need_id="n1",
            plan_field="aspect",
            plan_quote="What changed in the ROPA",
        )
    if code == "clause_omitted":
        value = citation(
            user_turn_id="u1",
            user_quote="employment records",
            inspected_need_ids=["n1", "n2"],
        )
    return {"code": code, "citation": value}


def transport(reviews, plan=None):
    calls = []
    pending = iter(reviews)

    def handle(request):
        body = json.loads(request.content)
        calls.append(body)
        checking = (
            body["response_format"]["json_schema"]["name"] == "interpretation_check"
        )
        content = next(pending) if checking else (plan or EXAMPLES[0]["plan"])
        if isinstance(content, Exception):
            raise content
        if checking and set(content) == {"code", "citation"}:
            checked = json.loads(body["messages"][1]["content"])["plan"]
            content = {
                **content,
                "temporal_assessments": [
                    {
                        "need_id": n["need_id"],
                        "temporal_intent_preserved": "yes",
                        "references": [],
                    }
                    for n in checked["needs"]
                ],
            }
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
        [decision(code), decision("accepted" if corrected else code)]
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
    assert receipts[0]["interpretation_check"]["checker_code"] == code
    assert receipts[0]["interpretation_check"]["citation_verification"] == "verified"
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
            {
                "code": "clause_omitted",
                "citation": citation(
                    user_turn_id="u1",
                    user_quote="How often were highest-risk residents repositioned before January 2026",
                    inspected_need_ids=["n1"],
                ),
            }
            if check
            else (invalid_plan() if attempt == 2 and len(calls) == 1 else plan)
        )
        if check:
            output["temporal_assessments"] = [
                {"need_id": "n1", "temporal_intent_preserved": "yes", "references": []}
            ]
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
    assert caught.value.interpretation_check["citation_verification"] == "verified"
    assert (
        sum(
            c["response_format"]["json_schema"]["name"] == "evidence_needs_plan"
            for c in calls
        )
        == 2
    )


@pytest.mark.parametrize("code", ["scope_added", "meaning_changed", "clause_omitted"])
@pytest.mark.parametrize("second_attempt", [False, True])
def test_unverifiable_flag_keeps_valid_plan_without_an_additional_attempt(
    code, second_attempt
):
    reviews = ([decision("scope_added")] if second_attempt else []) + [
        {"code": code, "citation": None}
    ]
    client, calls = transport(reviews)
    planner = StructuredChatNeedsPlanner(
        api_url="https://fake.invalid",
        api_key=SecretStr("fake"),
        provider_name="openai",
        model="gpt-5-mini",
        timeout_seconds=1,
        client=client,
    )
    result = planner.plan_needs(turns(EXAMPLES[0]), evaluated_at="2026-11-12T12:00:00Z")
    assert len(calls) == (4 if second_attempt else 2)
    check = result.usage.validation_attempts[-1]["interpretation_check"]
    assert check["checker_code"] == code and check["code"] == "accepted"
    assert check["citation_verification"] == "unverifiable"
    assert check["verification_reason"] == "missing_citation"
    assert result.plan.model_dump(mode="json") == EXAMPLES[0]["plan"]


def test_diagnostic_only_aspect_flag_is_unverifiable_without_retry():
    client, calls = transport(
        [
            {
                "code": "scope_added",
                "citation": citation(
                    need_id="n1", plan_field="aspect", plan_quote="ROPA changes"
                ),
            }
        ]
    )
    planner = StructuredChatNeedsPlanner(
        api_url="https://fake.invalid",
        api_key=SecretStr("fake"),
        provider_name="openai",
        model="gpt-5-mini",
        timeout_seconds=1,
        client=client,
    )
    result = planner.plan_needs(turns(EXAMPLES[0]), evaluated_at="2026-11-12T12:00:00Z")
    check = result.usage.validation_attempts[0]["interpretation_check"]
    assert check["checker_code"] == "scope_added"
    assert check["code"] == "accepted"
    assert check["citation_verification"] == "unverifiable"
    assert check["verification_reason"] == "plan_quote_not_found"
    assert len(calls) == 2
    assert result.usage.retry_count == 0
    assert result.plan.needs[0].aspect == "ROPA changes"
    executed = json.loads(calls[1]["messages"][1]["content"])["plan"]
    assert executed["needs"][0]["aspect"] == "What changed in the ROPA"


def synthetic():
    value = deepcopy(EXAMPLES[0]["plan"])
    question = "Who reviews incidents and how often are reviews held?"
    need = deepcopy(value["needs"][1])
    need.update(
        need_id="n1",
        supports_need_id=None,
        question_span={"turn_id": "u1", "quote": question},
        aspect="Who reviews incidents for agency workers?",
    )
    value.update(needs=[need], bindings=[{**value["bindings"][1], "need_id": "n1"}])
    return EvidenceNeedsPlan.model_validate(value), (
        UserTurn(turn_id="u1", text=question),
    )


@pytest.mark.parametrize(
    "code,cite",
    [
        (
            "scope_added",
            citation(
                need_id="n1", plan_field="aspect", plan_quote="for agency workers"
            ),
        ),
        (
            "meaning_changed",
            citation(
                need_id="n1",
                plan_field="aspect",
                plan_quote="for agency workers",
                user_turn_id="u1",
                user_quote="Who reviews incidents",
            ),
        ),
        (
            "clause_omitted",
            citation(
                user_turn_id="u1",
                user_quote="how often are reviews held?",
                inspected_need_ids=["n1"],
            ),
        ),
    ],
)
def test_genuine_shapes_and_forged_quotes_have_distinct_verification(code, cite):
    plan, user_turns = synthetic()
    result = InterpretationDecision(code=code, citation=cite)
    assert verify_citation(result, user_turns, plan) == ("verified", None)
    bad = deepcopy(cite)
    bad["user_quote" if code == "clause_omitted" else "plan_quote"] = (
        "invented quotation"
    )
    assert (
        verify_citation(
            InterpretationDecision(code=code, citation=bad), user_turns, plan
        )[0]
        == "unverifiable"
    )


def test_different_turn_and_valid_citation_do_not_prove_semantic_wrongness():
    plan, user_turns = synthetic()
    flag = InterpretationDecision(**decision("scope_added"))
    flag = flag.model_copy(
        update={
            "citation": flag.citation.model_copy(
                update={"plan_field": "aspect", "plan_quote": "for agency workers"}
            )
        }
    )
    other = UserTurn(turn_id="u0", text="Please answer for agency workers")
    assert verify_citation(flag, (other, *user_turns), plan) == (
        "unverifiable",
        "plan_quote_in_user_turns",
    )
    omission = InterpretationDecision(
        code="clause_omitted",
        citation=citation(
            user_turn_id="u0",
            user_quote="for agency workers",
            inspected_need_ids=["n1"],
        ),
    )
    assert verify_citation(omission, (other, *user_turns), plan) == ("verified", None)
    # The scope is actually covered: verification certifies the quote, NOT omission.
    paraphrase = InterpretationDecision(**decision("scope_added"))
    assert verify_citation(
        paraphrase,
        turns(EXAMPLES[0]),
        EvidenceNeedsPlan.model_validate(EXAMPLES[0]["plan"]),
    ) == ("verified", None)


@pytest.mark.parametrize("code", ["scope_added", "meaning_changed"])
@pytest.mark.parametrize("unit", ["this year", "last month", "this quarter"])
def test_application_periods_cannot_alone_support_a_flag(code, unit):
    from app.retrieval.relative_calendar import relative_period

    plan, _ = synthetic()
    value = plan.model_dump(mode="json")
    period = relative_period(unit, "2026-11-12T12:00:00Z")
    value["needs"][0]["aspect"] = "Who reviewed incidents during " + period["value"]
    user_turns = (UserTurn(turn_id="u1", text="Who reviewed incidents " + unit + "?"),)
    flag = InterpretationDecision(
        code=code,
        citation=citation(
            need_id="n1",
            plan_field="aspect",
            plan_quote=period["value"],
            user_turn_id="u1",
            user_quote=unit,
        ),
    )
    assert verify_citation(
        flag,
        user_turns,
        EvidenceNeedsPlan.model_validate(value),
        "2026-11-12T12:00:00Z",
    ) == ("unverifiable", "application_grounded_period")


def test_whole_span_is_not_coverage_and_every_need_must_be_identified():
    plan, user_turns = synthetic()
    flag = InterpretationDecision(
        code="clause_omitted",
        citation=citation(
            user_turn_id="u1",
            user_quote="how often are reviews held?",
            inspected_need_ids=["n1"],
        ),
    )
    assert flag.citation.user_quote in plan.needs[0].question_span.quote
    assert verify_citation(flag, user_turns, plan) == ("verified", None)
    for ids in ([], ["n9"], ["n1", "n1"]):
        bad = flag.model_copy(
            update={
                "citation": flag.citation.model_copy(update={"inspected_need_ids": ids})
            }
        )
        assert verify_citation(bad, user_turns, plan) == (
            "unverifiable",
            "inspected_needs_mismatch",
        )


def test_all_four_frozen_omission_probes_retain_actionable_citations():
    probes = json.loads(
        Path(
            "/workspace/docs/evaluation/r28-s04/block1-closing-20261112/omission-probes.json"
        ).read_text()
    )["items"]
    assert len(probes) == 4
    for probe in probes:
        flag = InterpretationDecision(
            code="clause_omitted",
            citation=citation(
                user_turn_id="u1",
                user_quote="How often were highest-risk residents repositioned before January 2026",
                inspected_need_ids=["n1"],
            ),
        )
        assert verify_citation(
            flag,
            tuple(UserTurn.model_validate(t) for t in probe["user_turns"]),
            EvidenceNeedsPlan.model_validate(probe["plan"]),
        ) == ("verified", None)


def test_lossless_merged_need_acceptance_does_not_demand_multiple_needs():
    plan, user_turns = synthetic()
    value = plan.model_dump(mode="json")
    value["needs"][0]["aspect"] = "Identify the incident reviewer and review frequency."
    client, calls = transport([decision("accepted")], value)
    planner = StructuredChatNeedsPlanner(
        api_url="https://fake.invalid",
        api_key=SecretStr("fake"),
        provider_name="openai",
        model="gpt-5-mini",
        timeout_seconds=1,
        client=client,
    )
    result = planner.plan_needs(user_turns, evaluated_at="2026-11-12T12:00:00Z")
    assert len(result.plan.needs) == 1 and len(calls) == 2
    assert (
        result.usage.validation_attempts[0]["interpretation_check"][
            "citation_verification"
        ]
        == "not_applicable"
    )


def test_wrong_need_field_and_user_identity_fail_verification():
    plan, user_turns = synthetic()
    for changes, reason in [
        ({"need_id": "n9"}, "unknown_need"),
        ({"plan_field": "authority.value"}, "plan_quote_not_found"),
        ({"user_turn_id": "u9"}, "user_quote_not_found"),
    ]:
        cite = (
            citation(
                need_id="n1",
                plan_field="aspect",
                plan_quote="agency workers",
                user_turn_id="u1",
                user_quote="Who reviews incidents",
            )
            | changes
        )
        assert verify_citation(
            InterpretationDecision(code="meaning_changed", citation=cite),
            user_turns,
            plan,
        ) == ("unverifiable", reason)
