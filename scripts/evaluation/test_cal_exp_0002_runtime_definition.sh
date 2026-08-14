#!/usr/bin/env bash

set -euo pipefail

readonly repository_root="$(git rev-parse --show-toplevel)"
readonly launcher="${repository_root}/scripts/evaluation/cal_exp_0002_runtime.sh"
readonly definition="${repository_root}/tests/evaluation/experiment-definitions/CAL-EXP-0002-v3-evidence-threshold-calibration/runtime-lineage.json"
readonly override="${repository_root}/compose.cal-exp-0002.yaml"

jq -e '.experiment_id == "CAL-EXP-0002-v3-evidence-threshold-calibration"' "${definition}" >/dev/null
jq -e '.population.case_count == 44 and .population.variant_count == 132' "${definition}" >/dev/null
jq -e '.compatibility.result_digest | length == 64' "${definition}" >/dev/null
jq -e '.compatibility.requirements_sha256 | length == 64' "${definition}" >/dev/null
grep -Fq 'requires HEAD to equal the pushed origin/main commit' "${launcher}"
grep -Fq 'already has provider observations; refusing a second provider pass' "${launcher}"
grep -Fq 'test ! -e /evaluation/held-out' "${launcher}"
grep -Fq 'test ! -e /evaluation/engineering-snapshots' "${launcher}"
grep -Fq ':/evaluation/calibration/population-manifest.json:ro' "${override}"
grep -Fq ':/evaluation/calibration/composition-compatibility.json:ro' "${override}"
