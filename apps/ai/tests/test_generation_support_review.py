"""Controlled reviews prove enforcement, not the accuracy of a model judgement."""

from types import SimpleNamespace

import pytest

from app.generation.entailment import EntailmentRejection
from app.generation.errors import GenerationProviderFailure
from app.generation.openai_adapter import (
    NEEDS_ADAPTER_VERSION,
    NEEDS_CONTRACT_VERSION,
    NEEDS_PROMPT_VERSION,
    OpenAIGenerationProfile,
    OpenAIGenerator,
)
from app.generation.support_review import SourceSupportReview, validate_support
from tests.test_generation_entailment import scenario
from tests.test_openai_generation_adapter import FakeResponses, response

SOURCE = "If the pump stops, contact both the duty engineer and the laboratory. A low-pressure warning goes to the shift supervisor."
ANSWER = "If the pump stops, contact both the duty engineer and the laboratory."


def receipt(answer=ANSWER, **overrides):
    claim = {
        "need_id": "n1",
        "answer_quote": answer,
        "evidence_id": "ev-01",
        "source_quote": SOURCE,
        "same_situation": "established",
        "requirement_preserved": "established",
        **overrides,
    }
    return SourceSupportReview.model_validate(
        {
            "parts": [
                {
                    "part_index": 0,
                    "verdict": "supported",
                    "missing_condition": None,
                    "claims": [claim],
                }
            ]
        }
    )


def test_valid_condition_and_mandatory_conjunction_are_accepted():
    incoming, output = scenario(SOURCE, ANSWER)
    assert validate_support(incoming, output, receipt())[0].verdict == "supported"


@pytest.mark.parametrize(
    "field,value,reason",
    [
        ("same_situation", "different", "same-situation requirement not established"),
        (
            "same_situation",
            "unestablished",
            "same-situation requirement not established",
        ),
        (
            "requirement_preserved",
            "changed",
            "same-situation requirement not established",
        ),
        (
            "requirement_preserved",
            "unestablished",
            "same-situation requirement not established",
        ),
        (
            "source_quote",
            "Contact the shift supervisor instead.",
            "support quotation not in source",
        ),
        ("answer_quote", "A different answer", "support quotation not in answer"),
        ("evidence_id", "ev-99", "support evidence not owned"),
        ("need_id", "n2", "support need not associated"),
        (
            "answer_quote",
            "contact both the duty engineer",
            "support receipt coverage incomplete",
        ),
    ],
)
def test_invalid_support_cannot_pass_on_a_supported_part_verdict(field, value, reason):
    incoming, output = scenario(SOURCE, ANSWER)
    with pytest.raises(EntailmentRejection) as error:
        validate_support(incoming, output, receipt(**{field: value}))
    assert error.value.code == "entailment_uncertain"
    assert error.value.missing_condition == reason


def test_related_situation_substitution_is_rejected_through_real_adapter():
    answer = "If the pump stops, call the shift supervisor or duty engineer and the laboratory."
    incoming, output = scenario(SOURCE, answer)
    responses = FakeResponses(
        [
            response(output),
            response(
                receipt(
                    answer, same_situation="different", requirement_preserved="changed"
                )
            ),
        ]
    )
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
    assert error.value.error.validation.code == "entailment_uncertain"
    assert (
        len(responses.calls) == 2
    )  # one generation, the existing review; no new retry
    assert responses.calls[1]["max_output_tokens"] == 32768


def test_well_formed_but_wrong_semantic_judgement_is_not_claimed_detected():
    # Quotes/ownership alone cannot detect a dishonest or mistaken semantic verdict.
    answer = "If the pump stops, call the shift supervisor instead."
    incoming, output = scenario(SOURCE, answer)
    assert validate_support(incoming, output, receipt(answer))[0].verdict == "supported"


def test_shared_temporal_paragraph_does_not_share_support_ownership():
    from app.generation.needs_output import OpenAIGenerationTemporalAssociationOutput
    from app.generation.needs_request import GenerationNeedsRequest
    from tests.test_temporal_associations import shared_output, shared_request

    request = GenerationNeedsRequest.model_validate(shared_request())
    output = OpenAIGenerationTemporalAssociationOutput.model_validate(shared_output())
    review = receipt(output.answer_parts[0].text).model_dump()
    claims = review["parts"][0]["claims"]
    claims[0].update(evidence_id="ev-02", source_quote=request.evidence[0].text)
    claims.append(
        {
            **claims[0],
            "need_id": "n2",
            "evidence_id": "ev-03",
            "source_quote": request.evidence[1].text,
        }
    )
    assert validate_support(request, output, SourceSupportReview.model_validate(review))
    claims[1]["evidence_id"] = "ev-02"
    with pytest.raises(EntailmentRejection, match="entailment_uncertain"):
        validate_support(request, output, SourceSupportReview.model_validate(review))


def test_full_quote_receipt_sizing_includes_headroom_not_just_small_fake_output():
    import tiktoken

    from app.generation.support_review import SUPPORT_REVIEW_OUTPUT_TOKENS

    # Three needs, five source passages, one claim/source association each.
    # Use the actual 8,000-character per-source-quote contract limit, not a short stub.
    source = (SOURCE + " ") * 70
    source = source[:8000]
    parts = []
    for need in range(1, 4):
        claims = []
        for evidence in range(1, 6):
            claims.append(
                {
                    "need_id": f"n{need}",
                    "answer_quote": ANSWER * 3,
                    "evidence_id": f"ev-{evidence:02}",
                    "source_quote": source,
                    "same_situation": "established",
                    "requirement_preserved": "established",
                }
            )
        parts.append(
            {
                "part_index": need - 1,
                "verdict": "supported",
                "missing_condition": None,
                "claims": claims,
            }
        )
    review = SourceSupportReview.model_validate({"parts": parts})
    tokens = len(tiktoken.get_encoding("o200k_base").encode(review.model_dump_json()))
    assert tokens > 1024
    assert tokens + 4096 <= SUPPORT_REVIEW_OUTPUT_TOKENS  # explicit reasoning headroom


@pytest.mark.parametrize("parsed", [None, "valid_partial"])
def test_incomplete_review_is_not_consumed_and_its_usage_survives(parsed):
    from app.generation.support_review import SUPPORT_REVIEW_OUTPUT_TOKENS

    incoming, output = scenario(SOURCE, ANSWER)
    truncated = response(receipt() if parsed else None)
    truncated.status = "incomplete"
    truncated.usage.output_tokens = SUPPORT_REVIEW_OUTPUT_TOKENS
    responses = FakeResponses([response(output), truncated])
    adapter = OpenAIGenerator(
        api_key="test-only",
        client=SimpleNamespace(responses=responses),
        profile=OpenAIGenerationProfile(
            contract_version=NEEDS_CONTRACT_VERSION,
            prompt_version=NEEDS_PROMPT_VERSION,
            adapter_version=NEEDS_ADAPTER_VERSION,
        ),
    )
    with pytest.raises(GenerationProviderFailure) as caught:
        adapter.generate_needs(incoming)
    assert caught.value.error.validation.code == "entailment_uncertain"
    assert caught.value.error.usage["request_count"] == 2
    assert (
        caught.value.error.usage["output_tokens"] == SUPPORT_REVIEW_OUTPUT_TOKENS + 45
    )
    assert len(responses.calls) == 2
