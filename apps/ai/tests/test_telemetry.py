import json
import threading
from typing import Any, cast

import httpx
import pytest
from fastapi.testclient import TestClient
from opentelemetry import trace
from opentelemetry.sdk.metrics import MeterProvider
from opentelemetry.sdk.metrics.export import InMemoryMetricReader
from opentelemetry.sdk.trace import TracerProvider
from opentelemetry.sdk.trace.export import SimpleSpanProcessor
from opentelemetry.sdk.trace.export.in_memory_span_exporter import (
    InMemorySpanExporter,
)

from app.ingestion.claim_client import ClaimDisposition, IngestionClaimClient
from app.ingestion.signing import IngestionWorkerSigner
from app.ingestion.sqs import IngestionQueueMessage, SqsIngestionQueue
from app.ingestion.worker import IngestionWorker
from app.settings import Settings
from app.telemetry import (
    TelemetryLifecycle,
    configure_telemetry,
    metric_attributes,
    trace_attributes,
)

TEST_SECRET = "MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY="


class FakeQueue:
    def __init__(self, message: IngestionQueueMessage) -> None:
        self.message = message
        self.acknowledged: list[str] = []

    def receive(self) -> list[IngestionQueueMessage]:
        return [self.message]

    def acknowledge(self, message: IngestionQueueMessage) -> None:
        self.acknowledged.append(message.message_id)


def telemetry_providers() -> tuple[
    InMemorySpanExporter,
    TracerProvider,
    InMemoryMetricReader,
    MeterProvider,
]:
    span_exporter = InMemorySpanExporter()
    tracer_provider = TracerProvider()
    tracer_provider.add_span_processor(SimpleSpanProcessor(span_exporter))
    metric_reader = InMemoryMetricReader()
    meter_provider = MeterProvider(metric_readers=[metric_reader])

    return span_exporter, tracer_provider, metric_reader, meter_provider


def ingestion_event() -> str:
    return json.dumps(
        {
            "event_id": "5a1e9c3e-3b3a-4e2a-9c7d-1f6b6f0a2b41",
            "event_type": "document.ingestion.requested",
            "event_version": 1,
            "occurred_at": "2026-07-29T12:00:00Z",
            "correlation_id": "1b8c3a32-19ad-4ade-9fe0-910fde6799c3",
            "workspace_id": "6deaf16f-7e2f-40d7-a933-53aee9048207",
            "document_id": "7b72d7ab-3867-4d06-bfd2-a7bb86f7de7c",
            "storage_bucket": "documents",
            "storage_key": "private/source.txt",
            "media_type": "text/plain",
            "byte_size": 18,
        },
        separators=(",", ":"),
    )


def test_attribute_allowlists_exclude_payloads_and_metric_entity_ids() -> None:
    candidate = {
        "http.route": "/health",
        "rag.document.id": "document-id",
        "rag.workspace.id": "workspace-id",
        "document.content": "synthetic-private-content",
        "prompt": "synthetic-private-prompt",
        "password": "synthetic-secret",
    }

    assert trace_attributes(candidate) == {
        "http.route": "/health",
        "rag.document.id": "document-id",
        "rag.workspace.id": "workspace-id",
    }
    assert metric_attributes(candidate) == {"http.route": "/health"}


def test_invalid_exporter_configuration_falls_back_to_noop() -> None:
    lifecycle = configure_telemetry(
        Settings(
            otel_exporter_otlp_protocol="grpc",
            otel_sdk_disabled=False,
        )
    )

    assert lifecycle == TelemetryLifecycle()


def test_telemetry_shutdown_failure_does_not_escape() -> None:
    class FailingProvider:
        def shutdown(self) -> None:
            raise RuntimeError("synthetic collector failure")

    lifecycle = TelemetryLifecycle(
        tracer_provider=cast(TracerProvider, FailingProvider()),
        meter_provider=cast(MeterProvider, FailingProvider()),
    )

    lifecycle.shutdown()


def test_ai_http_service_extracts_parent_and_uses_route_template(
    monkeypatch: Any,
) -> None:
    from app import main as main_module
    from app.telemetry import TelemetryLifecycle

    span_exporter, tracer_provider, metric_reader, meter_provider = (
        telemetry_providers()
    )
    monkeypatch.setattr(main_module.trace, "get_tracer", tracer_provider.get_tracer)
    monkeypatch.setattr(main_module.metrics, "get_meter", meter_provider.get_meter)
    monkeypatch.setattr(
        main_module,
        "configure_telemetry",
        lambda settings: TelemetryLifecycle(),
    )
    trace_id = "11111111111111111111111111111111"
    parent_span_id = "2222222222222222"

    with TestClient(main_module.app) as client:
        response = client.get(
            "/health?question=synthetic-private-question",
            headers={
                "traceparent": f"00-{trace_id}-{parent_span_id}-01",
            },
        )

    assert response.status_code == 200
    span = span_exporter.get_finished_spans()[0]
    assert span.name == "GET /health"
    assert f"{span.context.trace_id:032x}" == trace_id
    assert span.parent is not None
    assert f"{span.parent.span_id:016x}" == parent_span_id
    assert span.attributes == {
        "http.request.method": "GET",
        "http.route": "/health",
        "http.response.status_code": 200,
    }
    assert "synthetic-private-question" not in repr(span_exporter.get_finished_spans())
    metrics_data = repr(metric_reader.get_metrics_data())
    assert "http.server.request.count" in metrics_data
    assert "synthetic-private-question" not in metrics_data

    tracer_provider.shutdown()
    meter_provider.shutdown()


def test_worker_extracts_sqs_parent_and_emits_safe_trace_and_metrics(
    monkeypatch: Any,
) -> None:
    span_exporter, tracer_provider, metric_reader, meter_provider = (
        telemetry_providers()
    )
    monkeypatch.setattr(
        "app.ingestion.worker.trace.get_tracer",
        tracer_provider.get_tracer,
    )
    monkeypatch.setattr(
        "app.ingestion.worker.metrics.get_meter",
        meter_provider.get_meter,
    )
    parent_trace_id = "11111111111111111111111111111111"
    parent_span_id = "2222222222222222"
    message = IngestionQueueMessage(
        body=ingestion_event(),
        receipt_handle="receipt",
        message_id="transport-message",
        receive_count=2,
        trace_context={"traceparent": (f"00-{parent_trace_id}-{parent_span_id}-01")},
    )
    queue = FakeQueue(message)

    class AcknowledgingClaimClient:
        def claim(self, **arguments: object) -> ClaimDisposition:
            del arguments
            return ClaimDisposition.ACKNOWLEDGE

    worker = IngestionWorker(
        queue=cast(SqsIngestionQueue, queue),
        claim_client=cast(IngestionClaimClient, AcknowledgingClaimClient()),
        stop_event=threading.Event(),
        error_wait_seconds=0.1,
    )

    assert worker.run_once() == 1
    assert queue.acknowledged == ["transport-message"]
    span = span_exporter.get_finished_spans()[0]
    assert f"{span.context.trace_id:032x}" == parent_trace_id
    assert span.parent is not None
    assert f"{span.parent.span_id:016x}" == parent_span_id
    assert span.attributes is not None
    assert span.attributes["rag.document.id"] == (
        "7b72d7ab-3867-4d06-bfd2-a7bb86f7de7c"
    )
    serialized_spans = repr(span_exporter.get_finished_spans())
    assert "private/source.txt" not in serialized_spans
    serialized_metrics = repr(metric_reader.get_metrics_data())
    assert "rag.ingestion.message.count" in serialized_metrics
    assert "rag.document.id" not in serialized_metrics
    assert "rag.workspace.id" not in serialized_metrics

    tracer_provider.shutdown()
    meter_provider.shutdown()


def test_worker_records_unexpected_failure_without_exception_message(
    monkeypatch: Any,
) -> None:
    span_exporter, tracer_provider, metric_reader, meter_provider = (
        telemetry_providers()
    )
    monkeypatch.setattr(
        "app.ingestion.worker.trace.get_tracer",
        tracer_provider.get_tracer,
    )
    monkeypatch.setattr(
        "app.ingestion.worker.metrics.get_meter",
        meter_provider.get_meter,
    )

    class FailingClaimClient:
        def claim(self, **arguments: object) -> ClaimDisposition:
            del arguments
            raise RuntimeError("synthetic-private-exception-message")

    worker = IngestionWorker(
        queue=cast(
            SqsIngestionQueue,
            FakeQueue(
                IngestionQueueMessage(
                    body=ingestion_event(),
                    receipt_handle="receipt",
                    message_id="transport-message",
                    receive_count=1,
                )
            ),
        ),
        claim_client=cast(IngestionClaimClient, FailingClaimClient()),
        stop_event=threading.Event(),
        error_wait_seconds=0.1,
    )

    with pytest.raises(RuntimeError):
        worker.run_once()

    span = span_exporter.get_finished_spans()[0]
    assert span.attributes is not None
    assert span.attributes["error.type"] == "RuntimeError"
    assert "synthetic-private-exception-message" not in repr(span.attributes)
    assert "synthetic-private-exception-message" not in repr(span.events)
    metrics_data = repr(metric_reader.get_metrics_data())
    assert "rag.processing.outcome': 'error'" in metrics_data
    assert "synthetic-private-exception-message" not in metrics_data

    tracer_provider.shutdown()
    meter_provider.shutdown()


def test_claim_injects_current_w3c_context_without_exposing_body(
    monkeypatch: Any,
) -> None:
    span_exporter, tracer_provider, metric_reader, meter_provider = (
        telemetry_providers()
    )
    monkeypatch.setattr(
        "app.ingestion.claim_client.trace.get_tracer",
        tracer_provider.get_tracer,
    )
    monkeypatch.setattr(
        "app.ingestion.claim_client.metrics.get_meter",
        meter_provider.get_meter,
    )
    captured: dict[str, str] = {}

    def capture(request: httpx.Request) -> httpx.Response:
        captured["traceparent"] = request.headers["traceparent"]
        return httpx.Response(200, json={"data": {"outcome": "claimed"}})

    client = IngestionClaimClient(
        base_url="http://api:8000",
        timeout_seconds=2,
        signer=IngestionWorkerSigner("local-v1", TEST_SECRET),
        client=httpx.Client(transport=httpx.MockTransport(capture)),
    )
    parent = tracer_provider.get_tracer("test").start_span("parent")

    with trace.use_span(parent, end_on_exit=True):
        client.claim(
            raw_body='{"event_id":"synthetic-private-body"}',
            event_id="5a1e9c3e-3b3a-4e2a-9c7d-1f6b6f0a2b41",
            timestamp=1_785_326_400,
        )

    assert captured["traceparent"].startswith(
        f"00-{parent.get_span_context().trace_id:032x}-"
    )
    spans = span_exporter.get_finished_spans()
    claim_span = next(
        span
        for span in spans
        if span.name == "POST /api/internal/ingestion/events/{event}/claim"
    )
    assert claim_span.parent is not None
    assert claim_span.parent.span_id == parent.get_span_context().span_id
    assert "synthetic-private-body" not in repr(spans)
    assert "rag.event.id" not in repr(metric_reader.get_metrics_data())

    tracer_provider.shutdown()
    meter_provider.shutdown()
