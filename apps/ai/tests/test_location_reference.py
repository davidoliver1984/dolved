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
            "contract_version": 3,
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


def test_site_document_collision_rejects_a_real_riverside_site_even_with_a_real_title() -> None:
    source = LocationReferenceRequest.model_validate(
        {
            **request("Riverside").model_dump(mode="json"),
            "locations": [{"location_id": SITE, "name": "Riverside House", "aliases": []}],
            "document_families": [
                {"family_id": FAMILY, "title": "Riverside Key Holder Pilot"}
            ],
        }
    )
    document_reading = decision(
        decision="not_a_site", location_id=None,
        reference_type="document_or_record", evidence_family_id=FAMILY,
        quote="Riverside", evidence_quote="Riverside",
    )
    with pytest.raises(LocationReferenceReviewError, match="site_document_conflict"):
        validate_selection(source, document_reading)
    exact_alias = LocationReferenceRequest.model_validate(
        {**source.model_dump(mode="json"),
         "locations": [{"location_id": SITE, "name": "Other House", "aliases": ["Riverside"]}]}
    )
    with pytest.raises(LocationReferenceReviewError, match="site_document_conflict"):
        validate_selection(exact_alias, document_reading)
    unrelated_site = LocationReferenceRequest.model_validate(
        {**source.model_dump(mode="json"),
         "locations": [{"location_id": SITE, "name": "Riverbank House", "aliases": []}]}
    )
    assert validate_selection(unrelated_site, document_reading).decision == "not_a_site"


def test_existing_document_and_unregistered_site_controls_stay_valid() -> None:
    for reference, title in (
        ("backup policy", "Data Backup and Recovery Policy"),
        ("the legionella procedure", "Legionella Control Procedure"),
    ):
        source = LocationReferenceRequest.model_validate(
            {**request(reference).model_dump(mode="json"),
             "document_families": [{"family_id": FAMILY, "title": title}]}
        )
        selection = decision(decision="not_a_site", location_id=None,
                             reference_type="document_or_record", evidence_family_id=FAMILY,
                             quote=reference, evidence_quote=title)
        assert validate_selection(source, selection).decision == "not_a_site"
    unresolved = decision(decision="unresolved_or_ambiguous", location_id=None,
                          quote=None, reason="unresolved")
    assert validate_selection(request("Oakfield outreach"), unresolved).decision == "unresolved_or_ambiguous"


@pytest.mark.parametrize("reference_type", ["organisation", "answer_value", "incidental_phrase"])
@pytest.mark.parametrize("reference", ["Oakfield outreach", "we", "the site"])
def test_v3_schema_cannot_express_unanchored_non_site_role(
    reference_type: str, reference: str
) -> None:
    old_selection = decision().model_dump(mode="json")
    old_selection.update(
        decision="not_a_site",
        location_id=None,
        reference_type=reference_type,
        evidence_family_id=None,
        quote=reference,
        evidence_quote=f"At {reference}, when did a key safe need reviewing?",
        competing_target_quote="when did a key safe need reviewing",
    )
    with pytest.raises(ValidationError):
        LocationReferenceDecision.model_validate(old_selection)


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


def test_reviewer_rejects_case_141_old_incidental_output_at_schema() -> None:
    source = LocationReferenceRequest.model_validate(
        {
            **request("Oakfield outreach").model_dump(mode="json"),
            "question": "At Oakfield outreach on 1 July 2026, when did a key safe need reviewing?",
        }
    )
    clause = source.question.removesuffix("?")
    old_bypass = decision().model_dump(mode="json")
    old_bypass.update(decision="not_a_site", location_id=None,
                      reference_type="incidental_phrase", evidence_family_id=None,
                      quote=source.reference, evidence_quote=clause,
                      competing_target_quote="when did a key safe need reviewing")
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
                        {"finish_reason": "stop", "message": {"content": json.dumps(old_bypass)}}
                    ]
                },
            )
        )
    )
    with pytest.raises(LocationReferenceReviewError) as error:
        reviewer.review(source)
    assert error.value.code == "validation_rejected:selection_schema"
    assert clause in (error.value.raw_response or "")


def test_reviewer_rejects_case_142_old_incidental_output_at_schema() -> None:
    source = LocationReferenceRequest.model_validate(
        {
            **request("Oakfield outreach").model_dump(mode="json"),
            "question": "For an Oakfield outreach key safe in July 2026, what were the review triggers?",
        }
    )
    malformed = decision().model_dump(mode="json")
    malformed.update(decision="not_a_site", location_id=None,
                     reference_type="incidental_phrase", evidence_family_id=None,
                     quote="Oakfield outreach", evidence_quote="Oakfield outreach",
                     competing_target_quote="review triggers")
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
    assert error.value.code == "validation_rejected:selection_schema"
    raw_completion = json.loads(error.value.raw_response or "{}")
    assert json.loads(raw_completion["choices"][0]["message"]["content"])[
        "reference_type"
    ] == "incidental_phrase"


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
