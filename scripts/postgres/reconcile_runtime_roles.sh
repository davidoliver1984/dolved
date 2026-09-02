#!/usr/bin/env bash
set -euo pipefail

: "${PGHOST:?PGHOST is required}"
: "${PGDATABASE:?PGDATABASE is required}"
: "${PGUSER:?PGUSER is required}"
: "${PGPASSWORD:?PGPASSWORD is required}"
: "${RAG_PLATFORM_MIGRATOR_PASSWORD:?RAG_PLATFORM_MIGRATOR_PASSWORD is required}"
: "${RAG_PLATFORM_APP_PASSWORD:?RAG_PLATFORM_APP_PASSWORD is required}"

application_schemas="${RAG_PLATFORM_DB_SCHEMAS:-public}"

if [[ ! "$application_schemas" =~ ^[a-zA-Z_][a-zA-Z0-9_]*(,[a-zA-Z_][a-zA-Z0-9_]*)*$ ]]; then
  printf 'Invalid RAG_PLATFORM_DB_SCHEMAS value: %s\n' "$application_schemas" >&2
  exit 1
fi

psql=(
  psql
  --no-psqlrc
  --set=ON_ERROR_STOP=1
  --set=migrator_password="$RAG_PLATFORM_MIGRATOR_PASSWORD"
  --set=runtime_password="$RAG_PLATFORM_APP_PASSWORD"
  --set=application_database="$PGDATABASE"
)

"${psql[@]}" <<'SQL'
SELECT 'CREATE ROLE rag_platform_owner NOLOGIN NOINHERIT NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS'
WHERE NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'rag_platform_owner')
\gexec

SELECT format(
    'CREATE ROLE rag_platform_migrator LOGIN NOINHERIT NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS PASSWORD %L',
    :'migrator_password'
)
WHERE NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'rag_platform_migrator')
\gexec

SELECT format(
    'CREATE ROLE rag_platform_app LOGIN NOINHERIT NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS PASSWORD %L',
    :'runtime_password'
)
WHERE NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'rag_platform_app')
\gexec

ALTER ROLE rag_platform_owner NOLOGIN NOINHERIT NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS;
SELECT format(
    'ALTER ROLE rag_platform_migrator LOGIN NOINHERIT NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS PASSWORD %L',
    :'migrator_password'
)
\gexec
SELECT format(
    'ALTER ROLE rag_platform_app LOGIN NOINHERIT NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS PASSWORD %L',
    :'runtime_password'
)
\gexec

SELECT 'REVOKE rag_platform_owner FROM rag_platform_app'
WHERE EXISTS (
    SELECT 1
    FROM pg_auth_members membership
    WHERE membership.roleid = 'rag_platform_owner'::regrole
      AND membership.member = 'rag_platform_app'::regrole
)
\gexec
SELECT 'REVOKE rag_platform_migrator FROM rag_platform_app'
WHERE EXISTS (
    SELECT 1
    FROM pg_auth_members membership
    WHERE membership.roleid = 'rag_platform_migrator'::regrole
      AND membership.member = 'rag_platform_app'::regrole
)
\gexec
SELECT 'REVOKE rag_platform_app FROM rag_platform_migrator'
WHERE EXISTS (
    SELECT 1
    FROM pg_auth_members membership
    WHERE membership.roleid = 'rag_platform_app'::regrole
      AND membership.member = 'rag_platform_migrator'::regrole
)
\gexec
GRANT rag_platform_owner TO rag_platform_migrator WITH INHERIT FALSE, SET TRUE;

SELECT format('REVOKE CONNECT, TEMPORARY ON DATABASE %I FROM PUBLIC', :'application_database')
\gexec
SELECT format('GRANT CONNECT ON DATABASE %I TO rag_platform_migrator, rag_platform_app', :'application_database')
\gexec
SELECT format('ALTER DATABASE %I OWNER TO rag_platform_owner', :'application_database')
\gexec

ALTER DEFAULT PRIVILEGES FOR ROLE rag_platform_owner REVOKE EXECUTE ON FUNCTIONS FROM PUBLIC;
SQL

IFS=',' read -r -a schemas <<<"$application_schemas"

for schema in "${schemas[@]}"; do
  "${psql[@]}" --set=application_schema="$schema" <<'SQL'
SELECT set_config('r26.application_schema', :'application_schema', false);

SELECT format('ALTER SCHEMA %I OWNER TO rag_platform_owner', :'application_schema')
\gexec
SELECT format('REVOKE CREATE ON SCHEMA %I FROM PUBLIC', :'application_schema')
\gexec
SELECT format('GRANT USAGE ON SCHEMA %I TO rag_platform_app', :'application_schema')
\gexec

DO $ownership$
DECLARE
    target record;
BEGIN
    FOR target IN
        SELECT c.relkind, n.nspname, c.relname
        FROM pg_class c
        JOIN pg_namespace n ON n.oid = c.relnamespace
        WHERE n.nspname = current_setting('r26.application_schema')
          AND c.relkind IN ('r', 'p', 'v', 'm')
    LOOP
        EXECUTE format(
            'ALTER %s %I.%I OWNER TO rag_platform_owner',
            CASE target.relkind
                WHEN 'v' THEN 'VIEW'
                WHEN 'm' THEN 'MATERIALIZED VIEW'
                ELSE 'TABLE'
            END,
            target.nspname,
            target.relname
        );
    END LOOP;

    FOR target IN
        SELECT n.nspname, c.relname
        FROM pg_class c
        JOIN pg_namespace n ON n.oid = c.relnamespace
        WHERE n.nspname = current_setting('r26.application_schema')
          AND c.relkind = 'S'
          AND NOT EXISTS (
              SELECT 1
              FROM pg_depend dependency
              WHERE dependency.classid = 'pg_class'::regclass
                AND dependency.objid = c.oid
                AND dependency.refclassid = 'pg_class'::regclass
                AND dependency.deptype IN ('a', 'i')
          )
    LOOP
        EXECUTE format(
            'ALTER SEQUENCE %I.%I OWNER TO rag_platform_owner',
            target.nspname,
            target.relname
        );
    END LOOP;

    FOR target IN
        SELECT p.prokind, n.nspname, p.proname,
               pg_get_function_identity_arguments(p.oid) AS identity_arguments
        FROM pg_proc p
        JOIN pg_namespace n ON n.oid = p.pronamespace
        WHERE n.nspname = current_setting('r26.application_schema')
          AND p.prokind IN ('f', 'p')
    LOOP
        EXECUTE format(
            'ALTER %s %I.%I(%s) OWNER TO rag_platform_owner',
            CASE target.prokind WHEN 'p' THEN 'PROCEDURE' ELSE 'FUNCTION' END,
            target.nspname,
            target.proname,
            target.identity_arguments
        );
    END LOOP;
END
$ownership$;

SELECT format('GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA %I TO rag_platform_app', :'application_schema')
\gexec
SELECT format('GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA %I TO rag_platform_app', :'application_schema')
\gexec
SELECT format('REVOKE EXECUTE ON ALL FUNCTIONS IN SCHEMA %I FROM PUBLIC', :'application_schema')
\gexec

SELECT format(
    'ALTER DEFAULT PRIVILEGES FOR ROLE rag_platform_owner IN SCHEMA %I GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO rag_platform_app',
    :'application_schema'
)
\gexec
SELECT format(
    'ALTER DEFAULT PRIVILEGES FOR ROLE rag_platform_owner IN SCHEMA %I GRANT USAGE, SELECT ON SEQUENCES TO rag_platform_app',
    :'application_schema'
)
\gexec

-- ADR-0036 protected-table reconciliation must run after every broad/default
-- grant pass. PostgreSQL privileges are additive: omitting a column grant does
-- not neutralise a table-level grant.
-- Preserve ADR-0035's existing protected bulk boundary for the same reason.
SELECT format(
    'REVOKE INSERT, UPDATE, DELETE ON TABLE %1$I.bulk_operations, %1$I.bulk_operation_items, %1$I.bulk_operation_item_attempts, %1$I.bulk_operation_item_subordinate_transitions, %1$I.bulk_operation_audit_events FROM rag_platform_app',
    :'application_schema'
)
\gexec
SELECT format('GRANT SELECT, INSERT ON TABLE %I.bulk_operations TO rag_platform_app', :'application_schema')
\gexec
SELECT format(
    'GRANT UPDATE (status, membership_digest, confirmed_at, cancellation_requested_at, updated_at) ON TABLE %I.bulk_operations TO rag_platform_app',
    :'application_schema'
)
\gexec
SELECT format(
    'GRANT SELECT ON TABLE %1$I.bulk_operation_items, %1$I.bulk_operation_item_attempts, %1$I.bulk_operation_item_subordinate_transitions TO rag_platform_app',
    :'application_schema'
)
\gexec
SELECT format(
    'GRANT INSERT (bulk_operation_id, workspace_id, operation_type, ordinal, target_family_id, target_document_id, target_import_item_id, target_kind, target_public_id, target_display_label, expected_state_snapshot, eligibility_status, exclusion_reason, execution_status, terminal_reason, started_at, completed_at, subordinate_kind, subordinate_identity_kind, subordinate_identity_value, subordinate_awaited_since, result_identity, audit_event_id, incorporated_attempt_generation, created_at, updated_at) ON TABLE %I.bulk_operation_items TO rag_platform_app',
    :'application_schema'
)
\gexec
SELECT format(
    'GRANT UPDATE (execution_status, terminal_reason, started_at, completed_at, subordinate_kind, subordinate_identity_kind, subordinate_identity_value, subordinate_awaited_since, result_identity, audit_event_id, incorporated_attempt_generation, updated_at) ON TABLE %I.bulk_operation_items TO rag_platform_app',
    :'application_schema'
)
\gexec
SELECT format('GRANT INSERT ON TABLE %I.bulk_operation_item_attempts TO rag_platform_app', :'application_schema')
\gexec
SELECT format(
    'GRANT UPDATE (status, failure_category, not_applied_reason, completed_at, success_kind, result_digest, result_subordinate_kind, result_identity_kind, result_identity_value, updated_at) ON TABLE %I.bulk_operation_item_attempts TO rag_platform_app',
    :'application_schema'
)
\gexec
SELECT format('GRANT INSERT ON TABLE %I.bulk_operation_item_subordinate_transitions TO rag_platform_app', :'application_schema')
\gexec
SELECT format('GRANT SELECT, INSERT ON TABLE %I.bulk_operation_audit_events TO rag_platform_app', :'application_schema')
\gexec

SELECT format('REVOKE UPDATE, INSERT ON TABLE %I.document_families FROM rag_platform_app', :'application_schema')
\gexec
SELECT format(
    'GRANT INSERT (public_id, workspace_id, name, description, category_id, owner_user_id, review_due_date, tombstoned_at, created_at, updated_at) ON TABLE %I.document_families TO rag_platform_app',
    :'application_schema'
)
\gexec
SELECT format(
    'GRANT UPDATE (name, description, category_id, review_due_date, tombstoned_at, updated_at) ON TABLE %I.document_families TO rag_platform_app',
    :'application_schema'
)
\gexec
SELECT format('REVOKE UPDATE ON TABLE %I.document_governance_commands FROM rag_platform_app', :'application_schema')
\gexec
SELECT format(
    'GRANT EXECUTE ON FUNCTION %I.apply_document_family_owner_change(bigint) TO rag_platform_app',
    :'application_schema'
)
\gexec
SQL
done

printf 'PostgreSQL runtime roles and privileges reconciled for %s.\n' "$application_schemas"
