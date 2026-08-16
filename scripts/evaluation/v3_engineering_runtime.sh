#!/usr/bin/env bash

set -euo pipefail

readonly project_name='rag-platform'
readonly repository_root="$(git rev-parse --show-toplevel)"
readonly environment_file="${V3_ENGINEERING_ENV_FILE:-/Users/davidoliver/Desktop/RAG/.env}"
readonly compose_override="${repository_root}/compose.v3-engineering.yaml"
readonly state_source="${repository_root}/apps/api/storage/app/private/evaluation/dolved-care-engineering/v3/provisioning.json"
readonly exported_record="${V3_ENGINEERING_PROVISIONING_RECORD:-/private/tmp/rag-platform-v3-engineering/provisioning.json}"

usage() {
    printf 'Usage: %s start | verify | materialise | export\n' "$0" >&2
    exit 2
}

assert_clean_lineage() {
    local head origin
    head="$(git -C "${repository_root}" rev-parse HEAD)"
    origin="$(git -C "${repository_root}" rev-parse origin/main)"
    [[ -z "$(git -C "${repository_root}" status --porcelain --untracked-files=all)" ]] || {
        printf 'V3 engineering materialisation requires a clean worktree.\n' >&2
        exit 1
    }
    [[ "${head}" =~ ^[0-9a-f]{40}$ && "${head}" == "${origin}" ]] || {
        printf 'V3 engineering materialisation requires HEAD == origin/main.\n' >&2
        exit 1
    }
}

compose() {
    docker compose \
        --project-name "${project_name}" \
        --env-file "${environment_file}" \
        --file "${repository_root}/compose.yaml" \
        --file "${compose_override}" \
        "$@"
}

mount_source() {
    docker inspect "$1" --format "{{range .Mounts}}{{if eq .Destination \"$2\"}}{{.Source}}{{end}}{{end}}"
}

mount_destinations() {
    docker inspect "$1" --format '{{range .Mounts}}{{println .Destination}}{{end}}'
}

assert_mount() {
    local container=$1 destination=$2 expected=$3 actual
    actual="$(mount_source "${container}" "${destination}")"
    actual="${actual#/host_mnt}"
    [[ "${actual}" == "${expected}" ]] || {
        printf '%s mount %s resolved to %s, expected %s\n' "${container}" "${destination}" "${actual:-<missing>}" "${expected}" >&2
        exit 1
    }
}

assert_isolated() {
    local container=$1
    if mount_destinations "${container}" | grep -Eq '^/evaluation$|^/evaluation/(calibration|held-out|benchmarks|engineering-snapshots|planner-expectations)(/|$)|^/evaluation/engineering/(calibration|held-out|v2)(/|$)'; then
        printf '%s exposes a protected evaluation path.\n' "${container}" >&2
        exit 1
    fi
}

verify_queues() {
    local queue url visible inflight
    for queue in rag-platform-ingestion-local rag-platform-ingestion-dlq-local; do
        url="$(compose exec -T localstack awslocal sqs get-queue-url --queue-name "${queue}" --query QueueUrl --output text)"
        read -r visible inflight < <(compose exec -T localstack awslocal sqs get-queue-attributes \
            --queue-url "${url}" \
            --attribute-names ApproximateNumberOfMessages ApproximateNumberOfMessagesNotVisible \
            --query '[Attributes.ApproximateNumberOfMessages,Attributes.ApproximateNumberOfMessagesNotVisible]' \
            --output text)
        [[ "${visible}" == '0' && "${inflight}" == '0' ]] || {
            printf 'Queue %s is not empty (%s visible, %s in flight).\n' "${queue}" "${visible}" "${inflight}" >&2
            exit 1
        }
    done
}

verify() {
    assert_clean_lineage
    assert_mount rag-platform-api-1 /app "${repository_root}/apps/api"
    assert_mount rag-platform-api-1 /contracts "${repository_root}/contracts"
    assert_mount rag-platform-api-1 /evaluation/engineering/corpus.json "${repository_root}/tests/evaluation/engineering-populations/dolved-care-engineering/v3/v1/corpus.json"
    assert_mount rag-platform-api-1 /evaluation/engineering/expectations.json "${repository_root}/tests/evaluation/engineering-populations/dolved-care-engineering/v3/v1/expectations.json"
    assert_mount rag-platform-api-1 /evaluation/engineering/source/documents "${repository_root}/tests/evaluation/benchmarks/dolved-care-engineering/v3/documents"
    assert_mount rag-platform-ai-1 /app "${repository_root}/apps/ai"
    assert_mount rag-platform-ai-1 /contracts "${repository_root}/contracts"
    assert_mount rag-platform-ai-1 /evaluation/engineering/corpus.json "${repository_root}/tests/evaluation/engineering-populations/dolved-care-engineering/v3/v1/corpus.json"
    assert_mount rag-platform-publisher-1 /app "${repository_root}/apps/api"
    assert_mount rag-platform-worker-1 /app "${repository_root}/apps/ai"
    assert_isolated rag-platform-api-1
    assert_isolated rag-platform-ai-1
    assert_isolated rag-platform-publisher-1
    assert_isolated rag-platform-worker-1
    printf 'Verified isolated V3 engineering runtime at %s.\n' "$(git -C "${repository_root}" rev-parse HEAD)"
}

start() {
    assert_clean_lineage
    [[ -f "${environment_file}" ]] || { printf 'Missing environment file: %s\n' "${environment_file}" >&2; exit 1; }
    compose up --detach --no-deps --force-recreate --wait api ai publisher worker
    verify
}

export_record() {
    [[ -f "${state_source}" ]] || { printf 'Missing V3 provisioning state.\n' >&2; exit 1; }
    [[ "$(jq -er '.status' "${state_source}")" == 'MATERIALISED' ]] || {
        printf 'The V3 provisioning state is not MATERIALISED.\n' >&2
        exit 1
    }
    mkdir -p "$(dirname "${exported_record}")"
    install -m 0444 "${state_source}" "${exported_record}"
    printf 'Exported MATERIALISED V3 state to %s\n' "${exported_record}"
    openssl dgst -sha256 "${exported_record}"
}

materialise() {
    verify
    verify_queues
    local head
    head="$(git -C "${repository_root}" rev-parse HEAD)"
    compose exec -T api php artisan evaluation:v3-engineering:provision --repository-commit="${head}"
    compose exec -T api php artisan evaluation:v3-engineering:verify-ingestion --timeout=21600 --poll-ms=1000
    verify_queues
    compose exec -T api php artisan evaluation:v3-engineering:build-hybrid --batch-size=10
    verify_queues
    export_record
}

case "${1:-}" in
    start) start ;;
    verify) verify ;;
    materialise) materialise ;;
    export) export_record ;;
    *) usage ;;
esac
