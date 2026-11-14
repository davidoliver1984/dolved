# Operational incident — reviewer, 2026-11-14 (UTC ~08:48)

While preparing pre-dispatch checks, the reviewer ran `docker compose up -d --no-deps postgres` in the main development stack (`rag-platform`), whose postgres container had been exited for about 26 hours, after a read-only PHP script failed to connect there. The script then ran in a read-only transaction (rolled back) and failed on a missing user. The reviewer ran read-only `psql` counts (users 5, workspaces 4, documents 209) and listed the databases. The container was then stopped with `docker compose stop postgres`, returning it to its earlier stopped state. No workers or other services were started. No writes were issued. There is no evidence of any change to database contents, but none is asserted beyond the commands listed here.

Cause: the evaluation corpus lives in the isolated `dolved-r28-s03` stack, not the development stack. All recorded pre-dispatch results (amendment 2) come from `dolved-r28-s03-api-1` only.
Rule going forward: verify the `dolved-r28-s03` stack and connection identity before any runtime or database work; never start or query the development stack for evaluation checks.
