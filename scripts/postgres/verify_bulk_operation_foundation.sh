#!/usr/bin/env bash
set -euo pipefail

repository_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$repository_root"

database="${POSTGRES_DB:-rag_platform}"
admin_user="${POSTGRES_USER:-rag_platform}"
admin_password="${POSTGRES_PASSWORD:-local-development-only}"

psql_admin() {
  docker compose exec -T --env PGPASSWORD="$admin_password" postgres \
    psql --no-psqlrc --set=ON_ERROR_STOP=1 --tuples-only --no-align \
    --username "$admin_user" --dbname "$database" "$@"
}

required_constraints="$(psql_admin --command="
SELECT count(*)
FROM unnest(ARRAY[
  'bulk_operations_actor_check',
  'bulk_operations_type_check',
  'bulk_operations_status_check',
  'bulk_operations_selection_check',
  'bulk_operations_digest_check',
  'bulk_operation_items_parent_foreign',
  'bulk_operation_items_target_shape_check',
  'bulk_operation_items_exclusion_reason_check',
  'bulk_operation_items_terminal_reason_check',
  'bulk_operation_items_truth_check',
  'bulk_operation_items_result_shape_check',
  'bulk_operation_items_subordinate_kind_check',
  'bulk_operation_items_incorporated_attempt_foreign',
  'bulk_attempts_shape_check',
  'bulk_attempts_not_applied_reason_check',
  'bulk_attempts_subordinate_shape_check',
  'bulk_subordinate_transition_category_check',
  'bulk_operation_items_audit_event_foreign'
]) expected(name)
WHERE EXISTS (SELECT 1 FROM pg_constraint WHERE conname = expected.name AND convalidated);
")"
[[ "$required_constraints" == '18' ]] || {
  printf 'Expected 18 validated bulk-operation constraints, found %s.\n' "$required_constraints" >&2
  exit 1
}

required_indexes="$(psql_admin --command="
SELECT count(*)
FROM unnest(ARRAY[
  'bulk_operation_items_family_target_unique',
  'bulk_operation_items_document_target_unique',
  'bulk_operation_items_import_target_unique',
  'bulk_operation_item_attempts_one_open_unique'
]) expected(name)
WHERE EXISTS (SELECT 1 FROM pg_indexes WHERE indexname = expected.name);
")"
[[ "$required_indexes" == '4' ]] || {
  printf 'Expected four bulk-operation partial unique indexes, found %s.\n' "$required_indexes" >&2
  exit 1
}

required_triggers="$(psql_admin --command="
SELECT count(*)
FROM unnest(ARRAY[
  'bulk_operations_update_guard',
  'bulk_operation_items_retirement_guard',
  'bulk_operation_items_target_workspace_guard',
  'bulk_operation_items_update_guard',
  'bulk_operation_items_incorporation_guard',
  'bulk_attempts_update_guard',
  'bulk_subordinate_transitions_immutable_guard',
  'bulk_operation_audit_immutable_guard'
]) expected(name)
WHERE EXISTS (SELECT 1 FROM pg_trigger WHERE tgname = expected.name AND NOT tgisinternal);
")"
[[ "$required_triggers" == '8' ]] || {
  printf 'Expected eight bulk-operation integrity triggers, found %s.\n' "$required_triggers" >&2
  exit 1
}

psql_admin --command="BEGIN; DO \$probe\$
DECLARE
  workspace_key bigint;
  actor_key bigint;
  document_key bigint;
  document_public uuid;
  parent_key bigint;
  item_key bigint;
  rejected boolean;
BEGIN
  SELECT d.workspace_id, wm.user_id, d.id, d.public_id
    INTO workspace_key, actor_key, document_key, document_public
  FROM documents d
  JOIN workspace_memberships wm ON wm.workspace_id = d.workspace_id
  ORDER BY d.id, wm.id LIMIT 1;
  IF document_key IS NULL THEN RAISE EXCEPTION 'No local document fixture is available for the bulk probe'; END IF;

  INSERT INTO bulk_operations (
    public_id, workspace_id, actor_type, actor_user_id, actor_identity,
    operation_type, status, canonical_payload, payload_schema_version,
    selection_mode, filter_explanation, client_idempotency_key,
    request_digest, membership_digest, created_at, updated_at
  ) VALUES (
    gen_random_uuid(), workspace_key, 'human', actor_key, 'user:' || actor_key,
    'bulk_approval', 'awaiting_confirmation', '{}', 1, 'current_page', '{}',
    gen_random_uuid()::text, repeat('a', 64), repeat('b', 64), now(), now()
  ) RETURNING id INTO parent_key;

  INSERT INTO bulk_operation_items (
    bulk_operation_id, workspace_id, operation_type, ordinal,
    target_document_id, target_kind, target_public_id, target_display_label,
    expected_state_snapshot, eligibility_status, execution_status,
    created_at, updated_at
  ) VALUES (
    parent_key, workspace_key, 'bulk_approval', 1, document_key, 'version',
    document_public, 'constraint probe', '{}', 'eligible', 'eligible', now(), now()
  ) RETURNING id INTO item_key;

  rejected := false;
  BEGIN
    UPDATE bulk_operation_items SET execution_status = 'failed_retryable' WHERE id = item_key;
  EXCEPTION WHEN check_violation THEN rejected := true;
  END;
  IF NOT rejected THEN RAISE EXCEPTION 'Incomplete failed_retryable shape was accepted'; END IF;

  rejected := false;
  BEGIN
    INSERT INTO bulk_operation_items (
      bulk_operation_id, workspace_id, operation_type, ordinal,
      target_document_id, target_kind, target_public_id, target_display_label,
      expected_state_snapshot, eligibility_status, execution_status, created_at, updated_at
    ) VALUES (
      parent_key, workspace_key, 'bulk_promotion', 2, document_key, 'version',
      document_public, 'wrong discriminator', '{}', 'eligible', 'eligible', now(), now()
    );
  EXCEPTION WHEN check_violation OR foreign_key_violation THEN rejected := true;
  END;
  IF NOT rejected THEN RAISE EXCEPTION 'Parent/item discriminator mismatch was accepted'; END IF;

  INSERT INTO bulk_operation_item_attempts (
    bulk_operation_item_id, workspace_id, attempt_ordinal, generation, status,
    lease_expires_at, started_at, executor_identity, invocation_idempotency_key,
    attempt_token, created_at, updated_at
  ) VALUES (
    item_key, workspace_key, 1, 1, 'open', now() + interval '2 minutes', now(),
    'system:constraint-probe', gen_random_uuid()::text, gen_random_uuid(), now(), now()
  );

  rejected := false;
  BEGIN
    INSERT INTO bulk_operation_item_attempts (
      bulk_operation_item_id, workspace_id, attempt_ordinal, generation, status,
      lease_expires_at, started_at, executor_identity, invocation_idempotency_key,
      attempt_token, created_at, updated_at
    ) VALUES (
      item_key, workspace_key, 2, 2, 'open', now() + interval '2 minutes', now(),
      'system:constraint-probe', gen_random_uuid()::text, gen_random_uuid(), now(), now()
    );
  EXCEPTION WHEN unique_violation THEN rejected := true;
  END;
  IF NOT rejected THEN RAISE EXCEPTION 'A second open attempt was accepted'; END IF;
END \$probe\$; ROLLBACK;" >/dev/null

printf 'PostgreSQL bulk-operation foundation verification passed.\n'
