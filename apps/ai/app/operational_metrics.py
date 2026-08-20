from __future__ import annotations

from opentelemetry import metrics

from app.telemetry import metric_attributes

_ROUTE_STAGES = {
    "/api/internal/conversation/contextualize": "contextualisation",
    "/api/internal/generation/answer": "generation",
    "/api/internal/retrieval/plan": "retrieval_planning",
    "/api/internal/retrieval/rerank": "reranking",
    "/api/internal/retrieval/search": "retrieval",
}


def record_route_operation(
    route: str, status_code: int, duration_seconds: float
) -> None:
    stage = _ROUTE_STAGES.get(route)
    if stage is None:
        return
    outcome = (
        "success"
        if status_code < 400
        else ("rejected" if status_code < 500 else "failure")
    )
    try:
        attributes = metric_attributes(
            {"rag.operation.stage": stage, "rag.operation.outcome": outcome}
        )
        meter = metrics.get_meter("dolved.python.operations")
        meter.create_counter(
            "rag.operation.count",
            unit="{operation}",
            description="Count of bounded AI operations.",
        ).add(1, attributes)
        meter.create_histogram(
            "rag.operation.duration",
            unit="s",
            description="Duration of bounded AI operations.",
        ).record(max(0.0, duration_seconds), attributes)
    except Exception:  # noqa: BLE001 - telemetry cannot affect application work.
        return


def record_dependency(kind: str, available: bool, duration_seconds: float) -> None:
    try:
        attributes = metric_attributes({"rag.dependency.kind": kind})
        meter = metrics.get_meter("dolved.python.dependencies")
        meter.create_gauge(
            "rag.dependency.available",
            unit="1",
            description="Whether a dependency operation succeeded.",
        ).set(1 if available else 0, attributes)
        meter.create_histogram(
            "rag.dependency.duration",
            unit="s",
            description="Dependency operation duration.",
        ).record(max(0.0, duration_seconds), attributes)
    except Exception:  # noqa: BLE001 - telemetry cannot affect application work.
        return
