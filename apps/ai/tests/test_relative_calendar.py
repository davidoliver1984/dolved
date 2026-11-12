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
from app.retrieval.relative_calendar import grounded_periods, relative_period
from tests.test_evidence_needs import EXAMPLES

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
