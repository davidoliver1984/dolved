"""Provider-free contract and transport checks for shadow location review."""

import json
from uuid import UUID, uuid4

import httpx
import pytest
from fastapi.testclient import TestClient
from pydantic import SecretStr, ValidationError

from app.main import app
from app.retrieval.location_reference import (
    LocationReferenceDecision,
    LocationReferenceRequest,
    LocationReferenceResponse,
    LocationReferenceReviewer,
    LocationReferenceReviewError,
    validate_selection,
)
from app.retrieval.models import OperationUsage
from app.retrieval.routes import location_reference_reviewer_dependency
from tests.test_retrieval_routes import signed_headers

WORKSPACE = "5323cd6b-8b7a-40f9-98b0-9413c75f643e"
SITE = "00000000-0000-4000-8000-000000000011"
FAMILY = "00000000-0000-4000-8000-000000000012"
FOREIGN = "00000000-0000-4000-8000-000000000013"


def request(reference: str = "North West Region sites") -> LocationReferenceRequest:
    return LocationReferenceRequest.model_validate(
        {
            "contract_version": 2,
            "request_id": str(uuid4()),
            "workspace_id": WORKSPACE,
            "reference": reference,
            "question": f"Does the Equipment Policy cover {reference}?",
            "aspect": f"Check the recorded Equipment Policy scope for {reference}.",
            "locations": [
                {
                    "location_id": SITE,
                    "name": "North West Region",
                    "aliases": ["North West"],
                }
            ],
            "document_families": [{"family_id": FAMILY, "title": "Equipment Policy"}],
        }
    )


def decision(**changes: object) -> LocationReferenceDecision:
    return LocationReferenceDecision.model_validate(
        {
            "decision": "resolved_site",
            "location_id": SITE,
            "reference_type": None,
            "evidence_family_id": None,
            "quote": "North West Region sites",
            "evidence_quote": None,
            "competing_target_quote": None,
            "reason": None,
            **changes,
        }
    )


def test_site_identity_and_quote_must_be_in_the_supplied_workspace_request() -> None:
    source = request()
    assert validate_selection(source, decision()).location_id == UUID(SITE)
    for changed in (
        {"location_id": FOREIGN},
        {"quote": "North West"},
    ):
        with pytest.raises(LocationReferenceReviewError):
            validate_selection(source, decision(**changed))


def test_non_site_role_requires_grounded_context_and_document_candidate() -> None:
    source = request("Equipment Policy")
    valid = decision(
        decision="not_a_site",
        location_id=None,
        reference_type="document_or_record",
        evidence_family_id=FAMILY,
        quote="Equipment Policy",
        evidence_quote="Fabricated Policy Title",
    )
    # The exact cited context must really occur; a plausible-looking invented span fails.
    with pytest.raises(LocationReferenceReviewError):
        validate_selection(source, valid)
    valid = valid.model_copy(update={"evidence_quote": "Equipment Policy"})
    assert validate_selection(source, valid).evidence_family_id == UUID(FAMILY)
    with pytest.raises(LocationReferenceReviewError):
        validate_selection(
            source,
            valid.model_copy(update={"evidence_family_id": UUID(FOREIGN)}),
        )
    with pytest.raises(LocationReferenceReviewError):
        validate_selection(
            source,
            valid.model_copy(update={"evidence_quote": "A fabricated document role"}),
        )


def test_non_document_role_rejects_containing_clause_without_distinct_target() -> None:
    source = LocationReferenceRequest.model_validate(
        {
            **request("Oakfield outreach").model_dump(mode="json"),
            "question": "At Oakfield outreach on 1 July 2026, when did a key safe need reviewing?",
        }
    )
    containing_clause = source.question.removesuffix("?")
    bypass = decision(
        decision="not_a_site",
        location_id=None,
        reference_type="incidental_phrase",
        evidence_family_id=None,
        quote="Oakfield outreach",
        evidence_quote=containing_clause,
        competing_target_quote=containing_clause,
    )
    with pytest.raises(LocationReferenceReviewError, match="validation_rejected:target_contains_reference"):
        validate_selection(source, bypass)
    for target, code in (
        ("safe", "validation_rejected:target_too_short"),
        ("key safe", "validation_rejected:target_not_substantial"),
        ("fabricated distinct target", "validation_rejected:target_not_grounded"),
    ):
        with pytest.raises(LocationReferenceReviewError, match=code):
            validate_selection(
                source, bypass.model_copy(update={"competing_target_quote": target})
            )
    with pytest.raises(LocationReferenceReviewError, match="validation_rejected:no_competing_target"):
        validate_selection(
            source, bypass.model_copy(update={"competing_target_quote": None})
        )


def test_non_document_role_accepts_grounded_distinct_answer_target() -> None:
    source = LocationReferenceRequest.model_validate(
        {
            **request("we").model_dump(mode="json"),
            "question": "Do we have a staff gym?",
        }
    )
    selection = decision(
        decision="not_a_site",
        location_id=None,
        reference_type="incidental_phrase",
        evidence_family_id=None,
        quote="we",
        evidence_quote="Do we have a staff gym?",
        competing_target_quote="staff gym",
    )
    assert validate_selection(source, selection).competing_target_quote == "staff gym"


def test_unresolved_is_fail_closed_and_malformed_decisions_are_rejected() -> None:
    unresolved = decision(
        decision="unresolved_or_ambiguous",
        location_id=None,
        quote=None,
        reason="unresolved",
    )
    assert (
        validate_selection(request(), unresolved).decision == "unresolved_or_ambiguous"
    )
    with pytest.raises(ValidationError):
        decision(decision="unresolved_or_ambiguous", reason="unresolved")
    data = request().model_dump(mode="json")
    data["locations"].append(data["locations"][0])
    with pytest.raises(ValidationError):
        LocationReferenceRequest.model_validate(data)


def test_review_uses_one_bounded_provider_request_with_mock_transport() -> None:
    source = request()
    reviewer = LocationReferenceReviewer(
        api_url="https://provider.invalid/chat/completions",
        api_key=SecretStr("test-only"),
        model="test-model",
        timeout_seconds=1,
    )

    def handler(http_request: httpx.Request) -> httpx.Response:
        sent = json.loads(http_request.content)
        assert sent["max_completion_tokens"] == 4096
        assert sent["reasoning_effort"] == "low"
        assert (
            json.loads(sent["messages"][1]["content"])["reference"] == source.reference
        )
        return httpx.Response(
            200,
            json={
                "choices": [
                    {
                        "finish_reason": "stop",
                        "message": {"content": decision().model_dump_json()},
                    }
                ],
                "usage": {"prompt_tokens": 50, "completion_tokens": 20},
            },
        )

    reviewer.client = httpx.Client(transport=httpx.MockTransport(handler))
    result = reviewer.review(source)
    assert result.selection.location_id == UUID(SITE)
    assert result.usage.request_count == 1
    assert result.usage.retry_count == 0
    assert result.usage.cost_basis == "unavailable"


@pytest.mark.parametrize(
    ("response", "expected_code"),
    [
        (httpx.Response(503, json={"error": "unavailable"}), "transport_error"),
        (httpx.Response(200, content=b"not-json"), "malformed_output"),
        (
            httpx.Response(200, json={"choices": [{"finish_reason": "length", "message": {"content": ""}}]}),
            "incomplete_output",
        ),
        (
            httpx.Response(200, json={"choices": [{"finish_reason": "stop", "message": {"content": "{}"}}]}),
            "validation_rejected:selection_schema",
        ),
    ],
)
def test_review_failure_subtypes_are_bounded(
    response: httpx.Response, expected_code: str
) -> None:
    reviewer = LocationReferenceReviewer(
        api_url="https://provider.invalid/chat/completions",
        api_key=SecretStr("test-only"),
        model="test-model",
        timeout_seconds=1,
    )
    reviewer.client = httpx.Client(
        transport=httpx.MockTransport(lambda _: response)
    )
    with pytest.raises(LocationReferenceReviewError) as error:
        reviewer.review(request())
    assert error.value.code == expected_code
    assert error.value.raw_response == response.text
    assert error.value.raw_response_truncated is False


def test_reviewer_preserves_case_141_grounding_rejection_code() -> None:
    source = LocationReferenceRequest.model_validate(
        {
            **request("Oakfield outreach").model_dump(mode="json"),
            "question": "At Oakfield outreach on 1 July 2026, when did a key safe need reviewing?",
        }
    )
    clause = source.question.removesuffix("?")
    old_bypass = decision(
        decision="not_a_site",
        location_id=None,
        reference_type="incidental_phrase",
        evidence_family_id=None,
        quote=source.reference,
        evidence_quote=clause,
        competing_target_quote=clause,
    )
    reviewer = LocationReferenceReviewer(
        api_url="https://provider.invalid/chat/completions",
        api_key=SecretStr("test-only"),
        model="test-model",
        timeout_seconds=1,
    )
    reviewer.client = httpx.Client(
        transport=httpx.MockTransport(
            lambda _: httpx.Response(
                200,
                json={
                    "choices": [
                        {"finish_reason": "stop", "message": {"content": old_bypass.model_dump_json()}}
                    ]
                },
            )
        )
    )
    with pytest.raises(LocationReferenceReviewError) as error:
        reviewer.review(source)
    assert error.value.code == "validation_rejected:target_contains_reference"
    assert clause in (error.value.raw_response or "")


def test_reviewer_labels_missing_competing_target_before_grounding() -> None:
    source = LocationReferenceRequest.model_validate(
        {
            **request("we").model_dump(mode="json"),
            "question": "Do we have a staff gym?",
        }
    )
    malformed = decision(
        decision="not_a_site",
        location_id=None,
        reference_type="incidental_phrase",
        evidence_family_id=None,
        quote="we",
        evidence_quote=source.question,
        competing_target_quote="staff gym",
    ).model_dump(mode="json")
    malformed["competing_target_quote"] = None
    reviewer = LocationReferenceReviewer(
        api_url="https://provider.invalid/chat/completions",
        api_key=SecretStr("test-only"),
        model="test-model",
        timeout_seconds=1,
    )
    reviewer.client = httpx.Client(
        transport=httpx.MockTransport(
            lambda _: httpx.Response(
                200,
                json={
                    "choices": [
                        {"finish_reason": "stop", "message": {"content": json.dumps(malformed)}}
                    ]
                },
            )
        )
    )
    with pytest.raises(LocationReferenceReviewError) as error:
        reviewer.review(source)
    assert error.value.code == "validation_rejected:no_competing_target"
    raw_completion = json.loads(error.value.raw_response or "{}")
    assert json.loads(raw_completion["choices"][0]["message"]["content"])[
        "competing_target_quote"
    ] is None


def test_failed_review_raw_body_is_bounded_and_success_has_no_diagnostic() -> None:
    oversized = "x" * 70000
    reviewer = LocationReferenceReviewer(
        api_url="https://provider.invalid/chat/completions",
        api_key=SecretStr("test-only"),
        model="test-model",
        timeout_seconds=1,
    )
    reviewer.client = httpx.Client(
        transport=httpx.MockTransport(
            lambda _: httpx.Response(503, content=oversized.encode())
        )
    )
    with pytest.raises(LocationReferenceReviewError) as error:
        reviewer.review(request())
    assert len((error.value.raw_response or "").encode()) == 65536
    assert error.value.raw_response_truncated is True


def test_endpoint_requires_signed_workspace_identity_and_rejects_replay() -> None:
    source = request()

    class FakeReviewer:
        def review(
            self, incoming: LocationReferenceRequest
        ) -> LocationReferenceResponse:
            return LocationReferenceResponse(
                request_id=incoming.request_id,
                selection=validate_selection(incoming, decision()),
                usage=OperationUsage(
                    stage="location_reference_shadow",
                    provider="local",
                    model="fake",
                    execution="local",
                    request_count=0,
                    retry_count=0,
                    latency_ms=0,
                    cost_basis="zero_cost_local",
                    cost_usd=0,
                ),
            )

    app.dependency_overrides[location_reference_reviewer_dependency] = FakeReviewer
    path = "/api/internal/retrieval/location-reference-shadow"
    body = source.model_dump_json().encode()
    headers = signed_headers(
        path,
        body,
        WORKSPACE,
        str(source.request_id),
        "retrieval.location-reference-shadow",
    )
    try:
        with TestClient(app) as client:
            response = client.post(path, content=body, headers=headers)
            assert response.status_code == 200
            assert response.json()["selection"]["location_id"] == SITE
            assert "raw_response" not in response.json()
            assert client.post(path, content=body, headers=headers).status_code == 401
    finally:
        app.dependency_overrides.clear()


def test_endpoint_fails_closed_on_invalid_request_or_review_failure() -> None:
    source = request()

    class FailingReviewer:
        def review(
            self, incoming: LocationReferenceRequest
        ) -> LocationReferenceResponse:
            raise LocationReferenceReviewError(
                "malformed_output", httpx.Response(200, content=b"raw invalid reviewer output")
            )

    app.dependency_overrides[location_reference_reviewer_dependency] = FailingReviewer
    path = "/api/internal/retrieval/location-reference-shadow"
    try:
        with TestClient(app) as client:
            invalid = source.model_dump(mode="json")
            invalid["locations"][0]["location_id"] = "not-a-uuid"
            body = json.dumps(invalid).encode()
            headers = signed_headers(
                path,
                body,
                WORKSPACE,
                str(source.request_id),
                "retrieval.location-reference-shadow",
            )
            assert client.post(path, content=body, headers=headers).status_code == 422

            valid = source.model_copy(update={"request_id": uuid4()})
            body = valid.model_dump_json().encode()
            headers = signed_headers(
                path,
                body,
                WORKSPACE,
                str(valid.request_id),
                "retrieval.location-reference-shadow",
            )
            response = client.post(path, content=body, headers=headers)
            assert response.status_code == 503
            assert response.json() == {
                "detail": {
                    "code": "location_reference_review_unavailable",
                    "failure_reason": "malformed_output",
                    "raw_response": "raw invalid reviewer output",
                    "raw_response_truncated": False,
                }
            }
    finally:
        app.dependency_overrides.clear()
