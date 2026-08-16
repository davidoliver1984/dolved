#!/usr/bin/env bash

set -euo pipefail

readonly run_id='EXP-0007-v3-engineering-regression-confirmation'
readonly project_name='rag-platform'
readonly repository_root="$(git rev-parse --show-toplevel)"
readonly compose_override="${repository_root}/compose.exp0007.yaml"
readonly definition="${repository_root}/tests/evaluation/experiment-definitions/${run_id}/runtime-lineage.json"
readonly expected_record_sha="$(jq -er '.provisioning.record_sha256' "${definition}")"
readonly environment_file="${EXP0007_ENV_FILE:-/Users/davidoliver/Desktop/RAG/.env}"
readonly provisioning_record="${EXP0007_PROVISIONING_RECORD:-/private/tmp/rag-platform-exp0007/provisioning.json}"
readonly runs_root="${EXP0007_RUNS_ROOT:-/private/tmp/rag-platform-exp0007/runs}"
readonly index_file="${EXP0007_INDEX_FILE:-/private/tmp/rag-platform-exp0007/index/EXPERIMENTS.md}"

usage() {
    printf 'Usage: %s prepare <materialised-provisioning-source> | start | verify | run | close\n' "$0" >&2
    exit 2
}

sha256() {
    openssl dgst -sha256 "$1" | awk '{print $NF}'
}

assert_clean_lineage() {
    local head origin
    head="$(git -C "${repository_root}" rev-parse HEAD)"
    origin="$(git -C "${repository_root}" rev-parse origin/main)"
    [[ -z "$(git -C "${repository_root}" status --porcelain --untracked-files=all)" ]] || {
        printf '%s requires a clean worktree.\n' "${run_id}" >&2
        exit 1
    }
    [[ "${head}" =~ ^[0-9a-f]{40}$ && "${head}" == "${origin}" ]] || {
        printf '%s requires HEAD to equal the exact pushed origin/main commit.\n' "${run_id}" >&2
        exit 1
    }
}

assert_inputs() {
    [[ -f "${environment_file}" ]] || { printf 'Missing EXP-0007 environment file.\n' >&2; exit 1; }
    [[ -f "${provisioning_record}" ]] || { printf 'Missing EXP-0007 provisioning record.\n' >&2; exit 1; }
    [[ "$(sha256 "${provisioning_record}")" == "${expected_record_sha}" ]] || {
        printf 'The EXP-0007 provisioning record failed closed on SHA-256.\n' >&2
        exit 1
    }
}

compose() {
    EXP0007_PROVISIONING_RECORD="${provisioning_record}" \
    EXP0007_RUNS_ROOT="${runs_root}" \
    EXP0007_INDEX_FILE="${index_file}" docker compose \
        --project-name "${project_name}" \
        --env-file "${environment_file}" \
        --file "${repository_root}/compose.yaml" \
        --file "${compose_override}" "$@"
}

prepare() {
    local source=${1:-}
    [[ -n "${source}" && -f "${source}" ]] || usage
    [[ "$(sha256 "${source}")" == "${expected_record_sha}" ]] || {
        printf 'The provisioning source does not match EXP-0007.\n' >&2
        exit 1
    }
    mkdir -p "$(dirname "${provisioning_record}")"
    install -m 0444 "${source}" "${provisioning_record}"
    printf 'Prepared verified EXP-0007 provisioning record at %s\n' "${provisioning_record}"
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
            --queue-url "${url}" --attribute-names ApproximateNumberOfMessages ApproximateNumberOfMessagesNotVisible \
            --query '[Attributes.ApproximateNumberOfMessages,Attributes.ApproximateNumberOfMessagesNotVisible]' --output text)
        [[ "${visible}" == '0' && "${inflight}" == '0' ]] || {
            printf 'Queue %s is not empty (%s visible, %s in flight).\n' "${queue}" "${visible}" "${inflight}" >&2
            exit 1
        }
    done
}

verify() {
    assert_clean_lineage
    assert_inputs
    local root="${repository_root}/tests/evaluation/engineering-populations/dolved-care-engineering/v3/v1"
    assert_mount rag-platform-api-1 /app "${repository_root}/apps/api"
    assert_mount rag-platform-api-1 /evaluation/engineering/corpus.json "${root}/corpus.json"
    assert_mount rag-platform-api-1 /app/storage/app/private/evaluation/dolved-care-engineering/v3/provisioning.json "${provisioning_record}"
    assert_mount rag-platform-ai-1 /app "${repository_root}/apps/ai"
    assert_mount rag-platform-ai-1 /evaluation/engineering/corpus.json "${root}/corpus.json"
    assert_mount rag-platform-ai-1 /evaluation-lineage/provisioning.json "${provisioning_record}"
    assert_isolated rag-platform-api-1
    assert_isolated rag-platform-ai-1
    compose exec -T api php artisan evaluation:benchmark:preflight-exp-0007
    compose exec -T ai python /workspace/scripts/evaluation/verify_exp0005_qdrant.py --provisioning /evaluation-lineage/provisioning.json
    verify_queues
    printf 'Verified provider-free EXP-0007 preflight at %s.\n' "$(git -C "${repository_root}" rev-parse HEAD)"
}

start() {
    assert_clean_lineage
    assert_inputs
    mkdir -p "${runs_root}" "$(dirname "${index_file}")"
    touch "${index_file}"
    compose up --detach --no-deps --force-recreate --wait api ai
    verify
}

close() {
    local run_directory="${runs_root}/${run_id}"
    [[ -f "${run_directory}/application-observations.json" ]] || {
        printf 'Missing preserved EXP-0007 observations under %s\n' "${runs_root}" >&2
        exit 1
    }
    [[ "$(jq -er '.observations | length' "${run_directory}/application-observations.json")" == '31' ]] || {
        printf 'EXP-0007 cannot close without exactly 31 observations.\n' >&2
        exit 1
    }
    compose run --rm --no-deps -T --env PYTHONPATH=/app ai \
        python /workspace/scripts/evaluation/compile_application_benchmark_run.py \
        --observations "/evaluation-runs/${run_id}/application-observations.json" \
        --output-directory "/evaluation-runs/${run_id}" \
        --planner-prompt-version adr-0022-v4 \
        --planner-expectations /evaluation/engineering/expectations.json
    local first
    first="$(openssl dgst -sha256 "${run_directory}/result.json" "${run_directory}/config.json" "${run_directory}/comparison.json")"
    compose run --rm --no-deps -T --env PYTHONPATH=/app ai \
        python /workspace/scripts/evaluation/compile_application_benchmark_run.py \
        --observations "/evaluation-runs/${run_id}/application-observations.json" \
        --output-directory "/evaluation-runs/${run_id}" \
        --planner-prompt-version adr-0022-v4 \
        --planner-expectations /evaluation/engineering/expectations.json
    [[ "${first}" == "$(openssl dgst -sha256 "${run_directory}/result.json" "${run_directory}/config.json" "${run_directory}/comparison.json")" ]] || {
        printf 'EXP-0007 deterministic compilation changed between passes.\n' >&2
        exit 1
    }
    compose run --rm --no-deps -T --env PYTHONPATH=/app ai \
        python /workspace/scripts/evaluation/report.py generate \
        --run-dir "/evaluation-runs/${run_id}" \
        --runs-root /evaluation-runs \
        --index /evaluation-index/EXPERIMENTS.md
    compose run --rm --no-deps -T --env PYTHONPATH=/app ai \
        python /workspace/scripts/evaluation/write_run_checksums.py \
        --run-directory "/evaluation-runs/${run_id}"
}

run() {
    verify
    local head
    head="$(git -C "${repository_root}" rev-parse HEAD)"
    compose exec -T api php artisan evaluation:benchmark:run-exp-0007 --repository-commit="${head}"
    close
}

case "${1:-}" in
    prepare) prepare "${2:-}" ;;
    start) start ;;
    verify) verify ;;
    run) run ;;
    close) close ;;
    *) usage ;;
esac
