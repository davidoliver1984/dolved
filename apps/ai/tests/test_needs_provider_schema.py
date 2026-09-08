import json
from copy import deepcopy
from pathlib import Path
from uuid import UUID

import jsonschema
import pytest

from app.retrieval.needs import CatalogueFamily, EvidenceNeedsPlan, UserTurn
from app.retrieval.needs_schema import provider_needs_schema
from app.retrieval.planner import RetrievalPlanningError
from tests.test_evidence_needs import adapter, turns

EXAMPLES = json.loads(
    Path("/contracts/http/retrieval-needs/v3/examples.json").read_text()
)


def families(count):
    return tuple(
        CatalogueFamily(family_id=UUID(int=i + 1), title=f"Synthetic family {i}")
        for i in range(count)
    )


def test_scope_enquiry_is_explicit_in_real_provider_schema_and_does_not_rewrite_retrieve():
    from app.retrieval.needs_planner import PROMPT

    plan = deepcopy(EXAMPLES[0]["plan"])
    plan["needs"][0]["operation"] = "scope_enquiry"
    plan["needs"][0]["authority"] = {
        "kind": "current",
        "value": None,
        "precision": "none",
        "source": None,
    }
    parsed = EvidenceNeedsPlan.model_validate(plan)
    jsonschema.validate(plan, provider_needs_schema(()))
    assert parsed.needs[0].operation == "scope_enquiry"
    ordinary = EvidenceNeedsPlan.model_validate(EXAMPLES[0]["plan"])
    assert ordinary.needs[0].operation == EXAMPLES[0]["plan"]["needs"][0]["operation"]
    assert "never relabel empty retrieval after" in PROMPT
    assert "ONLY recorded applicability, never permission to act" in PROMPT


@pytest.mark.parametrize(
    "operations",
    [
        ("scope_enquiry",),
        ("scope_enquiry", "retrieve"),
        ("retrieve", "scope_enquiry"),
        ("retrieve", "retrieve"),
        ("compare", "retrieve"),
    ],
)
def test_clause_purpose_survives_real_adapter_without_posthoc_reclassification(
    operations,
):
    from app.retrieval.needs import UserTurn
    from app.retrieval.needs_planner import PROMPT

    clauses = {
        "scope_enquiry": "Does Equipment Inspection Procedure cover East Lodge?",
        "retrieve": "How do I book temporary staff?",
        "compare": "What changed from the previous Equipment Inspection Procedure?",
    }
    selected = [
        clauses[o]
        if index == 0 or o != operations[0]
        else "How do I confirm a booking?"
        for index, o in enumerate(operations)
    ]
    text = " And ".join(selected)
    plan = {
        "schema_version": "evidence-needs-plan-v3",
        "decision": "plan",
        "clarification": None,
        "needs": [],
        "bindings": [],
    }
    catalogue = families(1)
    for index, operation in enumerate(operations, 1):
        quote = selected[index - 1]
        plan["needs"].append(
            {
                "need_id": f"n{index}",
                "aspect": quote,
                "central": True,
                "question_span": {"turn_id": "user-1", "quote": quote},
                "operation": operation,
                "authority": {
                    "kind": "previous" if operation == "compare" else "current",
                    "value": "previous" if operation == "compare" else None,
                    "precision": "named" if operation == "compare" else "none",
                    "source": {"turn_id": "user-1", "quote": "previous"}
                    if operation == "compare"
                    else None,
                },
                "applicability": {
                    "kind": "named" if operation == "scope_enquiry" else "generic",
                    "source": {"turn_id": "user-1", "quote": "East Lodge"}
                    if operation == "scope_enquiry"
                    else None,
                },
                "fact_reference": None,
                "supports_need_id": None,
            }
        )
        plan["bindings"].append(
            {
                "need_id": f"n{index}",
                "ownership": "authorised_workspace",
                "ownership_source": None,
                "subject_status": "topic" if operation == "retrieve" else "matched",
                "family_ids": []
                if operation == "retrieve"
                else [str(catalogue[0].family_id)],
            }
        )
    calls = []
    result = adapter(plan, calls).plan_needs(
        (UserTurn(turn_id="user-1", text=text),),
        evaluated_at="2026-09-08T12:00:00Z",
        catalogue=catalogue,
    )
    assert tuple(n.operation for n in result.plan.needs) == operations
    assert len(calls) == 1
    assert "ORIGINAL requested clause before rewriting" in PROMPT
    assert "adding an unrelated second question does not turn" in PROMPT
    assert "How do I inspect equipment at East Lodge?' remains retrieve" in PROMPT
    assert "A policy-version\nchange question remains compare" in PROMPT
    # Fake model output proves transport/contract preservation, NOT model judgement.


@pytest.mark.parametrize("example", EXAMPLES, ids=lambda e: e["name"])
def test_existing_structurally_valid_examples_remain_representable(example):
    jsonschema.validate(example["plan"], provider_needs_schema(()))


@pytest.mark.parametrize(
    "kind,precisions",
    [
        ("current", ["none"]),
        ("date", ["day"]),
        ("period", ["month", "year", "interval"]),
        ("previous", ["named"]),
        ("historical_reference", ["named"]),
        ("transition_period", ["month", "year", "interval"]),
        ("transition_boundary", ["day"]),
    ],
)
def test_every_authority_alternative_and_invalid_cross_field_shape(kind, precisions):
    values = {
        "none": None,
        "day": "2026-06-01",
        "month": "2026-06",
        "year": "2026",
        "interval": "2026-06-01/2026-06-30",
        "named": "previous version",
    }
    for precision in precisions:
        plan = deepcopy(EXAMPLES[0]["plan"])
        need = plan["needs"][0]
        need["operation"] = "retrieve" if kind == "current" else "compare"
        need["authority"] = {
            "kind": kind,
            "precision": precision,
            "value": values[precision],
            "source": None if kind == "current" else need["question_span"],
        }
        schema = provider_needs_schema(())
        jsonschema.validate(plan, schema)
        EvidenceNeedsPlan.model_validate(plan)
        broken = deepcopy(plan)
        broken["needs"][0]["authority"]["source"] = (
            need["question_span"] if kind == "current" else None
        )
        with pytest.raises(jsonschema.ValidationError):
            jsonschema.validate(broken, schema)
        broken = deepcopy(plan)
        broken["needs"][0]["authority"]["precision"] = (
            "day" if precision != "day" else "named"
        )
        with pytest.raises(jsonschema.ValidationError):
            jsonschema.validate(broken, schema)


@pytest.mark.parametrize(
    "fault",
    [
        "unknown_id",
        "empty_match",
        "populated_topic",
        "single_ambiguous",
        "ownership_without_quote",
        "compare_current",
        "retrieve_transition",
        "wrong_date_shape",
    ],
)
def test_provider_schema_excludes_previously_representable_invalid_combinations(fault):
    plan = deepcopy(EXAMPLES[0]["plan"])
    binding = plan["bindings"][0]
    if fault == "unknown_id":
        binding.update(subject_status="matched", family_ids=[str(UUID(int=99))])
    elif fault == "empty_match":
        binding["subject_status"] = "matched"
    elif fault == "populated_topic":
        binding["family_ids"] = [str(UUID(int=1))]
    elif fault == "single_ambiguous":
        binding.update(subject_status="ambiguous", family_ids=[str(UUID(int=1))])
    elif fault == "ownership_without_quote":
        binding["ownership"] = "external_tenant"
    elif fault == "compare_current":
        plan["needs"][0]["authority"] = {
            "kind": "current",
            "value": None,
            "precision": "none",
            "source": None,
        }
    elif fault == "retrieve_transition":
        plan["needs"][0]["operation"] = "retrieve"
        plan["needs"][0]["authority"].update(
            kind="transition_period", precision="month", value="2026-06"
        )
    elif fault == "wrong_date_shape":
        plan["needs"][0]["authority"].update(
            kind="period", precision="month", value="June 2026"
        )
    with pytest.raises(jsonschema.ValidationError):
        jsonschema.validate(plan, provider_needs_schema(families(2)))


def test_real_transport_sends_request_specific_schema_and_does_not_cache_other_tenants():
    for family in families(2):
        example = deepcopy(EXAMPLES[0])
        example["plan"]["bindings"][0].update(
            subject_status="matched", family_ids=[str(family.family_id)]
        )
        calls = []
        result = adapter(example["plan"], calls).plan_needs(
            turns(example), evaluated_at="2026-09-06T12:00:00Z", catalogue=(family,)
        )
        schema = calls[0]["response_format"]["json_schema"]["schema"]
        jsonschema.validate(example["plan"], schema)
        other = str(UUID(int=2 if family.family_id.int == 1 else 1))
        assert other not in json.dumps(schema)
        assert result.lineage.adapter_version == "structured-chat-needs-v6"
        assert len(calls) == 1


@pytest.mark.parametrize("count", [0, 1, 250, 251, 500])
def test_empty_and_maximum_catalogues_use_supported_bounded_schema(count):
    schema = provider_needs_schema(families(count))
    jsonschema.Draft202012Validator.check_schema(schema)
    assert schema["type"] == "object" and "anyOf" not in schema
    enums = []

    def visit(node):
        if isinstance(node, list):
            for child in node:
                visit(child)
        elif isinstance(node, dict):
            assert not {
                "allOf",
                "if",
                "then",
                "else",
                "not",
                "dependentRequired",
                "dependentSchemas",
                "uniqueItems",
            }.intersection(node)
            if node.get("type") == "object":
                assert node["additionalProperties"] is False
                assert set(node["required"]) == set(node["properties"])
            if "enum" in node:
                enums.extend(node["enum"])
                assert (
                    len(node["enum"]) <= 250
                    or sum(len(str(v)) for v in node["enum"]) <= 15000
                )
            for child in node.values():
                visit(child)

    visit(schema)
    assert len(enums) <= 1000
    assert provider_needs_schema(families(count)) == schema
    if count == 0:
        assert "CatalogueFamilyId" not in schema["$defs"]
    if count == 500:
        assert len(json.dumps(schema).encode()) < 35000


def test_invalid_catalogue_stops_before_dispatch():
    for catalogue in [families(501), families(1) * 2]:
        calls = []
        with pytest.raises(RetrievalPlanningError):
            adapter(EXAMPLES[0]["plan"], calls).plan_needs(
                turns(EXAMPLES[0]),
                evaluated_at="2026-09-06T12:00:00Z",
                catalogue=catalogue,
            )
        assert not calls


def test_complete_provider_request_over_byte_allowance_never_dispatches():
    calls = []
    oversized = tuple(UserTurn(turn_id=f"t{i}", text="x" * 8000) for i in range(20))
    with pytest.raises(RetrievalPlanningError, match="input allowance"):
        adapter(EXAMPLES[0]["plan"], calls).plan_needs(
            oversized, evaluated_at="2026-09-06T12:00:00Z", catalogue=families(500)
        )
    assert not calls
