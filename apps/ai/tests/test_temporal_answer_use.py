from copy import deepcopy
from types import SimpleNamespace

import pytest

from app.generation.needs_request import GenerationNeedsRequest
from app.generation.temporal_targets import TemporalUse, validate_temporal_uses
from tests.test_generation_needs_request import request


def temporal_request():
    raw = request()
    raw["contract_version"] = 4
    need = deepcopy(raw["evidence_needs"][1])
    need.update(
        need_id="n1",
        question_span={
            "turn_id": "u1",
            "quote": "What was the inspection interval on 17 March 2025?",
        },
        context_spans=[],
        request_text_version="extractive-request-v1",
        fact_reference=None,
        evidence_ids=["ev-01", "ev-02"],
    )
    need["aspect"] = need["question_span"]["quote"]
    need["applicability"] = {"kind": "generic", "source": None}
    raw["evidence_needs"] = [need]
    raw["question"] = need["aspect"]
    raw["constraints"]["required_sides"] = ["primary"]
    raw["evidence"] = [deepcopy(raw["evidence"][2]), deepcopy(raw["evidence"][2])]
    family = raw["evidence"][0]["temporal_authority"]["document_family_id"]
    versions = [
        "10000000-0000-4000-8000-000000000001",
        "10000000-0000-4000-8000-000000000002",
    ]
    intervals = [
        ("2026-01-01T00:00:00Z", None),
        ("2024-01-01T00:00:00Z", "2026-01-01T00:00:00Z"),
    ]
    ids = ["n1:requested", "n1:date:1"]
    need["temporal_targets"] = []
    for index, item in enumerate(raw["evidence"]):
        item.update(
            evidence_id=f"ev-0{index + 1}", side="primary", document_id=index + 1
        )
        item["temporal_authority"].update(
            need_id="n1",
            document_version_id=versions[index],
            temporal_target_ids=[ids[index]],
        )
        need["temporal_targets"].append(
            {
                "target_id": ids[index],
                "need_id": "n1",
                "family_id": family,
                "purpose": "requested" if index == 0 else "precautionary",
                "mode": "current" if index == 0 else "dated",
                "at": "2026-11-14T00:00:00Z" if index == 0 else "2025-03-17T00:00:00Z",
                "reference": None
                if index == 0
                else {
                    "turn_id": "u1",
                    "quote": "17 March 2025",
                    "at": "2025-03-17T00:00:00Z",
                },
                "versions": [
                    {
                        "version_id": versions[index],
                        "start": intervals[index][0],
                        "end": intervals[index][1],
                    }
                ],
                "status": "searched",
                "reason": None,
            }
        )
    return raw


def objects(raw=None, target="n1:date:1", handle="ev-02"):
    incoming = GenerationNeedsRequest.model_validate(raw or temporal_request())
    parts = [SimpleNamespace(evidence_ids=[handle])]
    links = [SimpleNamespace(need_id="n1", answer_part_indices=[0])]
    uses = [
        TemporalUse(
            part_index=0, need_id="n1", target_id=target, question_quote="17 March 2025"
        )
    ]
    return incoming, parts, links, uses


def test_dated_alternative_can_answer_instead_of_provisional_current():
    validate_temporal_uses(*objects())


def test_current_target_uses_current_evidence_not_historical_background():
    validate_temporal_uses(*objects(target="n1:requested", handle="ev-01"))
    with pytest.raises(ValueError, match="version mismatch"):
        validate_temporal_uses(*objects(target="n1:requested"))


@pytest.mark.parametrize(
    "mutation",
    [
        "family",
        "version",
        "lane",
        "target",
        "quote",
        "interval",
        "empty",
        "missing_use",
        "need",
    ],
)
def test_temporal_use_rejects_wrong_identity_or_unavailable_evidence(mutation):
    raw = temporal_request()
    if mutation in {"family", "version", "lane", "interval", "empty"}:
        if mutation == "family":
            raw["evidence_needs"][0]["temporal_targets"][1]["family_id"] = (
                "foreign-family"
            )
        elif mutation == "version":
            raw["evidence_needs"][0]["temporal_targets"][1]["versions"][0][
                "version_id"
            ] = "other-version"
        elif mutation == "lane":
            raw["evidence"][1]["temporal_authority"]["temporal_target_ids"] = [
                "n1:requested"
            ]
        elif mutation == "interval":
            raw["evidence_needs"][0]["temporal_targets"][1]["versions"][0]["end"] = (
                "2025-02-01T00:00:00Z"
            )
        else:
            raw["evidence_needs"][0]["temporal_targets"][1]["status"] = "empty"
    args = list(objects(raw))
    if mutation == "target":
        args[3] = [args[3][0].model_copy(update={"target_id": "n1:invented"})]
    elif mutation == "quote":
        args[3] = [args[3][0].model_copy(update={"question_quote": "invented"})]
    elif mutation == "need":
        args[3] = [args[3][0].model_copy(update={"need_id": "n2"})]
    elif mutation == "missing_use":
        args[3] = []
    with pytest.raises(ValueError):
        validate_temporal_uses(*args)


def test_real_adapter_uses_temporal_output_without_an_extra_model_stage():
    from app.generation.needs_output import OpenAIGenerationTemporalOutput
    from tests.test_generation_needs_transport import AutoReviewResponses, prepared
    from tests.test_openai_generation_adapter import response

    _, adapter, _ = prepared()
    output = OpenAIGenerationTemporalOutput.model_validate(
        {
            "outcome": "answered",
            "answer_parts": [
                {"text": "The interval was daily.", "evidence_ids": ["ev-02"]}
            ],
            "unsupported_aspects": [],
            "insufficiency_reason": None,
            "aspect_links": [
                {
                    "need_id": "n1",
                    "answer_part_indices": [0],
                    "unsupported_aspect_indices": [],
                }
            ],
            "temporal_uses": [
                {
                    "part_index": 0,
                    "need_id": "n1",
                    "target_id": "n1:date:1",
                    "question_quote": "17 March 2025",
                }
            ],
        }
    )
    responses = AutoReviewResponses([response(output)])
    adapter._client = SimpleNamespace(responses=responses)
    result = adapter.generate_needs(
        GenerationNeedsRequest.model_validate(temporal_request())
    )
    assert result.contract_version == 4
    assert result.usage["contract_version"] == "generation-result-v4"
    assert (
        len(responses.calls) == 2
    )  # Existing generation plus entailment, no additional call.
    assert responses.calls[0]["text_format"] is OpenAIGenerationTemporalOutput
    assert "PROVISIONAL" in responses.calls[0]["instructions"]
