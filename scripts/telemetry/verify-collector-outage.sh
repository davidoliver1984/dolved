#!/usr/bin/env bash

set -euo pipefail

: "${API_URL:=http://localhost:8000}"

cookie_file="$(mktemp /tmp/rag-telemetry-outage-cookie.XXXXXX)"
collector_stopped=false

cleanup() {
    rm -f "$cookie_file"

    if [[ "$collector_stopped" == true ]]; then
        docker compose up \
            --detach \
            --wait \
            --wait-timeout 60 \
            otel-collector >/dev/null
    fi
}

trap cleanup EXIT

curl --fail --silent --show-error \
    --cookie-jar "$cookie_file" \
    "${API_URL}/sanctum/csrf-cookie" \
    --output /dev/null

xsrf_token="$({
    awk '$6 == "XSRF-TOKEN" {print $7}' "$cookie_file" || true
} | tail -n 1 | sed 's/%3D/=/g')"

curl --fail --silent --show-error \
    --cookie "$cookie_file" \
    --cookie-jar "$cookie_file" \
    --header "X-XSRF-TOKEN: ${xsrf_token}" \
    --header 'Accept: application/json' \
    --header 'Content-Type: application/json' \
    --header 'Origin: http://localhost:3000' \
    --data '{"email":"workspace.tester@example.test","password":"password"}' \
    "${API_URL}/api/auth/login" \
    --output /dev/null

docker compose stop --timeout 15 otel-collector >/dev/null
collector_stopped=true

status_code="$(
    curl --silent --show-error \
        --cookie "$cookie_file" \
        --header 'Accept: application/json' \
        --header 'Origin: http://localhost:3000' \
        --output /dev/null \
        --write-out '%{http_code}' \
        "${API_URL}/api/platform/status"
)"

if [[ "$status_code" != "200" ]]; then
    printf 'Laravel request failed during Collector outage: HTTP %s\n' \
        "$status_code" >&2
    exit 1
fi

printf 'Collector outage isolation verified: user-facing request returned HTTP 200.\n'
