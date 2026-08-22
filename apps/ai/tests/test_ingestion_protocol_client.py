import json

import httpx

from app.ingestion.protocol_client import IngestionProtocolClient
from app.ingestion.signing import IngestionWorkerSigner

SECRET = "MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY="
EVENT_ID = "5a1e9c3e-3b3a-4e2a-9c7d-1f6b6f0a2b41"
CONTEXT = {
    "event_id": EVENT_ID,
    "workspace_id": "b3f2a6d4-8e4b-4b0a-9d3f-6c2e1a7d9f10",
    "document_id": "d4c9e2b7-1a6f-4e3d-8b2c-9f0e5a3d7c62",
    "lease_token": "c9a7b8d0-2e1f-4a3b-9c8d-7e6f5a4b3c2d",
}


def test_every_operation_has_an_independent_signed_purpose() -> None:
    captured: list[tuple[str, str]] = []

    def respond(request: httpx.Request) -> httpx.Response:
        captured.append(
            (
                request.url.path,
                request.headers["X-Ingestion-Worker-Purpose"],
            )
        )
        if request.url.path.endswith("/claim"):
            return httpx.Response(
                200,
                json={"data": {"outcome": "stale_event", "document_status": "indexed"}},
            )
        return httpx.Response(200, json={"data": {"outcome": "accepted"}})

    client = IngestionProtocolClient(
        base_url="http://api:8000",
        timeout_seconds=1,
        signer=IngestionWorkerSigner("test-v2", SECRET),
        client=httpx.Client(transport=httpx.MockTransport(respond)),
    )
    client.claim(raw_body=json.dumps({"event_id": EVENT_ID}), event_id=EVENT_ID)
    client.renew(CONTEXT)
    client.submit_chunks(CONTEXT, [{"chunk_id": EVENT_ID}])
    client.seal(CONTEXT, {"expected_chunk_count": 1})
    client.resume(CONTEXT)
    client.authorise_publication(CONTEXT, {"expected_point_count": 1})
    client.complete(CONTEXT, {"expected_point_count": 1})
    client.fail(
        CONTEXT,
        failure_code="extraction.invalid",
        failure_message="Invalid source.",
    )
    client.cancel(CONTEXT)

    assert [purpose for _, purpose in captured] == [
        "ingestion.claim",
        "ingestion.lease.renew",
        "ingestion.chunks.submit",
        "ingestion.chunks.seal",
        "ingestion.attempt.resume",
        "ingestion.publication.authorise",
        "ingestion.complete",
        "ingestion.fail",
        "ingestion.attempt.cancel",
    ]


def test_signer_reproduces_both_normative_v2_vectors() -> None:
    signer = IngestionWorkerSigner("local-v1", SECRET)
    complete = signer.sign(
        timestamp=1_785_326_400,
        method="POST",
        request_path=f"/api/internal/ingestion/events/{EVENT_ID}/complete",
        body=b'{"contract_version":1,"expected_chunk_count":0}',
        event_id=EVENT_ID,
        purpose="ingestion.complete",
    )
    renew = signer.sign(
        timestamp=1_785_326_400,
        method="POST",
        request_path=f"/api/internal/ingestion/events/{EVENT_ID}/lease/renew",
        body=b'{"contract_version":1,"lease_token":"c9a7b8d0-2e1f-4a3b-9c8d-7e6f5a4b3c2d"}',
        event_id=EVENT_ID,
        purpose="ingestion.lease.renew",
    )

    assert (
        complete.signature
        == "v2=3ed660fd462b535fc169849ffcd4383324ae05c52921b3bb08b748e55aa4bc97"
    )
    assert (
        renew.signature
        == "v2=8c182114de5b9615eaae2d6ddcc5b358432ad8b6e56c7fb378330613411b9afb"
    )


def test_claim_preserves_non_success_status_outcomes_as_protocol_data() -> None:
    def respond(_: httpx.Request) -> httpx.Response:
        return httpx.Response(
            423,
            json={
                "data": {
                    "outcome": "owned_by_another_worker",
                    "document_status": "processing",
                }
            },
        )

    client = IngestionProtocolClient(
        base_url="http://api:8000",
        timeout_seconds=1,
        signer=IngestionWorkerSigner("test-v2", SECRET),
        client=httpx.Client(transport=httpx.MockTransport(respond)),
    )

    result = client.claim(raw_body="{}", event_id=EVENT_ID)

    assert result.outcome == "owned_by_another_worker"
    assert result.may_process is False


def test_transient_callback_is_retried_with_the_identical_body() -> None:
    bodies: list[bytes] = []
    delays: list[float] = []

    def respond(request: httpx.Request) -> httpx.Response:
        bodies.append(request.content)
        if len(bodies) == 1:
            return httpx.Response(503, json={"error": {"code": "unavailable"}})
        return httpx.Response(200, json={"data": {"outcome": "renewed"}})

    client = IngestionProtocolClient(
        base_url="http://api:8000",
        timeout_seconds=1,
        signer=IngestionWorkerSigner("test-v2", SECRET),
        client=httpx.Client(transport=httpx.MockTransport(respond)),
        max_attempts=3,
        initial_backoff_seconds=0.25,
        sleep=delays.append,
    )

    assert client.renew(CONTEXT)["outcome"] == "renewed"
    assert len(bodies) == 2
    assert bodies[0] == bodies[1]
    assert json.loads(bodies[0])["event_id"] == EVENT_ID
    assert delays == [0.25]
