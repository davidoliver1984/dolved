import json
import os
from copy import deepcopy
from pathlib import Path

import pytest
from pydantic import ValidationError

from app.retrieval.needs import FactReference, UserTurn
from app.retrieval.needs_planner import (
    _canonicalize_explicit_calendar_values,
)
from app.retrieval.planner import RetrievalPlanningError
from tests.test_evidence_needs import EXAMPLES, adapter

pytestmark = pytest.mark.usefixtures("accepted_interpretation")


def test_current_python_adapter_matches_shared_php_consumption_fixture(monkeypatch):
    """PHP consumes this same artifact; producer drift must fail this assertion."""
    monkeypatch.setattr("app.retrieval.needs_planner.time.perf_counter", lambda: 1.0)
    plan, turns = sample("November 2023", "month")
    result = adapter(plan, [], finished=True).plan_needs(
        turns, evaluated_at="2026-11-14T12:00:00Z"
    )
    artifact = {
        "evidence_kind": "real_python_adapter_controlled_transport_and_interpretation",
        "evaluated_at": "2026-11-14T12:00:00Z",
        "turns": [turn.model_dump(mode="json") for turn in turns],
        "plan": result.plan.model_dump(mode="json"),
        "classifier_lineage": result.lineage.model_dump(mode="json"),
        "usage": result.usage.model_dump(mode="json"),
    }
    target = Path("/contracts/http/retrieval-needs/v4/python-produced-plan.json")
    # Explicit regeneration is a maintenance action, never automatic on a test failure.
    if os.environ.get("WRITE_NEEDS_CROSS_SERVICE_FIXTURE") == "1":
        writable = (
            Path(__file__).resolve().parents[3]
            / "contracts/http/retrieval-needs/v4/python-produced-plan.json"
        )
        writable.write_text(json.dumps(artifact, indent=2) + "\n")
    assert artifact == json.loads(target.read_text())


def sample(value, precision, quote=None):
    quote = value if quote is None else quote
    question = f"The event was {quote}. What does the current policy require?"
    plan = deepcopy(EXAMPLES[0]["plan"])
    need = deepcopy(plan["needs"][1])
    need.update(
        need_id="n1",
        supports_need_id=None,
        aspect=question,
        question_span={"turn_id": "u1", "quote": question},
        fact_reference={
            "value": value,
            "precision": precision,
            "source": {"turn_id": "u1", "quote": quote},
        },
    )
    plan.update(needs=[need], bindings=[{**plan["bindings"][1], "need_id": "n1"}])
    return plan, (UserTurn(turn_id="u1", text=question),)


@pytest.mark.parametrize(
    "raw,precision,canonical",
    [
        ("March 2025", "month", "2025-03"),
        ("1 July 2025", "day", "2025-07-01"),
        ("November 2023", "month", "2023-11"),
        ("Nov. 2023", "month", "2023-11"),
        ("29th February 2024", "day", "2024-02-29"),
        ("February 29th, 2024", "day", "2024-02-29"),
        ("Feb. 29, 2024", "day", "2024-02-29"),
        ("09 OCT 2023", "day", "2023-10-09"),
    ],
)
@pytest.mark.parametrize("field", ["fact_reference", "authority"])
def test_notation_only_conversion_reaches_real_adapter(
    raw, precision, canonical, field
):
    plan, turns = sample(raw, precision)
    if field == "authority":
        reference = plan["needs"][0]["fact_reference"]
        plan["needs"][0]["authority"] = {
            **reference,
            "kind": "date" if precision == "day" else "period",
        }
        plan["needs"][0]["fact_reference"] = None
    original = deepcopy(plan)
    expected = deepcopy(plan)
    expected["needs"][0][field]["value"] = canonical
    converted, count = _canonicalize_explicit_calendar_values(plan, turns)
    assert converted == expected
    assert plan == original
    assert count == 1
    assert _canonicalize_explicit_calendar_values(converted, turns) == (expected, 0)
    calls = []
    result = adapter(plan, calls).plan_needs(turns, evaluated_at="2026-11-14T12:00:00Z")
    assert len(calls) == 1
    assert getattr(result.plan.needs[0], field).value == canonical
    assert result.lineage.prompt_version == "bounded-evidence-needs-v26"
    assert result.lineage.adapter_version == "structured-chat-needs-v12"
    if field == "fact_reference":
        assert result.plan.needs[0].authority.kind == "current"


@pytest.mark.parametrize(
    "value,precision,quote",
    [
        ("November 2023", "day", "November 2023"),
        ("9 November 2023", "month", "9 November 2023"),
        ("9 November 2023", "year", "9 November 2023"),
        ("November 2023", "month", "December 2023"),
        ("31 November 2023", "day", "31 November 2023"),
        ("29 February 2023", "day", "29 February 2023"),
        ("11/09/2023", "day", "11/09/2023"),
        ("last month", "month", "last month"),
        ("November 2023", "month", "November 20230"),
        ("November 2023", "interval", "November 2023"),
    ],
)
def test_invalid_or_ungrounded_notation_is_unchanged_and_rejected(
    value, precision, quote
):
    plan, turns = sample(value, precision, quote)
    assert _canonicalize_explicit_calendar_values(plan, turns) == (plan, 0)
    with pytest.raises(ValidationError):
        FactReference.model_validate(plan["needs"][0]["fact_reference"])


@pytest.mark.parametrize(
    "value,precision",
    [
        ("2023-11-09", "day"),
        ("2023-11", "month"),
        ("2023", "year"),
        ("2023-11-01/2023-11-30", "interval"),
        ("three years", "duration"),
        ("five shifts", "quantity"),
    ],
)
def test_existing_numeric_and_noncalendar_values_are_untouched(value, precision):
    plan, turns = sample(value, precision)
    assert _canonicalize_explicit_calendar_values(plan, turns) == (plan, 0)


def test_invented_source_is_not_normalized_and_does_not_bypass_grounding():
    plan, _ = sample("November 2023", "month")
    turns = (UserTurn(turn_id="u1", text="What is the policy?"),)
    assert _canonicalize_explicit_calendar_values(plan, turns) == (plan, 0)
    with pytest.raises(RetrievalPlanningError):
        adapter(plan, []).plan_needs(turns, evaluated_at="2026-11-14T12:00:00Z")


def test_format_correction_does_not_repair_unquoted_after_relation():
    plan, turns = sample("9 November 2023", "day")
    plan["needs"][0]["authority"] = {
        "kind": "after",
        "value": "2023-11-09",
        "precision": "day",
        "source": {"turn_id": "u1", "quote": "9 November 2023"},
    }
    calls = []
    with pytest.raises(RetrievalPlanningError) as error:
        adapter(plan, calls, finished=True).plan_needs(
            turns, evaluated_at="2026-11-14T12:00:00Z"
        )
    assert len(calls) == 2
    assert error.value.validation["code"] == "temporal_relation_grounding"


def test_logging_contains_count_only(caplog):
    plan, turns = sample("November 2023", "month")
    with caplog.at_level("INFO", logger="retrieval.needs.planner"):
        adapter(plan, []).plan_needs(turns, evaluated_at="2026-11-14T12:00:00Z")
    records = [r for r in caplog.records if hasattr(r, "normalized_reference_count")]
    assert len(records) == 1
    assert records[0].normalized_reference_count == 1
    assert "November" not in records[0].getMessage()
