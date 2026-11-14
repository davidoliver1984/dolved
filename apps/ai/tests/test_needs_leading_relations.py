"""Grounded kind-only canonicalisation; no semantic relation inference."""

import json
from copy import deepcopy
from uuid import uuid4

import pytest

from app import evaluation_capture as capture
from app.retrieval.needs import EvidenceNeedsPlan
from app.retrieval.needs_planner import (
    TEMPORAL_RELATION_HINT,
    NeedsValidationFailure,
    _canonicalize_leading_relations,
)
from tests.test_evaluation_capture import setup_capture
from tests.test_needs_calendar_notation import sample
from tests.test_needs_correction_feedback import run_outputs

pytestmark = pytest.mark.usefixtures("accepted_interpretation")
ANCHOR = "2026-11-14T12:00:00Z"


def proposal(quote="before April 2024", value="2024-04", precision="month"):
    plan, turns = sample(value, precision, quote)
    need = plan["needs"][0]
    need["authority"] = {**need["fact_reference"], "kind": "period"}
    need["fact_reference"] = None
    return plan, turns


@pytest.mark.parametrize(
    "quote,value,precision,kind",
    [
        ("before April 2024", "2024-04", "month", "before"),
        ("  AFTER 6 June 2023", "2023-06-06", "day", "after"),
        ("Before 2022", "2022", "year", "before"),
    ],
)
def test_kind_only_grounded_conversion_and_real_adapter(
    quote, value, precision, kind, tmp_path, monkeypatch
):
    plan, turns = proposal(quote, value, precision)
    original = deepcopy(plan)
    expected = deepcopy(plan)
    expected["needs"][0]["authority"]["kind"] = kind
    converted, count = _canonicalize_leading_relations(plan, turns)
    assert (converted, count) == (expected, 1)
    assert plan == original
    assert _canonicalize_leading_relations(converted, turns) == (expected, 0)
    planner, calls, _ = run_outputs([plan], turns)
    settings = setup_capture(tmp_path, monkeypatch)
    with capture.capture_scope(settings, capture.WORKSPACE, str(uuid4()), "planner"):
        result = planner.plan_needs(turns, evaluated_at=ANCHOR)
    assert len(calls) == 1
    assert result.plan.model_dump(mode="json") == expected
    record = json.loads(next(tmp_path.glob("*.json")).read_text())
    assert record["leading_relation_conversion_count"] == 1
    assert record["calendar_notation_normalization_count"] == 0
    assert record["status"] == "accepted"


@pytest.mark.parametrize(
    "quote", ["April 2024", "rules before April 2024", "beforehand April 2024"]
)
def test_only_leading_word_can_convert(quote):
    plan, turns = proposal(quote)
    assert _canonicalize_leading_relations(plan, turns) == (plan, 0)


@pytest.mark.parametrize(
    "change", ["unknown_turn", "ungrounded", "interval", "named", "none"]
)
def test_invalid_proposals_unchanged_and_real_validation_rejects(change):
    plan, turns = proposal()
    authority = plan["needs"][0]["authority"]
    if change == "unknown_turn":
        authority["source"]["turn_id"] = "not-authorised"
    elif change == "ungrounded":
        authority["source"]["quote"] = "before May 2024"
    else:
        authority["precision"] = change
        if change == "interval":
            authority["value"] = "2024-04-01/2024-04-30"
    assert _canonicalize_leading_relations(plan, turns) == (plan, 0)
    planner, calls, _ = run_outputs([plan], turns)
    with pytest.raises(NeedsValidationFailure):
        planner.plan_needs(turns, evaluated_at=ANCHOR)
    assert len(calls) == 2


@pytest.mark.parametrize(
    "kind",
    [
        "current",
        "date",
        "before",
        "after",
        "previous",
        "historical_reference",
        "transition_period",
        "transition_boundary",
    ],
)
def test_other_kinds_not_rewritten(kind):
    plan, turns = proposal()
    plan["needs"][0]["authority"]["kind"] = kind
    assert _canonicalize_leading_relations(plan, turns) == (plan, 0)


def test_hint_only_for_relation_error_and_no_additional_attempt():
    plan, turns = proposal("on 6 June 2023", "2023-06-06", "day")
    plan["needs"][0]["authority"]["kind"] = "after"
    for field, value, hint in [
        ("kind", "after", True),
        ("value", "invalid-date", False),
    ]:
        broken = deepcopy(plan)
        broken["needs"][0]["authority"][field] = value
        planner, calls, _ = run_outputs([broken], turns)
        with pytest.raises(NeedsValidationFailure):
            planner.plan_needs(turns, evaluated_at=ANCHOR)
        assert len(calls) == 2
        first, second = [json.loads(c["messages"][1]["content"]) for c in calls]
        assert "validation_feedback" not in first
        feedback = second.pop("validation_feedback")
        assert second == first
        assert feedback.get("hint") == (TEMPORAL_RELATION_HINT if hint else None)
        assert set(feedback) == {"stage", "field", "code"} | (
            {"hint"} if hint else set()
        )


def test_background_event_remains_fact_context_and_current():
    plan, turns = sample("2024-04", "month", "April 2024")
    planner, calls, _ = run_outputs([plan], turns)
    result = planner.plan_needs(turns, evaluated_at=ANCHOR)
    assert len(calls) == 1
    assert result.plan.needs[0].authority.kind == "current"
    assert result.plan.needs[0].fact_reference.value == "2024-04"
    assert EvidenceNeedsPlan.model_validate(plan) == result.plan
