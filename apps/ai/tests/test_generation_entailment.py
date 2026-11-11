"""Provider-free checks of the bounded needs entailment boundary."""

from types import SimpleNamespace

import pytest

from app.generation.entailment import (
    EntailmentRejection,
    EntailmentReview,
    PartEntailment,
    conditional_obligation_guard,
    review_input,
)
from app.generation.errors import GenerationProviderFailure
from app.generation.needs_output import OpenAIGenerationNeedsOutput
from app.generation.needs_request import GenerationNeedsRequest
from app.generation.openai_adapter import (
    NEEDS_ADAPTER_VERSION,
    NEEDS_CONTRACT_VERSION,
    NEEDS_PROMPT_VERSION,
    OpenAIGenerationProfile,
    OpenAIGenerator,
)
from tests.test_generation_needs_request import request
from tests.test_openai_generation_adapter import FakeResponses, response


def scenario(source: str, answer: str, resolved: dict[str, object] | None = None):
    raw = request()
    raw["question"] = "Does Harbour View need an asbestos register check?"
    raw["evidence"] = [raw["evidence"][2]]
    raw["constraints"]["required_sides"] = ["primary"]
    raw["evidence"][0]["evidence_id"] = "ev-01"
    raw["evidence"][0]["temporal_authority"]["need_id"] = "n1"
    raw["evidence"][0]["text"] = source
    if resolved is not None:
        raw["evidence"][0]["applicability_context"]["resolved_scope_fact"] = resolved
    raw["evidence_needs"] = [raw["evidence_needs"][1]]
    raw["evidence_needs"][0].update(
        need_id="n1",
        aspect="Does the register check apply at Harbour View?",
        applicability={
            "kind": "named",
            "source": {"turn_id": "u1", "quote": "Harbour View"},
        },
        evidence_ids=["ev-01"],
    )
    incoming = GenerationNeedsRequest.model_validate(raw)
    output = OpenAIGenerationNeedsOutput.model_validate(
        {
            "outcome": "answered",
            "answer_parts": [{"text": answer, "evidence_ids": ["ev-01"]}],
            "unsupported_aspects": [],
            "insufficiency_reason": None,
            "aspect_links": [
                {
                    "need_id": "n1",
                    "answer_part_indices": [0],
                    "unsupported_aspect_indices": [],
                }
            ],
        }
    )
    return incoming, output


DUTY = "Check the asbestos register before any work disturbing a fabric surface at an in-scope site."
OVERCLAIM = (
    "Harbour View must have the asbestos register checked before drilling into a wall."
)


def test_i01_unqualified_named_site_obligation_is_rejected():
    incoming, output = scenario(DUTY, OVERCLAIM)
    with pytest.raises(EntailmentRejection) as error:
        conditional_obligation_guard(incoming, output)
    assert error.value.code == "site_scope_condition_unproven"
    assert error.value.missing_condition == "at an in-scope site"


def test_retained_condition_and_visible_gap_pass():
    incoming, output = scenario(
        DUTY,
        "At an in-scope site, staff must check the asbestos register. "
        "Whether Harbour View is in scope is not established by this passage.",
    )
    conditional_obligation_guard(incoming, output)


def test_genuinely_universal_unconditional_claim_passes():
    incoming, output = scenario(
        "Every site must check the asbestos register before drilling.", OVERCLAIM
    )
    conditional_obligation_guard(incoming, output)


def test_separately_resolved_matching_scope_fact_satisfies_condition():
    incoming, output = scenario(DUTY, OVERCLAIM)
    version = incoming.evidence[0].temporal_authority["document_version_id"]
    raw = incoming.model_dump(mode="json")
    raw["evidence"][0]["applicability_context"]["resolved_scope_fact"] = {
        "result": "applies",
        "location_name": "Harbour View",
        "condition": "at an in-scope site",
        "document_version_id": version,
    }
    conditional_obligation_guard(GenerationNeedsRequest.model_validate(raw), output)


def test_scope_fact_saying_not_covered_cannot_satisfy_condition():
    incoming, output = scenario(DUTY, OVERCLAIM)
    version = incoming.evidence[0].temporal_authority["document_version_id"]
    raw = incoming.model_dump(mode="json")
    raw["evidence"][0]["applicability_context"]["resolved_scope_fact"] = {
        "result": "does_not_apply",
        "location_name": "Harbour View",
        "condition": "at an in-scope site",
        "document_version_id": version,
    }
    with pytest.raises(EntailmentRejection):
        conditional_obligation_guard(GenerationNeedsRequest.model_validate(raw), output)


def test_unrelated_subject_to_phrase_does_not_trigger_narrow_guard():
    incoming, output = scenario(
        "Funding is subject to annual review. Every site must check the asbestos register before drilling.",
        OVERCLAIM,
    )
    conditional_obligation_guard(incoming, output)


def test_absent_obligation_is_blocked_by_independent_review():
    incoming, output = scenario("The register is kept by Facilities.", OVERCLAIM)
    review = EntailmentReview(
        parts=[
            PartEntailment(
                part_index=0,
                verdict="unsupported",
                missing_condition="No cited passage requires a register check before drilling.",
            )
        ]
    )
    responses = FakeResponses([response(output), response(review)])
    adapter = OpenAIGenerator(
        api_key="test-only",
        client=SimpleNamespace(responses=responses),
        profile=OpenAIGenerationProfile(
            contract_version=NEEDS_CONTRACT_VERSION,
            prompt_version=NEEDS_PROMPT_VERSION,
            adapter_version=NEEDS_ADAPTER_VERSION,
        ),
    )
    with pytest.raises(GenerationProviderFailure) as error:
        adapter.generate_needs(incoming)
    assert error.value.error.validation.code == "entailment_unsupported"
    assert len(responses.calls) == 2


def alarm_scenario(question: str, answer: str):
    incoming, output = scenario(
        "If the fire alarm sounds, call the fire brigade and evacuate residents.",
        answer,
    )
    raw = incoming.model_dump(mode="json")
    raw["question"] = question
    raw["evidence_needs"][0]["aspect"] = "What should staff do about the alarm?"
    return GenerationNeedsRequest.model_validate(raw), output


@pytest.mark.parametrize(
    "question",
    [
        "An alarm sounded overnight at Riverside House. What should I do?",
        "I've been alerted to an alarm at Riverside House — what procedure follows?",
    ],
)
def test_unspecified_alarm_cannot_trigger_categorical_fire_response(question):
    incoming, output = alarm_scenario(
        question, "Call the fire brigade and evacuate residents immediately."
    )
    with pytest.raises(EntailmentRejection) as error:
        conditional_obligation_guard(incoming, output)
    assert error.value.code == "entailment_uncertain"
    assert error.value.missing_condition == "the kind of alarm was not established"


def test_explicit_fire_alarm_allows_fire_response():
    incoming, output = alarm_scenario(
        "The fire alarm sounded at Riverside House. What should I do?",
        "Call the fire brigade and evacuate residents immediately.",
    )
    conditional_obligation_guard(incoming, output)


def test_unspecified_alarm_allows_conditional_fire_guidance():
    incoming, output = alarm_scenario(
        "An alarm sounded at Riverside House. What should I do?",
        "If it is a fire alarm, call the fire brigade and evacuate residents.",
    )
    conditional_obligation_guard(incoming, output)
    assert '"user_question":"An alarm sounded at Riverside House.' in review_input(
        incoming, output
    )


def test_other_alarm_type_does_not_license_fire_response():
    incoming, output = alarm_scenario(
        "The burglar alarm sounded at Riverside House. What should I do?",
        "Call the fire brigade and evacuate residents immediately.",
    )
    with pytest.raises(EntailmentRejection):
        conditional_obligation_guard(incoming, output)
