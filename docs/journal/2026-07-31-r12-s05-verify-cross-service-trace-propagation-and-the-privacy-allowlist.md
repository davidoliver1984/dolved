# Session Journal: R12-S05 — Verify Cross-Service Trace Propagation and the Privacy Allowlist

## Date

2026-07-31

## Session mode

Cross-service verification and a narrow implementation correction in teaching
mode. ADR-0012 remained authoritative; no new architecture decision or ADR was
required.

## What happened

The complete ingestion request path was exercised across Laravel, the
transactional outbox, LocalStack SQS, the Python worker and the signed claim
request back to Laravel.

The review found that the request and publisher were not yet one trace. The
publisher correctly injected its context into SQS, and Python correctly
continued it, but the initiating request context was lost while the event
waited in the transactional outbox. Nullable W3C context fields were therefore
added to the durable outbox record. Laravel captures them with the event and
the asynchronous publisher resumes them before creating its producer span.

## Important implementation decisions

* Only the standard W3C `traceparent` and optional `tracestate` carrier values
  cross the asynchronous persistence boundary.
* Trace context is immutable with the logical outbox event, but nullable so
  old records and requests made while telemetry is disabled remain valid.
* Failure to inject telemetry context never rolls back the ingestion request.
* The canonical event contract was not changed. Its durable
  `correlation_id` remains distinct from the OpenTelemetry trace ID.
* The verification uses the real authenticated upload and ingestion APIs,
  publisher, LocalStack queue, Python worker and internal claim endpoint.
* Synthetic database, S3 and local temporary artifacts are cleaned up even
  when the verification exits early.

## Tests and runtime verification

`TelemetryTest` now proves request-context persistence and publisher-parent
restoration in addition to the existing privacy and cardinality assertions.
The focused suite passed with 8 tests and 76 assertions.

`make telemetry-verify` proved one real trace across the Laravel API,
publisher and Python worker. The Document reached `PROCESSING`, the required
publication/process/claim spans were present, a unique private-content marker
was absent, and entity IDs were absent from metric labels.

`make telemetry-outage` stopped only the Collector and observed HTTP 200 from
the authenticated platform-status endpoint. Its exit trap restored the
Collector, which returned healthy.

Repository-wide verification passed: web lint, TypeScript and 10 tests;
Laravel Pint and 127 tests (568 assertions); Python Ruff, mypy and 130 tests;
LocalStack checks; telemetry smoke; cross-service verification; and outage
isolation. All migrations also ran from empty in a disposable PostgreSQL
database, which was removed afterward.

## Problems or corrections

The first live script attempt reused a CSRF token after Laravel rotated the
session during login. The script now refreshes the CSRF cookie before every
unsafe request, matching the browser client. The host's older Bash also treats
an expanded empty array as unbound under `set -u`; the upload argument array
now always contains its HTTP method and remains portable.

## Next steps / important takeaways

* Phase 12's accepted ingestion observability boundary is now proven rather
  than inferred from separate service tests.
* A transactional outbox is also a trace-context persistence boundary; SQS
  propagation alone cannot connect the initiating request across delayed
  publication.
* Phase 13 begins with the architecture session for the provider-neutral
  embedding boundary. External provider telemetry remains future work until
  those calls actually exist.
