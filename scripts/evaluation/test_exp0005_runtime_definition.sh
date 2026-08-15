#!/usr/bin/env bash

set -euo pipefail

readonly repository_root="$(git rev-parse --show-toplevel)"
readonly override="${repository_root}/compose.exp0005.yaml"
readonly launcher="${repository_root}/scripts/evaluation/exp0005_runtime.sh"
readonly definition="${repository_root}/tests/evaluation/experiment-definitions/EXP-0005-adr0022-v2-consolidated-engineering-baseline/runtime-lineage.json"

grep -Fq 'volumes: !override' "${override}"
grep -Fq './tests/evaluation/engineering-snapshots/dolved-care-engineering/v2/corpus.json:/evaluation/engineering-snapshots/dolved-care-engineering/v2/corpus.json:ro' "${override}"
grep -Fq './tests/evaluation/planner-expectations/v2/engineering-expectations.json:/evaluation/planner-expectations/v2/engineering-expectations.json:ro' "${override}"
if grep -Eq 'calibration|held.out|tests/evaluation:/evaluation|engineering-snapshots:/evaluation/engineering-snapshots' "${override}"; then
    printf 'EXP-0005 must expose only its exact engineering files.\n' >&2
    exit 1
fi
grep -Fq 'status --porcelain --untracked-files=all' "${launcher}"
if grep -Fq -- '--dirty=' "${launcher}"; then
    printf 'EXP-0005 must not expose a dirty-lineage override.\n' >&2
    exit 1
fi
grep -Fq 'evaluation:benchmark:run-exp-0005' "${launcher}"
grep -Fq 'compile_application_benchmark_run.py' "${launcher}"
grep -Fq 'write_run_checksums.py' "${launcher}"
[[ "$(jq -er '.experiment_id' "${definition}")" == 'EXP-0005-adr0022-v2-consolidated-engineering-baseline' ]]
[[ "$(jq -er '.planner.fingerprint' "${definition}")" == '77d052cff157f679cc374b1fba86bb32790e17815051e2f24f12a97ceb751d30' ]]
[[ "$(jq -er '.retrieval.fusion.rrf_k' "${definition}")" == '5' ]]
[[ "$(jq -er '.retrieval.evidence_threshold' "${definition}")" == '0.337890625' ]]
[[ "$(jq -er '.engineering_snapshot.case_count' "${definition}")" == '42' ]]
[[ "$(jq -er '.engineering_snapshot.variant_count' "${definition}")" == '126' ]]

printf 'EXP-0005 definition and runtime are physically engineering-only.\n'
