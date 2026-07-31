#!/bin/sh

set -eu

: "${OTEL_COLLECTOR_HTTP_PORT:=4318}"
: "${OTEL_COLLECTOR_HEALTH_PORT:=13133}"
: "${GRAFANA_PORT:=3001}"

collector_url="http://127.0.0.1:${OTEL_COLLECTOR_HTTP_PORT}"
collector_health_url="http://127.0.0.1:${OTEL_COLLECTOR_HEALTH_PORT}"
grafana_url="http://127.0.0.1:${GRAFANA_PORT}"

timestamp_seconds="$(date +%s)"
start_time_unix_nano="$((timestamp_seconds * 1000000000))"
end_time_unix_nano="$((start_time_unix_nano + 1000000))"
trace_id="$(printf '%016x%016x' "$timestamp_seconds" "$$")"
span_id="$(printf '%016x' "$$")"

curl --fail --silent --show-error \
    "${collector_health_url}/" \
    >/dev/null

curl --fail --silent --show-error \
    --header "Content-Type: application/json" \
    --data "{
      \"resourceSpans\": [{
        \"resource\": {
          \"attributes\": [{
            \"key\": \"service.name\",
            \"value\": {\"stringValue\": \"rag-platform-telemetry-smoke\"}
          }]
        },
        \"scopeSpans\": [{
          \"scope\": {\"name\": \"rag-platform.infrastructure-smoke\"},
          \"spans\": [{
            \"traceId\": \"${trace_id}\",
            \"spanId\": \"${span_id}\",
            \"name\": \"collector-to-local-backend\",
            \"kind\": 1,
            \"startTimeUnixNano\": \"${start_time_unix_nano}\",
            \"endTimeUnixNano\": \"${end_time_unix_nano}\",
            \"status\": {\"code\": 1}
          }]
        }]
      }]
    }" \
    "${collector_url}/v1/traces" \
    >/dev/null

curl --fail --silent --show-error \
    --header "Content-Type: application/json" \
    --data "{
      \"resourceMetrics\": [{
        \"resource\": {
          \"attributes\": [{
            \"key\": \"service.name\",
            \"value\": {\"stringValue\": \"rag-platform-telemetry-smoke\"}
          }]
        },
        \"scopeMetrics\": [{
          \"scope\": {\"name\": \"rag-platform.infrastructure-smoke\"},
          \"metrics\": [{
            \"name\": \"rag_platform_telemetry_smoke_test\",
            \"gauge\": {
              \"dataPoints\": [{
                \"timeUnixNano\": \"${end_time_unix_nano}\",
                \"asInt\": \"${timestamp_seconds}\"
              }]
            }
          }]
        }]
      }]
    }" \
    "${collector_url}/v1/metrics" \
    >/dev/null

trace_found=false
metric_found=false
attempt=1

while [ "$attempt" -le 30 ]; do
    if curl --fail --silent --show-error \
        "${grafana_url}/api/datasources/proxy/uid/tempo/api/traces/${trace_id}" \
        >/dev/null 2>&1; then
        trace_found=true
    fi

    metric_response="$(
        curl --fail --silent --show-error \
            "${grafana_url}/api/datasources/proxy/uid/prometheus/api/v1/query?query=rag_platform_telemetry_smoke_test" \
            2>/dev/null || true
    )"

    case "$metric_response" in
        *rag_platform_telemetry_smoke_test*"$timestamp_seconds"*)
            metric_found=true
            ;;
    esac

    if [ "$trace_found" = true ] && [ "$metric_found" = true ]; then
        break
    fi

    sleep 1
    attempt=$((attempt + 1))
done

if [ "$trace_found" != true ]; then
    printf 'Synthetic trace %s was not queryable through Grafana.\n' \
        "$trace_id" \
        >&2
    exit 1
fi

if [ "$metric_found" != true ]; then
    printf 'Synthetic metric was not queryable through Grafana.\n' >&2
    exit 1
fi

printf 'Local telemetry verified:\n'
printf '  collector: %s\n' "$collector_url"
printf '  trace ID:  %s\n' "$trace_id"
printf '  metric:    rag_platform_telemetry_smoke_test\n'
printf '  Grafana:   %s\n' "$grafana_url"
