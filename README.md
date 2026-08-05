# dolved RAG system

A container-first, multi-service Retrieval Augmented Generation platform built with
Next.js, Laravel, FastAPI and PostgreSQL.

## Getting started

Docker with Docker Compose and Make are the only host tools required for the standard
development workflow.

```bash
make bootstrap
```

This creates `.env` from `.env.example` when needed, builds and starts the platform,
waits for every service to become healthy and runs the Laravel migrations.

Run `make help` to see the complete developer interface. Common commands include:

```bash
make up
make down
make logs
make lint
make format-check
make typecheck
make test
make aws-status
make qdrant-status
```

`make reset` intentionally deletes all local Compose volumes, including PostgreSQL,
Qdrant and telemetry data, and therefore requires typing `RESET` interactively.

## Local services

| Service | URL |
|---|---|
| Web | http://localhost:3000 |
| API | http://localhost:8000 |
| Python API | http://localhost:8001 |
| Ingestion publisher | Background `publisher` process |
| Ingestion worker | Background `worker` process |
| PostgreSQL | `127.0.0.1:5433` |
| LocalStack gateway | http://localhost:4566 |
| Qdrant REST API and dashboard | http://localhost:6333/dashboard |
| Mailpit | http://localhost:8025 |
| Grafana telemetry UI | http://localhost:3001 |
| OpenTelemetry Collector (OTLP/gRPC) | `127.0.0.1:4317` |
| OpenTelemetry Collector (OTLP/HTTP) | http://localhost:4318 |
| OpenTelemetry Collector health | http://localhost:13133 |

LocalStack provides local S3 and SQS services. Mailpit captures local verification
and password-reset messages without sending external email. Qdrant stores its local,
derived vector projection in the `qdrant_data` volume; `make down` preserves it and
the deliberately destructive `make reset` removes it. Redis is added in a later
phase.

Qdrant's REST API is bound only to the host loopback interface for local inspection;
its gRPC and cluster ports are not published. The Python services connect internally
through Compose DNS at `http://qdrant:6333`. Verify that route with:

```bash
make qdrant-status
```

The ingestion publisher continuously delivers durable Laravel outbox events to
LocalStack SQS. Inspect it with `docker compose logs publisher`, or run one
publication batch explicitly with `make publish-ingestion`.

The separate Python ingestion worker validates those events and requests
Laravel's authoritative `QUEUED` to `PROCESSING` claim. Inspect it with
`docker compose logs worker`. To run one receive batch manually, first stop the
background worker and then run `make consume-ingestion`.

## Local telemetry

OpenTelemetry is the instrumentation and transport standard; it does not
store telemetry. Once instrumented in the following stages, application
services will send OTLP traces and metrics only to the repository's
dedicated `otel-collector` service. The Collector owns batching and routing,
then exports locally to the replaceable `otel-lgtm` development backend:

```text
Laravel / Python
  -> OpenTelemetry SDK
  -> otel-collector
  -> otel-lgtm
  -> Grafana
```

`otel-lgtm` packages local trace and metric storage with Grafana for
development inspection. It is not the production backend and application
code must not depend on its SDKs, APIs, credentials or service address.
Changing the backend therefore remains a Collector-configuration change.

Open Grafana at http://localhost:3001. It is anonymously accessible on the
loopback interface for local development only. Run the infrastructure smoke
test with:

```bash
make telemetry-smoke
```

The smoke test submits one synthetic trace and metric to the application-facing
Collector and confirms both can be queried through Grafana's provisioned data
sources. It does not instrument Laravel or Python.
