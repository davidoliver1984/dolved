import json
import threading
from typing import cast

import pytest

from app.ingestion.claim_client import ClaimDisposition, IngestionClaimClient
from app.ingestion.contract import CONTRACT_DIRECTORY
from app.ingestion.sqs import IngestionQueueMessage, SqsIngestionQueue
from app.ingestion.worker import IngestionWorker


def contract_example() -> str:
    path = CONTRACT_DIRECTORY / "v1.example.json"
    return json.dumps(json.loads(path.read_text()), separators=(",", ":"))


class FakeQueue:
    def __init__(self, messages: list[IngestionQueueMessage]) -> None:
        self.messages = messages
        self.acknowledged: list[str] = []
        self.receive_count = 0

    def receive(self) -> list[IngestionQueueMessage]:
        self.receive_count += 1
        return self.messages

    def acknowledge(self, message: IngestionQueueMessage) -> None:
        self.acknowledged.append(message.message_id)


class FakeClaimClient:
    def __init__(self, disposition: ClaimDisposition) -> None:
        self.disposition = disposition
        self.event_ids: list[str] = []
        self.bodies: list[str] = []

    def claim(
        self,
        *,
        raw_body: str,
        event_id: str,
        timestamp: int | None = None,
    ) -> ClaimDisposition:
        del timestamp
        self.event_ids.append(event_id)
        self.bodies.append(raw_body)
        return self.disposition


def message(message_id: str, body: str | None = None) -> IngestionQueueMessage:
    return IngestionQueueMessage(
        body=contract_example() if body is None else body,
        receipt_handle=f"receipt-{message_id}",
        message_id=message_id,
        receive_count=1,
    )


def worker_for(
    queue: FakeQueue,
    claim_client: FakeClaimClient,
    stop_event: threading.Event | None = None,
) -> IngestionWorker:
    return IngestionWorker(
        queue=cast(SqsIngestionQueue, queue),
        claim_client=cast(IngestionClaimClient, claim_client),
        stop_event=stop_event or threading.Event(),
        error_wait_seconds=0.1,
    )


def test_valid_durable_claim_is_acknowledged() -> None:
    queue = FakeQueue([message("transport-1")])
    claims = FakeClaimClient(ClaimDisposition.ACKNOWLEDGE)

    processed = worker_for(queue, claims).run_once()

    assert processed == 1
    assert queue.acknowledged == ["transport-1"]
    assert claims.event_ids == ["5a1e9c3e-3b3a-4e2a-9c7d-1f6b6f0a2b41"]


@pytest.mark.parametrize(
    "disposition",
    [ClaimDisposition.POISON, ClaimDisposition.RETRY],
)
def test_failed_claim_is_not_acknowledged(
    disposition: ClaimDisposition,
) -> None:
    queue = FakeQueue([message("transport-1")])
    claims = FakeClaimClient(disposition)

    worker_for(queue, claims).run_once()

    assert queue.acknowledged == []


def test_malformed_or_unsupported_event_never_reaches_laravel_or_acknowledges() -> None:
    unsupported = json.loads(contract_example())
    unsupported["event_version"] = 2
    queue = FakeQueue(
        [
            message("malformed", "{"),
            message(
                "unsupported",
                json.dumps(unsupported, separators=(",", ":")),
            ),
        ]
    )
    claims = FakeClaimClient(ClaimDisposition.ACKNOWLEDGE)

    worker_for(queue, claims).run_once()

    assert claims.event_ids == []
    assert queue.acknowledged == []


def test_duplicate_transport_messages_keep_the_same_logical_event_identity() -> None:
    raw_body = contract_example()
    queue = FakeQueue(
        [
            message("transport-1", raw_body),
            message("transport-2", raw_body),
        ]
    )
    claims = FakeClaimClient(ClaimDisposition.ACKNOWLEDGE)

    worker_for(queue, claims).run_once()

    assert queue.acknowledged == ["transport-1", "transport-2"]
    assert claims.event_ids == [
        "5a1e9c3e-3b3a-4e2a-9c7d-1f6b6f0a2b41",
        "5a1e9c3e-3b3a-4e2a-9c7d-1f6b6f0a2b41",
    ]


def test_shutdown_before_a_batch_does_not_begin_or_acknowledge_work() -> None:
    stop_event = threading.Event()
    stop_event.set()
    queue = FakeQueue([message("transport-1")])
    claims = FakeClaimClient(ClaimDisposition.ACKNOWLEDGE)

    processed = worker_for(queue, claims, stop_event).run_once()

    assert processed == 0
    assert claims.event_ids == []
    assert queue.acknowledged == []


def test_shutdown_during_a_claim_finishes_it_but_starts_no_new_message() -> None:
    stop_event = threading.Event()

    class StoppingClaimClient(FakeClaimClient):
        def claim(
            self,
            *,
            raw_body: str,
            event_id: str,
            timestamp: int | None = None,
        ) -> ClaimDisposition:
            disposition = super().claim(
                raw_body=raw_body,
                event_id=event_id,
                timestamp=timestamp,
            )
            stop_event.set()
            return disposition

    queue = FakeQueue([message("transport-1"), message("transport-2")])
    claims = StoppingClaimClient(ClaimDisposition.ACKNOWLEDGE)

    processed = worker_for(queue, claims, stop_event).run_once()

    assert processed == 1
    assert queue.acknowledged == ["transport-1"]
    assert len(claims.event_ids) == 1
