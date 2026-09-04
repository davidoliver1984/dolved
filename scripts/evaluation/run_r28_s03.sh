#!/usr/bin/env bash
set -euo pipefail

repository_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$repository_root"

runtime_root="$(mktemp -d /tmp/dolved-r28-s03.XXXXXX)"
corpus_parent="$runtime_root/corpus"
mkdir -p "$corpus_parent"
tar -xzf tests/evaluation/corpus/dolved-care-v4/v1/checkpoint-19-application-evidence-corrections.tar.gz -C "$corpus_parent"
corpus_root="$corpus_parent/eval-corpus-v4-authoring"
export R28_CORPUS_ROOT="$corpus_root"

python3 scripts/evaluation/preflight_r28_s03.py

compose=(docker compose --env-file .env.r28-s03 -p dolved-r28-s03 -f compose.yaml -f compose.e2e.yaml -f compose.r28-s03.yaml)
services=(postgres qdrant localstack mailpit ai api publisher worker conversation-worker)

"${compose[@]}" up --detach --build --wait --wait-timeout "${WAIT_TIMEOUT:-240}" "${services[@]}"
"${compose[@]}" run --rm migrator php artisan migrate --force
"${compose[@]}" exec -T api php artisan e2e:provision-retrieval > "$runtime_root/retrieval-provisioning.json"
embedding_space="$(jq -r .embedding_space_generation_id "$runtime_root/retrieval-provisioning.json")"
sparse_space="$(jq -r .sparse_space_generation_id "$runtime_root/retrieval-provisioning.json")"
"${compose[@]}" exec -T ai uv run python /workspace/scripts/evaluation/provision_r28_s03_vector_space.py \
  --profile /contracts/testing/deterministic-retrieval-profile-v1.json \
  --embedding-space-generation-id "$embedding_space" \
  --sparse-space-generation-id "$sparse_space" \
  > "$runtime_root/vector-space-provisioning.json"

"${compose[@]}" exec -T api php artisan e2e:bootstrap --run r28-s03 --scenario primary > "$runtime_root/primary.json"
"${compose[@]}" exec -T api php artisan e2e:bootstrap --run r28-s03 --scenario foreign > "$runtime_root/foreign.json"
"${compose[@]}" exec -T api php artisan e2e:bootstrap --run r28-s03 --scenario injection > "$runtime_root/injection.json"

primary_workspace="$(jq -r .workspace_public_id "$runtime_root/primary.json")"
foreign_workspace="$(jq -r .workspace_public_id "$runtime_root/foreign.json")"
injection_workspace="$(jq -r .workspace_public_id "$runtime_root/injection.json")"
"${compose[@]}" exec -T api php artisan e2e:provision-organisation --workspace "$primary_workspace" --manifest /r28-corpus/organisation.json > "$runtime_root/primary-locations.json"
"${compose[@]}" exec -T api php artisan e2e:provision-organisation --workspace "$foreign_workspace" --manifest /r28-corpus/foreign-tenant/organisation.json > "$runtime_root/foreign-locations.json"

mkdir -p docs/evaluation/r28-s03/run
python3 scripts/evaluation/materialise_r28_s03.py \
  --corpus-root "$corpus_root" \
  --password 'Dolved-R28-S03-Only-42!' \
  --primary-identity "$runtime_root/primary.json" \
  --foreign-identity "$runtime_root/foreign.json" \
  --injection-identity "$runtime_root/injection.json" \
  --primary-locations "$runtime_root/primary-locations.json" \
  --output "$runtime_root/materialisation-result.json"

"${compose[@]}" exec -T api php artisan retrieval:rebuild-hybrid-corpus \
  "$primary_workspace" "$sparse_space" \
  > docs/evaluation/r28-s03/run/primary-hybrid-rebuild.txt
"${compose[@]}" exec -T api php artisan retrieval:rebuild-hybrid-corpus \
  "$foreign_workspace" "$sparse_space" \
  > docs/evaluation/r28-s03/run/foreign-hybrid-rebuild.txt
"${compose[@]}" exec -T api php artisan retrieval:rebuild-hybrid-corpus \
  "$injection_workspace" "$sparse_space" \
  > docs/evaluation/r28-s03/run/injection-hybrid-rebuild.txt

primary_actor="$(jq -r .user_public_id "$runtime_root/primary.json")"
foreign_actor="$(jq -r .user_public_id "$runtime_root/foreign.json")"
injection_actor="$(jq -r .user_public_id "$runtime_root/injection.json")"
"${compose[@]}" exec -T api php artisan e2e:apply-frozen-governance \
  --workspace "$primary_workspace" --actor "$primary_actor" \
  --manifest /r28-corpus/source-manifest.json \
  > docs/evaluation/r28-s03/run/primary-governance.json
"${compose[@]}" exec -T api php artisan e2e:apply-frozen-governance \
  --workspace "$foreign_workspace" --actor "$foreign_actor" \
  --manifest /r28-corpus/foreign-tenant/source-manifest.json \
  > docs/evaluation/r28-s03/run/foreign-governance.json
"${compose[@]}" exec -T api php artisan e2e:apply-frozen-governance \
  --workspace "$injection_workspace" --actor "$injection_actor" \
  --manifest /r28-corpus/prompt-injection-pack/source-manifest.json \
  > docs/evaluation/r28-s03/run/injection-governance.json

mv "$runtime_root/materialisation-result.json" docs/evaluation/r28-s03/run/materialisation-result.json
mv "$runtime_root/retrieval-provisioning.json" docs/evaluation/r28-s03/run/retrieval-provisioning.json
mv "$runtime_root/vector-space-provisioning.json" docs/evaluation/r28-s03/run/vector-space-provisioning.json

"${compose[@]}" ps --format json > docs/evaluation/r28-s03/run/runtime-services.json
sha256sum docs/evaluation/r28-s03/run/materialisation-result.json \
  docs/evaluation/r28-s03/run/primary-governance.json \
  docs/evaluation/r28-s03/run/foreign-governance.json \
  docs/evaluation/r28-s03/run/injection-governance.json \
  docs/evaluation/r28-s03/run/retrieval-provisioning.json \
  docs/evaluation/r28-s03/run/vector-space-provisioning.json \
  docs/evaluation/r28-s03/run/primary-hybrid-rebuild.txt \
  docs/evaluation/r28-s03/run/foreign-hybrid-rebuild.txt \
  docs/evaluation/r28-s03/run/injection-hybrid-rebuild.txt \
  docs/evaluation/r28-s03/run/runtime-services.json \
  > docs/evaluation/r28-s03/run/checksums.sha256

printf 'R28-S03 materialisation completed. Runtime retained for verification: %s\n' "$runtime_root"
