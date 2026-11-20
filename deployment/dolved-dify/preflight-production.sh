#!/bin/sh
set -eu

usage() {
  echo "Usage: $0 /srv/dify/docker /srv/dolved-pilot/deployment/dolved-dify /secure/path/dolved.env" >&2
  exit 1
}

[ "$#" -eq 3 ] || usage
dify_dir=$1
delivery_dir=$2
dolved_env=$3
dify_root=$(dirname "$dify_dir")
dify_env=$dify_dir/.env

expected_dify_commit=8e689e868b63b6dd2b449d0eb568ca5cc7127759
expected_compose_sha=c2a0ebc758e651e7ff298a8f121fc8a6ae8a64d5ae7a1c872048e6db05fa1dfe
expected_env_example_sha=ff2e6a7909506f487284dbc53025602b36dccb3f8204338ea852a29796cbd154

for command in docker git jq sha256sum stat; do
  command -v "$command" >/dev/null 2>&1 || {
    echo "Required command is unavailable: $command" >&2
    exit 1
  }
done

for file in \
  "$dify_dir/docker-compose.yaml" \
  "$dify_dir/.env.example" \
  "$dify_env" \
  "$delivery_dir/compose.dify-security.yaml" \
  "$delivery_dir/compose.production.yaml" \
  "$dolved_env"; do
  [ -f "$file" ] || {
    echo "Required deployment file is unavailable: $file" >&2
    exit 1
  }
done

check_secret_file() {
  file=$1
  [ -r "$file" ] || {
    echo "Secret environment file is not readable by the deployment account: $file" >&2
    exit 1
  }
  mode=$(stat -c '%a' "$file" 2>/dev/null || stat -f '%Lp' "$file")
  other=${mode#${mode%?}}
  [ "$other" = 0 ] || {
    echo "Secret environment file must not grant permissions to other users: $file (mode $mode)" >&2
    exit 1
  }
  placeholders=$(awk -F= '$2 == "SECURE_STORE_REQUIRED" { print $1 }' "$file")
  [ -z "$placeholders" ] || {
    echo "Unresolved secure-store values in $file:" >&2
    echo "$placeholders" >&2
    exit 1
  }
}

check_secret_file "$dify_env"
check_secret_file "$dolved_env"

actual_commit=$(git -C "$dify_root" rev-parse HEAD)
[ "$actual_commit" = "$expected_dify_commit" ] || {
  echo "Dify commit mismatch: expected $expected_dify_commit, found $actual_commit" >&2
  exit 1
}

actual_compose_sha=$(sha256sum "$dify_dir/docker-compose.yaml" | awk '{ print $1 }')
[ "$actual_compose_sha" = "$expected_compose_sha" ] || {
  echo "Dify Compose checksum mismatch." >&2
  exit 1
}

actual_env_sha=$(sha256sum "$dify_dir/.env.example" | awk '{ print $1 }')
[ "$actual_env_sha" = "$expected_env_example_sha" ] || {
  echo "Dify environment-example checksum mismatch." >&2
  exit 1
}

server_secret=$(awk -F= '$1 == "DIFY_AGENT_SERVER_SECRET_KEY" { sub(/^[^=]*=/, ""); print; exit }' "$dify_env")
case "$server_secret" in
  *[!A-Za-z0-9_-]*|'')
    echo "DIFY_AGENT_SERVER_SECRET_KEY is not unpadded base64url." >&2
    exit 1
    ;;
esac
[ "${#server_secret}" -eq 43 ] || {
  echo "DIFY_AGENT_SERVER_SECRET_KEY must encode exactly 32 bytes as unpadded base64url." >&2
  exit 1
}

for key in DOLVED_DOMAIN API_DOMAIN STORAGE_DOMAIN; do
  value=$(awk -F= -v key="$key" '$1 == key { sub(/^[^=]*=/, ""); print; exit }' "$dolved_env")
  case "$value" in
    ''|*example.com*|*' '*|*://*|*/*)
      echo "$key is missing or is not a production hostname." >&2
      exit 1
      ;;
  esac
done

backplane_internal=$(docker network inspect --format '{{.Internal}}' dolved-dify-backplane 2>/dev/null || true)
[ "$backplane_internal" = true ] || {
  echo "The dolved-dify-backplane network is missing or is not internal." >&2
  exit 1
}

tmp_dir=$(mktemp -d)
trap 'rm -rf "$tmp_dir"' EXIT HUP INT TERM
chmod 0700 "$tmp_dir"

docker compose \
  --project-directory "$dify_dir" \
  --env-file "$dify_env" \
  -p dolved-dify \
  -f "$dify_dir/docker-compose.yaml" \
  -f "$delivery_dir/compose.dify-security.yaml" \
  config --quiet

docker compose \
  --project-directory "$dify_dir" \
  --env-file "$dify_env" \
  -p dolved-dify \
  -f "$dify_dir/docker-compose.yaml" \
  -f "$delivery_dir/compose.dify-security.yaml" \
  config --format json > "$tmp_dir/dify.json"

jq -e '
  [.services | to_entries[] | .key as $service |
    (.value.ports // [])[] | {
      service: $service,
      host_ip: .host_ip,
      target: .target,
      published: .published,
      protocol: .protocol
    }] as $ports |
  ($ports | length) == 2 and
  ($ports | all(
    .service == "nginx" and .host_ip == "127.0.0.1" and .protocol == "tcp" and
    ((.target == 80 and .published == "8080") or
     (.target == 443 and .published == "8443"))
  )) and
  ((.services.plugin_daemon.ports // []) | length) == 0
' "$tmp_dir/dify.json" >/dev/null || {
  echo "Dify effective host-port boundary does not match the frozen private configuration." >&2
  exit 1
}

jq -e '
  [.services | to_entries[] |
    select(.value.logging.driver != "local" or
      .value.logging.options["max-size"] != "10m" or
      .value.logging.options["max-file"] != "5")] | length == 0
' "$tmp_dir/dify.json" >/dev/null || {
  echo "One or more Dify services lack the frozen bounded logging configuration." >&2
  exit 1
}

docker compose \
  --project-directory "$delivery_dir" \
  --env-file "$dolved_env" \
  -f "$delivery_dir/compose.production.yaml" \
  config --quiet

docker compose \
  --project-directory "$delivery_dir" \
  --env-file "$dolved_env" \
  -f "$delivery_dir/compose.production.yaml" \
  config --format json > "$tmp_dir/dolved.json"

jq -e '
  [.services | to_entries[] |
    select(.value.logging.driver != "local" or
      .value.logging.options["max-size"] != "10m" or
      .value.logging.options["max-file"] != "5")] | length == 0
' "$tmp_dir/dolved.json" >/dev/null || {
  echo "One or more Dolved services lack the frozen bounded logging configuration." >&2
  exit 1
}

jq -e '
  (.services.api.networks | has("dify_backplane")) and
  (.services.worker.networks | has("dify_backplane")) and
  (([.services | to_entries[] |
    select(.key != "api" and .key != "worker") |
    select(.value.networks | has("dify_backplane"))] | length) == 0)
' "$tmp_dir/dolved.json" >/dev/null || {
  echo "Dolved effective backplane membership does not match the frozen boundary." >&2
  exit 1
}

jq -e '
  (.services.api.environment.SESSION_COOKIE == "rag-platform-session") and
  (.services.web.environment.FRONTEND_URL == .services.api.environment.FRONTEND_URL)
' "$tmp_dir/dolved.json" >/dev/null || {
  echo "Dolved web/API session handoff configuration is inconsistent." >&2
  exit 1
}

echo "Production preflight passed without starting services or contacting a model provider."
