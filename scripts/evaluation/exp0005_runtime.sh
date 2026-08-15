#!/usr/bin/env bash

set -euo pipefail

readonly run_id='EXP-0005-adr0022-v2-consolidated-engineering-baseline'
readonly project_name='rag-platform'
readonly repository_root="$(git rev-parse --show-toplevel)"
readonly compose_override="${repository_root}/compose.exp0005.yaml"
readonly definition="${repository_root}/tests/evaluation/experiment-definitions/${run_id}/runtime-lineage.json"
readonly expected_record_sha="$(jq -er '.provisioning.record_sha256' "${definition}")"
readonly environment_file="${EXP0005_ENV_FILE:-${repository_root}/.env}"
readonly provisioning_record="${EXP0005_PROVISIONING_RECORD:-/private/tmp/rag-platform-exp0005/provisioning.json}"
readonly runs_root="${EXP0005_RUNS_ROOT:-${repository_root}/docs/evaluation/runs}"
readonly index_file="${EXP0005_INDEX_FILE:-${repository_root}/docs/evaluation/EXPERIMENTS.md}"

usage() {
    printf 'Usage: %s prepare <verified-provisioning-source> | start | verify | close | run\n' "$0" >&2
    exit 2
}

sha256() {
    openssl dgst -sha256 "$1" | awk '{print $NF}'
}

assert_clean_lineage() {
    local head origin
    head="$(git -C "${repository_root}" rev-parse HEAD)"
    origin="$(git -C "${repository_root}" rev-parse origin/main)"
    if [[ -n "$(git -C "${repository_root}" status --porcelain --untracked-files=all)" ]]; then
        printf '%s requires a clean worktree.\n' "${run_id}" >&2
        exit 1
    fi
    if [[ ! "${head}" =~ ^[0-9a-f]{40}$ || "${head}" != "${origin}" ]]; then
        printf '%s requires HEAD to equal the exact pushed origin/main commit.\n' "${run_id}" >&2
        exit 1
    fi
}

assert_runtime_inputs() {
    [[ -f "${environment_file}" ]] || { printf 'Missing EXP-0005 environment file: %s\n' "${environment_file}" >&2; exit 1; }
    [[ -f "${provisioning_record}" ]] || { printf 'Missing EXP-0005 provisioning record: %s\n' "${provisioning_record}" >&2; exit 1; }
    [[ "$(sha256 "${provisioning_record}")" == "${expected_record_sha}" ]] || {
        printf 'The EXP-0005 provisioning record failed closed on its SHA-256 digest.\n' >&2
        exit 1
    }
}

compose() {
    EXP0005_PROVISIONING_RECORD="${provisioning_record}" \
    EXP0005_RUNS_ROOT="${runs_root}" \
    EXP0005_INDEX_FILE="${index_file}" \
    docker compose \
        --project-name "${project_name}" \
        --env-file "${environment_file}" \
        --file "${repository_root}/compose.yaml" \
        --file "${compose_override}" \
        "$@"
}

prepare() {
    local source=${1:-}
    [[ -n "${source}" && -f "${source}" ]] || usage
    [[ "$(sha256 "${source}")" == "${expected_record_sha}" ]] || {
        printf 'The provisioning source does not match the immutable EXP-0005 definition.\n' >&2
        exit 1
    }
    mkdir -p "$(dirname "${provisioning_record}")"
    install -m 0444 "${source}" "${provisioning_record}"
    printf 'Prepared verified EXP-0005 provisioning record at %s\n' "${provisioning_record}"
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

assert_no_protected_mounts() {
    local container=$1
    if mount_destinations "${container}" | grep -Eq '^/evaluation$|^/evaluation/(calibration|held-out|benchmarks)(/|$)|^/evaluation/engineering-snapshots$|^/evaluation/planner-expectations($|/v2$)'; then
        printf '%s exposes a broad or protected evaluation path.\n' "${container}" >&2
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
    assert_runtime_inputs
    local corpus="${repository_root}/tests/evaluation/engineering-snapshots/dolved-care-engineering/v2/corpus.json"
    local expectations="${repository_root}/tests/evaluation/planner-expectations/v2/engineering-expectations.json"
    assert_mount rag-platform-api-1 /app "${repository_root}/apps/api"
    assert_mount rag-platform-api-1 /contracts "${repository_root}/contracts"
    assert_mount rag-platform-api-1 /evaluation/engineering-snapshots/dolved-care-engineering/v2/corpus.json "${corpus}"
    assert_mount rag-platform-api-1 /evaluation/planner-expectations/v2/engineering-expectations.json "${expectations}"
    assert_mount rag-platform-api-1 /evaluation-runs "${runs_root}"
    assert_mount rag-platform-api-1 /app/storage/app/private/evaluation/dolved-care-engineering/v2/provisioning.json "${provisioning_record}"
    assert_mount rag-platform-ai-1 /app "${repository_root}/apps/ai"
    assert_mount rag-platform-ai-1 /contracts "${repository_root}/contracts"
    assert_mount rag-platform-ai-1 /evaluation/engineering-snapshots/dolved-care-engineering/v2/corpus.json "${corpus}"
    assert_mount rag-platform-ai-1 /evaluation/planner-expectations/v2/engineering-expectations.json "${expectations}"
    assert_mount rag-platform-ai-1 /evaluation-lineage/provisioning.json "${provisioning_record}"
    assert_mount rag-platform-ai-1 /workspace/scripts "${repository_root}/scripts"
    assert_no_protected_mounts rag-platform-api-1
    assert_no_protected_mounts rag-platform-ai-1
    compose exec -T api php artisan evaluation:benchmark:preflight-exp-0005
    compose exec -T ai python /workspace/scripts/evaluation/verify_exp0005_qdrant.py \
        --provisioning /evaluation-lineage/provisioning.json
    verify_queues
    printf 'Verified provider-free EXP-0005 preflight at %s.\n' "$(git -C "${repository_root}" rev-parse HEAD)"
}

start() {
    assert_clean_lineage
    assert_runtime_inputs
    compose up --detach --no-deps --force-recreate --wait api ai
    verify
}

run() {
    verify
    local head
    head="$(git -C "${repository_root}" rev-parse HEAD)"
    compose exec -T api php artisan evaluation:benchmark:run-exp-0005 --repository-commit="${head}"
    close
}

close() {
    [[ -f "${runs_root}/${run_id}/application-observations.json" ]] || {
        printf 'Missing preserved EXP-0005 observations under %s\n' "${runs_root}" >&2
        exit 1
    }
    [[ -f "${runs_root}/EXP-0004-rrf-k-5-controlled-engineering-experiment/result.json" ]] || {
        printf 'Missing immutable EXP-0004 comparison result under %s\n' "${runs_root}" >&2
        exit 1
    }
    compose run --rm --no-deps -T --env PYTHONPATH=/app ai \
        python /workspace/scripts/evaluation/compile_application_benchmark_run.py \
        --observations "/evaluation-runs/${run_id}/application-observations.json" \
        --output-directory "/evaluation-runs/${run_id}" \
        --historical-baseline /evaluation-runs/EXP-0004-rrf-k-5-controlled-engineering-experiment/result.json
    compose run --rm --no-deps -T --env PYTHONPATH=/app ai \
        python /workspace/scripts/evaluation/report.py generate \
        --run-dir "/evaluation-runs/${run_id}" \
        --runs-root /evaluation-runs \
        --index /evaluation-index/EXPERIMENTS.md \
        --baseline-result /evaluation-runs/EXP-0004-rrf-k-5-controlled-engineering-experiment/result.json
    compose run --rm --no-deps -T --env PYTHONPATH=/app ai \
        python /workspace/scripts/evaluation/write_run_checksums.py \
        --run-directory "/evaluation-runs/${run_id}"
}

case "${1:-}" in
    prepare) prepare "${2:-}" ;;
    start) start ;;
    verify) verify ;;
    close) close ;;
    run) run ;;
    *) usage ;;
esac
