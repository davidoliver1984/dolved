import copy
import json
from pathlib import Path

import pytest
from openai.lib._pydantic import to_strict_json_schema

from app.generation.models import GenerationRequest, GenerationResult
from app.generation.needs_output import (
    GenerationNeedsResult,
    OpenAIGenerationNeedsOutput,
)
from tests.test_generation_contract import request_payload

CASES = json.loads(
    Path("/contracts/fixtures/generation-aspect-coverage/v1.json").read_text()
)["cases"]


@pytest.mark.parametrize(
    "text,valid",
    [
        ("Yes. The employee's manager is not notified.", False),
        ("No! The guidance permits it.", False),
        ("No", False),
        ("No, the guidance explicitly forbids sharing passwords.", True),
        ("Yes, employees may contact the provider directly.", True),
        ("The manager is not notified unless the employee shares it.", True),
    ],
)
def test_detached_polarity_is_rejected_but_connected_statements_remain(text, valid):
    case = copy.deepcopy(CASES[0])
    case["result"]["answer_parts"][0]["text"] = text
    output = OpenAIGenerationNeedsOutput.model_validate(
        {**case["result"], "aspect_links": case["links"]}
    )
    if valid:
        output.to_result(
            GenerationRequest.model_validate(request_payload()), tuple(case["need_ids"])
        )
    else:
        with pytest.raises(ValueError, match="detached yes/no"):
            output.to_result(
                GenerationRequest.model_validate(request_payload()),
                tuple(case["need_ids"]),
            )


@pytest.mark.parametrize("case", CASES, ids=lambda case: case["name"])
def test_real_provider_output_and_consumer_cover_shared_cases(case):
    def validate():
        output = OpenAIGenerationNeedsOutput.model_validate(
            {**case["result"], "aspect_links": case["links"]}
        )
        result = output.to_result(
            GenerationRequest.model_validate(request_payload()), tuple(case["need_ids"])
        )
        assert result.contract_version == 2
        assert "aspect_results" not in result.model_dump()
        with pytest.raises(ValueError):
            GenerationResult.model_validate(result.model_dump())

    if case["valid"]:
        validate()
    else:
        with pytest.raises(ValueError):
            validate()


@pytest.mark.parametrize("ids", [["ev02"], ["ev-99"], ["ev-01", "ev-01"], []])
def test_v2_keeps_canonical_duplicate_and_membership_checks(ids):
    case = copy.deepcopy(CASES[0])
    case["result"]["answer_parts"][0]["evidence_ids"] = ids
    with pytest.raises(ValueError):
        OpenAIGenerationNeedsOutput.model_validate(
            {**case["result"], "aspect_links": case["links"]}
        ).to_result(GenerationRequest.model_validate(request_payload()), ("n1", "n2"))


def test_provider_strict_schema_has_only_reference_links_and_canonical_citations():
    schema = to_strict_json_schema(OpenAIGenerationNeedsOutput)
    link = schema["$defs"]["AspectLink"]
    assert set(link["properties"]) == {
        "need_id",
        "answer_part_indices",
        "unsupported_aspect_indices",
    }
    assert link["additionalProperties"] is False
    assert set(schema["required"]) == set(schema["properties"])
    assert (
        schema["$defs"]["OpenAIAnswerPart"]["properties"]["evidence_ids"]["items"][
            "pattern"
        ]
        == r"^ev-[0-9]{2,}$"
    )
    assert "aspect_results" not in schema["properties"]


def test_response_rejects_model_invented_projection():
    case = CASES[0]
    with pytest.raises(ValueError):
        GenerationNeedsResult.model_validate(
            {
                **case["result"],
                "contract_version": 2,
                "usage": None,
                "aspect_links": case["links"],
                "aspect_results": {"n1": "supported"},
            }
        )


def test_shared_result_schema_matches_implemented_producer():
    from jsonschema import Draft202012Validator

    schema = json.loads(
        Path("/contracts/http/generation-needs/v2/result.schema.json").read_text()
    )
    Draft202012Validator.check_schema(schema)
    assert schema == GenerationNeedsResult.model_json_schema()


@pytest.mark.parametrize("missing", [False, True])
def test_answer_cannot_borrow_another_needs_citation(missing):
    from app.generation.needs_request import GenerationNeedsRequest
    from tests.test_generation_needs_request import request

    raw = request()
    if missing:
        raw["evidence"] = [raw["evidence"][2]]
        raw["constraints"]["required_sides"] = ["primary"]
        raw["evidence_needs"][0]["evidence_ids"] = []
    incoming = GenerationNeedsRequest.model_validate(raw)
    result = GenerationNeedsResult.model_validate(
        {
            "contract_version": 2,
            "outcome": "answered",
            "answer_parts": [
                {"text": "Supported current guidance.", "evidence_ids": ["ev-03"]}
            ],
            "unsupported_aspects": [],
            "insufficiency_reason": None,
            "usage": None,
            "aspect_links": [
                {
                    "need_id": "n1",
                    "answer_part_indices": [0],
                    "unsupported_aspect_indices": [],
                },
                {
                    "need_id": "n2",
                    "answer_part_indices": [0],
                    "unsupported_aspect_indices": [],
                },
            ],
        }
    )
    with pytest.raises(ValueError, match="another need"):
        result.validate_for_needs_request(incoming)
    valid = result.model_dump(mode="json")
    valid["outcome"] = "qualified"
    valid["unsupported_aspects"] = ["The requested history is not supported."]
    valid["aspect_links"][0] = {
        "need_id": "n1",
        "answer_part_indices": [],
        "unsupported_aspect_indices": [0],
    }
    GenerationNeedsResult.model_validate(valid).validate_for_needs_request(incoming)
