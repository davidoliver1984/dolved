# Legacy RAG recovery record

Last verified: 19 November 2026. This is a preservation inventory, not proof that a full restoration has been exercised.

## Frozen implementation identity

- Source checkout: `/Users/davidoliver/Desktop/RAG-current-policy-pilot`
- Commit: `9e43561cedcf53174d2367fb9c71bfd55a4bab8a` (`Record automatic observer cleanup verification and preparation`)
- Compose project: `dolved-r28-s03`
- Compose inputs recorded on the running containers: `/Users/davidoliver/Desktop/RAG-current-policy-pilot/compose.yaml`, `/Users/davidoliver/Desktop/RAG-current-policy-pilot/compose.e2e.yaml`, `/Users/davidoliver/Desktop/RAG-current-policy-pilot/compose.r28-s03.yaml`, `/Users/davidoliver/.dolved/r28-s03/production.override.json`, and `/Users/davidoliver/Desktop/dolved-experiments/current-policy-pilot-20261116/sealed-confirmation-02/preparation/smoke.override.json`.
- Private environment file: `/Users/davidoliver/Desktop/RAG/.env.r28-s03`. It remains outside Git and must remain access-restricted. Its values are intentionally not reproduced here.

The checkout had only Python cache directories untracked when inspected. Existing repository evaluation evidence under `docs/evaluation/`, contracts, fixtures and authoritative manifests remain preserved. The new Dify delivery branch does not delete or rewrite them.

## Runtime and dependency identities

| Component | Preserved identity |
|---|---|
| Dolved API / conversation worker / publisher | image `sha256:2831b7678833d675161ab61fea4f3ba1440b62c5f3a7d9c743ca06c84b9dcaa5`; Dockerfile bases `composer:2.10.2`, `php:8.4-cli-alpine` |
| Legacy AI / ingestion worker | image `sha256:3da21b4478c1f0d357f9941d01b8b65e3c008847742b9a57ac87eef18d246468`; Dockerfile base `ghcr.io/astral-sh/uv:0.11.31-python3.14-trixie-slim` |
| Web | image `sha256:5bc29dea39ef8eb9e0f9bb30d9ab409aac05cac2abb1ec3aad80e5ddc998291b`; Dockerfile base `node:24-alpine` |
| PostgreSQL | `postgres:18.4-alpine` |
| Qdrant | `qdrant/qdrant:v1.18.1` |
| LocalStack | `localstack/localstack:4.14.0` |
| Mailpit | `axllent/mailpit:v1.30.0` |
| Observability | `grafana/otel-lgtm:0.29.2`, `prom/alertmanager:v0.33.1`, OpenTelemetry collector `0.153.0` |

The detailed Python and PHP dependency locks remain in `apps/ai/uv.lock` and `apps/api/composer.lock` at the frozen commit. Retrieval/generation configuration references remain in `compose.yaml`, `.env.example`, `apps/ai/.env.example`, `apps/api/.env.example`, `apps/api/config/`, and `apps/ai/app/settings.py`. Secret values must be restored from approved secure storage, not from this document or Git.

## Stateful assets that must not be deleted

- PostgreSQL volume `dolved-r28-s03_postgres_data`, mounted at `/var/lib/postgresql`.
- Qdrant volume `dolved-r28-s03_qdrant_data`, mounted at `/qdrant/storage`.
- Runtime dependency/cache volumes `dolved-r28-s03_api_vendor`, `dolved-r28-s03_ai_venv`, `dolved-r28-s03_fastembed_cache`, `dolved-r28-s03_web_node_modules`, and `dolved-r28-s03_web_next`.
- The frozen corpus archive `tests/evaluation/corpus/dolved-care-v4/v1/checkpoint-19-application-evidence-corrections.tar.gz` at the frozen commit. Its recorded SHA-256 is `6fa6602935efe8379cc2a7de4ba85af17aa8d8827082ae6de8df959f6e19a06e`.
- The internal Dify-stage evidence and exact-byte manifests under `/Users/davidoliver/Desktop/dolved-experiments/dify-cloud-trial-01/` remain outside Git and must remain access-restricted.

No container, image, volume, database or file named above is to be removed as part of the Dify cutover. No additional paid storage is currently required: these assets already exist locally. Off-host durability has not yet been demonstrated and must be costed before purchase if later required.

At the last inspection, `api`, `web`, `ai`, PostgreSQL and Qdrant were running and healthy; mutation-capable workers, publisher, scheduler-equivalent one-offs and LocalStack were not running. Preservation work did not start, stop or delete any legacy container.

## Current-revision custom-pipeline configuration

The current repository keeps the E2E and R28 runtime inputs under their owning
test directories. Validate the current effective model from any working
directory with repository-root-derived paths and the established project name:

```sh
repository_root="$(git rev-parse --show-toplevel)"
docker compose --project-directory "$repository_root" \
  --project-name dolved-r28-s03 \
  --env-file "$repository_root/tests/evaluation/runtime/.env.r28-s03" \
  -f "$repository_root/compose.yaml" \
  -f "$repository_root/tests/end-to-end/runtime/compose.e2e.yaml" \
  -f "$repository_root/tests/evaluation/runtime/compose.r28-s03.yaml" \
  -f /Users/davidoliver/.dolved/r28-s03/production.override.json \
  -f /Users/davidoliver/Desktop/dolved-experiments/current-policy-pilot-20261116/sealed-confirmation-02/preparation/smoke.override.json \
  config --quiet
```

Set `R28_CORPUS_ROOT` to the approved extracted corpus before rendering or
starting this model. Starting services remains a separate, explicitly
authorised recovery action.

Historical evidence must be reproduced from its recorded revision rather than
by translating old literal commands to the current layout. For a general
pre-relocation snapshot, tag `pilot-unified-design-20261125` resolves to
`b201f34b844a3359b97c422bfc498e0d8cb12437` and retains all 13 former root paths.
Run-specific evidence takes precedence and should be checked out in a detached
worktree at the exact commit it records. Frozen manifests, journals and command
captures are intentionally unchanged.

## Frozen-revision isolated restart outline

Do not start this legacy stack alongside production Dify using shared ports, credentials or project names. From the frozen checkout, with the retained private environment and override files available, validate the effective Compose model first:

```sh
docker compose --project-name dolved-r28-s03 \
  --env-file /Users/davidoliver/Desktop/RAG/.env.r28-s03 \
  -f compose.yaml -f compose.e2e.yaml -f compose.r28-s03.yaml \
  -f /Users/davidoliver/.dolved/r28-s03/production.override.json \
  -f /Users/davidoliver/Desktop/dolved-experiments/current-policy-pilot-20261116/sealed-confirmation-02/preparation/smoke.override.json \
  config --quiet
```

Then start only the stateful dependencies and read paths needed for a recovery inspection. Keep publishers, ingestion workers, schedulers and conversation workers stopped until an explicit restoration exercise authorises mutation:

```sh
docker compose --project-name dolved-r28-s03 \
  --env-file /Users/davidoliver/Desktop/RAG/.env.r28-s03 \
  -f compose.yaml -f compose.e2e.yaml -f compose.r28-s03.yaml \
  -f /Users/davidoliver/.dolved/r28-s03/production.override.json \
  -f /Users/davidoliver/Desktop/dolved-experiments/current-policy-pilot-20261116/sealed-confirmation-02/preparation/smoke.override.json \
  up -d postgres qdrant api web ai
```

Before any recovery use, confirm the named volumes above are attached and the runtime image digests match this record. Provider and HMAC secrets must come from approved secure storage.

## Known gaps and limitations

- The legacy LocalStack service used `tmpfs` at `/var/lib/localstack`; it has no persistent data volume. Its former S3/SQS state is not recoverable from the stopped container.
- Eleven of the twelve inventoried evaluation originals were independently located byte-for-byte in the frozen archive. `v4.injection.workspace-enumeration-bait.md` remains unavailable as original bytes; only extracted chunk text was retained. It must not be reconstructed or represented as an original.
- Current container health demonstrates that PostgreSQL and Qdrant data are presently mountable, but no backup restore or end-to-end legacy recovery exercise has been performed.
- Source, images and volumes are all required. Source code alone is insufficient to claim recoverability.

The legacy system may remain stopped and is excluded from the new production deployment. Restart or restoration is a separate, explicitly controlled operation.
