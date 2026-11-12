import json
from copy import deepcopy
from pathlib import Path

import pytest
from pydantic import ValidationError

from app.retrieval.needs import (
    EvidenceNeedsPlan,
    FactReference,
    UserTurn,
    validate_date_grounding,
)
from app.retrieval.needs_planner import NeedsValidationFailure
from app.retrieval.relative_calendar import (
    canonicalize_relative_periods,
    grounded_periods,
    relative_period,
)
from tests.test_evidence_needs import EXAMPLES, adapter

pytestmark = pytest.mark.usefixtures("accepted_interpretation")

CASES = json.loads(
    Path(
        "/contracts/http/retrieval-needs/v3/relative-calendar-examples.json"
    ).read_text()
)


@pytest.mark.parametrize("case", CASES)
def test_shared_london_calendar_boundaries(case):
    result = relative_period(case["quote"], case["at"])
    assert result == {
        "value": case["value"],
        "precision": case["precision"],
        "start_inclusive": case["start"],
        "end_exclusive": case["end"],
        "timezone": "Europe/London",
    }
    validate_date_grounding(case["value"], case["precision"], case["quote"], case["at"])
    with pytest.raises(ValueError):
        validate_date_grounding("1900", "year", case["quote"], case["at"])


def test_event_is_not_calendar_and_followup_uses_questions_anchor():
    assert relative_period("when I did that shift", "2026-11-12T12:00:00Z") is None
    turns = (
        UserTurn(turn_id="u1", text="What applied last month?"),
        UserTurn(turn_id="u2", text="And who was responsible then?"),
    )
    value = grounded_periods(turns, "2026-03-01T00:00:00Z")[0]
    assert value["turn_id"] == "u1" and value["value"] == "2026-02"
    plan = deepcopy(EXAMPLES[0]["plan"])
    need = deepcopy(plan["needs"][1])
    need.update(
        need_id="n1",
        supports_need_id=None,
        operation="retrieve",
        question_span={"turn_id": "u2", "quote": turns[1].text},
        authority={
            "kind": "period",
            "precision": "month",
            "value": "2026-02",
            "source": {"turn_id": "u1", "quote": "last month"},
        },
    )
    plan.update(needs=[need], bindings=[{**plan["bindings"][1], "need_id": "n1"}])
    EvidenceNeedsPlan.model_validate(plan).validate_grounding(
        turns, evaluated_at="2026-03-01T00:00:00Z"
    )
    with pytest.raises(ValueError):
        EvidenceNeedsPlan.model_validate(plan).validate_grounding(
            turns, evaluated_at="2026-04-01T00:00:00Z"
        )


@pytest.mark.parametrize("precision", ["duration", "quantity"])
def test_relative_calendar_cannot_hide_as_quantity(precision):
    with pytest.raises(ValidationError, match="relative calendar"):
        FactReference(
            value="last month",
            precision=precision,
            source={"turn_id": "u1", "quote": "last month"},
        )


@pytest.mark.parametrize("anchor", [None, "2026-01-01T00:00:00"])
def test_no_implicit_clock_or_zone(anchor):
    with pytest.raises(ValueError):
        relative_period("last month", anchor)


def relative_plan(quote, field="authority"):
    plan = deepcopy(EXAMPLES[0]["plan"])
    need = deepcopy(plan["needs"][1])
    need.update(
        need_id="n1",
        supports_need_id=None,
        operation="retrieve",
        question_span={"turn_id": "u1", "quote": quote},
        aspect=quote,
    )
    need["authority"] = {
        "kind": "current",
        "value": None,
        "precision": "none",
        "source": None,
    }
    need[field] = {
        "value": "1900",
        "precision": "year",
        "source": {"turn_id": "u1", "quote": quote},
    }
    if field == "authority":
        need[field]["kind"] = "period"
    else:
        need[field]["precision"] = "duration"
    plan.update(needs=[need], bindings=[{**plan["bindings"][1], "need_id": "n1"}])
    return plan


@pytest.mark.parametrize("field", ["authority", "fact_reference"])
@pytest.mark.parametrize(
    "quote,anchor,value,precision",
    [
        ("What applied this year?", "2026-01-01T00:00:00Z", "2026", "year"),
        ("Who handled it last year?", "2026-01-01T00:00:00Z", "2025", "year"),
        (
            "What applied this quarter?",
            "2026-09-30T23:30:00Z",
            "2026-10-01/2026-12-31",
            "interval",
        ),
        (
            "Please check the claim approved last month for me.",
            "2024-03-01T00:00:00Z",
            "2024-02",
            "month",
        ),
        ("What applied this month?", "2026-10-25T01:30:00Z", "2026-10", "month"),
        ("What applied last month?", "2026-03-31T23:30:00Z", "2026-03", "month"),
    ],
)
def test_real_adapter_owns_relative_arithmetic_and_preserves_anchor(
    field, quote, anchor, value, precision
):
    plan = relative_plan(quote, field)
    turns = (UserTurn(turn_id="u1", text=quote),)
    result = adapter(plan, []).plan_needs(turns, evaluated_at=anchor).plan
    reference = getattr(result.needs[0], field)
    assert (reference.value, reference.precision) == (value, precision)
    assert reference.source.quote == quote
    assert result.needs[0].question_span.quote == quote
    # Independent schema/grounding checks remain strict on unadapted arithmetic.
    if field == "authority":
        with pytest.raises(ValueError):
            EvidenceNeedsPlan.model_validate(plan).validate_grounding(
                turns, evaluated_at=anchor
            )


@pytest.mark.parametrize(
    "quote", ["last month and this year", "last month then last month"]
)
def test_multiple_relative_phrases_fail_honestly(quote):
    with pytest.raises(NeedsValidationFailure):
        adapter(relative_plan(quote), []).plan_needs(
            (UserTurn(turn_id="u1", text=quote),), evaluated_at="2026-01-01T00:00:00Z"
        )


def test_ungrounded_quote_and_elliptical_followup_are_not_resolved():
    with pytest.raises(NeedsValidationFailure):
        adapter(relative_plan("last year"), []).plan_needs(
            (UserTurn(turn_id="u1", text="Who handles it?"),),
            evaluated_at="2026-01-01T00:00:00Z",
        )
    quote = "and the month before?"
    plan = relative_plan(quote)
    turns = (
        UserTurn(turn_id="u0", text="What applied last month?"),
        UserTurn(turn_id="u1", text=quote),
    )
    assert canonicalize_relative_periods(plan, turns, "2026-01-01T00:00:00Z") == plan
    with pytest.raises(NeedsValidationFailure):
        adapter(plan, []).plan_needs(turns, evaluated_at="2026-01-01T00:00:00Z")
