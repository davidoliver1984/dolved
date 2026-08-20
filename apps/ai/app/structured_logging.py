import json
import logging
import re
import traceback
from datetime import UTC, datetime
from pathlib import Path
from types import TracebackType
from typing import Any, TextIO

from opentelemetry import trace

_EVENT_NAME_PATTERN = re.compile(r"^[a-z][a-z0-9_.-]*\.v[1-9][0-9]*$")
_SAFE_CONTEXT_FIELDS = frozenset(
    {
        "attempt_count",
        "claim_outcome",
        "conversation_id",
        "correlation_id",
        "document_id",
        "document_status",
        "duration_ms",
        "duration_seconds",
        "embedding_http_status",
        "embedding_input_tokens",
        "embedding_item_count",
        "embedding_model",
        "embedding_outcome",
        "embedding_provider",
        "embedding_provider_timing_source",
        "embedding_purpose",
        "embedding_request_timestamp",
        "embedding_retry_after_seconds",
        "embedding_retry_count",
        "event_id",
        "event_version",
        "failure_category",
        "failure_class",
        "failure_provider",
        "failure_stage",
        "key_id",
        "operation_kind",
        "outcome",
        "processing_outcome",
        "provider_status",
        "receive_count",
        "request_id",
        "retry_delay_seconds",
        "run_id",
        "signal",
        "sqs_message_id",
        "systemic",
        "telemetry_outcome",
        "transport_message_id",
        "verification_outcome",
        "workspace_id",
    }
)
_STANDARD_LOG_RECORD_FIELDS = frozenset(
    logging.LogRecord(
        name="",
        level=0,
        pathname="",
        lineno=0,
        msg="",
        args=(),
        exc_info=None,
    ).__dict__
)


def _safe_scalar(value: object) -> str | int | float | bool | None:
    if value is None or isinstance(value, str | int | float | bool):
        return value
    return None


def _safe_frames(traceback_value: TracebackType | None) -> list[dict[str, object]]:
    if traceback_value is None:
        return []

    return [
        {
            "file": Path(frame.filename).name,
            "function": frame.name,
            "line": frame.lineno,
        }
        for frame in traceback.extract_tb(traceback_value)
    ]


class PrivacySafeJsonFormatter(logging.Formatter):
    def __init__(self, *, service_name: str, environment: str) -> None:
        super().__init__()
        self._service_name = service_name
        self._environment = environment

    def format(self, record: logging.LogRecord) -> str:
        event_name = getattr(record, "event_name", "application.unclassified.v1")
        if not isinstance(event_name, str) or not _EVENT_NAME_PATTERN.fullmatch(
            event_name
        ):
            event_name = "application.unclassified.v1"

        payload: dict[str, Any] = {
            "timestamp": datetime.now(UTC).isoformat(),
            "level": record.levelname.lower(),
            "service": self._service_name,
            "environment": self._environment,
            "event_name": event_name,
        }

        span_context = trace.get_current_span().get_span_context()
        if span_context.is_valid:
            payload["trace_id"] = format(span_context.trace_id, "032x")
            payload["span_id"] = format(span_context.span_id, "016x")

        for key, value in record.__dict__.items():
            if key in _STANDARD_LOG_RECORD_FIELDS or key not in _SAFE_CONTEXT_FIELDS:
                continue
            safe_value = _safe_scalar(value)
            if safe_value is not None:
                payload[key] = safe_value

        if record.exc_info:
            exception_type, _, traceback_value = record.exc_info
            if exception_type is not None:
                payload["exception_type"] = exception_type.__name__
            frames = _safe_frames(traceback_value)
            if frames:
                payload["exception_frames"] = frames
        elif isinstance(record.__dict__.get("exception"), BaseException):
            exception = record.__dict__["exception"]
            payload["exception_type"] = type(exception).__name__
            frames = _safe_frames(exception.__traceback__)
            if frames:
                payload["exception_frames"] = frames
        elif isinstance(record.__dict__.get("error_type"), str):
            payload["exception_type"] = record.__dict__["error_type"]

        return json.dumps(payload, separators=(",", ":"), sort_keys=True)


class FailureIsolatedStreamHandler(logging.StreamHandler[TextIO]):
    def emit(self, record: logging.LogRecord) -> None:
        try:
            message = self.format(record)
            self.stream.write(message + self.terminator)
            self.flush()
        except Exception:  # noqa: BLE001 - logging must never fail the observed work
            return


def configure_structured_logging(
    *, service_name: str, environment: str, level: int = logging.INFO
) -> None:
    handler = FailureIsolatedStreamHandler()
    handler.setFormatter(
        PrivacySafeJsonFormatter(
            service_name=service_name,
            environment=environment,
        )
    )
    root = logging.getLogger()
    root.handlers = [handler]
    root.setLevel(level)
