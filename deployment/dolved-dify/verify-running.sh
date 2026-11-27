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
dify_env=$dify_dir/.env
dify_project=${DIFY_PROJECT_NAME:-dolved-dify}
dolved_project=$(awk -F= '$1 == "COMPOSE_PROJECT_NAME" { sub(/^[^=]*=/, ""); print; exit }' "$dolved_env")
[ -n "$dolved_project" ] || dolved_project=dolved-pilot

for command in curl docker jq; do
  command -v "$command" >/dev/null 2>&1 || {
    echo "Required command is unavailable: $command" >&2
    exit 1
  }
done

"$delivery_dir/preflight-production.sh" "$dify_dir" "$delivery_dir" "$dolved_env"

dify_compose() {
  docker compose \
    --project-directory "$dify_dir" \
    --env-file "$dify_env" \
    -p "$dify_project" \
    -f "$dify_dir/docker-compose.yaml" \
    -f "$delivery_dir/compose.dify-security.yaml" "$@"
}

dolved_compose() {
  docker compose \
    --project-directory "$delivery_dir" \
    --env-file "$dolved_env" \
    -p "$dolved_project" \
    -f "$delivery_dir/compose.production.yaml" "$@"
}

check_completed_service() {
  project_kind=$1
  service=$2
  if [ "$project_kind" = dify ]; then
    container_id=$(dify_compose ps --all -q "$service")
  else
    container_id=$(dolved_compose ps --all -q "$service")
  fi
  [ -n "$container_id" ] || {
    echo "Completed service has no container: $project_kind/$service" >&2
    return 1
  }
  state=$(docker inspect --format '{{.State.Status}} {{.State.ExitCode}} {{.State.OOMKilled}}' "$container_id")
  [ "$state" = "exited 0 false" ] || {
    echo "Completed service is not a clean exit: $project_kind/$service ($state)" >&2
    return 1
  }
}

check_running_service() {
  project_kind=$1
  service=$2
  if [ "$project_kind" = dify ]; then
    container_id=$(dify_compose ps -q "$service")
  else
    container_id=$(dolved_compose ps -q "$service")
  fi
  [ -n "$container_id" ] || return 1
  state=$(docker inspect --format '{{.State.Status}} {{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}} {{.State.OOMKilled}}' "$container_id")
  case "$state" in
    'running healthy false'|'running none false') return 0 ;;
    *) return 1 ;;
  esac
}

report_running_service() {
  project_kind=$1
  service=$2
  if [ "$project_kind" = dify ]; then
    container_id=$(dify_compose ps --all -q "$service")
  else
    container_id=$(dolved_compose ps --all -q "$service")
  fi
  if [ -z "$container_id" ]; then
    echo "  $project_kind/$service: container unavailable" >&2
    return
  fi
  state=$(docker inspect --format '{{.State.Status}} {{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}} oom={{.State.OOMKilled}} exit={{.State.ExitCode}}' "$container_id")
  echo "  $project_kind/$service: $state" >&2
}

dify_runtime_services='db_postgres local_sandbox weaviate agent_ssrf_proxy plugin_daemon redis agent_backend api web nginx sandbox worker worker_beat ssrf_proxy api_websocket'
dolved_runtime_services='postgres objectstore api web worker caddy scheduler alertmanager otel-lgtm otel-collector'

attempt=1
while [ "$attempt" -le 60 ]; do
  all_ready=true
  for service in $dify_runtime_services; do
    check_running_service dify "$service" || all_ready=false
  done
  for service in $dolved_runtime_services; do
    check_running_service dolved "$service" || all_ready=false
  done
  [ "$all_ready" = true ] && break
  [ "$attempt" -lt 60 ] || {
    echo "Runtime services did not all become running/healthy without OOM kills." >&2
    for service in $dify_runtime_services; do
      check_running_service dify "$service" || report_running_service dify "$service"
    done
    for service in $dolved_runtime_services; do
      check_running_service dolved "$service" || report_running_service dolved "$service"
    done
    exit 1
  }
  sleep 5
  attempt=$((attempt + 1))
done

check_completed_service dify init_permissions
check_completed_service dolved migrate
check_completed_service dolved storage-init

tmp_dir=$(mktemp -d)
trap 'rm -rf "$tmp_dir"' EXIT HUP INT TERM
chmod 0700 "$tmp_dir"

docker network inspect --format '{{range $id, $container := .Containers}}{{$id}}{{"\n"}}{{end}}' \
  dolved-dify-backplane | while IFS= read -r container_id; do
    [ -n "$container_id" ] || continue
    docker inspect --format '{{index .Config.Labels "com.docker.compose.project"}}/{{index .Config.Labels "com.docker.compose.service"}}' \
      "$container_id"
  done | sort > "$tmp_dir/actual-backplane.txt"

{
  printf '%s\n' "$dify_project/nginx"
  printf '%s\n' "$dolved_project/api"
  printf '%s\n' "$dolved_project/worker"
} | sort > "$tmp_dir/expected-backplane.txt"

cmp -s "$tmp_dir/expected-backplane.txt" "$tmp_dir/actual-backplane.txt" || {
  echo "The live cross-stack backplane has unexpected membership:" >&2
  diff -u "$tmp_dir/expected-backplane.txt" "$tmp_dir/actual-backplane.txt" >&2 || true
  exit 1
}

nginx_id=$(dify_compose ps -q nginx)
docker inspect "$nginx_id" > "$tmp_dir/nginx-inspect.json"
jq -e '
  .[0].HostConfig.PortBindings as $ports |
  ($ports | keys | sort) == ["443/tcp", "80/tcp"] and
  ($ports["80/tcp"] == [{"HostIp":"127.0.0.1","HostPort":"8080"}]) and
  ($ports["443/tcp"] == [{"HostIp":"127.0.0.1","HostPort":"8443"}])
' "$tmp_dir/nginx-inspect.json" >/dev/null || {
  echo "The live Dify nginx port boundary is not loopback-only 8080/8443." >&2
  exit 1
}

plugin_id=$(dify_compose ps -q plugin_daemon)
docker inspect "$plugin_id" | jq -e '.[0].HostConfig.PortBindings == {} or .[0].HostConfig.PortBindings == null' >/dev/null || {
  echo "The live Dify plugin daemon unexpectedly publishes a host port." >&2
  exit 1
}

api_id=$(dolved_compose ps -q api)
docker exec "$api_id" php -r '
  $headers = @get_headers("http://dify-nginx/");
  if (!is_array($headers) || !preg_match("/^HTTP\\/\\S+\\s+[23][0-9]{2}\\b/", $headers[0])) { exit(1); }
' || {
  echo "The Dolved API cannot reach Dify nginx through the private backplane." >&2
  exit 1
}

domain_value() {
  awk -F= -v key="$1" '$1 == key { sub(/^[^=]*=/, ""); print; exit }' "$dolved_env"
}

dolved_domain=$(domain_value DOLVED_DOMAIN)
api_domain=$(domain_value API_DOMAIN)
storage_domain=$(domain_value STORAGE_DOMAIN)

curl --retry 5 --retry-delay 2 --retry-connrefused --fail --silent --show-error \
  --output /dev/null "https://$dolved_domain/"
curl --retry 5 --retry-delay 2 --retry-connrefused --fail --silent --show-error \
  --output /dev/null "https://$api_domain/up"
storage_status=$(curl --retry 5 --retry-delay 2 --retry-connrefused --silent --show-error \
  --output /dev/null --write-out '%{http_code}' "https://$storage_domain/")
case "$storage_status" in
  200|403) ;;
  *)
    echo "The HTTPS storage boundary returned unexpected status $storage_status." >&2
    exit 1
    ;;
esac

echo "Running production verification passed without retrieval or generation."
