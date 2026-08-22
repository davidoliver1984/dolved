#!/usr/bin/env bash
set -euo pipefail

repository_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$repository_root"

./scripts/e2e/preflight.sh
compose=(docker compose --env-file .env.e2e -p dolved-e2e -f compose.yaml -f compose.e2e.yaml)
services=(postgres qdrant localstack mailpit ai api publisher worker web)

"${compose[@]}" up --detach --build --wait --wait-timeout "${WAIT_TIMEOUT:-180}" "${services[@]}"
"${compose[@]}" exec -T api php artisan migrate --force
"${compose[@]}" exec -T api php artisan e2e:provision-retrieval

set +e
(
  cd tests/end-to-end
  npm ci
  npx playwright test
)
status=$?
set -e

if [[ "$status" -ne 0 ]]; then
  mkdir -p tests/end-to-end/test-results
  "${compose[@]}" logs --no-color web api ai worker publisher postgres qdrant localstack \
    > tests/end-to-end/test-results/services.log 2>&1 || true
  printf 'E2E failed; dolved-e2e is preserved. Use make test-e2e-inspect or make test-e2e-clean.\n' >&2
  exit "$status"
fi

"${compose[@]}" down --volumes --remove-orphans
printf 'E2E passed and the isolated dolved-e2e resources were removed.\n'
