#!/usr/bin/env bash

set -euo pipefail

ROOT=$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)
OUTPUT=${EVALUATION_CURRENT_OUTPUT:-/tmp/rag-platform-evaluation-current}
COMPOSE=(docker compose --env-file "$ROOT/.env.e2e" --project-name dolved-evaluation-current --file "$ROOT/compose.yaml" --file "$ROOT/compose.e2e.yaml" --file "$ROOT/compose.evaluation-current.yaml")

cd "$ROOT"

HEAD=$(git rev-parse HEAD)
REMOTE=$(git rev-parse origin/main)
if [[ "$HEAD" != "$REMOTE" ]]; then
  echo "Current retrieval candidate requires HEAD == origin/main." >&2
  exit 1
fi
if [[ -n $(git status --porcelain --untracked-files=no) ]]; then
  echo "Current retrieval candidate requires a tracked-clean worktree." >&2
  exit 1
fi

mkdir -p "$OUTPUT"
CONFIG=$(mktemp)
trap 'rm -f "$CONFIG"; "${COMPOSE[@]}" down --volumes --remove-orphans >/dev/null 2>&1 || true' EXIT
"${COMPOSE[@]}" config --format json >"$CONFIG"
python3 "$ROOT/scripts/evaluation/verify_current_retrieval_topology.py" "$CONFIG"

"${COMPOSE[@]}" up --detach --wait postgres qdrant
"${COMPOSE[@]}" run --rm migrator php artisan migrate --force
"${COMPOSE[@]}" run --rm --no-deps \
  --volume "$OUTPUT:/output" \
  api php artisan evaluation:resolve-current-eligibility \
    --run=r22-s03-current-retrieval-candidate \
    --repository-commit="$HEAD" \
    --document-catalog=/evaluation/document-catalog.json \
    --organisation=/evaluation/organisation.json \
    --plans=/evaluation/plans.json \
    --schema=/evaluation/eligibility-artifact.schema.json \
    --output=/output/eligibility-artifact.json
"${COMPOSE[@]}" run --rm --no-deps \
  --volume "$OUTPUT:/output" \
  --env PYTHONPATH=/app \
  ai python /evaluation/run.py retrieval-current \
    --corpus /evaluation/corpus.json \
    --policy /evaluation/policy.json \
    --plan-catalogue /evaluation/plans.json \
    --document-catalog /evaluation/document-catalog.json \
    --organisation /evaluation/organisation.json \
    --source-root /evaluation/source-root \
    --source-checksums /evaluation/source-checksums.json \
    --eligibility-artifact /output/eligibility-artifact.json \
    --repository-commit "$HEAD" \
    --output /output/current-run.json \
    --candidate-output /output/experiment-result.json \
    --report-output /output/candidate-report.md

echo "Provider-free current retrieval candidate written to $OUTPUT"
