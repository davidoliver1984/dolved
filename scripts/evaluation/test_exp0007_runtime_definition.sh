#!/usr/bin/env bash

set -euo pipefail

readonly repository_root="$(git rev-parse --show-toplevel)"
readonly runtime="${repository_root}/scripts/evaluation/exp0007_runtime.sh"
readonly compose_file="${repository_root}/compose.exp0007.yaml"

bash -n "${runtime}"
if grep -Eq '/evaluation/(calibration|held-out)|engineering-snapshots/dolved-care-engineering/v2' "${runtime}" "${compose_file}"; then
    printf 'EXP-0007 exposes a protected population.\n' >&2
    exit 1
fi
grep -Fq '/evaluation/engineering/corpus.json:ro' "${compose_file}"
grep -Fq '/evaluation/engineering/expectations.json:ro' "${compose_file}"
grep -Fq 'preflight-exp-0007' "${runtime}"
grep -Fq 'evaluation:benchmark:run-exp-0007' "${runtime}"
grep -Fq -- '--planner-expectations /evaluation/engineering/expectations.json' "${runtime}"
grep -Fq 'EXP-0007 cannot close without exactly 31 observations.' "${runtime}"
[[ "$(grep -Fc 'compile_application_benchmark_run.py' "${runtime}")" == '2' ]]
printf 'EXP-0007 execution runtime is physically engineering-only.\n'
