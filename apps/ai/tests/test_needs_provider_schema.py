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
BOUNDARY_MATRIX = json.loads(
    Path(__file__)
    .with_name("fixtures")
    .joinpath("needs_operation_boundary_matrix.json")
    .read_text()
)


def test_operation_boundary_matrix_is_broad_and_prompt_states_semantic_division():
    from app.retrieval.needs_planner import PROMPT

    assert len(BOUNDARY_MATRIX["scope_enquiry"]) >= 20
    assert len(BOUNDARY_MATRIX["retrieve"]) >= 20
    assert len(BOUNDARY_MATRIX["controls"]) >= 8
    assert (
        len(
            {q for side in ("scope_enquiry", "retrieve") for q in BOUNDARY_MATRIX[side]}
        )
        == 40
    )
    assert "requested fact must be WHERE the policy applies" in PROMPT
    assert "current way to act asks for operational guidance" in PROMPT
    assert "not words such as old, still, can, number or cover" in PROMPT
    assert "Preserve a real location" in PROMPT
    # This checks instruction and fixture coverage, not model classification quality.


@pytest.mark.parametrize(
    ("question", "kind", "quote"),
    [
        (
            "At the home, do we evacuate residents in stages or move everyone out at once when the fire alarm sounds?",
            "relational",
            "the home",
        ),
        (
            "At our home, which fire evacuation procedure should staff follow?",
            "relational",
            "our home",
        ),
        (
            "What is the organisation-wide sickness policy for employees?",
            "generic",
            None,
        ),
        ("What is the general fire-safety policy for all care homes?", "generic", None),
        (
            "Under Thornfield's own Fire Safety Policy, is the Cinderhill House manager the Responsible Person there?",
            "named",
            "Cinderhill House",
        ),
        (
            "Is the Cinderhill House manager definitely our Responsible Person for fire safety?",
            "named",
            "Cinderhill House",
        ),
    ],
)
def test_deictic_home_and_generic_policy_shapes_remain_distinct_in_real_contract(
    question, kind, quote
):
    from app.retrieval.needs_planner import PROMPT

    catalogue = families(1)
    plan = deepcopy(EXAMPLES[0]["plan"])
    plan["needs"] = plan["needs"][:1]
    need = plan["needs"][0]
    need.update(
        need_id="n1",
        aspect=question,
        question_span={"turn_id": "u1", "quote": question},
        operation="retrieve",
        scope_question=None,
        claim_under_test=None,
        authority={
            "kind": "current",
            "value": None,
            "precision": "none",
            "source": None,
        },
        applicability={
            "kind": kind,
            "source": None if quote is None else {"turn_id": "u1", "quote": quote},
        },
        supports_need_id=None,
    )
    plan["bindings"] = [
        {
            "need_id": "n1",
            "subject_status": "topic",
            "family_ids": [],
            "ownership": "authorised_workspace",
            "ownership_source": None,
        }
    ]
    jsonschema.validate(plan, provider_needs_schema(catalogue))
    calls = []
    result = adapter(plan, calls).plan_needs(
        (UserTurn(turn_id="u1", text=question),),
        evaluated_at="2026-11-08T12:00:00Z",
        catalogue=catalogue,
    )
    assert len(calls) == 1
    assert result.plan.needs[0].applicability.kind == kind
    assert (result.plan.needs[0].applicability.source.quote if quote else None) == quote
    assert '"at the home"' in PROMPT
    assert "organisation-wide policy" in PROMPT
    # Fake output proves schema/transport/grounding only, not live model selection.


def families(count):
    return tuple(
        CatalogueFamily(family_id=UUID(int=i + 1), title=f"Synthetic family {i}")
        for i in range(count)
    )


def test_scope_enquiry_is_explicit_in_real_provider_schema_and_does_not_rewrite_retrieve():
    from app.retrieval.needs_planner import PROMPT

    plan = deepcopy(EXAMPLES[0]["plan"])
    plan["needs"][0]["operation"] = "scope_enquiry"
    plan["needs"][0]["scope_question"] = "membership"
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
    ("question", "scope_question"),
    [
        ("Is the Key Safe Procedure still just a Willow Bank thing?", "breadth"),
        ("Does the Key Safe Procedure cover Willow Bank?", "membership"),
        (
            "Under the Key Holder Procedure, is this still just for North West Region sites?",
            "breadth",
        ),
    ],
)
def test_breadth_and_membership_examples_preserve_distinct_real_contract_shapes(
    question, scope_question
):
    from app.retrieval.needs_planner import PROMPT

    location = "North West Region" if "North West" in question else "Willow Bank"
    catalogue = families(1)
    plan = deepcopy(EXAMPLES[3]["plan"])
    need = plan["needs"][0]
    need.update(
        aspect=question,
        question_span={"turn_id": "u1", "quote": question},
        operation="scope_enquiry",
        scope_question=scope_question,
        applicability={"kind": "named", "source": {"turn_id": "u1", "quote": location}},
    )
    plan["bindings"] = [
        {
            "need_id": "n1",
            "subject_status": "matched",
            "family_ids": [str(catalogue[0].family_id)],
            "ownership": "authorised_workspace",
            "ownership_source": None,
        }
    ]
    jsonschema.validate(plan, provider_needs_schema(catalogue))
    calls = []
    result = adapter(plan, calls).plan_needs(
        (UserTurn(turn_id="u1", text=question),),
        evaluated_at="2026-11-08T12:00:00Z",
        catalogue=catalogue,
    )
    assert len(calls) == 1
    assert result.plan.needs[0].scope_question == scope_question
    assert result.plan.needs[0].operation == "scope_enquiry"
    assert "still just\na Willow Bank thing?'" in PROMPT
    # Fake output proves transport and instruction presence, not live model judgement.


def test_disputed_claim_and_real_site_can_coexist_in_strict_planner_contract():
    from app.retrieval.needs import UserTurn

    question = (
        "At Riverside House, do grievances go to the Brambleford Office People team?"
    )
    catalogue = families(1)
    plan = deepcopy(EXAMPLES[0]["plan"])
    plan["needs"] = [
        {
            "need_id": "n1",
            "aspect": "Where grievances are submitted at Riverside House",
            "central": True,
            "question_span": {"turn_id": "u1", "quote": question},
            "operation": "retrieve",
            "authority": {
                "kind": "current",
                "value": None,
                "precision": "none",
                "source": None,
            },
            "fact_reference": None,
            "applicability": {
                "kind": "named",
                "source": {"turn_id": "u1", "quote": "Riverside House"},
            },
            "claim_under_test": {
                "version": "claim-under-test-v1",
                "type": "named_recipient",
                "relation": "submission_destination",
                "source": {"turn_id": "u1", "quote": "Brambleford Office People team"},
            },
            "scope_question": None,
            "supports_need_id": None,
        }
    ]
    plan["bindings"] = [
        {
            "need_id": "n1",
            "ownership": "authorised_workspace",
            "ownership_source": None,
            "subject_status": "matched",
            "family_ids": [str(catalogue[0].family_id)],
        }
    ]
    calls = []
    result = adapter(plan, calls).plan_needs(
        (UserTurn(turn_id="u1", text=question),),
        evaluated_at="2026-11-08T12:00:00Z",
        catalogue=catalogue,
    )
    assert len(calls) == 1
    assert (
        result.plan.needs[0].claim_under_test.source.quote
        == "Brambleford Office People team"
    )
    assert result.plan.needs[0].applicability.source.quote == "Riverside House"


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
                "claim_under_test": None,
                "scope_question": "membership"
                if operation == "scope_enquiry"
                else None,
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


@pytest.mark.parametrize(
    ("question", "clauses", "location_quote"),
    [
        (
            "Quick one: how long before an immediate on-site search escalates to calling the police for a missing resident, and separately, how far can a window actually open under the restrictor policy?",
            (
                "how long before an immediate on-site search escalates to calling the police for a missing resident",
                "how far can a window actually open under the restrictor policy?",
            ),
            None,
        ),
        (
            "How far can a resident's window open under the Window Restrictor Policy?",
            (
                "How far can a resident's window open under the Window Restrictor Policy?",
            ),
            None,
        ),
        (
            "Can staff at Riverside House wear the fleece under the Dress Code Policy?",
            (
                "Can staff at Riverside House wear the fleece under the Dress Code Policy?",
            ),
            "Riverside House",
        ),
        (
            "At Oakfield Lodge, what does the Window Restrictor Procedure require?",
            ("At Oakfield Lodge, what does the Window Restrictor Procedure require?",),
            "Oakfield Lodge",
        ),
    ],
)
def test_document_reference_and_real_site_are_separate_applicability_fields(
    question, clauses, location_quote
):
    from app.retrieval.needs_planner import PROMPT

    catalogue = (CatalogueFamily(family_id=UUID(int=1), title="Named policy"),)
    plan = {
        "schema_version": "evidence-needs-plan-v3",
        "decision": "plan",
        "clarification": None,
        "needs": [],
        "bindings": [],
    }
    for index, clause in enumerate(clauses, 1):
        plan["needs"].append(
            {
                "need_id": f"n{index}",
                "aspect": clause,
                "central": True,
                "question_span": {"turn_id": "user-1", "quote": clause},
                "operation": "retrieve",
                "authority": {
                    "kind": "current",
                    "value": None,
                    "precision": "none",
                    "source": None,
                },
                "fact_reference": None,
                "claim_under_test": None,
                "scope_question": None,
                "applicability": {
                    "kind": "named" if location_quote else "generic",
                    "source": {"turn_id": "user-1", "quote": location_quote}
                    if location_quote
                    else None,
                },
                "supports_need_id": None,
            }
        )
        is_topical = len(clauses) > 1 and index == 1
        plan["bindings"].append(
            {
                "need_id": f"n{index}",
                "subject_status": "topic" if is_topical else "matched",
                "family_ids": [] if is_topical else [str(catalogue[0].family_id)],
                "ownership": "authorised_workspace",
                "ownership_source": None,
            }
        )
    calls = []
    result = adapter(plan, calls).plan_needs(
        (UserTurn(turn_id="user-1", text=question),),
        evaluated_at="2026-11-06T12:00:00Z",
        catalogue=catalogue,
    )
    assert len(calls) == 1
    assert all(need.operation == "retrieve" for need in result.plan.needs)
    assert all(
        (need.applicability.source.quote if need.applicability.source else None)
        == location_quote
        for need in result.plan.needs
    )
    assert result.plan.bindings[-1].family_ids == (catalogue[0].family_id,)
    assert "trailing\n'under/according to the [named] policy or procedure'" in PROMPT
    assert "quote only that site for named applicability" in PROMPT
    # Fake output proves valid transport/contract separation, not model judgement.


@pytest.mark.parametrize(
    ("question", "location_quote"),
    [
        (
            "Does the Key Holder Procedure cover North West Region sites?",
            "North West Region",
        ),
        (
            "Does the Key Holder Procedure cover the Riverside House site?",
            "Riverside House",
        ),
        (
            "Does the Key Holder Procedure cover Willow Bank Community Service?",
            "Willow Bank Community Service",
        ),
    ],
)
def test_location_quote_excludes_incidental_trailing_qualifier_in_real_plan_contract(
    question, location_quote
):
    from app.retrieval.needs_planner import PROMPT

    catalogue = (CatalogueFamily(family_id=UUID(int=1), title="Key Holder Procedure"),)
    plan = {
        "schema_version": "evidence-needs-plan-v3",
        "decision": "plan",
        "clarification": None,
        "needs": [
            {
                "need_id": "n1",
                "aspect": question,
                "central": True,
                "question_span": {"turn_id": "user-1", "quote": question},
                "operation": "scope_enquiry",
                "scope_question": "membership",
                "authority": {
                    "kind": "current",
                    "value": None,
                    "precision": "none",
                    "source": None,
                },
                "fact_reference": None,
                "applicability": {
                    "kind": "named",
                    "source": {"turn_id": "user-1", "quote": location_quote},
                },
                "supports_need_id": None,
            }
        ],
        "bindings": [
            {
                "need_id": "n1",
                "subject_status": "matched",
                "family_ids": [str(catalogue[0].family_id)],
                "ownership": "authorised_workspace",
                "ownership_source": None,
            }
        ],
    }
    calls = []
    result = adapter(plan, calls).plan_needs(
        (UserTurn(turn_id="user-1", text=question),),
        evaluated_at="2026-11-07T12:00:00Z",
        catalogue=catalogue,
    )
    assert len(calls) == 1
    assert result.plan.needs[0].applicability.source.quote == location_quote
    assert "For 'North West Region sites', quote 'North West Region'" in PROMPT
    # The fake response proves schema/grounding, not that the model will obey the instruction.


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
        assert result.lineage.adapter_version == "structured-chat-needs-v7"
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
