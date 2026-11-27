#!/usr/bin/env bash

set -euo pipefail

readonly repository_root="$(git rev-parse --show-toplevel)"
readonly override="${repository_root}/tests/evaluation/runtime/compose.exp0006.yaml"
readonly launcher="${repository_root}/scripts/evaluation/exp0006_runtime.sh"
readonly definition="${repository_root}/tests/evaluation/experiment-definitions/EXP-0006-adr0022-v4-consolidated-engineering-confirmation/runtime-lineage.json"

grep -Fq 'volumes: !override' "${override}"
grep -Fq './tests/evaluation/engineering-snapshots/dolved-care-engineering/v2/corpus.json:/evaluation/engineering-snapshots/dolved-care-engineering/v2/corpus.json:ro' "${override}"
grep -Fq './tests/evaluation/planner-expectations/v2/engineering-expectations.json:/evaluation/planner-expectations/v2/engineering-expectations.json:ro' "${override}"
if grep -Eq 'calibration|held.out|tests/evaluation:/evaluation|engineering-snapshots:/evaluation/engineering-snapshots' "${override}"; then
    printf 'EXP-0006 must expose only its exact engineering files.\n' >&2
    exit 1
fi
grep -Fq -- '--project-directory "${repository_root}"' "${launcher}"
grep -Fq 'status --porcelain --untracked-files=all' "${launcher}"
if grep -Fq -- '--dirty=' "${launcher}"; then
    printf 'EXP-0006 must not expose a dirty-lineage override.\n' >&2
    exit 1
fi
grep -Fq 'evaluation:benchmark:run-exp-0006' "${launcher}"
grep -Fq 'compile_application_benchmark_run.py' "${launcher}"
grep -Fq 'write_run_checksums.py' "${launcher}"
[[ "$(jq -er '.experiment_id' "${definition}")" == 'EXP-0006-adr0022-v4-consolidated-engineering-confirmation' ]]
[[ "$(jq -er '.planner.prompt_version' "${definition}")" == 'adr-0022-v4' ]]
[[ "$(jq -er '.retrieval.fusion.rrf_k' "${definition}")" == '5' ]]
[[ "$(jq -er '.retrieval.evidence_threshold' "${definition}")" == '0.337890625' ]]
[[ "$(jq -er '.engineering_snapshot.case_count' "${definition}")" == '42' ]]
[[ "$(jq -er '.engineering_snapshot.variant_count' "${definition}")" == '126' ]]

printf 'EXP-0006 definition and runtime are physically engineering-only.\n'
