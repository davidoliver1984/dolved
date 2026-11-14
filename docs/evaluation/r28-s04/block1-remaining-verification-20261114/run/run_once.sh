#!/bin/bash
set -euo pipefail
cd /Users/davidoliver/Desktop/RAG
export LC_ALL=C LANG=C R28_CORPUS_ROOT=/private/tmp/dolved-r28-s03.zU3HX6/corpus/eval-corpus-v4-authoring
P=docs/evaluation/r28-s04/block1-remaining-verification-20261114/run
D=(docker compose --env-file .env.r28-s03 -p dolved-r28-s03 -f compose.yaml -f compose.e2e.yaml -f compose.r28-s03.yaml)
REMOTE=/tmp/R28-BLOCK1-REMAINING-20261114-01
verify() {
 test "$(git rev-parse HEAD)" = c28e69c15a1ae03f900fdb60ca579aafab8cb34f
 test "$(git branch --show-current)" = codex/block1-extractive-hold-20261112
 test "$(shasum -a 256 "$P/preflight/runtime-source.sha256" | cut -d' ' -f1)" = cbff39c23c64c7226ca3592248816884f2af8a5dcf7d5672f125d524078ba238
 shasum -a 256 -c "$P/preflight/runtime-source.sha256" > "$P/runtime-check-latest.txt"
 (cd "$P" && shasum -a 256 -c governed-inputs.sha256) > "$P/harness-check-latest.txt"
 "${D[@]}" exec -T api sha256sum -c < "$P/preflight/api-mounted.sha256" > "$P/api-mounted-latest.txt"
 "${D[@]}" exec -T ai sha256sum -c < "$P/preflight/ai-mounted.sha256" > "$P/ai-mounted-latest.txt"
}
verify
mkdir "$P/authorization-consumed"
date -u +%FT%TZ > "$P/authorization-consumed/at.txt"
shasum -a 256 "$P/authorization.json" > "$P/authorization-consumed/authorization.sha256"
for item in 17 18 19 20 21 22 23 24 25 26 27 28 29 30 31 32 G2 G3; do
 verify
 test ! -e "$P/cases/$item.json"
 "${D[@]}" run --rm --no-deps -v "$PWD:/workspace" ai /app/.venv/bin/python "/workspace/$P/harness/accounting.py" > "$P/budget-before-$item.json"
 "${D[@]}" exec -T api php < "$P/harness/preflight.php" > "$P/preflight/runtime-before-$item.json"
 "${D[@]}" run --rm --no-deps -v "$PWD:/workspace" ai /app/.venv/bin/python "/workspace/$P/harness/check_preflight.py" "$item"
 "${D[@]}" exec -T api sh -c "cd '$REMOTE' && sha256sum -c harness.sha256" > "$P/container-harness-$item.txt"
 printf '{"item":"%s","event":"started","at":"%s"}\n' "$item" "$(date -u +%FT%TZ)" >> "$P/dispatch.ndjson"
 set +e
 "${D[@]}" exec -T -e CONVERSATION_NEEDS_WORKSPACE_IDS=5323cd6b-8b7a-40f9-98b0-9413c75f643e api php "$REMOTE/dispatch.php" < "$P/inputs/$item.json" > "$P/cases/$item.json" 2> "$P/cases/$item.stderr.txt"
 rc=$?
 set -e
 docker cp "dolved-r28-s03-api-1:$REMOTE/ledger-$item.ndjson" "$P/cases/$item-ledger.ndjson"
 test "$rc" = 0
 "${D[@]}" run --rm --no-deps -v "$PWD:/workspace" ai /app/.venv/bin/python "/workspace/$P/harness/check_record.py" "$item"
 printf '{"item":"%s","event":"recorded","at":"%s"}\n' "$item" "$(date -u +%FT%TZ)" >> "$P/dispatch.ndjson"
done
verify
"${D[@]}" run --rm --no-deps -v "$PWD:/workspace" ai /app/.venv/bin/python "/workspace/$P/harness/accounting.py"
