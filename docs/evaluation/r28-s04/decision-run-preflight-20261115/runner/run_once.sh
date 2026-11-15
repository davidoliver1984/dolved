#!/bin/bash
# Same fixed Docker/dispatch boundary as C2 run_once.sh. No authority creation.
set -euo pipefail
cd /Users/davidoliver/Desktop/RAG
export LC_ALL=C LANG=C
export R28_CORPUS_ROOT=/private/tmp/dolved-r28-s03.zU3HX6/corpus/eval-corpus-v4-authoring
P=docs/evaluation/r28-s04/decision-run-preflight-20261115
R=docs/evaluation/r28-s04/decision-run-20261115/backend-run
REMOTE=/tmp/R28-DECISION-BACKEND-20261115-01
D=(docker compose --env-file .env.r28-s03 -p dolved-r28-s03 -f compose.yaml -f compose.r28-s03.yaml)
test -f "$R/authorization.json"
test ! -e "$R/authorization-consumed.json"
test ! -e "$R/accounting.json"
test ! -d "$R/items"
test -z "$(find "$R" -maxdepth 1 -name '*.started.json' -print)"
prepared=false
verify() {
  local n="$1" out="$R/integrity/$1"
  mkdir -p "$out"
  test "$(git branch --show-current)" = codex/block1-extractive-hold-20261112
  test "$(git rev-parse main)" = 348f09e053c8ad73676921c5c616cced812111d7
  git merge-base --is-ancestor b98569487046d1649003ccffa429c90678482c1c HEAD
  # Never start containers/dependencies; restrict every operation to isolated names.
  test "$(docker inspect dolved-r28-s03-api-1 --format '{{index .Config.Labels "com.docker.compose.project"}}')" = dolved-r28-s03
  test "$(docker inspect dolved-r28-s03-ai-1 --format '{{index .Config.Labels "com.docker.compose.project"}}')" = dolved-r28-s03
  docker ps --filter label=com.docker.compose.project=dolved-r28-s03 --format '{{.Label "com.docker.compose.service"}}' > "$out/running-services.txt"
  if grep -E '^(worker|queue|scheduler|publisher|role-bootstrap)' "$out/running-services.txt" >/dev/null; then return 1; fi
  docker exec -i dolved-r28-s03-api-1 sha256sum -c < "$P/api-mounted.sha256" > "$out/api-runtime.txt"
  docker exec -i dolved-r28-s03-ai-1 sha256sum -c < "$P/ai-mounted.sha256" > "$out/ai-runtime.txt"
  docker exec -i dolved-r28-s03-api-1 php < "$P/mechanical/context.php" > "$out/context.json"
  docker exec -i dolved-r28-s03-api-1 php < "$P/harness/preflight.php" > "$out/preflight.json"
  docker inspect dolved-r28-s03-api-1 dolved-r28-s03-ai-1 | "${D[@]}" run --rm --no-deps -T -v "$PWD:/workspace:ro" -v /Users/davidoliver/.dolved/r28-s03:/private-config:ro ai /app/.venv/bin/python "/workspace/$P/mechanical/verify_running.py" > "$out/running.json"
  test "$(stat -f '%Lp' /Users/davidoliver/.dolved/r28-s03)" = 700
  test "$(stat -f '%Lp' /Users/davidoliver/.dolved/r28-s03/production.override.json)" = 600
  printf '{"commit":"%s","branch":"%s","override_sha256":"%s"}\n' "$(git rev-parse HEAD)" "$(git branch --show-current)" "$(shasum -a 256 /Users/davidoliver/.dolved/r28-s03/production.override.json | cut -d' ' -f1)" > "$out/host.json"
  if [ "$prepared" = false ]; then
    docker exec dolved-r28-s03-api-1 mkdir "$REMOTE"
    for name in dispatch.php C1Capture.php verify_persistence.php; do
      docker cp "$P/harness/$name" "dolved-r28-s03-api-1:$REMOTE/$name"
    done
    prepared=true
  fi
  for name in dispatch.php C1Capture.php verify_persistence.php; do
    expected=$(shasum -a 256 "$P/harness/$name" | cut -d' ' -f1)
    printf '%s  %s/%s\n' "$expected" "$REMOTE" "$name"
  done | docker exec -i dolved-r28-s03-api-1 sha256sum -c > "$out/remote-harness.txt"
}
dispatch() {
  local item="$1" folder="$R/items/$1"
  test ! -e "$folder/raw.json"
  set +e
  docker exec -i -e CONVERSATION_NEEDS_WORKSPACE_IDS=5323cd6b-8b7a-40f9-98b0-9413c75f643e dolved-r28-s03-api-1 php "$REMOTE/dispatch.php" < "$folder/input.json" > "$folder/raw.json" 2>/dev/null
  code=$?
  docker cp "dolved-r28-s03-api-1:$REMOTE/ledger-$item.ndjson" "$folder/ledger.ndjson" 2>/dev/null
  copied=$?
  set -e
  printf '{"dispatch_exit":%s,"ledger_copy_exit":%s,"stderr":"not_retained_unrestricted_exception_risk"}\n' "$code" "$copied" > "$folder/transport.json"
  test "$code" = 0 && test "$copied" = 0
}
persist() {
  local folder="$R/items/$1"
  docker exec -i dolved-r28-s03-api-1 php "$REMOTE/verify_persistence.php" < "$folder/persistence-input.json" > "$folder/persistence.json" 2>/dev/null
}
# Pipe only fixed operation names/IDs; no shell commands or secrets cross it.
# Python runs in the existing image; no host Python and no mounted Docker socket.
IPC=$(mktemp -d /tmp/r28-decision-runner.XXXXXX)
mkfifo "$IPC/ack" "$IPC/commands"
exec 3<>"$IPC/ack"
"${D[@]}" run --rm --no-deps -T -v "$PWD:/workspace" ai /app/.venv/bin/python "/workspace/$P/runner/runner.py" < "$IPC/ack" > "$IPC/commands" &
controller=$!
trap 'kill "$controller" 2>/dev/null || true' EXIT
while read -r operation value; do
  case "$value" in *[!A-Za-z0-9_-]*|'') printf 'ERROR\n' >&3; break;; esac
  case "$operation" in
    VERIFY|DISPATCH|PERSIST)
      # Explicit subshell with errexit; preserve failures inside multi-command
      # callbacks instead of the shell's conditional-function errexit suppression.
      set +e
      ( set -e; case "$operation" in VERIFY) verify "$value";; DISPATCH) dispatch "$value";; PERSIST) persist "$value";; esac )
      result=$?
      set -e
      # Subshell cannot retain the one-time remote preparation state.
      if [ "$operation" = VERIFY ] && [ "$result" = 0 ]; then prepared=true; fi
      if [ "$result" = 0 ]; then printf 'OK\n' >&3; else printf 'ERROR\n' >&3; fi
      ;;
    DONE) wait "$controller"; trap - EXIT; exit 0;;
    HALT) wait "$controller" || true; trap - EXIT; exit 1;;
    *) printf 'ERROR\n' >&3; break;;
  esac
done < "$IPC/commands"
wait "$controller"
exit 1
