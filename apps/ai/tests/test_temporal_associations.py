"""Shared paragraphs do not share evidence ownership or temporal authority."""

from copy import deepcopy
from types import SimpleNamespace

import pytest

from app.generation.needs_output import OpenAIGenerationTemporalAssociationOutput
from app.generation.needs_request import GenerationNeedsRequest
from app.generation.temporal_targets import (
    TemporalAssociationUse,
    validate_temporal_uses,
)
from tests.test_temporal_answer_use import temporal_request


def shared_request():
    raw = temporal_request()
    raw["contract_version"] = 5
    first = raw["evidence_needs"][0]
    first["evidence_ids"] = ["ev-02"]
    second = deepcopy(first)
    second.update(need_id="n2", central=False, evidence_ids=["ev-03"])
    for target in second["temporal_targets"]:
        target.update(need_id="n2", target_id=target["target_id"].replace("n1", "n2"))
    item = deepcopy(raw["evidence"][1])
    item["evidence_id"] = "ev-03"
    item["temporal_authority"].update(need_id="n2", temporal_target_ids=["n2:date:1"])
    raw["evidence"] = [raw["evidence"][1], item]
    raw["evidence_needs"] = [first, second]
    return raw


def shared_output():
    return {
        "outcome": "answered",
        "answer_parts": [
            {
                "text": "The historical interval was daily for both requested duties.",
                "evidence_ids": ["ev-02", "ev-03"],
            }
        ],
        "unsupported_aspects": [],
        "insufficiency_reason": None,
        "aspect_links": [
            {"need_id": n, "answer_part_indices": [0], "unsupported_aspect_indices": []}
            for n in ("n1", "n2")
        ],
        "temporal_uses": [
            {
                "part_index": 0,
                "need_id": n,
                "target_id": n + ":date:1",
                "question_quote": "17 March 2025",
                "evidence_ids": [handle],
            }
            for n, handle in (("n1", "ev-02"), ("n2", "ev-03"))
        ],
    }


@pytest.mark.parametrize(
    "mutation",
    [
        None,
        "borrow",
        "missing",
        "duplicate",
        "unassigned",
        "version",
        "interval",
        "target",
        "quote",
    ],
)
def test_shared_temporal_associations_keep_independent_guards(mutation):
    raw, output = shared_request(), shared_output()
    if mutation == "borrow":
        output["temporal_uses"][0]["evidence_ids"] = ["ev-03"]
    if mutation == "missing":
        output["temporal_uses"].pop()
    if mutation == "duplicate":
        output["temporal_uses"].append(output["temporal_uses"][0])
    if mutation == "unassigned":
        extra = deepcopy(raw["evidence"][0])
        extra["evidence_id"] = "ev-04"
        raw["evidence"].append(extra)
        raw["evidence_needs"][0]["evidence_ids"].append("ev-04")
        output["answer_parts"][0]["evidence_ids"].append("ev-04")
    if mutation == "version":
        raw["evidence_needs"][1]["temporal_targets"][1]["versions"][0]["version_id"] = (
            "wrong"
        )
    if mutation == "interval":
        raw["evidence_needs"][1]["temporal_targets"][1]["versions"][0]["end"] = (
            "2025-01-01T00:00:00Z"
        )
    if mutation == "target":
        output["temporal_uses"][1]["target_id"] = "n1:date:1"
    if mutation == "quote":
        output["temporal_uses"][1]["question_quote"] = "invented date"
    request = GenerationNeedsRequest.model_validate(raw)
    parsed = OpenAIGenerationTemporalAssociationOutput.model_validate(output)
    args = (request, parsed.answer_parts, parsed.aspect_links, parsed.temporal_uses)
    if mutation:
        with pytest.raises(ValueError):
            validate_temporal_uses(*args)
    else:
        validate_temporal_uses(*args)


def test_shared_part_real_adapter_and_result_validation_without_extra_call():
    from tests.test_generation_needs_transport import AutoReviewResponses, prepared
    from tests.test_openai_generation_adapter import response

    _, adapter, _ = prepared()
    output = OpenAIGenerationTemporalAssociationOutput.model_validate(shared_output())
    responses = AutoReviewResponses([response(output)])
    adapter._client = SimpleNamespace(responses=responses)
    result = adapter.generate_needs(
        GenerationNeedsRequest.model_validate(shared_request())
    )
    assert result.contract_version == 5
    assert len(result.temporal_uses) == 2
    assert len(responses.calls) == 2
    assert result.usage["contract_version"] == "generation-result-v5"


def test_v5_single_association_preserves_single_need_behaviour():
    raw = temporal_request()
    raw["contract_version"] = 5
    request = GenerationNeedsRequest.model_validate(raw)
    validate_temporal_uses(
        request,
        [SimpleNamespace(evidence_ids=["ev-02"])],
        [SimpleNamespace(need_id="n1", answer_part_indices=[0])],
        [
            TemporalAssociationUse(
                part_index=0,
                need_id="n1",
                target_id="n1:date:1",
                question_quote="17 March 2025",
                evidence_ids=["ev-02"],
            )
        ],
    )
