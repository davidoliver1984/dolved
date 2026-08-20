import json
import logging
import threading
import time

from opentelemetry import metrics, trace
from opentelemetry.propagate import extract
from opentelemetry.trace import SpanKind, Status, StatusCode

from app.deletion.contract import (
    InvalidDocumentDeletionEvent,
    parse_and_validate_deletion_event,
)
from app.deletion.orchestrator import DocumentDeletionOrchestrator
from app.ingestion.claim_client import ClaimDisposition, IngestionClaimClient
from app.ingestion.contract import InvalidIngestionEvent, parse_and_validate_event
from app.ingestion.orchestrator import IngestionOrchestrator
from app.ingestion.sqs import IngestionQueueMessage, SqsIngestionQueue
from app.telemetry import metric_attributes, trace_attributes

logger = logging.getLogger("ingestion.worker")


class IngestionWorker:
    def __init__(
        self,
        *,
        queue: SqsIngestionQueue,
        claim_client: IngestionClaimClient,
        stop_event: threading.Event,
        error_wait_seconds: float,
        orchestrator: IngestionOrchestrator | None = None,
        deletion_orchestrator: DocumentDeletionOrchestrator | None = None,
        reconcile_dlq: bool = False,
    ) -> None:
        self._queue = queue
        self._claim_client = claim_client
        self._stop_event = stop_event
        self._error_wait_seconds = error_wait_seconds
        self._orchestrator = orchestrator
        self._deletion_orchestrator = deletion_orchestrator
        self._reconcile_dlq = reconcile_dlq

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
                logger.exception(
                    "Ingestion queue polling failed.",
                    extra={"event_name": "ingestion.queue.poll_failed.v1"},
                )
                self._stop_event.wait(self._error_wait_seconds)

    def _handle(self, message: IngestionQueueMessage) -> None:
        context: dict[str, object] = {
            "sqs_message_id": message.message_id,
            "receive_count": message.receive_count,
        }
        attributes: dict[str, object] = {
            "messaging.system": "aws_sqs",
            "messaging.destination.name": message.destination_name,
            "messaging.operation.name": "process",
            "messaging.operation.type": "process",
            "messaging.message.id": message.message_id,
            "messaging.message.delivery_count": message.receive_count,
        }
        meter = metrics.get_meter("dolved.python.ingestion.worker")
        processed_count = meter.create_counter(
            "rag.ingestion.message.count",
            unit="{message}",
            description="Count of ingestion messages handled by the Python worker.",
        )
        processing_duration = meter.create_histogram(
            "messaging.process.duration",
            unit="s",
            description="Duration of ingestion message processing.",
        )
        started_at = time.perf_counter()
        parent_context = extract(message.trace_context)

        with trace.get_tracer("dolved.python.ingestion.worker").start_as_current_span(
            f"process {message.destination_name}",
            context=parent_context,
            kind=SpanKind.CONSUMER,
            attributes=trace_attributes(attributes),
            record_exception=False,
            set_status_on_exception=False,
        ) as span:
            outcome = "error"

            try:
                outcome = self._process_message(message, context, attributes)
            except Exception as exception:
                attributes["error.type"] = type(exception).__name__
                span.set_status(Status(StatusCode.ERROR))
                raise
            finally:
                attributes["rag.processing.outcome"] = outcome
                span.set_attributes(trace_attributes(attributes))
                metric_values = metric_attributes(attributes)
                processed_count.add(1, metric_values)
                processing_duration.record(
                    time.perf_counter() - started_at,
                    metric_values,
                )

                if outcome != ClaimDisposition.ACKNOWLEDGE.value:
                    span.set_status(Status(StatusCode.ERROR))

    def _process_message(
        self,
        message: IngestionQueueMessage,
        context: dict[str, object],
        attributes: dict[str, object],
    ) -> str:

        try:
            discriminator = json.loads(message.body)
        except json.JSONDecodeError:
            discriminator = None
        if (
            isinstance(discriminator, dict)
            and discriminator.get("event_type") == "document.deletion.requested"
        ):
            try:
                deletion_event = parse_and_validate_deletion_event(message.body)
            except InvalidDocumentDeletionEvent:
                logger.warning(
                    "Document deletion event is poison and remains unacknowledged.",
                    extra={
                        **context,
                        "event_name": "document.deletion.invalid_event.v1",
                        "processing_outcome": "invalid_event",
                    },
                )
                return "invalid_event"
            if self._deletion_orchestrator is None:
                return "deletion_orchestrator_unavailable"
            deletion_result = self._deletion_orchestrator.process(
                event=deletion_event, raw_body=message.body
            )
            if deletion_result.acknowledge:
                self._queue.acknowledge(message)
            return deletion_result.code

        try:
            event = parse_and_validate_event(message.body)
        except InvalidIngestionEvent:
            logger.warning(
                "Ingestion event is poison and remains unacknowledged.",
                extra={
                    **context,
                    "event_name": "document.ingestion.invalid_event.v1",
                    "processing_outcome": "invalid_event",
                },
            )
            return "invalid_event"

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
        attributes.update(
            {
                "rag.event.id": event_id,
                "rag.event.version": event["event_version"],
                "rag.correlation.id": event["correlation_id"],
                "rag.workspace.id": event["workspace_id"],
                "rag.document.id": event["document_id"],
            }
        )
        if self._orchestrator is not None:
            result = (
                self._orchestrator.reconcile_dlq(
                    event=event,
                    raw_body=message.body,
                    message=message,
                )
                if self._reconcile_dlq
                else self._orchestrator.process(
                    event=event,
                    raw_body=message.body,
                    message=message,
                )
            )
            if result.acknowledge:
                self._queue.acknowledge(message)
                logger.info(
                    "Ingestion event reached an authoritative terminal outcome.",
                    extra={
                        **context,
                        "event_name": "document.ingestion.terminal.v1",
                        "processing_outcome": result.code,
                    },
                )
            else:
                logger.warning(
                    "Ingestion event remains unacknowledged for recovery.",
                    extra={
                        **context,
                        "event_name": "document.ingestion.recovery_pending.v1",
                        "processing_outcome": result.code,
                    },
                )
            return result.code

        disposition = self._claim_client.claim(
            raw_body=message.body,
            event_id=event_id,
        )

        if disposition is ClaimDisposition.ACKNOWLEDGE:
            self._queue.acknowledge(message)
            logger.info(
                "Ingestion event was durably claimed and acknowledged.",
                extra={
                    **context,
                    "event_name": "document.ingestion.claimed.v1",
                    "processing_outcome": disposition.value,
                },
            )
            return disposition.value

        logger.warning(
            "Ingestion event remains unacknowledged.",
            extra={
                **context,
                "event_name": "document.ingestion.unacknowledged.v1",
                "processing_outcome": disposition.value,
            },
        )

        return disposition.value
