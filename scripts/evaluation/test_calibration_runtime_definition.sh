#!/usr/bin/env bash

set -euo pipefail

readonly repository_root="$(git rev-parse --show-toplevel)"
readonly override="${repository_root}/compose.calibration.yaml"
readonly launcher="${repository_root}/scripts/evaluation/calibration_runtime.sh"
readonly definition="${repository_root}/tests/evaluation/experiment-definitions/CAL-EXP-0001-evidence-threshold-calibration/runtime-lineage.json"

grep -Fq 'volumes: !override' "${override}"
grep -Fq '${CALIBRATION_SNAPSHOT:?Prepare the isolated calibration snapshot}:/evaluation/calibration/corpus.json:ro' "${override}"
grep -Fq '${CALIBRATION_EXPECTATIONS:?Prepare the isolated calibration expectations}:/evaluation/calibration/expectations.json:ro' "${override}"
grep -Fq './tests/evaluation/policies/evidence-threshold-calibration/v1/policy.json:/evaluation/calibration/policy.json:ro' "${override}"
if grep -Eq 'engineering-snapshots|engineering-expectations|held.out|benchmarks/dolved|tests/evaluation:/evaluation' "${override}"; then
    printf 'Calibration runtime must not mount engineering, held-out or broad evaluation paths.\n' >&2
    exit 1
fi
grep -Fq 'test ! -e /evaluation/held-out' "${launcher}"
grep -Fq 'test ! -e /evaluation/benchmarks' "${launcher}"
grep -Fq 'test ! -e /evaluation/corpus' "${launcher}"
grep -Fq 'test ! -e /evaluation/observations' "${launcher}"
grep -Fq 'test ! -e /evaluation/hybrid' "${launcher}"
grep -Fq 'test ! -e /evaluation/engineering-snapshots' "${launcher}"
grep -Fq 'test ! -e /evaluation/planner-expectations' "${launcher}"
grep -Fq 'status --porcelain --untracked-files=all' "${launcher}"
grep -Fq 'every-distinct-score-plus-exact-control-plus-above-maximum' "${definition}"
[[ "$(jq -er '.split.name' "${definition}")" == 'threshold_calibration' ]]
[[ "$(jq -er '.split.case_count' "${definition}")" == '28' ]]
[[ "$(jq -er '.split.variant_count' "${definition}")" == '84' ]]
[[ "$(jq -er '.candidate_pipeline.rrf_k' "${definition}")" == '5' ]]
[[ "$(jq -er '.candidate_pipeline.factual_control_threshold' "${definition}")" == '0.337890625' ]]
[[ "$(jq -er '.execution.provider_passes' "${definition}")" == '1' ]]

printf 'Calibration runtime definition is physically calibration-only.\n'
