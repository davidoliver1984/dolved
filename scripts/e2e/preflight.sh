#!/usr/bin/env bash
set -euo pipefail

repository_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$repository_root"

compose=(docker compose --env-file .env.e2e -p dolved-e2e -f compose.yaml -f compose.e2e.yaml)

fail() {
  printf 'E2E preflight failed: %s\n' "$1" >&2
  exit 1
}

[[ "$(awk -F= '$1 == "COMPOSE_PROJECT_NAME" { print $2 }' .env.e2e)" == "dolved-e2e" ]] || fail "project identity is not dolved-e2e"
[[ "$(awk -F= '$1 == "APP_ENV" { print $2 }' .env.e2e)" == "e2e" ]] || fail "APP_ENV is not e2e"
grep -q '^POSTGRES_DB=dolved_e2e$' .env.e2e || fail "database identity is ambiguous"
grep -q '^DOCUMENT_UPLOAD_BUCKET=dolved-e2e-' .env.e2e || fail "bucket identity is ambiguous"
grep -q '^INGESTION_QUEUE=dolved-e2e-' .env.e2e || fail "queue identity is ambiguous"

node_major="$(node --version | sed -E 's/^v([0-9]+).*/\1/')"
[[ "$node_major" == "24" ]] || fail "Node 24 is required (found $(node --version))"

if ! existing_stack="$("${compose[@]}" ps --all --quiet)"; then
  fail "Docker Compose cannot inspect the dolved-e2e project"
fi
if [[ -n "$existing_stack" ]]; then
  fail "a dolved-e2e stack already exists; inspect it or run make test-e2e-clean"
fi

while IFS='=' read -r name port; do
  [[ "$name" == *_PORT ]] || continue
  if lsof -nP -iTCP:"$port" -sTCP:LISTEN >/dev/null 2>&1; then
    fail "dedicated port $port ($name) is already occupied"
  fi
done < .env.e2e

rendered="$(${compose[@]} config --format json)"
forbidden='^/(workspace|evaluation|evaluation-runs|generation-evaluation)(/|$)'
if jq -e --arg pattern "$forbidden" '
  [.services[] | (.volumes // [])[] | .target | select(test($pattern))] | length > 0
' <<<"$rendered" >/dev/null; then
  fail "rendered Compose contains a forbidden repository/evaluation mount"
fi

if jq -e '
  [.services.ai, .services.worker]
  | map(.environment)
  | any(.[]; ((.RETRIEVAL_PLANNER_API_KEY // "") != "") or ((.VOYAGE_API_KEY // "") != "") or ((.GENERATION_OPENAI_API_KEY // "") != "") or ((.CONTEXTUALISER_API_KEY // "") != ""))
' <<<"$rendered" >/dev/null; then
  fail "provider credentials are present in the rendered E2E services"
fi

if ! jq -e '
  [.services.api, .services["conversation-worker"], .services.ai, .services.worker]
  | map(.environment)
  | all(.[];
      .CONTEXTUALISER_PROVIDER == "deterministic"
      and .GENERATION_PROVIDER == "deterministic")
' <<<"$rendered" >/dev/null; then
  fail "rendered E2E services do not share the deterministic chat profile"
fi

if ! jq -e '.services.api.environment.CONVERSATION_SSE_EVENT_LIMIT_PER_CONNECTION == "1"' \
  <<<"$rendered" >/dev/null; then
  fail "the E2E API does not enforce deterministic one-event SSE connections"
fi

if ! jq -e '.services.api.environment.CONVERSATION_SSE_EVENT_LIMIT_DISCONNECT_DELAY_MICROSECONDS == "500000"' \
  <<<"$rendered" >/dev/null; then
  fail "the E2E API does not preserve an observable progress state before reconnect"
fi

printf 'E2E preflight passed for isolated project dolved-e2e.\n'
