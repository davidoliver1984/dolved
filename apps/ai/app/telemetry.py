import logging
from dataclasses import dataclass
from typing import Any

from opentelemetry import metrics, trace
from opentelemetry.exporter.otlp.proto.http.metric_exporter import (
    OTLPMetricExporter,
)
from opentelemetry.exporter.otlp.proto.http.trace_exporter import (
    OTLPSpanExporter,
)
from opentelemetry.sdk.metrics import MeterProvider
from opentelemetry.sdk.metrics.export import PeriodicExportingMetricReader
from opentelemetry.sdk.resources import Resource
from opentelemetry.sdk.trace import TracerProvider
from opentelemetry.sdk.trace.export import BatchSpanProcessor

from app.settings import Settings

logger = logging.getLogger("telemetry")

TRACE_ATTRIBUTE_ALLOWLIST = frozenset(
    {
        "error.type",
        "gen_ai.operation.name",
        "gen_ai.provider.name",
        "gen_ai.request.model",
        "gen_ai.usage.input_tokens",
        "http.request.method",
        "http.response.status_code",
        "http.route",
        "messaging.destination.name",
        "messaging.message.delivery_count",
        "messaging.message.id",
        "messaging.operation.name",
        "messaging.operation.type",
        "messaging.system",
        "rag.correlation.id",
        "rag.document.id",
        "rag.event.id",
        "rag.event.version",
        "rag.embedding.estimated_cost_usd",
        "rag.embedding.item_count",
        "rag.embedding.profile_fingerprint",
        "rag.embedding.purpose",
        "rag.embedding.retry_count",
        "rag.processing.outcome",
        "rag.workspace.id",
    }
)

METRIC_ATTRIBUTE_ALLOWLIST = frozenset(
    {
        "http.request.method",
        "error.type",
        "gen_ai.operation.name",
        "gen_ai.provider.name",
        "gen_ai.request.model",
        "http.response.status_code",
        "http.route",
        "messaging.destination.name",
        "messaging.operation.name",
        "messaging.operation.type",
        "messaging.system",
        "rag.event.version",
        "rag.embedding.purpose",
        "rag.processing.outcome",
    }
)


def trace_attributes(attributes: dict[str, Any]) -> dict[str, Any]:
    return {
        key: value
        for key, value in attributes.items()
        if key in TRACE_ATTRIBUTE_ALLOWLIST and value is not None
    }


def metric_attributes(attributes: dict[str, Any]) -> dict[str, Any]:
    return {
        key: value
        for key, value in attributes.items()
        if key in METRIC_ATTRIBUTE_ALLOWLIST and value is not None
    }


@dataclass(frozen=True)
class TelemetryLifecycle:
    tracer_provider: TracerProvider | None = None
    meter_provider: MeterProvider | None = None

    def shutdown(self) -> None:
        for provider in (self.tracer_provider, self.meter_provider):
            if provider is None:
                continue

            try:
                provider.shutdown()
            except Exception:  # noqa: BLE001 - telemetry is best effort.
                logger.warning(
                    "OpenTelemetry shutdown failed.",
                    extra={"telemetry_outcome": "shutdown_failed"},
                )


def configure_telemetry(settings: Settings) -> TelemetryLifecycle:
    if settings.otel_sdk_disabled:
        return TelemetryLifecycle()

    try:
        if settings.otel_exporter_otlp_protocol != "http/protobuf":
            raise ValueError("Python telemetry requires OTLP over HTTP/protobuf.")

        endpoint = settings.otel_exporter_otlp_endpoint.rstrip("/")
        timeout_seconds = settings.otel_exporter_otlp_timeout / 1_000
        resource = Resource(
            {
                "service.name": settings.service_name,
                "deployment.environment.name": settings.environment,
            }
        )
        tracer_provider = TracerProvider(resource=resource)
        tracer_provider.add_span_processor(
            BatchSpanProcessor(
                OTLPSpanExporter(
                    endpoint=f"{endpoint}/v1/traces",
                    timeout=timeout_seconds,
                ),
                export_timeout_millis=settings.otel_exporter_otlp_timeout,
            )
        )
        metric_reader = PeriodicExportingMetricReader(
            OTLPMetricExporter(
                endpoint=f"{endpoint}/v1/metrics",
                timeout=timeout_seconds,
            ),
            export_timeout_millis=settings.otel_exporter_otlp_timeout,
        )
        meter_provider = MeterProvider(
            resource=resource,
            metric_readers=[metric_reader],
        )
        trace.set_tracer_provider(tracer_provider)
        metrics.set_meter_provider(meter_provider)

        return TelemetryLifecycle(tracer_provider, meter_provider)
    except Exception as exception:  # noqa: BLE001 - fall back to no-op telemetry.
        logger.warning(
            "OpenTelemetry SDK setup failed; using no-op telemetry.",
            extra={
                "error_type": type(exception).__name__,
                "telemetry_outcome": "setup_failed",
            },
        )

        return TelemetryLifecycle()
