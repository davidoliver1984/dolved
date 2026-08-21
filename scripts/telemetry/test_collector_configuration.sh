#!/usr/bin/env bash

set -euo pipefail

repository_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "${repository_root}"

components="$(docker compose run --rm --no-deps --entrypoint /otelcol otel-collector components)"
grep -Fq 'name: probabilistic_sampler' <<<"${components}"
grep -Fq 'github.com/open-telemetry/opentelemetry-collector-contrib/processor/probabilisticsamplerprocessor' <<<"${components}"

docker compose run --rm --no-deps --entrypoint /otelcol otel-collector \
    validate --config=/etc/otelcol/config.yaml

pipeline=$'      processors:\n        - probabilistic_sampler\n        - batch'
grep -Fq "${pipeline}" infrastructure/opentelemetry/collector.yaml

printf '%s\n' 'Pinned Collector sampling component and configuration validated.'
