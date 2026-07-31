# Session Journal: R12-S04 — Instrument the Python AI Service with OpenTelemetry

## Date

2026-07-31

## Session mode

Python implementation in teaching mode. ADR-0012 remained authoritative;
no architecture redesign or new ADR was required.

## What happened

The FastAPI AI service and ingestion worker were instrumented with the
official OpenTelemetry Python SDK and OTLP/HTTP exporter 1.44.0. Both process
types emit traces and cumulative metrics only through the dedicated
Collector established in Stage 12.2.

FastAPI now emits safe route-template server telemetry. The worker emits
standard receive and process telemetry, extracts the W3C context carried in
SQS attributes, and propagates its current context into the signed Laravel
claim request. The shared JSON event contract was not changed.

## Important implementation decisions

* Direct OpenTelemetry APIs are used. The small telemetry module configures
  official providers and privacy policy; it is not a parallel telemetry
  facade.
* Resource metadata is explicit and limited to service name and deployment
  environment, matching the Laravel privacy correction from Stage 12.3.
* Trace attributes may contain safe entity, event and correlation IDs;
  metric attributes cannot contain any per-entity or transport identifier.
* SQS uses the official messaging operation names, kinds and duration metric
  conventions. The one-message worker uses the message creation context as
  the process span parent; the claim client span is its child.
* Automatic exception events are disabled. Only an allowlisted exception
  type can be recorded, preventing exception messages from leaking URLs,
  payload fragments or credentials.
* SDK setup and shutdown are best effort. OTLP uses the shared 250 ms timeout
  and cumulative metric temporality.

## Tests added

Seven focused telemetry tests cover:

* FastAPI W3C extraction and route-template naming;
* SQS parent extraction into the worker consumer span;
* W3C injection into the Laravel claim request;
* exclusion of synthetic private content from spans and metrics;
* exclusion of entity IDs from metric labels;
* no-op fallback and guarded shutdown failures.
* safe recording of unexpected failure types without exception messages.

Existing SQS and worker-entrypoint tests were extended to cover transport
attributes and lifecycle shutdown.

## Runtime verification

Tempo returned real `rag-platform-ai` `GET /health` traces and
`rag-platform-ingestion-worker` receive traces. Prometheus returned the
cumulative SQS receive-duration metric with bounded service, environment,
queue, operation and messaging-system labels.

A separate bounded worker poll exported to an intentionally unreachable
endpoint. The SDK logged trace and metric export failures, while the worker
still completed successfully.

## Verification performed

* Ruff format and lint checks passed;
* mypy passed across 51 source and test files;
* all 130 Python tests passed;
* Compose configuration validated;
* AI and worker images rebuilt and services started successfully;
* AI health returned `{"status":"ok"}`;
* stored Tempo traces and Prometheus metrics inspected through Grafana;
* unavailable-exporter behaviour tested with a real worker process.
* repository-wide verification passed: web 10 tests, Laravel 125 tests (561
  assertions), Python 130 tests, formatting, linting, type checking,
  LocalStack checks and the Collector-to-Grafana telemetry smoke test.

## Problems or corrections

The host `uv` runtime selected Python 3.14.0rc2, which is incompatible with
the current locked Pydantic build. Repository-authoritative tests therefore
ran in the Python 3.14.6 container; the host remained suitable for Ruff and
mypy.

The initial receive-duration metric was renamed to the official
`messaging.client.operation.duration` convention, and
`messaging.operation.name` was added. The process path uses the official
`messaging.process.duration` metric.

## Next steps / important takeaways

* Stage 12.5 will exercise one real Laravel-to-SQS-to-Python trace and prove
  the complete privacy allowlist across service boundaries.
* OpenTelemetry trace IDs remain observability identifiers. The durable
  contract correlation ID stays separate and is attached only as a safe
  trace attribute.
* The backend remains replaceable because Python knows only the Collector's
  OTLP endpoint.
