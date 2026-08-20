import io
import json
import logging
import sys

from app.structured_logging import (
    FailureIsolatedStreamHandler,
    PrivacySafeJsonFormatter,
)


def test_formatter_allows_only_safe_fields_and_omits_free_text() -> None:
    record = logging.LogRecord(
        name="test",
        level=logging.INFO,
        pathname=__file__,
        lineno=12,
        msg="sensitive document text",
        args=(),
        exc_info=None,
    )
    record.event_name = "document.ingestion.claimed.v1"
    record.correlation_id = "correlation-1"
    record.document_id = "document-1"
    record.prompt = "sensitive prompt"

    encoded = PrivacySafeJsonFormatter(
        service_name="rag-platform-ai",
        environment="test",
    ).format(record)
    payload = json.loads(encoded)

    assert payload["event_name"] == "document.ingestion.claimed.v1"
    assert payload["correlation_id"] == "correlation-1"
    assert payload["document_id"] == "document-1"
    assert "message" not in payload
    assert "prompt" not in payload
    assert "sensitive" not in encoded


def test_formatter_sanitizes_exception_message_and_frame_locals() -> None:
    sensitive = "private evidence value"
    try:
        raise ValueError(sensitive)
    except ValueError:
        record = logging.LogRecord(
            name="test",
            level=logging.ERROR,
            pathname=__file__,
            lineno=36,
            msg="untrusted %s",
            args=(sensitive,),
            exc_info=sys.exc_info(),
        )
    record.event_name = "generation.failed.v1"

    encoded = PrivacySafeJsonFormatter(
        service_name="rag-platform-ai",
        environment="test",
    ).format(record)
    payload = json.loads(encoded)

    assert payload["exception_type"] == "ValueError"
    assert payload["exception_frames"]
    assert "private evidence value" not in encoded
    assert "sensitive" not in encoded


def test_handler_swallows_stream_failure() -> None:
    class BrokenStream(io.StringIO):
        def write(self, value: str) -> int:
            raise OSError("private stream failure")

    handler = FailureIsolatedStreamHandler(BrokenStream())
    handler.setFormatter(
        PrivacySafeJsonFormatter(
            service_name="rag-platform-ai",
            environment="test",
        )
    )
    record = logging.LogRecord(
        name="test",
        level=logging.INFO,
        pathname=__file__,
        lineno=70,
        msg="ignored",
        args=(),
        exc_info=None,
    )

    handler.emit(record)
