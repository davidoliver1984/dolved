import hashlib
import io
import json
import threading
import zipfile
from typing import Any, cast

import httpx

from app.import_preflight.contract import parse_and_validate_preflight_event
from app.import_preflight.orchestrator import (
    ImportPreflightOrchestrator,
    ImportPreflightOutcome,
)
from app.ingestion.claim_client import ClaimDisposition, IngestionClaimClient
from app.ingestion.sqs import IngestionQueueMessage, SqsIngestionQueue
from app.ingestion.worker import IngestionWorker


def event() -> dict[str, Any]:
    return {
        "event_id": "5a1e9c3e-3b3a-4e2a-9c7d-1f6b6f0a2b41",
        "event_type": "import.preflight.requested",
        "event_version": 1,
        "contract_version": "import-preflight-v1",
        "occurred_at": "2026-08-31T12:00:00Z",
        "workspace_id": "200c38eb-8fcf-441a-9db0-07c4e6ebff01",
        "import_batch_id": "749b6237-6e17-406b-a4e6-1f8a23d0ca9b",
        "import_item_id": "168cec21-f2b1-448b-9b08-01e9befe4181",
        "staged_object": {
            "key": "imports/workspaces/200c38eb-8fcf-441a-9db0-07c4e6ebff01/items/168cec21-f2b1-448b-9b08-01e9befe4181/source",
            "read_url": "https://storage.example/exact-read",
            "expires_at": "2026-08-31T12:10:00Z",
        },
        "declared_media_type": "text/plain",
        "lease_token": "f842706f-fc6f-4b9f-93ce-0f723bc9e229",
        "lease_generation": 3,
        "correlation_id": "d93554a7-dff6-4a0a-9f6e-c9df0ed1106b",
    }


class FakeCallbackClient:
    def __init__(self) -> None:
        self.completed: list[dict[str, Any]] = []
        self.failed: list[str] = []

    def complete(self, _: dict[str, Any], result: dict[str, Any]) -> str:
        self.completed.append(result)
        return "recorded"

    def fail(self, _: dict[str, Any], diagnostic_code: str) -> str:
        self.failed.append(diagnostic_code)
        return "recorded"


def docx_bytes() -> bytes:
    output = io.BytesIO()
    with zipfile.ZipFile(output, "w") as package:
        package.writestr("[Content_Types].xml", "<Types/>")
        package.writestr("word/document.xml", "<document/>")
    return output.getvalue()


def test_event_contract_round_trips_lease_generation_and_exact_object() -> None:
    parsed = parse_and_validate_preflight_event(json.dumps(event()))
    assert parsed["lease_generation"] == 3
    assert parsed["staged_object"]["key"].endswith("/source")


def test_readable_text_reports_only_verified_source_facts() -> None:
    callbacks = FakeCallbackClient()
    source = b"Grounded policy text."
    transport = httpx.Client(
        transport=httpx.MockTransport(lambda _: httpx.Response(200, content=source))
    )
    orchestrator = ImportPreflightOrchestrator(
        client=cast(Any, callbacks), timeout_seconds=1, http_client=transport
    )

    result = orchestrator.process(event())

    assert result == ImportPreflightOutcome("readable", True)
    assert callbacks.completed == [
        {
            "result": "readable",
            "diagnostic_code": "readable",
            "source_checksum_sha256": hashlib.sha256(source).hexdigest(),
            "media_type": "text/plain",
            "size_bytes": len(source),
        }
    ]


def test_mime_mismatch_corrupt_container_and_encrypted_office_are_bounded() -> None:
    assert ImportPreflightOrchestrator._inspect(b"%PDF-invalid", "text/plain") == {
        "result": "mime_mismatch",
        "diagnostic_code": "declared_type_mismatch",
    }
    assert ImportPreflightOrchestrator._inspect(
        b"PK-not-a-zip",
        "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
    ) == {"result": "corrupt_structure", "diagnostic_code": "invalid_container"}
    assert ImportPreflightOrchestrator._inspect(
        bytes.fromhex("d0cf11e0a1b11ae1") + b"encrypted",
        "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
    ) == {"result": "encrypted", "diagnostic_code": "office_encrypted"}
    readable = ImportPreflightOrchestrator._inspect(
        docx_bytes(),
        "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
    )
    assert readable["result"] == "readable"


def test_transport_failure_reports_typed_failure_without_raw_exception() -> None:
    callbacks = FakeCallbackClient()
    transport = httpx.Client(
        transport=httpx.MockTransport(lambda _: httpx.Response(503))
    )
    orchestrator = ImportPreflightOrchestrator(
        client=cast(Any, callbacks), timeout_seconds=1, http_client=transport
    )

    assert orchestrator.process(event()).code == "source_unavailable"
    assert callbacks.failed == ["source_unavailable"]


def test_worker_routes_preflight_without_touching_ingestion_claim() -> None:
    raw = json.dumps(event(), separators=(",", ":"))
    message = IngestionQueueMessage(
        body=raw,
        receipt_handle="receipt",
        message_id="transport",
        receive_count=1,
    )

    class Queue:
        def __init__(self) -> None:
            self.acknowledged: list[str] = []

        def receive(self) -> list[IngestionQueueMessage]:
            return [message]

        def acknowledge(self, received: IngestionQueueMessage) -> None:
            self.acknowledged.append(received.message_id)

    class Claims:
        called = False

        def claim(self, **_: object) -> ClaimDisposition:
            self.called = True
            return ClaimDisposition.RETRY

    class Preflight:
        called = False

        def process(self, _: dict[str, Any]) -> ImportPreflightOutcome:
            self.called = True
            return ImportPreflightOutcome("readable", True)

    queue = Queue()
    claims = Claims()
    preflight = Preflight()
    worker = IngestionWorker(
        queue=cast(SqsIngestionQueue, queue),
        claim_client=cast(IngestionClaimClient, claims),
        stop_event=threading.Event(),
        error_wait_seconds=0.1,
        import_preflight_orchestrator=cast(Any, preflight),
    )

    worker.run_once()

    assert preflight.called is True
    assert claims.called is False
    assert queue.acknowledged == ["transport"]
