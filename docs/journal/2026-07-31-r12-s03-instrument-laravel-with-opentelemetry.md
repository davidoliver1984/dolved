# Session Journal: R12-S03 — Instrument Laravel with OpenTelemetry

## Date

2026-07-31

## Session mode

Laravel implementation in teaching mode. ADR-0012 remained authoritative;
no architecture redesign or new ADR was required.

## What happened

The Laravel API and ingestion publisher were instrumented with the official
OpenTelemetry PHP SDK and OTLP exporter. The API now emits safe HTTP and
database spans and metrics. Claimed outbox publications emit producer spans
and outcome metrics, and the SQS transport carries the current W3C trace
context in message attributes without changing the shared event body.

The API and publisher have separate service identities but communicate only
with the dedicated Collector established in Stage 12.2. No Grafana-specific
package, endpoint or application configuration was introduced.

## Important implementation decisions

* The implementation depends directly on official OpenTelemetry API and SDK
  interfaces. The small application classes configure the SDK and enforce
  ADR-0012's privacy boundary; they do not form a proprietary telemetry
  facade.
* HTTP names and attributes use Laravel route templates after route
  resolution. Raw URLs, query strings and bodies are never exported.
* Database telemetry records only the driver and SQL operation verb. SQL,
  bindings, table names and returned data are excluded.
* Trace attributes may include workspace, document, event and correlation
  identifiers. Metric attributes cannot, which prevents unbounded
  per-entity series.
* Resource attributes are explicitly limited to service name and deployment
  environment. Default PHP process detection is intentionally not merged
  because the local server can expose request query values as process
  command arguments.
* Metrics use cumulative temporality. The Collector received default delta
  points, but the local Prometheus-compatible backend did not retain them;
  cumulative points were immediately queryable.
* Export uses a 250 ms timeout with no SDK retries. Collector-side queuing
  and retries remain infrastructure concerns. Request telemetry is flushed
  after the response, and all lifecycle failures are guarded.
* The PHP image pins protobuf 5.35.1, matching the Composer protobuf
  dependency used by the OTLP exporter.

## Tests added

`TelemetryTest` covers:

* W3C parent-context extraction and route-template HTTP spans;
* HTTP request count and duration metrics;
* database operation spans without SQL or binding leakage;
* outbox producer spans and low-cardinality outcome metrics;
* SQS `traceparent` message-attribute injection;
* trace, metric and resource privacy allowlists;
* graceful flush and shutdown failures.

Normal PHPUnit execution sets `OTEL_SDK_DISABLED=true`. The focused tests
enable only manually constructed in-memory SDK providers, so the suite
cannot depend on or send to local infrastructure.

## Runtime verification

A request carrying a known `traceparent` produced a stored
`GET /api/auth/user` span with the same trace and parent IDs. Tempo returned
the span through Grafana. Its resource contained only:

* `service.name=rag-platform-api`;
* `deployment.environment.name=local`.

The synthetic secret placed in the query string was absent. Prometheus
listed the Laravel HTTP count/duration and database duration series after
cumulative export.

The Collector was then stopped briefly. The API still returned its expected
401 response in 0.036 seconds. The Collector was restarted and verified
healthy immediately afterwards.

## Verification performed

* focused Laravel telemetry suite: 7 tests, 70 assertions;
* rebuilt Laravel image and confirmed protobuf 5.35.1;
* inspected a real stored Tempo trace and Prometheus metric names;
* simulated and recovered from a Collector outage;
* web: ESLint, TypeScript and 10 tests;
* Laravel: Pint and 125 tests (561 assertions);
* Python: Ruff formatting/linting, mypy and 123 tests;
* LocalStack bucket, queue, DLQ and redrive verification;
* shared Collector/Grafana telemetry smoke verification;
* Compose configuration, Composer metadata and whitespace validation.

## Problems or corrections

Three issues were found through real backend inspection rather than only
unit tests:

1. the SDK's default PHP resource detector could expose the development
   server's command arguments, so resource attributes became explicit;
2. default delta metrics reached the Collector but were not retained by the
   local backend, so cumulative temporality became explicit;
3. global middleware starts before route resolution, so route naming now
   finalises after downstream dispatch.

A Collector debug exporter was enabled temporarily to diagnose metric flow
and removed before the final configuration was verified.

## Next steps / important takeaways

* Stage 12.4 instruments Python against the same Collector boundary and
  equivalent privacy/cardinality rules.
* Stage 12.5 remains responsible for proving one complete Laravel-to-SQS-to-
  Python trace. This stage injects the W3C SQS attributes but does not claim
  end-to-end Python extraction yet.
* A trace can safely carry bounded diagnostic identity that would be
  dangerous as a metric label.
* Privacy applies to resource metadata as well as span and metric
  attributes.
