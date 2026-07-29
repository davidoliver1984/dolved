import logging
from dataclasses import dataclass
from typing import Any

logger = logging.getLogger("ingestion.sqs")


@dataclass(frozen=True)
class IngestionQueueMessage:
    body: str
    receipt_handle: str
    message_id: str
    receive_count: int


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
        self._queue_url = str(client.get_queue_url(QueueName=queue_name)["QueueUrl"])
        self._wait_time_seconds = wait_time_seconds
        self._visibility_timeout_seconds = visibility_timeout_seconds
        self._batch_size = batch_size

    def receive(self) -> list[IngestionQueueMessage]:
        response = self._client.receive_message(
            QueueUrl=self._queue_url,
            MaxNumberOfMessages=self._batch_size,
            WaitTimeSeconds=self._wait_time_seconds,
            VisibilityTimeout=self._visibility_timeout_seconds,
            AttributeNames=["ApproximateReceiveCount"],
        )
        messages: list[IngestionQueueMessage] = []

        for raw_message in response.get("Messages", []):
            body = raw_message.get("Body")
            receipt_handle = raw_message.get("ReceiptHandle")
            message_id = raw_message.get("MessageId")
            attributes = raw_message.get("Attributes", {})
            receive_count = attributes.get("ApproximateReceiveCount", "1")

            if not all(
                isinstance(value, str)
                for value in (body, receipt_handle, message_id, receive_count)
            ):
                logger.warning(
                    "Malformed SQS receive entry remains unacknowledged.",
                    extra={
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
                    receive_count=int(receive_count),
                )
            )

        return messages

    def acknowledge(self, message: IngestionQueueMessage) -> None:
        self._client.delete_message(
            QueueUrl=self._queue_url,
            ReceiptHandle=message.receipt_handle,
        )
