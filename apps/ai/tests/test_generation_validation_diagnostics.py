import pytest

from app.generation.diagnostics import validation_diagnostic
from app.generation.errors import GenerationProviderFailure
from app.generation.needs_request import GenerationNeedsRequest
from tests.test_generation_needs_transport import prepared
from tests.test_openai_generation_adapter import response


@pytest.mark.parametrize(
    "mutation,field,code",
    [
        ("framing", "answer_parts.text", "detached_polarity"),
        ("membership", "answer_parts.evidence_ids", "citation_membership"),
        ("duplicate", "answer_parts.evidence_ids", "duplicate_citation"),
        ("ownership", "answer_parts.evidence_ids", "citation_ownership"),
        ("outcome", "outcome", "outcome_invariant"),
        ("reason", "outcome", "outcome_invariant"),
        ("coverage", "aspect_links", "need_coverage"),
        ("range", "aspect_links", "reference_range"),
        ("empty", "aspect_links", "empty_need"),
        ("unmapped", "aspect_links", "unmapped_content"),
    ],
)
def test_real_adapter_reports_static_rejected_rule(mutation, field, code):
    raw, adapter, responses = prepared()
    output = responses.values[0].output_parsed.model_dump()
    if mutation == "framing":
        output["answer_parts"][0]["text"] = "No. PRIVATE-CONTENT"
    elif mutation == "membership":
        output["answer_parts"][0]["evidence_ids"] = ["ev-99"]
    elif mutation == "duplicate":
        output["answer_parts"][0]["evidence_ids"] = ["ev-01", "ev-01"]
    elif mutation == "ownership":
        output["answer_parts"][0]["evidence_ids"] = ["ev-03"]
    elif mutation == "outcome":
        output["unsupported_aspects"] = ["PRIVATE-CONTENT"]
    elif mutation == "reason":
        output["insufficiency_reason"] = "PRIVATE-CONTENT"
    elif mutation == "coverage":
        output["aspect_links"] = output["aspect_links"][:1]
    elif mutation == "range":
        output["aspect_links"][0]["answer_part_indices"] = [99]
    elif mutation == "empty":
        output["aspect_links"][0]["answer_part_indices"] = []
    elif mutation == "unmapped":
        output["answer_parts"].append(dict(output["answer_parts"][0]))
    responses.values = [response(output)]
    with pytest.raises(GenerationProviderFailure) as caught:
        adapter.generate_needs(GenerationNeedsRequest.model_validate(raw))
    error = caught.value.error
    assert error.category == "contract_validation_failure"
    diagnostic = error.validation.model_dump(exclude_none=True, mode="json")
    if mutation == "ownership":
        assert diagnostic.pop("ownership") == {
            "need_id": "n1",
            "answer_part_index": 0,
            "cited_ids": ["ev-03"],
            "allowed_ids": ["ev-01", "ev-02"],
        }
    assert diagnostic == {"field": field, "code": code}
    assert "PRIVATE" not in error.model_dump_json()
    assert len(responses.calls) == 1


def test_unknown_exception_content_is_never_exposed():
    assert validation_diagnostic(ValueError("PRIVATE-CONTENT credential=secret")) == {
        "field": "result",
        "code": "schema_validation",
    }


def test_diagnostic_bounds_cannot_replace_ownership_failure():
    from app.generation.diagnostics import CitationOwnershipError

    failure = CitationOwnershipError("n1", 999, ("ev-01",), {"ev-02"})
    diagnostic = validation_diagnostic(failure)
    assert diagnostic["code"] == "citation_ownership"
    assert diagnostic["ownership"] is None
