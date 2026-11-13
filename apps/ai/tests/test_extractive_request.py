"""Source-only request composition, independent of the diagnostic paraphrase."""

from copy import deepcopy

import pytest

from app.generation.needs_request import GenerationNeedsRequest
from app.retrieval.needs import EvidenceNeedsPlan, UserTurn, compiled_request
from tests.test_generation_needs_request import request as legacy_generation_request


def sample(question="May I borrow a tablet?", context=()):
    return {
        "schema_version": "evidence-needs-plan-v4",
        "decision": "plan",
        "clarification": None,
        "bindings": [],
        "needs": [
            {
                "need_id": "n1",
                "aspect": "Invented examples: orbital insurance and mineral surveys",
                "central": True,
                "question_span": {"turn_id": "u2", "quote": question},
                "context_spans": list(context),
                "operation": "retrieve",
                "authority": {
                    "kind": "current",
                    "precision": "none",
                    "value": None,
                    "source": None,
                },
                "fact_reference": None,
                "applicability": {"kind": "generic", "source": None},
                "claim_under_test": None,
                "scope_question": None,
                "supports_need_id": None,
            }
        ],
    }


@pytest.mark.parametrize(
    "question",
    [
        "May I borrow a tablet?",
        "Which devices, for example a tablet or scanner, are permitted?",
        "Who reviews the log, and on which day?",
    ],
)
def test_arbitrary_paraphrase_cannot_reach_executable_text(question):
    raw = sample(question)
    plan = EvidenceNeedsPlan.model_validate(raw)
    plan.validate_grounding((UserTurn(turn_id="u2", text=question),))
    executed = plan.execution_plan()
    assert executed.needs[0].aspect == question
    assert plan.needs[0].aspect == raw["needs"][0]["aspect"]  # diagnostic retained
    mutated = deepcopy(raw)
    mutated["needs"][0]["aspect"] = "An entirely different invented constraint"
    assert EvidenceNeedsPlan.model_validate(mutated).execution_plan() == executed


def test_cross_turn_pronoun_uses_only_grounded_context_not_replacement_words():
    raw = sample(
        "And who maintains it?", [{"turn_id": "u1", "quote": "the equipment register"}]
    )
    turns = (
        UserTurn(turn_id="u1", text="Tell me about the equipment register."),
        UserTurn(turn_id="u2", text="And who maintains it?"),
    )
    plan = EvidenceNeedsPlan.model_validate(raw)
    plan.validate_grounding(turns)
    assert (
        compiled_request(plan.needs[0])
        == "the equipment register And who maintains it?"
    )
    raw["needs"][0]["context_spans"][0]["quote"] = "a fictional register"
    with pytest.raises(ValueError, match="grounded"):
        EvidenceNeedsPlan.model_validate(raw).validate_grounding(turns)


def test_independent_clauses_comparison_relative_and_claim_site_keep_typed_rules():
    question = "Compare the equipment rule before March 2026; this year at Birch House, is the charge 12 pounds?"
    raw = sample("Compare the equipment rule before March 2026")
    n1 = raw["needs"][0]
    n1["operation"] = "compare"
    n1["authority"] = {
        "kind": "before",
        "precision": "month",
        "value": "2026-03",
        "source": {"turn_id": "u2", "quote": "before March 2026"},
    }
    n2 = deepcopy(
        sample("this year at Birch House, is the charge 12 pounds?")["needs"][0]
    )
    n2["need_id"] = "n2"
    n2["authority"] = {
        "kind": "period",
        "precision": "year",
        "value": "2026",
        "source": {"turn_id": "u2", "quote": "this year"},
    }
    n2["applicability"] = {
        "kind": "named",
        "source": {"turn_id": "u2", "quote": "Birch House"},
    }
    n2["claim_under_test"] = {
        "version": "claim-under-test-v1",
        "type": "numeric_value",
        "relation": "numeric_rate",
        "source": {"turn_id": "u2", "quote": "12 pounds"},
    }
    raw["needs"].append(n2)
    plan = EvidenceNeedsPlan.model_validate(raw)
    plan.validate_grounding(
        (UserTurn(turn_id="u2", text=question),), evaluated_at="2026-06-01T12:00:00Z"
    )
    executed = plan.execution_plan()
    for need in executed.needs:
        assert need.aspect in question
    assert executed.needs[0].authority == plan.needs[0].authority
    assert executed.needs[1].claim_under_test == plan.needs[1].claim_under_test
    assert executed.needs[1].applicability == plan.needs[1].applicability


def test_generation_boundary_rejects_paraphrase_and_preserves_ownership_checks():
    raw = legacy_generation_request()
    raw["contract_version"] = 3
    for need in raw["evidence_needs"]:
        need["request_text_version"] = "extractive-request-v1"
        need["question_span"] = {
            "turn_id": "u2",
            "quote": "Explain the equipment requirement",
        }
        need["context_spans"] = []
        # Compile all typed quotations too; no model-generated query text.
        from app.generation.needs_request import GenerationNeed

        need["aspect"] = compiled_request(GenerationNeed.model_validate(need))
    GenerationNeedsRequest.model_validate(raw)
    bad = deepcopy(raw)
    bad["evidence_needs"][0]["aspect"] += " invented extra scope"
    with pytest.raises(ValueError, match="compiled extractive"):
        GenerationNeedsRequest.model_validate(bad)
    bad = deepcopy(raw)
    bad["evidence_needs"][1]["evidence_ids"] = bad["evidence_needs"][0]["evidence_ids"]
    with pytest.raises(ValueError, match="owner"):
        GenerationNeedsRequest.model_validate(bad)


def test_legacy_plan_read_does_not_rewrite_history():
    raw = sample()
    raw["schema_version"] = "evidence-needs-plan-v3"
    del raw["needs"][0]["context_spans"]
    plan = EvidenceNeedsPlan.model_validate(raw)
    assert plan.execution_plan() is plan
