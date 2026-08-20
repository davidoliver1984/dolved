from opentelemetry.sdk.metrics import MeterProvider
from opentelemetry.sdk.metrics.export import InMemoryMetricReader

from app.operational_metrics import record_route_operation


def test_operational_metrics_use_bounded_stage_and_outcome(monkeypatch) -> None:
    reader = InMemoryMetricReader()
    provider = MeterProvider(metric_readers=[reader])
    monkeypatch.setattr("app.operational_metrics.metrics.get_meter", provider.get_meter)

    record_route_operation("/api/internal/retrieval/search", 200, 0.25)
    record_route_operation("/api/internal/retrieval/search", 503, 0.5)
    record_route_operation("/unrecognised", 200, 1.0)

    data = reader.get_metrics_data()
    assert data is not None
    metrics = [
        metric
        for resource in data.resource_metrics
        for scope in resource.scope_metrics
        for metric in scope.metrics
    ]
    count = next(metric for metric in metrics if metric.name == "rag.operation.count")
    attributes = [
        {key: value for key, value in (point.attributes or {}).items()}
        for point in count.data.data_points
    ]
    assert {item["rag.operation.outcome"] for item in attributes} == {
        "success",
        "failure",
    }
    assert all(
        item
        == {
            "rag.operation.stage": "retrieval",
            "rag.operation.outcome": item["rag.operation.outcome"],
        }
        for item in attributes
    )
