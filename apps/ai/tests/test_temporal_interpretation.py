"""Structural guarantees only; semantic performance requires the frozen probes."""

from copy import deepcopy

import pytest
from pydantic import SecretStr

from app.retrieval.interpretation import InterpretationCheckError, review_interpretation
from app.retrieval.needs import EvidenceNeedsPlan
from tests.test_evidence_needs import EXAMPLES, turns
from tests.test_interpretation_check import citation, transport


def output():
    return {
        "code": "accepted",
        "citation": None,
        "temporal_assessments": [
            {
                "need_id": n["need_id"],
                "temporal_intent_preserved": "yes",
                "references": [],
            }
            for n in EXAMPLES[0]["plan"]["needs"]
        ],
    }


def check(value):
    client, calls = transport([value])
    plan = EvidenceNeedsPlan.model_validate(EXAMPLES[0]["plan"]).execution_plan()
    before = plan.model_dump()
    result = review_interpretation(
        client=client,
        api_url="https://fake.invalid",
        api_key=SecretStr("fake"),
        model="gpt-5-mini",
        provider="openai",
        turns=turns(EXAMPLES[0]),
        plan=plan,
    )
    assert plan.model_dump() == before
    assert len(calls) == 1
    assert calls[0]["max_completion_tokens"] == 4096
    return result


@pytest.mark.parametrize("role", ["authority", "fact_context", "both", "ambiguous"])
def test_temporal_assessment_is_bounded_grounded_and_not_an_authority_rewrite(role):
    value = output()
    turn = turns(EXAMPLES[0])[0]
    value["temporal_assessments"][0]["references"] = [
        {"turn_id": turn.turn_id, "quote": turn.text, "role": role}
    ]
    result = check(value)
    assert result["version"] == "interpretation-check-v3"
    assert result["temporal_assessments"] == value["temporal_assessments"]
    assert result["citation_verification"] == "not_applicable"
    assert result["input_tokens"] == 11
    assert result["output_tokens"] == 3


@pytest.mark.parametrize(
    "shape",
    [
        "missing",
        "duplicate",
        "unknown",
        "ungrounded",
        "unknown_turn",
        "unresolved",
        "extra",
    ],
)
def test_invalid_temporal_receipt_fails_closed_without_an_additional_call(shape):
    value = output()
    rows = value["temporal_assessments"]
    if shape == "missing":
        rows.pop()
    elif shape == "duplicate":
        rows[1] = deepcopy(rows[0])
    elif shape == "unknown":
        rows[0]["need_id"] = "n3"
    elif shape in {"ungrounded", "unknown_turn"}:
        turn = turns(EXAMPLES[0])[0]
        rows[0]["references"] = [
            {
                "turn_id": "absent" if shape == "unknown_turn" else turn.turn_id,
                "quote": turn.text
                if shape == "unknown_turn"
                else "never supplied date",
                "role": "authority",
            }
        ]
    elif shape == "unresolved":
        rows[0]["temporal_intent_preserved"] = "unclear"
    else:
        rows[0]["new_authority"] = "1900"
    with pytest.raises(InterpretationCheckError) as caught:
        check(value)
    assert caught.value.receipt["request_count"] == 1


def test_temporal_judgement_does_not_override_unverifiable_citation_rule():
    value = output()
    value["code"] = "meaning_changed"
    value["temporal_assessments"][0]["temporal_intent_preserved"] = "no"
    value["citation"] = citation(user_turn_id="u1", user_quote="invented")
    result = check(value)
    assert result["checker_code"] == "meaning_changed"
    assert result["code"] == "accepted"
    assert result["citation_verification"] == "unverifiable"
    assert result["verification_reason"] == "user_quote_not_found"
