#!/usr/bin/env bash

set -euo pipefail

: "${API_URL:=http://localhost:8000}"
: "${GRAFANA_URL:=http://localhost:3001}"
: "${DOCUMENT_UPLOAD_BUCKET:=rag-platform-document-uploads-local}"

for command in curl docker jq od; do
    command -v "$command" >/dev/null || {
        printf 'Required command is unavailable: %s\n' "$command" >&2
        exit 1
    }
done

cookie_file="$(mktemp /tmp/rag-telemetry-cookie.XXXXXX)"
payload_file="$(mktemp /tmp/rag-telemetry-payload.XXXXXX)"
response_file="$(mktemp /tmp/rag-telemetry-response.XXXXXX)"
document_id=""
workspace_id=""
storage_key=""

cleanup() {
    rm -f "$cookie_file" "$payload_file" "$response_file"

    if [[ -n "$document_id" ]]; then
        docker compose exec -T postgres psql \
            --username rag_platform \
            --dbname rag_platform \
            --set ON_ERROR_STOP=1 \
            --command "
                DELETE FROM ingestion_event_claims
                WHERE document_public_id = '${document_id}';
                DELETE FROM outbox_events
                WHERE document_public_id = '${document_id}';
                DELETE FROM documents
                WHERE public_id = '${document_id}';
            " >/dev/null 2>&1 || true
    fi

    if [[ -n "$storage_key" ]]; then
        docker compose exec -T localstack awslocal s3 rm \
            "s3://${DOCUMENT_UPLOAD_BUCKET}/${storage_key}" \
            >/dev/null 2>&1 || true
    fi
}

trap cleanup EXIT

sensitive_marker="stage-12-private-content-$(date +%s)-$$"
printf '%s\n' "$sensitive_marker" >"$payload_file"
payload_size="$(wc -c <"$payload_file" | tr -d ' ')"
trace_id="$(od -An -N16 -tx1 /dev/urandom | tr -d ' \n')"
parent_span_id="$(od -An -N8 -tx1 /dev/urandom | tr -d ' \n')"
correlation_id="$(uuidgen | tr '[:upper:]' '[:lower:]')"

refresh_xsrf() {
    curl --fail --silent --show-error \
        --cookie "$cookie_file" \
        --cookie-jar "$cookie_file" \
        "${API_URL}/sanctum/csrf-cookie" \
        --output /dev/null

    xsrf_token="$({
        awk '$6 == "XSRF-TOKEN" {print $7}' "$cookie_file" || true
    } | tail -n 1 | sed 's/%3D/=/g')"

    if [[ -z "$xsrf_token" ]]; then
        printf 'Laravel did not issue an XSRF token.\n' >&2
        exit 1
    fi
}

api_request() {
    curl --fail --silent --show-error \
        --cookie "$cookie_file" \
        --cookie-jar "$cookie_file" \
        --header "X-XSRF-TOKEN: ${xsrf_token}" \
        --header 'Accept: application/json' \
        --header 'Content-Type: application/json' \
        --header 'Origin: http://localhost:3000' \
        "$@"
}

refresh_xsrf
api_request \
    --data '{"email":"workspace.tester@example.test","password":"password"}' \
    "${API_URL}/api/auth/login" \
    --output /dev/null

api_request "${API_URL}/api/workspaces" --output "$response_file"
workspace_id="$(jq -r '.data[] | select(.slug == "atlas-research") | .public_id' "$response_file")"

if [[ -z "$workspace_id" || "$workspace_id" == "null" ]]; then
    printf 'The deterministic Atlas Research workspace is unavailable. Run make seed.\n' >&2
    exit 1
fi

refresh_xsrf
api_request \
    --data "{
        \"filename\": \"telemetry-privacy-check.txt\",
        \"media_type\": \"text/plain\",
        \"size_bytes\": ${payload_size}
    }" \
    "${API_URL}/api/workspaces/${workspace_id}/documents/uploads" \
    --output "$response_file"

document_id="$(jq -r '.data.document.public_id' "$response_file")"
upload_url="$(jq -r '.data.upload.url' "$response_file")"
storage_key="workspaces/${workspace_id}/documents/${document_id}/source.txt"
upload_args=(--request PUT)

while IFS=$'\t' read -r name value; do
    upload_args+=(--header "${name}: ${value}")
done < <(jq -r '.data.upload.headers | to_entries[] | [.key, .value] | @tsv' "$response_file")

curl --fail --silent --show-error \
    "${upload_args[@]}" \
    --data-binary "@${payload_file}" \
    "$upload_url" \
    --output /dev/null

refresh_xsrf
api_request \
    --request POST \
    "${API_URL}/api/workspaces/${workspace_id}/documents/${document_id}/uploads/complete" \
    --output /dev/null

refresh_xsrf
api_request \
    --request POST \
    --header "traceparent: 00-${trace_id}-${parent_span_id}-01" \
    --header "X-Correlation-ID: ${correlation_id}" \
    "${API_URL}/api/workspaces/${workspace_id}/documents/${document_id}/ingestion-requests" \
    --output /dev/null

document_status=""

for _ in {1..30}; do
    document_status="$(
        docker compose exec -T postgres psql \
            --username rag_platform \
            --dbname rag_platform \
            --tuples-only \
            --no-align \
            --command "SELECT status FROM documents WHERE public_id = '${document_id}'" \
            | tr -d '[:space:]'
    )"

    if [[ "$document_status" == "processing" ]]; then
        break
    fi

    sleep 1
done

if [[ "$document_status" != "processing" ]]; then
    printf 'Document did not reach PROCESSING; observed: %s\n' "$document_status" >&2
    exit 1
fi

trace_json=""

for _ in {1..30}; do
    trace_json="$(
        curl --fail --silent --show-error \
            "${GRAFANA_URL}/api/datasources/proxy/uid/tempo/api/traces/${trace_id}" \
            2>/dev/null || true
    )"

    if [[ "$trace_json" == *rag-platform-ingestion-worker* ]] \
        && [[ "$trace_json" == *rag-platform-ingestion-publisher* ]] \
        && [[ "$trace_json" == *rag-platform-api* ]]; then
        break
    fi

    sleep 1
done

for service_name in \
    rag-platform-api \
    rag-platform-ingestion-publisher \
    rag-platform-ingestion-worker; do
    if [[ "$trace_json" != *"$service_name"* ]]; then
        printf 'Trace %s is missing service %s.\n' "$trace_id" "$service_name" >&2
        exit 1
    fi
done

for span_name in \
    'messaging.publish document.ingestion.requested' \
    'process rag-platform-ingestion-local' \
    'POST /api/internal/ingestion/events/{eventId}/claim'; do
    if [[ "$trace_json" != *"$span_name"* ]]; then
        printf 'Trace %s is missing span %s.\n' "$trace_id" "$span_name" >&2
        exit 1
    fi
done

if [[ "$trace_json" == *"$sensitive_marker"* ]]; then
    printf 'Synthetic sensitive content appeared in exported trace data.\n' >&2
    exit 1
fi

metric_json="$(
    curl --fail --silent --show-error \
        --get \
        --data-urlencode 'query={__name__=~"http_server_request_count_total|rag_ingestion_.*"}' \
        "${GRAFANA_URL}/api/datasources/proxy/uid/prometheus/api/v1/query"
)"

if jq -e '
    .data.result[].metric
    | keys[]
    | select(. == "rag_correlation_id"
        or . == "rag_document_id"
        or . == "rag_event_id"
        or . == "rag_workspace_id"
        or . == "messaging_message_id")
' >/dev/null <<<"$metric_json"; then
    printf 'An unbounded entity identifier appeared in metric labels.\n' >&2
    exit 1
fi

printf 'Cross-service telemetry verified:\n'
printf '  trace ID:       %s\n' "$trace_id"
printf '  correlation ID: %s\n' "$correlation_id"
printf '  final status:   %s\n' "$document_status"
printf '  services:       Laravel API, publisher, Python worker\n'
printf '  privacy:        synthetic content absent\n'
printf '  metrics:        entity labels absent\n'
