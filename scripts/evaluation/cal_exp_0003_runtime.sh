#!/usr/bin/env bash

set -euo pipefail

readonly repository_root="$(git rev-parse --show-toplevel)"
export CALIBRATION_RUN_ID='CAL-EXP-0003-v3-post-planner-hardening-calibration'
export CALIBRATION_DEFINITION="${repository_root}/tests/evaluation/experiment-definitions/${CALIBRATION_RUN_ID}/runtime-lineage.json"
export CALIBRATION_COMPOSE_OVERRIDE="${repository_root}/compose.cal-exp-0002.yaml"
export CALIBRATION_ARTISAN_COMMAND='evaluation:benchmark:run-cal-exp-0003'
export CALIBRATION_ISOLATED_ROOT="${CALIBRATION_ISOLATED_ROOT:-/private/tmp/rag-platform-cal-exp-0003}"

exec "${repository_root}/scripts/evaluation/cal_exp_0002_runtime.sh" "$@"
