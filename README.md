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
```

`make reset` intentionally deletes all local Compose volumes, including PostgreSQL
data, and therefore requires typing `RESET` interactively.

## Local services

| Service | URL |
|---|---|
| Web | http://localhost:3000 |
| API | http://localhost:8000 |
| Python API | http://localhost:8001 |
| Ingestion publisher | Background `publisher` process |
| PostgreSQL | `127.0.0.1:5433` |
| LocalStack gateway | http://localhost:4566 |
| Mailpit | http://localhost:8025 |

LocalStack provides local S3 and SQS services. Mailpit captures local verification
and password-reset messages without sending external email. Qdrant and Redis are
added in later phases.

The ingestion publisher continuously delivers durable Laravel outbox events to
LocalStack SQS. Inspect it with `docker compose logs publisher`, or run one
publication batch explicitly with `make publish-ingestion`.
