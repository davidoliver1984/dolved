#!/usr/bin/env bash

set -euo pipefail

readonly run_id='CAL-EXP-0002-v3-evidence-threshold-calibration'
readonly project_name='rag-platform'
readonly repository_root="$(git rev-parse --show-toplevel)"
readonly definition="${repository_root}/tests/evaluation/experiment-definitions/${run_id}/runtime-lineage.json"
readonly population_root="${repository_root}/tests/evaluation/calibration-populations/dolved-care-engineering/v3/v1"
readonly compose_override="${repository_root}/compose.cal-exp-0002.yaml"
readonly environment_file="${CALIBRATION_ENV_FILE:-${repository_root}/.env}"
readonly isolated_root="${CALIBRATION_ISOLATED_ROOT:-/private/tmp/rag-platform-cal-exp-0002}"
readonly calibration_snapshot="${isolated_root}/corpus.json"
readonly population_manifest="${isolated_root}/population-manifest.json"
readonly compatibility_result="${isolated_root}/composition-compatibility.json"
readonly authoring_review="${isolated_root}/authoring-review.json"
readonly independence_evidence="${isolated_root}/independence-evidence.json"
readonly taxonomy_evidence="${isolated_root}/taxonomy-evidence.json"
readonly provisioning_record="${isolated_root}/provisioning.json"
readonly input_lineage="${isolated_root}/input-lineage.json"
readonly runs_root="${CALIBRATION_RUNS_ROOT:-${repository_root}/docs/evaluation/runs}"

usage() {
    printf 'Usage: %s prepare <provisioning-record> | start | verify | run | replay\n' "$0" >&2
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

assert_hash() {
    local path=$1 expression=$2 label=$3
    [[ "$(sha256 "${path}")" == "$(jq -er "${expression}" "${definition}")" ]] || {
        printf '%s differs from the immutable CAL-EXP-0002 definition.\n' "${label}" >&2
        exit 1
    }
}

prepare() {
    local provisioning=${1:-}
    [[ -f "${provisioning}" ]] || usage
    assert_hash "${population_root}/corpus.json" '.population.corpus_sha256' population-corpus
    assert_hash "${population_root}/population-manifest.json" '.population.manifest_sha256' population-manifest
    assert_hash "${population_root}/composition-compatibility.json" '.compatibility.result_sha256' compatibility-result
    assert_hash "${population_root}/authoring-review.json" '.population.authoring_review_sha256' authoring-review
    assert_hash "${provisioning}" '.provisioning.record_sha256' provisioning-record
    [[ "$(jq -er '.compatible' "${population_root}/composition-compatibility.json")" == true ]]
    [[ "$(jq -er '.result_digest' "${population_root}/composition-compatibility.json")" == "$(jq -er '.compatibility.result_digest' "${definition}")" ]]
    mkdir -p "${isolated_root}"
    install -m 0444 "${population_root}/corpus.json" "${calibration_snapshot}"
    install -m 0444 "${population_root}/population-manifest.json" "${population_manifest}"
    install -m 0444 "${population_root}/composition-compatibility.json" "${compatibility_result}"
    install -m 0444 "${population_root}/authoring-review.json" "${authoring_review}"
    install -m 0444 "${provisioning}" "${provisioning_record}"
    jq '.independence' "${population_manifest}" >"${independence_evidence}"
    jq '.benchmark_taxonomy' "${population_manifest}" >"${taxonomy_evidence}"
    chmod 0444 "${independence_evidence}" "${taxonomy_evidence}"
    jq -n \
        --arg repository_commit "$(git -C "${repository_root}" rev-parse HEAD)" \
        --arg corpus_sha256 "$(sha256 "${calibration_snapshot}")" \
        --arg population_manifest_sha256 "$(sha256 "${population_manifest}")" \
        --arg compatibility_result_sha256 "$(sha256 "${compatibility_result}")" \
        --arg provisioning_sha256 "$(sha256 "${provisioning_record}")" \
        '{schema_version:"v1", repository_commit:$repository_commit, corpus_sha256:$corpus_sha256, population_manifest_sha256:$population_manifest_sha256, compatibility_result_sha256:$compatibility_result_sha256, provisioning_sha256:$provisioning_sha256}' \
        >"${input_lineage}"
    chmod 0444 "${input_lineage}"
}

assert_inputs() {
    [[ -f "${calibration_snapshot}" && -f "${population_manifest}" && -f "${compatibility_result}" && -f "${provisioning_record}" && -f "${input_lineage}" ]]
    [[ "$(sha256 "${calibration_snapshot}")" == "$(jq -er '.corpus_sha256' "${input_lineage}")" ]]
    [[ "$(sha256 "${population_manifest}")" == "$(jq -er '.population_manifest_sha256' "${input_lineage}")" ]]
    [[ "$(sha256 "${compatibility_result}")" == "$(jq -er '.compatibility_result_sha256' "${input_lineage}")" ]]
    [[ "$(sha256 "${provisioning_record}")" == "$(jq -er '.provisioning_sha256' "${input_lineage}")" ]]
    [[ "$(jq -er '.benchmark.version' "${calibration_snapshot}")" == '3' ]]
    [[ "$(jq -er '.split.name' "${calibration_snapshot}")" == 'threshold_calibration' ]]
    [[ "$(jq -er '.cases | length' "${calibration_snapshot}")" == '44' ]]
    [[ "$(jq -er '[.cases[].variants[]] | length' "${calibration_snapshot}")" == '132' ]]
    if grep -Eq 'engineering_tuning|held_out_acceptance' "${calibration_snapshot}"; then
        printf 'The isolated CAL-EXP-0002 corpus exposes a non-calibration split.\n' >&2
        exit 1
    fi
}

compose() {
    CALIBRATION_SNAPSHOT="${calibration_snapshot}" \
    CALIBRATION_POPULATION_MANIFEST="${population_manifest}" \
    CALIBRATION_COMPATIBILITY_RESULT="${compatibility_result}" \
    CALIBRATION_INDEPENDENCE_EVIDENCE="${independence_evidence}" \
    CALIBRATION_TAXONOMY_EVIDENCE="${taxonomy_evidence}" \
    CALIBRATION_AUTHORING_REVIEW="${authoring_review}" \
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
        assert_mount "${container}" /evaluation/calibration/population-manifest.json "${population_manifest}"
        assert_mount "${container}" /evaluation/calibration/composition-compatibility.json "${compatibility_result}"
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
    local run_root="${runs_root}/${run_id}"
    [[ ! -e "${run_root}/application-observations.json" ]] || {
        printf '%s already has provider observations; refusing a second provider pass.\n' "${run_id}" >&2
        exit 1
    }
    mkdir -p "${run_root}"
    install -m 0444 "${definition}" "${run_root}/run-definition.json"
    install -m 0444 "${input_lineage}" "${run_root}/input-lineage.json"
    compose exec -T api php artisan evaluation:benchmark:run-cal-exp-0002 \
        --repository-commit="$(git -C "${repository_root}" rev-parse HEAD)"
}

replay() {
    verify
    local run_root="${runs_root}/${run_id}"
    [[ -f "${run_root}/application-observations.json" ]]
    compose run --rm --no-deps -T --env PYTHONPATH=/app ai \
        python /workspace/scripts/evaluation/compile_threshold_replay_input.py \
        --observations "/evaluation-runs/${run_id}/application-observations.json" \
        --run-id "${run_id}" --expected-cases 44 --expected-variants 132 \
        --output "/evaluation-runs/${run_id}/pre-threshold-replay-input.json"
    compose run --rm --no-deps -T --env PYTHONPATH=/app ai \
        python /workspace/scripts/evaluation/compile_threshold_execution_evidence.py \
        --input "/evaluation-runs/${run_id}/pre-threshold-replay-input.json" \
        --output "/evaluation-runs/${run_id}/threshold-execution-evidence.json"
    compose run --rm --no-deps -T --env PYTHONPATH=/app ai \
        python /workspace/scripts/evaluation/validate_calibration_compatibility.py \
        --snapshot /evaluation/calibration/corpus.json \
        --threshold-policy /evaluation/calibration/policy.json \
        --requirements /evaluation/calibration/compatibility-requirements.json \
        --population-specification /evaluation/calibration/population-specification.json \
        --independence-evidence /evaluation/calibration/independence-evidence.json \
        --benchmark-taxonomy /evaluation/calibration/taxonomy-evidence.json \
        --authoring-review-evidence /evaluation/calibration/authoring-review.json \
        --expected-compatibility-policy-sha256 "$(jq -er '.compatibility.requirements_sha256' "${definition}")" \
        --threshold-execution-evidence "/evaluation-runs/${run_id}/threshold-execution-evidence.json" \
        --observations "/evaluation-runs/${run_id}/application-observations.json" \
        --population-manifest "/evaluation-runs/${run_id}/post-provider-population-manifest.json" \
        --compatibility-result "/evaluation-runs/${run_id}/post-provider-compatibility.json"
    compose run --rm --no-deps -T --env PYTHONPATH=/app ai \
        python /workspace/scripts/evaluation/replay_evidence_thresholds.py \
        --input "/evaluation-runs/${run_id}/pre-threshold-replay-input.json" \
        --policy /evaluation/calibration/policy.json \
        --output "/evaluation-runs/${run_id}/threshold-replay.json" \
        --report "/evaluation-runs/${run_id}/threshold-replay.md"
    [[ -f "${run_root}/threshold-replay.json" ]]
    (
        cd "${run_root}"
        openssl dgst -sha256 application-observations.json input-lineage.json post-provider-compatibility.json post-provider-population-manifest.json pre-threshold-replay-input.json run-definition.json threshold-execution-evidence.json threshold-replay.json threshold-replay.md > checksums.txt
    )
}

case "${1:-}" in
    prepare) prepare "${2:-}" ;;
    start) start ;;
    verify) verify ;;
    run) run ;;
    replay) replay ;;
    *) usage ;;
esac
