#!/bin/bash
set -euo pipefail
cd /Users/davidoliver/Desktop/RAG
export LC_ALL=C LANG=C R28_CORPUS_ROOT=/private/tmp/dolved-r28-s03.zU3HX6/corpus/eval-corpus-v4-authoring
P=docs/evaluation/r28-s04/block1-planner-contract-diagnostic-20261114/live
REMOTE=/tmp/block1-planner-contract-diagnostic-20261114/live
D=(docker compose --env-file .env.r28-s03 -p dolved-r28-s03 -f compose.yaml -f compose.e2e.yaml -f compose.r28-s03.yaml)
verify() {
  test "$(git branch --show-current)" = codex/block1-extractive-hold-20261112
  test "$(git rev-parse HEAD)" = 13b7e09bb9cad2b5c6cb0aff600efeb8b217cdf4
  test "$(shasum -a 256 "$P/runtime-source.sha256" | cut -d' ' -f1)" = 7f20f123cb9e4af9567e0ea0240ebf715d54755ae2611dc66ecd6719756773af
  shasum -a 256 -c "$P/runtime-source.sha256" > "$P/preflight/runtime-latest.txt"
  test "$(shasum -a 256 docs/evaluation/r28-s04/block1-c2-live-20261114/released-input/questions-only.json | cut -d' ' -f1)" = 7603fc82fc2f95f9852f584f28cc6a3d4949a2a30e17b8ab3b56a52ce4d2899f
  (cd "$P" && shasum -a 256 -c governed-inputs.sha256) > "$P/preflight/harness-latest.txt"
  test "$(docker exec dolved-r28-s03-api-1 sha256sum /tmp/r28-planner-diagnostic-request.php | cut -d' ' -f1)" = "$(shasum -a 256 "$P/harness/request.php" | cut -d' ' -f1)"
  "${D[@]}" exec -T ai sh -c "cd '$REMOTE' && sha256sum -c governed-inputs.sha256" > "$P/preflight/container-harness-latest.txt"
  "${D[@]}" exec -T ai sha256sum -c < docs/evaluation/r28-s04/block1-c2-live-20261114/preflight/ai-mounted.sha256 > "$P/preflight/ai-mounted-latest.txt"
  "${D[@]}" exec -T api sha256sum -c < docs/evaluation/r28-s04/block1-c2-live-20261114/preflight/api-mounted.sha256 > "$P/preflight/api-mounted-latest.txt"
}
verify
mkdir "$P/authorization-consumed"
printf '{"run_id":"R28-PLANNER-CONTRACT-DIAGNOSTIC-20261114-01","consumed_at":"%s","cap_usd":10,"reservation_usd":3.25}\n' "$(date -u +%FT%TZ)" > "$P/authorization-consumed/marker.json"
for item in G2 G3; do
  verify
  test ! -e "$P/cases/$item.json"
  "${D[@]}" exec -T api php < docs/evaluation/r28-s04/block1-c2-live-20261114/harness/preflight.php > "$P/preflight/identity-$item.json"
  "${D[@]}" exec -T api php /tmp/r28-planner-diagnostic-request.php < "$P/inputs/$item.json" > "$P/requests/$item.json"
  docker cp "$P/requests/$item.json" "dolved-r28-s03-ai-1:$REMOTE/requests/$item.json"
  printf '{"item":"%s","event":"dispatch_started","at":"%s"}\n' "$item" "$(date -u +%FT%TZ)" >> "$P/dispatch.ndjson"
  set +e
  "${D[@]}" exec -T -e PYTHONPATH=/app -e C2_DIAGNOSTIC_AUTH=R28-PLANNER-CONTRACT-DIAGNOSTIC-20261114-01 ai /app/.venv/bin/python "$REMOTE/harness/dispatch.py" "$item"
  rc=$?
  set -e
  docker cp "dolved-r28-s03-ai-1:$REMOTE/cases/$item.json" "$P/cases/$item.json"
  docker cp "dolved-r28-s03-ai-1:$REMOTE/cases/$item.ledger.ndjson" "$P/cases/$item.ledger.ndjson"
  printf '{"item":"%s","event":"recorded","exit_code":%s,"at":"%s"}\n' "$item" "$rc" "$(date -u +%FT%TZ)" >> "$P/dispatch.ndjson"
  if [ "$rc" -ne 0 ]; then exit "$rc"; fi
done
verify
echo 'Two diagnostic planner invocations complete; no downstream execution.'
