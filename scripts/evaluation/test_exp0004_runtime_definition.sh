#!/usr/bin/env bash

set -euo pipefail

readonly repository_root="$(git rev-parse --show-toplevel)"
readonly override="${repository_root}/compose.exp0004.yaml"
readonly launcher="${repository_root}/scripts/evaluation/exp0004_runtime.sh"
readonly definition="${repository_root}/tests/evaluation/experiment-definitions/EXP-0004-rrf-k-5-controlled-engineering-experiment/runtime-lineage.json"

grep -Fq 'volumes: !override' "${override}"
grep -Fq './tests/evaluation/engineering-snapshots:/evaluation/engineering-snapshots:ro' "${override}"
grep -Fq './docs/evaluation/runs:/evaluation-runs' "${override}"
if grep -Fq './tests/evaluation:/evaluation:ro' "${override}"; then
    printf 'EXP-0004 must not expose the complete evaluation tree.\n' >&2
    exit 1
fi
grep -Fq 'status --porcelain --untracked-files=all' "${launcher}"
if grep -Fq -- '--dirty=' "${launcher}"; then
    printf 'EXP-0004 must not expose a caller-controlled dirty override.\n' >&2
    exit 1
fi
[[ "$(jq -er '.experiment_id' "${definition}")" == 'EXP-0004-rrf-k-5-controlled-engineering-experiment' ]]
[[ "$(jq -er '.retrieval_variable.control' "${definition}")" == '60' ]]
[[ "$(jq -er '.retrieval_variable.treatment' "${definition}")" == '5' ]]
[[ "$(jq -er '.engineering_snapshot.case_count' "${definition}")" == '42' ]]
[[ "$(jq -er '.engineering_snapshot.variant_count' "${definition}")" == '126' ]]

printf 'EXP-0004 runtime definition is physically engineering-only.\n'
