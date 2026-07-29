import json

import httpx
import pytest

from app.ingestion.claim_client import ClaimDisposition, IngestionClaimClient
from app.ingestion.signing import IngestionWorkerSigner

TEST_SECRET = "MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY="
EVENT_ID = "5a1e9c3e-3b3a-4e2a-9c7d-1f6b6f0a2b41"
RAW_BODY = json.dumps(
    {"event_id": EVENT_ID},
    separators=(",", ":"),
)


def client_for(
    handler: httpx.MockTransport,
) -> IngestionClaimClient:
    return IngestionClaimClient(
        base_url="http://api:8000",
        timeout_seconds=2,
        signer=IngestionWorkerSigner("local-v1", TEST_SECRET),
        client=httpx.Client(transport=handler),
    )


@pytest.mark.parametrize("outcome", ["claimed", "already_claimed"])
def test_successful_claim_outcomes_are_acknowledged(outcome: str) -> None:
    transport = httpx.MockTransport(
        lambda request: httpx.Response(
            200,
            json={"data": {"outcome": outcome}},
        )
    )

    disposition = client_for(transport).claim(
        raw_body=RAW_BODY,
        event_id=EVENT_ID,
        timestamp=1_785_326_400,
    )

    assert disposition is ClaimDisposition.ACKNOWLEDGE


def test_stale_event_is_a_safe_acknowledgement() -> None:
    transport = httpx.MockTransport(
        lambda request: httpx.Response(
            409,
            json={"data": {"outcome": "stale_event"}},
        )
    )

    assert (
        client_for(transport).claim(
            raw_body=RAW_BODY,
            event_id=EVENT_ID,
        )
        is ClaimDisposition.ACKNOWLEDGE
    )


@pytest.mark.parametrize(
    "status",
    [401, 403, 429, 500, 503],
)
def test_authentication_and_transient_failures_remain_retryable(
    status: int,
) -> None:
    transport = httpx.MockTransport(
        lambda request: httpx.Response(status, json={"error": {"code": "failure"}})
    )

    assert (
        client_for(transport).claim(
            raw_body=RAW_BODY,
            event_id=EVENT_ID,
        )
        is ClaimDisposition.RETRY
    )


@pytest.mark.parametrize(
    ("status", "code"),
    [
        (404, "unknown_document"),
        (409, "event_identity_reused"),
        (422, "invalid_event_contract"),
    ],
)
def test_permanent_domain_or_contract_failures_are_poison(
    status: int,
    code: str,
) -> None:
    transport = httpx.MockTransport(
        lambda request: httpx.Response(status, json={"error": {"code": code}})
    )

    assert (
        client_for(transport).claim(
            raw_body=RAW_BODY,
            event_id=EVENT_ID,
        )
        is ClaimDisposition.POISON
    )


def test_ineligible_state_response_is_poison() -> None:
    transport = httpx.MockTransport(
        lambda request: httpx.Response(
            409,
            json={
                "data": {
                    "outcome": "ineligible_state",
                    "document_status": "uploaded",
                }
            },
        )
    )

    assert (
        client_for(transport).claim(
            raw_body=RAW_BODY,
            event_id=EVENT_ID,
        )
        is ClaimDisposition.POISON
    )


def test_network_failure_remains_retryable() -> None:
    def raise_timeout(request: httpx.Request) -> httpx.Response:
        raise httpx.ReadTimeout("synthetic timeout", request=request)

    assert (
        client_for(httpx.MockTransport(raise_timeout)).claim(
            raw_body=RAW_BODY,
            event_id=EVENT_ID,
        )
        is ClaimDisposition.RETRY
    )


def test_client_sends_the_exact_body_and_signed_internal_path() -> None:
    captured: dict[str, object] = {}

    def capture(request: httpx.Request) -> httpx.Response:
        captured["method"] = request.method
        captured["url"] = str(request.url)
        captured["body"] = request.content
        captured["event_id"] = request.headers["X-Ingestion-Worker-Event-ID"]
        captured["signature"] = request.headers["X-Ingestion-Worker-Signature"]

        return httpx.Response(200, json={"data": {"outcome": "claimed"}})

    disposition = client_for(httpx.MockTransport(capture)).claim(
        raw_body=RAW_BODY,
        event_id=EVENT_ID,
        timestamp=1_785_326_400,
    )

    assert disposition is ClaimDisposition.ACKNOWLEDGE
    assert captured == {
        "method": "POST",
        "url": (f"http://api:8000/api/internal/ingestion/events/{EVENT_ID}/claim"),
        "body": RAW_BODY.encode(),
        "event_id": EVENT_ID,
        "signature": captured["signature"],
    }
    assert str(captured["signature"]).startswith("v1=")
