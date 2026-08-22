#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/../.."
project="$({ docker compose --env-file .env.e2e -p dolved-e2e -f compose.yaml -f compose.e2e.yaml config --format json; } | jq -r '.name')"
[[ "$project" == "dolved-e2e" ]] || { printf 'Refusing cleanup for project %s\n' "$project" >&2; exit 1; }
docker compose --env-file .env.e2e -p dolved-e2e -f compose.yaml -f compose.e2e.yaml down --volumes --remove-orphans
