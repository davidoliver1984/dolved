#!/usr/bin/env bash

set -euo pipefail

readonly repository_root="$(git rev-parse --show-toplevel)"
readonly launcher="${repository_root}/scripts/evaluation/cal_exp_0003_runtime.sh"
readonly shared_launcher="${repository_root}/scripts/evaluation/cal_exp_0002_runtime.sh"
readonly definition="${repository_root}/tests/evaluation/experiment-definitions/CAL-EXP-0003-v3-post-planner-hardening-calibration/runtime-lineage.json"
readonly override="${repository_root}/compose.cal-exp-0002.yaml"

jq -e '.experiment_id == "CAL-EXP-0003-v3-post-planner-hardening-calibration"' "${definition}" >/dev/null
jq -e '.predecessor.disposition == "immutable_failed_closed"' "${definition}" >/dev/null
jq -e '.population.case_count == 44 and .population.variant_count == 132' "${definition}" >/dev/null
jq -e '.compatibility.compatible == true' "${definition}" >/dev/null
jq -e '.planner.adapter_version == "structured-chat-v3"' "${definition}" >/dev/null
jq -e '.planner.fingerprint == "114789559d7032cefb4e93d1134ce3a4e2234a0db9c26048940cbb1d095758bd"' "${definition}" >/dev/null
jq -e '.candidate_pipeline == {dense_candidate_k:40,sparse_candidate_k:40,fusion_candidate_k:15,reranker_candidate_k:15,factual_control_threshold:0.337890625,final_evidence_k:5}' "${definition}" >/dev/null
grep -Fq "CALIBRATION_ARTISAN_COMMAND='evaluation:benchmark:run-cal-exp-0003'" "${launcher}"
grep -Fq 'requires HEAD to equal the pushed origin/main commit' "${shared_launcher}"
grep -Fq 'already has provider observations; refusing a second provider pass' "${shared_launcher}"
grep -Fq 'test ! -e /evaluation/held-out' "${shared_launcher}"
grep -Fq 'test ! -e /evaluation/engineering-snapshots' "${shared_launcher}"
grep -Fq ':/evaluation/calibration/population-manifest.json:ro' "${override}"
grep -Fq ':/evaluation/calibration/composition-compatibility.json:ro' "${override}"
