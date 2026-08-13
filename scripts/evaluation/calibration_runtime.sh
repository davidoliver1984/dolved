#!/usr/bin/env bash

set -euo pipefail

readonly run_id='CAL-EXP-0001-evidence-threshold-calibration'
readonly project_name='rag-platform'
readonly repository_root="$(git rev-parse --show-toplevel)"
readonly definition="${repository_root}/tests/evaluation/experiment-definitions/${run_id}/runtime-lineage.json"
readonly compose_override="${repository_root}/compose.calibration.yaml"
readonly environment_file="${CALIBRATION_ENV_FILE:-${repository_root}/.env}"
readonly isolated_root="${CALIBRATION_ISOLATED_ROOT:-/private/tmp/rag-platform-threshold-calibration}"
readonly calibration_snapshot="${isolated_root}/corpus.json"
readonly calibration_expectations="${isolated_root}/expectations.json"
readonly provisioning_record="${isolated_root}/provisioning.json"
readonly input_lineage="${isolated_root}/input-lineage.json"
readonly runs_root="${CALIBRATION_RUNS_ROOT:-${repository_root}/docs/evaluation/runs}"

usage() {
    printf 'Usage: %s prepare <calibration-snapshot> <calibration-expectations> <provisioning-record> | start | verify | run | replay\n' "$0" >&2
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
        printf '%s requires a clean exact-commit worktree.\n' "${run_id}" >&2
        exit 1
    }
    [[ "${head}" =~ ^[0-9a-f]{40}$ && "${head}" == "${origin}" ]] || {
        printf '%s requires HEAD to equal the pushed origin/main commit.\n' "${run_id}" >&2
        exit 1
    }
}

assert_snapshot_metadata() {
    local path=$1 label=$2
    [[ "$(jq -er '.benchmark.id' "${path}")" == 'dolved-care-engineering' ]]
    [[ "$(jq -er '.benchmark.version' "${path}")" == 'v2' ]]
    [[ "$(jq -er '.benchmark.digest' "${path}")" == 'aabeb8c444fc5af7642d894e2f786eb684e663efe17bb702512d609a2701286d' ]]
    [[ "$(jq -er '.split.name' "${path}")" == 'threshold_calibration' ]]
    [[ "$(jq -er '.case_count' "${path}")" == '28' ]]
    [[ "$(jq -er '.variant_count' "${path}")" == '84' ]]
    if grep -Eq 'held_out_acceptance|engineering_tuning' "${path}"; then
        printf '%s contains a protected non-calibration split.\n' "${label}" >&2
        exit 1
    fi
}

prepare() {
    local snapshot=${1:-} expectations=${2:-} provisioning=${3:-}
    [[ -f "${snapshot}" && -f "${expectations}" && -f "${provisioning}" ]] || usage
    assert_snapshot_metadata "${snapshot}" snapshot
    assert_snapshot_metadata "${expectations}" expectations
    [[ "$(sha256 "${provisioning}")" == "$(jq -er '.provisioning.record_sha256' "${definition}")" ]] || {
        printf 'Provisioning lineage differs from the immutable definition.\n' >&2
        exit 1
    }
    mkdir -p "${isolated_root}"
    install -m 0444 "${snapshot}" "${calibration_snapshot}"
    install -m 0444 "${expectations}" "${calibration_expectations}"
    install -m 0444 "${provisioning}" "${provisioning_record}"
    jq -n \
        --arg snapshot_sha256 "$(sha256 "${calibration_snapshot}")" \
        --arg expectations_sha256 "$(sha256 "${calibration_expectations}")" \
        --arg provisioning_sha256 "$(sha256 "${provisioning_record}")" \
        '{schema_version:"v1", snapshot_sha256:$snapshot_sha256, expectations_sha256:$expectations_sha256, provisioning_sha256:$provisioning_sha256}' \
        >"${input_lineage}"
    chmod 0444 "${input_lineage}"
}

assert_inputs() {
    [[ -f "${calibration_snapshot}" && -f "${calibration_expectations}" && -f "${provisioning_record}" && -f "${input_lineage}" ]]
    [[ "$(sha256 "${calibration_snapshot}")" == "$(jq -er '.snapshot_sha256' "${input_lineage}")" ]]
    [[ "$(sha256 "${calibration_expectations}")" == "$(jq -er '.expectations_sha256' "${input_lineage}")" ]]
    [[ "$(sha256 "${provisioning_record}")" == "$(jq -er '.provisioning_sha256' "${input_lineage}")" ]]
    assert_snapshot_metadata "${calibration_snapshot}" snapshot
    assert_snapshot_metadata "${calibration_expectations}" expectations
}

compose() {
    CALIBRATION_SNAPSHOT="${calibration_snapshot}" \
    CALIBRATION_EXPECTATIONS="${calibration_expectations}" \
    CALIBRATION_PROVISIONING_RECORD="${provisioning_record}" \
    CALIBRATION_RUNS_ROOT="${runs_root}" \
    docker compose --project-name "${project_name}" --env-file "${environment_file}" \
        --file "${repository_root}/compose.yaml" --file "${compose_override}" "$@"
}

mount_source() {
    docker inspect "$1" --format "{{range .Mounts}}{{if eq .Destination \"$2\"}}{{.Source}}{{end}}{{end}}"
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

verify() {
    assert_clean_lineage
    assert_inputs
    local container service
    for container in rag-platform-api-1 rag-platform-ai-1; do
        service=api
        [[ "${container}" == 'rag-platform-ai-1' ]] && service=ai
        assert_mount "${container}" /evaluation/calibration/corpus.json "${calibration_snapshot}"
        assert_mount "${container}" /evaluation/calibration/expectations.json "${calibration_expectations}"
        assert_mount "${container}" /evaluation/calibration/policy.json "${repository_root}/tests/evaluation/policies/evidence-threshold-calibration/v1/policy.json"
        [[ -z "$(mount_source "${container}" /evaluation)" ]]
        compose exec -T "${service}" sh -c \
            'test ! -e /evaluation/held-out && test ! -e /evaluation/benchmarks && test ! -e /evaluation/corpus && test ! -e /evaluation/observations && test ! -e /evaluation/hybrid && test ! -e /evaluation/engineering-snapshots && test ! -e /evaluation/planner-expectations'
    done
    printf 'Verified physically calibration-only runtime at %s\n' "$(git -C "${repository_root}" rev-parse HEAD)"
}

start() {
    assert_clean_lineage
    assert_inputs
    compose up --detach --no-deps --force-recreate --wait api ai
    verify
}

run() {
    verify
    compose exec -T api php artisan evaluation:benchmark:run-threshold-calibration \
        --repository-commit="$(git -C "${repository_root}" rev-parse HEAD)"
}

replay() {
    verify
    local run_root="${runs_root}/${run_id}"
    compose run --rm --no-deps -T --env PYTHONPATH=/app ai \
        python /workspace/scripts/evaluation/compile_threshold_replay_input.py \
        --observations "/evaluation-runs/${run_id}/application-observations.json" \
        --output "/evaluation-runs/${run_id}/pre-threshold-replay-input.json"
    compose run --rm --no-deps -T --env PYTHONPATH=/app ai \
        python /workspace/scripts/evaluation/replay_evidence_thresholds.py \
        --input "/evaluation-runs/${run_id}/pre-threshold-replay-input.json" \
        --policy /evaluation/calibration/policy.json \
        --output "/evaluation-runs/${run_id}/threshold-replay.json" \
        --report "/evaluation-runs/${run_id}/threshold-replay.md"
    [[ -f "${run_root}/threshold-replay.json" ]]
}

case "${1:-}" in
    prepare) prepare "${2:-}" "${3:-}" "${4:-}" ;;
    start) start ;;
    verify) verify ;;
    run) run ;;
    replay) replay ;;
    *) usage ;;
esac
