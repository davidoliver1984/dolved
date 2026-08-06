import logging
from typing import Any

from app.ingestion.sqs import IngestionQueueMessage, SqsIngestionQueue


class FakeSqsClient:
    def __init__(self) -> None:
        self.receive_arguments: dict[str, Any] = {}
        self.delete_arguments: dict[str, Any] = {}
        self.visibility_arguments: dict[str, Any] = {}

    def get_queue_url(self, **arguments: Any) -> dict[str, str]:
        assert arguments == {"QueueName": "ingestion"}

        return {"QueueUrl": "http://localstack/queue/ingestion"}

    def receive_message(self, **arguments: Any) -> dict[str, object]:
        self.receive_arguments = arguments

        return {
            "Messages": [
                {
                    "Body": '{"event_id":"logical-event"}',
                    "ReceiptHandle": "receipt",
                    "MessageId": "transport-message",
                    "Attributes": {
                        "ApproximateReceiveCount": "3",
                    },
                    "MessageAttributes": {
                        "traceparent": {
                            "DataType": "String",
                            "StringValue": (
                                "00-11111111111111111111111111111111-"
                                "2222222222222222-01"
                            ),
                        },
                        "tracestate": {
                            "DataType": "String",
                            "StringValue": "vendor=value",
                        },
                    },
                }
            ]
        }

    def delete_message(self, **arguments: Any) -> None:
        self.delete_arguments = arguments

    def change_message_visibility(self, **arguments: Any) -> None:
        self.visibility_arguments = arguments


def test_sqs_adapter_preserves_transport_metadata_and_poll_configuration() -> None:
    client = FakeSqsClient()
    queue = SqsIngestionQueue(
        client=client,
        queue_name="ingestion",
        wait_time_seconds=10,
        visibility_timeout_seconds=30,
        batch_size=2,
    )

    messages = queue.receive()

    assert messages == [
        IngestionQueueMessage(
            body='{"event_id":"logical-event"}',
            receipt_handle="receipt",
            message_id="transport-message",
            receive_count=3,
            trace_context={
                "traceparent": (
                    "00-11111111111111111111111111111111-2222222222222222-01"
                ),
                "tracestate": "vendor=value",
            },
            destination_name="ingestion",
        )
    ]
    assert client.receive_arguments == {
        "QueueUrl": "http://localstack/queue/ingestion",
        "MaxNumberOfMessages": 2,
        "WaitTimeSeconds": 10,
        "VisibilityTimeout": 30,
        "AttributeNames": ["ApproximateReceiveCount"],
        "MessageAttributeNames": ["traceparent", "tracestate"],
    }

    queue.acknowledge(messages[0])

    assert client.delete_arguments == {
        "QueueUrl": "http://localstack/queue/ingestion",
        "ReceiptHandle": "receipt",
    }
    queue.extend_visibility(messages[0])
    assert client.visibility_arguments == {
        "QueueUrl": "http://localstack/queue/ingestion",
        "ReceiptHandle": "receipt",
        "VisibilityTimeout": 30,
    }


def test_malformed_sqs_entry_is_not_returned_and_is_logged(
    caplog: Any,
) -> None:
    class MalformedSqsClient(FakeSqsClient):
        def receive_message(self, **arguments: Any) -> dict[str, object]:
            self.receive_arguments = arguments

            return {
                "Messages": [
                    {
                        "Body": '{"event_id":"logical-event"}',
                        "MessageId": "transport-message",
                        "Attributes": {
                            "ApproximateReceiveCount": "1",
                        },
                    }
                ]
            }

    queue = SqsIngestionQueue(
        client=MalformedSqsClient(),
        queue_name="ingestion",
        wait_time_seconds=10,
        visibility_timeout_seconds=30,
        batch_size=1,
    )

    with caplog.at_level(logging.WARNING, logger="ingestion.sqs"):
        assert queue.receive() == []

    assert "Malformed SQS receive entry remains unacknowledged." in caplog.text
    record_context = caplog.records[0].__dict__

    assert record_context["sqs_message_id"] == "transport-message"
    assert record_context["processing_outcome"] == "invalid_sqs_envelope"
