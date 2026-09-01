#!/usr/bin/env bash
set -euo pipefail

repository_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$repository_root"

compose=(docker compose)
database="${POSTGRES_DB:-rag_platform}"
admin_user="${POSTGRES_USER:-rag_platform}"
admin_password="${POSTGRES_PASSWORD:-local-development-only}"
runtime_password="${POSTGRES_APP_PASSWORD:-local-runtime-only}"
migrator_password="${POSTGRES_MIGRATOR_PASSWORD:-local-migrator-only}"

admin_psql() {
  "${compose[@]}" exec -T --env PGPASSWORD="$admin_password" postgres \
    psql --no-psqlrc --set=ON_ERROR_STOP=1 --username "$admin_user" --dbname "$database" "$@"
}

cleanup_probe() {
  admin_psql \
    --command='DROP FUNCTION IF EXISTS public.r26_private_probe(); DROP TABLE IF EXISTS public.r26_runtime_privilege_probe;' \
    >/dev/null 2>&1 || true
}

role_row="$(admin_psql --tuples-only --no-align --command="
SELECT concat_ws('|', rolname, rolcanlogin, rolinherit, rolsuper, rolcreatedb, rolcreaterole, rolreplication, rolbypassrls)
FROM pg_roles
WHERE rolname IN ('rag_platform_owner', 'rag_platform_migrator', 'rag_platform_app')
ORDER BY rolname;
")"

expected_role_row=$'rag_platform_app|t|f|f|f|f|f|f\nrag_platform_migrator|t|f|f|f|f|f|f\nrag_platform_owner|f|f|f|f|f|f|f'
[[ "$role_row" == "$expected_role_row" ]] || {
  printf 'Unexpected protected-role attributes:\n%s\n' "$role_row" >&2
  exit 1
}

membership_row="$(admin_psql --tuples-only --no-align --command="
SELECT concat_ws('|', parent.rolname, member.rolname, membership.inherit_option, membership.set_option)
FROM pg_auth_members membership
JOIN pg_roles parent ON parent.oid = membership.roleid
JOIN pg_roles member ON member.oid = membership.member
WHERE parent.rolname IN ('rag_platform_owner', 'rag_platform_migrator', 'rag_platform_app')
   OR member.rolname IN ('rag_platform_owner', 'rag_platform_migrator', 'rag_platform_app')
ORDER BY parent.rolname, member.rolname;
")"
[[ "$membership_row" == 'rag_platform_owner|rag_platform_migrator|f|t' ]] || {
  printf 'Unexpected protected-role membership graph:\n%s\n' "$membership_row" >&2
  exit 1
}

ownership_boundary="$(admin_psql --tuples-only --no-align --command="
SELECT concat_ws('|',
  pg_get_userbyid(database_entry.datdba),
  pg_get_userbyid(namespace_entry.nspowner),
  EXISTS (
    SELECT 1
    FROM aclexplode(COALESCE(database_entry.datacl, acldefault('d', database_entry.datdba))) acl
    WHERE acl.grantee = 0
      AND acl.privilege_type = 'CONNECT'
  ),
  has_database_privilege('rag_platform_app', database_entry.datname, 'CONNECT'),
  has_database_privilege('rag_platform_app', database_entry.datname, 'TEMPORARY')
)
FROM pg_database database_entry
CROSS JOIN pg_namespace namespace_entry
WHERE database_entry.datname = current_database()
  AND namespace_entry.nspname = 'public';
")"
[[ "$ownership_boundary" == 'rag_platform_owner|rag_platform_owner|f|t|f' ]] || {
  printf 'Unexpected database/schema ownership or CONNECT boundary: %s\n' "$ownership_boundary" >&2
  exit 1
}

owner_drift="$(admin_psql --tuples-only --no-align --command="
SELECT count(*)
FROM (
  SELECT c.oid
  FROM pg_class c
  JOIN pg_namespace n ON n.oid = c.relnamespace
  JOIN pg_roles owner_role ON owner_role.oid = c.relowner
  WHERE n.nspname = 'public'
    AND c.relkind IN ('r', 'p', 'S', 'v', 'm')
    AND owner_role.rolname <> 'rag_platform_owner'
  UNION ALL
  SELECT p.oid
  FROM pg_proc p
  JOIN pg_namespace n ON n.oid = p.pronamespace
  JOIN pg_roles owner_role ON owner_role.oid = p.proowner
  WHERE n.nspname = 'public'
    AND p.prokind IN ('f', 'p')
    AND owner_role.rolname <> 'rag_platform_owner'
) drift;
")"
[[ "$owner_drift" == '0' ]] || {
  printf 'Application object ownership drift count: %s\n' "$owner_drift" >&2
  exit 1
}

privilege_drift="$(admin_psql --tuples-only --no-align --command="
SELECT count(*)
FROM pg_class c
JOIN pg_namespace n ON n.oid = c.relnamespace
WHERE n.nspname = 'public'
  AND c.relname NOT IN (
    'bulk_operations',
    'bulk_operation_items',
    'bulk_operation_item_attempts',
    'bulk_operation_item_subordinate_transitions'
  )
  AND (
    (c.relkind IN ('r', 'p', 'v', 'm') AND NOT (
      has_table_privilege('rag_platform_app', c.oid, 'SELECT')
      AND has_table_privilege('rag_platform_app', c.oid, 'INSERT')
      AND has_table_privilege('rag_platform_app', c.oid, 'UPDATE')
      AND has_table_privilege('rag_platform_app', c.oid, 'DELETE')
    ))
    OR (c.relkind = 'S' AND NOT (
      has_sequence_privilege('rag_platform_app', c.oid, 'USAGE')
      AND has_sequence_privilege('rag_platform_app', c.oid, 'SELECT')
    ))
  );
")"
[[ "$privilege_drift" == '0' ]] || {
  printf 'Runtime privilege drift count: %s\n' "$privilege_drift" >&2
  exit 1
}

bulk_privilege_boundary="$(admin_psql --tuples-only --no-align --command="
SELECT concat_ws('|',
  has_table_privilege('rag_platform_app', 'bulk_operations', 'SELECT'),
  has_table_privilege('rag_platform_app', 'bulk_operations', 'INSERT'),
  has_table_privilege('rag_platform_app', 'bulk_operations', 'UPDATE'),
  has_column_privilege('rag_platform_app', 'bulk_operations', 'status', 'UPDATE'),
  has_table_privilege('rag_platform_app', 'bulk_operations', 'DELETE'),
  has_table_privilege('rag_platform_app', 'bulk_operation_items', 'SELECT'),
  has_table_privilege('rag_platform_app', 'bulk_operation_items', 'INSERT'),
  has_column_privilege('rag_platform_app', 'bulk_operation_items', 'target_document_id', 'INSERT'),
  has_column_privilege('rag_platform_app', 'bulk_operation_items', 'target_reference_status', 'INSERT'),
  has_table_privilege('rag_platform_app', 'bulk_operation_items', 'UPDATE'),
  has_column_privilege('rag_platform_app', 'bulk_operation_items', 'execution_status', 'UPDATE'),
  has_column_privilege('rag_platform_app', 'bulk_operation_items', 'target_document_id', 'UPDATE'),
  has_table_privilege('rag_platform_app', 'bulk_operation_items', 'DELETE'),
  has_table_privilege('rag_platform_app', 'bulk_operation_item_attempts', 'SELECT'),
  has_table_privilege('rag_platform_app', 'bulk_operation_item_attempts', 'INSERT'),
  has_table_privilege('rag_platform_app', 'bulk_operation_item_attempts', 'UPDATE'),
  has_column_privilege('rag_platform_app', 'bulk_operation_item_attempts', 'status', 'UPDATE'),
  has_column_privilege('rag_platform_app', 'bulk_operation_item_attempts', 'attempt_token', 'UPDATE'),
  has_table_privilege('rag_platform_app', 'bulk_operation_item_attempts', 'DELETE'),
  has_table_privilege('rag_platform_app', 'bulk_operation_item_subordinate_transitions', 'SELECT'),
  has_table_privilege('rag_platform_app', 'bulk_operation_item_subordinate_transitions', 'INSERT'),
  has_table_privilege('rag_platform_app', 'bulk_operation_item_subordinate_transitions', 'UPDATE'),
  has_table_privilege('rag_platform_app', 'bulk_operation_item_subordinate_transitions', 'DELETE')
);
")"
expected_bulk_privileges='t|t|f|t|f|t|f|t|f|f|t|f|f|t|t|f|t|f|f|t|t|f|f'
[[ "$bulk_privilege_boundary" == "$expected_bulk_privileges" ]] || {
  printf 'Unexpected protected bulk-operation privilege boundary: %s\n' "$bulk_privilege_boundary" >&2
  exit 1
}

bulk_function_boundary="$(admin_psql --tuples-only --no-align --command="
SELECT count(*)
FROM pg_proc p
JOIN pg_namespace n ON n.oid = p.pronamespace
WHERE n.nspname = 'public'
  AND p.proname IN ('retire_bulk_operation_item_targets')
  AND p.prosecdef
  AND pg_get_userbyid(p.proowner) = 'rag_platform_owner';
")"
[[ "$bulk_function_boundary" == '1' ]] || {
  printf 'Bulk target-retirement function ownership/security drift: %s\n' "$bulk_function_boundary" >&2
  exit 1
}

public_function_grants="$(admin_psql --tuples-only --no-align --command="
SELECT count(*)
FROM pg_proc p
JOIN pg_namespace n ON n.oid = p.pronamespace
JOIN LATERAL aclexplode(COALESCE(p.proacl, acldefault('f', p.proowner))) acl ON true
WHERE n.nspname = 'public'
  AND p.prokind IN ('f', 'p')
  AND acl.grantee = 0
  AND acl.privilege_type = 'EXECUTE';
")"
[[ "$public_function_grants" == '0' ]] || {
  printf 'PUBLIC can execute %s application functions.\n' "$public_function_grants" >&2
  exit 1
}

trap cleanup_probe EXIT

"${compose[@]}" run --rm --no-deps migrator sh -lc \
  "php artisan migrate:status --no-interaction >/dev/null && php -r 'echo \"migrator-ok\\n\";'" \
  | grep -q '^migrator-ok$'

runtime_user="$("${compose[@]}" exec -T --env PGPASSWORD="$runtime_password" postgres \
  psql --no-psqlrc --tuples-only --no-align --username rag_platform_app --dbname "$database" \
  --command='SELECT current_user;')"
[[ "$runtime_user" == 'rag_platform_app' ]] || {
  printf 'Runtime connection used unexpected role: %s\n' "$runtime_user" >&2
  exit 1
}

if "${compose[@]}" exec -T --env PGPASSWORD="$runtime_password" postgres \
  psql --no-psqlrc --username rag_platform_app --dbname "$database" \
  --command='SET ROLE rag_platform_owner;' >/dev/null 2>&1; then
  printf 'Runtime role unexpectedly assumed owner authority.\n' >&2
  exit 1
fi

if "${compose[@]}" exec -T --env PGPASSWORD="$runtime_password" postgres \
  psql --no-psqlrc --username rag_platform_app --dbname "$database" \
  --command='CREATE TABLE public.r26_forbidden_runtime_ddl (id integer);' >/dev/null 2>&1; then
  printf 'Runtime role unexpectedly created a table.\n' >&2
  exit 1
fi

"${compose[@]}" run --rm --no-deps migrator sh -lc \
  "php -r '\$pdo = new PDO(\"pgsql:host=postgres;port=5432;dbname=$database\", \"rag_platform_migrator\", getenv(\"DB_PASSWORD\"), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]); echo \$pdo->query(\"SELECT current_user\")->fetchColumn(), PHP_EOL;'" \
  | grep -q '^rag_platform_owner$'

admin_psql --command='DROP FUNCTION IF EXISTS public.r26_private_probe(); DROP TABLE IF EXISTS public.r26_runtime_privilege_probe;' >/dev/null
"${compose[@]}" run --rm --no-deps migrator sh -lc "php -r '\$pdo = new PDO(\"pgsql:host=postgres;port=5432;dbname=$database\", \"rag_platform_migrator\", getenv(\"DB_PASSWORD\"), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]); \$pdo->exec(\"CREATE TABLE public.r26_runtime_privilege_probe (id bigserial PRIMARY KEY, value text NOT NULL); CREATE FUNCTION public.r26_private_probe() RETURNS integer LANGUAGE sql AS \\\$function\\\$ SELECT 1 \\\$function\\\$;\");'"

"${compose[@]}" exec -T --env PGPASSWORD="$runtime_password" postgres \
  psql --no-psqlrc --set=ON_ERROR_STOP=1 --username rag_platform_app --dbname "$database" \
  --command="INSERT INTO public.r26_runtime_privilege_probe (value) VALUES ('created')" \
  --command="UPDATE public.r26_runtime_privilege_probe SET value = 'updated'" \
  --command='SELECT value FROM public.r26_runtime_privilege_probe' \
  --command='DELETE FROM public.r26_runtime_privilege_probe' >/dev/null

if "${compose[@]}" exec -T --env PGPASSWORD="$runtime_password" postgres \
  psql --no-psqlrc --username rag_platform_app --dbname "$database" \
  --command='SELECT public.r26_private_probe();' >/dev/null 2>&1; then
  printf 'A future function unexpectedly inherited runtime EXECUTE.\n' >&2
  exit 1
fi

probe_owners="$(admin_psql --tuples-only --no-align --command="
SELECT string_agg(owner_name, ',' ORDER BY owner_name)
FROM (
  SELECT pg_get_userbyid(relowner) AS owner_name
  FROM pg_class
  WHERE oid IN ('public.r26_runtime_privilege_probe'::regclass,
                'public.r26_runtime_privilege_probe_id_seq'::regclass)
  UNION ALL
  SELECT pg_get_userbyid(proowner)
  FROM pg_proc
  WHERE oid = 'public.r26_private_probe()'::regprocedure
) owners;
")"
[[ "$probe_owners" == 'rag_platform_owner,rag_platform_owner,rag_platform_owner' ]] || {
  printf 'Future objects have unexpected owners: %s\n' "$probe_owners" >&2
  exit 1
}

admin_psql --command='DROP FUNCTION public.r26_private_probe(); DROP TABLE public.r26_runtime_privilege_probe;' >/dev/null
trap - EXIT

"${compose[@]}" exec -T --env OTEL_SDK_DISABLED=true api \
  php artisan migrate:status --no-interaction >/dev/null

printf 'PostgreSQL runtime-role verification passed.\n'
