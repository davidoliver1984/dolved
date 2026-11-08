import json
from copy import deepcopy
from pathlib import Path
from uuid import uuid4

import httpx
import pytest
from fastapi.testclient import TestClient
from pydantic import SecretStr, ValidationError

from app.main import app
from app.retrieval.needs import (
    CatalogueFamily,
    EvidenceNeedsPlan,
    NeedsPlanRequest,
    NeedsPlanResponse,
    UserTurn,
)
from app.retrieval.needs_planner import (
    NeedsValidationFailure,
    StructuredChatNeedsPlanner,
    _normalize_scope_claim_conflicts,
)
from app.retrieval.planner import RetrievalPlanningError
from app.retrieval.routes import needs_planner_dependency
from tests.test_retrieval_routes import signed_headers

ROOT = Path("/contracts/http/retrieval-needs/v3")
EXAMPLES = json.loads((ROOT / "examples.json").read_text())
MUTATIONS = json.loads((ROOT / "invalid-mutations.json").read_text())


def test_prompt_distinguishes_requested_tasks_from_source_count_and_named_binding():
    from app.retrieval.needs_planner import PROMPT

    assert "Number of possible source documents is not need count" in PROMPT
    assert "two independently requested topics require two needs" in PROMPT
    assert "use topic\nwith empty family_ids" in PROMPT
    assert "never broaden an\nunavailable named subject into topic" in PROMPT
    assert (
        "Decide whether the user named a document from the original user turns"
        in PROMPT
    )
    assert "do not add an unspoken policy title" in PROMPT
    assert (
        "If the user expressly names a particular policy or guidance document" in PROMPT
    )
    # This proves instruction presence, not model judgement/generalisation.


@pytest.mark.parametrize(
    "question,family_title",
    [
        ("May I accept a reference letter brought in by an applicant?", None),
        ("Can staff rely on a candidate's own reference letter?", None),
        (
            "What does the Recruitment and Selection Policy say about references?",
            "Recruitment and Selection Policy",
        ),
        (
            "Under Safer Recruitment Guidance, can I accept an open reference?",
            "Safer Recruitment Guidance",
        ),
    ],
)
def test_reference_rule_topic_and_explicit_document_bindings_transport(
    question, family_title
):
    # Fake output proves the real schema/transport accepts the intended distinction;
    # the model's judgement remains a live-regression question.
    sample = deepcopy(EXAMPLES[0])
    sample["turns"] = [{"turn_id": "u1", "text": question}]
    sample["plan"]["needs"] = sample["plan"]["needs"][:1]
    need = sample["plan"]["needs"][0]
    need.update(
        aspect=question,
        question_span={"turn_id": "u1", "quote": question},
        operation="retrieve",
        authority={
            "kind": "current",
            "value": None,
            "precision": "none",
            "source": None,
        },
        supports_need_id=None,
    )
    family = CatalogueFamily(
        family_id=uuid4(), title=family_title or "Recruitment and Selection Policy"
    )
    sample["plan"]["bindings"] = [
        {
            "need_id": "n1",
            "subject_status": "matched" if family_title else "topic",
            "family_ids": [str(family.family_id)] if family_title else [],
            "ownership": "authorised_workspace",
            "ownership_source": None,
        }
    ]
    result = adapter(sample["plan"], []).plan_needs(
        turns(sample), evaluated_at="2026-11-06T12:00:00Z", catalogue=(family,)
    )
    assert result.plan.bindings[0].subject_status == (
        "matched" if family_title else "topic"
    )


@pytest.mark.parametrize(
    "value,precision,code",
    [
        ("last month", "month", "fact_date_precision"),
        ("2025-02-30", "day", "fact_date_calendar"),
        ("2025-02-01/2025-01-01", "interval", "fact_date_interval_reversed"),
        ("2025-01-01", "interval", "fact_date_interval_shape"),
    ],
)
def test_fact_date_failure_diagnostics_are_allowlisted(value, precision, code):
    from pydantic import ValidationError

    from app.retrieval.needs import FactReference

    try:
        FactReference.model_validate(
            {
                "value": value,
                "precision": precision,
                "source": {"turn_id": "user-1", "quote": "PRIVATE"},
            }
        )
    except ValidationError as exception:
        failure = NeedsValidationFailure("schema", exception)
    else:
        pytest.fail("invalid fact date accepted")
    assert failure.validation["code"] == code
    assert "PRIVATE" not in json.dumps(failure.validation)
    assert value not in json.dumps(failure.validation)


def test_safe_failure_details_exclude_input_exception_context_and_unknown_keys():
    from pydantic import ValidationError

    from app.retrieval.needs import NeedBinding

    secret = "PRIVATE-CONTENT-MUST-NOT-LEAK"
    try:
        NeedBinding.model_validate({"need_id": secret})
    except ValidationError as exception:
        failure = NeedsValidationFailure("schema", exception)
    assert failure.validation == {
        "stage": "schema",
        "field": "need_id",
        "code": "literal_error",
    }
    assert secret not in json.dumps(failure.validation)
    failure = NeedsValidationFailure(
        "catalogue", ValueError("every need requires exactly one catalogue binding")
    )
    assert failure.validation["code"] == "binding_coverage"
    failure = NeedsValidationFailure("grounding", ValueError(secret))
    assert failure.validation["code"] == "invalid_value"
    assert secret not in json.dumps(failure.validation)


@pytest.mark.parametrize(
    ("operation", "scope_question", "claim_present", "code"),
    [
        ("scope_enquiry", None, False, "scope_question_required"),
        ("retrieve", "membership", False, "scope_question_misplaced"),
        ("scope_enquiry", "membership", True, "scope_claim_conflict"),
    ],
)
def test_scope_claim_cross_field_failures_have_distinct_safe_codes(
    operation, scope_question, claim_present, code
):
    from pydantic import ValidationError

    plan = deepcopy(EXAMPLES[0]["plan"])
    need = plan["needs"][1]
    need["need_id"] = "n1"
    need["supports_need_id"] = None
    need["operation"] = operation
    need["scope_question"] = scope_question
    need["claim_under_test"] = (
        {
            "version": "claim-under-test-v1",
            "type": "named_recipient",
            "relation": "submission_destination",
            "source": {"turn_id": "u1", "quote": "PRIVATE-CLAIM"},
        }
        if claim_present
        else None
    )
    plan["needs"] = [need]
    plan["bindings"] = [{**plan["bindings"][1], "need_id": "n1"}]
    with pytest.raises(ValidationError) as caught:
        EvidenceNeedsPlan.model_validate(plan)
    safe = NeedsValidationFailure("schema", caught.value).validation
    assert safe == {"stage": "schema", "field": "needs.0", "code": code}
    assert "PRIVATE-CLAIM" not in json.dumps(safe)


@pytest.mark.parametrize(
    "fault",
    [None, "foreign", "missing", "duplicate", "ownership_location", "ownership_quote"],
)
def test_catalogue_binding_is_complete_authorised_and_separate_from_location(fault):
    example = deepcopy(EXAMPLES[0])
    family = CatalogueFamily(family_id=uuid4(), title="Records procedure")
    binding = example["plan"]["bindings"][0]
    binding.update(subject_status="matched", family_ids=[str(family.family_id)])
    if fault == "foreign":
        binding["family_ids"] = [str(uuid4())]
    elif fault == "missing":
        example["plan"]["bindings"].pop()
    elif fault == "duplicate":
        binding["family_ids"] *= 2
    elif fault in {"ownership_location", "ownership_quote"}:
        binding.update(
            ownership="external_tenant",
            ownership_source=example["plan"]["needs"][0]["question_span"].copy(),
        )
        if fault == "ownership_location":
            example["plan"]["needs"][0]["applicability"] = {
                "kind": "relational",
                "source": binding["ownership_source"],
            }
        else:
            binding["ownership_source"]["quote"] = "invented ownership"
    calls = []
    if fault is None:
        result = adapter(example["plan"], calls).plan_needs(
            turns(example), evaluated_at="2026-09-06T12:00:00Z", catalogue=(family,)
        )
        assert str(result.plan.bindings[0].family_ids[0]) == str(family.family_id)
        sent = json.loads(calls[0]["messages"][1]["content"])
        assert sent["catalogue"] == [family.model_dump(mode="json")]
    else:
        with pytest.raises(RetrievalPlanningError):
            adapter(example["plan"], calls).plan_needs(
                turns(example), evaluated_at="2026-09-06T12:00:00Z", catalogue=(family,)
            )
    assert len(calls) == 1


def turns(example):
    return tuple(UserTurn.model_validate(t) for t in example["turns"])


@pytest.mark.parametrize("example", EXAMPLES, ids=lambda e: e["name"])
def test_shared_grounded_examples(example):
    EvidenceNeedsPlan.model_validate(example["plan"]).validate_grounding(turns(example))


@pytest.mark.parametrize("mutation", MUTATIONS, ids=lambda m: m["name"])
def test_shared_semantic_rejections(mutation):
    example = deepcopy(EXAMPLES[mutation["example"]])
    node = example["plan"]
    for key in mutation["path"][:-1]:
        node = node[key]
    node[mutation["path"][-1]] = mutation["value"]
    with pytest.raises(ValueError):
        EvidenceNeedsPlan.model_validate(example["plan"]).validate_grounding(
            turns(example)
        )


@pytest.mark.parametrize(
    "name,model",
    [
        ("plan", EvidenceNeedsPlan),
        ("request", NeedsPlanRequest),
        ("response", NeedsPlanResponse),
    ],
)
def test_shared_schema_matches_real_producer(name, model):
    assert (
        json.loads((ROOT / f"{name}.schema.json").read_text())
        == model.model_json_schema()
    )


def adapter(output, calls):
    def handle(request):
        calls.append(json.loads(request.content))
        return httpx.Response(
            200,
            json={
                "choices": [{"message": {"content": json.dumps(output)}}],
                "usage": {"prompt_tokens": 120, "completion_tokens": 90},
            },
        )

    return StructuredChatNeedsPlanner(
        api_url="https://not-dispatched.invalid",
        api_key=SecretStr("fake"),
        provider_name="openai",
        model="gpt-5-mini",
        timeout_seconds=1,
        client=httpx.Client(transport=httpx.MockTransport(handle)),
    )


def test_scope_claim_collision_normalizes_only_operation_and_scope_question(caplog):
    import logging

    caplog.set_level(logging.INFO, logger="retrieval.needs.planner")
    sample = deepcopy(EXAMPLES[3])
    question = "Do formal grievances go to the Brambleford Office People team?"
    sample["turns"] = [{"turn_id": "u1", "text": question}]
    need = sample["plan"]["needs"][0]
    need.update(
        aspect=question,
        question_span={"turn_id": "u1", "quote": question},
        operation="scope_enquiry",
        scope_question="membership",
        applicability={"kind": "generic", "source": None},
        claim_under_test={
            "version": "claim-under-test-v1",
            "type": "named_recipient",
            "relation": "submission_destination",
            "source": {"turn_id": "u1", "quote": "Brambleford Office People team"},
        },
    )
    with pytest.raises(ValidationError):
        EvidenceNeedsPlan.model_validate(sample["plan"])
    original = deepcopy(sample["plan"])
    calls = []
    result = adapter(original, calls).plan_needs(
        turns(sample), evaluated_at="2026-11-08T09:00:00Z"
    )
    expected = deepcopy(original)
    expected["needs"][0]["operation"] = "retrieve"
    expected["needs"][0]["scope_question"] = None
    assert result.plan.model_dump(mode="json") == expected
    assert len(calls) == 1
    assert "Scope/claim conflict normalized." in caplog.text
    assert "Brambleford" not in caplog.text


def test_scope_claim_normalizer_preserves_other_needs_and_site_constraint():
    sample = deepcopy(EXAMPLES[3]["plan"])
    need = sample["needs"][0]
    need["operation"] = "scope_enquiry"
    need["scope_question"] = "membership"
    need["claim_under_test"] = {
        "version": "claim-under-test-v1",
        "type": "named_recipient",
        "relation": "submission_destination",
        "source": {"turn_id": "u1", "quote": "workplace hazard"},
    }
    need["applicability"] = {
        "kind": "named",
        "source": {"turn_id": "u1", "quote": "workplace"},
    }
    before = deepcopy(sample)
    output, count = _normalize_scope_claim_conflicts(json.dumps(sample))
    after = json.loads(output)
    assert count == 1
    assert after["needs"][0] == {
        **before["needs"][0],
        "operation": "retrieve",
        "scope_question": None,
    }
    assert after["bindings"] == before["bindings"]
    assert after["needs"][0]["applicability"] == before["needs"][0]["applicability"]
    for operation, claim, scope in [
        ("scope_enquiry", None, "breadth"),
        ("retrieve", need["claim_under_test"], None),
    ]:
        unchanged = deepcopy(before)
        unchanged["needs"][0].update(
            operation=operation, claim_under_test=claim, scope_question=scope
        )
        text = json.dumps(unchanged)
        assert _normalize_scope_claim_conflicts(text) == (text, 0)


def test_scope_claim_normalizer_keeps_malformed_and_ungrounded_claims_rejected():
    sample = deepcopy(EXAMPLES[3])
    need = sample["plan"]["needs"][0]
    need.update(
        operation="scope_enquiry",
        scope_question="membership",
        claim_under_test={
            "version": "claim-under-test-v1",
            "type": "named_recipient",
            "relation": "submission_destination",
            "source": {"turn_id": "u1", "quote": "not in the user turn"},
        },
    )
    with pytest.raises(NeedsValidationFailure) as caught:
        adapter(sample["plan"], []).plan_needs(
            turns(sample), evaluated_at="2026-11-08T09:00:00Z"
        )
    assert caught.value.validation["code"] == "reference_grounding"
    need["claim_under_test"]["relation"] = "invented"
    with pytest.raises(NeedsValidationFailure) as caught:
        adapter(sample["plan"], []).plan_needs(
            turns(sample), evaluated_at="2026-11-08T09:00:00Z"
        )
    assert caught.value.validation["stage"] == "schema"


def test_out_of_order_needs_reconcile_provenance_bindings_and_support_edges():
    example = deepcopy(EXAMPLES[0])
    expected = deepcopy(example["plan"])
    families = tuple(CatalogueFamily(family_id=uuid4(), title=t) for t in ("A", "B"))
    for binding, family in zip(expected["bindings"], families, strict=True):
        binding.update(subject_status="matched", family_ids=[str(family.family_id)])
    raw = deepcopy(expected)
    raw["needs"].reverse()
    raw["bindings"].reverse()
    remap = {"n1": "n2", "n2": "n1"}
    for need in raw["needs"]:
        need["need_id"] = remap[need["need_id"]]
        need["supports_need_id"] = remap.get(need["supports_need_id"])
    for binding in raw["bindings"]:
        binding["need_id"] = remap[binding["need_id"]]
    with pytest.raises(ValueError, match="question order"):
        EvidenceNeedsPlan.model_validate(raw).validate_grounding(turns(example))
    result = adapter(raw, []).plan_needs(
        turns(example), evaluated_at="2026-09-06T12:00:00Z", catalogue=families
    )
    assert result.plan.model_dump(mode="json") == expected
    assert result.plan.in_question_order(turns(example)) == result.plan
    raw["needs"][0]["question_span"]["quote"] = "not in the user turn"
    with pytest.raises(NeedsValidationFailure) as caught:
        adapter(raw, []).plan_needs(
            turns(example), evaluated_at="2026-09-06T12:00:00Z", catalogue=families
        )
    assert caught.value.validation["code"] == "reference_grounding"


def test_larger_combined_budget_accepts_output_after_old_reasoning_ceiling():
    """Fake transport proves parameters/handling, not a model output guarantee."""
    example = EXAMPLES[0]
    calls = []

    def handle(request):
        sent = json.loads(request.content)
        calls.append(sent)
        assert sent["max_completion_tokens"] == 25000
        assert sent["reasoning_effort"] == "low"
        return httpx.Response(
            200,
            json={
                "choices": [
                    {
                        "finish_reason": "stop",
                        "message": {"content": json.dumps(example["plan"])},
                    }
                ],
                "usage": {
                    "prompt_tokens": 120,
                    "completion_tokens": 6000,
                    "completion_tokens_details": {"reasoning_tokens": 5000},
                },
            },
        )

    planner = StructuredChatNeedsPlanner(
        api_url="https://not-dispatched.invalid",
        api_key=SecretStr("fake"),
        provider_name="openai",
        model="gpt-5-mini",
        timeout_seconds=1,
        client=httpx.Client(transport=httpx.MockTransport(handle)),
    )
    result = planner.plan_needs(turns(example), evaluated_at="2026-09-06T12:00:00Z")
    assert result.plan.model_dump(mode="json") == example["plan"]
    assert result.usage.output_tokens == 6000
    assert len(calls) == 1


@pytest.mark.parametrize(
    "reason,content,code",
    [
        ("length", '{"needs":[', "output_truncated"),
        ("length", "{}", "output_truncated"),
        ("stop", '{"needs":[', "json_invalid"),
        ("content_filter", "", "output_not_completed"),
    ],
)
def test_completion_failure_keeps_safe_usage_and_only_completed_output_retries(
    reason, content, code
):
    calls = []

    def handle(request):
        calls.append(request)
        return httpx.Response(
            200,
            json={
                "choices": [{"finish_reason": reason, "message": {"content": content}}],
                "usage": {
                    "prompt_tokens": 100,
                    "completion_tokens": 4096,
                    "completion_tokens_details": {"reasoning_tokens": 4000},
                    "secret": "PRIVATE",
                },
            },
        )

    planner = StructuredChatNeedsPlanner(
        api_url="https://not-dispatched.invalid",
        api_key=SecretStr("fake"),
        provider_name="openai",
        model="gpt-5-mini",
        timeout_seconds=1,
        client=httpx.Client(transport=httpx.MockTransport(handle)),
    )
    with pytest.raises(NeedsValidationFailure) as caught:
        planner.plan_needs(turns(EXAMPLES[0]), evaluated_at="2026-09-06T12:00:00Z")
    assert caught.value.validation["code"] == code
    assert caught.value.completion == {
        "finish_reason": reason,
        "input_tokens": 100,
        "output_tokens": 4096,
        "reasoning_tokens": 4000,
    }
    assert len(calls) == (2 if reason == "stop" else 1)
    assert len(caught.value.validation_attempts) == len(calls)
    assert caught.value.category == "invalid_grounded_needs_plan"


def test_real_transport_preserves_month_and_mixed_needs_without_normalisation():
    for example in EXAMPLES[:3]:
        calls = []
        result = adapter(example["plan"], calls).plan_needs(
            turns(example), evaluated_at="2026-09-06T12:00:00Z"
        )
        assert result.plan.model_dump(mode="json") == example["plan"]
        assert result.lineage.contract_schema_version == "evidence-needs-plan-v3"
        assert result.usage.request_count == 1
        assert result.usage.input_tokens == 120
        assert len(calls) == 1
        assert calls[0]["max_completion_tokens"] == 25000
        assert calls[0]["reasoning_effort"] == "low"
        assert (
            json.loads(calls[0]["messages"][1]["content"])["user_turns"]
            == example["turns"]
        )


@pytest.mark.parametrize(
    "guidance",
    [
        "Which mileage allowance should I enter on my travel form?",
        "What retention period belongs in the supplier register?",
        "Which safety classification goes in this inspection checklist?",
    ],
)
def test_topical_guidance_transport_does_not_require_a_form_family(guidance):
    # Recording output proves contract transport, not live language understanding.
    sample = deepcopy(EXAMPLES[0])
    question = "What changed in the form? " + guidance
    sample["turns"] = [{"turn_id": "user-1", "text": question}]
    plan = sample["plan"]
    plan["needs"] = plan["needs"][:2]
    for i, need in enumerate(plan["needs"]):
        quote = "What changed in the form?" if i == 0 else guidance
        need.update(
            aspect=quote,
            question_span={"turn_id": "user-1", "quote": quote},
            fact_reference=None,
            applicability={"kind": "generic", "source": None},
            supports_need_id=None,
        )
        need["operation"] = "compare" if i == 0 else "retrieve"
        need["authority"] = (
            {
                "kind": "previous",
                "value": quote,
                "precision": "named",
                "source": {"turn_id": "user-1", "quote": quote},
            }
            if i == 0
            else {"kind": "current", "value": None, "precision": "none", "source": None}
        )
    plan["bindings"] = [
        {
            "need_id": n["need_id"],
            "subject_status": "topic",
            "family_ids": [],
            "ownership": "authorised_workspace",
            "ownership_source": None,
        }
        for n in plan["needs"]
    ]
    calls = []
    result = adapter(plan, calls).plan_needs(
        turns(sample), evaluated_at="2026-09-06T12:00:00Z"
    )
    assert result.plan.bindings[1].family_ids == ()
    assert result.plan.needs[1].aspect == guidance
    assert calls[0]["reasoning_effort"] == "low"
    assert calls[0]["response_format"]["json_schema"]["strict"] is True


def test_invalid_grounding_never_falls_back_or_exposes_output():
    example = deepcopy(EXAMPLES[0])
    example["plan"]["needs"][0]["question_span"]["quote"] = "private invented text"
    calls = []
    with pytest.raises(RetrievalPlanningError) as error:
        adapter(example["plan"], calls).plan_needs(
            turns(example), evaluated_at="2026-09-06T12:00:00Z"
        )
    assert error.value.category == "invalid_grounded_needs_plan"
    assert "private" not in str(error.value)
    assert len(calls) == 1


@pytest.mark.parametrize(
    "month,canonical", [("June 2026", "2026-06"), ("February 2025", "2025-02")]
)
def test_transition_month_uses_canonical_value_and_verbatim_quote(month, canonical):
    example = deepcopy(EXAMPLES[1])
    question = f"What changed during {month}?"
    example["turns"][0]["text"] = question
    need = example["plan"]["needs"][0]
    need["question_span"]["quote"] = question
    need["authority"]["source"]["quote"] = month
    need["authority"]["value"] = canonical
    calls = []
    result = adapter(example["plan"], calls).plan_needs(
        turns(example), evaluated_at="2026-09-06T12:00:00Z"
    )
    assert result.plan.needs[0].authority.value == canonical
    assert result.plan.needs[0].authority.precision == "month"
    assert result.plan.needs[0].authority.source.quote == month
    assert "month=YYYY-MM" in calls[0]["messages"][0]["content"]
    assert "Temporal selection precedence" in calls[0]["messages"][0]["content"]
    # Exact live failure shape remains rejected, never silently repaired.
    need["authority"]["value"] = month
    with pytest.raises(RetrievalPlanningError):
        adapter(example["plan"], []).plan_needs(
            turns(example), evaluated_at="2026-09-06T12:00:00Z"
        )
    # Nor may the planner manufacture an exact day from the month wording.
    need["authority"].update(
        kind="transition_boundary", precision="day", value=canonical + "-01"
    )
    with pytest.raises(RetrievalPlanningError):
        adapter(example["plan"], []).plan_needs(
            turns(example), evaluated_at="2026-09-06T12:00:00Z"
        )


@pytest.mark.parametrize("field", ["value", "precision", "source"])
def test_non_current_reference_still_rejects_missing_components(field):
    example = deepcopy(EXAMPLES[0])
    authority = example["plan"]["needs"][0]["authority"]
    assert authority["kind"] != "current"
    authority[field] = "none" if field == "precision" else None
    calls = []
    planner = adapter(example["plan"], calls)
    assert planner.lineage().prompt_version == "bounded-evidence-needs-v20"
    with pytest.raises(RetrievalPlanningError) as error:
        planner.plan_needs(turns(example), evaluated_at="2026-09-06T12:00:00Z")
    assert error.value.category == "invalid_grounded_needs_plan"
    assert len(calls) == 1
    instructions = calls[0]["messages"][0]["content"]
    assert 'precision="named"' in instructions
    assert "Do not leave value or source null" in instructions
    assert "SELF-CONTAINED retrieval question" in instructions
    assert "does NOT require a supplied date" in instructions
    assert "Tenant/account ownership" in instructions
    assert "must never be widened" in instructions


@pytest.mark.parametrize(
    ("question", "family_title", "location"),
    [
        (
            "Did the January medication audit at Willow Bank find any problems?",
            "Memo: Medication Audit Outcome — Willow Bank, January 2026",
            "Willow Bank",
        ),
        (
            "What actions were recorded in the March infection-control meeting minutes at Oakfield Lodge?",
            "Infection-control meeting minutes — Oakfield Lodge, March 2026",
            "Oakfield Lodge",
        ),
        (
            "Summarise the 2025 fire-drill outcome memo for Riverside House.",
            "Fire-drill outcome memo — Riverside House, 2025",
            "Riverside House",
        ),
    ],
)
def test_point_in_time_record_date_is_identity_not_policy_version_selector(
    question, family_title, location
):
    family = CatalogueFamily(family_id=uuid4(), title=family_title)
    current = {
        "schema_version": "evidence-needs-plan-v3",
        "decision": "plan",
        "clarification": None,
        "needs": [
            {
                "need_id": "n1",
                "aspect": question,
                "central": True,
                "question_span": {"turn_id": "user-1", "quote": question},
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
                    "source": {"turn_id": "user-1", "quote": location},
                },
                "supports_need_id": None,
            }
        ],
        "bindings": [
            {
                "need_id": "n1",
                "subject_status": "matched",
                "family_ids": [str(family.family_id)],
                "ownership": "authorised_workspace",
                "ownership_source": None,
            }
        ],
    }
    calls = []
    result = adapter(current, calls).plan_needs(
        (UserTurn(turn_id="user-1", text=question),),
        evaluated_at="2026-09-07T12:00:00Z",
        catalogue=(family,),
    )
    assert result.plan.needs[0].authority.kind == "current"
    assert result.plan.needs[0].applicability.source is not None
    assert result.plan.needs[0].applicability.source.quote == location
    assert result.plan.bindings[0].family_ids == (family.family_id,)
    instructions = calls[0]["messages"][0]["content"]
    assert "point-in-time record" in instructions
    assert "does not select a historical version" in instructions
    assert "policy, procedure, form or other versioned\nauthority" in instructions
    assert "pre-nominal date in a definite record phrase" in instructions
    assert "smallest\nexact location phrase" in instructions

    historical = deepcopy(current)
    historical_question = "Which rule did the January 2025 Medication Policy require?"
    historical["needs"][0].update(
        aspect=historical_question,
        question_span={"turn_id": "user-1", "quote": historical_question},
        applicability={"kind": "generic", "source": None},
    )
    historical["needs"][0]["authority"] = {
        "kind": "period",
        "value": "2025-01",
        "precision": "month",
        "source": {"turn_id": "user-1", "quote": "January 2025"},
    }
    policy = CatalogueFamily(family_id=uuid4(), title="Medication Policy")
    historical["bindings"][0]["family_ids"] = [str(policy.family_id)]
    result = adapter(historical, []).plan_needs(
        (UserTurn(turn_id="user-1", text=historical_question),),
        evaluated_at="2026-09-07T12:00:00Z",
        catalogue=(policy,),
    )
    assert result.plan.needs[0].authority.kind == "period"
    assert result.plan.needs[0].authority.value == "2025-01"


@pytest.mark.parametrize("fault", [None, "purpose", "workspace", "turns"])
def test_signed_needs_endpoint_isolated_and_replay_safe(fault):
    example = EXAMPLES[0]
    workspace, request_id = str(uuid4()), str(uuid4())
    path = "/api/internal/retrieval/plan-needs"
    payload = {
        "contract_version": 2,
        "request_id": request_id,
        "workspace_id": workspace,
        "evaluated_at": "2026-09-06T12:00:00Z",
        "user_turns": example["turns"],
    }
    if fault == "turns":
        payload["user_turns"] = example["turns"] * 2
    body = json.dumps(payload).encode()
    calls = []
    app.dependency_overrides[needs_planner_dependency] = lambda: adapter(
        example["plan"], calls
    )
    try:
        headers = signed_headers(
            path,
            body,
            str(uuid4()) if fault == "workspace" else workspace,
            request_id,
            "retrieval.plan" if fault == "purpose" else "retrieval.plan-needs",
        )
        client = TestClient(app)
        response = client.post(path, content=body, headers=headers)
        assert response.status_code == (
            200 if fault is None else 422 if fault == "turns" else 401
        )
        assert len(calls) == (1 if fault is None else 0)
        if fault is None:
            NeedsPlanResponse.model_validate(response.json())
            assert client.post(path, content=body, headers=headers).status_code == 401
    finally:
        app.dependency_overrides.clear()
