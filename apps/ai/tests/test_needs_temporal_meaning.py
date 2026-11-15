"""Controlled producer plans prove contracts, not live temporal interpretation."""

import json
import os
from copy import deepcopy
from pathlib import Path

import pytest

from app.retrieval.needs import UserTurn
from app.retrieval.needs_planner import PROMPT
from app.retrieval.planner import RetrievalPlanningError
from tests.test_evidence_needs import EXAMPLES, adapter

pytestmark = pytest.mark.usefixtures("accepted_interpretation")

# Unfamiliar development examples, not the retained regression questions.
CASES = [
    (
        "update",
        "What changed in the archive rota revision during September 2024?",
        "compare",
        "transition_period",
        "2024-09",
        "month",
        "September 2024",
    ),
    (
        "boundary",
        "What changed in the archive rota on 18 September 2024?",
        "compare",
        "transition_boundary",
        "2024-09-18",
        "day",
        "18 September 2024",
    ),
    (
        "state",
        "Which checks applied on 18 September 2024?",
        "retrieve",
        "date",
        "2024-09-18",
        "day",
        "18 September 2024",
    ),
    (
        "interval",
        "Which checks applied throughout October 2024?",
        "retrieve",
        "period",
        "2024-10",
        "month",
        "October 2024",
    ),
    (
        "before",
        "Who held the duty before September 2024?",
        "retrieve",
        "before",
        "2024-09",
        "month",
        "before September 2024",
    ),
    (
        "after",
        "Who held the duty after September 2024?",
        "retrieve",
        "after",
        "2024-09",
        "month",
        "after September 2024",
    ),
    (
        "background",
        "An archive rota began in 2023; which checks are required now?",
        "retrieve",
        "current",
        None,
        "none",
        None,
    ),
    (
        "previous",
        "What changed from the previous version of the archive rota?",
        "compare",
        "previous",
        "previous version",
        "named",
        "previous version",
    ),
    (
        "missing_transition",
        "What changed in the archive rota during August 2024?",
        "compare",
        "transition_period",
        "2024-08",
        "month",
        "August 2024",
    ),
    (
        "conflicting_window",
        "What changed in the archive rota during 2024?",
        "compare",
        "transition_period",
        "2024",
        "year",
        "2024",
    ),
]


def controlled_plan(question, operation, kind, value, precision, quote):
    plan = deepcopy(EXAMPLES[0]["plan"])
    need = deepcopy(plan["needs"][1])
    need.update(
        need_id="n1",
        aspect=question,
        supports_need_id=None,
        question_span={"turn_id": "u1", "quote": question},
        context_spans=[],
        operation=operation,
        fact_reference=None,
        authority={
            "kind": kind,
            "value": value,
            "precision": precision,
            "source": {"turn_id": "u1", "quote": quote} if quote else None,
        },
    )
    if kind == "current":
        need["fact_reference"] = {
            "value": "2023",
            "precision": "year",
            "source": {"turn_id": "u1", "quote": "2023"},
        }
    plan.update(needs=[need], bindings=[{**plan["bindings"][1], "need_id": "n1"}])
    return plan


def test_temporal_guidance_is_coherent_not_a_keyword_classifier():
    # Presence only: no assertion that the live model follows these instructions.
    for text in (
        "Change event:",
        "State at a date:",
        "Requested interval:",
        "Before/after:",
        "Background dates:",
        "not keyword mappings",
        "separate retrieve/date needs",
        "not that every operational",
        "requests its predecessor/successor pair",
    ):
        assert text in PROMPT
    assert "only when two versions are actually requested" not in PROMPT


def test_temporal_meaning_producer_artifact(monkeypatch):
    monkeypatch.setattr("app.retrieval.needs_planner.time.perf_counter", lambda: 1.0)
    artifacts = []
    for name, question, *fields in CASES:
        raw = controlled_plan(question, *fields)
        turns = (UserTurn(turn_id="u1", text=question),)
        calls = []
        result = adapter(raw, calls).plan_needs(
            turns, evaluated_at="2026-11-15T12:00:00Z"
        )
        assert len(calls) == 1
        assert (
            result.plan.needs[0].authority.model_dump() == raw["needs"][0]["authority"]
        )
        artifacts.append(
            {
                "name": name,
                "turns": [t.model_dump() for t in turns],
                "plan": result.plan.model_dump(mode="json"),
                "classifier_lineage": result.lineage.model_dump(mode="json"),
                "usage": result.usage.model_dump(mode="json"),
            }
        )
    target = Path("/contracts/http/retrieval-needs/v4/temporal-meaning-examples.json")
    if os.environ.get("WRITE_TEMPORAL_MEANING_FIXTURE") == "1":
        writable = (
            Path(__file__).resolve().parents[3]
            / "contracts/http/retrieval-needs/v4/temporal-meaning-examples.json"
        )
        writable.write_text(json.dumps(artifacts, indent=2) + "\n")
    assert json.loads(target.read_text()) == artifacts


def test_conflicting_selectors_can_use_existing_clarification_without_retry():
    raw = {
        "schema_version": "evidence-needs-plan-v4",
        "decision": "clarify",
        "clarification": "Do you mean the archive rota at its start or after its first revision?",
        "needs": [],
        "bindings": [],
    }
    calls = []
    result = adapter(raw, calls).plan_needs(
        (
            UserTurn(
                turn_id="u1",
                text="Use its starting rule, not its starting rule but the later one.",
            ),
        ),
        evaluated_at="2026-11-15T12:00:00Z",
    )
    assert result.plan.decision == "clarify"
    assert len(calls) == 1


def test_retained_g3_after_rejects_and_controlled_date_representation_passes():
    root = Path(
        "/workspace/docs/evaluation/r28-s04/block1-temporal-association-diagnostic-20261114"
    )
    captured = json.loads(
        (
            root
            / "capture/51b13294-d548-4241-b2cb-832eb5d6c162-b89ae43c-46f5-4cc0-8887-2b3c65da5932.json"
        ).read_text()
    )
    question = json.loads((root / "inputs/G3.json").read_text())["utterance"]
    turns = (UserTurn(turn_id="user-1", text=question),)
    # Allowlisted capture lacks envelope fields. This is explicitly a controlled
    # envelope carrying retained need fields, not reconstruction of original output.
    raw = deepcopy(captured["proposed"])
    raw["clarification"] = None
    for need in raw["needs"]:
        need.update(aspect=need["question_span"]["quote"], supports_need_id=None)
    for binding in raw["bindings"]:
        binding.update(ownership="authorised_workspace", ownership_source=None)
    calls = []
    with pytest.raises(RetrievalPlanningError) as caught:
        adapter(raw, calls, finished=True).plan_needs(
            turns, evaluated_at="2026-11-15T12:00:00Z"
        )
    assert len(calls) == 2
    assert caught.value.validation["code"] == "temporal_relation_grounding"
    raw["needs"][0]["authority"]["kind"] = "date"
    calls = []
    result = adapter(raw, calls).plan_needs(turns, evaluated_at="2026-11-15T12:00:00Z")
    assert len(calls) == 1
    assert result.plan.needs[0].authority.value == "2025-07-01"
