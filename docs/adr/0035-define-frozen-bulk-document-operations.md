# ADR 0035: Define Frozen Bulk Document Operations

## Status

Accepted

## Date

2026-08-28

## Relationship to prior ADRs

### Consumes ADR-0027 and ADR-0033 as frozen; makes their reserved seams real

ADR-0027 establishes the shadcn/Tailwind/Lucide component system, the
semantic-token/theme architecture, and the WCAG 2.2 AA baseline. ADR-0033
explicitly reserved a selection seam on the library table — "no checkbox
column until this ADR supplies a real action to select for" — and named
the "select this page" versus "select every filtered result" distinction
as a requirement this ADR would define. This ADR makes that seam real.

### Consumes ADR-0030, ADR-0031, ADR-0034 as frozen (Proposed, Tier-2
### freeze-audited) without reopening or weakening them

This ADR invokes, per item, the **existing single-item domain actions**
those ADRs already define — `ApproveDocumentVersion`,
`WithdrawDocumentVersion`, `RescheduleDocumentVersion`,
`CreateApplicabilityOnlySuccessor` (ADR-0031); the `PromotionAttempt`
sequence and its shared `WorkspaceChecksumReservation` serialization
primitive (ADR-0034); ADR-0030's metadata-mutation actions and audit
model. **It introduces no new governance rule, no new clone mechanism, no
new checksum-serialization primitive, and no new promotion sequence** —
every domain-level decision about *whether* and *how* a single target may
be approved, withdrawn, cloned, or promoted remains exactly where those
ADRs already put it. This ADR's own contribution is orchestration: freezing
a target set, invoking the existing per-item action once per target under
its own existing rules, and aggregating honest results.

### Extends ADR-0025's precedent, not the reverse

ADR-0025's `DocumentDeletionOperation` already establishes this
platform's shape for "one durable parent, real progress, idempotent
retry, derived status" — verified directly, its
`(document_id, idempotency_key)`-style single-open-operation pattern and
its "surfaced as visibly stuck" read-model language are the direct
precedents this ADR's own parent/item model and stuck-operation
visibility reuse, generalised from one document to many.

### Does not redecide ADR-0017's authority model, ADR-0007's technical
### lifecycle, or any Laravel/Python ownership boundary

Locations remain applicability/relevance facts, never access control; no
departments or teams exist in V1; categories/tags/keywords remain library
organisation only, never retrieval signals — all unchanged, restated where
this ADR's own operations touch them.

## Context

**Verified directly, not assumed**: no `BulkOperation`-shaped domain exists
anywhere in this codebase today — the closest analogue is ADR-0025's
single-document `DocumentDeletionOperation`. Laravel's own application-
owned database queue is already proven in production shape by
`AdvanceDocumentDeletion` and `ExecuteGenerationRun`
(`apps/api/app/Jobs/`), both running on the `database` queue driver —
this ADR's execution model reuses that exact, already-working pattern,
via its own dedicated, explicitly named connection (`bulk`, "Idempotency
and concurrency" below) **rather than depending on whatever the
application's global default queue connection happens to be**.
`RetryDocumentIngestion`'s `(document_id, idempotency_key)` shape,
generalised in ADR-0034's `(workspace_id, import_item_id, actor_identity,
operation_kind, client_idempotency_key)` idempotency identity, is the
direct precedent this ADR's own request-idempotency model follows.
`WorkspaceRole::{Owner, Admin, Member}` is the complete, unchanged
authorization vocabulary this ADR gates against — no new role is
introduced.

This is the second of two ADRs completing the accepted Import, Staging and
Bulk Governance phase (following ADR-0034); its browser surfaces are
sequenced to appear only after ADR-0034's `ImportItem`/`PromotionAttempt`
primitives are implemented, per "Phase/session allocation" below.

## Decision

### Non-negotiable ownership boundary, restated and held throughout

**Bulk orchestration is entirely Laravel-owned.** Python never resolves
selection, decides eligibility, authorises, approves, classifies an
exclusion, determines retryability, aggregates a result, renders browser
wording, or sends a notification. **A bulk applicability change invokes
ADR-0031's already-accepted clone mechanism**, which crosses the existing
Laravel/Python boundary for bounded technical cloning work exactly as
ADR-0031 already defines it — this gives Python no new authority; it
performs the identical bounded Qdrant operation it would perform for a
single, non-bulk applicability change. **No OpenAI or Voyage call is part
of bulk preflight, membership freezing, or orchestration.** Where a
single-item fallback legitimately triggers full ingestion (ADR-0031's own
fail-closed fallback when a clone's compatibility proof does not hold),
that fallback remains governed entirely by its existing ADR and
evaluation/provider rules — this ADR neither relaxes nor tightens them.

### V1 operation allowlist

**A deliberately small, closed set for V1** — every candidate the brief
names is addressed explicitly; destructive and high-consequence actions
are deferred rather than casually included.

| # | Operation | Included in V1 | Target type | Backing single-item action | Mutates metadata or creates a successor | Can leave a knowledge gap |
|---|---|---|---|---|---|---|
| 1 | Bulk approval | **Yes** | Version (`Document`) | `ApproveDocumentVersion` | Governance transition, no new row | No |
| 2 | Bulk promotion of resolved staged `ImportItem`s | **Yes** | `ImportItem` | `PromotionAttempt` sequence (ADR-0034) | Creates a new `Document` | No |
| 3 | Bulk applicability/location change | **Yes**, as a governed successor | Family (fans out to its current version) | `CreateApplicabilityOnlySuccessor` (ADR-0031) | Creates a governed successor, never edits in place | No — the predecessor's own authority window is unaffected until the successor genuinely attains authority |
| 4 | Bulk family-owner assignment | **Yes** | Family | ADR-0030's owner-reassignment action | Metadata only | No |
| 5 | Bulk category assignment/change | **Yes** | Family | ADR-0030's category-assignment action | Metadata only | No |
| 6 | Bulk tag add/remove/replace | **Yes** | Family | ADR-0030's lock-based tag-assignment action | Metadata only | No |
| 7 | Bulk review-due-date assignment | **Yes** | Family | ADR-0030's review-date action | Metadata only | No |
| 8 | Bulk deletion (family or version) | **Deferred from V1** | — | — | — | — |
| 9 | Bulk withdrawal/rescheduling | **Deferred from V1** | — | — | — | — |
| 10 | Retry of a previously failed/skipped bulk item | **Yes**, as its own distinct concept — see "Retry semantics" below, not a new operation type | Whichever the original item's target was | The original item's own backing action | — | — |

**Deferred explicitly, not by omission**: bulk deletion and bulk
withdrawal/rescheduling are **not** in the V1 allowlist. Both are
genuinely destructive or authority-affecting at scale, and this ADR
declines to introduce them casually. **The existing single-item path
remains fully available for both** — an owner/admin may withdraw or
delete documents one at a time through ADR-0031's existing governance
routes and family-deletion preview/confirm flow, entirely unaffected by
this ADR. A future ADR may extend this allowlist once a genuine,
demonstrated need is established, following exactly the same pattern this
ADR itself establishes.

**Every included operation's typed exclusion conditions, retryability,
confirmation severity, and audit event** are specified per-operation in
"Preflight and exclusion model" and "Audit and observability" below,
rather than repeated per row here.

**Restated, not reopened**: locations remain applicability/relevance
facts, never access control; V1 has no departments or teams; categories,
tags, and keywords remain library organisation only, never retrieval
signals — operation 3 (applicability) and operations 5–6 (category/tags)
are both bound by these unchanged rules.

### Domain and relational model

**`BulkOperation`** (the parent):

| Column | Constraint |
|---|---|
| `id`, `public_id` | Internal/public identity |
| `workspace_id` | `restrictOnDelete()` |
| `actor_type`, `actor_user_id`, `system_actor_code` | The same human/system XOR provenance ADR-0030 already establishes — reused, not reinvented; this is the **initiating** actor, permanently recorded, per "Authorization and tenancy" below |
| `actor_identity` | **Non-nullable**, ADR-0034's own namespaced-string design (`user:{id}` / `system:{code}`), derived deterministically from whichever of `actor_type`/`actor_user_id`/`system_actor_code` is populated and written once, at creation, alongside them — **not** a second, independently-settable source of truth; it exists as its own stored column solely because the idempotency `UNIQUE` constraint below needs one non-nullable, directly-indexable column, closing the same nullable-column deduplication gap ADR-0034 already fixed for `PromotionAttempt` |
| `operation_type` | Bounded enum, exactly the seven included rows above |
| `status` | Bounded enum — see "Parent state machine" below |
| `canonical_payload`, `payload_schema_version` | The requested change, canonicalised (the same RFC 8785 rule ADR-0032/0034 already establish) — e.g. the applicability set for operation 3, the owner/category/tag/date value for operations 4–7 |
| `selection_mode` | Bounded enum: `current_page`, `all_filtered` |
| `filter_explanation` | The original filter/query, retained **only** for explanation and audit — never re-executed for membership |
| `client_idempotency_key`, `request_digest` | See "Idempotency and concurrency" below |
| `confirmed_at` | Nullable — set once, at explicit confirmation |
| `cancellation_requested_at` | Nullable |
| `created_at` / `updated_at` | |

**`confirmed_at`, once set, makes every column above it in this table —
`operation_type`, `canonical_payload`, `selection_mode`,
`filter_explanation` — immutable.** A `BulkOperation` is never re-scoped
after confirmation; a materially different request is a new
`BulkOperation` with its own idempotency identity.

**`BulkOperation` gains one further declared constraint required by the
item design below**: `UNIQUE (id, workspace_id, operation_type)` — this is
what lets a `BulkOperationItem` bind to its parent **and** structurally
guarantee its own discriminator can never diverge from the parent's,
below.

### Target-type enforcement — corrected to a genuinely enforceable design

**Withdrawn: the prior design's `CHECK` constraint, which described
matching an item's target columns against its *parent's*
`operation_type` — a plain PostgreSQL `CHECK` constraint cannot inspect
another table, and no such constraint can actually enforce that
comparison. This was a fiction, not an implementable design, and is
corrected here in full.**

**Selected: the discriminator is duplicated onto the item itself,
structurally bound to the parent by a composite foreign key, with the
actual target-shape rule enforced by an ordinary, same-row `CHECK` —
which PostgreSQL genuinely supports, because every column that `CHECK`
references now lives on the one row it is defined against.**

**`BulkOperationItem`**:

| Column | Constraint |
|---|---|
| `id` | |
| `bulk_operation_id`, `workspace_id`, `operation_type` | **Composite FK** `(bulk_operation_id, workspace_id, operation_type)` references `bulk_operations (id, workspace_id, operation_type)` — because `operation_type` is immutable on `BulkOperation` from creation (never updated, and frozen further by `confirmed_at`), this FK makes it **structurally impossible** for an item's own `operation_type` to ever disagree with its parent's; there is no separate write path that could let the two drift apart |
| `ordinal` | `UNIQUE (bulk_operation_id, ordinal)` — stable, deterministic ordering assigned at freeze time |
| `target_family_id` | Nullable, **single-column** FK to `document_families (id)`, `nullOnDelete()` — see "Reconciling live targets with permanent history" below for why this is single-column, not composite with `workspace_id` |
| `target_document_id` | Nullable, single-column FK to `documents (id)`, `nullOnDelete()` |
| `target_import_item_id` | Nullable, single-column FK to `import_items (id)`, `nullOnDelete()` — retained as a defensive backstop only; the correctness-bearing mechanism is the retirement trigger below, which always nulls this column (together with `target_reference_status`) before the FK's own `ON DELETE SET NULL` action ever has a referencing row left to act on |
| `target_reference_status` | **Non-nullable**, bounded enum `live` \| `target_deleted`, defaults `live`, set to `target_deleted` **exactly once, only by the retirement trigger below — never by application code, and never reverted** — the discriminator the target-shape `CHECK` below branches on |
| `target_kind` | **Non-nullable**, bounded enum `family` \| `version` \| `import_item`, set once at freeze time, **never nulled and never updated afterward** — the permanent record of what kind of target this item held, independent of whether the live FK above still resolves |
| `target_public_id` | **Non-nullable**, the target's own `public_id`, copied as a plain scalar at freeze time — never a live join, never affected by the target's later deletion |
| `target_display_label` | **Non-nullable**, a human-readable label captured at freeze time (the family's title, or the version's source filename, or the `ImportItem`'s staged filename), **bounded to 255 characters (truncated, never silently expanded) and rendered exactly as every other user-supplied string already is per ADR-0027 (escaped, never raw HTML/markup) — and, per "Audit and observability" below, excluded from structured logs and metrics, appearing only in the audit/history UI itself** — what a retained history view actually shows once the live FK is gone |
| `expected_state_snapshot` | The specific state fact revalidated immediately before mutation — e.g. the version's governance status and `authority_start` inputs for approval, the family's current owner/category/tags for a metadata change, the `ImportItem`'s `preflight_status`/`match_status`/decision-snapshot identity for promotion |
| `eligibility_status` | Bounded enum: `eligible`, `excluded` — fixed at confirmation, per "Preflight and exclusion model" below |
| `exclusion_reason` | Nullable, bounded typed vocabulary — populated **if and only if** `eligibility_status = 'excluded'`, per the item-level database constraints below |
| `execution_status` | Bounded enum — see "Item state machine" below |
| `terminal_reason` | Nullable, bounded typed vocabulary, describing **only this item's own final terminal outcome** (`skipped`/`failed_permanent`/`cancelled`, including the `target_no_longer_exists` reason below) — **never** a transient `failed_retryable` attempt's own failure, which is recorded instead on that attempt's own `BulkOperationItemAttempt.failure_category` (see the durable attempt model below); `failed_retryable` is non-terminal everywhere in this design and therefore never itself populates `terminal_reason` |
| `started_at`, `completed_at` | Nullable; `completed_at` is populated if and only if `execution_status` is one of this item's own terminal values, per the item-level database constraints below |
| `subordinate_kind` | Nullable, bounded enum: `promotion_attempt`, `content_clone_operation`, `full_ingestion_fallback` — set only while `execution_status = 'waiting_on_subordinate'` (and retained, not nulled, on the terminal outcome that waiting converges to, as permanent lineage), see "Item state machine" below |
| `subordinate_identity_kind` | Nullable, bounded enum: `public_id` \| `event_id` — **withdrawn: the prior assumption that every subordinate's identity is a `public_id`.** A `PromotionAttempt` and a `DocumentContentCloneOperation` each have their own stable `public_id` (`subordinate_identity_kind = 'public_id'`); ordinary full-ingestion fallback has no such row of its own to reference and is instead tracked through ADR-0007's real ingestion lineage `event_id` (`subordinate_identity_kind = 'event_id'`) — see "Subordinate-waiting semantics" below for the same-row `CHECK` binding each `subordinate_kind` to its one permitted `subordinate_identity_kind` |
| `subordinate_identity_value` | Nullable — the opaque scalar value itself (a `public_id` string or an `event_id`), interpreted according to `subordinate_identity_kind`; never a bare foreign key across an unrelated schema |
| `subordinate_awaited_since` | Nullable — set when entering `waiting_on_subordinate`, used for stuck-visibility (below); **retained, not cleared, once the item converges to its own terminal outcome**, as part of the same permanent subordinate lineage `subordinate_kind`/`subordinate_identity_kind`/`subordinate_identity_value` preserve |
| `result_identity` | Nullable — the stable public identity the invoked action (or its resolved subordinate) ultimately produced; whether it is required or forbidden on `succeeded` depends on `operation_type` — see "Complete item-level database constraints" below |
| `audit_event_id` | Non-nullable for executed terminal results (`succeeded`, `failed_permanent`, `skipped`, `cancelled`) and null for preflight `excluded`; excluded-item evidence is the immutable item plus the parent freeze audit, never a fabricated execution event |
| `incorporated_attempt_generation` | Nullable, **composite FK** `(id, incorporated_attempt_generation)` references `bulk_operation_item_attempts (bulk_operation_item_id, generation)` — the exact attempt generation whose terminal result has been incorporated into this item's own `execution_status`/`terminal_reason`/`completed_at`/`audit_event_id`; null until the item's first attempt is incorporated, then never null again — see "The item-to-attempt incorporation marker" below for the complete constraint set and claim algorithm this column drives |

**Withdrawn: the prior `attempt_count` column, incremented directly on
the item row.** A counter column mutated by a transaction that might
itself abort cannot durably record how many attempts have actually been
made — exactly the hazard "Introducing a durable per-item attempt/failure
authority" below exists to close. **`BulkOperationItem` carries no attempt
counter of its own at all**; the number of attempts, and whether the
retry ceiling has been reached, is always derived by counting the
item's own durable `BulkOperationItemAttempt` rows at read time — see
below.

**`eligibility_status` and `execution_status` are both retained** (merging
them would lose `eligibility_status`'s own immutable-at-confirmation
property, which `execution_status` deliberately does not share, since it
evolves through execution) — **and every valid combination of the two,
together with every other column this item carries, is now stated as
exactly one master truth-table `CHECK`, replacing the prior draft's five
separately-composed `CHECK` clauses.**

**Withdrawn: five independently-composed `CHECK` clauses, each correct on
its own narrow axis but collectively leaving real gaps.** Codex correctly
identified two, across two audit passes: (1) the subordinate-field
`CHECK` only *required* the full tuple while `waiting_on_subordinate`,
but for `succeeded` and `failed_permanent` it merely fell through to an
unconstrained `TRUE` — nothing rejected a **partial** subordinate tuple
(e.g. `subordinate_kind` set but `subordinate_identity_value` null) on a
terminal row; (2) the `terminal_reason` `CHECK` required a reason for
`skipped`/`failed_permanent`/`cancelled` but, symmetrically, never
*forbade* one outside that list; (3) **found in this pass** — the closed
truth table that corrected (1) and (2) still left `started_at` and
`result_identity` entirely unconstrained by any branch, the exact same
class of gap recurring on two columns the previous pass's sweep missed.
Every gap existed because a `CHECK` (or a branch within one) asserted
what a given state *must* have, without comprehensively stating what
every *other* state must **not** have. **Corrected: one `CHECK`,
expressed as a closed set of mutually exclusive branches (one per
`execution_status` value, discriminated by that column, which can hold
only one value per row), each branch now a complete, two-directional
specification of `eligibility_status`, `exclusion_reason`, `started_at`,
`completed_at`, `audit_event_id`, `terminal_reason`, the full subordinate
tuple, and `incorporated_attempt_generation` — leaving no state, and no
column, to an unconstrained "anything goes" fallthrough:**

**`started_at` and `incorporated_attempt_generation`, resolved as one
pair**: both are null exactly when this item has never been attempted at
all, and both are non-null exactly once an attempt has genuinely begun —
they are never independently nullable, since "execution genuinely began"
(`started_at`) and "an attempt's own generation exists to incorporate"
(`incorporated_attempt_generation`) are the same underlying fact,
observed through two columns. `excluded`, `eligible`, and `cancelled` are
reachable only before any attempt exists, so both are null in those three
branches. `failed_retryable`, `waiting_on_subordinate`, `succeeded`, and
`failed_permanent` are reachable only after at least one attempt has been
incorporated, so both are non-null in those four. **`skipped` is the one
deliberate exception, stated explicitly rather than left implicit**: a
skip reached via `target_no_longer_exists` (discovered by the claim
query itself, before any attempt row is ever created — "Reconciling live
targets with permanent history" above) has both null; a skip reached via
an incorporated `not_applied` attempt (`expected_state_mismatch`, "The
item-to-attempt incorporation marker" above) has both non-null — the
branch below permits exactly these two shapes and no other, never a row
with one of the pair null and the other not.

**No separate "active"/"executing" state exists to resolve**: per "Item
state machine" below, this design has no durable execution status
between "not yet attempted" and "attempt reached its own outcome" — the
seven `execution_status` values already enumerated there
(`eligible`, `failed_retryable`, `waiting_on_subordinate`, `succeeded`,
`failed_permanent`, `skipped`, `cancelled`) plus the preflight-only
`excluded` value are the complete, closed set this `CHECK` covers; no
eighth state is invented here.

```sql
CHECK (
  -- excluded: a fixed preflight classification, never executed, never
  -- carrying an attempt, a subordinate, or any completion or start
  -- evidence.
  (
    execution_status = 'excluded'
    AND eligibility_status = 'excluded'
    AND exclusion_reason IS NOT NULL
    AND started_at IS NULL
    AND completed_at IS NULL
    AND audit_event_id IS NULL
    AND terminal_reason IS NULL
    AND result_identity IS NULL
    AND incorporated_attempt_generation IS NULL
    AND subordinate_kind IS NULL
    AND subordinate_identity_kind IS NULL
    AND subordinate_identity_value IS NULL
    AND subordinate_awaited_since IS NULL
  )
  -- eligible: frozen, passed preflight, never yet attempted — no start,
  -- completion, result, or subordinate evidence of any kind exists yet.
  OR (
    execution_status = 'eligible'
    AND eligibility_status = 'eligible'
    AND exclusion_reason IS NULL
    AND started_at IS NULL
    AND completed_at IS NULL
    AND audit_event_id IS NULL
    AND terminal_reason IS NULL
    AND result_identity IS NULL
    AND incorporated_attempt_generation IS NULL
    AND subordinate_kind IS NULL
    AND subordinate_identity_kind IS NULL
    AND subordinate_identity_value IS NULL
    AND subordinate_awaited_since IS NULL
  )
  -- failed_retryable: at least one attempt has genuinely begun and been
  -- incorporated as a transient failure; never subordinate-backed (a
  -- subordinate only ever exists once waiting_on_subordinate is reached);
  -- non-terminal, so no completion evidence and no result yet.
  OR (
    execution_status = 'failed_retryable'
    AND eligibility_status = 'eligible'
    AND exclusion_reason IS NULL
    AND started_at IS NOT NULL
    AND completed_at IS NULL
    AND audit_event_id IS NULL
    AND terminal_reason IS NULL
    AND result_identity IS NULL
    AND incorporated_attempt_generation IS NOT NULL
    AND subordinate_kind IS NULL
    AND subordinate_identity_kind IS NULL
    AND subordinate_identity_value IS NULL
    AND subordinate_awaited_since IS NULL
  )
  -- waiting_on_subordinate: execution genuinely began (the initiating
  -- attempt was incorporated as its own success); the complete
  -- subordinate tuple is required; no final result yet — only the
  -- subordinate's own identity is known, tracked separately from
  -- result_identity, which names the eventual outcome, not the
  -- subordinate itself.
  OR (
    execution_status = 'waiting_on_subordinate'
    AND eligibility_status = 'eligible'
    AND exclusion_reason IS NULL
    AND started_at IS NOT NULL
    AND completed_at IS NULL
    AND audit_event_id IS NULL
    AND terminal_reason IS NULL
    AND result_identity IS NULL
    AND incorporated_attempt_generation IS NOT NULL
    AND subordinate_kind IS NOT NULL
    AND subordinate_identity_kind IS NOT NULL
    AND subordinate_identity_value IS NOT NULL
    AND subordinate_awaited_since IS NOT NULL
  )
  -- succeeded: execution began, completion evidence required, no
  -- terminal_reason (success needs no reason), and the subordinate tuple
  -- is all-or-none — wholly absent (a direct database-only success never
  -- had one) or wholly present and retained as permanent lineage (a
  -- subordinate-backed success retains exactly the tuple it entered
  -- waiting_on_subordinate with). result_identity's own requirement is
  -- operation-specific and governed by the separate operation-type/
  -- result-shape CHECK below, not by this branch.
  OR (
    execution_status = 'succeeded'
    AND eligibility_status = 'eligible'
    AND exclusion_reason IS NULL
    AND started_at IS NOT NULL
    AND completed_at IS NOT NULL
    AND audit_event_id IS NOT NULL
    AND terminal_reason IS NULL
    AND incorporated_attempt_generation IS NOT NULL
    AND (
      (subordinate_kind IS NULL AND subordinate_identity_kind IS NULL
        AND subordinate_identity_value IS NULL AND subordinate_awaited_since IS NULL)
      OR (subordinate_kind IS NOT NULL AND subordinate_identity_kind IS NOT NULL
        AND subordinate_identity_value IS NOT NULL AND subordinate_awaited_since IS NOT NULL)
    )
  )
  -- failed_permanent: execution began, identical completion shape to
  -- succeeded except a typed terminal_reason is required, the same
  -- all-or-none subordinate rule applies, and result_identity is always
  -- null — a failure never carries a fabricated successful result,
  -- regardless of operation type.
  OR (
    execution_status = 'failed_permanent'
    AND eligibility_status = 'eligible'
    AND exclusion_reason IS NULL
    AND started_at IS NOT NULL
    AND completed_at IS NOT NULL
    AND audit_event_id IS NOT NULL
    AND terminal_reason IS NOT NULL
    AND result_identity IS NULL
    AND incorporated_attempt_generation IS NOT NULL
    AND (
      (subordinate_kind IS NULL AND subordinate_identity_kind IS NULL
        AND subordinate_identity_value IS NULL AND subordinate_awaited_since IS NULL)
      OR (subordinate_kind IS NOT NULL AND subordinate_identity_kind IS NOT NULL
        AND subordinate_identity_value IS NOT NULL AND subordinate_awaited_since IS NOT NULL)
    )
  )
  -- skipped: completion evidence and a typed terminal_reason required;
  -- never subordinate-backed and never a successful result; started_at/
  -- incorporated_attempt_generation are the one deliberate either/or pair
  -- in this table, per the explicit rule stated above this CHECK.
  OR (
    execution_status = 'skipped'
    AND eligibility_status = 'eligible'
    AND exclusion_reason IS NULL
    AND (
      (started_at IS NULL AND incorporated_attempt_generation IS NULL)
      OR (started_at IS NOT NULL AND incorporated_attempt_generation IS NOT NULL)
    )
    AND completed_at IS NOT NULL
    AND audit_event_id IS NOT NULL
    AND terminal_reason IS NOT NULL
    AND result_identity IS NULL
    AND subordinate_kind IS NULL
    AND subordinate_identity_kind IS NULL
    AND subordinate_identity_value IS NULL
    AND subordinate_awaited_since IS NULL
  )
  -- cancelled: reachable only from `eligible`, before any attempt ever
  -- existed, per "Item state machine" below — never any start, result, or
  -- subordinate evidence.
  OR (
    execution_status = 'cancelled'
    AND eligibility_status = 'eligible'
    AND exclusion_reason IS NULL
    AND started_at IS NULL
    AND completed_at IS NOT NULL
    AND audit_event_id IS NOT NULL
    AND terminal_reason IS NOT NULL
    AND result_identity IS NULL
    AND incorporated_attempt_generation IS NULL
    AND subordinate_kind IS NULL
    AND subordinate_identity_kind IS NULL
    AND subordinate_identity_value IS NULL
    AND subordinate_awaited_since IS NULL
  )
)
```

**A separate operation-type/result-shape `CHECK` governs `result_identity`
specifically on `succeeded`** — a genuine same-row constraint (`operation_type`
already lives on this row, duplicated from the parent and FK-bound
immutable, per "Target-type enforcement" above), kept distinct from the
primary truth table because the requirement is operation-specific, not
execution-state-specific: bulk promotion and bulk applicability change
each create a new entity that must be referenceable, so `result_identity`
is **required**; every other V1 operation (approval, owner/category/tag/
review-date assignment) mutates the existing target in place with nothing
new to reference, so `result_identity` is **forbidden**:

```sql
CHECK (
  execution_status <> 'succeeded'
  OR (
    (operation_type IN ('bulk_promotion', 'bulk_applicability_change')
       AND result_identity IS NOT NULL)
    OR (operation_type IN ('bulk_approval', 'bulk_owner_assignment',
          'bulk_category_assignment', 'bulk_tag_change',
          'bulk_review_date_assignment')
       AND result_identity IS NULL)
  )
)
```

This uses the already-defined subordinate identity/transition model
without duplicating or contradicting it: for the two subordinate-backed
operations, `result_identity` is the eventual **outcome** identity (the
newly created `Document`'s `public_id`, once promotion or the
applicability successor's own subordinate genuinely completes) —
distinct from, and populated later than, `subordinate_identity_value`
(the subordinate **workflow's own** identity, populated at initiation).
Both may be non-null simultaneously on a subordinate-backed `succeeded`
row; neither substitutes for the other.

**This is a true closed truth table, now covering every state-bearing
column**: `execution_status` can hold only one value per row, so the
eight branches above (`excluded`, `eligible`, `failed_retryable`,
`waiting_on_subordinate`, `succeeded`, `failed_permanent`, `skipped`,
`cancelled`) are mutually exclusive by construction, and every branch
fully constrains `eligibility_status`, `exclusion_reason`, `started_at`,
`completed_at`, `audit_event_id`, `terminal_reason`, `result_identity`
(jointly with the second `CHECK` above for the `succeeded` case),
`incorporated_attempt_generation`, and the subordinate tuple, in both
directions — there is no state, and no column, left to an implicit,
unconstrained `TRUE`. `excluded` is folded in as its own first branch
rather than a separate `CHECK`, since a truth table with an exception
bolted on beside it is exactly the shape that produced every gap found
across this ADR's audit passes so far.

**Sweep of every remaining `BulkOperationItem` column, confirming nothing
else is left unconstrained**: `id`, `bulk_operation_id`, `workspace_id`,
`operation_type`, and `ordinal` are immutable identity/membership facts
governed by "Immutable membership" below, not by execution state, and are
unaffected by any execution-status branch. `target_family_id`/
`target_document_id`/`target_import_item_id`/`target_reference_status`/
`target_kind`/`target_public_id`/`target_display_label` are governed by
the separate target-shape `CHECK`, keyed on `target_reference_status`,
deliberately independent of `execution_status` — confirmed compatible
below. `expected_state_snapshot` is captured once at freeze time for
every row, including excluded ones, and never varies by execution
outcome, so it is not state-bearing in the sense this truth table
governs and is intentionally left outside it.

**`failed_retryable` is non-terminal everywhere, and `failed_permanent`
is terminal everywhere** — both facts are now database-enforced by this
one `CHECK`: `failed_retryable` has its own branch requiring no completion
evidence (`completed_at`/`audit_event_id` both null), and `failed_permanent`
is grouped with the terminal, reason-required branches, unconditionally,
in every row that reaches it.

**Subordinate lineage retention, stated as one explicit rule**: the tuple
(`subordinate_kind`, `subordinate_identity_kind`, `subordinate_identity_value`,
`subordinate_awaited_since`) is **wholly absent or wholly present, never
partial, on every row this `CHECK` admits** — the all-or-none condition
inside the `succeeded`/`failed_permanent` branches above is what makes
this database-enforced rather than a convention. Once a subordinate-backed
item resolves terminally, **all four fields are retained permanently, as
permanent audit lineage — none are cleared, including
`subordinate_awaited_since`**, which continues to record when this item
first began waiting, now read as historical fact rather than a
"currently waiting since" marker. **A direct database-only action
(approval, any ADR-0030 metadata action) never populates any of the four
fields at all** — the all-or-none rule's "wholly absent" branch is the
only shape such an item can ever have, for both its non-terminal and its
terminal life.

**Attempt counts and per-attempt results are never stored on this row at
all** — they derive exclusively from the item's own `BulkOperationItemAttempt`
rows, introduced below, which is what makes the retry ceiling durable
against a mutation that itself aborts (the exact hazard Codex identified:
"a transaction that aborts cannot durably increment its own retry
count"). **No attempt row is permitted for an `excluded` item** — not
database-enforceable by a same-row `CHECK` (it is a cross-table fact), so
it is enforced instead by construction: the attempt-claim step (below)
only ever selects from `execution_status IN ('eligible',
'failed_retryable')`, and `excluded` is never assigned either value (per
the truth table above), so an excluded item is never reachable by the
claim query at all.

**Compatibility with target deletion, confirmed**: none of the branches
above reference the target FK columns or `target_reference_status` at
all, so the retirement trigger's own `UPDATE` (which touches only the
target FK columns and `target_reference_status`, per "Reconciling live
targets with permanent history" above) can never violate this `CHECK`,
regardless of the item's current `eligibility_status`/`execution_status`
— including a `target_deleted` row that had already reached `succeeded`
with retained subordinate lineage, which remains fully valid under both
`CHECK`s simultaneously.

**Withdrawn: the immediately prior version of this `CHECK`, which
required exactly one live target FK to be non-null for every row
regardless of the target's fate.** Codex correctly identified the
resulting contradiction: `nullOnDelete()` deletes a target by issuing an
`UPDATE ... SET target_document_id = NULL` (PostgreSQL's own internal
mechanism for `ON DELETE SET NULL`) against every referencing
`bulk_operation_items` row, and **PostgreSQL re-evaluates every `CHECK`
constraint on that row as part of that `UPDATE`** — a `CHECK` that
unconditionally demanded a non-null target column would reject the very
`UPDATE` that deletion depends on, either raising a constraint-violation
error back to the caller deleting the target (silently coupling this
ADR's history retention to every other ADR's deletion paths) or, if
somehow not raised, leaving a row that violates its own declared
invariant. Neither outcome is acceptable, and the earlier draft simply
did not account for PostgreSQL's re-check-on-every-write behaviour.

**Corrected: the `CHECK` now branches on `target_reference_status`, so
the two genuinely different row shapes — live and retained-after-
deletion — are each separately, correctly enforced, and the transition
between them is performed by a single dedicated statement (the retirement
trigger, below) that changes both the FK and the discriminator together,
so the row is never evaluated in an invalid intermediate shape:**

```sql
CHECK (
  (target_reference_status = 'live' AND (
    (operation_type = 'bulk_approval'
       AND target_kind = 'version'
       AND target_document_id IS NOT NULL
       AND target_family_id IS NULL AND target_import_item_id IS NULL)
    OR (operation_type = 'bulk_promotion'
       AND target_kind = 'import_item'
       AND target_import_item_id IS NOT NULL
       AND target_family_id IS NULL AND target_document_id IS NULL)
    OR (operation_type IN (
          'bulk_applicability_change', 'bulk_owner_assignment',
          'bulk_category_assignment', 'bulk_tag_change',
          'bulk_review_date_assignment'
        )
       AND target_kind = 'family'
       AND target_family_id IS NOT NULL
       AND target_document_id IS NULL AND target_import_item_id IS NULL)
  ))
  OR (target_reference_status = 'target_deleted'
     AND target_family_id IS NULL
     AND target_document_id IS NULL
     AND target_import_item_id IS NULL)
)
```

**This closes the gap completely, for both shapes the brief requires:**

- **Live shape** (`target_reference_status = 'live'`): unchanged from the
  prior design — the composite FK guarantees `operation_type` cannot
  diverge from the parent; this `CHECK` guarantees `target_kind` and
  exactly one live target column agree with whichever `operation_type`
  the row actually carries; `target_kind`/`target_public_id`/
  `target_display_label` are populated (non-nullable from insertion,
  unconditionally) as already specified above.
- **Retained-target-deleted shape** (`target_reference_status =
  'target_deleted'`): all three live target FKs are required to be null
  (not merely permitted to be); `workspace_id`, `target_kind`,
  `target_public_id`, and `target_display_label` are untouched by the
  transition, since none of them are part of it (see below); the item
  cannot execute (the per-item revalidation step, corrected below, checks
  `target_reference_status` first and never invokes a mutation against a
  `target_deleted` row); historical rows never block target deletion,
  since the retirement trigger performs the transition itself, inside the
  same transaction as the deletion, before the delete's own row-removal
  step and before the FK's `ON DELETE SET NULL` action would otherwise
  have anything left to act on.

**No cross-table inspection is required by the `CHECK` itself** — every
column it references lives on the one row it is defined against; the
composite FK remains PostgreSQL's own native foreign-key mechanism, not a
`CHECK`.

**An operation-specific item table per type was considered as the
alternative and rejected**: it would eliminate the discriminator
entirely, at the cost of triplicating every shared column
(`eligibility_status`, `execution_status`, audit correlation, etc.) across
three tables and complicating every query that needs to summarise a mixed
operation's progress in one place. The single-table, duplicated-and-FK-
bound-discriminator design keeps one shared shape while still making
every invariant this section requires database-enforced.

### Reconciling live targets with permanent history

**Withdrawn: the prior description of a target FK "becoming unusable" on
deletion while history "survives" — a constrained foreign key cannot
simply become unusable; `restrictOnDelete()` would block the very target
deletion ADR-0025/ADR-0031 must remain free to perform, and an ordinary
cascade would destroy the retained history this ADR requires. Neither was
actually specified before; both are now resolved explicitly.**

- **Each target FK declares `nullOnDelete()`** — the same Laravel schema-
  builder method this decomposition already uses for exactly this
  "preserve the row, drop the now-dangling reference" shape (ADR-0025's
  own correction of `EvidenceSnapshot.document_chunk_id`, from
  `restrictOnDelete()` to `nullOnDelete()`, is the direct precedent
  reused here, not reinvented) — **but, per the corrected `CHECK` above,
  this declared FK action is now a defensive backstop only, never the
  correctness-bearing mechanism**: see the retirement trigger immediately
  below for why. Deleting a family, a version, or an `ImportItem` that a
  `BulkOperationItem` references **never blocks** that deletion —
  ADR-0025/ADR-0031's own deletion paths remain entirely free to proceed.
- **The retirement trigger, not the FK's own `ON DELETE SET NULL` action,
  is what actually performs the live→retained transition.** A new
  `BEFORE DELETE` trigger function, `retire_bulk_operation_item_targets()`,
  is attached to each of `document_families`, `documents`, and
  `import_items`. For the row about to be deleted, it issues exactly one
  `UPDATE bulk_operation_items SET target_reference_status =
  'target_deleted', target_family_id = NULL (or target_document_id /
  target_import_item_id, whichever column matches this table) WHERE
  <that column> = OLD.id` — changing the discriminator and nulling the FK
  **together, in one statement**, so the row is never evaluated by the
  `CHECK` in an invalid intermediate shape (a bare `UPDATE ... SET
  target_document_id = NULL` alone, without also flipping
  `target_reference_status`, would still fail the live-shape branch of
  the `CHECK` above — exactly the bug being corrected). Because this
  trigger fires `BEFORE DELETE` on the target's own row, it runs, and
  commits its `UPDATE`, before the actual row removal — by the time
  PostgreSQL's own internal `ON DELETE SET NULL` action for the
  `nullOnDelete()` FK would otherwise run, no referencing row still
  points at `OLD.id`, so that action finds nothing to do. The FK's
  declared action only ever fires for real if some future write path
  deletes a target row without going through this trigger (impossible for
  an ordinary `DELETE`, since the trigger is attached at the table level
  and fires for every caller — Eloquent, a queued job, or raw SQL alike);
  in that hypothetical case it would raise the live-shape `CHECK`
  violation and **block** the deletion rather than silently corrupt the
  row — a safe failure mode, not the silent one the prior draft risked.

#### Retirement provenance is a database privilege boundary, never session state

**Rejected historically: using a caller-controlled session setting as
retirement authority.** Such a setting proves nothing about who caused a
transition because the runtime role could manufacture it. No live
authorization, provenance, trigger, or guard decision in this design
consults custom session state. **Selected: a three-role PostgreSQL
boundary, required consistently in local, CI, and deployed topology:**

- **`rag_platform_owner`** is `NOLOGIN`. It owns every application schema,
  table, sequence, ordinary function, and trigger function, including
  `public.retire_bulk_operation_item_targets()`. It has no password or
  login credential to inject into any container.
- **`rag_platform_migrator`** is `LOGIN NOINHERIT`. It is granted
  membership in `rag_platform_owner` solely so a one-shot bootstrap or
  migration task can explicitly `SET ROLE rag_platform_owner`. Its
  credential is absent from every long-running API, queue-worker,
  scheduler, web, and Python container.
- **`rag_platform_app`** is `LOGIN NOINHERIT` and is the only PostgreSQL
  identity used by ordinary Laravel HTTP requests, queue workers, and the
  scheduler. It has no direct or inherited membership in either privileged
  role and cannot `SET ROLE` to either one. Web and Python services receive
  no privileged PostgreSQL credential unless a separate accepted boundary
  independently requires one.

**Bootstrap/migration flow is fixed**: the one-shot process connects as
`rag_platform_migrator`, executes `SET ROLE rag_platform_owner`, applies
schema migrations and privilege changes, then resets/closes that
connection. Every created application object is owned by
`rag_platform_owner`, never accidentally by the migrator login. The
current single-role Compose configuration is replaced as foundation work
before any migration depending on protected-column grants may run.

**Complete runtime grant baseline, not only a bulk-table exception, and
now explicitly covering functions, not only tables and sequences**: the
owner-managed bootstrap grants `rag_platform_app` `CONNECT` on the
application database and `USAGE` on each allowlisted application schema;
applies the repository's ordinary runtime DML grant set (`SELECT`,
`INSERT`, `UPDATE`, `DELETE`, as actually required) to every existing
application table; grants `USAGE, SELECT` on every existing application
sequence; **revokes PostgreSQL's own default `EXECUTE ... TO PUBLIC` grant
from every existing function in every allowlisted schema** (PostgreSQL
grants `EXECUTE` on a newly created function to `PUBLIC` automatically
unless told otherwise — a fact this baseline must actively undo for
every function that already exists, since `ALTER DEFAULT PRIVILEGES`
below only ever governs objects created *after* it runs); and then grants
`EXECUTE` back to `rag_platform_app` only on the specific, explicitly
audited list of functions the runtime genuinely calls directly.
**Withdrawn: describing this baseline's future-object coverage as
complete while naming only tables and sequences.** It now also installs
`ALTER DEFAULT PRIVILEGES FOR ROLE rag_platform_owner IN SCHEMA <schema>
REVOKE EXECUTE ON FUNCTIONS FROM PUBLIC` for **every** allowlisted schema,
alongside the existing table/sequence default-privilege rules — so a
function created after this bootstrap runs, by `rag_platform_owner`
(directly, or via `rag_platform_migrator`'s `SET ROLE`), never receives
`PUBLIC EXECUTE` merely from PostgreSQL's own default behaviour, and
requires the same explicit, audited grant as any existing function before
`rag_platform_app` may call it. A migration creating a future application
schema must establish its schema `USAGE`, existing-object grants
(tables, sequences, **and functions**), and owner-scoped default
privileges (tables, sequences, **and functions**) before any runtime
dependency on that schema is deployed. **Trigger-only and owner-only
functions — including every `SECURITY DEFINER` function this ADR
introduces — never receive a runtime `EXECUTE` grant at all**, whether
existing or future: PostgreSQL's trigger mechanism invokes a trigger
function regardless of whether the table-owning role or the statement's
issuing role holds `EXECUTE` on it, so no such grant is ever needed or
given. This baseline is reconciled idempotently in local, CI, and
deployed bootstrap and verified across the existing application before
the restricted role becomes the default.

**Every `SECURITY DEFINER` function this ADR introduces — currently just
`retire_bulk_operation_item_targets()` — independently satisfies the same
four requirements, not merely by inheriting the baseline above**: owned by
`rag_platform_owner`; declares `SET search_path = ''` with every
referenced object fully schema-qualified; has `EXECUTE` revoked from
`PUBLIC` (both by the sweep above, for existing functions, and by the
default-privilege rule above, for any future one); and receives an
explicit runtime `EXECUTE` grant **only** where direct runtime invocation
is actually intended — which, for every `SECURITY DEFINER` function this
ADR defines, is never, since each is invoked exclusively as a trigger.
PostgreSQL's trigger mechanism can still invoke the already-installed
trigger function when runtime deletes a target, with no `EXECUTE` grant
of any kind required for that invocation path. Its internal `UPDATE`
therefore runs as `current_user = 'rag_platform_owner'`.

**Column-level privilege split on `bulk_operation_items`, covering both
`UPDATE` and `INSERT`**: table-level `UPDATE` is revoked from
`rag_platform_app`, then granted only on `execution_status`,
`terminal_reason`, `started_at`, `completed_at`, `subordinate_kind`,
`subordinate_identity_kind`, `subordinate_identity_value`,
`subordinate_awaited_since`, `result_identity`,
`incorporated_attempt_generation`, and `audit_event_id`. Runtime is never
granted update authority over target references, target identity,
membership, eligibility, exclusion, parent, workspace, operation type, or
ordinal.

**Withdrawn: granting runtime unrestricted, whole-row `INSERT`.** Codex
correctly identified that an unrestricted `INSERT` grant would let
`rag_platform_app` create a row with `target_reference_status =
'target_deleted'`, a null target FK, and populated scalar identity
fields directly — satisfying the target-shape `CHECK` without ever
passing through the retirement trigger at all. **Corrected: table-level
`INSERT` is revoked from `rag_platform_app`, then re-granted only on
every column **except** `target_reference_status`.** PostgreSQL's
column-level `INSERT` privilege model makes this exact: a role granted
`INSERT` on a table but not on one specific column may still `INSERT`
rows that omit that column from the target list (receiving its declared
`DEFAULT 'live'`, per the column's own definition above), but any
`INSERT` statement that explicitly names `target_reference_status` in its
column list fails with a permission-denied error before the statement
ever executes — regardless of what value it tried to supply. The frozen
membership `INSERT ... SELECT` never names this column (every frozen row
is `live` by construction, at the moment of freezing), so ordinary
operation is entirely unaffected.

**The guard trigger is extended to `BEFORE INSERT OR UPDATE`** (previously
`BEFORE UPDATE` only), so a hypothetical future privilege regression that
widened `rag_platform_app`'s grant back to the full row is still caught:
on `INSERT`, it rejects `NEW.target_reference_status <> 'live'` unless
`current_user = 'rag_platform_owner'` — allowing the owner-executed
migration/backfill path (via `rag_platform_migrator`'s `SET ROLE`) to
insert a historical `target_deleted` row directly if a data migration
ever genuinely needs to, while runtime never can, through any column
grant, mass-assignment, raw SQL, or session-state manipulation. On
`UPDATE`, its existing live-to-deleted check is unchanged.

The child guard accepts a `target_deleted` row — by `INSERT` or by the
live-to-deleted `UPDATE` transition — only while `current_user =
'rag_platform_owner'`; protected-column privileges are the load-bearing
boundary for both statement types, and this owner-identity check is
defence in depth for both. Ordinary runtime cannot forge the owner
identity, inherit it, authenticate as it, or assume it. The role
boundary — not caller-provided context — is the database-enforced
provenance that only the target-side trigger, or a genuine owner-executed
migration, ever produces a retained-deleted row.
- **Worked through, concretely**: a `DELETE FROM document_families WHERE
  id = :id` (issued by `rag_platform_app`, ADR-0031's own deletion
  action) fires `retire_bulk_operation_item_targets()`; because the
  function is `SECURITY DEFINER` owned by `rag_platform_owner`, its own
  `UPDATE bulk_operation_items ...` executes as `rag_platform_owner`, is
  permitted by that role's full ownership privileges, and succeeds. A
  hypothetical direct attempt by application code —
  `UPDATE bulk_operation_items SET target_reference_status =
  'target_deleted' WHERE id = :id`, issued as `rag_platform_app` — fails
  with a PostgreSQL permission-denied error **before** the guard trigger
  even runs, because `rag_platform_app` was never granted `UPDATE` on
  that column at all. Supplying arbitrary caller-controlled session
  context before retrying the identical `UPDATE` fails identically — the
  column-level grant is independent of such context. **An `INSERT
  INTO bulk_operation_items (..., target_reference_status, ...) VALUES
  (..., 'target_deleted', ...)`, issued as `rag_platform_app` through
  raw SQL or an ORM mass-assignment path that names the column
  explicitly, fails identically at the privilege-check stage** — Eloquent
  mass assignment cannot bypass a database-level column grant, since the
  grant is checked by PostgreSQL itself, not by application code. An
  ordinary frozen-membership `INSERT` that never names the column
  receives `'live'` from the column default and succeeds normally.
- **Behaviour named explicitly per target type**: for a **family**
  deletion, ADR-0031's family-deletion preview/confirm flow ultimately
  issues an ordinary `DELETE` against `document_families`, which the
  retirement trigger intercepts exactly as described above. For a
  **version** (`Document`) deletion, the same applies against `documents`.
  For an **`ImportItem`**, this ADR distinguishes two genuinely different
  events that the brief's word "expiry" could mean: (1) **staging
  expiry** — ADR-0034's own retention window elapsing — is a **status
  change** (`staging_expired`), never a row deletion; the `ImportItem` row
  continues to exist, so **no retirement-trigger transition ever occurs**
  for this case, and a bulk-promotion item targeting it is instead
  `skipped` with the existing typed reason `staging_expired` (per the
  per-operation contract matrix above), which already covers this
  honestly without needing the target-deletion machinery at all; (2) an
  **actual `ImportItem` row deletion** (e.g. batch purge after retention),
  which the retirement trigger handles identically to a family or version
  deletion. **Only genuine row deletion ever exercises the retirement
  trigger; a status-only expiry never does**, and this ADR's typed
  exclusion/skip vocabulary already distinguishes the two honestly.
- **Execution racing target deletion**: the per-item finalize transaction
  (below, "Idempotency and concurrency") acquires a row lock on the
  specific `bulk_operation_items` row it is finalising; the retirement
  trigger's own `UPDATE` against that same row, fired from a concurrent
  `DELETE` on the target table, requires the identical row lock. PostgreSQL
  serialises the two ordinarily: whichever transaction's statement
  reaches the row first proceeds, and the other blocks until the first
  commits or rolls back, then proceeds against the now-current row —
  **no special handling is required, because both are ordinary row-level
  writes to the same row, and PostgreSQL's own lock manager already
  totally orders them.** Two genuinely distinguishable outcomes result,
  both honest: if the item's own finalize transaction commits first, the
  target deletion's retirement `UPDATE` then finds `target_reference_status`
  already `target_deleted` is **not** the case — it finds the item already
  terminal (e.g. `succeeded`) with its target FK still live, and the
  retirement `UPDATE`'s `WHERE <column> = OLD.id` clause still matches
  (the FK was never nulled by the item's own finalize step), so the
  retirement transition still applies correctly to the now-terminal row,
  retaining `target_kind`/`target_public_id`/`target_display_label` and
  the item's own terminal outcome exactly as already committed — this is
  the "terminal-lineage retention" case the item-level constraints above
  explicitly allow. If the retirement trigger's `UPDATE` commits first
  (the target was deleted before this item was claimed), the item's own
  subsequent claim/finalize transaction observes `target_reference_status
  = 'target_deleted'` at its very first revalidation step and commits
  `skipped`/`target_no_longer_exists` instead of attempting a mutation
  against a target that no longer exists — **no third, inconsistent
  outcome is reachable**, because the row lock makes the two transactions'
  relative order well-defined rather than a race with an undefined
  result.
- **Ordinary callers are prevented from manufacturing the
  retained-deleted shape directly, on both write paths** — from ever
  setting `target_reference_status = 'target_deleted'` via `UPDATE`, or
  inserting a row already in that shape via `INSERT`, through any path
  other than the retirement trigger or a genuine owner-executed migration.
  A second trigger, `guard_bulk_operation_item_target_retirement()`, fires
  `BEFORE INSERT OR UPDATE` on `bulk_operation_items`: on `UPDATE`, it
  rejects any attempted write where `NEW.target_reference_status =
  'target_deleted' AND OLD.target_reference_status = 'live'` unless
  `current_user = 'rag_platform_owner'`; on `INSERT`, it rejects any new
  row where `NEW.target_reference_status <> 'live'` unless `current_user
  = 'rag_platform_owner'`. The only runtime-reachable path with that
  identity is the target-side `SECURITY DEFINER` retirement trigger
  already installed by the owner. A direct child `INSERT` or `UPDATE` is
  denied first by the protected-column grant boundary (see "Retirement
  provenance is a database privilege boundary" above for the `INSERT`
  side specifically) and, if a future grant regression ever widened it,
  again by this guard. The transition — or the row's very existence in
  the retained shape — is therefore attributable to target-side trigger
  execution or genuine owner authority, never to caller-manufactured
  context.
- **This is the second and third place this ADR's migration requires
  explicit, raw PostgreSQL DDL** (`DB::unprepared`) **rather than
  Laravel's schema-builder abstraction alone** — alongside the
  cross-workspace trigger below, the schema builder has no API for
  authoring either the retirement trigger or its guard, exactly as it has
  none for ADR-0017's own analogous guards.
- **Each target FK is deliberately single-column**, referencing only the
  target table's own `id` — **not** composite with `workspace_id`. This
  is the specific fix the prior draft's composite-FK design needed:
  PostgreSQL's `ON DELETE SET NULL` nulls **every** column participating
  in the foreign key together: a composite `(target_family_id,
  workspace_id)` FK would null the item's own `workspace_id` the moment
  its target was deleted — destroying exactly the tenancy scoping this
  ADR's retained history depends on. Keeping the target FK single-column
  means only `target_family_id` (etc.) is ever nulled; `workspace_id`
  survives untouched, because it was never part of that FK's own column
  set.
- **Cross-workspace safety for the target reference is therefore enforced
  by a database trigger, not the FK's own shape** — the same pattern this
  codebase already established for a cross-table invariant a plain
  `CHECK` cannot express (ADR-0017's migration already defines
  `enforce_document_lineage()`/`enforce_organisational_location_hierarchy()`
  as raw, `DB::unprepared`-authored trigger functions for precisely this
  reason). A new trigger,
  `enforce_bulk_operation_item_target_workspace()`, fires `BEFORE INSERT
  OR UPDATE` on `bulk_operation_items` and, for whichever of the three
  target columns is non-null, looks up that target row's own
  `workspace_id` and raises an exception if it does not equal `NEW.
  workspace_id`. **This trigger is one of the three places this ADR's
  migration requires explicit, raw PostgreSQL DDL rather than Laravel's
  schema-builder abstraction alone**, alongside the retirement trigger and
  its guard above — the builder has no API for authoring any of the
  three, exactly as it has none for ADR-0017's own analogous guards.
  Setting a column to `NULL` (the retirement trigger's own path, above)
  never enters this trigger's violation branch, since a null target
  trivially has nothing to compare — the two triggers never conflict.
- **`workspace_id`, `target_kind`, and `target_public_id` are retained
  permanently**, immune to any target's later deletion, exactly per the
  required shape: `workspace_id` because it was deliberately kept outside
  the nullable FK's own column set; `target_kind` and `target_public_id`
  because they are plain scalar columns, never foreign keys at all, and
  neither is touched by the retirement trigger's `UPDATE`.
- **Pending execution sees a retired target honestly**: the per-item
  revalidation step, immediately before mutation, checks
  `target_reference_status` first — if it is `target_deleted`, the item
  is marked `skipped` with the typed reason **`target_no_longer_exists`**
  without needing to inspect the (necessarily null) target FK at all,
  distinct from an `expected_state_snapshot` mismatch (below), and
  distinct from `authorization_insufficient`.
- **A retained `BulkOperationItem` never blocks, and is never blocked by,**
  ADR-0025's or ADR-0031's own deletion paths — satisfied structurally by
  the retirement trigger performing the live-to-retained transition inside
  the deleting transaction itself, with no further coordination required
  between this ADR and either of theirs.

**Constraints, stated explicitly:**

- **One target once per operation**: `UNIQUE (bulk_operation_id,
  target_family_id)`, `UNIQUE (bulk_operation_id, target_document_id)`,
  `UNIQUE (bulk_operation_id, target_import_item_id)` — each a partial
  unique index active only where the respective column is non-null. **A
  target retired by the deletion trigger no longer occupies this
  uniqueness space** — a deliberate, harmless consequence of the
  retirement transition, since a deleted target can never legitimately be
  re-targeted by a second item in the same operation regardless.
- **Immutable membership**: once a `BulkOperationItem` row is inserted at
  freeze time, `bulk_operation_id`, `operation_type`, `target_kind`,
  `target_public_id`, and `target_display_label` are never updated — only
  the live target FK and `target_reference_status` (solely via the
  retirement trigger, together, exactly once, never an application write,
  and never reverted once `target_deleted`), `execution_status`,
  `terminal_reason`, `started_at`/`completed_at`, `subordinate_kind`/
  `subordinate_identity_kind`/`subordinate_identity_value`/
  `subordinate_awaited_since`, `result_identity`, and `audit_event_id` are
  ever written afterward — **`eligibility_status` and `exclusion_reason`
  are fixed at freeze/preflight and never updated again either**, and
  **this entire list is now database-enforced, not merely prose**: it is
  exactly the column-level `UPDATE` grant `rag_platform_app` receives on
  this table, per "Retirement provenance is a database privilege
  boundary" above. `attempt_count` does not exist on this table at all —
  see "Introducing a durable per-item attempt/failure authority" below.
- **Terminal immutability**: once `execution_status` reaches `succeeded`,
  `skipped`, `failed_permanent`, or `cancelled`, no further write to that
  row's execution-related columns is permitted — a retry (below) creates
  work under a **new** `BulkOperation`, never mutates a terminal item.
- **Ordinal uniqueness**: `UNIQUE (bulk_operation_id, ordinal)`, assigned
  once, at freeze time, never reassigned.
- **Actor provenance**: the initiating actor recorded on the parent
  `BulkOperation` is who every item's mutation is attributed to for
  authorization purposes — no separate, per-item initiating actor exists.
  **The Laravel job or reconciliation pass that actually executes or
  reconciles an item runs under its own, separately tracked system
  identity, which never overwrites, and is never recorded as, the
  parent's own initiating-actor provenance** — see "Authorization and
  tenancy" below for the complete initiating-versus-executing distinction.
- **Deletion/retention**: `BulkOperationItem` rows are **never deleted**
  as part of ordinary operation completion — they are the permanent,
  retained history of what this operation did, readable forever through
  `target_kind`/`target_public_id`/`target_display_label` regardless of
  what happens to the live target FK.

**No derived aggregate is ever stored as independent authority.** A
`BulkOperation`'s total/eligible/excluded/succeeded/failed/skipped counts
are **always computed from `BulkOperationItem` rows at read time** — never
a maintained counter column on the parent capable of drifting from the
rows it purports to summarise.

### Selection semantics and frozen membership

**A stored query is never a frozen target set.** The library's selection
control (ADR-0033's reserved seam) distinguishes:

- **Current-page selection**: the rows visible on the current page,
  individually checked.
- **An explicit second action, "select every result matching the current
  filters"** — never the default, never triggered by the page checkbox
  alone. Once chosen, the interface shows the **exact frozen count** (not
  an estimate) and a clear control to return to page-only selection.

**Changing a filter, search term, sort, historical-inclusion setting, or
saved view after a selection has been made visibly clears the selection**
— it is never silently retargeted to whatever the new filter now matches.

**At confirmation — the exact freeze mechanism:**

1. Laravel resolves the current selection/filter **exactly once**, as one
   bounded, set-based `INSERT ... SELECT` (or an equivalent set-based
   statement) against the **same library query contract ADR-0033 already
   defines** (for family/version-targeted operations) or the **same
   `ImportItem` query contract ADR-0034 already defines** (for
   promotion) — never a row-by-row loop issuing one browser request per
   target.
2. Workspace scope and current authorization are enforced **inside** this
   selection query itself (a `WHERE workspace_id = :current_workspace`
   clause bound to the authenticated actor's own workspace, never a
   filter applied after the fact) — a cross-workspace target can never
   enter the frozen set even transiently.
3. Stable ordinal assignment happens as part of the same statement (a
   window-function `ROW_NUMBER()` or equivalent), never a second pass.
4. **Page selection freezes only the checked rows**; all-filtered
   selection freezes every row the current filter predicate matches,
   evaluated once, at this exact moment.
5. **A configured maximum target count bounds the freeze** — a selection
   exceeding it is rejected outright at confirmation with a clear,
   actionable message (narrow the filter, or select fewer rows), never
   silently truncated to the first N matches. The exact numeric bound is
   R25/R26 implementation measurement, not fixed here.
6. **Transaction duration is monitored, not merely assumed safe** — the
   freeze statement's execution time is measured against a configured
   threshold during implementation; if a representative worst-case
   selection cannot complete the bounded `INSERT ... SELECT` within a
   safe transaction duration, the maximum target count (step 5) is
   tightened, rather than the freeze itself being decomposed into
   multiple transactions that could observe an inconsistent view.
7. **The original filter/query is retained on `BulkOperation.filter_
   explanation` for explanation and audit only** — execution never
   re-runs it to discover additional targets, and membership never grows
   or shrinks after this point.

**Each frozen item is revalidated immediately before its own mutation**
(below) — a state change after confirmation can cause an item to be
skipped or failed with a typed reason, but can never substitute a
different target into the frozen set.

### Preflight and exclusion model

**Before execution**, Laravel presents, computed from the just-frozen
`BulkOperationItem` rows:

- Total frozen targets.
- Eligible count and excluded count.
- **Grouped, typed exclusion reasons** — drawn from the **closed,
  per-operation category matrix** "Per-operation contract matrix" below
  defines for every V1 operation — never free text, and never left
  "enumerated at implementation time": every stable semantic category is
  fixed in this ADR; only the low-level infrastructure code beneath each
  category is implementation detail.
- The requested change (the canonical payload) and the scope affected.
- **An explicit confirmation action.**

**Excluded targets never prevent eligible targets from proceeding** unless
an operation is explicitly defined as all-or-nothing — **no V1 operation
in the allowlist above is all-or-nothing**; the default, for every
included operation, is partial execution with honest per-item results.

**Aggregate confirmation, not 200 individual clicks**: the confirmation
surface presents one aggregate summary with searchable/filterable item
detail (by exclusion reason, by target name) and an explicit exception/
exclusion review section — a user confirms the *operation*, having been
shown, and able to drill into, exactly what it will and will not affect.

**An operation whose frozen set contains zero eligible items** (every
target excluded) **can still be confirmed** — confirmation is never
withheld merely because nothing is eligible, since the honest, visible
outcome "nothing was eligible" is itself useful information. It reaches
the truthful terminal outcome `completed_with_exclusions` (zero succeeded,
100% excluded) — see "Parent state machine" below for why this is
deliberately distinct from an ordinary `completed` result.

### Parent state machine — one ordered, total terminal-mapping function

**Withdrawn: restating the terminal mapping as parallel prose bullets, a
separate distribution table, and an ASCII diagram — three descriptions of
the same fact, already found to drift out of sync with each other as this
ADR was corrected.** Replaced with exactly **one normative, ordered,
total function** — total in the precise sense that it is defined for
**every** reachable input, including the zero-item and cancellation-race
edge cases the prior prose left implicit. **Every other section, every
test, and the final report below reference this function by name (the
"terminal-mapping function") rather than restate its logic.**

```
resolve_bulk_operation_terminal_state(
  freeze_outcome,          -- 'succeeded' | 'failed'
  total_item_count,        -- integer, >= 0, valid only if freeze_outcome = 'succeeded'
  cancellation_requested,  -- boolean
  item_distribution        -- counts, by execution_status, over every frozen item
):

  0. IF freeze_outcome = 'failed':
       RETURN failed_before_execution
       -- No BulkOperationItem row was ever durably committed; this state
       -- is reached instead of awaiting_confirmation entirely, since
       -- confirmation was never possible without a completed freeze.

  1. IF total_item_count = 0:
       RETURN completed_with_exclusions
       -- A deliberate, named no-action outcome, not a fabricated eighth
       -- state: a freeze that matched zero targets is, from this closed
       -- vocabulary's perspective, indistinguishable from "every target
       -- was excluded" — both truthfully mean "nothing was, or could be,
       -- mutated." This is the one explicitly decided answer to the
       -- zero-total-item case; no other code path may reach a different
       -- outcome for total_item_count = 0.

  2. IF any item is currently in a NON-TERMINAL execution_status
     (eligible, failed_retryable, or waiting_on_subordinate):
       RETURN not-yet-terminal  -- the parent remains `running`; this
       -- function is not invoked again for this operation until the next
       -- item reaches one of its own terminal outcomes, per the
       -- parent-lock convergence protocol below.

  -- From this point on, every item is in one of its own terminal states:
  -- excluded, succeeded, skipped, failed_permanent, or cancelled.

  3. IF cancellation_requested:
       IF count(succeeded) + count(skipped) + count(failed_permanent) = 0:
         RETURN cancelled
         -- No eligible item ever reached a non-cancelled execution
         -- outcome; every eligible item ended `cancelled`.
       ELSE:
         RETURN cancelled_after_partial_execution
         -- At least one eligible item reached succeeded, skipped, or
         -- failed_permanent before convergence. This NEVER implies
         -- rollback — those items' mutations remain fully committed and
         -- authoritative.

  4. ELSE (cancellation was never requested):
       IF count(skipped) + count(failed_permanent) + count(cancelled) > 0:
         RETURN completed_with_exceptions
         -- `cancelled` is included here defensively: this item state is
         -- reachable only when cancellation_requested is true (per "Item
         -- state machine" below), so a `cancelled` item observed under
         -- cancellation_requested = false is a data-integrity condition
         -- this function still maps to exactly one honest outcome,
         -- rather than being undefined, rather than silently reporting
         -- `completed`.
       ELSE IF count(excluded) > 0:
         RETURN completed_with_exclusions
         -- Preflight exclusion is a distinct concept from execution
         -- failure, never folded into either `completed` or
         -- `completed_with_exceptions`.
       ELSE:
         RETURN completed
```

**This function is total**: every one of its four numbered branches
(after the zero-item short-circuit) is mutually exclusive and
collectively exhaustive over `{freeze_outcome, total_item_count,
cancellation_requested, item_distribution}` — there is no reachable
combination of inputs for which it is undefined, and no combination maps
to more than one of the six closed terminal values plus the
explicit not-yet-terminal result.

**Cancellation precedence controls only the headline terminal state, and
never hides detail**: a `cancelled_after_partial_execution` result is
always accompanied by the same fully itemised counts (succeeded/skipped/
failed_permanent/excluded) any other terminal state already exposes,
computed identically, at read time, from `BulkOperationItem` rows — this
function decides which single closed-vocabulary label best characterises
the operation as a whole; it never suppresses, aggregates away, or
implies a rollback of any individual item's own already-committed result.

**State-machine skeleton, referencing the function above rather than
restating its branches**:

```
preparing_membership → awaiting_confirmation → queued → running
running → (any of the six terminal values the function above returns)
(freeze failure) → failed_before_execution   -- via branch 0, bypassing
                                              -- awaiting_confirmation entirely
```

### Item state machine — corrected, with subordinate waiting and no
### relabelling of committed work

**Withdrawn: `claimed → cancelled`.** An item that has genuinely begun
executing is never relabelled `cancelled` — it always reaches its own
truthful outcome (`succeeded`, `skipped`, `failed_retryable`/
`failed_permanent`, or `waiting_on_subordinate`) instead. `cancelled` is
reachable **only** from `eligible` (work that was never started).

```
eligible → succeeded
eligible → skipped (target_no_longer_exists, discovered before any
                     attempt row exists — started_at stays null)
eligible → failed_retryable
failed_retryable → failed_retryable (reclaimed directly — never back
                                       through `eligible` — for another
                                       bounded attempt, within the
                                       ceiling; a fresh generation opens,
                                       `started_at` is unchanged from the
                                       item's first attempt)
failed_retryable → skipped (expected-state mismatch found and
                              incorporated at a later attempt's own
                              mutation boundary — started_at is already
                              non-null from the first attempt)
                 ↘ failed_permanent (ceiling exhausted)
eligible → waiting_on_subordinate → succeeded
                                  ↘ failed_permanent (subordinate's own
                                                        terminal failure)
eligible → cancelled (cancellation converges before this item starts)
```

*(`excluded` is fixed at confirmation and never entered from `eligible` —
it is a preflight-time-only classification, not a reachable execution
transition; see the `eligibility_status`/`execution_status` reconciliation
above.)*

| State | Meaning | Terminal? |
|---|---|---|
| `excluded` | Fixed at preflight; never enters execution at all | Yes |
| `eligible` | Frozen, passed preflight, not yet claimed or currently being attempted within one short transaction | No |
| `succeeded` | The invoked single-item action (or its resolved subordinate workflow) completed and committed | Yes |
| `skipped` | Before any mutation, revalidation found the target gone (`target_no_longer_exists`) or its expected state changed (`expected_state_snapshot` mismatch) — a typed, honest non-mutation, never a failure | Yes |
| `failed_retryable` | A transient failure within one attempt, durably recorded on that attempt's own `BulkOperationItemAttempt` row (below) and incorporated into the item — claimed directly (never routed back through `eligible` first) for another bounded attempt, below ceiling | **No, everywhere in this design** — never carries `completed_at`/`audit_event_id`, per the item-level constraints above |
| `failed_permanent` | The durable attempt ceiling is exhausted (computed from `BulkOperationItemAttempt` rows, never a mutable counter), the single-item action itself rejected the mutation for a non-transient reason, or a subordinate workflow reached one of the terminal outcomes the ADR-0034/ADR-0031 mapping tables below classify as permanent | **Yes, everywhere in this design** |
| `waiting_on_subordinate` | The bulk item's own initiating action has committed (a `PromotionAttempt` or clone/fallback operation now exists), and this item is waiting for that durable subordinate workflow to reach its own terminal outcome — see "Subordinate-waiting semantics" below | No |
| `cancelled` | Cancellation converged while this item was still `eligible` and had not yet been attempted | Yes |

**There is no separately-tracked "claimed but not yet executing"
`BulkOperationItem.execution_status` distinct from "executing"** — the
item's own `execution_status` column only ever holds `eligible` before an
attempt and one of its terminal values (or `waiting_on_subordinate`)
after one; the durable in-progress fact — that an attempt is currently
open — is tracked instead by a `BulkOperationItemAttempt` row's own
`status = 'open'`, per "Introducing a durable per-item attempt/failure
authority" below, never by `BulkOperationItem.execution_status` itself.

**No committed mutation is ever relabelled by a later cancellation
request** — `succeeded` and `failed_permanent` are exactly as terminal
and immutable as `excluded` and `cancelled`.

### Idempotency and concurrency

**Withdrawn completely: any earlier notion of one open bulk operation per
workspace.** Any number of `BulkOperation`s, including of the same
`operation_type`, may be open concurrently in one workspace.

**Client-idempotency identity:**

```
UNIQUE (workspace_id, actor_identity, operation_type, client_idempotency_key)
```

with a canonical request digest stored alongside (the same RFC 8785/
SHA-256 approach ADR-0032/0034 already establish) — `actor_identity`
reuses ADR-0034's own non-nullable, human/system-namespaced column design
(`user:{id}` / `system:{code}`), closing the same nullable-column
deduplication gap that correction already fixed there.

- Same identity, same digest → returns the existing operation/result.
- Same identity, different digest → a typed conflict, rejected outright.

**Withdrawn: the prior "one short transaction from claim through commit"
model, on three independent grounds Codex identified across two passes.**
First, a transaction that itself aborts (a caught database error, a
crash) cannot durably record its own failure or increment its own retry
count against the row it never committed — "the current model can retry
forever." Second, committing the parent's own terminal-status write "in
the same transaction as the last item's own terminal commit" is unsafe
under PostgreSQL `READ COMMITTED`: two items finishing at nearly the same
moment, in two different transactions, can each independently query the
item distribution, each see the *other's* work as not-yet-committed, and
each conclude a different (and potentially both-wrong) parent status,
racing to write it. **Third — identified in this pass** — asserting that
`ApproveDocumentVersion` and the ADR-0030 metadata actions "need no
additional idempotency primitive because they are naturally idempotent"
was true of their **domain effect** but false of their **auditable
side-effects**: `ApproveDocumentVersion` requires `DRAFT`, sets
`approved_at`, writes a governance audit event, and **rejects a second
invocation once the version is no longer `DRAFT`** — it is not, in fact,
safely replayable "as though nothing happened," because a naive replay
after a crash would hit that very rejection and be misread as a genuine
failure. **All three are corrected together below.**

#### Global lock order — one sequence, stated once, used everywhere

**Every transaction first determines its complete lock set without
retaining an item lock, then follows exactly one of these sequences:**

1. **Claim**: item (`I`) only. It never locks a target, attempt, or parent.
2. **Approval mutation**: family (`F`, where required by governance), then
   every `Document` in that family (`D`) in ascending immutable internal
   ID order, then attempt (`A`). The selected document is identified and
   revalidated inside that already-locked collection. The transaction
   never later issues an unordered family-wide `lockForUpdate()` and never
   locks the item or parent.
3. **Family metadata mutation**: family target (`F`), then attempt (`A`),
   with no item or parent lock.
4. **Subordinate initiation**: the subordinate action's complete target
   and identity lock set (`S`) in the deterministic order frozen by
   ADR-0034 or ADR-0031, then attempt (`A`), then item (`I`) for the short
   atomic write of its current subordinate identity and waiting state.
   The item lock is released at commit before any external or subsequent
   subordinate execution.
5. **Item/parent finalisation**: parent (`P`), then item (`I`), followed by
   an ordinary MVCC read—not a row lock—of immutable terminal attempt
   evidence. It acquires neither target nor attempt lock.
6. **Target deletion**: target (`T`), then item (`I`) through the retirement
   trigger. It acquires neither parent nor attempt.
7. **Reclaimer**: attempt (`A`) only and acquires nothing afterward.

**Required `ApproveDocumentVersion` refactor**: the current action locks
the selected document and later performs an unordered family-wide
`lockForUpdate()`, allowing two approvals in one family to hold different
documents and wait on one another. Before bulk approval is enabled, the
action must determine family identity without retaining an item lock; lock
the family row when governance requires it; lock all family documents in
ascending immutable internal ID order; revalidate the selected document
from that set; then lock/fence the attempt and execute approval using the
already-locked collection. Approval, `approved_at`, audit,
governance-idempotency result, and attempt success commit atomically. This
is a lock-order refactor only and changes no ADR-0017/ADR-0031 governance
semantics.

**Cycle-freedom with the real item write included**: mutation and
subordinate paths obey target/identity-before-attempt; subordinate
initiation alone continues from attempt to item. No path locks an item and
then waits for a target or attempt. Claim holds only the item and commits;
finalisation holds parent then item but never waits for target/attempt;
deletion holds target then item but never parent/attempt; database-local
mutation never locks item/parent; the reclaimer holds only attempt. Thus
the relevant partial orders are `{F,D,S,T} < A`, `A < I` only for
subordinate initiation, `{S,T} < I`, and `P < I`, with no reverse edge and
therefore no cycle. Query-by-query inspection of every frozen subordinate
action remains a fail-closed implementation acceptance gate: if an action
acquires item before its target/identity or attempt, bulk use of that
action is blocked until corrected. Repeated PostgreSQL deadlock stress of
approval, metadata, subordinate initiation, target deletion, reclaimer,
and finalisation is mandatory.

**In particular, and stated as its own rule because Codex asked for it
explicitly: claim-time target locking while holding the item lock is
prohibited.** Sequence 1 (claim) never acquires a target lock — this is
precisely what prevents an item-before-target/attempt edge.

#### Introducing a durable per-item attempt/failure authority

**A new table, `BulkOperationItemAttempt`, owned entirely by Laravel — no
Python HMAC worker pattern is borrowed; it is not needed, since no
external trust boundary is crossed by this table at all.**

| Column | Constraint |
|---|---|
| `id` | Internal identity — sufficient on its own, since this table is never browser-facing |
| `bulk_operation_item_id`, `workspace_id` | Composite FK `(bulk_operation_item_id, workspace_id)` references `bulk_operation_items (id, workspace_id)` — safe as a composite FK here (unlike the target FKs above) because `BulkOperationItem` rows are **never deleted**, so `ON DELETE` behaviour is never exercised |
| `attempt_ordinal` | `UNIQUE (bulk_operation_item_id, attempt_ordinal)` — monotonically assigned per item, never reused or rewritten |
| `status` | Bounded enum: `open`, `succeeded`, `not_applied`, `failed_retryable`, `failed_permanent`, `abandoned` |
| `lease_expires_at` | Non-nullable on every row, set once at creation to a fixed, configured lease duration and retained historically after terminalisation — **no heartbeat/renewal mechanism exists or is needed**, because every attempt's own database-only work is short and bounded by construction (per the non-negotiable ownership boundary above: no S3/Qdrant/HTTP call, no wait on another job's result, ever occurs under an open attempt) |
| `failure_category` | Nullable, bounded typed vocabulary — populated if and only if `status` is `failed_retryable`, `failed_permanent`, or `abandoned` |
| `not_applied_reason` | Nullable, bounded typed vocabulary — populated if and only if `status = 'not_applied'`; includes `expected_state_mismatch` and is never treated as infrastructure failure or retry authority |
| `started_at`, `completed_at` | Both non-nullable except that `completed_at` is null while `status = 'open'`; `completed_at` is required for every terminal status |
| `executor_identity` | Non-nullable — the specific system/worker process identity that owns this attempt, distinct from, and never confused with, the `BulkOperation`'s own initiating-actor provenance (see "Authorization and tenancy" below) |
| `invocation_idempotency_key` | The key this attempt passes to the underlying single-item domain action, derived from this attempt's own stable `(bulk_operation_item_id, attempt_ordinal)` pair — see "Idempotent invocation" below |
| `attempt_token` | **Non-nullable**, a randomly generated opaque value set once at creation — this attempt's own unforgeable fencing identity, distinct from `id` (an internal auto-increment `id` is guessable/enumerable and unsuitable as a fencing credential handed to worker code; `attempt_token` is not) |
| `generation` | **Non-nullable**, allocated for every new attempt as `COALESCE(MAX(previous generations), 0) + 1`, with `UNIQUE (bulk_operation_item_id, generation)`; historical rows are never rewritten |
| `success_kind` | Nullable unless `status = 'succeeded'`; bounded enum `database_local` \| `subordinate_initiated` |
| `result_digest` | Nullable — required only on `status = 'succeeded'`; canonical RFC 8785/SHA-256 over the actual resulting database-local state or over the exact subordinate kind/identity that initiation committed |
| `result_subordinate_kind`, `result_identity_kind`, `result_identity_value` | Nullable all-or-none evidence; required for `subordinate_initiated`, with the same kind-valid subordinate-kind/`public_id`/`event_id` binding used by item subordinate lineage; absent for `database_local` unless a future accepted action deliberately extends this closed contract |

**Database constraints:**

```sql
-- Exactly one open attempt per item, ever, at a time.
CREATE UNIQUE INDEX ON bulk_operation_item_attempts (bulk_operation_item_id)
  WHERE status = 'open';

UNIQUE (bulk_operation_item_id, generation);

CHECK (
  (status = 'open'
      AND lease_expires_at IS NOT NULL AND completed_at IS NULL
      AND failure_category IS NULL AND not_applied_reason IS NULL
      AND success_kind IS NULL AND result_digest IS NULL
      AND result_subordinate_kind IS NULL
      AND result_identity_kind IS NULL AND result_identity_value IS NULL)
  OR (status = 'not_applied'
      AND lease_expires_at IS NOT NULL AND completed_at IS NOT NULL
      AND failure_category IS NULL AND not_applied_reason IS NOT NULL
      AND success_kind IS NULL AND result_digest IS NULL
      AND result_subordinate_kind IS NULL
      AND result_identity_kind IS NULL AND result_identity_value IS NULL)
  OR (status IN ('failed_retryable', 'failed_permanent', 'abandoned')
      AND lease_expires_at IS NOT NULL AND completed_at IS NOT NULL
      AND failure_category IS NOT NULL AND not_applied_reason IS NULL
      AND success_kind IS NULL AND result_digest IS NULL
      AND result_subordinate_kind IS NULL
      AND result_identity_kind IS NULL AND result_identity_value IS NULL)
  OR (status = 'succeeded' AND success_kind = 'database_local'
      AND lease_expires_at IS NOT NULL AND completed_at IS NOT NULL
      AND failure_category IS NULL AND not_applied_reason IS NULL
      AND result_digest IS NOT NULL
      AND result_subordinate_kind IS NULL
      AND result_identity_kind IS NULL AND result_identity_value IS NULL)
  OR (status = 'succeeded' AND success_kind = 'subordinate_initiated'
      AND lease_expires_at IS NOT NULL AND completed_at IS NOT NULL
      AND failure_category IS NULL AND not_applied_reason IS NULL
      AND result_digest IS NOT NULL
      AND result_subordinate_kind IS NOT NULL
      AND result_identity_kind IS NOT NULL AND result_identity_value IS NOT NULL)
)

CHECK (
  (result_subordinate_kind IN ('promotion_attempt', 'content_clone_operation')
      AND result_identity_kind = 'public_id')
  OR (result_subordinate_kind = 'full_ingestion_fallback'
      AND result_identity_kind = 'event_id')
  OR (result_subordinate_kind IS NULL
      AND result_identity_kind IS NULL AND result_identity_value IS NULL)
)
```

The subordinate-success branch is paired with a second CHECK binding
`result_identity_kind` to the recorded subordinate kind. Every
non-success branch requires `result_digest IS NULL`; result identity is
never partial. `attempt_token`, `generation`, `attempt_ordinal`,
`invocation_idempotency_key`, `executor_identity`, `started_at`, and
`lease_expires_at` are immutable after insert. A terminal-attempt guard
rejects every later status/result rewrite, and runtime column grants allow
only the single open-to-terminal transition fields needed by the attempt
reporter. The fixed historical lease timestamp is never interpreted as a
currently live lease once status is terminal.

#### The item-to-attempt incorporation marker

**Withdrawn: committing an attempt's terminal outcome and incorporating
that outcome into `BulkOperationItem` as two independently-timed
transactions with no durable link between them.** Codex correctly
identified the resulting crash window: between the attempt's own terminal
commit and the later, separate item-finalisation transaction, the item's
`execution_status` can still read `eligible`/`failed_retryable`, and
nothing previously stopped the claim query from opening a **second**
attempt in that window — repeating an already-succeeded mutation.

**Selected: `BulkOperationItem.incorporated_attempt_generation`** — chosen
over inventing a new noun because it reuses `generation`, the column this
ADR's own attempt table already uses as the stable per-item attempt
ordinal, rather than introducing a second numbering scheme for the same
concept.

- **Nullable, and null exactly until this item's first attempt has been
  incorporated** — an item that has never been claimed carries no
  generation to point to.
- **Identifies the exact attempt generation whose terminal result has
  been incorporated into this item's own `execution_status`/
  `terminal_reason`/`completed_at`/`audit_event_id`** — not merely "the
  latest attempt that exists," but specifically the one this item's
  current state was actually derived from.
- **Bound to an attempt belonging to the same item by a composite foreign
  key**, not application convention: `(id, incorporated_attempt_generation)
  REFERENCES bulk_operation_item_attempts (bulk_operation_item_id,
  generation)` — this is what makes "cannot point to a generation
  belonging to another item" a structural fact rather than a hope; the FK
  simply cannot resolve against a `(bulk_operation_item_id, generation)`
  pair that does not exist for **this** item's own `id`.
- **A dedicated trigger enforces the two properties a plain FK cannot
  express**: `enforce_bulk_operation_item_incorporation()`, `BEFORE
  UPDATE` on `bulk_operation_items`, fires whenever
  `NEW.incorporated_attempt_generation IS DISTINCT FROM
  OLD.incorporated_attempt_generation` and:

  1. **Rejects regression**: raises an exception if
     `OLD.incorporated_attempt_generation IS NOT NULL AND
     NEW.incorporated_attempt_generation < OLD.incorporated_attempt_generation`.
     Writing the **same** value again (a retried, duplicate finalisation)
     is explicitly permitted — this is what makes duplicate finalisation
     of the same generation an idempotent no-op rather than an error.
  2. **Rejects advancing past an open attempt**: looks up
     `bulk_operation_item_attempts` for `(NEW.id,
     NEW.incorporated_attempt_generation)` and raises an exception unless
     that attempt's own `status <> 'open'` — the marker can only ever
     name a generation whose own terminal outcome already exists.

  This is a cross-table read inside a trigger, exactly the same
  established pattern this ADR already uses for the workspace-scoping and
  retirement guards above — not a plain `CHECK`, because a `CHECK` cannot
  compare `OLD` to `NEW` or inspect another table.
- **Atomicity with item terminalisation**: every write to
  `incorporated_attempt_generation` happens in the **same** `UPDATE
  bulk_operation_items ...` statement that also writes
  `execution_status`/`terminal_reason`/`completed_at`/`audit_event_id` (or,
  for the non-terminal entry into `waiting_on_subordinate`, the same
  statement that writes the subordinate tuple) — never a separate
  statement, so the two can never be observed out of step with each
  other by any other transaction.

**Corrected claim algorithm — inspects every generation newer than the
item's incorporated generation under the item lock before ever inserting
another one:**

```sql
SELECT ... FROM bulk_operation_items i
FOR UPDATE SKIP LOCKED
WHERE i.execution_status IN ('eligible', 'failed_retryable')
  AND NOT EXISTS (
    SELECT 1 FROM bulk_operation_item_attempts a
    WHERE a.bulk_operation_item_id = i.id
      AND a.generation > COALESCE(i.incorporated_attempt_generation, 0)
  )
```

**Normative rule: no new attempt may be claimed while any attempt
generation newer than the item's incorporated generation exists,
regardless of whether that newer attempt is open or terminal.** The
partial unique index permitting at most one open attempt per item remains
a structural backstop, but it is not the complete claim-race solution:
the generation predicate above prevents the claim from reaching a second
attempt insert at all.

Read against the six numbered rules the brief requires:

1. **A latest attempt that is `open`** is excluded by the generation
   predicate itself. The item deliberately retains its durable
   pre-attempt/retryable state (`eligible` or `failed_retryable`) while
   execution in progress is represented by the separate open-attempt row;
   item state alone therefore does not block another claim. The existence
   of the newer, unincorporated generation does. The dispatcher inspects
   that newer attempt's state without inserting: while its lease remains
   valid it waits; once its lease expires it routes the attempt to the
   existing reclaim path.
2. **A latest attempt that is terminal but not yet incorporated**
   (`generation > incorporated_attempt_generation`) is excluded by the
   `NOT EXISTS` clause — this item is **not** selected for claiming at
   all. **The same job, finding this condition instead of an eligible row
   to claim, routes that item to finalisation** — it already holds
   nothing (the `SELECT` found zero matching rows for this item), so it
   simply invokes the ordinary "item finalisation and parent-lock
   convergence protocol" below against the existing unincorporated
   attempt, using that protocol's own item lock (step 2 there), rather
   than fabricating a new one here.
3. **No newer attempt exists; the latest terminal attempt is already
   incorporated; and the item is `failed_retryable`, below ceiling**: the
   `NOT EXISTS` clause is
   satisfied, so this row may be selected and a new generation opened,
   subject to the existing retry-ceiling, cancellation, authorization, and
   all other eligibility checks. Generation N+1 therefore cannot be
   inserted until generation N is terminal **and incorporated**.
4. **Terminal, excluded, or `waiting_on_subordinate` items**: excluded by
   the `execution_status IN ('eligible', 'failed_retryable')` filter,
   unchanged from the prior design.
5. **No attempt is ever bypassed**: this is precisely what the `NOT
   EXISTS` clause guarantees — an item can never be selected while any
   newer generation remains unincorporated, whether open or terminal and,
   if terminal, regardless of its outcome (`succeeded`, `not_applied`,
   `failed_retryable`, `failed_permanent`, or `abandoned`). A terminal
   successful attempt is therefore always routed to incorporation rather
   than bypassed by a later generation.
6. **The reclaimer** transitions an expired open attempt to `abandoned`
   in its own transaction (unchanged, "Stale-worker fencing" below); that
   `abandoned` attempt is, from the claim query's perspective, exactly
   the same as any other terminal-but-unincorporated attempt — rule 2
   applies identically, routing to finalisation first. **The expired open
   generation must first become durably `abandoned`, and only once
   finalisation incorporates the abandonment** (advancing
   `incorporated_attempt_generation` to that generation) does the `NOT
   EXISTS` clause clear and a new generation become claimable.

**Subordinate initiation (phase 3, below) also advances the marker**, in
the same atomic statement that writes `execution_status =
'waiting_on_subordinate'` — not because a crash window exists there (phase
3 already writes the attempt's own success and the item's waiting state
together, in one transaction, so there is no gap to close), but so the
marker's own invariant — "identifies the exact attempt generation whose
terminal result has been incorporated" — holds unconditionally for every
item, not only those that pass through the separate finalisation
protocol. This costs nothing extra: the write was already happening in
that same statement.

**Lifecycle, restructured into the named phases "Global lock order" above
defines:**

1. **Claim** (lock-order sequence 1 — item only, a short transaction):
   `SELECT ... FOR UPDATE SKIP LOCKED` one `BulkOperationItem` matching
   the **corrected claim algorithm** in "The item-to-attempt incorporation
   marker" above — `execution_status IN ('eligible', 'failed_retryable')`
   **and no attempt generation newer than the item's incorporated
   generation exists, open or terminal**; verify
   eligibility (target still `live`, per `target_reference_status`;
   current authorization holds); `INSERT` one new
   `BulkOperationItemAttempt` row with `status = 'open'`, the next
   `attempt_ordinal`, a freshly generated `attempt_token`, and
   `generation = COALESCE(MAX(the item's historical generations), 0) + 1`
   for **every** new attempt — first attempt, retry after a recorded
   retryable failure, or retry after abandonment alike. The item lock
   serialises ordinal/generation allocation; both uniqueness constraints
   and the partial open-attempt index provide structural backstops.
   Historical attempts are never incremented or rewritten. Commit. **No
   target row is locked during this phase** — per "Global lock order"
   above. **If the corrected claim query finds no eligible row because a
   newer generation exists**, no attempt is created for that item this
   pass. The dispatcher inspects the newer attempt: an `open` attempt waits
   while its lease is valid or enters the existing reclaim path once
   expired; a terminal attempt is routed to item finalisation, per rules 1
   and 2 of the corrected claim algorithm.

2. **Database-local mutation — approval and every ADR-0030 metadata
   action (lock-order sequence 2)**: **one outer Laravel database
   transaction**, opened fresh for this phase, that:

   a. Locks the complete target set in the global order above: approval
      uses family then all family documents by ascending immutable ID;
      metadata uses its one family target. It does not rely on the current
      approval action's later unordered family query.
   b. Locks and re-reads the open attempt by `(id, attempt_token,
      generation)` — **stale-worker fencing**, see below — rejecting
      (without mutating anything) if the attempt is no longer `open`, the
      token/generation no longer match, the lease has expired, or this is
      no longer the item's sole open attempt.
   c. Revalidates `expected_state_snapshot`. A mismatch performs no domain
      mutation but **does not roll back and leave the attempt open**: in
      this same transaction it atomically writes the attempt as
      `not_applied`, with `completed_at` and the typed
      `not_applied_reason = 'expected_state_mismatch'`, then commits. This
      is a truthful non-failure terminal attempt outcome, counts in durable
      attempt history, is never counted toward the retryable-failure
      ceiling, and finalises the item as `skipped` with the corresponding
      typed reason and a no-application item audit event satisfying the
      terminal item's `audit_event_id` invariant. A duplicate not-applied report uses a guarded
      open-to-terminal update and is an idempotent no-op.
   d. **Invokes the existing single-item domain action** (`ApproveDocument
      Version`, an ADR-0030 metadata action), tagged with this attempt's
      own `invocation_idempotency_key`.
   e. **Atomically, in this same transaction**: writes the domain
      mutation; writes that action's own existing governance/audit event
      (ADR-0030/0031's existing audit tables, unmodified); writes this
      attempt's own `status = 'succeeded'`, `success_kind =
      'database_local'`, `completed_at`, and `result_digest` (the canonical
      digest of the resulting state); commits.

   **Withdrawn: describing `ApproveDocumentVersion` and the ADR-0030
   metadata actions as needing no durable result/idempotency primitive
   because reapplying them is naturally safe.** `ApproveDocumentVersion`
   specifically **rejects** a second invocation once the version is no
   longer `DRAFT` — replaying it after a crash, with no durable evidence
   of the first attempt's own outcome, would misread that rejection as a
   failure rather than recognising the work as already done. **Corrected,
   per action family:**

   - **Approval** explicitly **reuses ADR-0031's own governance-idempotency
     record** — `UNIQUE (workspace_id, purpose, idempotency_key)` — binding
     this attempt's `invocation_idempotency_key` directly to that record's
     own `idempotency_key` column. The governance mutation, its audit
     event, that idempotency record reaching its own terminal result, and
     this attempt's `status = 'succeeded'` **commit together, in the one
     outer transaction described above**. A recovering reconciler that
     finds this attempt already `succeeded` never re-invokes
     `ApproveDocumentVersion` at all — it reads the governance-idempotency
     record's own terminal result directly.
   - **Metadata actions**: ADR-0030 defines no separate request-idempotency
     table of its own for these actions. **The `BulkOperationItemAttempt`
     row itself is the durable invocation/result authority** — step (b)'s
     fencing lock-and-verify **is** the "claim to act" step, and step (e)'s
     atomic write of the metadata mutation, its audit event, and this
     attempt's own `succeeded` status/`result_digest` together **is** the
     durable record that this specific attempt already applied this
     specific mutation. A recovering worker that observes this attempt
     already `succeeded` finalises the item directly, from the attempt's
     own durable `result_digest`, and **never reapplies the metadata
     mutation** — natural state-idempotency of the underlying write is
     **not** relied upon as a substitute for this durable record.

3. **Subordinate initiation — promotion and applicability cloning
   (lock-order sequence 3)**: the already-selected design is preserved,
   restated as its own explicit atomic unit: **one outer transaction**
   that follows the subordinate action's own existing target/identity
   lock order (ADR-0034's `PromotionAttempt` creation/adoption, or
   ADR-0031's clone/fallback authorisation) — this attempt's own
   `invocation_idempotency_key` is passed straight through and bound to
   that action's own existing idempotency primitive (`PromotionAttempt`'s
   `(workspace_id, import_item_id, actor_identity, operation_kind,
   client_idempotency_key)` uniqueness, ADR-0034; `CreateApplicabilityOnly
   Successor`'s equivalent governance-idempotency key, ADR-0031) — and,
   within that same transaction, atomically: locks/fences the attempt;
   revalidates the expected-state snapshot (using the identical
   `not_applied`/`expected_state_mismatch` terminal path from phase 2 when
   it no longer matches, without creating any subordinate); otherwise
   creates or adopts the subordinate identity; appends its immutable
   subordinate-transition record; locks the item and writes
   `BulkOperationItem.subordinate_kind`/`subordinate_identity_kind`/
   `subordinate_identity_value`/`subordinate_awaited_since` together with
   `execution_status = 'waiting_on_subordinate'` **and
   `incorporated_attempt_generation` advanced to this attempt's own
   generation, in the same statement** (per "The item-to-attempt
   incorporation marker" above — no crash window exists here since both
   writes are already one statement, but the marker's own invariant holds
   unconditionally as a result); writes this
   attempt's own `status = 'succeeded'`, `success_kind =
   'subordinate_initiated'`, result subordinate/identity fields, and a
   digest binding that exact identity. **The item lock is held only for
   this short atomic identity/waiting-state write; no parent lock is held,
   and no lock of any kind is retained across the subordinate's subsequent,
   independent execution. Initiation commits only database identity/outbox
   intent; it performs no external S3, Qdrant, HTTP, or provider work while
   locks are held** — a crash immediately afterward resumes at **reconciliation**
   of the now-durably-recorded subordinate identity, never at re-initiating
   a second, duplicate subordinate workflow.

4. **On a caught database failure during phase 2 or 3** (the mutation
   transaction itself aborts): **none** of the domain mutation, its audit
   event, the action's own result/idempotency record, or this attempt's
   `succeeded` status is committed — ordinary PostgreSQL rollback discards
   all of it together. A **fresh** transaction then looks up the **same**
   attempt by `id` and writes `failed_retryable` or `failed_permanent`
   (ceiling-dependent, "Retry semantics" below) with a `failure_category`
   — guarded by `UPDATE ... WHERE status = 'open'`, so a duplicate failure
   report (a retried error handler, a redelivered job) that finds zero
   rows affected is a **safe no-op**, never a second write.

5. **Item finalisation** (lock-order sequence 4 — writing
   `BulkOperationItem.execution_status` itself, and attempting parent
   convergence) is a **separate, later, short transaction**, run
   immediately after phase 2/3/4 commits — see "Item finalisation and
   parent-lock convergence protocol" below. It reads (never needs to lock)
   the now-terminal attempt's own `status`/`result_digest`/subordinate
   identity as the durable evidence it finalises from — **it never
   re-invokes the domain action**, whether running immediately after a
   successful phase 2/3, or resuming after an arbitrary crash-and-recovery
   gap, because the attempt row already durably records everything
   finalisation needs. A `not_applied` outcome finalises the item as
   `skipped` from its typed reason and never reopens or retries it. On a
   `failed_*` attempt outcome, finalisation
   writes the **item's** own `execution_status` to `failed_retryable` or
   `failed_permanent` according to the same durable ceiling count defined
   below — an attempt recorded `abandoned` (stale-worker fencing, below)
   is treated identically to a recorded `failed_retryable`/`failed_permanent`
   for this purpose.

#### Stale-worker fencing

**A worker whose lease has expired must be structurally incapable of
later mutating the target, even if it eventually wakes up and tries.**
This is the property `attempt_token`/`generation` exist to guarantee:

- **Every phase-2/3 mutation transaction verifies fencing immediately
  before mutating anything** (step 2(b) above): it locks the specific
  attempt row (`SELECT ... FOR UPDATE`) by `id`, then checks, under that
  lock, that `status = 'open'`, the caller's own remembered
  `(attempt_token, generation)` still matches the row's current values,
  `lease_expires_at > now()`, and this is still the item's sole open
  attempt (the partial unique index already guarantees at most one exists;
  this check guards against the row having been reclaimed and reopened
  under a new `generation` since the worker last read it). **Any
  mismatch fails closed — no mutation, no attempt-status write, ordinary
  rollback** — a worker that failed this check performs no side effect of
  any kind.
- **Only once every check above passes does the mutation proceed**, and
  the attempt's own success/failure is recorded in the **same**
  transaction that performed the check, per phases 2–4 above — there is
  no window between "fencing verified" and "mutation applied" in which a
  reclaim could invalidate the check that was just performed, because
  both happen under the one lock, in the one transaction.
- **The reclaimer** (a bounded, idempotent sweep): locks the specific
  expired-open attempt (`SELECT ... FOR UPDATE`, scoped to `status =
  'open' AND lease_expires_at < now()`); re-verifies, under that lock,
  that it is still expired and still `open` (defence against a race with
  a worker that was not, in fact, dead, and committed its own outcome a
  moment before the reclaimer's own lock acquisition); transitions it
  **exactly once** to `abandoned`. That terminal generation is then routed
  to item finalisation and incorporated; **only after both abandonment and
  incorporation commit** may a subsequent claim (phase 1) open a **new**
  attempt for the item, with `generation = MAX(historical generations) +
  1` — a stale worker that later
  attempts phase 2/3's fencing check against its own, now-superseded
  `(attempt_token, generation)` fails the generation comparison and
  mutates nothing, even though its lease has, by then, long since
  expired and a new attempt may already be in progress.
- **"No heartbeat because work is short" remains the operational
  choice** — fencing does not require a heartbeat to be correct; it
  requires only that every mutation re-verify its own fencing identity
  immediately before acting, which every phase-2/3 transaction already
  does as its own first act.

6. **Retry ceiling, derived, never counted**: the number of attempts
   already made for an item is `COUNT(*) FROM bulk_operation_item_attempts
   WHERE bulk_operation_item_id = :id AND status IN ('failed_retryable',
   'failed_permanent', 'abandoned')` — computed fresh at the moment a new
   attempt would otherwise be created, **never** a counter capable of
   losing an increment to a rolled-back transaction. Reaching the
   configured ceiling is what finalisation uses to decide `failed_retryable`
   (below ceiling — eligible for another claim) versus `failed_permanent`
   (at ceiling).

#### Crash-recovery table

**One normative table, covering every named crash point across phases
1–5 above, so no prose elsewhere needs to restate recovery behaviour:**

| Crash point | Durable evidence present | Next safe action | Invocation repeatable? | Finalisation/reconciliation | Why no duplicate mutation |
|---|---|---|---|---|---|
| Before attempt creation (phase 1 never committed) | None — no attempt row exists | Item remains `eligible`/`failed_retryable`; an ordinary future claim | N/A — nothing was invoked | None needed | Nothing was ever attempted |
| After open-attempt commit, before target mutation begins | One `open` attempt, fenced by its own `(attempt_token, generation)`; the item itself remains `eligible`/`failed_retryable` | Lease-expiry reclaim (if the worker never proceeds), or the same worker proceeding to phase 2/3; the generation-based claim predicate rejects every later claim while this generation exists unincorporated | Yes — no domain work has occurred yet | None yet | The domain action was never invoked, and another generation cannot open merely because the item retains its durable pre-attempt/retryable state |
| During an aborted mutation transaction (phase 2/3 rolls back) | The `open` attempt, unchanged (the transaction that would have changed it also rolled back) | Phase 4: fresh transaction records `failed_retryable`/`failed_permanent` | Yes, if below ceiling (a new attempt, new generation) | Finalisation reads the recorded failure | Rollback discarded the mutation, its audit event, and any result record together — nothing partial exists to duplicate |
| Expected-state mismatch after fencing | Attempt `not_applied`, completed with a typed reason in the same transaction that observed the mismatch; no mutation/audit exists | Item finalisation records `skipped` | **No** — this is a truthful non-failure terminal observation, not retry authority | Finalisation reads `not_applied_reason`; duplicate reporting/finalisation is a no-op | No domain action ran, the attempt is not left open, and the outcome is durably distinguishable from infrastructure failure |
| After phase 2's/4's terminal attempt commit, before item finalisation incorporates it | Attempt `succeeded`/`not_applied`/`failed_*`/`abandoned`, with `incorporated_attempt_generation` **not yet** advanced to this generation | Item finalisation (phase 5) | **No** — the corrected claim algorithm's `NOT EXISTS` clause excludes this item from claiming entirely while unincorporated, and routes it to finalisation instead | Finalisation completes normally, whenever it runs, advancing `incorporated_attempt_generation` atomically with the item's own terminal write | The domain mutation, its audit event, and the attempt's own terminal status are one atomic fact; the claim query can see the gap (via the marker) and refuses to open a new attempt until finalisation closes it — this is the exact window Codex identified, now structurally closed |
| After phase 3's atomic subordinate-initiation commit | Attempt `succeeded`; item `waiting_on_subordinate` with `incorporated_attempt_generation` already advanced, in the same statement | Ordinary subordinate reconciliation | N/A — phase 3 has no separate incorporation step to crash between | Reconciliation observes the subordinate's own outcome later | Phase 3 writes the attempt's success and the item's waiting state (including the marker) together; there is no gap for a stale claim to exploit |
| During item finalisation (parent-lock protocol, below) | Attempt already `succeeded`/`failed_*` (unaffected by this crash) | Retry finalisation from scratch | No — finalisation performs no domain invocation at all | Retried finalisation re-locks the parent and item and completes | Finalisation's own steps are themselves transactional (below); a partial finalisation simply rolls back |
| During parent finalisation specifically (parent lock held, item write in progress) | Same as above | The parent-lock protocol's own transaction rolls back as a unit | No | A subsequent finalisation attempt (ordinary retry, or the explicit reconciliation action) | Steps 1–7 of the parent-lock protocol are one transaction; nothing partial is ever visible to another transaction |
| After subordinate initiation (phase 3) commits, before any reconciliation | Attempt `succeeded`; `BulkOperationItem.subordinate_*` populated | Ordinary subordinate reconciliation (per "Subordinate-waiting semantics" below) | **No** — reconciliation never re-initiates the subordinate | Reconciliation resumes from the durably recorded subordinate identity | The subordinate identity is already durably recorded; only its *outcome* remains to be observed |
| After the subordinate itself reaches a terminal outcome, before reconciliation observes it | The subordinate's own terminal state (in its own ADR's tables), plus this item's durable `waiting_on_subordinate` record | The next reconciliation pass observes the subordinate's current state and finalises | N/A — no bulk-layer invocation is repeated; the subordinate's own ADR governs its own retry/finality | Finalisation converts the observed outcome into the item's own terminal state | Reconciliation is read-then-finalise and idempotent — observing the same terminal subordinate state twice produces the same finalisation once |

#### Item finalisation and parent-lock convergence protocol

**One consistent lock order, used for every path that writes both an
item's own terminal transition and attempts parent convergence — ordinary
per-item execution, subordinate reconciliation, cancellation convergence,
and the explicit reconciliation action alike — closing the exact
lost-update race Codex identified, and chosen specifically to prevent
deadlock by never being acquired in the opposite order anywhere in this
ADR:**

1. **Lock the `BulkOperation` parent row**: `SELECT ... FOR UPDATE` on
   `bulk_operations WHERE id = :bulk_operation_id` (a genuine blocking
   wait, not `SKIP LOCKED` — every finalisation for the same operation
   must actually wait its turn here, never skip past a concurrently
   finalising sibling).
2. **Lock and re-read the specific `BulkOperationItem` row** being
   finalised (`SELECT ... FOR UPDATE`), confirming it is still in the
   state this finalisation expects (defence against an already-finalised
   duplicate finalisation attempt being a no-op).
3. **Apply this item's own terminal transition** — write
   `execution_status`, `completed_at`, `terminal_reason` (where
   applicable), `audit_event_id`, **and `incorporated_attempt_generation`
   advanced to the generation of the attempt being incorporated, all in
   this one `UPDATE` statement** (per "The item-to-attempt incorporation
   marker" above — this is precisely the atomicity that closes the crash
   window between an attempt's own terminal commit and its incorporation
   into the item), and (if this outcome was reached via a subordinate) the
   already-populated `subordinate_*` lineage columns, left untouched. A
   retried finalisation that finds `incorporated_attempt_generation`
   already equal to this generation performs an idempotent no-op write —
   permitted by the incorporation guard trigger's own same-value rule.
4. **Recompute the full item distribution for this operation**, counted
   fresh from `bulk_operation_items` rows, **while still holding the
   parent row lock from step 1** — this is what makes the recompute
   race-free: no other finalisation for this same operation can be
   concurrently writing an item's terminal state and reaching this same
   step, because step 1's lock serialises them.
5. **Apply the terminal-mapping function** ("Parent state machine"
   above) to the freshly recomputed distribution.
6. **Update `BulkOperation.status` only if its current value legally
   permits the transition** — from a pre-terminal value
   (`preparing_membership`/`awaiting_confirmation`/`queued`/`running`) to
   the function's result, or a same-value idempotent no-op rewrite if a
   retried finalisation finds the parent already converged to the exact
   value the function would produce again; **never** a write that would
   regress an already-terminal `status` to a different value — this
   guard is what makes the whole protocol idempotent and safe to invoke
   from more than one of the four call sites concurrently.
7. **Commit** — the item's own terminal write and any parent-status
   convergence happen atomically together, or neither happens (ordinary
   rollback).

**The concurrency consequence, stated honestly**: this protocol
serialises the **finalisation** step — recording an item's terminal
outcome and deciding whether the parent has converged — to one item at a
time **per operation** (contention is scoped to `bulk_operations.id`, a
single row per operation, never a workspace-wide lock, and never
contending with any other operation's own finalisation at all). The
**domain mutation** itself (steps 1–3 of the attempt lifecycle above) is
unaffected and remains fully concurrent across items, since it acquires
no parent lock at all — only the short, final "record the outcome and
check convergence" step contends, and it is short precisely because steps
4–6 above are simple, indexed counts and a single-row conditional write,
never external I/O. This is an accepted, bounded cost — see "Consequences
→ Negative" below — not a workspace-wide bottleneck.

**Two concurrent bulk operations targeting the same item** still
serialise only at that item's own attempt-claim step (the partial unique
index on `BulkOperationItemAttempt` for the specific item each operation's
own `BulkOperationItem` row represents) — since each operation has its
**own** `BulkOperationItem` row for a shared target (per the per-operation
uniqueness constraints above), this is, precisely, two different items'
own independent claim-and-finalise sequences, each serialising only
against its own operation's parent, never against each other. **The
later-finalising operation revalidates against the state the earlier one
actually committed** and commits a typed `skipped` result when its own
expected state is no longer true — it never double-applies a mutation.

**Bulk-orchestration queue connection, named explicitly**: rather than
depending on whatever the application's global default queue connection
happens to be, this ADR's jobs run on a dedicated, explicitly configured
Laravel queue connection, `bulk` (`config/queue.php`), using the
`database` driver **regardless of what `QUEUE_CONNECTION` the
application's default queue is set to** — the same underlying driver
`AdvanceDocumentDeletion`/`ExecuteGenerationRun` already prove in
production shape, but named and configured independently so a future
change to the *global* default queue connection can never silently move
bulk orchestration onto an unsuitable driver.

**One Laravel job repeats the claim-then-invoke-then-finalise sequence
for a configured, bounded number of items**, then re-enqueues itself if
`eligible` or recently-reclaimed `waiting_on_subordinate` work remains —
never one job per item, and never an unbounded loop inside a single job
invocation.

### Cancellation

Before execution starts (`awaiting_confirmation`/`queued`), the whole
operation may be cancelled outright — no item has been claimed, so
convergence is immediate. During execution (`running`), a cancellation
request prevents not-yet-claimed `eligible` items from being claimed;
**an item with an already-open attempt always finishes that attempt
honestly** (per the durable attempt lifecycle above), reaching whichever
of `succeeded`/`skipped`/`failed_retryable`/`failed_permanent`/
`waiting_on_subordinate` that attempt's own logic produces — cancellation
is checked only when an item would otherwise be claimed for a **new**
attempt, never against one already open. **An item already
`waiting_on_subordinate` continues to be reconciled to its own truthful
outcome regardless of the parent's cancellation request** — the
subordinate workflow it initiated has already been durably committed and
cannot be un-initiated; per "Item state machine" above, `cancelled` is
reachable only from `eligible`. Completed item results remain
authoritative and are never rolled back. Marking a not-yet-claimed
`eligible` item `cancelled` is itself an ordinary item terminal
transition, and goes through the exact same item finalisation and
parent-lock convergence protocol every other item terminal transition
uses — there is no separate "cancellation convergence" mechanism.
Once every remaining item has reached one of its own terminal outcomes
(including `cancelled`), the parent's own terminal state is whatever the
terminal-mapping function above returns for the resulting distribution —
`cancelled` or `cancelled_after_partial_execution`, per that function's
own branch 3.

### Parent-status authority and reconciliation

**One coherent design, replacing both the prior draft's unresolved
tension between a stored `status` column and "derived at read time," and
its unsafe claim that same-transaction commit alone was sufficient to
prevent a lost update — Codex correctly identified that two items
finishing at nearly the same moment, each in its own transaction, could
each independently recompute the distribution under `READ COMMITTED`
without ever seeing the other's just-committed row, and race to decide
(and write) the parent's status. The fix is the parent-lock convergence
protocol in "Item finalisation and parent-lock convergence protocol"
above, which every path described below now uses.**

- **`BulkOperation.status` persists only the irreducible, genuinely
  sequential workflow states** a parent moves through before any item
  outcome exists to derive from: `preparing_membership`,
  `awaiting_confirmation`, `queued`, `running`. These are written
  directly, by the step that causes each transition (freeze completion,
  explicit confirmation, successful dispatch, first item claimed).
- **The stored `status` converges to a terminal value only through the
  item finalisation and parent-lock convergence protocol above** —
  never a separate, later pass, and never two finalisations racing to
  decide the parent's status without a shared lock, because step 1 of
  that protocol (`SELECT ... FOR UPDATE` on the parent row) totally
  orders any two finalisations attempting to converge the same
  operation, and step 6's "only if the current state legally permits
  the transition" guard makes a retried or duplicated finalisation
  idempotent.
- **Counts remain always derived at read time** — total/eligible/
  excluded/succeeded/skipped/failed/waiting, computed fresh from
  `BulkOperationItem` rows on every read, never cached on the parent as
  authority.
- **A crash between an item's own terminal write and the parent-status
  write** cannot occur, because both are steps 3 and 6 of the **same**
  finalisation transaction (protocol above) — either both commit
  together, or neither does, and the item's finalisation is simply
  retried from scratch (its attempt is already durably `succeeded`/
  `not_applied`/`failed_*`, per the attempt authority above, so retrying finalisation
  never re-invokes the domain action, only re-attempts the item-terminal
  write and convergence check).
- **An idempotent explicit reconciliation action** — safe to invoke at
  any time, by a scheduled sweep or an explicit operator action —
  **is itself simply an out-of-band invocation of the same protocol**:
  it locks the parent (step 1), does not need to lock any specific item
  (step 2 is skipped — nothing is being newly transitioned), recomputes
  the distribution (step 4), applies the mapping function (step 5), and
  writes `status` only if it currently permits the transition (step 6).
  This correctly repairs the one narrow condition this design can still
  produce — a parent left `running` because its triggering finalisation
  crashed between steps 1 and 7 and was never retried — and is a safe
  no-op otherwise, including when it races an ordinary finalisation
  already in progress: whichever of the two acquires the parent's row
  lock first proceeds; the other simply waits, then finds `status`
  already correctly converged at step 6 and performs no write.
- **No separately maintained aggregate counter is ever consulted to
  decide `status`** — only the item rows themselves, every time.

### Subordinate-waiting semantics

**A genuinely new, non-terminal item state, `waiting_on_subordinate`**,
because promotion and applicability cloning are durable subordinate
workflows a bulk item cannot pretend to complete synchronously inside its
own short transaction — and must never report a fabricated success at
initiation only to later contradict itself with a subordinate failure.

**Complete subordinate lineage is append-only.** A single mutable current
identity is insufficient when a clone proceeds through cleanup into an
ordinary-ingestion fallback, so Laravel owns
`BulkOperationItemSubordinateTransition`:

| Column | Constraint |
|---|---|
| `id` | Internal identity |
| `bulk_operation_item_id`, `workspace_id` | Composite FK to `bulk_operation_items (id, workspace_id)`; `restrictOnDelete()` because operation items and their history are retained |
| `ordinal` | Stable monotonic ordinal; `UNIQUE (bulk_operation_item_id, ordinal)` |
| `transition_key` | Deterministic RFC 8785/SHA-256 identity over item, subordinate kind/identity and transition category; `UNIQUE (bulk_operation_item_id, transition_key)` makes repeated callback/reconciliation append a no-op |
| `subordinate_kind` | `promotion_attempt` \| `content_clone_operation` \| `full_ingestion_fallback` |
| `subordinate_identity_kind`, `subordinate_identity_value` | Required, kind-valid `public_id`/`event_id` pair |
| `transition_category` | Bounded reason/event vocabulary such as `initiated`, `adopted`, `fallback_started` |
| `recorded_at`, `correlation_identity` | Required immutable provenance |
| `mapped_state_digest` | Optional canonical digest of the subordinate state that caused the transition |

Rows are insert-only: runtime receives `INSERT`/`SELECT`, never `UPDATE`
or `DELETE`; the item is retained, so the ledger is retained with it. The
first clone identity is appended when created/adopted; cleanup-to-fallback
appends the ingestion `event_id` and advances the current item pointer in
the same transaction that initiates/adopts fallback, without rewriting the
clone row. The
item's existing subordinate fields are only the efficient pointer to the
currently awaited identity; the transition ledger is authoritative for
complete lineage. A direct fallback before clone creation appends only its
ingestion transition. Ordinary promotion uses the same generalized ledger
and appends its `PromotionAttempt` identity, keeping one correlation model
for every subordinate-backed item.

- **`subordinate_kind`** (`promotion_attempt` | `content_clone_operation`
  | `full_ingestion_fallback`), **`subordinate_identity_kind`** (`public_id`
  | `event_id`), and **`subordinate_identity_value`** are set, atomically
  with the item's transition to `waiting_on_subordinate`, in the same
  transaction that initiated the subordinate workflow and appended its
  transition-ledger row. They represent the current reconciliation pointer,
  not the complete history. **Withdrawn: the
  prior assumption that every subordinate's identity is a `public_id`.**
  A same-row `CHECK` binds each kind to its one permitted identity kind:

  ```sql
  CHECK (
    (subordinate_kind IN ('promotion_attempt', 'content_clone_operation')
       AND subordinate_identity_kind = 'public_id')
    OR (subordinate_kind = 'full_ingestion_fallback'
       AND subordinate_identity_kind = 'event_id')
    OR subordinate_kind IS NULL
  )
  ```

  `PromotionAttempt` and `DocumentContentCloneOperation` each have their
  own stable `public_id`; ordinary full-ingestion fallback has no row of
  its own to reference and is instead tracked through ADR-0007's real
  ingestion lineage `event_id` (the `document.ingestion.requested` event
  ADR-0034 already names).
- **Expected terminal outcomes are exactly the subordinate's own,
  already-defined ones — this ADR introduces no new subordinate
  vocabulary, only maps the existing one onto its own item outcome — via
  exactly two normative mapping tables, below, which are the sole source
  of this mapping. No other prose in this ADR restates or redefines
  it.**
- **Idempotent, Laravel-owned reconciliation**: the same bounded batch
  job that claims `eligible` items also, in its own bounded pass, reads
  the current status of every `waiting_on_subordinate` item's subordinate
  workflow (a plain read query against `PromotionAttempt`/
  `DocumentContentCloneOperation`'s own status column — **no lock is held
  on the bulk item's original target row while waiting**, and no lock is
  taken on the subordinate's own row beyond what that subordinate's own
  ADR already requires for its own state reads) and, where the
  subordinate has reached a terminal outcome, commits the bulk item's own
  corresponding terminal state in one short transaction. Re-running this
  reconciliation against an already-resolved item is a safe no-op.
- **Cancellation behaviour**: as stated above, a `waiting_on_subordinate`
  item is never cancelled — it is always reconciled to its subordinate's
  actual outcome, since that work already exists durably and cannot be
  retracted.
- **Parent-convergence behaviour**: a `BulkOperation` cannot reach any
  terminal status while any item remains `waiting_on_subordinate` — the
  parent stays `running` until every such item resolves.
- **Timeout/stuck visibility**: an item that remains
  `waiting_on_subordinate` past a bounded threshold is surfaced through
  the same "visibly stuck" administrative read model ADR-0025 already
  establishes — reused directly, not reinvented, and consistent with
  how that subordinate's own ADR already defines its own stuck-operation
  visibility.
- **No database lock is ever held while waiting** — satisfied by
  construction, since the reconciliation pass is itself a fresh, short,
  independently-scoped transaction each time it runs.

#### Normative mapping table 1 — ADR-0034 `PromotionAttempt`, read directly
#### from its actual state machine

**ADR-0034's own `PromotionAttempt` state machine, verified directly**:
`RESERVED → COPYING → SOURCE_VERIFIED → COMMITTED`, branching to
`CONFLICT` (from `SOURCE_VERIFIED`), to `FAILED` (technical exhaustion,
from `COPYING` or `SOURCE_VERIFIED`), to `ABANDONED` (cancellation, from
any non-terminal state), or to `EXPIRED` (retention elapsed, no valid
lease). This table is the **sole** source of the promotion mapping; no
other prose in this ADR restates it.

| `PromotionAttempt` state | Bulk item outcome | Typed reason | Notes |
|---|---|---|---|
| `RESERVED`, `COPYING`, `SOURCE_VERIFIED` (including any internal reclaim/retry ADR-0034 performs on its own ceiling) | Remains `waiting_on_subordinate` | — | A transient failure still being retried/reclaimed **inside** ADR-0034 never terminates the bulk item — the bulk layer only ever observes the subordinate's own terminal outcomes below |
| `COMMITTED` | `succeeded` | — | **Never earlier.** This never means the resulting `Document` is indexed, approved, or searchable — see "downstream pending work," per-operation matrix above, unaffected by this correction |
| `CONFLICT` | `failed_permanent` | `promotion_conflict` | **Never `skipped`.** The subordinate workflow genuinely started and now requires a new, human-corrected decision snapshot per ADR-0034 — this is a permanent failure of *this* attempt, not a mere preflight mismatch |
| `FAILED` (technical exhaustion) | `failed_permanent` | `promotion_technical_failure` | Reached only once ADR-0034's own ceiling is exhausted — never while ADR-0034 itself would still reclaim |
| `ABANDONED` | `failed_permanent` | `promotion_abandoned_externally` | The bulk layer never requests cancellation of a subordinate `PromotionAttempt` (per "Cancellation behaviour" above) — an `ABANDONED` outcome can only arise from an independent, direct ADR-0034 cancellation of the same attempt; truthfully terminal from the bulk item's perspective, since the attempt will never itself complete |
| `EXPIRED` | `failed_permanent` | `promotion_expired` | ADR-0034's own retention window elapsed with no valid lease; truthfully terminal for the same reason as `ABANDONED` |

**Bulk retry never bypasses ADR-0034's corrected-decision/adoption
rules**: every row above except `COMMITTED` resolves to `failed_permanent`
— per "Retry semantics" below, a `failed_permanent` item never reopens
within the same `BulkOperation`; acting again requires a new
`BulkOperation`, whose fresh freeze picks up the `ImportItem` in
whatever *current* state it holds (a corrected decision snapshot, for a
prior `CONFLICT`) — never a bulk-level shortcut around ADR-0034's own
correction workflow.

#### Normative mapping table 2 — ADR-0031 clone/fallback, read directly
#### from its actual state machine

**ADR-0031's own `DocumentContentCloneOperation` state machine, verified
directly**: `AUTHORISED → COPYING → VERIFYING → INDEXED`, with
`COPYING`/`VERIFYING` able to transition to `CLEANUP_REQUIRED →
FALLBACK_READY → ordinary ingestion` on any layer's integrity-proof
failure. This table is the **sole** source of the applicability/clone
mapping; no other prose in this ADR restates it.

| ADR-0031 state / event | Bulk item outcome | Typed reason | Notes |
|---|---|---|---|
| Compatibility proof fails **before** any `DocumentContentCloneOperation` is created | Immediately enters `waiting_on_subordinate` with `subordinate_kind = 'full_ingestion_fallback'` | — | **Withdrawn: treating this as a skip.** No clone was ever authorised to fail; ordinary full-ingestion is initiated directly, tracked by its own ingestion `event_id` |
| `AUTHORISED`, `COPYING`, `VERIFYING` | Remains `waiting_on_subordinate` | — | In progress |
| `COPYING`/`VERIFYING` → `CLEANUP_REQUIRED` → `FALLBACK_READY` | Remains `waiting_on_subordinate`; the item's current pointer advances to `full_ingestion_fallback`/`event_id` when ordinary ingestion begins, while the append-only transition ledger retains both clone and fallback identities | — | **Withdrawn: mapping any point in this sequence to `failed_permanent`, or overwriting the only record of the clone.** Cleanup and fallback are still in progress; recovery remains possible and both identities remain directly correlated |
| `INDEXED` | `succeeded` | — | Technical materialisation/indexing is complete at exactly this point — never described as "still pending" once reached |
| Full-ingestion fallback reaches ADR-0007's own ingestion `INDEXED` | `succeeded` | — | Mapped at ingestion's own authoritative technical terminal state, not at upload/extraction/any earlier point |
| Full-ingestion fallback reaches ADR-0007's own ingestion `FAILED` (ceiling exhausted) | `failed_permanent` | `full_ingestion_failed` | The **only** point in this entire table with no remaining cleanup/fallback/recovery path — ADR-0031's own design guarantees every clone failure routes through fallback, so this is the sole genuine dead end |
| A checksum-serialization lock (`WorkspaceChecksumReservation`, ADR-0034) is briefly held by a concurrent operation | Remains an open attempt, ordinarily waiting on the lock | — | **Withdrawn: describing this as an exclusion or a skip.** Ordinary lock contention is not a semantic outcome at all |
| The same lock wait exceeds a bounded timeout | Attempt-level `failed_retryable` (via the durable attempt authority above) | `checksum_lock_timeout` | A retryable **attempt** failure, reclaimed under the item's own ceiling exactly like any other transient failure — never simultaneously a preflight exclusion or a mutation-boundary skip |

**After technical success, governance approval/authority may still be
pending — technical indexing is never also described as pending once
`succeeded` is reached**: a bulk-applicability item's `succeeded` outcome
means the successor's technical materialisation is complete and nothing
further is owed to that fact; the per-operation matrix's "downstream
pending work" for this operation names only the still-open **governance**
step (separate approval before the successor can attain authority),
never a second, contradictory "technical indexing still pending" claim.
**Searchability still requires both the accepted technical rules
(ADR-0007/ADR-0032) and the accepted governance rules (ADR-0017)** —
unchanged by this ADR in either direction.

**Laravel owns every reconciliation decision in both tables above. Python
reports only its own already-bounded technical result** (a clone's
completeness report, per ADR-0031) — this ADR grants it no new authority
over what a bulk item's own outcome means.

### Applicability/location bulk change — orchestration detail

This is a governed action, not an in-place edit, and is treated with the
weight that implies:

- **The user chooses**: replace the complete applicability selection, or
  add/remove specific locations from the current set — both are
  supported, both are explicit, mutually distinct choices at confirmation
  time, never inferred from a bare list of checked locations.
- **Parent-location selection includes every descendant**, per ADR-0017's
  existing hierarchy-extension rule, unmodified — selecting a region
  previews its full descendant-site coverage, not merely the region node
  itself.
- **The exact resulting applicability set is previewed before
  confirmation** — every family/version this operation will touch shows
  its resulting applicability plainly, not merely the delta expression the
  user typed.
- **Universal applicability** is represented explicitly as its own
  selectable state (`UNIVERSAL`, per ADR-0017), never as "zero locations
  selected" being silently reinterpreted as universal.
- **Duplicate/no-op changes are excluded at preflight**, with the typed
  reason `no_op_unchanged_applicability` — a target whose resulting
  applicability set would be identical to its current one is never
  processed as a real mutation, and never counted as "succeeded" for
  having changed nothing.
- **Per item, at the mutation boundary**, this operation invokes
  `CreateApplicabilityOnlySuccessor` exactly as a single, non-bulk request
  would — ADR-0031's own compatibility proof (`materialisation_pipeline_
  fingerprint`, checksum, active-generation check) is evaluated
  unmodified, per target, never bypassed or batched.
- **ADR-0034's shared `WorkspaceChecksumReservation` serialization is
  respected identically** — each item's clone invocation acquires the
  same workspace/checksum lock any concurrent import promotion would,
  exactly as ADR-0034 already specifies; this bulk operation introduces
  no exemption from, and no new instance of, that primitive.
- **Clone fallback/failure outcomes are governed entirely by normative
  mapping table 2 in "Subordinate-waiting semantics" above — restated
  nowhere else.** In summary only: a compatibility-proof failure never a
  skip, always an immediate fallback initiation; `CLEANUP_REQUIRED`/
  `FALLBACK_READY` remain `waiting_on_subordinate`, never `failed_permanent`,
  since recovery is still in progress; the sole `failed_permanent` outcome
  in this whole operation type is the fallback's own ingestion reaching
  ADR-0007's `FAILED` after its own ceiling — never silently retried by
  this ADR, and never presented as `succeeded` ahead of that subordinate's
  own genuine outcome.
- **Why this never edits an authoritative version in place**: ADR-0017's
  applicability snapshot is immutable per version, by design — this ADR
  does not, and structurally cannot, alter that; every applicability
  change is, and remains, a new governed successor.

**No departments or teams are introduced.** The applicability-change UI
exposes only the existing organisational-location hierarchy; a future
department/team axis's extension seam is preserved by not hard-coding
this ADR's own preview/confirmation UI to a single-hierarchy assumption
beyond what ADR-0017 already requires — no inactive, greyed-out
department/team control is shown in V1.

### Per-operation contract matrix — closed, implementation-ready

**Withdrawn: "bounded and enumerated at implementation time" as the
description of any operation's exclusion/result vocabulary.** Every
stable semantic category below is fixed by this ADR, for all seven V1
operation types; only the low-level infrastructure code beneath a given
category (an exact string constant, a specific database error class) is
implementation detail.

**Bulk approval** — target kind: version (`Document`). Required
capability: owner/admin. Payload shape: none (approval carries no
parameters beyond the target itself). Confirmation severity: standard.
Preflight eligibility: technically `INDEXED`, governance `DRAFT`, and not
already `APPROVED`/`WITHDRAWN`. Exclusion categories: `not_indexed`
(technical status is not yet `INDEXED`); `already_approved_or_current`;
`withdrawn`; `authorization_insufficient`. Mutation-boundary skip
categories: `target_no_longer_exists`; `governance_inputs_changed`
(the version's governance state changed since freeze — e.g. it was
approved or withdrawn by another action in the meantime);
`authority_window_conflict` (ADR-0017's own lineage-monotonicity check
rejects the approval, e.g. a successor already attained authority first).
Retryable technical failure categories: an ordinary database/
infrastructure error during `ApproveDocumentVersion`'s own transaction.
Permanent failure categories: none beyond ceiling exhaustion — a rejected
approval is always a `skipped` conflict, never a `failed_permanent`
outcome, since ADR-0017's own governance rules are deterministic, not
retryable-and-failable. Backing action: `ApproveDocumentVersion`
(ADR-0031), unmodified. Item success: `ApproveDocumentVersion` commits;
the version is now governance-`APPROVED`. Audit event:
`bulk_operation_item.approved`. Downstream pending work: whether the
version has genuinely *attained* authority depends on ADR-0017's
`authority_start` derivation, unaffected by this ADR — approval alone
**never guarantees current/searchable authority**. Knowledge-gap
behaviour: none — approval only ever adds authority, never removes it.

**Bulk promotion** — target kind: `ImportItem`. Required capability:
owner/admin. Payload shape: none (promotion uses the item's own current
decision snapshot, per ADR-0034). Confirmation severity: standard.
Preflight eligibility: `preflight_status = 'verified'`,
`match_status = 'resolved'`, and every ADR-0034 promotion-readiness
criterion satisfied. Exclusion categories: `preflight_not_verified`;
`match_unresolved`; `readiness_criteria_incomplete` (required metadata/
applicability missing). Mutation-boundary skip/conflict categories:
`target_no_longer_exists`; `decision_snapshot_changed` (the item's current
decision snapshot changed since freeze); `staging_expired` (the batch's
retention window elapsed); `authorization_changed` (ADR-0034's own live-
authorization-at-commit check fails). Retryable technical failure
categories: a transient `PromotionAttempt` `COPYING`/`SOURCE_VERIFIED`
failure, reclaimed under ADR-0034's own ceiling. Permanent failure
categories: **governed entirely by normative mapping table 1 in
"Subordinate-waiting semantics" above** — `CONFLICT` (`promotion_conflict`),
exhausted `FAILED` (`promotion_technical_failure`), `ABANDONED`
(`promotion_abandoned_externally`), and `EXPIRED` (`promotion_expired`)
all resolve to `failed_permanent`; no other prose restates this. Backing
action/subordinate: the `PromotionAttempt` sequence (ADR-0034), via
`waiting_on_subordinate`. Item success: the `PromotionAttempt` reaches
`COMMITTED`. Audit event: `bulk_operation_item.promoted`. Downstream
pending work: the created `Document` still requires ordinary ingestion,
indexing, and separate governance approval — promotion alone **never
implies indexed, approved, or searchable**. Knowledge-gap behaviour: none
— promotion only ever adds a new, not-yet-authoritative version.

**Bulk applicability change** — target kind: family (fans out to its
current version). Required capability: owner/admin. Payload shape: the
requested applicability set (replace, or add/remove against the current
set), canonicalised. Confirmation severity: **high** — a governed action
creating a new successor, previewed in full before confirmation.
Preflight eligibility: the family has a current, `INDEXED` predecessor,
and the resulting applicability set genuinely differs from the current
one. Exclusion categories: `no_authoritative_predecessor` (no current
version exists to clone from); `no_op_unchanged_applicability`;
`invalid_or_retired_location` (a selected location no longer exists or
has been retired). Mutation-boundary skip/conflict categories:
`target_no_longer_exists`; `predecessor_state_changed` (the current
version changed since freeze — e.g. it was withdrawn). **Withdrawn: listing
a compatibility-proof failure or checksum-lock contention as a skip/
conflict category here — neither is one; both are governed entirely by
normative mapping table 2 in "Subordinate-waiting semantics" above** (a
compatibility-proof failure initiates full-ingestion fallback via
`waiting_on_subordinate`; ordinary lock waiting is not a semantic outcome
at all, and only a bounded lock-wait timeout is a retryable **attempt**
failure, `checksum_lock_timeout`). Retryable technical failure
categories: a transient clone-attempt infrastructure failure within
ADR-0031's own ceiling, and `checksum_lock_timeout` (above). Permanent
failure categories: **exactly one, per mapping table 2** — the
full-ingestion fallback's own ingestion reaching ADR-0007's `FAILED`
after its own ceiling (`full_ingestion_failed`); no other point in the
clone/fallback lifecycle resolves here, since ADR-0031's own design
guarantees every clone failure routes through fallback first. Backing
action/subordinate: `CreateApplicabilityOnlySuccessor`, then either a
`DocumentContentCloneOperation` or a full-ingestion fallback, via
`waiting_on_subordinate`. Item success: the subordinate reaches `INDEXED`
(clone) or ADR-0007's own ingestion `INDEXED` (fallback) — technical
materialisation is **complete** at this point, not merely initiated.
Audit event: `bulk_operation_item.applicability_successor_created`.
Downstream pending work: **governance approval only** — the new
successor's technical indexing is already complete by the time this item
is `succeeded` (never described as "still pending" once reached); the
successor must still be separately approved before it can attain
authority. Knowledge-gap behaviour: **none** — the predecessor's own
authority window is unaffected until the successor genuinely attains
authority, exactly as ADR-0017 already guarantees.

**Bulk owner assignment** — target kind: family. Required capability:
owner/admin. Payload shape: the requested owner's stable user identity.
Confirmation severity: standard. Preflight eligibility: the requested
owner is a currently active workspace member (ADR-0030's eligibility
rule). Exclusion categories: `requested_owner_not_active_member`;
`current_owner_already_matches` (a genuine no-op). Mutation-boundary skip
categories: `target_no_longer_exists`; `membership_changed_before_
mutation` (the requested owner's own membership lapsed between
confirmation and this item's execution). Retryable technical failure
categories: an ordinary database/infrastructure error. Permanent failure
categories: none beyond ceiling exhaustion. Backing action: ADR-0030's
owner-reassignment action, unmodified. Item success: the reassignment
commits. Audit event: `bulk_operation_item.owner_reassigned`. Downstream
pending work: none — this is a pure metadata fact with no further
lifecycle. Knowledge-gap behaviour: none — ownership carries no
authorization or retrieval weight, per ADR-0030.

**Bulk category assignment** — target kind: family. Required capability:
owner/admin. Payload shape: the requested category's stable identity, or
an explicit "no category" clear. Confirmation severity: standard.
Preflight eligibility: the requested category is `active` (not archived)
if being newly assigned; clearing a category has no such requirement.
Exclusion categories: `category_archived_or_deleted` (only when newly
assigning; an archived category may still be cleared); `already_
assigned` (a genuine no-op). Mutation-boundary skip categories: `target_
no_longer_exists`. Retryable technical failure categories: an ordinary
database/infrastructure error. Permanent failure categories: none beyond
ceiling exhaustion. Backing action: ADR-0030's category-assignment
action, unmodified. Item success: the assignment commits. Audit event:
`bulk_operation_item.category_assigned`. Downstream pending work: none.
Knowledge-gap behaviour: none — categories never affect retrieval or
authority, per ADR-0030.

**Bulk tag mutation** — target kind: family. Required capability: owner/
admin. Payload shape: an explicit add-set, remove-set, or full-replace-
set of tag values (never an ambiguous toggle, per ADR-0030's own
set/add/remove discipline). Confirmation severity: standard. Preflight
eligibility: the resulting tag set, after normalisation, would genuinely
change the family's current tags, and would not exceed ADR-0030's
20-tag-per-family cap. Exclusion categories: `add_remove_replace_no_op`
(the resulting set is identical to the current one); `tag_limit_
exceeded`. Mutation-boundary skip categories: `target_no_longer_exists`;
`requested_tag_set_changed_before_mutation` (another concurrent mutation
already altered the family's tags since freeze, per ADR-0030's own
lock-based tag Action — this item's own requested delta is re-evaluated
against the current set at the mutation boundary, exactly as a single,
non-bulk tag edit already would be, and skips rather than silently
compounding onto a state it never previewed). Retryable technical failure
categories: an ordinary database/infrastructure error. Permanent failure
categories: none beyond ceiling exhaustion. Backing action: ADR-0030's
lock-based tag-assignment action, unmodified. Item success: the tag
mutation commits. Audit event: `bulk_operation_item.tags_mutated`.
Downstream pending work: none. Knowledge-gap behaviour: none — tags never
affect retrieval or authority, per ADR-0030.

**Bulk review-due-date assignment** — target kind: family. Required
capability: owner/admin. Payload shape: a requested date, or an explicit
clear (removing any existing review-due date is an allowed, deliberate
outcome, not an error). Confirmation severity: standard. Preflight
eligibility: the requested date, if present, is a genuinely valid
calendar date; a clear is always eligible. Exclusion categories:
`invalid_date`; `same_existing_date` (a genuine no-op, including
clear-when-already-unset). Mutation-boundary skip categories: `target_
no_longer_exists`. Retryable technical failure categories: an ordinary
database/infrastructure error. Permanent failure categories: none beyond
ceiling exhaustion. Backing action: ADR-0030's review-date action,
unmodified. Item success: the assignment (or clear) commits. Audit
event: `bulk_operation_item.review_date_assigned`. Downstream pending
work: none beyond ADR-0036's future reminder scheduling, which this ADR
does not define. Knowledge-gap behaviour: none — review dates are
advisory only, per ADR-0030, and never affect authority.

### Authorization and tenancy

Owner/admin authority is sufficient for every V1 operation — **no
four-eyes requirement**, matching ADR-0017/0031's own settled V1 rule.
"No four eyes" does not mean "no confirmation" or "no audit" — every
operation still requires the explicit confirmation step above and
produces the complete audit trail below.

- **Current authorization is checked three times, independently**: when
  resolving membership (the freeze query's own workspace/role filter);
  again at confirmation (re-resolved, never trusted from the moment
  selection began); and again, per item, immediately before every
  mutation (step 3 of the per-item transaction in "Idempotency and
  concurrency" above).
- **Authority lost during execution**: an item whose initiating actor has
  lost the required role or workspace membership by the time that item is
  claimed is `skipped`, with the typed reason
  `authorization_insufficient` at execution time — **fail-closed**,
  consistent with ADR-0034's own live-authorization-at-commit rule; an
  already-running system job never completes an item after the
  initiating actor's authority has lapsed, absent an existing accepted
  ADR requiring otherwise (none does).
- **Initiating actor versus executing/reconciling identity — never
  conflated**: `BulkOperation.actor_type`/`actor_user_id`/
  `system_actor_code` permanently record **who requested and confirmed**
  the operation — this identity is never overwritten or reassigned once
  set. The Laravel queue worker or the `waiting_on_subordinate`
  reconciliation pass that later executes or reconciles an item runs
  under its **own**, separately recorded system identity (an
  implementation-level fact, not a parent-provenance field) — **it never
  replaces, and is never confused with, the initiating actor's own
  identity**, which remains what "authority lost during execution" above
  re-checks at every mutation boundary. Every item's audit record
  therefore carries two distinct facts, never one merged into the other:
  who requested/confirmed the operation, and which system process
  actually executed or reconciled this specific item.
- **Tenant-safe concealment**: an attempt to reference another
  workspace's target at any point resolves through the same not-found
  response every other route in this codebase already uses — never a
  distinguishable forbidden/not-found pair, and the frozen membership's
  own workspace-scoped freeze query (above) already makes a genuine
  cross-workspace target structurally unreachable in the first place.
- **A disabled actor account**: an operation already confirmed by an
  actor whose account is later disabled behaves exactly as "authority
  lost during execution" above — remaining eligible items are `skipped`,
  never force-completed.
- **Bulk actions never become a way to infer hidden cross-workspace
  identities or counts** — every exclusion reason, every count, and every
  preflight figure is computed exclusively within the initiating actor's
  own workspace scope.
- **Audit attribution** is complete for every item, per "Audit and
  observability" below.

### Retry semantics — four distinct concepts, never conflated, and one
### immutability rule with no exception

**The final, unambiguous rule, replacing the prior draft's internal
contradiction (which described `failed_permanent` as immutable, then
separately permitted retrying it, then separately prohibited that same
retry):**

- An item in **non-terminal** `failed_retryable`, below its ceiling, may
  be reclaimed for another attempt **automatically** (the corrected
  `SKIP LOCKED` claim in "Idempotency and concurrency" above selects it
  directly — `execution_status` never routes back through `eligible`
  first) or **explicitly** (an operator-triggered "retry now," which does
  nothing but make the item immediately selectable by that same claim
  query for its next generation) — both within the **same, unchanged**
  `BulkOperation`, and both still gated by the incorporation marker: a
  `failed_retryable` item is only ever selectable once its most recent
  attempt's own failure has been incorporated.
- **Ceiling exhaustion transitions it exactly once, irreversibly, to
  immutable `failed_permanent`.**
- **`failed_permanent`, `skipped`, `cancelled`, `excluded`, and
  `succeeded` never reopen, under any of the four concepts below, and
  under no other mechanism this ADR defines.** Acting again on any
  terminal item requires a genuinely **new** `BulkOperation` — its own
  fresh membership, its own fresh preflight, its own fresh authorization
  check, its own idempotency identity, and its own explicit confirmation.

**The four distinct concepts, kept separate:**

1. **Automatic bounded worker retry** — the ordinary reclaim of a
   `failed_retryable` item for another attempt, within its ceiling, inside
   the same operation, once its prior failure is incorporated; not a
   user-facing action at all, simply how the execution job already
   behaves.
2. **Explicit "retry currently retryable items" within the same
   operation** — a user-facing action scoped to exactly the items
   presently `failed_retryable` (not yet exhausted) in the **same,
   unchanged** `BulkOperation` — never touching `succeeded`, `skipped`,
   `excluded`, or already-`failed_permanent` items, and never expanding
   the frozen set.
3. **Create a new `BulkOperation` from exceptions** — an explicit action
   that freezes a **new** operation whose membership is exactly the
   `skipped`/`failed_permanent`/`cancelled` items of a prior one (their
   target identities, re-resolved and re-frozen through the ordinary
   freeze mechanism above, with fresh eligibility) — a genuinely new
   operation with its own idempotency identity, its own preflight, its
   own confirmation. This is the **only** route back to acting on a
   terminal item.
4. **Rerun the original filter as a brand-new selection** — an ordinary,
   unrelated new `BulkOperation`, sharing nothing with the prior one
   except, coincidentally, a similar filter, and **may contain entirely
   different targets** than the original if the underlying library state
   has changed — never conflated with options 2 or 3, and never silently
   including newly-matching targets under the guise of "retrying" the old
   operation.

**`retry` is a workflow action available against an existing
`BulkOperation`, never an eighth entry in the V1 operation allowlist** —
options 2 and 3 both operate through the same `operation_type` the
original operation already carried; retrying a bulk approval is still a
`bulk_approval`-typed action, never a new, distinct operation type of its
own.

### Audit and observability

- **One parent audit event** describing the requested operation, its
  canonical payload, actor, workspace, filter/selection explanation, the
  frozen scope (total/eligible/excluded counts at confirmation), and the
  canonical frozen-membership digest, recorded at confirmation.
- **Excluded-item evidence is deliberately not an execution audit
  event.** No mutation or execution occurred, so an excluded item's
  `audit_event_id` remains null. Its immutable item row records frozen
  target identity, eligibility status, typed exclusion reason,
  expected-state snapshot, and ordinal. That row plus the parent freeze
  audit is the authoritative, complete historical evidence of exclusion.
- **One item-level execution audit result per executed terminal target**,
  recorded atomically with that item's own mutation/result, carrying the
  typed terminal reason, the invoked action's own result identity, and the
  correlation chain: browser request → `BulkOperation.public_id` →
  `BulkOperationItem.id` → the invoked single-item action's own audit
  record (ADR-0030/0031's existing audit tables) or, where a subordinate
  is involved, every append-only subordinate-transition row (a
  `PromotionAttempt.public_id`, a `DocumentContentCloneOperation.public_id`,
  and/or an ADR-0007 ingestion `event_id` for fallback), with the item's
  current pointer retained only as a reconciliation optimization.
- **No raw document content, source bytes, or unsafe filename-derived
  values appear in any bulk log or audit record** — the same allowlist-
  over-blocklist discipline this entire decomposition already applies
  everywhere else; this specifically includes `target_display_label`,
  which is bounded and safely rendered in the audit/history **UI** (per
  the column definition above) but is **never** written into a
  structured log line or metric label, for exactly the same
  unbounded-cardinality/unsafe-content reason filenames never are.
- **Bounded-cardinality metrics**: duration, throughput (items/second),
  queue delay, cancellation rate, and failure rate, labelled by
  `operation_type` and terminal reason category — **never** by tenant
  identifier, filename, or any other unbounded-cardinality value.
- **Operation history is accessible to owner/admin** through a dedicated
  route (below), retained even after a target is later deleted, using
  the captured scalar public-identity/label snapshot (above) rather than
  a live join that a later deletion would break.
- **Notification consumption, reserved, not designed here**: this ADR
  emits the domain events (`bulk_operation.completed`,
  `bulk_operation.completed_with_exceptions`) ADR-0036 may subscribe to
  for notification delivery — this ADR does not define delivery,
  recipients, or preferences, only that the events exist and carry safe,
  allowlisted content.

### Browser and UX contract

**Route-backed, following ADR-0033's route hierarchy exactly**:
`/documents` gains its selection controls and bulk-action toolbar in
place; a new `/documents/bulk/{bulkOperationPublicId}` route provides the
operation-detail/result surface — a real, refresh-safe, deep-linkable,
tenant-concealed route, not a modal-only experience that loses state on
refresh.

**The library provides**: row checkboxes (only once this ADR ships, per
ADR-0033's own reserved seam); current-page selection; the explicit
all-filtered-results selection action with its visible exact frozen
count; a bulk-action toolbar offering only the operations the current
selection's target type and the viewer's role actually support;
eligibility preflight with the eligible/excluded summary and expandable,
searchable exclusion detail; the aggregate confirmation; real progress
using the item state machine above (never a fabricated percentage, and
**never a "claimed" count** — this design has no durable `claimed` item
state to back one; the only in-flight figure ever shown is **open
attempts**, which the durable `BulkOperationItemAttempt` authority above
makes genuinely observable — a bounded set of real counts, each backed by
durable state: eligible/pending, open attempts, waiting on subordinate,
succeeded, excluded, skipped, retryable failure, permanent failure,
cancelled); the partial-result summary distinguishing `completed`
from `completed_with_exceptions`; cancellation, available while the
operation is not yet fully converged; a retryable-item action (retry
semantics option 2, scoped to exactly the eligible failed items); and
links from every item's result back to its affected family/version/
import detail page.

**Tooltips and accessible information affordances are required** for:
approval versus promotion (a genuinely confusable pair this ADR's own
allowlist keeps as two distinct operations); applicability; universal
applicability; excluded; skipped; retryable failure; and the current-page-
versus-every-filtered-result distinction. Every tooltip is concise, plain-
language, keyboard-accessible, and never the sole carrier of essential
information — the same rule ADR-0033 already establishes, applied here.

**Visual system**: shadcn/ui, Tailwind, and Lucide, consistent with
ADR-0027/0033; deliberate surface/card contrast establishing hierarchy,
never a page of indistinguishable same-colour panels; expand/collapse for
detailed per-item exclusions/outcomes, with summary facts always visible
without expanding anything.

### Responsive and accessible behaviour

Desktop: a full table with an inline toolbar. Tablet: the same table with
adapted column density. Mobile: a stacked list/card adaptation that
**never loses selection state or result detail** — a mobile user can
still see exactly what is selected and drill into any item's outcome.
Selection changes and stage transitions are announced through bounded
live regions (a summary announcement per state transition, never a
per-item stream that would flood a screen reader during a large
operation's execution). Every bulk action is keyboard-reachable, with
correct focus management carried through preflight → confirmation →
results (focus moves to the next meaningful heading at each transition,
never lost to the top of the page or left on a now-removed control).
Every status uses icon/text/pattern in addition to colour. Large-selection
performance is bounded by the same maximum-target-count and query-count
requirements already fixed above. No control's only affordance is a
hover-only tooltip.

### Visual acceptance

Binding throughout implementation, per every session below: a direct
development URL; representative and deliberately awkward fixtures; dark
and light review; desktop, tablet, and mobile; keyboard/focus review;
accessible live-region review; and David's explicit approval before a
pattern is replicated. Required checkpoints: no selection; several
current-page rows selected; every filtered result selected; preflight
with all eligible; preflight with a mixed eligible/excluded set; the
high-consequence applicability-change confirmation specifically; queued/
running progress; partial completion (`completed_with_exceptions`);
cancellation requested and converged; retryable failures; a fully
successful result; and an empty/no-action (zero-eligible) result.

## Alternatives considered

### Browser-only selection tracking, with no server-side freeze

Rejected — a client-held selection list has no durable, auditable
identity and cannot survive a browser refresh or a lost response; the
frozen-membership model this ADR requires is a server-side, durable fact
from confirmation onward.

### Storing only the query and rerunning it during execution

Rejected outright, per the brief's own settled decision — a rerun query
can discover new or fewer targets than the user actually confirmed,
silently retargeting the operation.

### All-or-nothing transactions over every item in an operation

Considered, and rejected as the V1 default: a single failing item would
roll back every other, already-valid mutation in a potentially large
operation, for no benefit the honest partial-execution model doesn't
already provide more usefully. Reserved as a possible future per-operation
flag if a genuine need is demonstrated, never the default.

### One workspace-wide bulk-operation lock

Withdrawn completely, per the brief's own settled decision — any number
of operations, including of the same type, may run concurrently; only
per-item mutation boundaries serialise.

### Automatic retry of every failure, without a bounded ceiling

Rejected — an unbounded automatic retry loop is exactly the hazard
ADR-0007 already rejects for ordinary document processing; a bounded
ceiling, then `failed_permanent`, applies identically here.

### Combining promotion and approval into one bulk action

Rejected outright, per the brief's own settled decision — promotion and
approval remain distinct operation types, distinct confirmations, and
distinct audit events; no combined shortcut is introduced.

### Including bulk deletion, withdrawal, or rescheduling in the V1
### allowlist

Rejected for V1 — both are genuinely destructive or authority-affecting
at scale; the existing single-item path remains fully available for
either, and this ADR declines to introduce their bulk form without a
separately demonstrated need.

### Python-owned bulk orchestration

Rejected outright — orchestration, eligibility, authorization, and
aggregation are Laravel's exclusively; Python's role, where invoked at
all (applicability cloning), is exactly the same bounded technical role
it already has for a single, non-bulk request.

### Synchronous execution inside the confirming browser request

Rejected — a bulk operation's total execution time scales with its
target count and cannot be safely bounded to one HTTP request/response
cycle; the Laravel database-queue model is required instead.

### A maintained duplicate aggregate-counter column on `BulkOperation`

Rejected — every count is derived from `BulkOperationItem` rows at read
time, closing the two-fields-that-can-disagree hazard this platform has
repeatedly rejected elsewhere (ADR-0007, ADR-0017, ADR-0034).

### An unrestricted, generic `target_type` + `target_id` polymorphic
### column

Rejected — verified this would permit a structurally cross-workspace-
unsafe reference with no database-enforceable guarantee; three
individually-typed nullable foreign keys, an FK-bound discriminator
duplicated from the parent, and a same-row `CHECK` are the selected
alternative, for keeping shared parent/status/audit columns in one table
without sacrificing type safety.

### A cross-table `CHECK` constraint comparing an item's target columns
### directly against its parent's `operation_type`

This was an earlier design in this ADR and is withdrawn as unimplementable
— verified that PostgreSQL `CHECK` constraints cannot inspect another
table at all; no such constraint could ever have enforced the comparison
it described. The corrected design duplicates `operation_type` onto the
item itself, binds it to the parent by an ordinary composite foreign key
(so it can never diverge), and expresses the actual target-shape rule as
a genuine same-row `CHECK` — closing the gap with two real, fully-
supported PostgreSQL features instead of one fictional one.

### Composite target foreign keys including `workspace_id`, with
### `ON DELETE SET NULL`

Considered, and rejected on inspection: PostgreSQL's `ON DELETE SET NULL`
nulls every column participating in the foreign key together, so a
composite `(target_family_id, workspace_id)` FK would null the item's own
`workspace_id` the instant its target was deleted — destroying the
tenancy scoping this ADR's retained history depends on. Single-column
target FKs, with cross-workspace safety enforced instead by a database
trigger (the same pattern ADR-0017's migration already establishes for an
analogous cross-table invariant), avoid this entirely.

### An unconditional target-shape `CHECK` with no live/retained
### discriminator, relying on `nullOnDelete()` alone

This was an earlier design in this ADR and is withdrawn as
self-contradictory — Codex correctly identified that PostgreSQL
re-evaluates every `CHECK` constraint on a row as part of the very
`UPDATE ... SET target_document_id = NULL` that implements
`ON DELETE SET NULL`, so a `CHECK` that unconditionally required a
non-null target column for its `operation_type` would reject that
`UPDATE` outright, coupling this ADR's history retention to every
deletion path in ADR-0025/ADR-0031 in a way neither of those ADRs'
deletion designs can accept. The corrected design adds
`target_reference_status` as an explicit discriminator, branches the
`CHECK` on it, and performs the live-to-retained transition as a single
atomic `UPDATE` (the retirement trigger, above) that changes the
discriminator and nulls the FK together — so the row is never evaluated
in an invalid intermediate shape and the `CHECK` never contradicts the
deletion it is meant to survive.

### `restrictOnDelete()` on the target foreign keys, to protect retained
### history

Rejected — this would block ADR-0025's and ADR-0031's own deletion paths
from ever removing a family, version, or `ImportItem` that any bulk
operation had ever targeted, permanently, which neither of those ADRs'
deletion designs can accept. `nullOnDelete()`, with the permanently
retained `target_kind`/`target_public_id`/`target_display_label` scalar
columns carrying the historical meaning instead, is the design that
satisfies both "retained history" and "deletion is never blocked"
simultaneously.

### A durable `claimed` item state with a lease, heartbeat, and expiry-
### based reclaim

This was an earlier design in this ADR and is withdrawn as unsafely
underspecified — a durable `claimed` row with no accompanying lease-
generation, heartbeat, or expiry mechanism cannot structurally guarantee
a crashed worker's claim is ever released, and adding that machinery
would duplicate ADR-0025's `IngestionEventClaim` lease pattern for no
benefit a bulk item's own short, bounded execution actually needs.
**Further corrected in a later pass**: the single-short-transaction
design this entry originally proposed as the fix was itself withdrawn —
see "A single claim-through-commit transaction, with no durable
per-attempt record" below — in favour of the durable
`BulkOperationItemAttempt` authority, which achieves the same "no
stranded lease" property without requiring the domain mutation and its
outcome-recording to be the same transaction.

### A single claim-through-commit transaction, with no durable
### per-attempt record

This was an earlier design in this ADR and is withdrawn as unsafe on two
independent grounds Codex identified. First, a transaction that itself
aborts cannot durably record its own failure or increment its own retry
count against a row it never committed — "the current model can retry
forever," since nothing survives the rollback to remember the attempt
happened at all. Second, treating "commit the item's terminal state" and
"commit the parent's convergence decision" as necessarily the same
transaction as the domain mutation left no room for a **shared lock
order** across the several different call sites (ordinary execution,
subordinate reconciliation, cancellation convergence, explicit
reconciliation) that all need to perform the same convergence decision
safely relative to one another. The corrected design splits attempt
claim/invocation from item finalisation into genuinely separate
transactions, backed by the durable `BulkOperationItemAttempt` table, and
introduces one consistent parent-then-item lock order for finalisation
specifically — see "Introducing a durable per-item attempt/failure
authority" and "Item finalisation and parent-lock convergence protocol"
above.

### Committing the parent's terminal status "in the same transaction as
### the last item's own terminal commit," with no shared lock

This was an earlier design in this ADR and is withdrawn as unsafe under
PostgreSQL `READ COMMITTED` — Codex correctly identified that two items
finishing at nearly the same moment, each in its own transaction, could
each independently query the item distribution without ever seeing the
other's just-committed row, and race to decide (and write) the parent's
status, each believing itself to be deciding a fully-converged
distribution. "Same transaction as the item's own commit" was true and
irrelevant: the race was never within one item's transaction, but
between two different items' transactions. The corrected design requires
every convergence decision to first acquire the `BulkOperation` parent
row's own lock, which totally orders any two transactions attempting to
converge the same operation — see "Item finalisation and parent-lock
convergence protocol" above. The honest cost is accepted explicitly: this
serialises the finalisation step to one item at a time per operation,
never a workspace-wide lock, and never affecting the (fully concurrent)
domain-mutation work itself.

### Restating the parent terminal mapping as parallel prose, a
### distribution table, and a diagram, with no single normative function

This was an earlier design in this ADR and is withdrawn because the three
descriptions were found to drift — the zero-total-item case and the
"unexpected cancelled" defensive branch had no home in any of the three,
and nothing prevented a future edit from updating one description without
the others. The corrected design states exactly one ordered, total
function ("Parent state machine" above) and requires every other section,
test, and the final report to reference it by name rather than restate
its branches.

### Assuming every subordinate workflow's identity is a `public_id`

This was an earlier design in this ADR and is withdrawn as incomplete —
ordinary full-ingestion fallback has no `public_id`-bearing row of its
own to reference; forcing one would have meant inventing a row this ADR
has no other reason to create, purely to satisfy a column's assumed
shape. The corrected design generalises the column into a
`subordinate_identity_kind`/`subordinate_identity_value` pair, with a
same-row `CHECK` binding each `subordinate_kind` to its one permitted
identity kind, and uses ADR-0007's real ingestion lineage `event_id` for
the fallback case.

### Exposing a fabricated "claimed" count in the browser progress UI

This was an earlier design in this ADR and is withdrawn because this
design has no durable `claimed` item state to back such a count with —
displaying one would have shown a number backed by nothing durably
observable. The corrected design shows only counts backed by durable
state, including a genuinely durable **open attempts** count, made
possible specifically by the `BulkOperationItemAttempt` authority
introduced in this pass.

### Treating a bulk item as `succeeded` the moment it merely initiates a
### subordinate workflow

This was an earlier design in this ADR and is withdrawn as internally
contradictory — it allowed an item to be reported terminal-successful at
the instant a `PromotionAttempt` or content-clone operation was merely
*created*, while separately (correctly) requiring that subordinate to
reach its own committed/indexed outcome before the underlying work is
actually done; a later subordinate failure would then have nothing left
to report against, since the item had already left its terminal state
unreachable. The corrected design introduces the non-terminal
`waiting_on_subordinate` item state, kept open until the subordinate
itself reaches a genuine terminal outcome, and only then converts to the
item's own truthful terminal state.

### A transaction-local custom GUC as the retirement trigger's authority

This was an earlier design in this ADR and is withdrawn because a
caller-controlled setting is forgeable by the Laravel runtime role and
therefore cannot prove that target deletion caused a child transition.
The corrected design uses protected columns, a trigger-only `SECURITY
DEFINER` function owned by the non-login owner, and the real database-role
identity described above. The rejected GUC design has no live or
diagnostic role in the implementation.

### Assuming `ApproveDocumentVersion` and the ADR-0030 metadata actions
### need no durable idempotency/result primitive because they are
### "naturally idempotent"

This was an earlier design in this ADR and is withdrawn as conflating a
domain effect's determinism with the safety of blindly replaying it.
`ApproveDocumentVersion` requires `DRAFT` and **rejects** a second
invocation once the version is no longer `DRAFT` — a crash-and-replay
with no durable record of the first attempt's own outcome would
misinterpret that rejection as a failure. The corrected design commits
the domain mutation, its audit event, a durable result/idempotency
record, and the `BulkOperationItemAttempt`'s own `succeeded` status
together, in one atomic transaction, per action family — reusing
ADR-0031's governance-idempotency record for approval, and treating the
attempt row itself as the durable authority for metadata actions, which
have no separate idempotency table of their own.

### An item-level `CHECK` design composed of several independently-correct
### clauses, rather than one closed truth table

This was an earlier design in this ADR and is withdrawn because
independently-composed clauses each correctly asserted what a given state
*must* have, but none of them asserted what every *other* state must
**not** have — leaving a partial subordinate tuple on a terminal row, and
a stray `terminal_reason` on a non-terminal-reason state, both
unconstrained. The corrected design is one `CHECK`, expressed as mutually
exclusive branches (one per `execution_status` value), each branch a
complete, two-directional specification — see "Complete item-level
database constraints" above.

### No stale-worker fencing, relying on the lease-expiry reclaim alone to
### prevent a late mutation

This was an earlier design in this ADR and is withdrawn as incomplete —
a bounded lease and a reclaim sweep prevent a stale attempt from being
*counted* as still open, but nothing previously stopped a worker that
merely paused past its own lease (a long GC pause, a slow network call
it was never supposed to make but did) from waking up and mutating
anyway, unaware it had been reclaimed. The corrected design adds a
fencing token/generation pair, verified under lock immediately before
every mutation, so a stale worker's own mutation attempt fails closed
regardless of whether it "believes" its lease is still valid.

### Leaving the lock order implicit, verified only piecemeal per section

This was an earlier design in this ADR and is withdrawn because a lock
order that is only ever justified locally, section by section, cannot be
checked for a global cycle — the exact category of bug a deadlock is.
The corrected design states one explicit, named lock order up front
("Global lock order" above), covering every phase this ADR defines, with
one explicit cycle-freedom argument covering all of them together.

### Committing an attempt's terminal outcome and incorporating it into
### the item as two independently-timed transactions with no durable link

This was an earlier design in this ADR and is withdrawn as leaving
exactly the crash window Codex identified: between an attempt's own
terminal commit and a later, separate item-finalisation transaction, the
item could still read `eligible`/`failed_retryable` with nothing to stop
the claim query from opening a second attempt and repeating an
already-applied mutation. The corrected design adds
`BulkOperationItem.incorporated_attempt_generation`, a composite-FK-bound,
trigger-guarded marker naming the exact attempt generation the item's
current state was derived from, and rewrites the claim query to refuse
any item with **any newer unincorporated generation** outstanding, open or
terminal — see
"The item-to-attempt incorporation marker" above.

### Granting the runtime role unrestricted, whole-row `INSERT` on
### `bulk_operation_items`

This was an earlier design in this ADR and is withdrawn as leaving a
structural path to the exact retained-deleted shape the retirement
privilege boundary exists to protect — Codex correctly identified that an
unrestricted `INSERT` grant would let runtime create a row already
`target_reference_status = 'target_deleted'` directly, bypassing the
retirement trigger entirely while still satisfying the target-shape
`CHECK`. The corrected design revokes table-level `INSERT` from the
runtime role and re-grants it only on every column except
`target_reference_status`, relying on that column's own `DEFAULT 'live'`
for ordinary inserts, and extends the existing guard trigger to also
fire `BEFORE INSERT`.

### Describing the default-privilege baseline as covering "every future
### object" while its `ALTER DEFAULT PRIVILEGES` rules named only tables
### and sequences

This was an earlier design in this ADR and is withdrawn as an overclaim —
Codex correctly identified that PostgreSQL grants `EXECUTE` on a newly
created function to `PUBLIC` automatically, and the prior baseline's
default-privilege rules never revoked that for **functions**, only for
tables and sequences, leaving every future function reachable by runtime
regardless of intent. The corrected design adds an explicit
`REVOKE EXECUTE ON FUNCTIONS FROM PUBLIC` default-privilege rule
alongside the existing table/sequence ones, and sweeps every existing
function retroactively during the foundation migration.

### An item `CHECK` truth table that constrained every column the prior
### audit named, but left `started_at` and `result_identity` unconstrained

This was an earlier design in this ADR and is withdrawn as the same class
of gap the truth-table correction itself was meant to close — closing two
named gaps without sweeping every state-bearing column left two more of
exactly the same shape. The corrected design adds both columns to every
branch of the primary truth table (with `started_at` tied one-to-one to
`incorporated_attempt_generation`, and an explicitly documented either/or
exception for `skipped`), and introduces a second, operation-type-aware
`CHECK` for `result_identity` specifically on `succeeded`, since that
column's requirement genuinely varies by `operation_type` in a way the
execution-state-keyed primary table should not be overloaded to express.

## Consequences

### Positive

- ADR-0033's reserved selection seam finally has a real, safe backing
  domain.
- Every included operation reuses an existing, already-decided single-
  item action unmodified — no governance rule is duplicated or
  reinterpreted at bulk scale.
- The frozen-membership model, the discriminated typed-target design, and
  the derived-never-stored aggregate rule each close a real correctness
  risk this decomposition has repeatedly had to correct for other
  entities.
- Deferring bulk deletion/withdrawal keeps V1's blast radius honestly
  bounded.
- Reusing Laravel's own already-proven database-queue pattern avoids
  inventing a new execution mechanism, and correctly avoids importing the
  Python HMAC worker pattern where no external trust boundary is actually
  crossed.

### Negative

- The three-way discriminated foreign-key design on `BulkOperationItem`
  is more schema surface than a single generic pointer would have been —
  accepted because the generic alternative is structurally unsafe.
- Bulk applicability change is the most complex operation in the
  allowlist, inheriting the full weight of ADR-0031's clone lifecycle per
  item — a real implementation cost, not a thin wrapper.
- Retained, undeleted operation history grows unboundedly over a
  workspace's lifetime — an accepted, bounded-per-row storage cost, not a
  performance concern at the scale this ADR targets.
- The maximum-target-count bound and transaction-duration monitoring
  requirement mean a very large "select all" action may be rejected
  outright rather than silently degrading — a real, visible V1
  limitation communicated honestly to the user rather than hidden.
- **The parent-lock convergence protocol serialises one operation's own
  item-finalisation step to one item at a time** — accepted honestly, not
  hidden: it is scoped to a single `BulkOperation` row's own lock (never
  workspace-wide, never affecting any other operation's own finalisation),
  the lock is held only across a short, indexed count-and-conditional-
  write, and it does not affect the fully concurrent domain-mutation work
  itself, which never touches the parent at all.
- **The durable attempt-authority table (`BulkOperationItemAttempt`)
  adds one further table and a bounded lease-expiry sweep** beyond what
  the (unsafe) single-transaction design would have required — accepted
  because it is what makes the retry ceiling, and duplicate-failure
  reporting, durable against a mutation that itself aborts.

## Scope boundaries

This ADR does not define: ADR-0030/0031's governance or metadata rules;
ADR-0032's artefact/projection mechanics; ADR-0034's staging/promotion
mechanics beyond invoking its existing `PromotionAttempt` sequence and
shared checksum-serialization primitive unmodified; ADR-0036's
notification delivery, recipients, or preferences; ADR-0037's export;
bulk deletion, withdrawal, or rescheduling (explicitly deferred); the
exact numeric values for maximum target count, retry ceilings, or
stuck-operation thresholds (R25/R26 implementation measurement).

## Testing

Mapped to ADR-0029's taxonomy. Provider-free coverage required for: page-
versus-all-filtered selection; immutable frozen membership (a filter
change after freeze never alters it); filter/sort/saved-view mutation
clearing an in-progress selection; cross-workspace exclusion and
concealment in the freeze query itself; every `BulkOperationItem`
database constraint (the same-row target-shape `CHECK`, the parent-bound
discriminator composite FK rejecting a mismatched `operation_type`, the
three partial unique indexes, the composite workspace-scoping FK to the
parent); the `enforce_bulk_operation_item_target_workspace()` trigger
rejecting a cross-workspace target reference; `nullOnDelete()` correctly
nulling only the target FK column while `workspace_id`/`target_kind`/
`target_public_id`/`target_display_label` survive a target's deletion
unchanged; the `target_no_longer_exists` skip path for an item whose
target was deleted after freeze; idempotent request replay
and digest-conflict rejection; two concurrent bulk operations targeting
the same item (one succeeds, the other observes the committed state and
records a typed `skipped`); a target's state changing between
confirmation and execution; authorization loss during execution
(fail-closed); per-item lock-and-revalidate correctness; partial success
(`completed_with_exceptions`); cancellation convergence (claimed items
finish, eligible items do not start); crash/`SKIP LOCKED` reclaim; retry
option 2 touching only retryable items, never expanding membership;
derived-aggregate correctness against underlying item rows; bulk approval
under a lapsed authority window; applicability-successor initiation
correctly acquiring ADR-0034's shared checksum-serialization lock; no-op
applicability exclusion; complete audit-chain correlation; bounded query-
count/N+1 checks for the freeze and preflight queries; a measured
benchmark of the bulk `INSERT ... SELECT` at a representative large
selection size; and accessibility/live-region behaviour.

**Additional coverage required for this round's corrections**: every
parent terminal state reachable exactly under its own item-distribution
condition, and no other (`completed`, `completed_with_exclusions`,
`completed_with_exceptions`, `cancelled`,
`cancelled_after_partial_execution`, `failed_before_execution`); the
parent-status reconciliation action correctly repairing the narrow crash-
window race and being a safe no-op when no repair is needed; an item
entering `waiting_on_subordinate` and later converging to its correct
terminal state on both the success and failure paths, for both a
`PromotionAttempt` and a content-clone/full-ingestion-fallback
subordinate; the reconciliation loop observing a subordinate's outcome
without ever holding a lock while waiting; a `waiting_on_subordinate`
item correctly surviving cancellation of its parent operation unmutated
(never cancelled out from under already-committed subordinate work); the
one-way, irreversible `failed_retryable` → `failed_permanent` transition
on ceiling exhaustion, and that no code path ever reopens a
`failed_permanent`, `skipped`, `excluded`, `cancelled`, or `succeeded`
item; each of the seven per-operation contract matrix rows — every listed
exclusion category, every listed mutation-boundary skip/conflict
category, and every listed retryable/permanent failure category actually
reachable and correctly classified; and the initiating-actor-versus-
executing-identity distinction, asserting the initiating actor's
recorded identity on `BulkOperation` is never overwritten by the system
identity that later executes or reconciles an item.

**Additional coverage required for the target-deletion/`CHECK`
correction**: deleting a live target (a family, a version, an
`ImportItem`) that a `BulkOperationItem` references succeeds and leaves
that item in the retained shape (`target_reference_status =
'target_deleted'`, all three target FKs null, `target_kind`/
`target_public_id`/`target_display_label`/`workspace_id` unchanged); the
retirement trigger's single `UPDATE` never raises a `CHECK` violation
against its own transition; an attempt to set
`target_reference_status = 'target_deleted'` (or null a target FK)
directly from application code, outside the retirement trigger, is
rejected by the guard trigger; a `target_deleted` item is correctly
skipped with `target_no_longer_exists` at the per-item revalidation step
without a null-FK lookup; the cross-workspace trigger does not misfire on
the retirement trigger's own nulling `UPDATE`; and, as a defensive-
backstop regression test, a simulated direct `UPDATE ... SET
target_document_id = NULL` on a live-shape row without also flipping
`target_reference_status` is rejected by the (unchanged) target-shape
`CHECK` — proving the backstop still fails safely rather than silently.

**Additional coverage required for this pass's corrections (terminal
function, parent-lock convergence, durable attempt authority, and the two
subordinate mapping tables)**:

- **Terminal-mapping function**: every branch of
  `resolve_bulk_operation_terminal_state` exercised directly with
  provider-free unit tests, including the zero-`total_item_count`
  short-circuit (`completed_with_exclusions`), the `failed_before_execution`
  branch, cancellation with zero non-cancelled outcomes (`cancelled`),
  cancellation with at least one succeeded/skipped/failed_permanent
  (`cancelled_after_partial_execution`), cancellation plus failures but no
  successes, cancellation plus skips, cancellation plus exclusions,
  cancellation plus a subordinate resolving to success after cancellation
  was requested, cancellation plus a subordinate resolving to failure
  after cancellation was requested, all-excluded with zero eligible, and
  the defensive "cancelled item with `cancellation_requested = false`"
  branch.
- **Parent-lock convergence protocol**: two final items in the same
  operation committing their own terminal transitions concurrently (from
  two separate database connections) — asserting the parent converges to
  exactly one correct terminal status, never a lost update, never two
  conflicting writes; the explicit reconciliation action invoked
  concurrently with an in-flight ordinary finalisation for the same
  operation, asserting no double-write and no incorrect regression of an
  already-terminal status; the reconciliation action correctly repairing
  a parent left `running` after a simulated crash between finalisation
  steps, and being a safe no-op when no repair is needed.
- **Durable attempt authority**: a domain-mutation transaction that
  itself aborts (a simulated database error) never durably increments any
  retry count, and the subsequent fresh failure-recording transaction
  correctly writes the attempt's own `failed_retryable`/`failed_permanent`
  status; duplicate failure reporting against the same attempt `id` is a
  no-op (zero rows affected on the second call); a simulated worker crash
  leaving an attempt `open` past its `lease_expires_at` is correctly
  reclaimed exactly once by the sweep, and two concurrent sweep
  invocations never double-reclaim the same attempt; the retry ceiling is
  computed correctly from durable attempt rows and converges to
  `failed_permanent` at the configured limit, never earlier and never
  later; the one-open-attempt-per-item partial unique index rejects a
  second concurrent open attempt for the same item.
- **ADR-0034 mapping table**: every named `PromotionAttempt` state
  (`RESERVED`/`COPYING`/`SOURCE_VERIFIED`/`COMMITTED`/`CONFLICT`/`FAILED`/
  `ABANDONED`/`EXPIRED`) drives the bulk item to exactly the outcome the
  mapping table specifies, with the correct typed reason for each of the
  four `failed_permanent`-producing states.
- **ADR-0031 clone/fallback mapping table**: an immediate compatibility-
  proof failure correctly initiates full-ingestion fallback (never a
  skip); `CLEANUP_REQUIRED`/`FALLBACK_READY` correctly remain
  `waiting_on_subordinate`; a full-ingestion fallback reaching ADR-0007's
  ingestion `INDEXED` correctly resolves `succeeded`, tracked by its real
  `event_id` lineage (`subordinate_identity_kind = 'event_id'`); a
  full-ingestion fallback reaching ADR-0007's ingestion `FAILED` after its
  own ceiling correctly resolves `failed_permanent`
  (`full_ingestion_failed`); a bounded checksum-lock-wait timeout
  correctly records an attempt-level `failed_retryable`
  (`checksum_lock_timeout`), never a preflight exclusion or mutation-
  boundary skip; ordinary lock waiting under the timeout produces no
  observable outcome change at all.
- **Item-level database constraints**: every `CHECK` introduced in this
  pass rejected by a direct, deliberately invalid `INSERT`/`UPDATE` —
  an excluded item with a null `exclusion_reason` or a populated
  subordinate field; an eligible item with a non-null `exclusion_reason`;
  a `waiting_on_subordinate` item missing any one of its four required
  subordinate fields; a terminal item missing `completed_at` or
  `audit_event_id`; a non-terminal item (including `failed_retryable`)
  carrying either; the `subordinate_kind`/`subordinate_identity_kind`
  binding `CHECK` rejecting a mismatched pair (e.g.
  `promotion_attempt`/`event_id`).
- **Fabricated-count absence**: the progress UI's rendered output (or its
  underlying read-model query) is asserted to expose no field or label
  resembling a "claimed" count anywhere; only the durably-backed counts
  named above are asserted present.

**Additional coverage required for this pass's corrections (retirement
privilege boundary, the closed item-`CHECK` truth table, atomic
committed-success recovery, stale-worker fencing, the global lock order,
and crash recovery)**:

- **Retirement privilege boundary**: legitimate family deletion,
  legitimate `Document` deletion, and legitimate `ImportItem` deletion
  each succeed and correctly retire their referencing items, run against
  the actual `rag_platform_app` role (never a superuser/owner shortcut);
  a direct `rag_platform_app` `UPDATE` against any protected column
  (`target_reference_status` or a target FK) fails with a permission
  error; the identical direct update, retried after supplying arbitrary
  caller-controlled session context as `rag_platform_app`, fails
  identically; a raw SQL caller (not routed
  through Eloquent) attempting to null a live target fails the same way;
  an attempt to update an immutable scalar target-identity field
  (`target_kind`/`target_public_id`/`target_display_label`/`workspace_id`)
  as `rag_platform_app` fails.
- **Three-role provisioning and credential isolation**: owner is
  `NOLOGIN`; runtime cannot authenticate or `SET ROLE` as owner/migrator;
  the one-shot migrator can explicitly assume owner; migrated objects are
  owned by owner rather than migrator; API/queue/scheduler containers
  expose only runtime credentials and no long-running service exposes the
  migrator secret; existing application behavior passes under the
  restricted runtime role; baseline and owner-scoped default privileges
  cover every allowlisted application schema, existing/future table, and
  existing/future sequence.
- **Item `CHECK` truth table**: a direct `excluded`-row insertion with
  every field in the shape the truth table requires succeeds; every
  invalid `eligibility_status`/`execution_status` pairing is rejected;
  a `terminal_reason` outside `skipped`/`failed_permanent`/`cancelled` is
  rejected; a partial subordinate tuple (any one of the four fields set
  without the other three) is rejected on every `execution_status`,
  including `succeeded` and `failed_permanent`; a terminal item
  (`succeeded`/`failed_permanent`) carrying a subordinate tuple where none
  was ever initiated is rejected at the application layer by construction
  (never reachable, since finalisation only ever writes a subordinate
  tuple it durably initiated itself) and, at the database layer, a
  directly inserted mismatched combination is rejected by the `CHECK`;
  a subordinate-backed item's complete tuple is retained, unchanged,
  after it resolves terminally.
- **Attempt CHECK and generation**: every new attempt receives
  `MAX(previous generation) + 1`; concurrent allocation cannot duplicate
  it; historical ordinal/generation/token rows are immutable; every
  `open`, `not_applied`, failed/abandoned, database-local success, and
  subordinate-initiation success branch is directly accepted while every
  partial or cross-branch result-evidence combination is rejected. An
  expected-state mismatch atomically records `not_applied` and later
  finalises `skipped`, never remains open and never counts as retryable
  failure.
- **Excluded audit completeness**: every excluded immutable item is
  correlated with the parent freeze audit and canonical membership digest;
  no execution audit identity is fabricated, and the history projection
  remains complete from those two authorities.
- **Subordinate transition ledger**: promotion records its attempt;
  clone-to-fallback retains both clone and ingestion identities in order;
  direct fallback records only `event_id`; duplicate transition append is
  a no-op; terminal item history retains every transition after current
  pointer convergence.
- **Atomic committed-success recovery**: a simulated crash immediately
  after an approval's atomic mutation/audit/idempotency-record/attempt-success
  commit, but before item finalisation, is recovered by finalisation
  alone, with **zero** additional invocations of `ApproveDocumentVersion`;
  the identical scenario for **each** ADR-0030 metadata action, recovered
  from the attempt's own `result_digest` with zero additional mutations; a
  simulated crash immediately after subordinate-identity creation commits,
  but before the item enters `waiting_on_subordinate` is observed by any
  reconciliation pass, recovered by finalisation alone, with **zero**
  additional `PromotionAttempt`/clone creations.
- **Fencing**: a worker whose lease has already expired, resuming and
  attempting its own mutation, fails closed (no mutation, no attempt-status
  write) because its `(attempt_token, generation)` no longer matches; the
  reclaimer racing a live (not actually dead) worker's own commit —
  whichever transaction's lock is acquired first wins, and the other
  observes a `status` that is no longer `open` and performs no write;
  an attempt-generation mismatch (a worker holding a superseded
  `generation` after a reclaim-and-reopen) is rejected.
- **Duplicate reporting**: duplicate success, `not_applied`, and failure
  reports against the same attempt `id` are each a no-op (zero rows
  affected on the second call).
- **Lock-order/deadlock stress**: two simultaneous approvals of different
  versions in one family prove the deterministic family-document order;
  subordinate target/identity → attempt → item, concurrent target deletion,
  database-local mutation, reclaimer, and item/parent finalisation are run
  repeatedly against overlapping items and produce no deadlock or lost
  update. Query inspection asserts no later unordered family lock query.

**Additional coverage required for this pass's corrections (the
item-to-attempt incorporation marker, runtime `INSERT` restrictions,
future-function privilege defaults, and the completed `started_at`/
`result_identity` truth table)**:

- **Incorporation marker**: a simulated crash after a terminal attempt
  (`succeeded`, `not_applied`, `failed_retryable`, `failed_permanent`, or
  `abandoned`) commits but before item finalisation incorporates it —
  the corrected claim query selects zero rows for that item, and the
  pending attempt is instead routed to finalisation; a concurrent claim
  attempt during that exact window is rejected identically; a
  finaliser-versus-claimer race (both contending for the item row)
  produces no double-attempt outcome regardless of which wins the lock;
  a duplicate finalisation of the same already-incorporated generation is
  a no-op (the guard trigger's same-value rule); an attempted regression
  of `incorporated_attempt_generation` to a lower value is rejected; a
  crafted `incorporated_attempt_generation` referencing another item's
  own generation is rejected by the composite foreign key; a retry is
  only ever claimable once the previous terminal result is incorporated,
  never before; a stale worker whose own attempt has already been
  incorporated as `succeeded` by another process is unable to mutate
  anything on a later, superseded attempt. Two claimers while generation N
  is open produce only generation N; generation N terminalising while a
  second claim waits still produces no N+1; the open-attempt partial-unique
  conflict clearing before incorporation does not permit N+1 because the
  generation predicate continues to reject N; terminal generation N is
  routed to finalisation rather than bypassed; an expired open N becomes
  durably `abandoned` and is incorporated before any retry opens; and a
  claim succeeds only after the prior generation is incorporated and the
  resulting item remains legitimately `failed_retryable` below ceiling.
- **Runtime `INSERT` restriction**: an ordinary frozen-membership
  `INSERT` that omits `target_reference_status` receives `'live'` from
  the column default; an explicit runtime `INSERT` naming
  `target_reference_status = 'target_deleted'` is rejected at the
  privilege-check stage, via raw SQL and via ORM mass assignment alike;
  an owner-executed migration/backfill `INSERT` of a historical
  `target_deleted` row succeeds; legitimate target deletion still
  retires the referencing item successfully, unaffected by the new
  `INSERT` restriction.
- **Function privilege defaults**: an existing function has no `PUBLIC
  EXECUTE` after the foundation migration; a newly created ordinary
  function, a newly created trigger function, and a newly created
  `SECURITY DEFINER` function each receive no `PUBLIC EXECUTE` by
  default; `rag_platform_app` holds `EXECUTE` only on the explicitly
  audited, intended set of runtime-callable functions, and nothing else.
- **Completed item truth table**: rejection tests for an `excluded` item
  carrying `started_at` or `result_identity`; an `eligible` item carrying
  either; a `waiting_on_subordinate` item carrying `completed_at`,
  `audit_event_id`, or `result_identity`; a `skipped`/`failed_permanent`
  item carrying a non-null `result_identity`; a `succeeded` bulk-promotion
  or bulk-applicability-change item with a null `result_identity`; a
  `succeeded` approval or metadata-assignment item with a non-null
  `result_identity`; every partial subordinate tuple on every branch;
  and a `started_at`/`incorporated_attempt_generation` pair with exactly
  one of the two null on any branch other than `skipped`.

**Required Playwright journey**: filter the library; select every
filtered result; review the exclusion detail; confirm a bulk approval;
observe honest, real progress; reach the result; **verify the approved
documents become current/searchable only once their existing, unmodified
downstream technical lifecycle (ADR-0007/0032) actually completes** —
never presented as searchable merely because the bulk approval itself
committed.

No live-provider run is required to prove bulk orchestration itself; any
provider-dependent downstream ingestion evidence (a full-ingestion
fallback from a failed clone) remains governed by its own existing
ADR-0029 boundary, unmodified by this ADR.

## Phase/session allocation

Consuming ADR-0034's `ImportItem`/`PromotionAttempt` primitives, this
ADR's implementation follows ADR-0034's within the Import, Staging and
Bulk Governance phase; its browser preflight/result surfaces are
sequenced after ADR-0034's own primitives exist, matching the
decomposition's earlier stated intent.

- **Foundation — PostgreSQL role/bootstrap boundary, before dependent
  migrations.** Provision owner/migrator/runtime roles; isolate secrets;
  apply/reconcile baseline and default privileges for all application
  objects; migrate local, CI, and deployment topology; prove the complete
  existing application under `rag_platform_app` before creating protected
  bulk grants.
- **Session 1 — Bulk domain, schema, frozen membership, provider-free API
  tests.** `BulkOperation`/`BulkOperationItem`/
  `BulkOperationItemAttempt`/`BulkOperationItemSubordinateTransition`
  schema and constraints; the
  bounded freeze `INSERT ... SELECT` for both library and `ImportItem`
  targets; the idempotency identity; the parent/item state machines'
  domain-layer implementation; per-operation preflight/exclusion logic for
  the full V1 allowlist.
- **Session 2 — Laravel queue execution, concurrency, cancellation,
  retry, audit.** The bounded-batch claiming job; per-item lock/
  revalidate/invoke/commit; cancellation convergence; the four retry
  concepts; stuck-operation visibility; the complete audit chain; bounded-
  cardinality metrics.
- **Session 3 — Library selection, preflight, progress/result UI, and the
  required Playwright journey.** The full browser/UX contract above,
  including every named visual checkpoint, and the end-to-end journey
  proving genuine downstream searchable readiness, not upload/promotion
  alone.

## Required final report — describes the corrected ADR, not any earlier draft

This ADR has now been through **seven** implementation-readiness
correction passes, the first six summarised at the top of the prior
report below this one in the file's own history. **This report covers
the seventh pass**, which closed the four concrete implementation
blockers Codex's read-only freeze audit found: a crash window allowing a
terminal attempt to be duplicated before item finalisation; a runtime
`INSERT` path able to manufacture a retained-deleted item; PostgreSQL's
default `PUBLIC EXECUTE` surviving on future functions; and two more
state-bearing item columns (`started_at`, `result_identity`) left
unconstrained by the item `CHECK` truth table.

1. **Exact sections changed**: "Target-type enforcement" (the item
   `CHECK` truth table fully replaced, with a new second operation-type/
   result-shape `CHECK`; new column-sweep note); `BulkOperationItem`
   schema table (`incorporated_attempt_generation` added; `result_identity`
   description updated); "Reconciling live targets with permanent history"
   (`INSERT` privilege restriction, `DEFAULT 'live'` cross-reference, guard
   trigger extended to `BEFORE INSERT OR UPDATE`, function-privilege sweep
   extending the runtime grant baseline); "Introducing a durable per-item
   attempt/failure authority" (new "The item-to-attempt incorporation
   marker" subsection); the claim step, subordinate-initiation phase, item
   finalisation step 3, and the crash-recovery table (all updated to read
   and write the new marker); "Item state machine" (diagram and
   `failed_retryable` row corrected — claimed directly, never routed back
   through `eligible`); "Retry semantics" (two bullets corrected to match);
   "Alternatives considered" (four entries added); "Testing" (new coverage
   block); this report.

2. **Item-to-attempt incorporation marker**:
   `BulkOperationItem.incorporated_attempt_generation` — nullable;
   null exactly until the item's first attempt is incorporated, non-null
   forever after; bound by a composite FK `(id,
   incorporated_attempt_generation) REFERENCES bulk_operation_item_attempts
   (bulk_operation_item_id, generation)`, making "cannot point to another
   item's generation" structural; a new trigger,
   `enforce_bulk_operation_item_incorporation()` (`BEFORE UPDATE` on
   `bulk_operation_items`), rejects regression (`NEW <
   OLD`, when `OLD` is non-null) while explicitly permitting a same-value
   rewrite (idempotent duplicate finalisation), and rejects advancing to a
   generation whose own attempt is still `open`. Every write to this
   column happens in the same statement as the item's own terminal (or
   `waiting_on_subordinate`) write — never a separate statement.

3. **Corrected claim algorithm**: `SELECT ... FOR UPDATE SKIP LOCKED`
   WHERE `execution_status IN ('eligible', 'failed_retryable')` **AND NOT
   EXISTS** any attempt for this item with `generation >
   COALESCE(incorporated_attempt_generation, 0)`, regardless of attempt
   status. This clause satisfies all six numbered rules in the brief: the
   item may retain `eligible`/`failed_retryable` while an open attempt
   carries the in-progress fact, but that newer generation removes it from
   the claimable set; a terminal-but-unincorporated attempt (of any outcome,
   including `abandoned`) remains equally excluded and is routed to
   finalisation instead of a new attempt; a fully incorporated
   `failed_retryable` item below ceiling remains claimable; terminal/
   excluded/waiting items are excluded as before; no attempt is ever
   bypassed; the partial unique index remains a structural backstop rather
   than the complete race solution; and the reclaimer's own `abandoned`
   transition is subject to the identical incorporation gate before a new
   generation can open.

4. **How the crash window is closed**: the attempt's own terminal commit
   and the item's incorporation of it are still two potentially separate
   transactions (unchanged from pass six, for the reasons already
   established), but the claim query itself now durably detects **every**
   newer generation via the marker and refuses to open another attempt
   until the prior generation is terminal and incorporated. An open
   generation waits or is reclaimed after lease expiry; a terminal one is
   routed to finalisation. This remains true if an open-attempt unique-index
   conflict clears concurrently, so a finaliser/claimer race fails closed
   instead of admitting generation N+1. The crash-recovery table's relevant
   rows are rewritten accordingly.

5. **`INSERT` privilege and guard behaviour**: table-level `INSERT` is
   revoked from `rag_platform_app` and re-granted on every
   `bulk_operation_items` column except `target_reference_status`, which
   already declares `DEFAULT 'live'`; any `INSERT` naming that column
   explicitly fails at the privilege-check stage regardless of the value
   supplied, raw SQL or ORM mass-assignment alike. The existing guard
   trigger is extended from `BEFORE UPDATE` to `BEFORE INSERT OR UPDATE`,
   rejecting a non-`live` inserted row unless `current_user =
   'rag_platform_owner'` — preserving a genuine owner-executed migration/
   backfill path while closing every runtime path.

6. **Existing/future function privilege behaviour**: the runtime grant
   baseline now explicitly revokes PostgreSQL's automatic `EXECUTE ...
   TO PUBLIC` from every existing function in every allowlisted schema,
   grants `EXECUTE` back to `rag_platform_app` only on the specific,
   audited functions it must call, and installs `ALTER DEFAULT PRIVILEGES
   FOR ROLE rag_platform_owner IN SCHEMA <schema> REVOKE EXECUTE ON
   FUNCTIONS FROM PUBLIC` for every allowlisted schema, alongside the
   existing table/sequence default-privilege rules the prior pass's
   sweep had left function coverage out of. Trigger-only and
   `SECURITY DEFINER` functions never receive a runtime `EXECUTE` grant,
   existing or future, since PostgreSQL's trigger mechanism never
   requires one.

7. **Complete treatment of `started_at` and `result_identity`**:
   `started_at` is null exactly while `execution_status ∈ {excluded,
   eligible, cancelled}` (never attempted) and non-null exactly while
   `execution_status ∈ {failed_retryable, waiting_on_subordinate,
   succeeded, failed_permanent}` (attempted at least once) — tied
   one-to-one to `incorporated_attempt_generation`'s own nullity, except
   for `skipped`, which explicitly permits either shape (a
   pre-attempt `target_no_longer_exists` skip has both null; a
   post-attempt `not_applied` skip has both non-null). `result_identity`
   is forced null by the primary truth table for every state except
   `succeeded`; on `succeeded`, a second, operation-type-aware `CHECK`
   requires it for `bulk_promotion`/`bulk_applicability_change` (a new
   entity was created) and forbids it for the other five V1 operations
   (nothing new to reference) — genuinely two same-row constraints, no
   cross-table `CHECK`.

8. **Other state-bearing columns found during the sweep**: none newly
   discovered needing constraint. `id`/`bulk_operation_id`/`workspace_id`/
   `operation_type`/`ordinal` are immutable membership facts, governed by
   "Immutable membership," not execution state. The target-shape columns
   are governed by the separate, already-complete target-shape `CHECK`,
   deliberately keyed on `target_reference_status` rather than
   `execution_status`. `expected_state_snapshot` is captured once at
   freeze time for every row and does not vary by execution outcome, so
   it is confirmed as not state-bearing in the sense this truth table
   governs, and is deliberately left outside it.

9. **Tests added**: full detail in "Testing" above — incorporation-marker
   crash/race/regression/cross-item/retry-ordering/stale-worker scenarios;
   ordinary-default, explicit-rejection, mass-assignment, and
   owner-migration `INSERT` scenarios; existing/new/trigger/definer
   function privilege inspection; and rejection tests for every named
   `started_at`/`result_identity` contradiction across every truth-table
   branch.

10. **Confirmation that no settled decision changed**: the seven-operation
    V1 allowlist, frozen membership, partial execution, approval without
    four-eyes, the promotion/approval separation, Laravel-exclusive
    orchestration ownership, applicability-only successors, subordinate
    workflow ownership (Laravel decides, Python reports only bounded
    technical results), the deferral of bulk deletion/withdrawal/
    rescheduling, target retirement's historical-record design, permanent
    actor provenance, the required visual checkpoints, and every
    ADR-0030–0034 decision are all unchanged — this pass added
    constraints and closed gaps around the existing model; it did not
    redesign any accepted behaviour.

11. **Remaining contradiction or blocker**: none identified in this pass.

12. **Ready for one final Codex read-only freeze audit**: yes — all four
    blockers from this pass's brief have closed, database-enforceable
    answers, consistent with, and layered on top of, every
    previously-accepted design from passes one through six.

13. **New SHA-256**: computed and reported alongside this message from
    the file's current on-disk state.

14. **Exact files touched**:
    `docs/adr/0035-define-frozen-bulk-document-operations.md` only.
    `Status: Proposed` unchanged throughout. Nothing else modified;
    nothing committed, tagged, or pushed; the ADR was not accepted; no
    provider was called.
