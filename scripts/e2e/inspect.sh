#!/usr/bin/env bash
set -euo pipefail
repository_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
compose=(docker compose --project-directory "$repository_root" --env-file "$repository_root/tests/end-to-end/runtime/.env.e2e" -p dolved-e2e -f "$repository_root/compose.yaml" -f "$repository_root/tests/end-to-end/runtime/compose.e2e.yaml")
"${compose[@]}" ps
"${compose[@]}" logs --tail "${TAIL:-100}" web api ai worker publisher postgres qdrant localstack
