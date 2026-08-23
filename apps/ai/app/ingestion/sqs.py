import logging
import time
from dataclasses import dataclass, field
from typing import Any

from opentelemetry import metrics, trace
from opentelemetry.trace import SpanKind, Status, StatusCode

from app.telemetry import metric_attributes, trace_attributes

logger = logging.getLogger("ingestion.sqs")


@dataclass(frozen=True)
class IngestionQueueMessage:
    body: str
    receipt_handle: str
    message_id: str
    receive_count: int
    trace_context: dict[str, str] = field(default_factory=dict)
    destination_name: str = "unknown"


class SqsIngestionQueue:
    def __init__(
        self,
        *,
        client: Any,
        queue_name: str,
        wait_time_seconds: int,
        visibility_timeout_seconds: int,
        batch_size: int,
    ) -> None:
        self._client = client
        self._queue_name = queue_name
        self._queue_url = str(client.get_queue_url(QueueName=queue_name)["QueueUrl"])
        self._wait_time_seconds = wait_time_seconds
        self._visibility_timeout_seconds = visibility_timeout_seconds
        self._batch_size = batch_size

    def receive(self) -> list[IngestionQueueMessage]:
        attributes = {
            "messaging.system": "aws_sqs",
            "messaging.destination.name": self._queue_name,
            "messaging.operation.name": "receive",
            "messaging.operation.type": "receive",
        }
        started_at = time.perf_counter()
        meter = metrics.get_meter("dolved.python.ingestion.sqs")
        duration = meter.create_histogram(
            "messaging.client.operation.duration",
            unit="s",
            description="Duration of SQS receive operations.",
        )

        with trace.get_tracer("dolved.python.ingestion.sqs").start_as_current_span(
            f"receive {self._queue_name}",
            kind=SpanKind.CLIENT,
            attributes=trace_attributes(attributes),
            record_exception=False,
            set_status_on_exception=False,
        ) as span:
            try:
                response = self._client.receive_message(
                    QueueUrl=self._queue_url,
                    MaxNumberOfMessages=self._batch_size,
                    WaitTimeSeconds=self._wait_time_seconds,
                    VisibilityTimeout=self._visibility_timeout_seconds,
                    AttributeNames=["ApproximateReceiveCount"],
                    MessageAttributeNames=["traceparent", "tracestate"],
                )
            except Exception as exception:
                span.set_attributes(
                    trace_attributes({"error.type": type(exception).__name__})
                )
                span.set_status(Status(StatusCode.ERROR))
                raise
            finally:
                duration.record(
                    time.perf_counter() - started_at,
                    metric_attributes(attributes),
                )

        messages: list[IngestionQueueMessage] = []

        for raw_message in response.get("Messages", []):
            body = raw_message.get("Body")
            receipt_handle = raw_message.get("ReceiptHandle")
            message_id = raw_message.get("MessageId")
            attributes = raw_message.get("Attributes", {})
            raw_receive_count = attributes.get("ApproximateReceiveCount", "1")
            message_attributes = raw_message.get("MessageAttributes", {})
            receive_count = _parse_receive_count(raw_receive_count)

            if (
                not all(
                    isinstance(value, str)
                    for value in (body, receipt_handle, message_id)
                )
                or receive_count is None
            ):
                logger.warning(
                    "Malformed SQS receive entry remains unacknowledged.",
                    extra={
                        "event_name": "ingestion.queue.invalid_envelope.v1",
                        "sqs_message_id": (
                            message_id if isinstance(message_id, str) else None
                        ),
                        "processing_outcome": "invalid_sqs_envelope",
                    },
                )
                continue

            messages.append(
                IngestionQueueMessage(
                    body=body,
                    receipt_handle=receipt_handle,
                    message_id=message_id,
                    receive_count=receive_count,
                    trace_context=self._trace_context(message_attributes),
                    destination_name=self._queue_name,
                )
            )

        return messages

    def acknowledge(self, message: IngestionQueueMessage) -> None:
        self._client.delete_message(
            QueueUrl=self._queue_url,
            ReceiptHandle=message.receipt_handle,
        )

    def extend_visibility(
        self,
        message: IngestionQueueMessage,
        *,
        visibility_timeout_seconds: int | None = None,
    ) -> None:
        self._client.change_message_visibility(
            QueueUrl=self._queue_url,
            ReceiptHandle=message.receipt_handle,
            VisibilityTimeout=(
                self._visibility_timeout_seconds
                if visibility_timeout_seconds is None
                else visibility_timeout_seconds
            ),
        )

    @staticmethod
    def _trace_context(message_attributes: object) -> dict[str, str]:
        if not isinstance(message_attributes, dict):
            return {}

        context: dict[str, str] = {}

        for name in ("traceparent", "tracestate"):
            attribute = message_attributes.get(name)

            if not isinstance(attribute, dict):
                continue

            value = attribute.get("StringValue")

            if isinstance(value, str):
                context[name] = value

        return context


def _parse_receive_count(value: object) -> int | None:
    if (
        not isinstance(value, str)
        or not value
        or len(value) > 10
        or not value.isascii()
        or not value.isdecimal()
    ):
        return None
    try:
        parsed = int(value)
    except ValueError, OverflowError:
        return None
    return parsed if parsed > 0 else None
