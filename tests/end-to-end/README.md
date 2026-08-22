# End-to-End Tests

This directory contains end-to-end tests that verify behaviour across the
entire platform.

These tests exercise complete user journeys rather than individual
applications.

Stage 12.5 introduced the first live cross-service acceptance workflow. It is
kept in `scripts/telemetry/verify-cross-service.sh` because it verifies the
running Compose telemetry topology as well as application behaviour. Run it
through `make telemetry-verify`.

Stage 22.3 adds the first Playwright product journey. Run it with
`make test-e2e`. The command creates a clean `dolved-e2e` Compose project,
checks that only its dedicated ports and mounts are present, and selects a
complete deterministic retrieval profile with no OpenAI or Voyage credentials.

The ingestion scenario authenticates through the real browser login, uploads a
representative text document and a corrupt PDF through the product UI, then
observes real outbox publication, SQS consumption, parsing, chunking, Qdrant
indexing, signed completion, retrieval and cross-workspace concealment. Its
fixtures are synthetic regression evidence; they are not a retrieval-quality
benchmark.

Successful runs remove the isolated stack and volumes. Failed runs preserve
them for `make test-e2e-inspect`; remove only those resources with
`make test-e2e-clean` after inspection.
