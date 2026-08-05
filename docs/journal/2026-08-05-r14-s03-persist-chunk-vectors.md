# Session Journal: R14-S03 — Persist Chunk Vectors

## Date

2026-08-05

## Boundary correction

Before Laravel implementation began, the R14-S03 boundary was corrected because its
original `apps/ai tests` commit scope did not include the PostgreSQL foundation that
accepted ADR 0014 requires and that the R14-S01 journal assigns to this stage.

R14-S03 now owns both complementary foundations: authoritative canonical chunks,
embedding-profile lineage and generation lifecycle in Laravel/PostgreSQL, and the
derived vector projection behind the Python `VectorStore`/Qdrant adapter. R14-S04
continues to own ingestion workflow integration, lifecycle outcomes, retries and
terminal-failure orchestration.

This correction changes where accepted work is implemented; it does not change the
architecture. ADR 0014 was not modified and no new ADR is required.

## What was implemented

Laravel/PostgreSQL now owns canonical Document chunks, immutable embedding-profile
lineage, embedding-space generations, workspace corpus generations and explicit
corpus-to-chunk assignments. The models expose the corresponding Workspace, Document,
profile, generation and chunk relationships and factories cover every lifecycle
state.

The database enforces unique public identities and semantic fingerprints, positive
dimensions and token counts, nonblank canonical text, tenant-safe composite foreign
keys, unique chunk ordinals within a chunking configuration, allowed lifecycle
states/timestamps and at most one active corpus generation per workspace. A composite
foreign key stores the authoritative Workspace pointer and prevents it crossing the
tenant boundary. PostgreSQL triggers ensure profile dimensions agree with their
embedding space, validate a non-null active pointer and require an active corpus to
use an available embedding space. A key-share lock makes the latter invariant safe
against a concurrent embedding-space retirement. PostgreSQL contains no raw vector
column.

Python now has a provider-neutral `VectorStore` protocol and immutable operation
models, with Qdrant details isolated in one adapter. The adapter uses deterministic
UUIDv5 point identities, a minimal five-field payload, mandatory workspace and corpus
generation scoping, idempotent collection/index creation, bounded synchronous
upserts, typed partial-write failures, scoped search/count/delete and completeness
verification that checks identity, payload and vector schema rather than count alone.
The official `qdrant-client` dependency is pinned at `1.18.0`.

## Verification evidence

* Focused Laravel foundation suite: 19 tests passed with 59 assertions.
* Focused Python vector-store suites: 13 tests passed against local Qdrant.
* Clean PostgreSQL migration, full rollback and full reapplication passed in the
  disposable `rag_platform_r14_s03_verify` database, which was removed afterward.
* Direct PostgreSQL checks rejected invalid active-space, duplicate-active,
  referenced-space-retirement and cross-workspace-chunk cases.
* Frontend: 26 tests passed.
* Laravel: 146 tests passed with 627 assertions.
* Python: 181 tests passed; the existing credential-dependent live embedding test
  was skipped as designed.
* ESLint, Pint (131 files), Ruff formatting/lint, TypeScript, MyPy (73 source files),
  Composer validation, Compose validation, container health and Qdrant readiness all
  passed.
* `git diff --check` passed.

## Problems and corrections

The deterministic UUID fixture was initially copied before the final namespace input
was settled and was corrected to the actual stable V1 value. Two Pydantic negative
tests initially used `model_copy(update=...)`, which bypasses validation by design;
they were rewritten to construct validated models. The clean-database check first
used an incorrect local PostgreSQL role, then succeeded with the configured
`rag_platform` role. Refreshing the empty development tables then exposed an earlier
uncommitted form of migration 000009; its down path was made tolerant of that local
pre-review state and the final schema was reapplied. A final concurrency review added
a key-share lock to the active corpus trigger so activation cannot race an
embedding-space retirement. Count,
scoped-delete and collection-removal operations were also brought under the same
vector-space compatibility guard already used by upsert and search.

## Boundary held

No ingestion-worker orchestration, lifecycle completion, retry policy or terminal
failure handling was added. Those operations remain wholly owned by R14-S04.

## Commit boundary

Proposed commit: `Persist canonical chunks and document vectors`

Proposed annotated tag: `phase-14-s03`
