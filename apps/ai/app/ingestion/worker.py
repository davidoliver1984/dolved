import logging
import threading

from app.ingestion.claim_client import ClaimDisposition, IngestionClaimClient
from app.ingestion.contract import InvalidIngestionEvent, parse_and_validate_event
from app.ingestion.sqs import IngestionQueueMessage, SqsIngestionQueue

logger = logging.getLogger("ingestion.worker")


class IngestionWorker:
    def __init__(
        self,
        *,
        queue: SqsIngestionQueue,
        claim_client: IngestionClaimClient,
        stop_event: threading.Event,
        error_wait_seconds: float,
    ) -> None:
        self._queue = queue
        self._claim_client = claim_client
        self._stop_event = stop_event
        self._error_wait_seconds = error_wait_seconds

    def run_once(self) -> int:
        messages = self._queue.receive()
        processed = 0

        for message in messages:
            if self._stop_event.is_set():
                break

            self._handle(message)
            processed += 1

        return processed

    def run(self) -> None:
        while not self._stop_event.is_set():
            try:
                self.run_once()
            except Exception:
                logger.exception("Ingestion queue polling failed.")
                self._stop_event.wait(self._error_wait_seconds)

    def _handle(self, message: IngestionQueueMessage) -> None:
        context: dict[str, object] = {
            "sqs_message_id": message.message_id,
            "receive_count": message.receive_count,
        }

        try:
            event = parse_and_validate_event(message.body)
        except InvalidIngestionEvent:
            logger.warning(
                "Ingestion event is poison and remains unacknowledged.",
                extra={**context, "processing_outcome": "invalid_event"},
            )
            return

        event_id = str(event["event_id"])
        context.update(
            {
                "event_id": event_id,
                "event_version": event["event_version"],
                "correlation_id": event["correlation_id"],
                "workspace_id": event["workspace_id"],
                "document_id": event["document_id"],
            }
        )
        disposition = self._claim_client.claim(
            raw_body=message.body,
            event_id=event_id,
        )

        if disposition is ClaimDisposition.ACKNOWLEDGE:
            self._queue.acknowledge(message)
            logger.info(
                "Ingestion event was durably claimed and acknowledged.",
                extra={**context, "processing_outcome": disposition.value},
            )
            return

        logger.warning(
            "Ingestion event remains unacknowledged.",
            extra={**context, "processing_outcome": disposition.value},
        )
