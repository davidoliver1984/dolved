#!/usr/bin/env bash

set -euo pipefail

readonly repository_root="$(git rev-parse --show-toplevel)"
readonly compose_file="${repository_root}/compose.v3-engineering.yaml"
readonly runtime="${repository_root}/scripts/evaluation/v3_engineering_runtime.sh"

grep -Fq '/evaluation/engineering/corpus.json:ro' "${compose_file}"
grep -Fq '/evaluation/engineering/expectations.json:ro' "${compose_file}"
grep -Fq '/evaluation/engineering/source/documents:ro' "${compose_file}"
grep -Fq 'assert_isolated rag-platform-api-1' "${runtime}"
grep -Fq 'assert_isolated rag-platform-ai-1' "${runtime}"
grep -Fq 'assert_isolated rag-platform-publisher-1' "${runtime}"
grep -Fq 'assert_isolated rag-platform-worker-1' "${runtime}"

if grep -Eq '/evaluation/(calibration|held-out|engineering-snapshots|planner-expectations)' "${compose_file}"; then
    printf 'The V3 runtime definition exposes a protected split.\n' >&2
    exit 1
fi

if grep -Fq 'evaluation:v3-engineering:reset' "${runtime}"; then
    printf 'The V3 runtime unexpectedly exposes a reset path.\n' >&2
    exit 1
fi
