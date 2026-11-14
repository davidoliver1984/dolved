#!/bin/bash
set -euo pipefail
cd /Users/davidoliver/Desktop/RAG
export R28_CORPUS_ROOT=/private/tmp/dolved-r28-s03.zU3HX6/corpus/eval-corpus-v4-authoring
export LC_ALL=C LANG=C
P=docs/evaluation/r28-s04/block1-c1-live-20261114/continuation
D=(docker compose --env-file .env.r28-s03 -p dolved-r28-s03 -f compose.yaml -f compose.e2e.yaml -f compose.r28-s03.yaml)
REMOTE=/tmp/R28-C1-LIVE-20261114-01
verify() {
  test "$(git branch --show-current)" = codex/block1-extractive-hold-20261112
  test "$(git rev-parse HEAD)" = 44219e2df49181e80f0bcaad2495372917d6c4c1
  test "$(shasum -a 256 "$P/preflight/runtime-source.sha256" | cut -d' ' -f1)" = 4dde6707235e66ac707ed1cba90be8845738240dff9e4e6f2a37cc309989b338
  shasum -a 256 -c "$P/preflight/runtime-source.sha256" > "$P/preflight/runtime-check-latest.txt"
  (cd "$P" && shasum -a 256 -c governed-inputs.sha256) > "$P/preflight/harness-check-latest.txt"
  test "$(shasum -a 256 docs/evaluation/r28-s04/block1-c1-live-20261114/released-input/questions-only.json | cut -d' ' -f1)" = e1a54d05d0b688988a083596390ff9a9062b7a32c9352ea716a3a156145a9659
}
verify
# Exclusive one-use consumption. No resume path or overwrite of item results.
mkdir "$P/authorization-consumed"
printf '{"authorization_id":"R28-C1-AUTH-20261114-01","run_id":"R28-C1-LIVE-20261114-01","consumed_at":"%s"}\n' "$(date -u +%FT%TZ)" > "$P/authorization-consumed/marker.json"
for item in F1 F2 F3 F4 F5; do
  verify
  test ! -e "$P/cases/$item.json"
  "${D[@]}" run --rm --no-deps -v "$PWD:/workspace" ai /app/.venv/bin/python "/workspace/$P/harness/accounting.py" > "$P/preflight/budget-before-$item.json"
  "${D[@]}" exec -T api php < docs/evaluation/r28-s04/block1-c1-live-20261114/harness/preflight.php > "$P/preflight/runtime-before-$item.json"
  "${D[@]}" run --rm --no-deps -v "$PWD:/workspace" ai /app/.venv/bin/python "/workspace/$P/harness/check_preflight.py" "$item"
  "${D[@]}" exec -T api sh -c "cd '$REMOTE' && sha256sum -c harness.sha256" > "$P/preflight/container-harness-$item.txt"
  printf '{"item":"%s","event":"dispatch_started","at":"%s"}\n' "$item" "$(date -u +%FT%TZ)" >> "$P/dispatch.ndjson"
  set +e
  "${D[@]}" exec -T -e CONVERSATION_NEEDS_WORKSPACE_IDS=5323cd6b-8b7a-40f9-98b0-9413c75f643e api php "$REMOTE/dispatch.php" < "$P/inputs/$item.json" > "$P/cases/$item.json" 2> "$P/cases/$item.stderr.txt"
  rc=$?
  set -e
  docker cp "dolved-r28-s03-api-1:$REMOTE/ledger-$item.ndjson" "$P/cases/$item-ledger.ndjson"
  if [ "$rc" -ne 0 ]; then
    printf '{"item":"%s","event":"infrastructure_stop","exit_code":%s}\n' "$item" "$rc" >> "$P/dispatch.ndjson"
    exit "$rc"
  fi
  "${D[@]}" run --rm --no-deps -v "$PWD:/workspace" ai /app/.venv/bin/python "/workspace/$P/harness/check_record.py" "$item"
  printf '{"item":"%s","event":"recorded","at":"%s","sha256":"%s"}\n' "$item" "$(date -u +%FT%TZ)" "$(shasum -a 256 "$P/cases/$item.json" | cut -d' ' -f1)" >> "$P/dispatch.ndjson"
  echo "$item completed; ungraded"
done
verify
"${D[@]}" run --rm --no-deps -v "$PWD:/workspace" ai /app/.venv/bin/python "/workspace/$P/harness/accounting.py"
echo 'COMPLETED_UNGRADED five once-only items'
