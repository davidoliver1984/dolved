#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/../.."
docker compose --env-file .env.e2e -p dolved-e2e -f compose.yaml -f compose.e2e.yaml ps
docker compose --env-file .env.e2e -p dolved-e2e -f compose.yaml -f compose.e2e.yaml logs --tail "${TAIL:-100}" web api ai worker publisher postgres qdrant localstack
