#!/bin/bash
set -euo pipefail
cd /Users/davidoliver/Desktop/RAG
export LC_ALL=C LANG=C R28_CORPUS_ROOT=/private/tmp/dolved-r28-s03.zU3HX6/corpus/eval-corpus-v4-authoring
P=docs/evaluation/r28-s04/block1-temporal-association-diagnostic-20261114
D=(docker compose --env-file .env.r28-s03 -p dolved-r28-s03 -f compose.yaml -f compose.e2e.yaml -f compose.r28-s03.yaml)
REMOTE=/tmp/R28-TEMPORAL-ASSOCIATION-DIAGNOSTIC-20261114-01
verify() {
 test "$(git rev-parse HEAD)" = "$(jq -r .head "$P/authorization.json")"
 test "$(git branch --show-current)" = codex/block1-extractive-hold-20261112
 test "$(git rev-parse main)" = "$(jq -r .main "$P/authorization.json")"
 test "$(shasum -a 256 "$P/preflight/runtime-source.sha256" | cut -d' ' -f1)" = "$(jq -r .runtime "$P/authorization.json")"
 test "$(shasum -a 256 "$P/governed-inputs.sha256" | cut -d' ' -f1)" = "$(jq -r .manifest_sha256 "$P/authorization.json")"
 shasum -a 256 -c "$P/preflight/runtime-source.sha256" > "$P/runtime-check-latest.txt"
 (cd "$P" && shasum -a 256 -c governed-inputs.sha256) > "$P/harness-check-latest.txt"
 "${D[@]}" exec -T api sha256sum -c < "$P/preflight/api-mounted.sha256" > "$P/api-mounted-latest.txt"
 "${D[@]}" exec -T ai sha256sum -c < "$P/preflight/ai-mounted.sha256" > "$P/ai-mounted-latest.txt"
 "${D[@]}" exec -T ai /app/.venv/bin/python -c 'from app.settings import get_settings; s=get_settings(); assert s.evaluation_capture_enabled and s.evaluation_capture_destination=="/evaluation-evidence/capture"'
}
verify
test "$(git ls-remote origin refs/heads/codex/block1-extractive-hold-20261112 | cut -f1)" = "$(jq -r .head "$P/authorization.json")"
mkdir "$P/authorization-consumed"
date -u +%FT%TZ > "$P/authorization-consumed/at.txt"
shasum -a 256 "$P/authorization.json" > "$P/authorization-consumed/authorization.sha256"
for item in G3 17 25; do
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
