# Session Journal: R14-S02 — Add Qdrant Development Service

## Date

2026-08-05

## Session mode

Implementation. This session added and verified local vector-store
infrastructure only. It did not implement collections, vector persistence,
the `VectorStore` boundary or ingestion-pipeline integration.

## What happened

The accepted ADR 0014 supplied the architecture before implementation began:
Qdrant is a disposable derived projection, PostgreSQL remains authoritative,
and application code must approach Qdrant through the Python-owned boundary
introduced in the following stage.

Qdrant was added to Docker Compose as the stable `qdrant` service using the
explicitly pinned `qdrant/qdrant:v1.18.1` image. Its storage directory is
backed by the `qdrant_data` named volume. The AI API and ingestion worker both
receive `QDRANT_URL=http://qdrant:6333` and wait for the service health check,
which establishes the internal route without prematurely adding a client.

The HTTP API and dashboard are available from the Mac only through
`127.0.0.1:6333`. The gRPC and cluster ports remain internal. This matters
because the local Qdrant service has no authentication; loopback publication
provides the required developer access without exposing it unnecessarily.

The repository gained `make qdrant-status`, which calls `/readyz` from the AI
container and therefore tests the same Compose-DNS route the application will
use. README and environment-example changes record the access URL, service
URL, persistence semantics and destructive-reset consequence.

## Important implementation decisions

* Qdrant is pinned to `v1.18.1`; `latest` is not used.
* Only the REST/dashboard port is host-published, and only on loopback.
* The application-facing address is `http://qdrant:6333`, never localhost.
* The Compose health check uses Qdrant's local TCP listener because the image
  does not include `curl` or `wget`; the repository command verifies semantic
  readiness separately through `/readyz`.
* `make down` preserves vector data, while the deliberately destructive and
  confirmed `make reset` removes it with the other local volumes.
* No new ADR was required because these choices implement ADR 0014 and the
  bounded Stage 14.2 requirements rather than changing architecture.

## Verification performed

```bash
docker compose config --quiet
docker compose up --detach qdrant --wait --wait-timeout 120
docker compose up --detach ai --wait --wait-timeout 120
make qdrant-status
docker compose up --detach qdrant --force-recreate --no-deps \
  --wait --wait-timeout 120
make up WAIT_TIMEOUT=180
docker compose ps qdrant ai worker
make format-check lint typecheck test
git diff --check
```

Qdrant became healthy and identified itself as version 1.18.1.
`make qdrant-status` returned `all shards are ready` through Compose DNS. A
temporary collection was created, survived forced container recreation,
remained green and was then deleted. The final collection list was empty.

The repository-wide quality gate passed: 26 frontend tests, 127 Laravel tests
with 568 assertions, and 168 Python tests. Frontend lint and TypeScript,
Laravel Pint, Python Ruff and MyPy all passed. The one opt-in live embedding
test was skipped because no external provider credentials were supplied.

## Problems and corrections

The first repository-wide gate attempt could not access the Docker socket from
the restricted command sandbox. It made no repository change. Re-running the
same gate with the required Docker permission completed successfully.

The Qdrant image does not ship a command-line HTTP client, so an HTTP health
check inside that container would have required modifying the vendor image.
The final design uses its Bash TCP capability for Compose health and keeps the
stronger `/readyz` check in the repeatable repository command.

## Next steps / important takeaways

R14-S03 can now implement collection provisioning, vector validation,
deterministic point identities, tenant-aware payloads and the provider-neutral
`VectorStore` boundary against a real local Qdrant service. It must continue to
treat Qdrant as a rebuildable projection and must not move authoritative text,
lineage or generation state out of PostgreSQL.

## Commit boundary

```text
Add Qdrant development service
```

Annotated tag: `phase-14-s02`.
