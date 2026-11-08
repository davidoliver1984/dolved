import copy
import json
from pathlib import Path

import pytest

from app.generation.needs_output import GenerationNeedsResult
from app.generation.needs_request import GenerationNeedsRequest


def request():
    return json.loads(
        Path("/contracts/http/generation-needs/v2/request.example.json").read_text()
    )


def test_request_preserves_independent_current_support_and_pair():
    parsed = GenerationNeedsRequest.model_validate(request())
    assert parsed.evidence_needs[1].evidence_ids == ("ev-03",)
    assert parsed.evidence_needs[0].evidence_ids == ("ev-01", "ev-02")
    assert len(parsed.legacy_request().evidence) == 3


def test_two_comparison_pairs_keep_separate_ownership_within_k5():
    raw = request()
    second = raw["evidence_needs"][1]
    second["operation"] = "compare"
    second["authority"] = copy.deepcopy(raw["evidence_needs"][0]["authority"])
    paired = copy.deepcopy(raw["evidence"][1])
    paired["evidence_id"] = "ev-04"
    paired["temporal_authority"]["need_id"] = "n2"
    paired["temporal_authority"]["document_family_id"] = raw["evidence"][2][
        "temporal_authority"
    ]["document_family_id"]
    raw["evidence"].append(paired)
    second["evidence_ids"] = ["ev-03", "ev-04"]
    parsed = GenerationNeedsRequest.model_validate(raw)
    assert len(parsed.evidence) == 4
    assert parsed.evidence_needs[1].evidence_ids == ("ev-03", "ev-04")
    raw["evidence_needs"].append(copy.deepcopy(second))
    raw["evidence_needs"][2]["need_id"] = "n3"
    with pytest.raises(ValueError):
        GenerationNeedsRequest.model_validate(raw)


@pytest.mark.parametrize(
    "mutation",
    [
        "owner",
        "family",
        "same_version",
        "unknown",
        "duplicate",
        "unassigned",
        "edge",
        "extra_lane",
    ],
)
def test_invalid_ownership_and_pairing_fail_closed(mutation):
    raw = request()
    if mutation == "owner":
        raw["evidence"][1]["temporal_authority"]["need_id"] = "n2"
    elif mutation == "family":
        raw["evidence"][1]["temporal_authority"]["document_family_id"] = raw[
            "evidence"
        ][2]["temporal_authority"]["document_family_id"]
    elif mutation == "same_version":
        raw["evidence"][1]["document_id"] = raw["evidence"][0]["document_id"]
    elif mutation == "unknown":
        raw["evidence_needs"][1]["evidence_ids"] = ["ev-99"]
    elif mutation == "duplicate":
        raw["evidence_needs"][1]["evidence_ids"] = ["ev-03", "ev-03"]
    elif mutation == "unassigned":
        raw["evidence_needs"][1]["evidence_ids"] = []
    elif mutation == "edge":
        raw["evidence_needs"][1]["supports_need_id"] = "n1"
    else:
        raw["evidence_needs"][1]["operation"] = "compare"
    with pytest.raises(ValueError):
        GenerationNeedsRequest.model_validate(raw)


def test_missing_need_is_an_explicit_gap_not_borrowed_support():
    raw = request()
    raw["evidence"] = [copy.deepcopy(raw["evidence"][2])]
    raw["constraints"]["required_sides"] = ["primary"]
    raw["evidence_needs"][0]["evidence_ids"] = []
    raw["evidence_needs"][0]["retrieval_outcome"] = "COMPARISON_SCOPE_INCOMPLETE"
    parsed = GenerationNeedsRequest.model_validate(raw)
    assert parsed.evidence_needs[0].evidence_ids == ()
    assert parsed.evidence_needs[1].evidence_ids == ("ev-03",)


def test_shared_request_schema_matches_model_and_example():
    from jsonschema import Draft202012Validator

    schema = json.loads(
        Path("/contracts/http/generation-needs/v2/request.schema.json").read_text()
    )
    Draft202012Validator.check_schema(schema)
    Draft202012Validator(schema).validate(request())
    assert schema == GenerationNeedsRequest.model_json_schema()


def test_result_is_bound_to_the_actual_assembled_request():
    incoming = GenerationNeedsRequest.model_validate(request())
    result = GenerationNeedsResult.model_validate(
        {
            "contract_version": 3,
            "outcome": "answered",
            "answer_parts": [
                {"text": "Synthetic claim.", "evidence_ids": ["ev-01"]},
                {"text": "Current guidance.", "evidence_ids": ["ev-03"]},
            ],
            "unsupported_aspects": [],
            "insufficiency_reason": None,
            "usage": None,
            "entailment_checks": [
                {"part_index": 0, "verdict": "supported", "missing_condition": None},
                {"part_index": 1, "verdict": "supported", "missing_condition": None},
            ],
            "aspect_links": [
                {
                    "need_id": "n1",
                    "answer_part_indices": [0],
                    "unsupported_aspect_indices": [],
                },
                {
                    "need_id": "n2",
                    "answer_part_indices": [1],
                    "unsupported_aspect_indices": [],
                },
            ],
        }
    )
    assert result.validate_for_needs_request(incoming) is result
    other = request()
    other["evidence_needs"][1]["need_id"] = "n3"
    other["evidence"][2]["temporal_authority"]["need_id"] = "n3"
    with pytest.raises(ValueError):
        result.validate_for_needs_request(GenerationNeedsRequest.model_validate(other))
