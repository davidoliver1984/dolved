#!/usr/bin/env bash

set -euo pipefail

readonly repository_root="$(git rev-parse --show-toplevel)"
readonly runtime="${repository_root}/scripts/evaluation/exp0007_runtime.sh"
readonly compose_file="${repository_root}/compose.exp0007.yaml"

bash -n "${runtime}"
if grep -Eq 'run-exp-0007|/evaluation/(calibration|held-out)|engineering-snapshots/dolved-care-engineering/v2' "${runtime}" "${compose_file}"; then
    printf 'EXP-0007 definition exposes execution or a protected population.\n' >&2
    exit 1
fi
grep -Fq '/evaluation/engineering/corpus.json:ro' "${compose_file}"
grep -Fq '/evaluation/engineering/expectations.json:ro' "${compose_file}"
grep -Fq 'preflight-exp-0007' "${runtime}"
printf 'EXP-0007 provider-free runtime definition is isolated.\n'
