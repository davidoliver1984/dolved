"""Maximum wire fields, context outside anchors, and honest capacity rejection."""

import json
from types import SimpleNamespace

import pytest
import tiktoken
from pydantic import ValidationError

from app.generation.entailment import EntailmentRejection, review_input
from app.generation.support_review import (
    SUPPORT_REVIEW_MAX_PARTS,
    SUPPORT_REVIEW_RECEIPT_TOKENS,
    SourceSupportReview,
    validate_review_capacity,
    validate_support,
)
from tests.test_generation_entailment import scenario
from tests.test_generation_support_review import receipt


def maximum_receipt():
    # All variable fields at maximum length, all part/count/index limits populated.
    return {
        "capacity_exceeded": False,
        "parts": [
            {
                "part_index": p,
                "verdict": "unsupported",
                "missing_condition": ("scope not established; " * 10)[:160],
                "claims": [
                    {
                        "need_id": "n" * 32,
                        "evidence_id": "e" * 32,
                        "answer_quote": (
                            "The authorised engineer records the inspection and its date. "
                            * 20
                        )[:600],
                        "source_quote": (
                            "Only the appointed engineer may inspect after approval. "
                            * 10
                        )[:300],
                        "same_situation": "unestablished",
                        "requirement_preserved": "unestablished",
                    }
                    for _ in range(3 if p < 6 else 2)
                ],
            }
            for p in range(SUPPORT_REVIEW_MAX_PARTS)
        ],
    }


def test_maximum_fields_and_total_associations_fit_bounded_receipt():
    raw = maximum_receipt()
    result = SourceSupportReview.model_validate(raw)
    assert len(result.parts) == 12
    assert sum(len(p.claims) for p in result.parts) == 30
    assert (
        len(tiktoken.get_encoding("o200k_base").encode(result.model_dump_json()))
        <= SUPPORT_REVIEW_RECEIPT_TOKENS
    )


def test_provider_strict_schema_requires_capacity_and_has_no_default():
    from openai.lib._pydantic import to_strict_json_schema

    schema = to_strict_json_schema(SourceSupportReview)
    assert "capacity_exceeded" in schema["required"]
    assert "default" not in schema["properties"]["capacity_exceeded"]
    assert schema["properties"]["parts"]["maxItems"] == 12
    assert schema["$defs"]["SupportedPart"]["properties"]["part_index"]["maximum"] == 11


@pytest.mark.parametrize(
    "field,limit",
    [
        ("need_id", 32),
        ("evidence_id", 32),
        ("answer_quote", 600),
        ("source_quote", 300),
    ],
)
def test_overlong_fields_are_rejected_not_truncated(field, limit):
    raw = maximum_receipt()
    raw["parts"][0]["claims"][0][field] = "x" * (limit + 1)
    with pytest.raises(ValidationError):
        SourceSupportReview.model_validate(raw)


def test_total_not_just_per_part_association_limit():
    raw = maximum_receipt()
    raw["parts"][0]["claims"].append(raw["parts"][0]["claims"][0])
    with pytest.raises(ValidationError, match="association capacity"):
        SourceSupportReview.model_validate(raw)


@pytest.mark.parametrize("mutation", ["parts", "index", "reason", "extra"])
def test_remaining_receipt_dimensions_are_closed(mutation):
    raw = maximum_receipt()
    if mutation == "parts":
        raw["parts"].append(raw["parts"][0])
    elif mutation == "index":
        raw["parts"][0]["part_index"] = 12
    elif mutation == "reason":
        raw["parts"][0]["missing_condition"] = "x" * 161
    else:
        raw["unbounded_reasoning"] = "not permitted"
    with pytest.raises(ValidationError):
        SourceSupportReview.model_validate(raw)


def test_split_answer_quotes_preserve_full_text_coverage_not_silent_shortening():
    answer = "The engineer inspects. " * 30 + "The coordinator records. " * 20
    incoming, output = scenario(answer, answer)
    quotes = [answer[i : i + 600] for i in range(0, len(answer), 600)]
    raw = receipt("placeholder").model_dump()
    template = raw["parts"][0]["claims"][0]
    raw["parts"][0]["claims"] = [
        {**template, "answer_quote": quote, "source_quote": quote[:300]}
        for quote in quotes
    ]
    assert (
        validate_support(incoming, output, SourceSupportReview.model_validate(raw))[
            0
        ].verdict
        == "supported"
    )
    raw["parts"][0]["claims"].pop()
    with pytest.raises(EntailmentRejection):
        validate_support(incoming, output, SourceSupportReview.model_validate(raw))


def test_dense_unicode_cannot_escape_whole_receipt_token_limit():
    raw = maximum_receipt()
    for part in raw["parts"]:
        for claim in part["claims"]:
            claim["answer_quote"] = "量🧪" * 300
            claim["source_quote"] = "量🧪" * 150
    with pytest.raises(ValidationError, match="token capacity"):
        SourceSupportReview.model_validate(raw)


def test_whole_receipt_boundary_includes_json_and_non_quote_fields(monkeypatch):
    import app.generation.support_review as module

    raw = maximum_receipt()
    result = SourceSupportReview.model_validate(raw)
    tokens = len(tiktoken.get_encoding("o200k_base").encode(result.model_dump_json()))
    monkeypatch.setattr(module, "SUPPORT_REVIEW_RECEIPT_TOKENS", tokens)
    assert SourceSupportReview.model_validate(raw) == result
    monkeypatch.setattr(module, "SUPPORT_REVIEW_RECEIPT_TOKENS", tokens - 1)
    with pytest.raises(ValidationError, match="token capacity"):
        SourceSupportReview.model_validate(raw)


def test_factory_binds_generation_and_review_to_same_existing_client_timeout(
    monkeypatch,
):
    from pydantic import SecretStr

    from app.generation.factory import build_needs_generator
    from app.settings import Settings

    captured = {}
    client = SimpleNamespace(responses=None)

    def fake_client(**kwargs):
        captured.update(kwargs)
        return client

    monkeypatch.setattr("app.generation.openai_adapter.openai.OpenAI", fake_client)
    generator = build_needs_generator(
        Settings().model_copy(
            update={
                "generation_provider": "openai",
                "generation_openai_api_key": SecretStr("controlled"),
                "generation_timeout_seconds": 120,
                "generation_max_attempts": 3,
                "generation_max_output_tokens": 4096,
            }
        )
    )
    assert captured["timeout"] == 120
    assert captured["max_retries"] == 0
    assert generator._client is client
    assert generator._max_attempts == 3
    assert generator.profile.max_output_tokens == 4096


def test_review_transport_retry_repeats_generation_not_an_independent_review_loop():
    import httpx
    import openai

    from app.generation.openai_adapter import (
        NEEDS_ADAPTER_VERSION,
        NEEDS_CONTRACT_VERSION,
        NEEDS_PROMPT_VERSION,
        OpenAIGenerationProfile,
        OpenAIGenerator,
    )
    from tests.test_openai_generation_adapter import response

    incoming, output = scenario("The engineer checks.", "The engineer checks.")
    calls = []

    def parse(**kwargs):
        calls.append(kwargs)
        if len(calls) == 2:
            raise openai.APITimeoutError(
                request=httpx.Request("POST", "https://controlled.invalid")
            )
        return response(
            receipt("The engineer checks.", source_quote="The engineer checks.")
            if kwargs["text_format"] is SourceSupportReview
            else output
        )

    delays = []
    generator = OpenAIGenerator(
        api_key="controlled",
        client=SimpleNamespace(responses=SimpleNamespace(parse=parse)),
        profile=OpenAIGenerationProfile(
            contract_version=NEEDS_CONTRACT_VERSION,
            prompt_version=NEEDS_PROMPT_VERSION,
            adapter_version=NEEDS_ADAPTER_VERSION,
        ),
        max_attempts=3,
        sleep=delays.append,
        jitter=lambda: 0,
    )
    generator.generate_needs(incoming)
    assert [c["text_format"] is SourceSupportReview for c in calls] == [
        False,
        True,
        False,
        True,
    ]
    assert len(delays) == 1


def test_capacity_is_explicit_rejection_not_partial_acceptance():
    incoming, output = scenario("The engineer checks.", "The engineer checks.")
    bounded = SourceSupportReview.model_validate(
        {"capacity_exceeded": True, "parts": []}
    )
    with pytest.raises(EntailmentRejection, match="entailment_uncertain"):
        validate_support(incoming, output, bounded)
    with pytest.raises(EntailmentRejection):
        validate_review_capacity(
            SimpleNamespace(answer_parts=[None] * 13, aspect_links=[])
        )
    with pytest.raises(EntailmentRejection):
        validate_review_capacity(
            SimpleNamespace(
                answer_parts=[None],
                aspect_links=[SimpleNamespace(answer_part_indices=[0] * 31)],
            )
        )


@pytest.mark.parametrize(
    "source,anchor,accepted",
    [
        ("Yes", "Yes", True),
        ("Approved", "Approved", True),
        ("The engineer checks.", "engine", False),
    ],
)
def test_short_passage_exception_requires_the_entire_substantive_passage(
    source, anchor, accepted
):
    incoming, output = scenario(source, source)
    proposed = receipt(source, source_quote=anchor)
    if accepted:
        assert validate_support(incoming, output, proposed)[0].verdict == "supported"
    else:
        with pytest.raises(EntailmentRejection):
            validate_support(incoming, output, proposed)


@pytest.mark.parametrize(
    "qualifier",
    [
        "Only when approval is recorded.",
        "Except during a chemical leak.",
        "Contact BOTH the engineer AND the laboratory.",
        "This instruction concerns a pressure warning, not a pump failure.",
    ],
)
def test_context_outside_anchor_is_supplied_and_negative_judgement_still_blocks(
    qualifier,
):
    source = "Contact the engineer. " + qualifier
    incoming, output = scenario(source, "Contact the engineer.")
    wire = json.loads(review_input(incoming, output))
    assert wire[0]["cited_passages"][0]["text"] == source
    proposed = receipt(
        "Contact the engineer.",
        source_quote="Contact the engineer.",
        requirement_preserved="unestablished",
    )
    with pytest.raises(EntailmentRejection):
        validate_support(incoming, output, proposed)


def test_review_retains_supplied_cross_turn_context_without_using_anchor_as_context():
    from app.retrieval.needs import QuestionSpan

    incoming, output = scenario(
        "An engineer inspects only during maintenance.", "An engineer inspects."
    )
    span = QuestionSpan(
        turn_id="earlier", quote="I mean the workshop during maintenance."
    )
    need = incoming.evidence_needs[0].model_copy(update={"context_spans": (span,)})
    incoming = incoming.model_copy(update={"evidence_needs": (need,)})
    wire = json.loads(review_input(incoming, output))
    assert wire[0]["need_associations"][0]["context_spans"] == [
        span.model_dump(mode="json")
    ]
    assert wire[0]["cited_passages"][0]["text"] == incoming.evidence[0].text
