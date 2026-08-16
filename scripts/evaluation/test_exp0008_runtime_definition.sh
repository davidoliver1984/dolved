#!/usr/bin/env bash

set -euo pipefail

readonly repository_root="$(git rev-parse --show-toplevel)"
readonly runtime="${repository_root}/scripts/evaluation/exp0008_runtime.sh"
readonly compose_file="${repository_root}/compose.exp0008.yaml"

if grep -Eq 'calibration|held-out|engineering-snapshots|planner-expectations|/evaluation:' "${compose_file}"; then
    printf 'EXP-0008 exposes a protected population.\n' >&2
    exit 1
fi
grep -Fq 'EXP-0008-v3-final-engineering-confirmation' "${runtime}"
grep -Fq 'preflight-exp-0008' "${runtime}"
grep -Fq 'evaluation:benchmark:run-exp-0008' "${runtime}"
grep -Fq -- '--planner-prompt-version adr-0022-v5' "${runtime}"
grep -Fq 'EXP-0008 cannot close without exactly 31 observations.' "${runtime}"
printf 'EXP-0008 execution runtime is physically engineering-only.\n'
