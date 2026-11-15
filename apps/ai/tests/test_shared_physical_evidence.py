from copy import deepcopy
from types import SimpleNamespace

import pytest

from app.generation.needs_output import OpenAIGenerationTemporalAssociationOutput
from app.generation.needs_request import GenerationNeedsRequest
from app.generation.openai_adapter import (
    NEEDS_ADAPTER_VERSION,
    NEEDS_CONTRACT_VERSION,
    NEEDS_PROMPT_VERSION,
    OpenAIGenerationProfile,
    OpenAIGenerator,
)
from app.generation.support_review import SourceSupportReview
from tests.test_generation_entailment import scenario
from tests.test_openai_generation_adapter import FakeResponses, response


def shared():
    incoming, _ = scenario(
        "Engineers inspect weekly; clerks record results.",
        "Engineers inspect weekly; clerks record results.",
    )
    raw = incoming.model_dump(mode="json")
    raw["contract_version"] = 6
    raw["question"] = "Who inspects and when? Who records results?"
    original = raw["evidence_needs"][0]
    raw["evidence_needs"] = []
    authority = deepcopy(raw["evidence"][0]["temporal_authority"])
    associations = {}
    for index, question in enumerate(
        ["Who inspects and when?", "Who records results?"], 1
    ):
        need_id = f"n{index}"
        raw["evidence_needs"].append(
            {
                **original,
                "need_id": need_id,
                "aspect": question,
                "question_span": {"turn_id": "u1", "quote": question},
                "context_spans": [],
                "request_text_version": "extractive-request-v1",
                "applicability": {"kind": "generic", "source": None},
            }
        )
        associations[need_id] = {
            "temporal_authority": {**authority, "need_id": need_id},
            "applicability_context": {},
            "side": "primary",
        }
    raw["evidence"][0]["temporal_authority"]["validated_need_associations"] = (
        associations
    )
    return raw


def test_shared_physical_passage_through_real_adapter_retains_independent_owners():
    incoming = GenerationNeedsRequest.model_validate(shared())
    answer = incoming.evidence[0].text
    output = OpenAIGenerationTemporalAssociationOutput.model_validate(
        {
            "outcome": "answered",
            "answer_parts": [{"text": answer, "evidence_ids": ["ev-01"]}],
            "unsupported_aspects": [],
            "insufficiency_reason": None,
            "temporal_uses": [],
            "aspect_links": [
                {
                    "need_id": n,
                    "answer_part_indices": [0],
                    "unsupported_aspect_indices": [],
                }
                for n in ("n1", "n2")
            ],
        }
    )
    review = SourceSupportReview.model_validate(
        {
            "parts": [
                {
                    "part_index": 0,
                    "verdict": "supported",
                    "missing_condition": None,
                    "claims": [
                        {
                            "need_id": n,
                            "evidence_id": "ev-01",
                            "answer_quote": answer,
                            "source_quote": answer,
                            "same_situation": "established",
                            "requirement_preserved": "established",
                        }
                        for n in ("n1", "n2")
                    ],
                }
            ]
        }
    )
    responses = FakeResponses([response(output), response(review)])
    adapter = OpenAIGenerator(
        api_key="controlled",
        client=SimpleNamespace(responses=responses),
        profile=OpenAIGenerationProfile(
            contract_version=NEEDS_CONTRACT_VERSION,
            prompt_version=NEEDS_PROMPT_VERSION,
            adapter_version=NEEDS_ADAPTER_VERSION,
        ),
    )
    actual = adapter.generate_needs(incoming)
    assert actual.contract_version == 6
    assert len(incoming.evidence) == 1
    assert len(actual.aspect_links) == 2
    assert len(responses.calls) == 2


@pytest.mark.parametrize(
    "mutation", ["missing", "version", "owner", "smuggled_text", "legacy"]
)
def test_shared_passage_cannot_invent_a_receiving_authority(mutation):
    raw = shared()
    associations = raw["evidence"][0]["temporal_authority"][
        "validated_need_associations"
    ]
    if mutation == "missing":
        del associations["n2"]
    elif mutation == "version":
        associations["n2"]["temporal_authority"]["document_version_id"] = (
            "00000000-0000-4000-8000-000000000001"
        )
    elif mutation == "owner":
        associations["n2"]["temporal_authority"]["need_id"] = "n1"
    elif mutation == "smuggled_text":
        associations["n2"]["text"] = "Different source"
    else:
        raw["contract_version"] = 5
    with pytest.raises(ValueError):
        GenerationNeedsRequest.model_validate(raw)
