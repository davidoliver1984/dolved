import time
from collections.abc import AsyncIterator, Awaitable, Callable
from contextlib import asynccontextmanager

from fastapi import FastAPI, Request, Response
from opentelemetry import metrics, trace
from opentelemetry.propagate import extract
from opentelemetry.trace import SpanKind, Status, StatusCode

from app.settings import get_settings
from app.telemetry import (
    configure_telemetry,
    metric_attributes,
    trace_attributes,
)


@asynccontextmanager
async def lifespan(application: FastAPI) -> AsyncIterator[None]:
    del application
    lifecycle = configure_telemetry(get_settings())

    try:
        yield
    finally:
        lifecycle.shutdown()


app = FastAPI(
    title="RAG Platform AI Service",
    version="0.1.0",
    lifespan=lifespan,
)


@app.middleware("http")
async def trace_http_request(
    request: Request,
    call_next: Callable[[Request], Awaitable[Response]],
) -> Response:
    started_at = time.perf_counter()
    method = request.method.upper()
    route_template = "unmatched"
    status_code = 500
    tracer = trace.get_tracer("maketime.python.http")
    meter = metrics.get_meter("maketime.python.http")
    request_count = meter.create_counter(
        "http.server.request.count",
        unit="{request}",
        description="Count of Python AI service HTTP requests.",
    )
    request_duration = meter.create_histogram(
        "http.server.request.duration",
        unit="s",
        description="Duration of Python AI service HTTP requests.",
    )

    with tracer.start_as_current_span(
        f"{method} {route_template}",
        context=extract(request.headers),
        kind=SpanKind.SERVER,
        attributes=trace_attributes(
            {
                "http.request.method": method,
                "http.route": route_template,
            }
        ),
        record_exception=False,
        set_status_on_exception=False,
    ) as span:
        try:
            response = await call_next(request)
            status_code = response.status_code
            route = request.scope.get("route")
            route_template = getattr(route, "path", "unmatched")
            span.update_name(f"{method} {route_template}")
            span.set_attributes(
                trace_attributes(
                    {
                        "http.route": route_template,
                        "http.response.status_code": status_code,
                    }
                )
            )

            if status_code >= 500:
                span.set_status(Status(StatusCode.ERROR))

            return response
        except Exception as exception:
            span.set_attributes(
                trace_attributes(
                    {
                        "error.type": type(exception).__name__,
                        "http.response.status_code": 500,
                    }
                )
            )
            span.set_status(Status(StatusCode.ERROR))
            raise
        finally:
            attributes = metric_attributes(
                {
                    "http.request.method": method,
                    "http.route": route_template,
                    "http.response.status_code": status_code,
                }
            )
            request_count.add(1, attributes)
            request_duration.record(time.perf_counter() - started_at, attributes)


@app.get("/health", tags=["health"])
async def health() -> dict[str, str]:
    return {"status": "ok"}
