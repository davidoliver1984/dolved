# Session Journal: R07-S01 — Define Tenant Model

## Date

2026-07-27

## Session mode

Architecture and documentation only. No application code, migrations, models,
middleware, policies, routes or frontend changes were made.

## What happened

The workspace tenancy model had already been agreed in principle (recorded as a
set of 25 agreed decisions plus required security invariants and alternatives
to record, in `docs/adr/gpt_drafts/r07-s01.md`). This session's job was to turn
that agreed brief into the platform's actual ADR and repository records,
checked against `CLAUDE.md`, `CONTRIBUTING.md`, `PROJECT_ROADMAP.md`,
`IMPLEMENTATION_GUIDE.md`, `tasks.json` and the existing `docs/adr/` files for
consistency of numbering, terminology and format.

The drafted ADR then went to Ralph for architecture review and came back
twice:

* `docs/adr/gpt_drafts/r07-s01-reviewed.md` — accepted PostgreSQL Row-Level
  Security as a defence-in-depth layer (previously deferred), added the
  workspace-configuration/platform-catalogue split, three independent audit
  layers, and reframed workspace deletion as an asynchronous lifecycle rather
  than an immediate delete.
* `docs/adr/gpt_drafts/r07-s01-final-amends.md` — a polish pass: strengthened
  the RLS defence-in-depth philosophy, added a fourth entity-classification
  category (`workspace-configurable`), an explicit tenant-propagation/
  trust-boundary invariant, a correlation-ID statement, an event-versioning
  consideration, a provider/model split within workspace configuration, and a
  closing "correctness over convenience" architectural principle.

The ADR was approved after the second round with no further changes
requested.

## Decisions recorded

`docs/adr/0006-use-workspace-as-the-tenancy-and-isolation-boundary.md` records,
in its final approved form:

* Workspace as the platform's tenant, collaboration and isolation boundary, with
  no organisation layer above it for now.
* Global users, multiple workspace memberships, and a first-class
  `WorkspaceMembership` model carrying a fixed `owner`/`admin`/`member` role set.
* Exactly one active owner membership per workspace at all times;
  `created_by_user_id` is provenance only, not authority.
* Pooled relational tenancy (shared database and tables) with a mandatory
  non-nullable workspace foreign key on every workspace-owned row, enforced
  through **defence in depth**: scoped routes, active-membership validation,
  scoped resource resolution, Laravel policies, explicit tenant-scoped
  queries, **PostgreSQL Row-Level Security**, and database constraints — no
  single layer is trusted alone, and application-layer authorisation must
  remain correct even where RLS is disabled.
* A restricted, non-superuser, non-`BYPASSRLS` runtime database role distinct
  from the privileged migration role; `FORCE ROW LEVEL SECURITY` on
  tenant-owned tables. RLS's actual implementation is Stage 7.2 work.
* A fourth entity-classification category, `workspace-configurable`, alongside
  platform-global, workspace-relationship and workspace-owned — platform
  capabilities (embedding/generation provider and model, retrieval config)
  that are globally available but configured per workspace.
* Three independent audit layers: business audit, search/RAG audit (once
  retrieval exists), and database audit (e.g. `pgAudit`, forensic-only, not
  the primary trail) — plus a future shared correlation identifier for
  end-to-end traceability.
* Workspace identity propagation into S3 object keys, SQS events, extracted
  documents, chunks and vectors, with async workers required to take context
  from the message rather than session or process-global state, and tenant
  identity treated as untrusted at every service boundary until the receiver
  validates it. Event contracts are expected to carry explicit version
  information as they evolve.
* Qdrant collection/sharding strategy deferred to `R13-S01`; workspace identity
  on every vector record is required regardless of that later decision.
* Explicit, atomic workspace creation (workspace + owner membership together);
  workspace deletion as an asynchronous, auditable, multi-system lifecycle
  (Postgres, S3, Qdrant, audit records) — the orchestration mechanics remain
  deferred, but the shape of deletion is now decided.

Thirteen rejected or deferred alternatives are recorded in the ADR with the
reasoning for not adopting each now (organisation hierarchy, one-workspace-per-
user, `workspace_id` on `users`, database/schema-per-tenant, session-only or
header-based tenant selection, subdomain resolution, global-scope-as-primary-
boundary, Qdrant collection-per-tenant, auto-created personal workspaces, and a
full custom permission engine) — including two added during review to reflect
what was actually reconsidered: application-layer authorisation alone without
RLS (the platform's original position), and RLS as the sole isolation
mechanism (also rejected; both layers are required).

## Verification performed

* Read every existing ADR (0001–0005) and `docs/adr/README.md` before drafting,
  to preserve the four-digit sequence, required-sections structure and
  kebab-case naming convention.
* Confirmed `tasks.json` and `docs/rag-platform-tasks.json` are byte-identical,
  so the tracker update applies to both.
* Checked the ADR's final form against each Stage 7.1 acceptance criterion in
  `IMPLEMENTATION_GUIDE.md`; all are met, including audit requirements, which
  moved from partially met to met once the three-layer audit model was added
  in review.
* Re-synced `guide_start_line`/`guide_end_line` references in `tasks.json` and
  `docs/rag-platform-tasks.json` after `IMPLEMENTATION_GUIDE.md` grew, and
  verified the new values against the actual file rather than assuming the
  arithmetic was correct.
* Did not run `make lint` / `make test` / etc. — no application code changed in
  this session, so those checks do not apply.

## Problems or corrections

None from the initial brief — no conflicts were found between the agreed
decisions in `docs/adr/gpt_drafts/r07-s01.md` and the existing repository
documents. Two rounds of architecture review did change the ADR materially
(see "What happened" above); those were review refinements, not corrections
of an error.

## Next steps / important takeaways

* Stage 7.2 (Implement Tenants and Memberships) can now proceed against a
  settled model: `workspaces`, `workspace_memberships` (naming to be finalised
  in that session), with tenant scoping applied at the query boundary per
  `CONTRIBUTING.md`'s Actions/Queries convention, and negative cross-tenant
  feature tests as a hard requirement, not optional hardening.
* Stage 7.2 will need to decide the concrete owner-transfer and last-admin-
  removal transactional rules implied by the "exactly one active owner" and
  role invariants in ADR 0006 — those are implementation details this ADR
  intentionally left to that session.
* Stage 7.2 now also carries the RLS implementation itself: policy
  definitions, a restricted non-superuser/non-`BYPASSRLS` runtime database
  role, `FORCE ROW LEVEL SECURITY` on tenant-owned tables, transaction-local
  tenant-context (GUC) propagation, and tests proving RLS actually holds —
  this was accepted as architecture in review but explicitly not implemented
  by this ADR.
* Business-audit logging (workspace creation, membership/role changes,
  ownership transfer) is now an accepted requirement, not a "future
  enhancement" — Stage 7.2 should account for it rather than treat it as
  out of scope.
* The `docs/adr/gpt_drafts/` directory now has three consumed drafts
  (`r07-s01.md`, `r07-s01-reviewed.md`, `r07-s01-final-amends.md`); they have
  been left in place rather than deleted, since removing them was not part of
  this session's agreed scope.
