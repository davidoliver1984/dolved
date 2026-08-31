#!/usr/bin/env bash
set -euo pipefail

repository_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$repository_root"

fail() {
  printf 'PostgreSQL role-topology test failed: %s\n' "$1" >&2
  exit 1
}

verify_rendered() {
  local label="$1"
  local rendered="$2"

  for service in api publisher conversation-worker; do
    jq -e --arg service "$service" '
      .services[$service].environment.DB_USERNAME == "rag_platform_app"
      and (.services[$service].environment.DB_PASSWORD | length > 0)
      and .services[$service].environment.DB_PASSWORD
          == .services["postgres-role-bootstrap"].environment.RAG_PLATFORM_APP_PASSWORD
    ' <<<"$rendered" >/dev/null || fail "$label $service is not bound to the runtime role"
  done

  jq -e '
    .services.migrator.environment.DB_USERNAME == "rag_platform_migrator"
    and .services.migrator.environment.PGOPTIONS == "-c role=rag_platform_owner"
    and (.services.migrator.environment.DB_PASSWORD | length > 0)
    and .services["postgres-role-bootstrap"].environment.RAG_PLATFORM_MIGRATOR_PASSWORD
        == .services.migrator.environment.DB_PASSWORD
  ' <<<"$rendered" >/dev/null || fail "$label one-shot migrator boundary is incomplete"

  for service in api publisher conversation-worker web ai worker; do
    if jq -e --arg service "$service" '
      (.services[$service].environment // {}) as $environment
      | ($environment | has("RAG_PLATFORM_MIGRATOR_PASSWORD"))
        or ($environment.DB_USERNAME? == "rag_platform_migrator")
        or ($environment.PGOPTIONS? == "-c role=rag_platform_owner")
    ' <<<"$rendered" >/dev/null; then
      fail "$label $service exposes migrator authority"
    fi
  done
}

verify_rendered local "$(docker compose --profile tools config --format json)"
verify_rendered e2e "$(
  docker compose --env-file .env.e2e --file compose.yaml --file compose.e2e.yaml \
    --profile tools config --format json
)"

printf 'PostgreSQL role-topology test passed.\n'
