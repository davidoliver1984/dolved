# ADR 0036: Define Document Governance Notifications and Reminders

## Status

Accepted

## Date

2026-08-29

## Relationship to prior ADRs

### Consumes ADR-0030–ADR-0035 as frozen (Proposed, independently
### implementation-readiness-audited) without reopening any of them

This ADR consumes the **event-producing lifecycles** these six ADRs
already define — document-family/version metadata and its owner/category/
tag/review-date fields (ADR-0030); version-governance orchestration,
applicability-only successors, the `DocumentContentCloneOperation`/
full-ingestion-fallback subordinate state machine, and family deletion
(ADR-0031); structured extraction and source delivery (ADR-0032, no
direct event surface of its own); import staging and the `PromotionAttempt`
state machine (ADR-0034 — the actual source authority for every
promotion outcome this ADR notifies about); the frozen bulk-operation
model, including its exhaustive parent terminal-mapping function and its
own normative mapping tables that **consume, but do not own**, ADR-0034's
`PromotionAttempt` states and ADR-0031's clone/fallback states (ADR-0035);
and ADR-0033's knowledge-library navigation and route hierarchy, into
which this ADR's own surfaces are placed. **It introduces no new
governance state, no new import/promotion rule, no new bulk-operation
outcome, and no new deletion behaviour** — every domain fact this ADR
notifies about is a fact one of those six ADRs already decided how to
reach; this ADR's own contribution is entirely about *who finds out, how,
and through what durable record*.

**Correcting one assumption the brief's own event list implies**: ADR-0031
defines no "reject a draft version" action distinct from `WITHDRAWN` (which
applies only to a version that has already attained authority, per
ADR-0017) — there is no "decline this still-`DRAFT` version" verb in the
frozen vocabulary. "Approval completed or rejected," read against the
actual accepted lifecycle, resolves to: `ApproveDocumentVersion`'s own
success (a real, existing transition), and — for the promotion path
specifically — `PromotionAttempt` reaching `CONFLICT` or an exhausted
`FAILED` (ADR-0034's own terminal outcomes, already mapped by ADR-0035's
own normative mapping table), which is the closest genuine analogue to
"rejected" this decomposition actually has. This ADR does not invent a
new rejection verb to fill the gap.

### Consumes ADR-0017's temporal-authority model exactly as accepted —
### including one fact this ADR's own brief did not anticipate

ADR-0017 computes `authority_start = max(effective_from, approved_at)`
and a version's authority window **live, at query time, from stored
timestamps — "requiring no scheduled job."** There is, today, no daemon
that "activates" a version at its `effective_from` moment; nothing fires
at that instant. **This means "scheduled activation completed" cannot be
produced as a real-time-triggered event** — it can only be *detected*,
after the fact, by a periodic scan noticing that `now() >=
authority_start` for a version that was not yet authoritative at the
previous scan. This ADR's reminder scheduler (below) is the first thing
in this decomposition that performs such a scan, and is designed
explicitly around that constraint rather than assuming a job that does
not exist. ADR-0017's own "blocked" condition (a scheduled version whose
`effective_from` has passed but which a faster-approved successor has
already superseded, so it never attains authority at all) is preserved
and surfaced honestly, not silently dropped.

### Consumes ADR-0025's content-free historical-activity pattern as the
### direct precedent for this ADR's own notification/domain-event
### correlation design

ADR-0025's "Content-free historical activity, independent of conversation
deletion" section already solved, for a different subsystem, the exact
problem this ADR faces: a durable record that must outlive the domain
object it describes, without a live foreign key that would either block
deletion or silently corrupt on cascade. Its answer — atomic same-
transaction writes, a plain unique index (not an FK) for idempotency, and
immutable **scalar** public identifiers rather than live relational
joins — is reused directly for this ADR's own domain-event and
notification correlation design, not reinvented. ADR-0025's `Document
DeletionOperation` "visibly stuck" administrative read model is likewise
reused directly for surfacing a stuck/failed deletion as a notification,
rather than this ADR inventing a second stuck-operation mechanism.

### Extends, but does not redefine, ADR-0008's transactional-outbox
### pattern — exercising the exact future decision ADR-0008 reserved

ADR-0008's own "Scope boundaries" section states explicitly: *"a generic,
platform-wide event bus for arbitrary future domain events... [is not
defined by this ADR]... Any broader adoption of the outbox pattern for
other future events should be a deliberate future decision, not an
assumption extending from this ADR."* **This is that deliberate decision.**
Verified directly against the running implementation: the existing
`outbox_events` table and `PublishIngestionOutbox` action are narrowly
scoped to publishing `document.ingestion.requested`/
`document.deletion.requested` to an external SQS queue for Python's own
consumption (`apps/api/app/Actions/Ingestion/PublishIngestionOutbox.php`
validates only those two event types and calls `IngestionEventPublisher`,
an SQS-backed contract) — it is not, and was never claimed to be, a
general-purpose intra-Laravel event mechanism. This ADR introduces a
**new**, separate, Laravel-internal-only outbox table (below), following
the same transactional discipline (atomic commit, claim-based polling,
bounded retry, terminal-failure visibility) ADR-0008 already established,
without touching, extending, or repurposing the existing ingestion
outbox.

### Consumes ADR-0027's shell, adding a surface it did not already
### reserve

Verified directly: ADR-0027's "One adaptive application shell" section
enumerates the stable-primary and contextual navigation regions in full
and explicitly lists what the reference mock-up shows but the shell does
**not** yet implement (search, bookmarks, reactions, a standalone
conversation-history icon distinct from the sidebar) — **no notification
bell, inbox, or unread-count affordance is mentioned or reserved
anywhere in ADR-0027.** This ADR is the first to introduce one. It is
placed within the shell's existing stable primary region (account-
controls area, alongside the theme toggle) rather than inventing a third
navigation region, and is designed to the same accessibility/keyboard/
theme baseline ADR-0027 already establishes, but the bell itself, its
inbox surface, and its unread-count behaviour are new product surface
this ADR defines from scratch, not a reserved seam being filled in.

### Restates, does not reopen, the Laravel/Python ownership boundary
### every prior ADR in this decomposition already fixes

Python performs bounded technical work and reports typed, already-
authenticated outcomes through the exact contracts ADR-0007/0009/0015/
0016/0032/0034 already define. Nothing about notifications, recipients,
wording, delivery, or preference changes that boundary in either
direction — restated fully in "Non-negotiable ownership boundary" below.

## Context

**Verified directly against the running repository, not assumed:**

- `apps/api/app/Notifications/WorkspaceInvitationNotification.php` is the
  **only** existing use of Laravel's `Notification` system in this
  codebase, and its `via()` returns `['mail']` only — **no `database`
  notification channel is used anywhere today.** Laravel's own built-in
  `notifications` table/`HasDatabaseNotifications` trait would need to be
  deliberately adopted or a purpose-built table introduced; neither
  exists yet. This ADR introduces a purpose-built table (below) rather
  than adopting Laravel's generic polymorphic `notifiable_type`/
  `notifiable_id` default schema, for the same reason ADR-0025 rejected
  live polymorphic references elsewhere in this decomposition: a bounded,
  typed schema with explicit tenant/workspace scoping is preferred over a
  generic, untyped one.
- `apps/api/database/migrations/2026_07_28_000004_create_outbox_events_table.php`
  and `App\Models\OutboxEvent` back the **existing, narrowly-scoped**
  ingestion/deletion outbox described above — confirmed not reusable as a
  generic mechanism, per ADR-0008's own disclaimer.
- `apps/api/routes/console.php` already establishes this codebase's
  scheduler pattern: `Schedule::command(...)->everyMinute()->
  withoutOverlapping()` (three existing examples:
  `conversation:reconcile-stale-runs`, `workspace-invitations:expire`,
  `observability:record-operational-snapshot`, all per-minute). **No
  daily-cadence scheduled command exists anywhere in this codebase
  today** — this ADR's reminder/authority-transition scanner is the first
  to need one. The mechanism (`Schedule::command()->dailyAt(...)`) is a
  standard, already-available Laravel facility; only the specific command
  is new.
- `App\Enums\WorkspaceRole` is exactly `{Owner, Admin, Member}` — no
  fourth role exists. `User.disabled_at` is an existing nullable
  timestamp column, already the accepted signal for "this account cannot
  act," reused directly by this ADR's recipient-eligibility rules,
  exactly as ADR-0030/0031/0034/0035 already reuse it for owner/approver
  eligibility.
- ADR-0017's `authority_start = max(effective_from, approved_at)` is
  computed live, with **no scheduled activation job today** (see
  "Relationship to prior ADRs" above) — a fact this ADR's reminder design
  must, and does, account for rather than assume away.
- No notification-preference table, workspace-notification-settings
  table, or digest-scheduling mechanism exists anywhere in the repository
  today. Every such facility this ADR requires is reported as new work,
  below, not claimed as already present.
- `apps/api/config/workspace_administration.php` establishes this
  codebase's existing pattern for simple, environment-driven, per-
  workspace-administrable configuration (`env()`-backed array config,
  no database-backed settings table) — this ADR's own workspace email
  defaults follow the same shape, extended with an actual database-backed
  per-workspace override where the settled decision requires
  administrators to configure it from the product itself (an `env()`
  value alone cannot be changed from the browser).

**This is the seventh and final ADR completing the Import, Staging and
Bulk Governance / Document Governance phase's Tier-2 decomposition**,
consuming every event-producing lifecycle the prior six already fixed.

## Decision

### Non-negotiable ownership boundary, restated and held throughout

**Notification and reminder orchestration is entirely Laravel-owned.**
Python never resolves a recipient, decides whether an event is important,
renders browser or email wording, chooses a template, inspects a
preference, schedules a reminder, or sends an email. Python's only
contribution is the **typed, already-authenticated technical outcomes**
it already reports through ADR-0007/0009/0015/0016/0032/0034's existing
contracts (an ingestion `complete`/`fail`/`cancel` callback, an extraction
result, a clone-completeness report) — this ADR reads those existing
outcomes to decide whether a domain event has occurred; it does not ask
Python a new question, call a new Python endpoint, or grant Python any
new authority. **No new Python endpoint or cross-language message is
introduced by this ADR** — every technical outcome this ADR's event
vocabulary depends on is already reported through an existing, accepted
contract; no missing technical-outcome contract was discovered during
this drafting.

### Six distinct record kinds, never conflated

This ADR is, at its core, about keeping six related-but-distinct
projections honestly separate, because collapsing any two of them is
exactly how a platform ends up either spamming users or losing an
auditable trail:

1. **A durable domain event** — a fact that something happened in
   ADR-0030–0035's own domain (a batch finished, a version was approved,
   a bulk operation converged). Recorded once, append-only, in this
   ADR's own new outbox table (below).
2. **An in-product notification** — a durable, per-recipient, read/
   dismissible record that a specific user should know about a specific
   event. Zero, one, or several notifications may be produced from one
   domain event (a batch-completion event produces one notification per
   eligible recipient, never one per document in the batch).
3. **An actionable-work/dashboard projection** — a *count or list*,
   derived at read time from authoritative domain tables (never a
   notification row, never a mutable counter), of work that remains
   outstanding until the underlying domain state changes.
4. **An optional email-delivery attempt** — a secondary, retryable
   projection of an in-product notification, gated by category/
   preference, never the authoritative record of anything.
5. **An audit record** — ADR-0030/0031/0034/0035's own existing audit
   tables, unmodified; this ADR never introduces a second audit
   mechanism, only correlates its own notifications back to them.
6. **Operational telemetry** — bounded-cardinality counters/histograms
   under ADR-0012/0026's existing allowlist discipline, describing this
   ADR's own production/delivery pipeline health, never describing tenant
   content.

### 1. Event vocabulary — a bounded, closed V1 set

**Withdrawn as a design principle before it was ever adopted: "one event
per state transition."** **Withdrawn, found during this pass: the prior
table listed 21 rows while the report claimed 20, because
`import.approval.awaiting` was included as a table row despite its own
description reading "not an event at all — a derived count."** A
pseudo-event that cannot genuinely be persisted in `DocumentGovernance
Event` (below) has no place in a table describing events at all, however
clearly its own row disclaimed itself — the corrected table removes it
entirely; "awaiting approval" is defined **exclusively** in "Notifications
versus actionable work" and "Dashboard and navigation projections" below,
never as an entry in the event vocabulary.

**The V1 event vocabulary is exactly twenty (20) entries — every one
below is a real, persistable fact, capable of being written as one row in
`DocumentGovernanceEvent`.** Each is evaluated individually against
whether it warrants an individual notification, a work-queue projection,
email eligibility, audit-only treatment, or telemetry-only treatment —
never defaulted to "notify," and several genuine candidate transitions
are deliberately **excluded** from producing any user-facing signal at
all (noted in "Deliberately excluded," below).

| Event key | Meaning | Individual notification? | Work-queue/dashboard? | Email-eligible? |
|---|---|---|---|---|
| `import.batch.completed` | Every item in a batch finished processing with no exception | Yes — uploader only | Recent-imports feed | Yes |
| `import.batch.completed_with_exceptions` | At least one item failed, needs action, or is ambiguous | Yes — uploader; and owners/admins with live approval capability if any item now awaits approval | Failed/warning-imports card | Yes |
| `import.item.processing_failed` | A single file failed processing | No individual notification — folded into the batch-level event above | Drill-down detail on the failed/warning-imports card | No (covered by the batch email) |
| `import.item.requires_user_action` | Corrupt, protected, unsupported, or oversized — the uploader must act | No individual notification — folded into the batch-level event | Work-queue item, visible to the uploader, until resolved or the batch expires | No |
| `import.item.match_ambiguous` | A family/version match needs human review | No individual notification | Work-queue item for owners/admins with approval capability | No |
| `governance.version.approved` | `ApproveDocumentVersion` committed (ADR-0031's own action) | Yes — the original importing/uploading actor only, and only if they are not the approver | No | Yes |
| `promotion.completed` | A `PromotionAttempt` reached `COMMITTED` — **ADR-0034's own state machine**; ADR-0035 only consumes it via its own normative mapping table, never owns it | Yes — the initiating actor | Recent-imports feed | Yes |
| `promotion.failed` | A `PromotionAttempt` reached `CONFLICT`/exhausted `FAILED`/`ABANDONED`/`EXPIRED` — **ADR-0034's own terminal outcomes**, exactly as ADR-0035's own normative mapping table already classifies them for bulk consumption; this ADR reuses that same classification, attributed to its actual source | Yes — the initiating actor | Failed/warning-imports card | Yes |
| `governance.authority.approaching` | A version's `effective_from` is inside the configured lead time, not yet authoritative | No individual notification (too routine) | Scheduled-changes card | No |
| `governance.authority.attained` | A daily scan detects `authority_start` has newly passed | No individual notification (expected, routine outcome of a decision already made) | Scheduled-changes / recent-activity feed | No |
| `governance.authority.blocked` | A version's schedule will never take effect — a successor already attained authority first (ADR-0017's own "blocked" condition) | Yes — document owner and workspace owners/admins | Scheduled-changes card, persistent until acknowledged | Yes |
| `governance.review.due_soon` | Review-due date inside the configured lead time | Yes — document owner and workspace owners/admins | Review-due card | Yes (grouped, see "Email policy") |
| `governance.review.overdue` | Review-due date has passed | Yes, once, then only a dashboard presence (no daily repeat) | Overdue-reviews card, persistent until resolved | Yes (grouped) |
| `governance.ownership.reassignment_required` | A family's recorded owner lost eligibility (disabled, removed, or demoted below the required capability) | Yes — workspace owners/admins | Work-queue item, persistent until reassigned | Yes |
| `applicability.successor.completed` | A `DocumentContentCloneOperation`/fallback reached its own success terminal state — **ADR-0031's own state machine**; ADR-0035 only consumes it via its own normative mapping table, never owns it | Yes — the initiating actor | Recent-activity feed | Yes |
| `applicability.successor.failed` | The clone/fallback subordinate reached `failed_permanent` — **ADR-0031's own terminal outcome**, exactly as ADR-0035's own normative mapping table already classifies it for bulk consumption; this ADR reuses that same classification, attributed to its actual source | Yes — the initiating actor | Failed/warning-imports card | Yes |
| `bulk_operation.completed` | Terminal `completed` — **ADR-0035's own terminal-mapping function**, the one event family in this table ADR-0035 genuinely owns rather than merely consumes | Yes — the initiating actor only | Recent bulk-operation outcomes | Yes |
| `bulk_operation.completed_with_exceptions` | Terminal `completed_with_exceptions` (ADR-0035's own terminal-mapping function) | Yes — the initiating actor, detail page explains exceptions per the settled decision | Recent bulk-operation outcomes | Yes |
| `bulk_operation.failed_before_execution` | The freeze itself could not complete (ADR-0035's own terminal-mapping function) | Yes — the initiating actor | Recent bulk-operation outcomes | Yes |
| `deletion.operation.stuck_or_failed` | ADR-0025's own `DocumentDeletionOperation` "visibly stuck" read model reports a stuck/failed deletion | Yes — the initiating actor and workspace owners/admins | ADR-0025's existing admin surface, cross-linked | Yes |

**"Awaiting approval" is never a row in this table.** It is a derived
count/list — see "Notifications versus actionable work" immediately
below — computed live from `Document`/`ImportItem` state, never written
to `DocumentGovernanceEvent`, never assigned an `event_key`, and never
capable of being "replayed" or "idempotency-checked" the way a real event
is, because it is not a fact that occurred at a point in time; it is a
question answerable at any time by querying current state.

**Deliberately excluded from any user-facing signal, and why**:
`completed_with_exclusions` (bulk operations, and `import.batch`'s
all-excluded case) — preflight exclusion is honest, expected information
already fully visible in the confirmation/result screen the initiating
actor already saw; a *second*, asynchronous notification for something
the actor already reviewed before confirming would be noise, not
information. Every intermediate `PromotionAttempt`/clone state
(`RESERVED`/`COPYING`/`SOURCE_VERIFIED`/`AUTHORISED`/`VERIFYING`/
`CLEANUP_REQUIRED`/`FALLBACK_READY`) — these are in-progress facts the
work-queue's own "still processing" projection already shows via a
live count, not discrete events; notifying on each would mean several
notifications per document for what the user experiences as one
continuous wait. Ordinary tag/category/owner/review-date metadata edits
(ADR-0030's own actions) — routine, low-consequence, self-attributed
edits a user just performed themselves.

**ADR-0037 (export) may extend this vocabulary later; this ADR does not
pre-design export-specific events, and the event-key namespace
(`domain.subject.outcome`) is chosen specifically so a future
`export.*` family slots in without renaming anything here.**

### 2. Notifications versus actionable work — the boundary, stated once

**A notification says something happened, once, and its own historical
truth never changes.** An **actionable-work item is a live fact about
current domain state**, computed fresh every time it is displayed, and
disappears the moment the underlying condition resolves — it has no
independent row of its own to become stale.

- **"Your upload finished" is a notification**: a fact about a moment in
  time (the batch completed), correctly historical forever, whether or
  not anything about the resulting documents changes afterward.
- **"12 documents require approval" is actionable work**: `SELECT COUNT(*)
  FROM documents WHERE workspace_id = :workspace AND technical_status =
  'INDEXED' AND governance_status = 'DRAFT'` (ADR-0017/0031's own
  authoritative columns), computed at read time, **never** a stored
  counter incremented on `import.batch.completed` and decremented on
  `governance.version.approved` — exactly the "two fields that can
  disagree" hazard this decomposition has already rejected repeatedly
  (ADR-0007, ADR-0017, ADR-0034, ADR-0035). If the count is wrong for a
  moment because a query is stale, the very next read self-corrects; a
  drifted counter never does.
- **"Review due in 14 days" is both**: a `governance.review.due_soon`
  notification is recorded once (a historical fact: "we told you this on
  this date"), and the document **simultaneously** appears in the
  review-due dashboard card for as long as its due date remains inside
  the lead time — computed live from `DocumentFamily.review_due_date`
  (ADR-0030), not from the notification's own existence. Dismissing the
  *notification* never removes the document from the *dashboard card*;
  those are two different projections of the same underlying fact,
  deliberately.
- **"Bulk operation completed with three exclusions" is one notification**
  whose detail page — the existing `/documents/bulk/{bulkOperationPublicId}`
  route ADR-0035 already defines — explains every exception with full
  drill-down; this ADR adds no second, item-level notification per
  excluded/failed item, per the settled decision.

**Batch-level, not per-document, is the default shape for every
multi-item event.** `import.batch.completed_with_exceptions` and every
`bulk_operation.*` event are recorded and notified exactly once per
batch/operation, correlated to the batch/operation's own public identity,
with full item-level detail reachable only by navigating into the
existing detail route — never by generating N notification rows for N
documents.

### 3. Recipient resolution — hybrid, stated precisely to avoid the race

**Decision: a hybrid model, chosen specifically to avoid the race the
brief names** (authority changing between event creation and delivery):

- **The notification *row itself* snapshots the recipient's identity at
  the moment the projector creates it** — `user_id` is a real, if
  eventually-`nullOnDelete()`, foreign key (below), because the
  notification is a historical fact about a specific past decision to
  inform a specific person, and that fact should remain intelligible even
  if that person is later disabled or removed.
- **Actionable-work/dashboard projections are never snapshotted** — they
  are recomputed against **current** Laravel authority (current
  `WorkspaceRole`, current `disabled_at`, current membership) on every
  read, per "Notifications versus actionable work" above. This is what
  satisfies "losing authority must remove work from the user's actionable
  queue even if an older informational notification remains in history":
  the notification row still exists and is still readable; the
  *actionable* dashboard card simply stops counting that user's own
  pending items the moment their authority is re-resolved as absent,
  because it was never counting from the notification table to begin
  with.
- **Recipient resolution itself always runs at projector-processing
  time, against current authority, workspace-scoped** — never against
  authority captured when the *domain event* occurred, since a projector
  can run moments or (in a reconciliation/replay scenario) much later
  than the event itself; resolving late, against current truth, is
  strictly more correct than resolving early, against a snapshot that
  could already be stale by the time delivery happens.

**Deterministic rules, per event family:**

- **Uploader/importer-addressed events** (`import.batch.*`,
  `promotion.*`, `applicability.successor.*`, `bulk_operation.*`): the
  single initiating actor recorded on the originating `ImportBatch`/
  `BulkOperation`/etc. — resolved by that record's own permanent
  initiating-actor provenance (ADR-0034/0035's own `actor_type`/
  `actor_user_id`/`system_actor_code` columns), never re-derived from
  "whoever is currently logged in."
- **Owner-addressed events** (`governance.review.*`,
  `governance.ownership.reassignment_required`): `DocumentFamily.owner_
  user_id` (ADR-0030), resolved live — if the recorded owner has lost
  eligibility (disabled, removed, or no longer an active member), they
  are excluded from delivery for *this* event and
  `governance.ownership.reassignment_required` is raised instead,
  addressed to workspace owners/admins.
- **Approval-capability-addressed events** (`import.batch.
  completed_with_exceptions` when items now await approval,
  `governance.authority.blocked`, `deletion.operation.stuck_or_failed`):
  every currently active `WorkspaceRole::Owner`/`Admin` in the workspace
  — resolved fresh, per delivery, from current membership; a user who
  holds both `Owner` and is also the document's own recorded owner is
  still exactly **one** recipient (deduplication below).
- **Deduplication**: recipient resolution produces a **set** of user
  IDs per event, keyed by `(event, user_id)` — a user occupying several
  qualifying roles for the same event (e.g. the workspace's sole Owner,
  who is also the document's own recorded owner) is deduplicated to
  exactly one notification row, never one per matching role.
- **Cross-workspace impossibility, structurally**: every recipient query
  is scoped by `WHERE workspace_id = :event_workspace_id` as an inherent
  part of the query, never a filter applied after an unscoped candidate
  list — the same discipline ADR-0035's own freeze query already
  establishes for target selection.
- **Disabled/removed/authority-lost users never receive new delivery**:
  checked at the moment the projector resolves recipients, not at event-
  creation time — a user disabled one second before the projector runs
  receives nothing, even if the domain event that triggered it occurred
  while they were still active.

### 4. In-product notification model

**`DocumentGovernanceNotification`** — a new, purpose-built, tenant-
scoped table, not Laravel's generic polymorphic default:

| Column | Constraint |
|---|---|
| `id`, `public_id` | Internal/public identity |
| `workspace_id` | `restrictOnDelete()` — a workspace is never deleted the way its content is |
| `recipient_user_id` | Nullable, `nullOnDelete()` — a **live, current-query convenience** FK, used only for "show me my own notifications right now" queries while the user account still exists. **Withdrawn: relying on this column, once nullable, as the recipient half of this table's own uniqueness boundary** — Codex correctly identified that once a hard-deleted user's row is nulled, every historical notification addressed to that now-deleted user would carry the identical `NULL`, and PostgreSQL's own "`NULL` never equals `NULL`" rule would make the uniqueness constraint below stop meaningfully enforcing anything for those rows at all (a second, duplicate notification for the same already-departed recipient would no longer collide with anything). See `recipient_user_public_id`, immediately below, for the corrected authority. |
| `recipient_user_public_id` | **Non-nullable, immutable, captured once at projection time — the durable scalar identity this table's own uniqueness and historical intelligibility actually depend on.** Never a live FK (so a hard-deleted user can never null it, unlike `recipient_user_id` above); carries no email address and no personal display name, satisfying "no email address or personal display name needs to be retained for this purpose" — the user's own already-existing public identifier is sufficient, exactly the same non-nullable-scalar discipline ADR-0025/0030's own owner/actor-identity corrections already established for an analogous nullable-column deduplication gap. |
| `event_key` | Non-nullable, the closed twenty-entry vocabulary above, e.g. `import.batch.completed_with_exceptions` |
| `source_event_id` | Non-nullable — the originating `DocumentGovernanceEvent`'s own immutable public `event_id` (below), **not** `event_key` plus a separately-reconstructed correlation value. This is the corrected idempotency authority (see "Reliable production and delivery" below for why `correlation_id` alone was insufficient) — one already-unique, already-immutable scalar, never rebuilt from mutable or nullable fields. |
| `template_key`, `template_version` | Non-nullable — the exact, versioned wording template this notification was rendered from; changing a template's copy in a later release never rewrites historical notifications' own rendered meaning, since old rows keep citing the version they were actually generated under |
| `parameters` | JSON, a **closed, allowlisted set of safe scalar values only** (a batch's item count, a document's family title already safe per ADR-0030's own bounded/escaped `target_display_label` precedent, a due date) — never raw prose, never unvalidated user input, never document content |
| `severity` | Bounded enum: `info`, `action_required`, `warning` — drives visual treatment, never delivery channel selection on its own |
| `target_kind` | Nullable, bounded enum naming what kind of thing this notification is about (`family`, `document`, `import_batch`, `bulk_operation`, etc.) — see "Reconciling stored target routes with live authorization" below; paired with `target_public_id`, never a live FK |
| `target_public_id` | Nullable — the target's own immutable public identity, a plain scalar, never a live join |
| `target_display_label` | Nullable, bounded/escaped exactly per ADR-0035's own `target_display_label` precedent — what the notification shows if its target is later deleted or no longer authorised for this recipient |
| `created_at`, `read_at`, `dismissed_at` | `read_at`/`dismissed_at` nullable, independent of each other (below) |
| `expires_at` | Non-nullable, set at creation from the settled per-severity retention policy — 90 days for `info`, 365 days for `warning`/`action_required` (below, "Retention and deletion") |
| `recipient_workspace_membership_id` | Nullable, **single-column** FK to `workspace_memberships (id)`, `nullOnDelete()` — the specific active membership that justified this notification's delivery, structurally bound to `workspace_id`/`recipient_user_id` by a trigger (below), never a composite FK — see "Structurally enforcing recipient membership tenancy" immediately below |

#### Structurally enforcing recipient membership tenancy

**Withdrawn: a workspace-scoped recipient query as the *only* guard
against a malformed notification row.** Codex correctly identified that
this is a guard on the *write path's own logic*, not a database-enforced
invariant on the row itself — nothing previously prevented a
notification row from existing with a `workspace_id`/`recipient_user_id`
pair that could not actually arise from any legitimate membership.
**Corrected:**

- **`recipient_workspace_membership_id` is deliberately single-column**,
  not composite with `workspace_id` — for the identical reason ADR-0035's
  own target FKs are single-column: a composite `(recipient_workspace_
  membership_id, workspace_id)` FK's `ON DELETE SET NULL` would null
  **both** columns together the moment the membership row was deleted,
  destroying `workspace_id` on a row that must remain workspace-scoped
  forever. Single-column `nullOnDelete()` nulls only the membership
  reference itself; `workspace_id` and `recipient_user_public_id` are
  never touched by a membership's own deletion.
- **A new `BEFORE INSERT OR UPDATE` trigger,
  `enforce_document_governance_notification_recipient_membership()`**,
  follows the exact same pattern this decomposition already establishes
  for an analogous cross-table invariant (ADR-0017's `enforce_document_
  lineage()`; ADR-0035's `enforce_bulk_operation_item_target_workspace()`).
  Whenever `recipient_workspace_membership_id` is non-null, it looks up
  that membership row's own `workspace_id` and `user_id` and raises an
  exception unless both equal `NEW.workspace_id` and `NEW.recipient_
  user_id` respectively — structurally, the runtime **cannot** pair a
  notification's workspace with a membership belonging to a different
  workspace, and **cannot** pair it with a membership belonging to a
  different user. Setting the column to `NULL` (the membership's own
  `ON DELETE SET NULL` path) never enters this trigger's violation
  branch, since a null reference trivially has nothing to compare.
- **The same trigger additionally rejects any `UPDATE` that changes
  `recipient_user_public_id` from its original value** — the immutable
  recipient identity can never be rewritten after creation, closing the
  third required rejection case directly, without a second trigger.
- **Membership deletion never deletes the notification, and never nulls
  `workspace_id`** — only `recipient_workspace_membership_id` itself is
  nulled, exactly as the single-column design above guarantees;
  `recipient_user_public_id` remains the row's own permanent history/
  idempotency authority, entirely unaffected, per "In-product
  notification model" above.
- **This is the fourth place this ADR's own migration requires explicit,
  raw PostgreSQL DDL** (`DB::unprepared`) rather than Laravel's schema-
  builder abstraction alone, following the identical precedent every
  other cross-table trigger in this decomposition already establishes.

**Corrected uniqueness — the immutable scalar, never the nullable live
FK**: `UNIQUE (workspace_id, recipient_user_public_id, source_event_id)`.
Because `source_event_id` already names one specific, already-unique
domain-event occurrence (per "Reliable production and delivery" below)
and `recipient_user_public_id` never becomes null regardless of what
later happens to the user's account, **a hard-deleted user's own historical
rows can never collide with, or fail to prevent a duplicate for, another
row** — the exact failure mode a nullable-FK-based uniqueness boundary
would have permitted. `event_key` is intentionally **not** part of this
tuple: `source_event_id` already identifies one specific event row, whose
own `event_key` is fixed and immutable, so including it again would be
redundant, never load-bearing.

**Removed/deleted/newly-unauthorised targets, and the live nullable FK's
remaining role**: `recipient_user_id` remains useful, and is retained,
purely as a live-query convenience column (`WHERE recipient_user_id =
auth()->id()`) for as long as the account exists and is the authenticated
user — every durable guarantee this table makes (uniqueness, historical
intelligibility, cross-referencing after a hard deletion) rests on
`recipient_user_public_id` alone, never on this column.

**Read versus dismissed, kept independent**: `read_at` records passive
acknowledgement (the inbox was opened, or the item was scrolled past and
marked read) and affects only the unread count; `dismissed_at` records an
active "remove this from my inbox" action and affects list visibility.
**A `severity = 'action_required'` notification may be dismissed from the
*notification inbox*, but dismissal never resolves the underlying
actionable-work item** — the dashboard card (a different projection,
per "Notifications versus actionable work" above) keeps showing the work
until the actual domain condition resolves, regardless of the
notification's own dismissed state. A dismissed *informational*
notification remains fully readable in history (`dismissed_at` is not
`deleted_at`) — dismissal hides it from the default inbox view, never
deletes the row.

**Removed/deleted/newly-unauthorised targets shown without dead links**:
`target_route` is never stored (see "Reconciling stored target routes
with live authorization" immediately below) — the notification always
renders using `target_kind`/`target_public_id`/`target_display_label`,
resolving an *effective* route at render time; when no live, authorised
destination exists, the row renders as inert text, never a link to
nowhere and never a client-side guess at a URL.

#### Reconciling stored target routes with live authorization

**Withdrawn: storing a resolved `target_route` string on the notification
row, combined with two mutually inconsistent claims elsewhere in the
prior draft — that the stored route "becomes null when the target
disappears," while separately stating no deletion-time update ever
touches historical notifications, and separately again describing route
availability as "checked lazily during rendering."** These three
statements cannot all be true of the same stored column at once — a
value that is genuinely only ever checked lazily at render time is never
itself rewritten to null by any deletion-time process, so describing it
as "becoming null on deletion" was never accurate. **Corrected: one
precise model, replacing all three:**

- **The durable notification stores only a safe target kind and an
  immutable scalar target public identity/label** — `target_kind`/
  `target_public_id`/`target_display_label`, per the schema above. It
  never stores a route string of any kind, so there is nothing on the
  row that could ever "become" stale or require a write to correct.
- **Laravel resolves an *effective* `target_route` for the HTTP response,
  fresh, every time a notification is rendered** — never persisted,
  never cached across requests. Resolution re-runs the destination's own
  ordinary authorization check (ADR-0031/0033/0035's existing route-level
  checks, unmodified) against the **current** requester and the
  **current** state of the target.
- **The durable notification row itself is never rewritten when its
  target later disappears or becomes inaccessible** — there is no
  deletion-time cascade this ADR performs against historical
  notifications; the row's own scalar `target_kind`/`target_public_id`/
  `target_display_label` remain exactly as captured at creation,
  permanently, regardless of the target's later fate.
- **If the target no longer exists, or the current requester is no
  longer authorised for it, the rendered response simply contains no
  route** — the browser receives `target_route: null` (or an equivalent
  absent field) for that request, and renders the inert-label state; a
  *different*, still-authorised user reading the *same* historical
  notification a moment later, before or after the target's own
  authorization changed, always gets a response reflecting **their own**
  current authorization, independently.
- **The browser never constructs a route itself**, from this notification
  or any other — restated, unchanged from the original design.
- **A stored historical target identity is never treated as proof of
  current accessibility** — `target_public_id` existing on the row proves
  only that a target with that identity existed at notification-creation
  time; it is never presented to, or trusted by, any authorization check.
- **The `404`-not-`403` concealment rule applies identically on direct
  navigation** — a user who bypasses the notification UI entirely and
  navigates straight to a stale or now-foreign `target_public_id`'s own
  route hits that route's own existing, unmodified tenant-safe `404`
  behaviour (ADR-0006/0027), exactly as if they had typed the URL from
  memory; this ADR adds no new path to that destination and therefore no
  new way to bypass its existing concealment.

**Pagination, retention, unread counts**: cursor-paginated by
`(created_at, id)`; unread count is `COUNT(*) WHERE recipient_user_id = :user
AND read_at IS NULL AND dismissed_at IS NULL` — this one live query is
exactly the case `recipient_user_id`'s remaining convenience role above
is for, scoped to the authenticated user's own still-existing account,
computed at read time, not cached; `expires_at` enforces bounded
retention (below, "Retention and deletion") via the same daily scheduler
this ADR already introduces for reminders.

**Idempotent replay**: reprocessing the same domain event (a projector
retry, an explicit reconciliation pass) is a plain `INSERT ... ON
CONFLICT (workspace_id, recipient_user_public_id, source_event_id) DO
NOTHING` — the same conflict-tolerant creation pattern ADR-0034's own
`WorkspaceChecksumReservation` already establishes for this codebase,
reused rather than reinvented.

### 5. Reliable production and delivery

**A new, purpose-built, Laravel-internal-only outbox — `DocumentGovernance
Event` — distinct from, and never reusing, the existing ingestion outbox**
(per "Relationship to prior ADRs" above), following the identical
transactional discipline ADR-0008 already established:

| Column | Constraint |
|---|---|
| `id`, `event_id` (public, immutable identity) | |
| `workspace_id` | Scoping, `restrictOnDelete()` |
| `event_key` | The closed twenty-entry vocabulary above |
| `event_version` | Integer, for future payload evolution |
| `payload` | JSON — safe, structured, allowlisted scalars only, the same discipline as notification `parameters` above, never raw content |
| `correlation_id` | The originating domain operation's own scalar public identity (an `ImportBatch.public_id`, a `BulkOperation.public_id`, a `Document.public_id`) — **non-unique lineage for navigation/audit only**, per the correction below; never consulted for idempotency |
| `occurrence_key` | **Non-nullable, immutable, computed deterministically by the calling Action before this row is ever inserted — the corrected idempotency authority, replacing `correlation_id`.** See "Event-occurrence identity, corrected" below for its exact construction per event family. |
| `occurred_at`, `claimed_at`, `claim_token`, `published_at`, `failed_at`, `attempt_count`, `next_attempt_at`, `last_error` | The same claim/retry/terminal-failure shape `OutboxEvent` already establishes, structurally duplicated here (a separate table, not a shared one, since this outbox's consumer is an in-process Laravel projector, never SQS, and mixing the two would reintroduce exactly the cross-scope confusion this ADR's "Relationship to prior ADRs" section corrects) |

**`UNIQUE (workspace_id, occurrence_key)`** — the domain-event-projection
idempotency identity, distinct from (2)/(3) below.

#### Event-occurrence identity, corrected

**Withdrawn: `UNIQUE (workspace_id, event_key, correlation_id)` as the
event table's own idempotency authority.** Codex correctly identified
that `correlation_id` alone names only the *target* (a family, an
operation) — it cannot distinguish two **legitimately different**
occurrences concerning the same target and the same `event_key`: the same
document family can genuinely receive a `governance.review.due_soon`
event for one due date, then a **different**, equally legitimate
`due_soon` event after the review date is later changed, then eventually
an `overdue` event, then further reminders across future review cycles.
A `(event_key, correlation_id)` pair would collide across all of these,
silently suppressing every occurrence after the first.

**Corrected: `occurrence_key` is defined *before* the domain transition
inserts the event, and its construction is exactly one of two shapes,
depending on the event family:**

- **One-time operation outcomes** (`import.batch.completed`, `import.
  batch.completed_with_exceptions`, `governance.version.approved`,
  `promotion.completed`, `promotion.failed`, `applicability.successor.
  completed`, `applicability.successor.failed`, `bulk_operation.
  completed`, `bulk_operation.completed_with_exceptions`, `bulk_operation.
  failed_before_execution`): the originating operation's own **immutable
  terminal operation/outcome identity** — concretely, the operation's own
  public identity (`ImportBatch.public_id`, `BulkOperation.public_id`, a
  `PromotionAttempt.public_id`, etc.), which is sufficient because every
  one of these operations reaches its *specific* terminal outcome **at
  most once, ever**, per ADR-0031/0034/0035's own already-accepted state
  machines — there is no second "occurrence" of the same operation
  reaching the same terminal outcome to disambiguate from. Retrying the
  surrounding code path recomputes the identical operation identity, so a
  retry safely collides with, rather than duplicates, the original.
- **Recurring facts** (`governance.review.due_soon`, `governance.review.
  overdue`, `governance.authority.approaching`, `governance.authority.
  attained`, `governance.authority.blocked`): a deterministic composite of
  **the target's own immutable identity, the reminder/transition kind,
  and the authoritative domain date the occurrence is actually about**
  (the family's current `review_due_date`, the version's current
  `effective_from`/`authority_start`, or the specific blocking
  successor's own identity) — **never the date the scheduler happened to
  run**. This is what "Scheduled reminder and authority-transition
  identities, corrected" below specifies precisely per reminder kind, and
  is what allows a changed review date, a later reminder cycle, or a
  missed-and-caught-up scan to each produce their own genuinely distinct,
  legitimate occurrence, while a rerun of the *same* day's scan against
  the *same* unchanged date recomputes the identical `occurrence_key` and
  safely no-ops.
- **Sub-operation, item-level facts that ADR-0034 does not structurally
  guarantee happen only once** (`import.item.processing_failed`, `import.
  item.requires_user_action`, `import.item.match_ambiguous`) and
  **repeated-eligibility-change facts** (`governance.ownership.
  reassignment_required`) — **withdrawn: leaving these four without a
  defined identity at all.** Codex correctly identified that an
  `ImportItem` can genuinely undergo more than one preflight attempt or
  decision cycle, and a family's owner can genuinely lose eligibility more
  than once across its lifetime, so a bare `(item, event_key)` or
  `(family, event_key)` pair would silently collapse every later,
  legitimately distinct recurrence into the first. **Corrected — the
  complete occurrence-key matrix for all twenty events, so no event is
  left under a vague "etc." category:**

**Complete occurrence-key matrix — all twenty V1 events**:

| Event key | Occurrence-key construction |
|---|---|
| `import.batch.completed` | `ImportBatch.public_id` |
| `import.batch.completed_with_exceptions` | `ImportBatch.public_id` |
| `import.item.processing_failed` | `(ImportItem.public_id, ImportPreflightAttempt.event_id)` — ADR-0034's own preflight-attempt identity; a genuinely new attempt (a new `event_id`) produces a distinct occurrence, while a replayed callback for the *same* attempt (the *same* `event_id`, ADR-0034's own existing replay/idempotency identity) collides with the original |
| `import.item.requires_user_action` | `(ImportItem.public_id, action_category, ImportPreflightAttempt.event_id \| null)` — `action_category` is the specific bounded reason (`password_protected`/`encrypted`/`corrupt_structure`/`mime_mismatch`, from ADR-0034's own bounded preflight-result vocabulary, or `oversized`, Laravel's own declared-size check made before any preflight attempt exists, in which case the attempt component is absent); a corrected decision cycle producing a *different* attempt naturally yields a distinct occurrence |
| `import.item.match_ambiguous` | `(ImportItem.public_id, ImportDecisionSnapshot.id)` — ADR-0034's own decision-snapshot identity; `current_decision_snapshot_id` reassignment (a corrected decision cycle) always produces a *new* snapshot row and therefore a distinct occurrence, while re-observing the *same* still-current snapshot collides with the original |
| `governance.version.approved` | `Document.public_id` (a version attains `APPROVED` at most once, per ADR-0017's one-directional governance model) |
| `promotion.completed` | `PromotionAttempt.public_id` |
| `promotion.failed` | `PromotionAttempt.public_id` |
| `governance.authority.approaching` | `(Document.public_id, effective_from)` |
| `governance.authority.attained` | `(Document.public_id, authority_start)` |
| `governance.authority.blocked` | `(Document.public_id, blocking_successor_document_id)` |
| `governance.review.due_soon` | `(DocumentFamily.public_id, review_due_date)` |
| `governance.review.overdue` | `(DocumentFamily.public_id, review_due_date)` |
| `governance.ownership.reassignment_required` | `(DocumentFamily.public_id, affected_owner_user_public_id, eligibility_loss_cause_identity)` — see "Ownership-loss producer and occurrence identity" below for `eligibility_loss_cause_identity`'s exact construction; **never** `(family, event_key)` alone, the current owner's own ID alone, or a scan date |
| `applicability.successor.completed` | The clone/fallback subordinate's own public identity (a `DocumentContentCloneOperation.public_id`, or the ingestion `event_id` for a direct fallback) |
| `applicability.successor.failed` | The same subordinate identity as above |
| `bulk_operation.completed` | `BulkOperation.public_id` |
| `bulk_operation.completed_with_exceptions` | `BulkOperation.public_id` |
| `bulk_operation.failed_before_execution` | `BulkOperation.public_id` |
| `deletion.operation.stuck_or_failed` | A durable stuck-episode identity — see "Deletion stuck/failed producer and occurrence identity" below |

**Retries/replays of the same occurrence always collide; a genuinely new
attempt, corrected decision cycle, or eligibility-loss cause never does**
— restated as the general rule the matrix above satisfies row by row:
every construction includes either a naturally-at-most-once operation
identity, or an explicit sub-identity (attempt `event_id`, decision-
snapshot `id`, membership-change cause) that changes exactly when, and
only when, the underlying fact genuinely recurs.

#### Ownership-loss producer and occurrence identity

**Withdrawn: an `eligibility_loss_cause_identity` built from a membership
removal timestamp and a membership role-change timestamp.** Verified
directly against the actual migrations
(`2026_07_28_000002_create_workspace_memberships_table.php`,
`2026_08_19_000002_add_workspace_membership_administration.php`):
`workspace_memberships` has **no** dedicated removal or role-change
timestamp column at all — only `id`, `public_id` (a `uuid`, unique — this
one *does* exist, verified), `workspace_id`, `user_id`, `role`,
`joined_at`, and ordinary `timestamps()`. A membership removal deletes
the row outright; a role change is an ordinary `UPDATE` to `role`. Codex
correctly identified that the prior design bound an occurrence key to
columns that do not exist. **Corrected — bound instead to the real,
verified, already-durable identities the actual administration Actions
already produce:**

- **`RecordWorkspaceAdministrationAudit::record()`**, called by the
  verified existing Actions `App\Actions\Workspaces\RemoveWorkspaceMember`
  (writing `action = 'member_removed'`, `target_type = 'membership'`,
  `target_public_id` = the membership's own `public_id`, **captured
  before** `$target->delete()` runs) and
  `App\Actions\Workspaces\ChangeWorkspaceMemberRole` (writing `action =
  'member_role_changed'`, the same `target_type`/`target_public_id`
  shape, `before`/`after` role values), inserts one row into
  `workspace_administration_audit_events`, whose own `event_id` (a
  `uuid`, unique) is generated fresh at write time — **verified this is
  exactly the kind of immutable, per-event identity this ADR needs, not
  invented.**
- **`OwnershipEligibilityReconciliation`** — a new, durable fan-out work
  item, with its own immutable public identity (`public_id`), created by
  extending each of the two Actions above (a required, named extension
  point for whichever implementation session wires this ADR up — not
  yet present in the running code, stated honestly) to additionally
  capture, in the **same** transaction as the audit-event write: the
  affected workspace's own identity; the affected user's own `public_id`
  (resolved from the membership row **before** any deletion); the
  membership's own `public_id` (also captured before deletion); and —
  the identity this work item exists to carry — the **audit event's own
  `event_id`** just written. **`eligibility_loss_cause_identity` is this
  audit event's own `event_id`, directly — not a digest over anything,
  since `event_id` is already a real, unique, immutable scalar.**
- **This closes the brief's own worked example honestly**: Alex loses
  eligibility once (`RemoveWorkspaceMember` writes audit event `E1`,
  `eligibility_loss_cause_identity = E1.event_id`) → reassigned → a later
  owner loses eligibility (a **new** `RemoveWorkspaceMember`/
  `ChangeWorkspaceMemberRole` invocation writes a **new** audit event
  `E2`, with its own distinct `event_id`, even if the affected person is
  Alex again after reappointment) → both occurrences are recordable,
  since `E1.event_id ≠ E2.event_id` by construction — no digest
  arithmetic is needed for this to hold; two distinct rows in an
  append-only audit table are, definitionally, two distinct identities.

**User disablement, spanning multiple workspaces — verified separately,
and found not yet to have a producer at all**: `grep`-verified directly
against `apps/api/app/Actions` — no existing Action currently *writes*
`User.disabled_at` (`ManagePlatformAdministrator` only grants/revokes the
*platform-administrator role*, and only *reads* `disabled_at` as an
eligibility check; it never sets it). **This ADR does not invent that
future disablement Action's full design** — it states the contract this
ADR requires of it, honestly, as a dependency on work not yet built:

- **Whichever future Action performs user disablement must, in the same
  transaction as setting `disabled_at`, create exactly one durable
  `UserDisablementReconciliationSource` row** — a new, minimal,
  ADR-0036-owned identity record (`id`, `public_id`, `user_id`,
  `disabled_at` copied for reference only, `created_at`) — **one durable
  source identity per disablement operation, never per affected
  workspace**, since a single disablement can affect many workspaces.
- **A separate, queued reconciler** discovers every workspace the
  affected user holds membership in, and — for each — produces
  workspace-scoped child reconciliation work deterministically (the same
  bounded, cursor-paginated batching as the membership-specific path
  below), with **every** resulting family occurrence's
  `eligibility_loss_cause_identity` bound to this **one**
  `UserDisablementReconciliationSource.public_id`, shared across every
  affected workspace/family from this single disablement — never
  `disabled_at`'s own timestamp value alone, which (per the withdrawn
  design above) carries no per-operation identity of its own and could
  not disambiguate two disablements that happened to be recorded at
  timestamps a naive comparison could conflate.

**Producer shape — bounded, transactional, never an unbounded
user-facing fan-out, for both cases above**:

- **The authoritative administration Action** (the two verified existing
  Actions for removal/demotion; the future disablement Action for
  disablement) **writes its own fan-out source identity in the same
  transaction as its own existing audit/state write** — never attempting
  to discover and lock every potentially-affected `DocumentFamily` inside
  that same user-facing request, which could make removing a member or
  disabling a user in a workspace with many owned families unsafely slow
  or lock-heavy.
- **A separate, queued, idempotent reconciler** (`ReconcileOwnership
  EligibilityAfterMembershipChange`) claims that work item and discovers
  affected owned families in **bounded, workspace-scoped batches**
  (`WHERE workspace_id = :workspace AND owner_user_id = :affected_user
  LIMIT :batch_size`, cursor-paginated by family `id`), emitting one
  `governance.ownership.reassignment_required` event per affected family
  per batch, each idempotent under the occurrence key above — a crash
  mid-fan-out simply resumes from the last-completed cursor position on
  retry, never re-emitting an already-produced occurrence (the
  `occurrence_key`'s own uniqueness makes a resumed/retried batch pass
  safe by construction) and never losing one either (the work item is not
  marked complete until every batch has been processed).
- **No Python involvement of any kind.**

#### Owner-change command and idempotency, corrected

**Withdrawn: describing owner reassignment as "ADR-0030's own existing
owner-reassignment action" and its idempotency identity as "the same
pattern ADR-0030/0031/0034 already establish."** Both claims are false,
verified directly against the actual repository: `ADR-0030` names no
owner-reassignment Action anywhere in its own text (it states only that
owner is "family-level editable metadata," mandatorily assigned at
creation), and no such Action, migration, or idempotency table exists in
`apps/api` today — `document_families` has no migration at all yet
(ADR-0030 itself remains `Proposed`, unimplemented), and a repository
search for any family/owner-reassignment Action returns nothing.
`ADR-0031` does establish a genuinely reusable governance-idempotency
*pattern* (`UNIQUE (workspace_id, purpose, idempotency_key)`, with
stored-but-excluded actor/target/digest, a stored terminal result,
matching-replay/conflicting-digest/independent-purpose rules) — but only
as a pattern for its own `DocumentVersion` governance routes (approve/
withdraw/reschedule/correct-timestamps); it names no table this ADR
could extend without reopening ADR-0031's own surface, and family-level
owner change is not one of the capabilities ADR-0031 lists. **Corrected:
this ADR defines its own, new, Laravel-owned command and idempotency
record — reusing ADR-0031's exact pattern (never inventing a second,
competing idempotency shape) — because no existing table or Action
genuinely covers this operation.**

**`DocumentGovernanceCommand`** (new table, this ADR's own) — one row per
attempted governance command, extensible to future family-governance
commands beyond owner change via `purpose`, though this ADR defines only
one:

| Column | Constraint |
|---|---|
| `id`, `public_id` | Internal/public identity |
| `workspace_id` | Scoping |
| `purpose` | Non-nullable — `'document_family.owner.change'` for every command this ADR defines |
| `client_idempotency_key` | Non-nullable, caller-supplied |
| `requested_by_user_id` | Nullable FK to `users`, `nullOnDelete()` — stored for audit, **excluded from the uniqueness constraint** |
| `target_document_family_id` | **Corrected this pass — see "Composite workspace binding" immediately below.** Part of a composite FK `(target_document_family_id, workspace_id)` to `document_families (id, workspace_id)`, never a bare single-column FK to `document_families (id)` alone; **excluded from the uniqueness constraint**; immutable once the command row is created (below); **nulled, and only this column, on family deletion** — see the column-targeted referential action below |
| `target_document_family_public_id` | **New this pass.** Non-nullable, immutable, captured once at `INSERT` time from the target family's own `public_id` — a content-free scalar lineage identity, never a live FK, following the same historical-correlation pattern ADR-0025 already establishes elsewhere in this decomposition. Survives `target_document_family_id` being nulled on family deletion, so a completed historical command remains intelligible (which family it targeted) even after that family no longer exists |
| `expected_current_owner_user_id` | **New this pass.** Nullable FK to `users` at the table level (kept nullable for a future `purpose` this table does not yet define); non-nullable whenever `purpose = 'document_family.owner.change'`, enforced by the `CHECK` below — the owner state the caller observed and is asserting still holds |
| `expected_current_generation` | **New this pass.** Nullable integer at the table level; non-nullable whenever `purpose = 'document_family.owner.change'` — the `owner_assignment_generation` the caller observed alongside the expected owner |
| `intended_new_owner_user_id` | **New this pass.** Nullable FK to `users` at the table level; non-nullable whenever `purpose = 'document_family.owner.change'` — the owner state this command is asking for |
| `request_digest` | Non-nullable, a canonical SHA-256 digest over the family's own identity and the intended new owner state, computed by the Action **from the same three columns immediately above** — immutable once written; used exclusively as the idempotency-conflict discriminant below, never independently re-derived inside the database (this repository computes every content digest in application code, never in SQL — introducing a database-side SHA-256 implementation here would be new, unjustified infrastructure; structural correctness is instead guaranteed because `request_digest` and the three plain columns above are written together, atomically, once, and never diverge afterward) |
| `result` | Nullable JSON — populated exactly once, atomically with command completion; holds the final owner identity and the resulting `owner_assignment_generation` |
| `completed_at` | Nullable — non-null if and only if `result` is non-null |
| `created_at` | Standard |

`CHECK (purpose <> 'document_family.owner.change' OR
(expected_current_owner_user_id IS NOT NULL AND
 expected_current_generation IS NOT NULL AND
 intended_new_owner_user_id IS NOT NULL))` — every command this ADR
actually defines carries a complete, structural statement of intent, not
merely an opaque digest. **This is also this table's purpose-scoped
target-shape `CHECK`**: the only `purpose` this ADR defines requires
exactly the family-owner-change target columns above; a future `purpose`
this table does not yet define would add its own analogous branch,
naming whichever target columns *that* command shape actually needs —
never a single, one-size-fits-all shape every purpose is forced to
share, and never a purpose that could carry an incompatible target shape
(e.g. family-owner-change columns populated under a hypothetical,
unrelated future purpose that has nothing to do with a family).

#### `BEFORE INSERT` target-shape guard, corrected

**Withdrawn: relying on a same-row `CHECK` to require `target_document_
family_id`/`target_document_family_public_id`/`workspace_id` non-null
for the owner-change purpose.** Codex correctly identified that this
column's own nullability is genuinely conditional on *when* the row is
observed, not on its `purpose` alone: it must be non-null the moment a
new command is created, yet must become nullable later, when the
column-targeted `ON DELETE SET NULL` referential action retires it on
family deletion (below). A same-row `CHECK` evaluates identically at
`INSERT` and at that later retiring `UPDATE` — it cannot express "required
at creation, but legitimately absent afterward" at all; a `CHECK` strict
enough to require it always would reject the very retirement this ADR's
own referential action performs, and a `CHECK` loose enough to permit
retirement would equally permit a freshly inserted row with no target at
all. **Corrected: an insert-sensitive trigger, never a same-row `CHECK`,**
for exactly the fields whose requirement is creation-time-only:

```sql
CREATE FUNCTION public.enforce_document_governance_command_target_shape()
RETURNS trigger
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = ''
AS $$
BEGIN
  IF NEW.purpose = 'document_family.owner.change' THEN
    IF NEW.target_document_family_id IS NULL
       OR NEW.target_document_family_public_id IS NULL
       OR NEW.workspace_id IS NULL
       OR NEW.expected_current_owner_user_id IS NULL
       OR NEW.expected_current_generation IS NULL
       OR NEW.intended_new_owner_user_id IS NULL
       OR NEW.request_digest IS NULL
    THEN
      RAISE EXCEPTION USING
        ERRCODE = 'integrity_constraint_violation',
        MESSAGE = 'owner_change_command_incomplete_at_creation';
    END IF;
  END IF;
  RETURN NEW;
END;
$$;

CREATE TRIGGER enforce_document_governance_command_target_shape
  BEFORE INSERT ON public.document_governance_commands
  FOR EACH ROW
  EXECUTE FUNCTION public.enforce_document_governance_command_target_shape();
```

- **Purpose-scoped**: the trigger's own required-field list applies only
  when `NEW.purpose = 'document_family.owner.change'`; a hypothetical
  future `purpose` this table does not yet define would need its own
  analogous `ELSIF` branch naming whichever creation-time fields *that*
  command shape requires — this trigger never imposes the owner-change
  shape on an unrelated purpose, and never permits an owner-change row
  to skip its own required fields because some other purpose exists.
- **Fails closed with a typed database integrity error**
  (`owner_change_command_incomplete_at_creation`) if any required
  creation-time field is absent — the row is never inserted.
- **`BEFORE INSERT` only, deliberately never `BEFORE INSERT OR
  UPDATE`**: this is what makes the trigger structurally incapable of
  firing for the later, legitimate retirement `UPDATE` that nulls
  `target_document_family_id` — there is no update path for this trigger
  to reject, accidentally or otherwise, and no special-casing or
  session-context inspection is needed to tell the two apart. The
  column-targeted `ON DELETE SET NULL (target_document_family_id)`
  referential action (below) is a system-enforced constraint action, not
  an ordinary `UPDATE` statement subject to a `BEFORE INSERT` trigger at
  all, so this trigger cannot prevent it even in principle.
- **Owner-controlled, under the established privilege model**: the
  function is `SECURITY DEFINER`, owned by `rag_platform_owner`, `SET
  search_path = ''`; as a trigger-only function it receives no runtime
  `EXECUTE` grant of any kind (PostgreSQL invokes a trigger function
  regardless of the issuing role's own `EXECUTE` privilege on it — the
  same trigger-function pattern ADR-0035 already establishes for
  `retire_bulk_operation_item_targets()`), so `rag_platform_app` cannot
  bypass it by calling it directly, and cannot avoid it by any ordinary
  `INSERT` path either, since the trigger fires for every caller — an
  Eloquent insert, a queued job, or raw SQL alike.

#### Post-insert immutability, restated completely

**Every column a freshly inserted owner-change command's own identity
and intent depend on is immutable after `INSERT`, with exactly one
documented exception.** Restated together, rather than left scattered
one column at a time: `target_document_family_id`,
`target_document_family_public_id`, `workspace_id`, `purpose`,
`intended_new_owner_user_id`, `expected_current_owner_user_id`,
`expected_current_generation`, and `request_digest` are none of them
ever rewritten by any application code path after creation. **The one,
sole, structurally distinct exception**: `target_document_family_id`
transitions `non-null → NULL`, and only in that direction, exclusively
via the column-targeted `ON DELETE SET NULL` referential action firing
on the *referenced family's own deletion* — never via an ordinary
`UPDATE` statement runtime could issue, and never in the reverse
direction (a nulled target is never subsequently repopulated). `result`
and `completed_at` remain the only columns this table's own function
ever legitimately writes post-creation, exactly as already established.

**Withdrawn: relying on a forgeable, application-facing signal (a custom
session variable, a request header, an ORM "this update came from the FK
action" flag) to distinguish a genuine retirement from a fabricated
one.** Any such signal is set by, and therefore trusted from, the same
restricted runtime role this boundary exists to constrain — indistinguishable,
from the database's own point of view, from an ordinary forged claim.
**Corrected: the existing privilege-controlled boundary this ADR already
uses everywhere else, never a session flag.** `rag_platform_app` receives
**no `UPDATE` grant of any kind — table-level or column-level — on
`document_governance_commands`**:

```sql
REVOKE UPDATE ON document_governance_commands FROM rag_platform_app;
```

**`rag_platform_app`'s complete, minimal privilege on this table, stated
explicitly**: ordinary `INSERT` (on the allowlisted creation-time
columns, guarded by the `BEFORE INSERT` trigger above) and ordinary
`SELECT` — nothing else. This pair is, and remains, sufficient for the
corrected acquisition algorithm below: `rag_platform_app` issues the
`INSERT ... ON CONFLICT ... RETURNING id`, and — only on the branch where
it did not just insert the row — a plain, non-locking `SELECT`. It
**never** issues a locking `SELECT ... FOR UPDATE` against this table,
and therefore never needs, and is never given, any `UPDATE` privilege on
it — not a full grant, not a column-level one, and not a narrowly-scoped
"dummy" column introduced merely to legalise a lock the algorithm does
not, in fact, require the runtime to take. `apply_document_family_owner_
change()`, running as `rag_platform_owner`, remains the **only** path
that ever locks or updates a command row.

This is sufficient, and deliberately not narrowed to a column
allowlist, because **no runtime code path ever needs to issue an
`UPDATE` against this table at all**: the only two ways any row's
content changes after `INSERT` are (1) `apply_document_family_owner_
change`'s own writes to `result`/`completed_at`, performed as
`rag_platform_owner` via `SECURITY DEFINER` — which bypasses
`rag_platform_app`'s own grants entirely, needing none — and (2) the
column-targeted referential action retiring `target_document_family_id`
on family deletion, which PostgreSQL performs as a constraint-enforcement
action, not as an ordinary DML statement subject to the issuing role's
own table privileges. A direct `rag_platform_app` `UPDATE` against
`target_document_family_id`, `target_document_family_public_id`,
`workspace_id`, `purpose`, `intended_new_owner_user_id`,
`expected_current_owner_user_id`, `expected_current_generation`, or
`request_digest` — or against `result`/`completed_at`, for that
matter — fails at the privilege-check stage, regardless of value,
regardless of any session-level claim about why the update is happening.

**Lifecycle shape, stated plainly**: a newly inserted owner-change
command always has a live target (enforced by the trigger above) — there
is no reachable state in which a fresh command exists with a null
target. A **historical** command may later show a null live target, and
only because its target family was genuinely deleted — never because it
was created that way. Its scalar `target_document_family_public_id` and
`workspace_id` remain, unconditionally, regardless of the live target's
own fate. A pending or incomplete command whose live target has been
retired cannot execute — `apply_document_family_owner_change`'s own
target-existence check (above) fails it closed with
`owner_change_target_family_missing` — but a command that already
reached `completed_at` (successful or a documented failure outcome)
remains exactly as readable, and exactly as intelligible, as before its
target was retired. No fresh, `NULL`-target row can ever satisfy the
owner-change purpose's own required shape — the trigger, not a same-row
`CHECK`, is what makes this true.

**Required tests, insert-time guard and post-insert immutability** —
provider-free, run against genuine PostgreSQL: a fresh `INSERT` with
`target_document_family_id` (or `target_document_family_public_id`, or
`workspace_id`, or any of the other trigger-checked columns) explicitly
`NULL` is rejected by the trigger with
`owner_change_command_incomplete_at_creation`, before the row is ever
visible to any other transaction; a fresh `INSERT` with a genuine,
non-null target for every required column succeeds normally; a direct
`rag_platform_app` `UPDATE` attempting to null `target_document_family_id`
post-insert is rejected at the privilege level, never reaching the
trigger at all (the trigger is `BEFORE INSERT` only and could not have
rejected it regardless); a direct `rag_platform_app` `UPDATE` attempting
to rewrite `target_document_family_id`, `target_document_family_public_id`,
`workspace_id`, or `purpose` to any other value is rejected identically;
a genuine family deletion is asserted to still null exactly
`target_document_family_id` on the referencing command row, confirming
the trigger's `BEFORE INSERT`-only scope never interferes with, blocks,
or is even invoked by the referential action; a pending command whose
target has been genuinely retired this way is asserted unable to execute
(fails `owner_change_target_family_missing`); and a completed command
remains fully readable after the same genuine retirement (Laravel unit,
Laravel feature/API, database integration test using genuine
PostgreSQL).

#### Composite workspace binding, corrected

**Withdrawn: a bare, single-column `target_document_family_id` FK to
`document_families (id)` alone.** Codex correctly identified that this
FK shape says nothing about which workspace the referenced family
belongs to — nothing in the schema itself prevents a command row whose
own `workspace_id` names one tenant from naming a `target_document_
family_id` that actually belongs to a **different** tenant's family, a
genuine cross-workspace reference the FK alone cannot reject. **Corrected:
the same composite-FK-against-a-declared-`UNIQUE`-pair pattern this ADR
already uses for `DocumentGovernanceEmailEnvelopeAttempt` and
`DocumentGovernanceNotificationProjectionReceipt`, applied here:**

- **`UNIQUE (id, workspace_id)` on `document_families`** — a new,
  additive constraint this ADR introduces (ADR-0030 does not declare one
  today), extending ADR-0030's own schema without changing any of its
  settled owner/metadata semantics, the same additive-extension pattern
  this ADR already uses for `owner_assignment_generation` itself.
- **`FOREIGN KEY (target_document_family_id, workspace_id) REFERENCES
  document_families (id, workspace_id)`** on `DocumentGovernanceCommand`
  — the exact matching column order, replacing the withdrawn
  single-column FK entirely, not layering a second, redundant one
  alongside it.
- **A cross-workspace command insert is now structurally impossible**:
  `INSERT`ing a `(target_document_family_id, workspace_id)` pair that
  does not match a real `document_families (id, workspace_id)` row
  together fails the composite FK outright, regardless of whether
  `target_document_family_id` alone would have resolved to a real family
  in some *other* workspace.
- **`workspace_id` is immutable once the command row is created, with
  `target_document_family_id` immutable except for its own one
  documented retirement transition** — see "Post-insert immutability,
  restated completely" below for the complete column list, the single
  `non-null → NULL` exception, and the exact privilege mechanism
  (`rag_platform_app` holds **no** `UPDATE` grant of any kind on this
  table) that makes runtime fabrication of any other change structurally
  impossible.
#### Column-targeted `ON DELETE SET NULL`, corrected

**Withdrawn: describing the composite FK's own deletion behaviour as an
ordinary `nullOnDelete()` shape.** Codex correctly identified that
PostgreSQL's *composite* `ON DELETE SET NULL` — which is exactly what
Laravel's `nullOnDelete()` schema-builder shorthand generates for a
multi-column foreign key — nulls **every** referencing column together,
as one tuple. Applied here, that would null `workspace_id` alongside
`target_document_family_id` on an ordinary family deletion, destroying
the command row's own required non-null tenancy identity — the exact
column this composite FK exists to protect, not merely an
incidental side effect. **Laravel's `nullOnDelete()` shorthand is
therefore insufficient for this FK and must not be used for it.**
**Corrected: PostgreSQL's column-targeted referential action**
(available from PostgreSQL 15 onward; this repository runs
`postgres:18.4-alpine`, verified directly against `compose.yaml`, so the
feature is available), applied via raw DDL in the migration — not the
schema-builder's composite shorthand:

```sql
ALTER TABLE document_governance_commands
  ADD CONSTRAINT document_governance_commands_target_family_fk
  FOREIGN KEY (target_document_family_id, workspace_id)
  REFERENCES document_families (id, workspace_id)
  ON DELETE SET NULL (target_document_family_id);
```

- **Only `target_document_family_id` is ever nulled by this action** —
  `workspace_id` is untouched by the referential action itself and
  remains exactly what it always was: non-null, and — independently,
  per the immutability rule above — never rewritten by any code path in
  this ADR either. A family deletion event and an ordinary application
  `UPDATE` are the only two ways any column on this row could ever
  change, and neither is permitted to touch `workspace_id`.
- **`target_document_family_public_id` (above) survives unconditionally**
  — it was never part of the FK and is never touched by the referential
  action, so a completed historical command remains fully intelligible
  (which workspace, which family, by public identity, what the command
  did) long after the live family row, and the live FK pointing at it,
  are both gone. Command identity (`id`/`public_id`), `request_digest`,
  `result`, the audit event it produced, and `workspace_id` itself are
  all likewise unaffected by the family's own deletion.
- **No command row blocks legitimate family deletion**: the referential
  action is `SET NULL`, never `RESTRICT`/`NO ACTION` — a family with
  outstanding command rows (pending or completed) is always deletable.
- **No family deletion can erase command tenancy**: `workspace_id`'s own
  non-null, immutable status is preserved through a family deletion
  precisely because the referential action is scoped to the one column
  that may legitimately go stale, never the tenancy column, which never
  goes stale (a family's own workspace never changes; the family simply
  ceases to exist).
- **A pending or incomplete command whose live target has been nulled
  cannot execute, and cannot produce a successful or no-op result.**
  `apply_document_family_owner_change`'s own step 3 (`SELECT ... FROM
  public.document_families WHERE id = commands.target_document_family_id
  FOR UPDATE`) locks **zero rows** once `target_document_family_id` is
  `NULL` — there is nothing left to lock. **The function fails closed on
  this condition explicitly**, with a typed
  `owner_change_target_family_missing` error, rather than allowing a
  `NULL`-driven query to fall through into any later branch by accident;
  no no-op result, no mutation, no audit write, and no command completion
  occur. This is checked immediately after the family-lock attempt and
  before the workspace-binding check (below), since a null target
  cannot meaningfully be compared to anything.
- **Tenant-safe external behaviour is unaffected**: whether a command
  fails because its target was deleted, because of a workspace mismatch,
  or because it never referenced a real family at all, the caller-facing
  outcome remains the same generic, tenant-safe concealment this
  decomposition applies everywhere else — never a signal distinguishing
  "the family was deleted" from "the family never existed" or "wrong
  workspace."
- **No cross-workspace information is disclosed through error
  behaviour**: a command submitted against a family id that belongs to a
  different workspace than the caller's own fails with the same generic,
  tenant-safe concealment this decomposition applies everywhere else
  (structurally indistinguishable, from the caller's own perspective,
  from "this family does not exist") — the composite FK's own rejection
  is caught and translated by the Action into the ordinary concealment
  response, never surfaced as a raw constraint-violation message that
  could confirm a family id's existence in another tenant.

`UNIQUE (workspace_id, purpose, client_idempotency_key)` — **reusing
ADR-0031's own governance-idempotency shape verbatim, never a second,
differently-shaped authority**:

- **Matching key, matching `request_digest`** → return the stored
  `result` unchanged; no mutation.
- **Matching key, differing `request_digest`** → typed
  `idempotency_key_conflict`, fails closed — the same rule, and the same
  public error vocabulary, ADR-0031 already establishes for its own
  governance routes.
- **Different `purpose` or different `workspace_id`** → entirely
  independent commands.
- **No Python involvement of any kind** — this command, its Action, and
  its table are exclusively Laravel-owned, consistent with every other
  owner/eligibility mechanism in this ADR.

#### Atomic idempotency acquisition, corrected

**Withdrawn: "look up an existing row; if found, replay; if not, insert
a new one" as two separate statements.** Codex correctly identified that
a plain `SELECT` followed by a conditional `INSERT` is not atomic: two
concurrent requests for the same, brand-new `(workspace_id, purpose,
client_idempotency_key)` can both run their own `SELECT` before either
`INSERT` commits, both find nothing, and both attempt to insert —
racing on the unique constraint in a way the algorithm never named a
resolution for. **Corrected: one atomic acquisition sequence, entirely
inside the owner-change transaction, before anything else happens:**

```sql
INSERT INTO document_governance_commands (
  public_id, workspace_id, purpose, client_idempotency_key,
  requested_by_user_id, target_document_family_id,
  expected_current_owner_user_id, expected_current_generation,
  intended_new_owner_user_id, request_digest
) VALUES (
  :public_id, :workspace_id, 'document_family.owner.change', :key,
  :actor_id, :family_id,
  :expected_owner_id, :expected_generation,
  :intended_owner_id, :digest
)
ON CONFLICT (workspace_id, purpose, client_idempotency_key) DO NOTHING
RETURNING id;
```

**Withdrawn: an Action-level `SELECT ... FOR UPDATE` on
`document_governance_commands`, issued as `rag_platform_app`, as step 2
of this algorithm.** Codex correctly identified a direct contradiction
with the privilege model this ADR itself establishes: PostgreSQL
requires `UPDATE` privilege on at least one column of a table to acquire
a row lock (`FOR UPDATE`) against it — a plain `SELECT` and a locking
`SELECT ... FOR UPDATE` are privilege-distinct operations — and
`rag_platform_app` holds **no** `UPDATE` privilege of any kind on this
table. The algorithm as previously written could not have executed at
all. **Corrected: the runtime never locks a command row. Locking is the
privileged function's own job, performed as `rag_platform_owner`, which
genuinely holds the privilege — never worked around by granting runtime
a dummy or narrowly-protected `UPDATE` column, which would simply
reopen the boundary items 8–10 already closed.**

1. **Attempt the `INSERT ... ON CONFLICT ... DO NOTHING RETURNING id`
   above.** `rag_platform_app` needs, and receives, only ordinary
   `INSERT` (on the allowlisted creation-time columns, guarded by the
   `BEFORE INSERT` trigger above) and `SELECT` on this table — nothing
   more. **PostgreSQL's own conflict handling, relied on directly, not
   merely asserted:** if another transaction has already inserted the
   same unique key but not yet committed, this statement itself **waits**
   for that transaction to finish (it must, to know whether a real
   conflict exists) — it does not return early. Once the competing
   transaction **commits**, this statement resolves the conflict against
   the now-visible row and returns **no** row. Once the competing
   transaction **rolls back**, the conflict vanishes and this statement
   **proceeds to insert**, returning its own new row's `id` — under
   ordinary `READ COMMITTED`, each statement (including this one)
   obtains a fresh snapshot, so a rolled-back competitor is simply gone
   by the time this `INSERT` re-evaluates the conflict.
2. **If `RETURNING id` produced a row** (this transaction is the
   creator — see "Newly inserted command" below), proceed directly to
   the privileged function; **no further read of this table by the
   Action is needed or performed**, since the Action already knows every
   value it just inserted.
3. **If `RETURNING id` produced no row** (a row for this exact key
   already existed, and this `INSERT` waited for, then lost, the race —
   see "Existing …" cases below), perform an ordinary, **non-locking**
   `SELECT` by the exact unique identity:
   ```sql
   SELECT request_digest, completed_at, result
   FROM document_governance_commands
   WHERE (workspace_id, purpose, client_idempotency_key) = (:workspace_id, 'document_family.owner.change', :key);
   ```
   No `FOR UPDATE`, and none is needed: this branch never mutates
   anything itself — it only ever returns a stored result, fails with a
   typed conflict, or fails closed on an exceptional shape, none of
   which requires holding a lock past the read.
4. **Route the row through exactly one of the following closed cases —
   never a fifth, ad hoc outcome:**

**Newly inserted command** (step 1's own `INSERT` returned the row's
`id`): this transaction owns the row outright, by construction — no
other transaction can have seen it before this one, and no lock is
needed to establish that. **Invoke `apply_document_family_owner_change
(id)` immediately.** The function itself, running as `rag_platform_owner`,
performs the authoritative `SELECT ... FROM public.document_governance_
commands WHERE id = p_command_id FOR UPDATE` as its own first act
(unchanged from its own internal step 1, below) — this is the **only**
lock ever taken on a command row in this entire algorithm, and the
**only** role that ever needs, or has, the privilege to take it.

**Existing completed command, matching digest** (step 3's plain `SELECT`
shows `completed_at IS NOT NULL` and the freshly computed digest for
this request equals the stored `request_digest`): return the stored
`result` verbatim; perform no mutation; do not increment
`owner_assignment_generation`; do not create another audit event; do not
call the privileged function at all.

**Existing command, different digest** (the stored `request_digest`
does not equal the freshly computed one for this request, regardless of
`completed_at`): fail closed with a typed `idempotency_key_conflict`;
perform no mutation; reveal nothing about the conflicting command's own
target, owner values, or workspace beyond the generic conflict — the
same tenant-safe concealment discipline this decomposition applies
everywhere else. **This is also the correct, and only, outcome for
concurrent same-key/different-digest requests**: whichever request's
`INSERT` actually commits first is the one whose `request_digest`
becomes permanently bound to the row; the other's own `INSERT` waited on
the conflict (step 1), lost once the first committed, fell through to
step 3's plain `SELECT`, and finds a digest that does not match its
own — it fails here, deterministically, never on which one "arrived
first" from the caller's own perspective, and never by holding, or
needing, any lock to discover it.

**Existing matching, incomplete command** (step 3's plain `SELECT` shows
`completed_at IS NULL` — this transaction did not insert the row in
step 1, by construction of reaching this branch at all, so no
parenthetical is needed to establish that): **structurally exceptional,
and treated as such.** Because command acquisition and the invocation of
the privileged function happen in immediate succession within the same
Action, and the function's own internal work — its lock, the family
mutation, the audit write, and command completion — is itself one short
transaction, an ordinary crash before that transaction commits rolls the
**entire** thing back — the row disappears with it, and the next
request's own `INSERT` succeeds cleanly as a fresh row, never as a
durably-visible incomplete one. A **durably committed, still-incomplete**
row is therefore not an expected outcome of any normal path; it can only
mean a completed-but-uncommitted intermediate state was somehow made
durable outside this algorithm's own transaction boundary, or a bug.
**Selected: fail closed.** The Action raises a typed
`owner_change_command_incomplete` integrity/recovery condition, performs
no mutation, and does **not** casually re-execute the mutation merely
because the row's status looks incomplete — the smallest safe design,
since no legitimate normal path should ever produce this row shape.

**Missing row after a no-return conflict result** (step 1's `INSERT`
returned no row — implying a conflicting row existed — yet step 3's
plain `SELECT` for that exact same unique identity finds **zero** rows):
**a distinct, more severe exceptional shape than the incomplete-command
case above, and treated as such.** Under the wait-then-resolve semantics
step 1 relies on, this should be impossible — by the time the `INSERT`
itself returns, the competing transaction has already definitively
committed (in which case the row must be visible to this immediately
following `SELECT`) or rolled back (in which case this `INSERT` would
have proceeded and returned its own row instead of reporting a
conflict). **Selected: fail closed as an impossible acquisition
inconsistency, never retried blindly** — the Action raises a typed
`owner_change_acquisition_inconsistency` error and performs no mutation;
a blind retry could not distinguish a genuine, deeper corruption from an
ordinary transient race, and retrying an already-anomalous read is never
the safe default this ADR applies elsewhere.

Recovering from either exceptional shape above (confirming, out of band,
whether the family mutation and audit actually occurred despite the
missing `completed_at`, or reconciling a row that should be
unreachable) is reserved for a future, explicitly designed operational-reconciliation
procedure with its own evidence and locking requirements — this ADR does
not speculatively design that procedure now, since no reachable path in
this ADR's own algorithm produces the condition it would recover from.

**Required tests, corrected acquisition privilege and concurrency** —
provider-free, run against genuine PostgreSQL, exercising the corrected
division between what the runtime does and what the privileged function
does: a `rag_platform_app` ordinary, non-locking `SELECT` against
`document_governance_commands` succeeds; a `rag_platform_app` `SELECT ...
FOR UPDATE` against the same table is rejected outright for lack of
`UPDATE` privilege, asserted directly against PostgreSQL's own error, not
merely against application-level behaviour; `apply_document_family_
owner_change`, invoked as `rag_platform_app` but running internally as
`rag_platform_owner`, successfully locks a command row; two genuinely
concurrent same-key/same-digest requests are asserted to have the second
transaction's own `INSERT ... ON CONFLICT` **wait** (never issue any
`SELECT ... FOR UPDATE` of its own) and, once the first commits, return
the identical completed stored result; two genuinely concurrent
same-key/different-digest requests are asserted the same way, the second
waiting at its own `INSERT` and then failing `idempotency_key_conflict`
once it can re-read; a forced rollback of the transaction that owns a
freshly inserted command row is asserted to let a second, previously
waiting `INSERT` for the same key proceed and become the genuine
inserter itself; a fabricated "no row returned, then missing row on the
follow-up `SELECT`" condition (constructed directly against test
fixtures, since it is not reachable through the algorithm's own normal
operation) is asserted to fail closed as
`owner_change_acquisition_inconsistency`, never retried automatically; a
durably visible, genuinely incomplete command row (constructed the same
way) is asserted to fail closed as `owner_change_command_incomplete`; a
direct concurrent-`INSERT` race against the unique constraint is
asserted to always surface as one of this algorithm's own typed outcomes,
never as a raw, unhandled unique-constraint-violation database error;
and the `command → family` lock order is asserted unchanged — every lock
this algorithm ever takes, across every path, is taken inside
`apply_document_family_owner_change`, in that order, and nowhere else
(Laravel feature/API, database integration test using genuine
PostgreSQL with real concurrent connections, not simulated sequential
calls).

#### Protected-column privilege model and the purpose-controlled mutation function

**Withdrawn: a `BEFORE UPDATE` consistency trigger described as the
authority boundary for `owner_user_id`/`owner_assignment_generation`.**
Codex correctly identified that a trigger which merely checks "did the
generation advance by exactly one alongside an owner change" proves
internal **consistency**, never **authority** — the restricted runtime
role, `rag_platform_app`, could issue that exact paired `UPDATE` directly,
satisfying the trigger, while completely bypassing `ChangeDocumentFamilyOwner`,
its idempotency record, and its audit write. **Corrected: the same
three-role PostgreSQL boundary frozen ADR-0035 already establishes**
(`rag_platform_owner` `NOLOGIN`, owning protected objects and functions;
`rag_platform_migrator`, an isolated one-shot migration identity;
`rag_platform_app`, the sole restricted runtime identity — no competing
role model is introduced) **— extended here with column-level privilege
revocation and one new, purpose-controlled `SECURITY DEFINER` function,
following the identical shape ADR-0035 already uses for its own
protected-column boundary on `bulk_operation_items`.**

**Protected columns — withdrawn: describing a table-level `GRANT UPDATE
ON document_families TO rag_platform_app` as harmless because
`owner_user_id`/`owner_assignment_generation` had been "explicitly
excluded from the column-level grant list."** Codex correctly identified
that this is false: PostgreSQL's privilege model is **additive**, never
subtractive-by-omission — a table-level `UPDATE` grant conveys `UPDATE`
on **every** column of that table, including one a prior, narrower,
column-level grant never named. There is no PostgreSQL privilege state
in which "this column was excluded from an earlier grant" itself blocks
a later, broader grant from covering it — the earlier grant's own
narrowness was never a durable exclusion, only an absence that a later,
broader grant fully overrides. **Corrected: the normative final privilege
state is a standing table-level `REVOKE`, never merely a narrower
`GRANT`:**

```sql
REVOKE UPDATE ON document_families FROM rag_platform_app;
GRANT UPDATE (
  name, description, category, tags, review_due_date
  -- , every other column ADR-0030 already treats as ordinary
  -- family-level editable metadata
) ON document_families TO rag_platform_app;
```

`rag_platform_app` **never** holds table-level `UPDATE` on
`document_families` at all, at any point — only the explicit column-level
grant above. This is the actual mechanism that makes
`owner_user_id`/`owner_assignment_generation` unreachable: not "these two
columns were left off a list," but "the table-level privilege these two
columns would otherwise fall under does not exist for this role, full
stop." **Table-level `INSERT` is additionally revoked from
`rag_platform_app` and re-granted on every column except
`owner_assignment_generation`**, the same `INSERT`-column-exclusion-plus-
`DEFAULT`-value pattern ADR-0035 already establishes for
`target_reference_status`: the column declares `DEFAULT 1`, so the
family-creation Action's ordinary `INSERT` (naming `owner_user_id`
explicitly, since a real initial owner is required, but never naming
`owner_assignment_generation`) receives `1` automatically, and any
`INSERT` that explicitly names `owner_assignment_generation` fails at
the privilege-check stage regardless of the value supplied.

#### Interaction with ADR-0035's own baseline/default-privilege sweep

**A real, verified tension, reconciled explicitly, not assumed away.**
ADR-0035's own "complete runtime grant baseline" (frozen, unmodified by
this ADR) applies "the repository's ordinary runtime DML grant set
(`SELECT`, `INSERT`, `UPDATE`, `DELETE`, as actually required) to **every
existing application table**," and states that "this baseline is
reconciled idempotently in local, CI, and deployed bootstrap" — meaning
it is not a one-shot event; it **re-applies**. Left unreconciled, this
general, table-level baseline would itself grant `rag_platform_app` a
broad table-level `UPDATE` on `document_families` (an ordinary
application table, with no reason for the general sweep to treat it
differently) every time it re-runs — directly reintroducing the exact
privilege this section exists to deny, regardless of how carefully this
ADR's own narrower column-level grant is written.

**Corrected sequencing, stated as an explicit ordering rule**:

1. ADR-0035's own general baseline may run first (or at any point) and
   may grant `rag_platform_app` table-level `UPDATE` on
   `document_families`, exactly as it does for every other ordinary
   application table — this is not itself an error.
2. **`document_families`' own table-specific migration then applies its
   own `REVOKE UPDATE ON document_families FROM rag_platform_app`,
   unconditionally, after the general baseline (or default privileges)
   have had any opportunity to grant it, followed by the column-level
   `GRANT UPDATE (...)` above.** This table-specific step must run
   **last**, and must be **idempotent** (a `REVOKE` on a privilege the
   role does not currently hold is a harmless no-op in PostgreSQL) so it
   is safe to include in every reconciliation pass, not merely the
   migration that first introduces it.
3. **Any later migration that adds a new mutable metadata column to
   `document_families` must make its own explicit, reviewed column-level
   `GRANT UPDATE (<new column>)`** — adding a column never, by itself,
   makes it runtime-writable; the table's own standing table-level
   `REVOKE` means an unlisted column defaults to unreachable, not
   reachable, closing exactly the "new column remains non-writable until
   explicitly granted" gap this pass's own required tests name.
4. **No general "grant DML on all tables" reconciliation command may
   run *after* step 2 without also re-applying `document_families`' own
   table-specific `REVOKE`** — this ADR's own reconciliation/verification
   step (immediately below) is what actually guarantees this, by
   treating a rediscovered table-level `UPDATE` on this one table as a
   deployment-failing condition regardless of which prior step
   reintroduced it, rather than trusting migration ordering alone to
   never be re-run out of sequence.

#### Final-state privilege reconciliation and verification, required

**Withdrawn: asserting the table-level check via
`information_schema.role_table_grants ... column_name`.** Codex correctly
identified that `role_table_grants` has no `column_name` at all — its
columns are exactly `grantor`, `grantee`, `table_catalog`,
`table_schema`, `table_name`, `privilege_type`, `is_grantable`,
`with_hierarchy`. The check as previously stated could not have run.
**Corrected: a dedicated verification step, run after every migration
and grant statement has applied, querying PostgreSQL's own catalogues
directly — never an application-level assertion about what a migration
file says it does — using the correct source for each distinct
question, since a table-level grant and a column-level grant are
genuinely different facts that must never be confused with each other:**

**Table-level privilege — corrected source and query.**
`information_schema.role_table_grants` reports **only** genuinely
table-level grants by its own definition — a column-level grant never
produces a row in this view at all, so no `column_name`-based
disambiguation is needed, or exists, to tell them apart; the mere
presence of a row here for this table/grantee/privilege combination is
already the table-level fact:

```sql
SELECT 1
FROM information_schema.role_table_grants
WHERE table_schema = 'public'
  AND table_name   = 'document_families'
  AND grantee      = 'rag_platform_app'
  AND privilege_type = 'UPDATE';
-- expected: zero rows
```

Run as (or joined against the visibility of) `rag_platform_owner` — the
table's own owner — since `role_table_grants` reports grants visible to
the querying role as grantor, grantee, or a role it is a member of, and
every grant in this scheme is issued by the owner. An **explicit
column-level `UPDATE` grant on the allowlisted metadata columns is never
confused with this check**: it is structurally invisible to
`role_table_grants` regardless of how many columns it covers, so a
passing allowlisted-column grant can never trip this table-level check,
and this check can never be satisfied by a column-level grant no matter
how broad. An equivalent direct `pg_catalog` ACL inspection
(`aclexplode(relacl)` on `pg_class` for `document_families`, filtered to
whole-table `UPDATE` entries) may be used instead where the
implementation prefers raw catalogue access over `information_schema`.

#### Column-level effective privileges, corrected source and query

**Withdrawn: `information_schema.column_privileges`, named without
verifying its actual reported shape.** Corrected to use PostgreSQL's own
built-in effective-privilege function, which is authoritative for "can
this role actually do this," including role inheritance, without
requiring hand-rolled catalogue joins:

```sql
SELECT
  has_column_privilege('rag_platform_app', 'document_families', 'name', 'UPDATE')                        AS name_ok,
  has_column_privilege('rag_platform_app', 'document_families', 'description', 'UPDATE')                 AS description_ok,
  has_column_privilege('rag_platform_app', 'document_families', 'category', 'UPDATE')                     AS category_ok,
  has_column_privilege('rag_platform_app', 'document_families', 'tags', 'UPDATE')                         AS tags_ok,
  has_column_privilege('rag_platform_app', 'document_families', 'review_due_date', 'UPDATE')              AS review_due_date_ok,
  has_column_privilege('rag_platform_app', 'document_families', 'owner_user_id', 'UPDATE')                AS owner_user_id_forbidden,
  has_column_privilege('rag_platform_app', 'document_families', 'owner_assignment_generation', 'UPDATE')  AS generation_forbidden;
-- expected: every "_ok" column true; both "_forbidden" columns false
```

- **Every approved mutable metadata column** must show `true`.
- **`owner_user_id` and `owner_assignment_generation` must each show
  `false`.**
- **`has_column_privilege` reports the effective privilege**, correctly
  folding in role inheritance and — critically — **a table-level grant
  would make every column, including both protected ones, show `true`
  here as well**: this is precisely why the table-level check above and
  this column-level check are complementary, never redundant, and why an
  intended column-level grant is never mistaken for the forbidden
  table-level shape — the table-level check asks "does a table-level ACL
  entry exist at all," and this check asks "is this specific column
  reachable by any means," and a passing, correctly scoped deployment
  must answer "no" to the first and "yes only for the allowlist" to the
  second.
- **No unexpected column is writable**: the verification step's own
  expected-column allowlist is compared, by set equality, against every
  column of `document_families` queried the same way — an extra,
  unreviewed column showing `true` fails the check exactly as a missing
  expected one would (enumerated via `information_schema.columns` for
  the table, then checked one by one, or via a single query against
  `pg_attribute`/ACL if the implementation prefers set-based catalogue
  access over a per-column loop).

#### Effective login-role membership and access audit, corrected and complete

**Withdrawn: checking only whether `rag_platform_app` itself holds a
direct membership row in either privileged role.** Codex correctly
identified that this proves nothing about the rest of the role graph:
it cannot detect another login role directly or transitively a member of
`rag_platform_app` (and therefore able to reach whatever `rag_platform_
app` can reach); another login role reaching `rag_platform_migrator` or
`rag_platform_owner` by any path; inherited function execution flowing
through a membership chain the one-level join never traverses; `SET
ROLE` authority reachable through a transitive chain rather than a
direct grant; or an unrelated login role holding superuser/role-creation
authority that would make the entire boundary moot regardless of any
grant this ADR controls. A single one-level join proves one fact about
one role; it is not an audit of the role graph. **Corrected: enumerate
every login role in the instance, and evaluate each one's
direct-or-transitive relationship to all three protected roles and to
the protected function, using PostgreSQL's own built-in privilege
functions as the authority for "is this true," and a recursive
catalogue traversal only where a diagnostic path or a membership-option
detail those functions do not expose is actually needed.**

**Step 1 — enumerate every login role:**

```sql
SELECT oid, rolname, rolsuper, rolcreaterole, rolcreatedb, rolinherit, rolreplication
FROM pg_roles
WHERE rolcanlogin;
```

**Withdrawn: omitting `rolcreatedb` from this enumeration.** Codex
correctly identified that the allowlist matrix below states an exact
expected `rolcreatedb` value for more than one row, yet the executable
query this ADR's own audit runs never selected the column that
comparison depends on — a matrix cell with nothing behind it. **Corrected:
`rolcreatedb` is selected here, alongside every other attribute the
matrix actually compares, so the query genuinely carries the data the
comparison requires** — never merely named in prose while absent from
the executable projection.

`rag_platform_owner` itself is `NOLOGIN` and must **never** appear in
this result set at all — its appearance here is itself an immediate
fail-closed condition, checked before any per-role evaluation below even
begins.

**Step 2 — for every login role returned above, and for each of the
three protected roles, compute the authoritative membership/usage/`SET`
facts via `pg_has_role()`** (its `'MEMBER'`, `'USAGE'`, and `'SET'`
privilege-type keywords, all three confirmed supported on this
repository's pinned PostgreSQL version, `postgres:18.4-alpine` —
verified directly against `compose.yaml`, well past the PostgreSQL 16
release that introduced the `SET`/`INHERIT` membership-option model
`pg_has_role('SET')` depends on):

```sql
SELECT
  lr.rolname                                            AS login_role,
  lr.rolsuper, lr.rolcreaterole, lr.rolcreatedb, lr.rolinherit, lr.rolreplication,
  pg_has_role(lr.oid, prot_app.oid,       'MEMBER') AS member_of_app,
  pg_has_role(lr.oid, prot_app.oid,       'USAGE')  AS usage_of_app,
  pg_has_role(lr.oid, prot_app.oid,       'SET')    AS can_set_role_app,
  pg_has_role(lr.oid, prot_migrator.oid,  'MEMBER') AS member_of_migrator,
  pg_has_role(lr.oid, prot_migrator.oid,  'USAGE')  AS usage_of_migrator,
  pg_has_role(lr.oid, prot_migrator.oid,  'SET')    AS can_set_role_migrator,
  pg_has_role(lr.oid, prot_owner.oid,     'MEMBER') AS member_of_owner,
  pg_has_role(lr.oid, prot_owner.oid,     'USAGE')  AS usage_of_owner,
  pg_has_role(lr.oid, prot_owner.oid,     'SET')    AS can_set_role_owner,
  has_function_privilege(
    lr.oid,
    to_regprocedure('public.apply_document_family_owner_change(bigint)'),
    'EXECUTE'
  )                                                  AS effective_execute
FROM pg_roles lr
CROSS JOIN (SELECT oid FROM pg_roles WHERE rolname = 'rag_platform_app')       prot_app
CROSS JOIN (SELECT oid FROM pg_roles WHERE rolname = 'rag_platform_migrator') prot_migrator
CROSS JOIN (SELECT oid FROM pg_roles WHERE rolname = 'rag_platform_owner')    prot_owner
WHERE lr.rolcanlogin;
```

**`'MEMBER'` answers "is there any membership path at all, direct or
transitive, regardless of inherit/`SET` options"; `'USAGE'` answers "are
this role's privileges currently, automatically active for the login
role, right now, without an explicit `SET ROLE`" (this is what
`INHERIT`/`NOINHERIT` actually governs); `'SET'` answers "can the login
role issue `SET ROLE` to reach this role's authority on demand."** These
are three genuinely different questions, and the audit evaluates all
three for every login role against every protected role — never
collapsing them into one, and never treating `NOINHERIT` as if it also
answered the `'SET'` question, which it does not.

**Step 3 — diagnostic path, via recursive `pg_auth_members` traversal,
used only to explain *how* a `pg_has_role()` result above came to be
true, never as a competing source of truth for whether it is true:**

```sql
WITH RECURSIVE membership_path AS (
  SELECT m.member AS login_oid, m.roleid AS reached_oid,
         ARRAY[m.member, m.roleid] AS path,
         m.inherit_option, m.set_option
  FROM pg_auth_members m
  JOIN pg_roles lr ON lr.oid = m.member AND lr.rolcanlogin
  UNION ALL
  SELECT mp.login_oid, m.roleid, mp.path || m.roleid,
         m.inherit_option, m.set_option
  FROM membership_path mp
  JOIN pg_auth_members m ON m.member = mp.reached_oid
  WHERE NOT m.roleid = ANY (mp.path)  -- cycle prevention: never revisit
                                       -- a role already on this path
)
SELECT login_oid, reached_oid, path, inherit_option, set_option
FROM membership_path
WHERE reached_oid IN (
  (SELECT oid FROM pg_roles WHERE rolname = 'rag_platform_app'),
  (SELECT oid FROM pg_roles WHERE rolname = 'rag_platform_migrator'),
  (SELECT oid FROM pg_roles WHERE rolname = 'rag_platform_owner')
);
```

**Withdrawn: claiming "PostgreSQL itself permits granting role A to role
B and role B to role A," as the reason a cycle guard is needed.** Codex
correctly identified this as false — PostgreSQL's own `GRANT role_name
TO other_role` rejects a grant that would create a circular membership
outright, at grant time, with its own catalogue error; a genuine cyclic
`pg_auth_members` graph is not a state a normally operating cluster can
ever reach. **Corrected: the `NOT m.roleid = ANY (mp.path)` guard is
retained, but as defensive programming, never as a response to a
reachable database state.** It protects this recursive query against:
malformed or externally-loaded catalogue data outside PostgreSQL's own
`GRANT` path (a restored dump from an inconsistent source, a
direct-catalogue-write bypassing ordinary DDL); this exact query being
reused in the future against a less-constrained edge source that does
not enforce acyclicity the way `pg_auth_members` itself does; an
implementation error in the traversal logic itself; or unexpected
environmental/catalogue corruption. It is the same defensive posture
this ADR already applies elsewhere (checking things "anyway," never
assuming a structural guarantee makes the check unnecessary) — not
evidence that the guarded-against state is normally reachable. Each path
only ever extends through roles it has not already visited. This query's own `path` column
is retained purely for **diagnostic** output (which intermediate roles a
flagged login role reaches a protected role through) — it deliberately
does not attempt to hand-derive the aggregate "is the whole chain
`INHERIT`-active" answer itself, since that aggregation is exactly what
`pg_has_role('USAGE')`/`pg_has_role('SET')` already compute correctly
and authoritatively in step 2; re-deriving it here would risk a second,
possibly divergent implementation of logic PostgreSQL itself already
owns.

**Self-membership, classified explicitly, never mistaken for a
violation**: `pg_has_role(role, role, 'MEMBER')` (and `'USAGE'`/`'SET'`)
is trivially `true` for a role with respect to **itself** — every role
is its own member. The classification below treats each of
`rag_platform_app`'s, `rag_platform_migrator`'s, and `rag_platform_
owner`'s own rows in the audit result set as **that role's own
identity**, not as an instance of "a login role reaching a protected
role," and asserts the self-referential `true` values as the expected,
required shape for that one row specifically — never flags them, and
never lets the general "no unexpected login reaches X" rule
inadvertently apply to a protected role's own row about itself.

#### Exact allowlist matrix, environment-versioned

**A closed, named matrix — never a wildcard pattern such as "all service
roles" — checked in this exact shape by the audit query above.** Every
login role the enumeration returns must fall into exactly one of these
categories; a login role the matrix does not name at all is, by
definition, an instance of "every other login role" below.

**Withdrawn: one combined "deployment/bootstrap" matrix row permitting
`rolsuper = environment-declared` (which the environment could set to
`true`) while simultaneously expecting every membership/`USAGE`/`SET`/
`effective_execute` cell to be `false`.** Codex correctly identified
that this combination is factually impossible — PostgreSQL's own
superuser semantics grant a `rolsuper = true` role unrestricted, catalog-
ACL-independent access to every object and the ability to assume any
role; no `GRANT`/`REVOKE` statement, and no catalogue check this ADR
defines, can make a genuine superuser's `effective_execute` or
`set_role_owner` read `false`. A single row cannot honestly represent
both "may or may not be superuser" and "definitely has no reach" at
once. **Corrected: two separate, factually accurate categories, never
one conflated row:**

| Login role | `rolsuper` | `rolcreaterole` | `rolcreatedb` | `member_of_app` | `usage_of_app` | `set_role_app` | `member_of_migrator` | `usage_of_migrator` | `set_role_migrator` | `member_of_owner` | `usage_of_owner` | `set_role_owner` | `effective_execute` |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| `rag_platform_app` (self) | `false` | `false` | `false` | `true`* | `true`* | `true`* | `false` | `false` | `false` | `false` | `false` | `false` | `true` |
| `rag_platform_migrator` | `false` | `false` | `false` | `false` | `false` | `false` | `true`* | `true`* | `true`* | `true` | `false` | `true` | `false` |
| Named non-superuser bootstrap/deployment identity (below) | `false` | environment-declared exact value | environment-declared exact value | `false` | `false` | `false` | `false` | `false` | `false` | `false` | `false` | `false` | `false` |
| Named cluster superuser (below) | `true` | environment-declared exact value† | environment-declared exact value† | `true`‡ | `true`‡ | `true`‡ | `true`‡ | `true`‡ | `true`‡ | `true`‡ | `true`‡ | `true`‡ | `true`‡ |
| Every other login role | `false` | `false` | `false` | `false` | `false` | `false` | `false` | `false` | `false` | `false` | `false` | `false` | `false` |

\* Self-referential rows, per "Self-membership, classified explicitly"
above — expected and required for that one role's own identity, never
evaluated against the "every other login role" row's own, stricter
expectations.

† **Withdrawn: hard-coding `rolcreaterole = true` and `rolcreatedb =
true` for a genuine cluster superuser.** Codex correctly identified that
PostgreSQL represents `rolsuper`, `rolcreaterole`, and `rolcreatedb` as
three genuinely independent role attributes — a role can hold
`rolsuper = true` while either or both of the other two are `false`; a
superuser's own unrestricted access to every object does not flow from,
or require, either creation flag, both of which govern only the
narrower ability to issue `CREATE ROLE`/`CREATE DATABASE` as a
convenience distinct from superuser status itself. **Corrected: this
row's own `rolcreaterole`/`rolcreatedb` are each an explicit,
environment-manifest-declared exact value — never inferred from
`rolsuper`, and never assumed to be `true` merely because the row is
already known to be a superuser.** The audit compares the database's own
observed `rolcreaterole`/`rolcreatedb` for this named role against
whatever exact value the environment's own manifest declares for each,
independently, and fails closed on either mismatch — a superuser
provisioned with both flags `false`, both `true`, or one of each, is
every bit as valid a shape as any other, provided it matches what the
manifest itself declares.

‡ **Truthful, not aspirational, and independent of `rolcreaterole`/
`rolcreatedb`'s own values**: every one of these cells is expected
`true` for a genuine superuser, because that is what `rolsuper = true`
actually means in PostgreSQL — a superuser bypasses every catalog ACL
this ADR's own `GRANT`/`REVOKE` statements establish, including the
protected function's own `EXECUTE` grant and every protected role's own
membership boundary. `pg_has_role()` and `has_function_privilege()`
correctly report `true` here on their own, without any special-casing
in the audit query — it is the **matrix's own stated expectation**, not
the query, that was previously dishonest about this row.

**`rag_platform_app`**: may log in; has explicit, effective `EXECUTE` on
the protected function; is not a member of, cannot inherit from, and
cannot `SET ROLE` to, either privileged role (all nine
`{member,usage,set}_of_{migrator,owner}` cells `false`); holds no
superuser, `CREATEROLE`, or `CREATEDB` authority.

**`rag_platform_migrator`**: an isolated, one-shot login; `NOINHERIT`
(confirmed via `rolinherit = false`, alongside the membership facts
above — `NOINHERIT` and holding `SET` capability are not in tension,
since `INHERIT`/`NOINHERIT` and the `SET` membership option are
independent facts, exactly why `usage_of_owner = false` while
`set_role_owner = true` is the row's own *expected*, correct shape, not
a contradiction); may explicitly assume `rag_platform_owner` only
through this exact, frozen, ADR-0035-established `member_of_owner =
true, set_role_owner = true, usage_of_owner = false` signature — **this
intentional reachability is allowlisted explicitly, by name, in this
matrix, never mistaken for an intrusion by the general "no unexpected
login reaches owner" rule below**, which is scoped to every login role
this matrix does not otherwise name; is not used as the connection
identity by any long-running service; receives no accidental
`rag_platform_app` membership of its own (`member_of_app = false`).

**`rag_platform_owner`**: `NOLOGIN` — never appears in the login-role
enumeration at all (checked before any other evaluation, above); owns
the protected function; reachable only through `rag_platform_migrator`'s
own explicitly allowlisted row.

**Named non-superuser bootstrap/deployment identity**: where the
environment uses a restricted bootstrap/deployment login that is
genuinely **not** a superuser (e.g. a role with `CREATEROLE`/`CREATEDB`
sufficient to provision the three protected roles and run migrations,
but no broader authority) — named explicitly, by role name, in this
environment's own copy of this matrix; `rolcanlogin = true`,
`rolsuper = false`, with `rolcreaterole`/`rolcreatedb` stated as the
environment's own exact, reviewed values (whichever combination that
bounded task genuinely requires — not assumed to be both, and not
assumed to be neither); every `MEMBER`/`USAGE`/`SET` path to all three
protected roles is `false`, and effective `EXECUTE` on the protected
function is `false` — this identity provisions the boundary, it does
not sit inside it. **A label containing "bootstrap" never, by itself,
justifies broader authority than this row states** — if the environment
grants this identity anything beyond its own bounded provisioning task,
that grant must be named and justified in the matrix like any other, not
assumed to be safe because of the role's own name. Used only for the
bounded bootstrap/deployment task; its credentials are absent from every
long-running API, queue-worker, scheduler, web, or Python container —
the same credential-isolation property ADR-0035 already requires for
`rag_platform_migrator`.

**Named cluster superuser**: where the environment genuinely has an
inspectable cluster superuser (the initial superuser most local Docker
images and many self-managed PostgreSQL deployments provision) — named
explicitly, by role name, in this environment's own copy of this
matrix; `rolsuper = true` is the **expected**, truthful value, not a
violation to detect. Its effective access to the protected function,
and its effective ability to assume or access every protected role, are
**acknowledged as `true`**, honestly, rather than pretended away —
catalog ACLs and role-membership boundaries do not, and cannot,
constrain a genuine superuser, and this matrix does not claim otherwise.
This is **not** treated as violating ordinary role isolation, because
superuser authority inherently, definitionally bypasses the boundary
this ADR builds for *ordinary* roles — its presence is accepted **only**
under this named, infrastructure-only exception. **`rolcreaterole` and
`rolcreatedb` are each compared against this role's own exact,
independently declared manifest value, never inferred from `rolsuper`
itself** — a superuser may legitimately carry either flag `true` or
`false` in either combination, and the audit fails closed on any
mismatch between the database's own observed value and the manifest's
own declared one for each attribute separately. Its credentials must be
proven absent from every long-running API, Laravel worker, scheduler,
web, Python, and end-to-end/runtime service — the SQL audit above cannot
itself observe this deployment/secrets-management fact, so credential
mounting and configuration isolation are verified separately, by the
deployment pipeline's own configuration review, not by a catalogue
query. Usage is limited to genuine infrastructure/bootstrap/break-glass
operations. **An additional or differently named superuser still fails
the gate closed** — naming and accepting exactly one specific superuser
identity is not a general license for any superuser to exist unnoticed.

**Managed-service environments**: where the environment's own manifest
states that no ordinary, customer-visible `rolsuper` role exists at all
(as is genuinely the case under several managed PostgreSQL offerings,
which withhold literal superuser status from any customer-reachable
identity) — **this matrix does not invent a superuser row that does not
exist.** Instead, the provider-managed administration role(s) that
**are** visible in that environment are classified explicitly by name,
their actual observed catalogue attributes (`rolsuper`, `rolcreaterole`,
`rolcreatedb`, `rolreplication`, and their own real membership/`EXECUTE`
reach) are recorded truthfully rather than assumed from a template, and
a named, reviewed allowlist row is retained for each. **Unexpected
powerful roles still fail closed** in this environment exactly as in
any other — a managed service withholding literal superuser status is
never read as "no login can ever be powerful here," only as "this
particular powerful-role shape does not apply."

**The isolation invariant this matrix actually states, corrected**:
**"No ordinary application or service login has privileged reachability;
the explicitly named infrastructure superuser (or provider-managed
administrative role) is inherently privileged but operationally
isolated."** It is emphatically **not**: "no login of any kind can ever
reach protected authority" — that claim is false the moment any
PostgreSQL cluster has a superuser at all, and this ADR does not assert
it.

**An unnamed additional login role carrying `rolsuper`, `rolcreaterole`,
`rolreplication`, or equivalent powerful authority fails the gate
outright**, in every environment category above — this matrix never
tolerates an unexplained powerful role, bootstrap, superuser, or
otherwise.

**Every other login role**: no direct or transitive membership in, no
`USAGE` of, and no `SET ROLE` path to, any of the three protected roles;
no effective `EXECUTE` on the protected function; no superuser or
role-management capability of any kind. **If a genuinely new,
legitimate application login must share `rag_platform_app`'s own
authority, it is named explicitly in this matrix, with its own
justification recorded alongside it** — this ADR never authorises a
wildcard "all service roles may reach `rag_platform_app`" shape, and
never will.

**Required tests, effective login-role membership and access audit** —
provider-free PostgreSQL integration tests, each constructing (or
confirming the absence of) a specific role-graph shape and then running
the audit query above, asserting it classifies that shape correctly:
the intended `rag_platform_app` row shows direct, effective `EXECUTE`
on the protected function; the intended `rag_platform_migrator` row
shows exactly `member_of_owner = true`, `set_role_owner = true`,
`usage_of_owner = false` (the frozen `SET ROLE`-without-`INHERIT`
signature); a fixture login role granted **direct** membership in
`rag_platform_app` is detected and fails the gate; a fixture login role
granted membership in an **intermediate** role that is itself granted
membership in `rag_platform_app` (a genuine two-hop chain) is detected
via `pg_has_role`'s own transitive evaluation, not merely a one-level
join; a fixture login role granted **direct** membership in
`rag_platform_owner` is detected; the equivalent **transitive**
login→intermediate→owner chain is detected; an **unexpected** additional
membership granted to `rag_platform_migrator` (beyond its own allowlisted
row) is detected; a fixture role granted membership `WITH INHERIT
FALSE, SET TRUE` (a genuine `NOINHERIT` role that can nonetheless `SET
ROLE`) is correctly reported `usage_of_* = false` **and**
`set_role_* = true` simultaneously, never conflating the two; a fixture
role granted membership `WITH INHERIT TRUE` into `rag_platform_app` is
detected as inheriting the function's `EXECUTE` automatically, without
ever issuing `SET ROLE`, confirming `usage_of_app`/`effective_execute`
both correctly reflect inherited access; the same automatic-inheritance
detection is exercised through a genuine multi-hop chain, not only a
direct grant; an **unnamed** fixture login role with `rolsuper = true`
is detected and fails the gate regardless of any membership facts; an
**unnamed** fixture login role with `rolcreaterole = true` is detected
and fails the gate identically; the environment's own **named**
non-superuser bootstrap/deployment identity is confirmed present with
`rolsuper = false` and every membership/`USAGE`/`SET`/`effective_execute`
cell `false` exactly as its own matrix row requires, and is asserted to
fail the gate if it is ever found `rolsuper = true` instead (a category
violation) or holding any authority beyond its own bounded provisioning
task; the environment's own **named** cluster superuser is confirmed
present with `rolsuper = true` and every membership/`USAGE`/`SET`/
`effective_execute` cell truthfully `true`, and does **not** itself trip
the "unexpected powerful login" failure, since it is named and justified
in the matrix as its own distinct category — while a fixture asserting
this named superuser's credentials are configured into any long-running
service (API, Laravel worker, scheduler, web, Python, or end-to-end
runtime) fails a separate, deployment-configuration-level check; a
fixture where the environment manifest declares an expected named
superuser that is then absent from the cluster, or where a superuser
exists contrary to what the manifest declares, fails the gate; **a
fixture named cluster superuser with `rolcreaterole = true` and
`rolcreatedb = true` (matching a manifest declaring both `true`) passes
cleanly**; **the same superuser identity with both flags `false`
(matching a manifest declaring both `false`) passes identically**;
**mixed combinations — `rolcreaterole = true`/`rolcreatedb = false` and
the reverse — each pass when they match their own manifest's own
independently declared values for each attribute**; **a manifest
mismatch on `rolcreaterole` alone (database `true`, manifest declares
`false`, or vice versa) fails the gate**, and **the equivalent mismatch
on `rolcreatedb` alone fails identically** — each attribute checked, and
each capable of failing, independently of the other; **in every one of
the above combinations, the superuser's own effective protected-role
reachability and `effective_execute` remain truthfully recognised as
`true`, unaffected by whatever `rolcreaterole`/`rolcreatedb` happen to
be** — confirming the matrix's own `‡`-footnoted independence claim
holds in the executable audit, not only in prose; **an unexpected
additional superuser (a second, unnamed `rolsuper = true` login) still
fails the gate closed** regardless of its own `rolcreaterole`/
`rolcreatedb` values, exactly as the single named superuser row's own
uniqueness already requires; a genuine PostgreSQL integration test proves PostgreSQL itself rejects a
circular grant — create role A, create role B, `GRANT A TO B`, then
attempt `GRANT B TO A`, asserting the second `GRANT` fails against the
actual supported error behaviour (checking that the statement is
rejected, without depending unnecessarily on brittle exact error-message
wording), and that the role audit above remains fully functional
afterward once the rejected statement's own transaction is cleaned up —
proving this is a database-enforced impossibility, never a state this
ADR merely hopes does not occur; separately, a **synthetic** traversal
test exercises the recursive path-building query's own cycle guard
directly, against a synthetic edge relation or an isolated query fixture
deliberately containing a cycle (never against genuine `pg_auth_members`,
which cannot hold one), asserting the guard terminates and reports the
path safely, as pure defensive-diagnostics coverage never presented as
evidence about real database state; a fixture in which
`rag_platform_app`'s own expected `EXECUTE` grant has been removed is
asserted to fail the gate as a **missing** expected permission, not
merely as an absence the audit silently tolerates; and the correct,
complete, unmodified role graph — every login role classified into
exactly one matrix row, with every cell matching its expected value —
passes the entire audit cleanly (infrastructure/configuration, security
regression, database integration test using genuine PostgreSQL with
real role/membership fixtures, run independently of the CI deployment
gate).

#### `SECURITY DEFINER` function verification, corrected and new

**Withdrawn: asserting `aclexplode(p.proacl)` produces "exactly one
row," `('rag_platform_app', 'EXECUTE')`.** Codex correctly identified
that this assumption does not hold against genuine PostgreSQL ACL
behaviour: the moment any explicit `REVOKE`/`GRANT` touches an object's
ACL, PostgreSQL typically also materialises an explicit entry for the
**owner** alongside it, `PUBLIC` may itself appear as an explicit,
zero-privilege or non-zero-privilege row (represented by grantee OID
`0`, not absence), and a `NULL` `proacl` (an object whose privileges were
never touched at all) means the **implicit default** ACL applies, not
"no privileges" — none of this is expressible as a fixed one-row
expectation. **Corrected: resolve the function by exact identity first,
normalise its ACL to always be explorable, and classify every row before
asserting anything about it:**

**Exact function identity, resolved once, used throughout:**

```sql
SELECT to_regprocedure(
  'public.apply_document_family_owner_change(bigint)'
) AS resolved_oid;
-- fails closed if resolved_oid IS NULL: the intended signature does not
-- exist at all (wrong schema, wrong name, wrong argument type/count, or
-- never created)
```

Every subsequent check below uses this `resolved_oid`, never a bare
name/argument-list `WHERE` clause repeated ad hoc — a single resolution,
reused for owner, `prosecdef`, `proconfig`, and ACL inspection alike.

**ACL normalisation and classification, corrected to an executable
shape:**

```sql
WITH fn AS (
  SELECT p.oid, p.proowner, p.prosecdef, p.proconfig,
         COALESCE(p.proacl, acldefault('f', p.proowner)) AS effective_acl
  FROM pg_proc p
  WHERE p.oid = to_regprocedure('public.apply_document_family_owner_change(bigint)')
),
acl_rows AS (
  SELECT
    fn.proowner,
    (aclexplode(fn.effective_acl)).grantee      AS grantee,
    (aclexplode(fn.effective_acl)).privilege_type AS privilege_type,
    (aclexplode(fn.effective_acl)).grantor      AS grantor,
    (aclexplode(fn.effective_acl)).is_grantable AS is_grantable
  FROM fn
)
SELECT
  CASE
    WHEN grantee = 0                              THEN 'PUBLIC'
    WHEN grantee = proowner                        THEN 'OWNER'
    WHEN grantee = 'rag_platform_app'::regrole     THEN 'RUNTIME'
    ELSE 'OTHER:' || grantee::regrole::text
  END AS classification,
  privilege_type,
  grantor::regrole::text AS grantor,
  is_grantable
FROM acl_rows;
```

`COALESCE(p.proacl, acldefault('f', p.proowner))` means the query never
special-cases a `NULL` ACL as "nothing to check" — a function whose
privileges were never explicitly touched is evaluated against
PostgreSQL's own real default for a function object (`'f'`) owned by
`p.proowner`, which is `EXECUTE` to `PUBLIC` plus every privilege to the
owner — so an accidentally-never-revoked-from-default function fails the
"no `PUBLIC EXECUTE`" assertion below exactly as a function whose ACL
was explicitly, incorrectly re-widened would, rather than silently
passing because there was "no ACL to inspect."

**Required assertions, evaluated against the classified row set above —
never against a fixed row count:**

- **No `PUBLIC` `EXECUTE`**: no row with `classification = 'PUBLIC' AND
  privilege_type = 'EXECUTE'`.
- **Runtime has explicit `EXECUTE`**: exactly one row with
  `classification = 'RUNTIME' AND privilege_type = 'EXECUTE'`.
- **No unexpected non-owner grantee has `EXECUTE`**: no row with
  `classification LIKE 'OTHER:%' AND privilege_type = 'EXECUTE'`.
- **The owner's own entry, whether explicit (materialised alongside the
  runtime grant, as PostgreSQL ordinarily does), defaulted (via
  `acldefault`), or purely inherent (ownership itself, independent of
  any ACL row at all), is never counted toward, or confused with, an
  "unexpected runtime grant"** — the classification bucket `'OWNER'` is
  deliberately separated from `'OTHER:%'` for exactly this reason, and
  the owner's own row is never asserted against the "no unexpected
  grantee" rule above.
- **Grant option absent for runtime**: the `'RUNTIME'`/`EXECUTE` row's
  own `is_grantable` must be `false` — `rag_platform_app` is never given
  the ability to re-grant `EXECUTE` to anything else, which was never
  required and is not deliberately introduced here.
- **Grantor is the expected owner/privileged identity**: the
  `'RUNTIME'`/`EXECUTE` row's own `grantor` must equal
  `'rag_platform_owner'` — the grant was issued by the object's own
  owner (directly, or via `rag_platform_migrator`'s `SET ROLE`), never by
  some other role that happened to hold `GRANT OPTION`.
- **Inherited role membership does not give another login role an
  unintended executable path**: this is exactly the question "Effective
  login-role membership and access audit, corrected and complete" above
  answers in full, for every login role and by direct-or-transitive
  membership, `USAGE`, and `SET ROLE` alike — never merely a one-level
  `pg_auth_members` join scoped to `rag_platform_app` alone (the
  withdrawn shape that audit itself corrects). This ACL-level check and
  that broader audit are complementary: this one proves the function's
  own explicit ACL contains no unexpected grantee; that one proves no
  *other* role can reach `EXECUTE` on it through membership the ACL
  itself would never show at all.

**`has_function_privilege()` used to confirm effective reachability,
never in place of the raw ACL inspection above**:

```sql
SELECT has_function_privilege(
  'rag_platform_app',
  'public.apply_document_family_owner_change(bigint)',
  'EXECUTE'
); -- expected: true

SELECT has_function_privilege(
  'PUBLIC',
  'public.apply_document_family_owner_change(bigint)',
  'EXECUTE'
); -- expected: false
```

`has_function_privilege` answers "can this one named role do this,"
correctly folding in role inheritance — useful for confirming
`rag_platform_app`'s own effective access and `PUBLIC`'s own effective
lack of it — but it can never answer "list every role that can," so it
is never relied on alone for the "no unexpected grantee" assertion,
which the classified `aclexplode()` row set above remains authoritative
for.

#### Separate overload check, new

**A signature-filtered resolution (`to_regprocedure(...)`, above) proves
the intended overload exists; it cannot, by itself, prove no *other*
overload exists under the same name.** A second, independent,
name-only query closes this:

```sql
SELECT p.oid, pg_get_function_identity_arguments(p.oid) AS arguments
FROM pg_proc p
JOIN pg_namespace n ON n.oid = p.pronamespace
WHERE n.nspname = 'public'
  AND p.proname = 'apply_document_family_owner_change';
-- expected: exactly one row, and its oid equals the resolved_oid from
-- to_regprocedure('public.apply_document_family_owner_change(bigint)')
```

- **Exactly one function with this name exists in the intended
  (`public`) schema**, and its `oid` is the same one the exact-signature
  resolution above already found — proving the one function that exists
  *is* the expected-signature one, not a differently-signed function
  merely sharing a name that the earlier, signature-filtered query would
  have silently ignored.
- **No zero-argument, text/UUID, variadic, or broader overload exists**
  under this name in `public` — any second row from the query above
  fails the check outright, regardless of that row's own argument list.
- **A same-named function in an unrelated schema is not counted as an
  overload of this one** — the query above is scoped to `n.nspname =
  'public'` deliberately, and a function of the same name living in, say,
  a test-fixture schema is a separate object with no bearing on this
  count. **It must still never become reachable through an unsafe
  runtime search path**: this is a property of how the Action itself
  invokes the function — the Action calls it by its fully schema-qualified
  name, `public.apply_document_family_owner_change(...)`, always, never
  an unqualified `apply_document_family_owner_change(...)` that would
  resolve according to `rag_platform_app`'s own `search_path` at call
  time — distinct from, and in addition to, the function's own internal
  `SET search_path = ''`, which governs only what the function's *body*
  resolves once already invoked, not what object the *caller's* own
  unqualified reference would have found.
- **The function's own empty `search_path` and fully-qualified internal
  references remain verified exactly as already established** — this
  overload check is additive, not a replacement for that assertion.

#### Final deployment-gate ordering, stated explicitly

**This complete verification — table-level, column-level, the complete
effective login-role membership/access audit, `SECURITY DEFINER`
function, overload, and the `document_governance_commands` insert-time
trigger/immutability boundary — runs, and must pass, strictly after**:
(1) every PostgreSQL role's own creation and every membership `GRANT`
between roles; (2) ADR-0035's own general baseline/default-privilege
sweep; (3) the protected function's own creation and its explicit ACL
setup (`REVOKE`/`GRANT EXECUTE`); (4) every `document_families`-specific
and `document_governance_commands`-specific `REVOKE`/`GRANT` statement
and every other application migration/grant reconciliation step. It runs
**as a CI/deployment acceptance gate** (the migration sequence is not
considered successfully applied, and deployment does not proceed, until
this passes) **and again, independently, as a provider-free security
regression test** in the ordinary test suite — the same assertions, run
twice, in two different enforcement contexts, so a regression is caught
whether it is introduced by a deployment-time ordering mistake or by a
later code change to the migrations or role provisioning themselves.

**Failure output may contain role names and membership paths in
deployment-facing diagnostics** (the CI log, the security regression
test's own failure message) — this is expected and useful, since a
human resolving a failed gate needs to see exactly which login role
reached which protected role and by what path — **but this same detail
must never be exposed through any tenant-facing or browser-facing API**;
this is an internal, infrastructure-owned diagnostic, never a response
this decomposition's own tenant-safe concealment discipline governs, and
the two must never be conflated.

**Fails closed for every one of**: forbidden table-level `UPDATE` on
`document_families`; effective `UPDATE` reachable on either protected
column; an unexpected column-level grant outside the allowlist;
`rag_platform_app` holding **any** `UPDATE` privilege at all on
`document_governance_commands`; the `BEFORE INSERT` target-shape trigger
missing, disabled, or not owned by `rag_platform_owner`; `rag_platform_
owner` appearing in the login-role enumeration at all; **any
non-allowlisted login role reaching `rag_platform_app`, `rag_platform_
migrator`, or `rag_platform_owner`** by `MEMBER`, `USAGE`, or `SET`
— checked individually, since any one of the three being true for an
unlisted role is itself a failure; **any unintended login role able to
`SET ROLE` to a protected role**; **any unintended login role holding
effective `EXECUTE`** on the protected function; **any unexpected login
role carrying `rolsuper`, `rolcreaterole`, `rolcreatedb`, or
`rolreplication`** not
named and justified in the environment's own allowlist matrix; the
expected `rag_platform_app` or `rag_platform_migrator` authority itself
being **missing** (the matrix's own required cells evaluating `false`
where they must be `true`); role membership or membership-option
attributes differing from the matrix's exact intended shape in either
direction; the audit query itself being unable to classify a login role
against the matrix at all (an unrecognised shape is a failure, never a
silent pass); **an unnamed superuser existing at all**; **a named
superuser whose credentials are found supplied to any long-running API,
Laravel worker, scheduler, web, Python, or end-to-end/runtime service**;
**the environment manifest's own expected named superuser being absent,
or a superuser existing contrary to what the manifest declares**; **the
named non-superuser bootstrap/deployment identity unexpectedly showing
`rolsuper = true`** (a genuine category violation — that role belongs in
the superuser row, or the environment's own manifest is wrong); **that
same restricted bootstrap identity showing any membership, `USAGE`,
`SET`, or effective `EXECUTE` authority beyond its own bounded
provisioning task**; **the named cluster superuser's own observed
`rolcreaterole` or `rolcreatedb` differing from the environment
manifest's own independently declared exact value for that attribute**
— checked separately for each of the two attributes, since either alone
mismatching is its own failure, and neither is inferred from `rolsuper`
or from the other; `to_regprocedure(...)` resolving to `NULL` for the expected
function signature (the function is missing, misnamed, or has the wrong
argument list); a wrong function owner; `prosecdef = false`; a missing
or unsafe function `search_path`; `PUBLIC EXECUTE` present on the
function (explicit or via an unrevoked default); the runtime `EXECUTE`
grant on the function missing entirely; an unexpected non-owner grantee
holding `EXECUTE`; `GRANT OPTION` present on the runtime's own `EXECUTE`
grant; a grantor other than `rag_platform_owner` on the runtime's own
grant; and an unexpected overload or broader callable signature for the
same function name in the `public` schema.

#### Ongoing protection against future privilege drift

- **A protected-table allowlist**, maintained alongside this
  verification step, names `document_families` (and any future table
  this decomposition similarly protects) explicitly, so a future,
  generic "ensure every table has the standard runtime grant set"
  maintenance task can consult it and skip, or specially handle, any
  listed table — rather than relying on every future maintainer to
  remember this table's own exception by convention.
- **Deployment/CI verification checks effective privileges after the
  full migration sequence**, including the general baseline's own
  default-grant application, never only the text of the one migration
  that introduces the protection — closing exactly the gap where a
  correct migration file could still be defeated by an out-of-order or
  re-run baseline step.

**Required tests, privilege reconciliation** — each run against genuine
PostgreSQL catalogue state, after applying the complete migration
sequence (never a single migration in isolation): `rag_platform_app`
holds **no** table-level `UPDATE` privilege on `document_families` after
the full sequence, including the general baseline's own default-grant
application; `rag_platform_app` can successfully update every intended
allowlisted metadata column (`name`, `description`, `category`, `tags`,
`review_due_date`, and each other reviewed column); a direct
`rag_platform_app` `UPDATE` naming only `owner_user_id` is rejected; a
direct `rag_platform_app` `UPDATE` naming only `owner_assignment_
generation` is rejected; a direct `rag_platform_app` `UPDATE` naming
both together (the paired shape the withdrawn trigger-only design once
treated as sufficient authority) is rejected identically; a **regression**
test that, inside an isolated test transaction, deliberately issues
`GRANT UPDATE ON document_families TO rag_platform_app` (the exact
table-level shape this section withdraws) and then runs the
reconciliation/verification query, asserting it **detects** the
resulting unexpected table-level grant; a follow-up assertion that
running this ADR's own table-specific `REVOKE`-then-column-`GRANT`
sequence again, idempotently, restores the intended final state,
verified by re-running the same query; a schema-migration test
adding a new, hypothetical mutable metadata column without an
accompanying explicit column-level `GRANT`, asserting the new column
remains non-writable by `rag_platform_app` until a reviewed grant is
added; and a test asserting the complete, correct final state passes the
full verification query set with no failures, as the ordinary "green"
case every other test above is implicitly contrasted against.

**Required tests, `SECURITY DEFINER` function verification** — each a
deliberate, isolated-transaction mutation of one aspect of the function's
own catalogue state, followed by the verification query, asserting
detection: the function's owner temporarily reassigned away from
`rag_platform_owner` is detected as a wrong-owner failure; `prosecdef`
temporarily false (a non-`SECURITY DEFINER` variant recreated in a test
fixture) is detected; a `search_path` other than the empty safe value is
detected; a temporary `GRANT EXECUTE ... TO PUBLIC` is detected as
restored `PUBLIC` access; a temporary `REVOKE EXECUTE ... FROM
rag_platform_app` is detected as the runtime grant missing; a temporary
`GRANT EXECUTE` to an unrelated role is detected as unexpected `EXECUTE`;
a temporary second function overload under the same name (e.g. accepting
an additional parameter) is detected as an unexpected broader signature;
and the correct, unmodified final state passes every one of these checks
cleanly (infrastructure/configuration, Laravel feature/API where a
database-level assertion can be driven from a feature test, security
regression run independently of the CI deployment gate).

**Required tests, ACL normalisation and overload resolution** —
corrected this pass, exercising the classification logic directly rather
than assuming a fixed row shape: the function's genuine, correctly
configured ACL (which legitimately contains **both** an explicit owner
entry **and** the runtime `rag_platform_app` `EXECUTE` entry together)
passes the verification cleanly, proving the check tolerates more than
one ACL row rather than requiring exactly one; a `PUBLIC` grantee is
correctly recognised via grantee OID `0` specifically (not by name
matching, which `regrole` casting alone would not guarantee against a
role literally named `"PUBLIC"`); a temporarily restored `PUBLIC
EXECUTE` is detected and rejected; an unexpected third-party grantee
(a role with no legitimate reason to hold `EXECUTE`) is detected and
rejected, while the owner's own entry in the same ACL is correctly
**not** flagged by the same check; a runtime `EXECUTE` grant temporarily
recreated `WITH GRANT OPTION` is detected and rejected; `to_regprocedure`
resolving to `NULL` (the intended signature genuinely absent — wrong
schema, wrong name, or wrong argument list) is detected as a missing-
signature failure, distinct from an overload failure; a deliberately
introduced extra overload is detected by the separate, name-only overload
query, independent of whether the exact-signature resolution itself
still succeeds; a same-named function created in an unrelated schema is
asserted to **not** affect the overload count for `public` and to remain
unreachable through an unqualified call from the Action (which always
uses the fully schema-qualified name); and the correct, complete,
unmodified final function definition passes every ACL/overload check
cleanly (infrastructure/configuration, Laravel feature/API, database
integration test using genuine PostgreSQL).

**`apply_document_family_owner_change(p_command_id bigint)`** — a new,
narrowly scoped, owner-owned function (illustrative name; final naming
may follow repository convention), satisfying the same four independent
`SECURITY DEFINER` requirements ADR-0035 already establishes for its own
protected-column function, **extended by the one property a trigger-only
function never needed, because this is this decomposition's first
directly-invoked (non-trigger) `SECURITY DEFINER` function**:

- `SECURITY DEFINER`, owned by `rag_platform_owner`.
- `SET search_path = ''`, with every referenced table and function
  fully schema-qualified (`public.document_families`,
  `public.document_governance_commands`, etc.) — never a bare,
  search-path-dependent identifier.
- `EXECUTE` revoked from `PUBLIC` (both by the existing baseline sweep
  for pre-existing functions and by the existing `ALTER DEFAULT
  PRIVILEGES ... REVOKE EXECUTE ON FUNCTIONS FROM PUBLIC` rule for any
  future one) — **and then, unlike every trigger-only `SECURITY DEFINER`
  function in this decomposition, `EXECUTE` is explicitly granted to
  `rag_platform_app`**, because direct runtime invocation is exactly
  what this function is for. No owner or migrator credential is ever
  available to any long-running service — `rag_platform_app` calls this
  function precisely because it cannot do the mutation itself.

**The function is not a generic "set owner" escape hatch — it takes only
a command identity, and binds every mutation to that command's own,
already-committed intent.** Internally, under lock:

1. Lock the command row: `SELECT ... FROM public.document_governance_commands
   WHERE id = p_command_id FOR UPDATE` — **the only lock ever taken on a
   command row in this entire algorithm.** The calling transaction (the
   Action, as `rag_platform_app`) holds no lock of its own to hand off —
   it cannot, having no `UPDATE` privilege on this table at all — so this
   is not a re-assertion of anything the caller already had; it is the
   first, and only, moment any command row is locked. Running as
   `rag_platform_owner` via `SECURITY DEFINER` is what makes this
   possible at all.
2. Verify, and fail closed (typed, distinct errors) on any mismatch: the
   row exists; `purpose = 'document_family.owner.change'`; `completed_at
   IS NULL` (a second invocation against an already-completed command is
   rejected, never silently re-executed or re-incremented);
   `request_digest IS NOT NULL` (the row was fully constructed by the
   atomic acquisition sequence above, never a partially-written row from
   any other path).
3. **Lock the target family — the second lock in this ADR's one
   consistent `command → family` order**: `SELECT ... FROM
   public.document_families WHERE id = commands.target_document_family_id
   FOR UPDATE`.
4. **Target-existence check, new — runs immediately after the lock
   attempt above, before the workspace check.** If
   `commands.target_document_family_id` is `NULL` (the family this
   command named was deleted after the command was created, per the
   column-targeted referential action above) or the `SELECT ... FOR
   UPDATE` locked zero rows, fail closed with a typed
   `owner_change_target_family_missing` error and **stop** — write no
   no-op result, no owner mutation, no generation change, no audit
   event, and no command completion. A `NULL` target cannot be
   meaningfully compared to anything in the workspace check that
   follows, so this must be checked first, not folded into it.
5. **Workspace-binding check — corrected to run immediately after the
   target-existence check and before every other family-state decision,
   including the no-op branch.** **Withdrawn: relying on the composite
   FK alone, verified only at `INSERT` time, as sufficient tenancy
   protection for the function's own later mutation.** Codex correctly
   identified that the composite FK protects the *command row's own
   creation*, but the function itself — the thing actually authorised to
   write `document_families` — must not assume its own caller passed a
   `p_command_id` for a row whose invariants still hold by the time this
   specific invocation runs; a defence-in-depth re-check belongs here for
   the same reason every other structural fact this function relies on
   (purpose, `completed_at`, digest presence) is re-verified under lock
   rather than merely trusted. **Corrected: explicitly compare
   `commands.workspace_id = family.workspace_id` here, under both locks,
   before any other decision is made.** If they are unequal, fail closed
   with a typed internal integrity/tenancy failure (e.g.
   `owner_change_workspace_mismatch`) and **stop** — write no no-op
   result, no owner mutation, no generation change, no audit event, and
   no command completion of any kind. This check runs **before** both of
   the next two steps, without exception: a workspace mismatch is never
   allowed to reach the goal-already-achieved/no-op branch (which would
   otherwise let a cross-workspace command "succeed" quietly, writing a
   completed, no-op-looking result for a family it was never entitled to
   even inspect) and never reaches the genuine-mutation branch either.
6. **Goal-already-achieved check** (only once the target-existence and
   workspace checks above have passed): if the family's **current**
   `owner_user_id` already
   equals `commands.intended_new_owner_user_id`, this is a no-op
   regardless of whether the command's own expected precondition still
   matches — the caller's actual goal (this owner, installed) already
   holds. Write the command's own `result` (current owner, current
   generation), set `completed_at`, and return — no generation
   increment, no audit event, no `document_families` write at all.
7. **Otherwise, verify the command's own precondition**: the family's
   current `owner_user_id`/`owner_assignment_generation` must equal
   `commands.expected_current_owner_user_id`/`expected_current_generation`
   exactly. **Structural compatibility of `intended_new_owner_user_id`
   is enforced by its own foreign key to `users`** — an invalid or
   nonexistent user id fails the mutation with an ordinary FK violation,
   never a silent acceptance. **Live eligibility (active membership
   and `disabled_at IS NULL`) is explicitly Laravel's own responsibility,
   revalidated immediately before the Action ever submits the command —
   the database function enforces the structural purpose/command/audit
   boundary, never a second, duplicated implementation of business
   eligibility rules that could drift out of sync with Laravel's own.**
   If the precondition does not match, fail closed with a typed
   `owner_change_precondition_stale` error — no mutation, no generation
   increment, no audit event; the caller must submit a **new** command
   with a fresh expected precondition reflecting current reality, never
   have this one silently retried against a state it never asked for.
8. **Otherwise — the precondition holds and the state genuinely
   changes**: write the new `owner_user_id`; **advance
   `owner_assignment_generation` by exactly one** (the same
   `DEFAULT`-then-explicit-write shape as creation, now performed as
   `rag_platform_owner`, for which the existing consistency trigger below
   still passes, as defence in depth, not as the authority); write one
   audit event carrying the before/after owner and the resulting
   generation; write the command's own `result` and `completed_at` — all
   in this one invocation, under both locks already held.
9. Return the final owner identity, the resulting generation, and
   whether the outcome was a no-op — to the calling Action, still inside
   the same outer transaction, which then commits.

**The existing consistency trigger, retained as defence in depth, not
redescribed as the authority boundary**: `enforce_document_family_owner_
generation()` (below) still rejects a paired owner/`+1` update that
somehow bypassed the function — a genuine backstop against a future
privilege-migration regression, exactly the same relationship ADR-0035's
own guard trigger has to its column-level privilege grants — but the
**column-level `UPDATE` revocation above is the load-bearing boundary**;
the trigger proves consistency, never authority, and this ADR no longer
describes it as anything more.

**Required tests, workspace binding** — provider-free, exercising both
the structural (composite FK) and runtime (function-level) layers
independently, since either alone is insufficient evidence the other
works: an `INSERT` into `document_governance_commands` naming a
`target_document_family_id` that belongs to a genuinely different
workspace than the row's own `workspace_id` is rejected by the composite
FK before any Action-level code runs; a direct attempt to `UPDATE`
`target_document_family_id` or `workspace_id` on an existing command row
is rejected (immutability, above); a **function-level defence** test
that constructs a malformed/legacy command row with a genuinely
mismatched `workspace_id`/family pair by temporarily bypassing the
composite FK in a controlled test fixture (e.g. disabling the constraint
for one fixture insert, or inserting directly against the underlying
table outside the FK's own enforcement path), then invoking
`apply_document_family_owner_change` against it directly and asserting
it independently rejects the row with `owner_change_workspace_mismatch`
— proving step 5 is genuine defence in depth, not merely trust in the
FK that supposedly makes the row unreachable; an assertion that the
workspace check (step 5) runs, and rejects, **before** the
goal-already-achieved/no-op branch (constructed so that, absent the
check, the mismatched row would otherwise present as a valid no-op);
a parallel assertion that it runs, and rejects, **before** the
genuine-mutation branch (constructed so that, absent the check, the
mismatched row would otherwise present as a valid owner change); a
same-workspace, otherwise-legitimate command asserted to still succeed
normally, confirming the new check adds no false rejection; and an
external-API-level test confirming a cross-workspace attempt surfaces
only the same generic, tenant-safe concealment response this
decomposition uses everywhere else, never a raw constraint-violation
message or any signal distinguishing "wrong workspace" from "family does
not exist" (Laravel unit, Laravel feature/API, security regression).

**Required tests, column-targeted family-deletion behaviour** —
provider-free, run against genuine PostgreSQL (never simulated in
application code, since the whole point is the database's own
referential-action semantics): deleting a family referenced by a command
row nulls **only** `target_document_family_id`, asserted directly by
re-reading the row after deletion; `workspace_id` on that same row
remains non-null and exactly its original value; `target_document_
family_public_id` remains exactly its original value, unaffected by the
deletion; the family deletion itself is **not** blocked or delayed by
the existence of any command row referencing it, pending or completed;
a **pending** command whose target has just been deleted is asserted to
fail closed with `owner_change_target_family_missing` when
`apply_document_family_owner_change` is invoked against it, producing no
no-op result, no mutation, no audit event, and no completion; a
previously **completed** command remains fully readable and intelligible
(workspace, target family by public identity, final result) after its
target family is later deleted; a cross-workspace `INSERT` attempt is
still rejected by the composite FK exactly as before this correction
(unaffected by the referential-action change); and the migration's own
raw DDL (`ON DELETE SET NULL (target_document_family_id)`) is asserted
to apply successfully against the actual supported PostgreSQL version
(`postgres:18.4-alpine`, per `compose.yaml`) in a genuine migration-test
run, not merely reviewed as SQL text (Laravel feature/API, database
integration test using genuine PostgreSQL).

#### Owner-change lock order and concurrency behaviour, traced

**One consistent lock order for every owner-change path, stated once**:
`command → family`. **Corrected this pass: the runtime's own
`INSERT ... ON CONFLICT` and its follow-up plain `SELECT` are not row
locks at all, and introduce no lock-order edge of their own** — the
Action cannot lock a command row (no `UPDATE` privilege), and does not
need to. `apply_document_family_owner_change` is the **sole** place any
command-or-family row is ever locked, and its own internal sequence
**is** the entire lock order:

1. Lock the command row (`SELECT ... FOR UPDATE` on
   `document_governance_commands`, by `id` — the function's own step 1).
2. Validate it (purpose, `completed_at`, digest presence — step 2).
3. Lock the family row (`SELECT ... FOR UPDATE` on `document_families`,
   by the locked command's own `target_document_family_id` — step 3).
4. Validate workspace binding and every precondition (steps 4–7).
5. Mutate and complete atomically (step 8), all under both locks still
   held.

**No owner-change path in this ADR ever acquires these two locks in the
reverse order**, and none ever could: since only one function ever locks
either row, there is no second, independently-written code path whose
own ordering could disagree with it.

**Traced explicitly, for every combination the brief names:**

- **Same key, same digest, sequential**: the second request's `INSERT`
  conflicts, finds the first's already-`completed_at` row, returns the
  identical stored result.
- **Same key, same digest, genuinely concurrent** (e.g. a client-side
  network retry racing its own original request): transaction A's
  `INSERT ... ON CONFLICT` proceeds and returns its own row's `id`;
  transaction B's own `INSERT ... ON CONFLICT` for the identical key
  **waits at the `INSERT` itself** (PostgreSQL's own conflict-resolution
  wait, never a `SELECT ... FOR UPDATE` — B holds no lock and needs
  none). Once A commits, B's `INSERT` resolves the now-visible conflict
  and returns no row; B falls through to its own plain, non-locking
  `SELECT`, finds `completed_at` set and a digest matching its own, and
  returns A's stored result. Exactly **one** mutation, one generation
  increment, one audit event, and one committed `result`, which both
  transactions observe identically — and at no point does B ever hold a
  row lock of any kind.
- **Same key, different digest, sequential or concurrent**: whichever
  transaction's `INSERT` actually commits first establishes the row's
  permanent `request_digest`; every other request for that key —
  whether it arrives after that commit or is blocked waiting for it —
  compares its own freshly computed digest against that stored one and
  fails `idempotency_key_conflict` if they differ, regardless of arrival
  order.
- **Different keys, same intended owner, against the same family**:
  each has its own row, so no `INSERT`/unique-constraint interaction
  between them at all; each proceeds independently to the family lock
  once its own command row is acquired. Whichever acquires the **family**
  lock first performs the change (or the goal-already-achieved no-op, if
  it turns out the other one already got there first and this one's
  precondition still happens to match current reality); the other either
  also succeeds as a no-op (its intended owner already matches current
  state) or fails `owner_change_precondition_stale` (its own
  precondition no longer matches) — never both blindly re-applying the
  same change twice.
- **Different keys, different intended owners, against the same family**:
  as in "Two concurrent, distinct owner-change commands" below — the
  family lock serialises them; whichever acquires it first succeeds if
  its precondition matches; the second observes the first's own
  committed state and either matches its own precondition (rare, but
  possible if the second's expectation happened to be the first's own
  result) or, far more commonly, fails `owner_change_precondition_stale`
  and must be resubmitted with a fresh precondition.
- **First transaction commits**: exactly as traced above in each case —
  a blocked second transaction's lock is granted immediately, and it
  observes fully committed, consistent state.
- **First transaction rolls back** (a crash, an exception, an explicit
  abort before commit): every write it made — the `INSERT`'s own row,
  any family mutation, any audit event — rolls back with it. A blocked
  second request proceeds against a database as if the first transaction
  never happened at all: if the second's own `INSERT` was waiting on the
  conflict (same key), it now proceeds as the genuine inserter itself
  (case "newly inserted command," above) — never a lock being "granted,"
  since none was held, only the `INSERT`'s own conflict wait resolving;
  if the second was instead blocked on the **family** lock inside the
  privileged function (different keys), it now observes the family's
  pre-first-transaction state, unaffected by the rolled-back attempt.
  **No durably visible partial state is left behind by a rollback** —
  this is exactly why "existing matching, incomplete command" (above) is
  structurally exceptional rather than an expected outcome of ordinary
  concurrent load.
- **Family lock acquisition after command lock**: the only order this
  ADR's algorithm ever produces — both locks taken inside the one
  privileged function, traced through every case above.
- **Interaction with the privilege-controlled function**: the function
  is invoked at most once per transaction, only for a command this
  transaction's own `INSERT ... RETURNING id` determined it genuinely
  created (the "newly inserted command" case) — a replay or a
  conflicting-digest request never calls it at all, so the function's own
  internal locking never needs to reason about idempotency routing; that
  is entirely the calling Action's own responsibility, decided before the
  function is ever reached, using nothing but ordinary `INSERT`/`SELECT`
  privilege.

**Named scenarios, answered explicitly:**

- **Initial family creation with an owner**: `owner_assignment_generation
  = 1`, received automatically from the column's own `DEFAULT 1` (per
  the protected-column privilege model below, `rag_platform_app`'s
  `INSERT` grant excludes this column entirely, so the family-creation
  Action's `INSERT` — ADR-0030's own, extended additively to also name
  `owner_user_id` — never names `owner_assignment_generation` and cannot
  supply any other starting value even if it tried) — **never** via
  `ChangeDocumentFamilyOwner`, which only ever mutates an *existing*
  family.
- **Initial family creation without an owner, if permitted**: **not
  permitted, verified directly against ADR-0030's own text** — "mandatory
  owner assignment at creation via a `restrictOnDelete()` foreign key to
  `users.id`." This ADR does not design behaviour for a case ADR-0030
  itself does not allow.
- **Assigning the first owner later**: not a distinct case — because
  owner is mandatory at creation (above), there is no "unowned" family
  state to assign into; the family's first-ever owner is always set at
  creation (generation `1`), and any subsequent change is an ordinary
  `ChangeDocumentFamilyOwner` command (scenario "changing owner A→B"
  below).
- **Clearing an owner, if permitted**: **not permitted** — verified
  against the same mandatory, `restrictOnDelete()`-backed assignment
  rule, and consistent with this ADR's own ownership-loss model, which
  always calls for **reassignment to a new eligible owner**, never for
  clearing to an unowned state; `ChangeDocumentFamilyOwner` therefore
  never accepts a null proposed owner.
- **Changing owner A→B**: the function's step 8 — `owner_user_id`
  becomes `B`, generation increments by exactly one, given a command
  whose `expected_current_owner_user_id = A` still matches reality at
  lock time (and whose target/workspace passed steps 4–5).
- **Later changing B→A**: a **new**, later, genuinely distinct command
  (a different `client_idempotency_key`, its own
  `expected_current_owner_user_id = B`, issued at a different time) —
  the function's step 8 again applies in full: `owner_user_id` becomes
  `A`, generation increments again, producing a value strictly greater
  than the generation `A`'s first assignment used — exactly the
  disambiguation `owner_assignment_generation` exists to provide.
- **Same-command replay**: the acquisition algorithm's own "existing
  completed command, matching digest" case — the stored `result` is
  returned unchanged; no mutation, no increment, no new audit event; the
  privileged function is never even called.
- **Same idempotency key, different digest**: the acquisition
  algorithm's own "existing command, different digest" case — typed
  `idempotency_key_conflict`, fails closed, before the family is ever
  locked and before the privileged function is ever called. Covered
  identically whether the two requests are sequential or genuinely
  concurrent (above).
- **Redundant new command targeting the current owner**: the function's
  own step 6, goal-already-achieved check (reached only once step 4's
  target-existence check and step 5's workspace-binding check have both
  passed) — an honest no-op result, no increment, no audit event,
  distinct from a same-command replay (this is a **new**, distinct
  idempotency key that merely happens to request a state that already
  holds).
- **A command whose live target family has been deleted since the
  command was created**: the function's own step 4 — fails closed with
  `owner_change_target_family_missing`, checked before the workspace
  comparison (a `NULL` target cannot be meaningfully compared), before
  the goal-already-achieved check, before the precondition check, and
  before any mutation (see "Column-targeted `ON DELETE SET NULL`,
  corrected" above).
- **A command whose `workspace_id` does not match its own target
  family's workspace**: the function's own step 5 — fails closed with
  `owner_change_workspace_mismatch` before the goal-already-achieved
  check, before the precondition check, and before any mutation; no
  no-op result, no audit event, and no command completion of any kind
  (see "Composite workspace binding, corrected" and "Function
  revalidation" above/below for the full structural and runtime
  treatment).
- **Two concurrent, distinct owner-change commands against the same
  family**: **corrected in the sixth pass from "both always succeed" to
  precondition-based, per the function's own step 7.** Each command
  carries its own caller-observed `expected_current_owner_user_id`/
  `expected_current_generation`. The `command → family` lock order
  serialises them — whichever transaction's function invocation acquires
  the family lock first evaluates its precondition against the current
  (as-yet-unchanged) state and, if it matches, proceeds (step 8) and
  commits. The second transaction's own function invocation then
  acquires the family lock, observes the **first** transaction's own
  committed result as "current," and compares it against its **own**
  expected precondition — which, if it was computed independently of the
  first request (a different admin's own stale view, or a genuinely
  different intended change), **now legitimately fails** the precondition
  check and is rejected with `owner_change_precondition_stale`, never
  silently applied against a state it never asked for and never silently
  retried by the function itself. A caller whose command fails this way
  must submit a **new** command with a freshly observed precondition —
  this is a deliberate optimistic-concurrency tightening this pass
  introduces, replacing the prior pass's "both commands are honoured"
  description, which did not account for a command's own precondition
  potentially being invalidated by the very concurrency it was trying to
  describe.
- **Unrelated family metadata edits** (title, description, category,
  tags, review-due date): **never** touch `owner_user_id` or
  `owner_assignment_generation` — enforced structurally by the
  column-level `UPDATE` revocation above, never merely by convention or
  by the existing consistency trigger alone. An unrelated metadata Action
  has no reason to, and no way to, invoke
  `apply_document_family_owner_change` on a family's behalf without
  first legitimately creating (or already holding) a real
  `document_family.owner.change` command row naming that exact family —
  the function's own internal `purpose` check rejects anything else, and
  an unrelated Action never constructs such a row.

**Migration and backfill:**

- **`UNIQUE (id, workspace_id)` on `document_families` is added in the
  same additive migration that introduces `owner_assignment_generation`**
  (or an earlier one, provided it exists before `DocumentGovernanceCommand`
  and its composite FK are created) — a schema-only addition requiring no
  data backfill of its own (`id` and `workspace_id` are both already
  populated and immutable on every existing row), applied under the same
  migrator/owner authority as every other structural change to this
  table.
- **Existing family rows receive `owner_assignment_generation = 1`
  deterministically**, in the same additive migration that adds the
  column (per the schema section above). **This backfill runs under the
  isolated migrator/owner authority** — connect as `rag_platform_migrator`,
  `SET ROLE rag_platform_owner`, apply the backfill `UPDATE`, exactly the
  same bootstrap/migration flow ADR-0035 already establishes — **never**
  by granting `rag_platform_app` a runtime `UPDATE` on either protected
  column, even temporarily, even for this one-shot operation.
- **Backfill is idempotent**: a rerun finds the column already populated
  on every row and writes nothing further.
- **Backfill alone emits no owner-loss event of any kind** — it is a
  schema-completeness operation, never a domain occurrence.
- **New rows receive `owner_assignment_generation = 1`** at creation, via
  the column's own `DEFAULT 1` under the `INSERT`-column-exclusion
  privilege above — consistent with the verified, mandatory
  owner-at-creation rule above, and requiring no runtime grant of any
  kind on the protected pair.
- **Database `CHECK (owner_assignment_generation > 0)`** enforces the
  valid positive range at the database level, not merely in application
  code.
- **`enforce_document_family_owner_generation()`, a `BEFORE UPDATE`
  trigger, retained as defence in depth, corrected to no longer be
  described as the authority boundary**: it still rejects an `UPDATE`
  where `NEW.owner_assignment_generation <> OLD.owner_assignment_
  generation` unless `NEW.owner_user_id <> OLD.owner_user_id` **and**
  `NEW.owner_assignment_generation = OLD.owner_assignment_generation + 1`
  exactly — a genuine backstop against a future privilege-migration
  regression, the same cross-row-transition trigger pattern this
  decomposition already uses elsewhere (e.g. `BulkOperationItem`'s own
  incorporation marker, ADR-0035) — **but the column-level `UPDATE`
  revocation in "Protected-column privilege model" above, not this
  trigger, is what actually prevents `rag_platform_app` from reaching
  either column at all**, structurally, before the trigger would ever
  need to fire against a runtime-issued statement.
- **Unrelated metadata Actions cannot call
  `apply_document_family_owner_change` under another purpose** — the
  function is not parameterised by purpose at all (only by a command
  identity it independently verifies is `document_family.owner.change`,
  above), so there is no "other purpose" argument such an Action could
  even supply; it simply has no path to this function.

**Provider-free tests required**: backfill determinism/idempotency and
no-event-on-backfill, run under migrator/owner authority; family
creation with an owner (generation `1` via `DEFAULT`, never explicitly
supplied); the unreachable without-owner and clear-owner cases asserted
rejected at the database/Action level; first assignment being simply
generation `1` at creation, not a distinct code path; the full `A → B →
A` cycle producing three strictly increasing, non-colliding generations;
same-command replay returning the identical stored result with zero
mutation and no function invocation; same-key/different-digest failing
closed as `idempotency_key_conflict` before any lock is taken, including
under genuine concurrency (whichever request's `INSERT` wins establishes
the bound digest; the other fails against it); a redundant new command
against the current owner producing an honest no-op result with no
increment and no audit event, asserted distinct from a same-command
replay; an unrelated metadata edit (e.g. category change) leaving
`owner_user_id`/`owner_assignment_generation` byte-for-byte unchanged; a
**direct** attempt by `rag_platform_app` to `UPDATE` either protected
column — owner-only, generation-only, and the paired owner/`+1` shape —
each rejected at the privilege level, never reaching the trigger at all;
a session-setting/GUC fabrication attempt (e.g. asserting a false
"current owner" via custom session state) confirmed to have no bearing
on the function's own re-verification, which reads only committed table
state under lock; the privileged function invoked with a nonexistent
`p_command_id` rejected; invoked against a command of the wrong
`purpose` rejected; invoked against a command whose
`target_document_family_id`/`workspace_id` mismatches the caller's own
context rejected; **the "wrong intended owner" case is structurally
unreachable by design, asserted as such** — the function takes only
`p_command_id`, never a separately supplied intended-owner parameter, so
there is no argument through which a caller could request one owner
while a differently-targeted command is honoured; the test instead
asserts the written result always equals the command row's own
`intended_new_owner_user_id`, never a value derived from anything else;
invoked with a stale `expected_current_owner_user_id`/
`expected_current_generation` rejected as
`owner_change_precondition_stale`; a legitimate, precondition-matching
command succeeding, with the owner write, generation increment, audit
event, and command `result`/`completed_at` all asserted to commit
together in one transaction (and to all roll back together on a forced
failure); two genuinely concurrent distinct owner commands against the
same family, one succeeding and one failing
`owner_change_precondition_stale`, asserted under real concurrent
execution, not merely sequential test calls; and a direct assertion that
`rag_platform_app`'s own database session cannot execute `SET ROLE
rag_platform_owner` or otherwise assume owner/migrator authority
(Laravel unit, Laravel feature/API, infrastructure/configuration).

**Concurrency-specific tests, required in addition to the above**: a
concurrent same-key/same-digest pair (two connections, one request each,
released together) produces exactly one mutation, one generation
increment, one audit event, and one committed `result`, observed
identically by both callers; a concurrent same-key/different-digest pair
produces exactly one winner and one typed `idempotency_key_conflict`,
never two mutations and never two conflicts; a forced rollback of the
transaction that owns a freshly inserted, not-yet-completed command row
is asserted to leave **no** row behind at all, so the next request for
that same key cleanly re-inserts and proceeds as a genuine new
acquisition, never encountering a stale incomplete row; two distinct
keys against the same family are asserted to serialise on the family
lock (one blocks until the other's transaction ends) rather than
interleaving; the redundant-second-command-under-the-now-current-owner
no-op (above) is exercised specifically under concurrency, not only
sequentially; a manually constructed durably-committed incomplete
command row (simulating the structurally-exceptional condition directly,
since no normal path produces one) is asserted to make the Action fail
closed with `owner_change_command_incomplete` rather than re-executing
the mutation; and a direct concurrent-`INSERT` race against the unique
constraint is asserted to always surface as one of this algorithm's own
typed outcomes — a successful acquisition, a replay, or a typed conflict
— **never** as a raw, unhandled unique-constraint-violation database
error reaching the caller (Laravel feature/API, database-level
concurrency test using genuinely separate connections).

#### Legacy ownership-eligibility sweep — a deliberately different
#### identity construction, and why

**A pre-existing ineligible-owner condition has no real causal audit
event to bind to — it predates this ADR's own producer by definition —
so it deliberately uses a different, but equally honest, identity
construction, stated explicitly rather than left to look like the same
rule as above.**

**Withdrawn: a digest over the bare value `(document_family_id,
owner_user_id)`.** Codex correctly identified this cannot distinguish
the case the brief's own worked example makes concrete: Alex owns the
family → loses eligibility (surfaced, digest `D(family, Alex)`) →
reassigned to Bob → Bob is later replaced, and Alex is reassigned again
→ Alex loses eligibility a **second** time. Because `owner_user_id`'s
*value* is `Alex` on both occasions, the digest is **identical** both
times — the second, genuinely new loss would silently collide with, and
be suppressed by, the first. A bare value cannot encode "which specific
assignment act this was," only "who currently holds it."

**Corrected: an immutable owner-assignment generation, extending
ADR-0030 with a new technical-lineage column, never changing its
settled owner semantics.**

- **`DocumentFamily.owner_assignment_generation`** — non-null, positive
  integer. This is purely **technical lineage**: it carries no authority,
  no eligibility meaning, and no metadata semantics of its own — it does
  not change who ADR-0030 considers the recorded owner, how eligibility
  is computed, or any other settled owner rule. It exists solely so this
  ADR can name "which specific assignment act" produced a given
  `owner_user_id` value.
- **Every actual owner assignment or reassignment goes exclusively
  through `ChangeDocumentFamilyOwner`** — a new Action this ADR itself
  defines in full, immediately below ("Owner-change command and
  idempotency, corrected") — **increments the generation by exactly one,
  atomically, while the family row is locked**, in the same transaction
  as the ownership write itself, following the identical lock-then-
  mutate discipline this ADR already establishes everywhere else. This
  is new work, not an extension of any pre-existing Action: no owner-
  reassignment Action, idempotency table, or `document_families`
  migration exists anywhere in the repository today, verified directly.
- **Assigning the same person again after an intervening owner still
  increments** — the generation counts *assignment acts*, never *distinct
  people*, closing the brief's own worked example precisely: Alex's first
  assignment is generation `G1`; after Bob, Alex's second assignment is
  generation `G3` (or whichever count follows), genuinely distinct from
  `G1` even though `owner_user_id` reads `Alex` both times.
- **An idempotent replay of the same owner-change command does not
  increment again** — `ChangeDocumentFamilyOwner`'s own
  `DocumentGovernanceCommand` idempotency record (below), reusing
  ADR-0031's own governance-idempotency *pattern*, never a second,
  competing shape, governs this: a replay detected as the *same* command
  returns the *already-computed* result, including the generation value
  already assigned, never a fresh increment for a request that was never
  actually new.
- **Changing unrelated family metadata (title, category, tags, review
  date) never touches this column** — only `ChangeDocumentFamilyOwner`
  itself ever writes to it, enforced structurally by a dedicated trigger,
  never merely by convention (below).
- **Clearing an owner is not permitted** — verified directly against
  ADR-0030's own "mandatory owner assignment... via a `restrictOnDelete()`
  foreign key" rule; `ChangeDocumentFamilyOwner` never accepts, and this
  column never represents, an unassigned state.
- **Audit evidence records the before/after owner and generation
  together** — `ChangeDocumentFamilyOwner`'s own new audit write (below)
  carries the generation value alongside the before/after owner, as one
  mechanism, never a second, separate one.
- **Backfill for existing rows**: a deterministic, idempotent migration
  sets `owner_assignment_generation = 1` for every existing family —
  idempotent because a rerun finds the column already populated and
  writes nothing further; **no event of any kind is emitted merely
  because this backfill ran** — the backfill is a schema-completeness
  operation, never itself a domain occurrence.

**The legacy sweep's own `eligibility_loss_cause_identity` is now**:

```
(document_family_public_id, owner_assignment_generation,
 affected_owner_user_public_id)
```

— read from the family's own **current** `owner_assignment_generation`
at the moment the sweep observes the ineligible condition (which, for a
row discovered before any reassignment has ever happened since backfill,
is simply `1`). Because the generation strictly increases on every
genuine reassignment act and never on a replay or an unrelated edit,
**repeated scans of the same still-current, still-ineligible generation
recompute the identical tuple and collide** (no repeat surfacing), while
**a reassignment — even restoring the same person — always advances the
generation before any subsequent loss can be observed against it**,
making every genuinely new loss cycle a distinct tuple, exactly as the
brief's own worked example requires.

- **This sweep remains reserved for the narrow legacy case only** — any
  ownership loss reachable through the two verified Actions
  (`RemoveWorkspaceMember`/`ChangeWorkspaceMemberRole`) above always uses
  their own real audit-event identity instead, never this generation-
  based fallback; the two constructions are never interchangeable for the
  same occurrence.

#### Deletion stuck/failed producer and occurrence identity

**Withdrawn: assuming ADR-0025's "visibly stuck" condition is itself a
domain transition this ADR can hook into directly.** It is a **read-model
observation** — a query, not an event ADR-0025 itself emits. **This ADR
does not mutate ADR-0025's own deletion state machine in any way** — it
only observes it.

- **A new, provider-free Laravel scheduler/reconciler**
  (`DetectStuckOrFailedDocumentDeletions`, on the same daily cadence)
  performs the identical read-model query ADR-0025's own administrative
  surface already uses to decide "visibly stuck," against the exact same
  authoritative fields ADR-0025 already defines for that determination
  (the deletion operation's own durable attempt/progress/lease-reclaim
  generation and elapsed-time threshold, unchanged, per ADR-0025's own
  "Retry semantics" and deletion-operation design).
**Withdrawn: `(operation, generation)` alone as the occurrence identity.**
Codex correctly identified that this cannot distinguish a temporary
stuck condition from a later permanent failure discovered **within the
same** durable generation — the reclaim generation only advances on an
actual reclaim, not on a mere change of classification, so a "stuck"
occurrence and a subsequent "failed permanently" occurrence for the
*same* generation would collide under the bare tuple, silently
suppressing the second, more severe fact.

- **Stuck-episode/failure identity, corrected**: `(DocumentDeletionOperation's
  own identity, its current durable attempt/reclaim generation,
  condition_kind)` — **never the scan date**, and **never the generation
  alone**. `condition_kind` is a small, closed, bounded enum: `stuck`,
  `failed_permanent` (extended with any further genuinely distinct
  ADR-0025 terminal category only if ADR-0025 itself exposes one requiring
  separate notification semantics — never multiplied speculatively; a
  category that only needs different wording, not different recipient/
  severity/delivery treatment, is carried as a safe typed **parameter**
  instead, per the bullet below, not as a new `condition_kind` value).
- **Repeated scans of the same stuck episode collide**: identical
  `(operation, generation, 'stuck')` tuple, recomputed each scan, no-ops
  against the already-recorded occurrence.
- **A later permanent failure within the *same* generation is a distinct
  occurrence**: `(operation, generation, 'failed_permanent')` differs from
  the earlier `(operation, generation, 'stuck')` tuple in its third
  component alone, and is therefore correctly surfaced as its own, more
  severe fact, never suppressed by the earlier stuck report.
- **Repeated scans of that permanent failure likewise collide**: the same
  tuple, recomputed, no-ops.
- **Recovery followed by a later, genuinely new stuck episode remains
  distinct**: a reclaim advances the generation, so a subsequent stuck
  observation under the new generation produces a new tuple regardless of
  `condition_kind`, exactly as the prior design already established for
  the generation component alone.
- **Locking/revalidation before event insertion, per the exact condition
  kind being recorded**: the reconciler locks (or re-reads under whatever
  lock ADR-0025's own reclaim mechanism already uses) the deletion
  operation's current state immediately before emitting the event, **and
  revalidates that the specific `condition_kind` about to be recorded
  still genuinely holds** at that moment — so a deletion that resolves,
  or a stuck condition that has already progressed to permanent failure,
  in the narrow window between the read-model query and event emission is
  never falsely reported under the wrong (or a stale) condition kind.
- **Event wording/severity may differ by `condition_kind`** — a `stuck`
  occurrence and a `failed_permanent` occurrence under the same
  `deletion.operation.stuck_or_failed` event key render with different
  plain-language wording and severity, exactly as the prior design's
  `stuck_reason` parameter already allowed, now carried alongside a
  `condition_kind` that is part of the **occurrence identity** itself,
  not merely a rendering parameter.
- **ADR-0025's own deletion state machine remains entirely unchanged** —
  restated: this correction only refines how this ADR's own read-model
  observer names what it saw; it adds no new field, state, or transition
  to ADR-0025's own tables.
- **Recipient resolution and actionable-work behaviour**: unchanged from
  the existing vocabulary-table entry — the initiating actor and
  workspace owners/admins, cross-linked to ADR-0025's own existing
  administrative surface, never a second, competing stuck-operation UI.

**`correlation_id` remains, deliberately non-unique**: it is retained
purely as **lineage** — "which operation/target does this event concern,
for navigation and audit correlation" — and is never consulted by any
idempotency check; several distinct occurrences (successive review
reminders across changed dates and cycles) legitimately share the same
`correlation_id` while each carrying its own distinct `occurrence_key`.

**Replay preserves the original occurrence identity**: because
`occurrence_key` is stored on the row at creation and never recomputed
at replay time, reprocessing an existing outbox row (crash-recovery, an
explicit reconciliation pass) always reuses that row's own already-stored
value — replay is a read-and-reprocess of an existing fact, never a
recomputation that could drift from what was originally decided.

**Three separate idempotency identities, never conflated, exactly as the
brief requires**:

1. **Domain-event → outbox projection**: `UNIQUE (workspace_id,
   occurrence_key)` on `DocumentGovernanceEvent` itself, above — the
   domain transition's own commit can never produce two outbox rows for
   the same logical occurrence, even if the surrounding code path is
   retried, and never suppresses a genuinely later, distinct occurrence
   concerning the same target.
2. **Outbox event → in-product notification, per recipient**: `UNIQUE
   (workspace_id, recipient_user_public_id, source_event_id)` on
   `DocumentGovernanceNotification` itself (per "In-product notification
   model" above) — a projector that reprocesses the same outbox row
   (crash-recovery, explicit replay) never creates a second notification
   per recipient, and the identity survives the recipient's own account
   being hard-deleted, unlike a nullable-FK-based tuple would.
3. **Each email-delivery attempt**: see "Email-delivery envelope model,
   corrected" immediately below — a single `UNIQUE (notification_id)` row
   was found insufficient to represent a digest containing several
   notifications, and is replaced with an envelope/membership design.

**A queued Laravel job (`ProjectDocumentGovernanceNotifications`), on the
same named-connection pattern ADR-0035 already establishes (a dedicated
queue connection, not the application's undifferentiated default), claims
outbox rows via the identical `SELECT ... FOR UPDATE SKIP LOCKED`
pattern `PublishIngestionOutbox` already proves, resolves recipients
(above), and inserts notification rows idempotently.** A second,
independent queued job (`DispatchDocumentGovernanceEmail`) consumes
email-eligible notifications, assigns each to its correct envelope, and
performs the actual Laravel `Notification::send()`/`Mail` dispatch,
entirely decoupled from the first — **notification persistence never
depends on email success, and a provider outage never rolls back,
blocks, or delays the original document-governance action**, since by
the time email dispatch is even attempted, the domain transition and the
notification row are already durably committed.

**Poison events**: an outbox row that fails contract/schema validation
(a malformed payload that should be structurally impossible, but is
checked anyway, exactly as `PublishIngestionOutbox` already checks its
own two event types) is marked `failed_at` after its own bounded retry
ceiling, visible through the same "visibly stuck"/operational-evidence
discipline ADR-0025/ADR-0026 already establish — never silently dropped,
never retried forever.

**Replay/reconciliation**: an operator (or a future scheduled
reconciliation command) may reset a `failed_at`/`published_at` row for
reprocessing; every idempotency identity above makes this safe by
construction, never a source of duplicate notifications or duplicate
emails.

#### Email-delivery envelope model, corrected

**Withdrawn: one `DocumentGovernanceEmailDelivery` row per notification,
`UNIQUE (notification_id)`, as the complete email-delivery model.** Codex
correctly identified that this shape cannot represent a single digest
email that legitimately contains several notifications at once — a
one-row-per-notification model would need to either duplicate the same
provider dispatch several times (defeating the point of a digest) or
silently invent an ad-hoc second relationship the schema never declared.
**Corrected: a durable envelope, with append-only membership, distinct
from the notifications it carries.**

**`DocumentGovernanceEmailEnvelope`** — one row per actual outbound email
attempt-group (immediate or digest alike):

| Column | Constraint |
|---|---|
| `id`, `public_id` | Internal/public identity |
| `workspace_id` | Scoping |
| `recipient_user_public_id` | The immutable scalar identity, matching the notification table's own correction above |
| `category_group` | The email category this envelope belongs to (e.g. `review_reminders`, or the notification's own `event_key` for a non-digested category) |
| `digest_date` | Nullable — null for an immediate envelope; set to the **effective** digest date selected by the late-arrival rule below for a digest envelope |
| `envelope_key` | **Non-nullable, deterministic, computed before creation** — `UNIQUE (workspace_id, envelope_key)`. For an **immediate** envelope: derived from the one notification's own `source_event_id`. For a **digest** envelope: derived from `(recipient_user_public_id, category_group, digest_date)`, where `digest_date` is the **effective** date the late-arrival rule below selects — never merely "today." |
| `assembly_status` | Bounded enum: `assembling`, `ready`, `dispatching`, `sent`, `failed_permanent`, `suppressed` — see "Envelope assembly lifecycle, corrected" below for the complete state machine. **Withdrawn: a distinct `failed_retryable` envelope state** — no transition in this ADR's own state diagram ever entered it; retryability is represented by returning to `ready` (below), and a transient failure is classified at the **attempt** level (`DocumentGovernanceEmailEnvelopeAttempt.status`, below), never duplicated as a second, unused envelope-level state. `suppressed` is the new terminal, non-failure outcome "Send-time eligibility and terminal suppression" below introduces. |
| `sealed_at` | Nullable, set exactly once, atomically with the `assembling → ready` transition — see below |
| `sealed_membership_digest` | Non-nullable once `sealed_at` is set — a canonical digest over **only** the sealed, ordered member identity list (who is in this envelope, and in what order) — a narrower, independently meaningful fact than `sealed_rendering_basis_digest` below, kept as its own field because it is knowable, and independently auditable, from the moment of sealing itself, before template/branding resolution is even considered relevant; **not** a redundant duplicate of the wider digest — see immediately below for the distinction |
| `attempt_count`, `next_attempt_at`, `last_error` | Read-only, derived summary fields for convenient querying — **not** the dispatch authority; see "Durable dispatch-attempt authority" below for the actual authoritative record |
| `provider_message_id` | Nullable — the mail provider's own returned message identity from the attempt that ultimately succeeded, retained for support/traceability; never sensitive content |
| `dispatched_at` | Nullable, set once the provider accepts the send — **`sent`-specific provider-acceptance evidence only; never the general retention anchor, and never fabricated for a `failed_permanent` or `suppressed` envelope** (below) |
| `terminal_at` | Nullable while `assembly_status ∈ {assembling, ready, dispatching}`; set exactly once, atomically with whichever of the three terminal transitions actually occurs (`dispatching → sent`, `dispatching → failed_permanent`, or `ready → suppressed`/`dispatching`'s own reclaim-driven `failed_permanent`); immutable thereafter — **the one common terminal timestamp shared by all three terminal states, and the actual anchor "Retention and deletion" below purges from** |
| `suppression_reason` | **New this pass — previously used throughout this ADR's own text without ever being declared as a column.** Nullable bounded enum, populated if and only if `assembly_status = 'suppressed'`, immutable once set. **Closed V1 vocabulary, narrowed to only the values reachable as an *envelope-level* outcome** (below): `workspace_email_disabled`, `personal_opt_out`, `recipient_disabled`, `recipient_unverified`, `membership_removed`, `no_deliverable_members`. **`authority_lost` is deliberately excluded from this envelope-level vocabulary** — it is reachable only as a *per-member* decision reason (`DocumentGovernanceEmailEnvelopeMemberDecision`'s own, separate `suppression_reason`, per-notification, per "Send-time eligibility and terminal suppression" above), never as the envelope's own aggregate reason: if every member is suppressed for `authority_lost`, the envelope's own reason is `no_deliverable_members`, the correct aggregate fact, never a per-member reason promoted to the envelope level. |
| `terminal_failure_category` | **New this pass.** Nullable bounded enum, populated if and only if `assembly_status = 'failed_permanent'`, immutable once set. **Closed V1 vocabulary, limited to the failure classes this ADR actually produces a reachable path for**: `provider_permanent_failure` (a single attempt's own provider-side rejection was inherently non-retryable, regardless of ceiling); `retry_ceiling_exhausted` (the bounded retry ceiling was exhausted via the ordinary caught-failure path, "Durable dispatch-attempt authority" step 5); `attempt_reclaim_ceiling_exhausted` (the ceiling was exhausted via the reclaim path specifically, step 6 — kept distinct from the previous value because the producing worker/process differs); `rendering_integrity_failure` (a `sealed_rendering_basis_digest` or `dispatch_decision_digest` mismatch at pre-call verification, step 9). **`configuration_failure` is deliberately not included in the V1 vocabulary** — this ADR describes no reachable path to a dispatch-time configuration failure (the Dolved branding fallback is a complete, always-available V1 state per "Email templates and tenant-branding seam" above, and template/branding resolution is fixed no later than sealing), so no enum value is added for a case this design does not produce; a future ADR introducing such a path would add the value then, not speculatively now. |
| `template_key`, `template_version` | Non-nullable once sealed — the exact, versioned rendering template selected for this envelope's `category_group` (an immediate per-category template, or the one digest template — see "Immediate versus digest template behaviour" below); fixed no later than sealing, never re-selected on retry |
| `branding_configuration_identity` | Non-nullable once sealed — the resolved branding configuration this envelope will render with |
| `workspace_display_name_snapshot` | Non-nullable once sealed — the safe workspace display name captured at sealing time, so a later workspace rename never changes an already-sealed envelope's own rendering |
| `resolved_accent_identity` | Non-nullable once sealed — either the tenant's own validated accent or the approved Dolved fallback accent, whichever the contrast check actually resolved to |
| `sealed_rendering_basis_digest` | **Renamed this pass from `rendering_input_digest`** (see "Two distinct immutable digests, corrected" immediately below). Non-nullable once sealed — a canonical digest over every safe scalar input fixed **at sealing time**: template identity, template version, branding identity, workspace display-name snapshot, resolved accent, and `sealed_membership_digest`. **Immutable from the moment sealing sets it — never rewritten by the eligibility preflight, a retry, or any later reconciliation pass.** |
| `dispatch_decision_digest` | **New this pass.** Nullable while `assembling` and immediately after ordinary sealing (`ready`, before the generation-1 preflight has run); non-nullable from the moment the generation-1 preflight completes onward. Computed **exactly once**, during the generation-1 preflight, over: the ordered sealed membership identity; every immutable per-member `included`/`suppressed` decision; each decision's typed suppression reason; the final ordered `included` set; and `sealed_rendering_basis_digest`'s own identity (chaining onto, never replacing, the sealed basis). Immutable once set; reused verbatim, never recomputed, by every retry generation. |

#### Two distinct immutable digests, corrected

**Withdrawn: one field, `rendering_input_digest`, made immutable at
sealing time and then recomputed during the generation-1 eligibility
preflight to cover a different set of facts.** Codex correctly identified
that a single field cannot honestly represent two different immutable
facts fixed at two different moments — "the sealed rendering basis never
changes after sealing" and "the dispatch decision never changes after
the first preflight" are both true, and both worth guaranteeing, but they
are not the same fact, and the ADR's own text asserted both immutability
claims about the identical column. **Corrected: two distinct, separately
immutable digest fields, each fixed at its own single moment and never
touched again:**

- **`sealed_rendering_basis_digest`** (renamed from `rendering_input_digest`,
  same computation this ADR always intended for it) — fixed once, at
  sealing, over the rendering/branding/template facts and the sealed
  membership digest; never touched by the preflight, a retry, or
  reconciliation.
- **`dispatch_decision_digest`** (new) — fixed once, at the generation-1
  preflight, over the per-member inclusion/suppression decisions, the
  final included set, and (by inclusion) the sealed rendering basis's own
  identity; never touched by a retry.

**Why `sealed_membership_digest` remains a separate, third field, not a
redundant one**: it captures a narrower fact — the ordered member
identity list alone — that already exists and is already meaningful at
the moment sealing closes membership, independently of whether template/
branding resolution has yet succeeded. `sealed_rendering_basis_digest`
is a wider, composite fact computed from `sealed_membership_digest`
itself plus the rendering-specific identities; the two are related by
composition, not duplication, and each remains independently useful for
audit (membership-only tampering versus full-rendering-basis tampering
are two distinguishable failure classes, and this ADR's own tests, below,
exercise them separately).

**Withdrawn: relying on `dispatched_at` as the retention anchor for every
terminal envelope.** Codex correctly identified that `dispatched_at` is
never set on `failed_permanent` or `suppressed` envelopes — no provider
ever accepted anything for either outcome — so a retention rule anchored
on it could never purge those two terminal states at all, leaving them
retained forever by omission. **Corrected: `terminal_at`, a common
immutable timestamp set exactly once by whichever terminal transition
actually occurs, is the sole retention anchor for all three terminal
states; `dispatched_at` remains `sent`-specific provider-acceptance
evidence and nothing else.**

#### Closed same-row `CHECK`, corrected to cover every branch completely

**Withdrawn: a `CHECK` that grouped `assembling`/`ready`/`dispatching`
into one branch and left `suppression_reason`/the (then-undeclared)
failure category and the two digest columns out of the constraint
entirely.** Codex correctly identified that `assembling`, freshly-sealed
`ready`, retryable `ready`, and `dispatching` are four genuinely
different shapes — they differ in which of `sealed_rendering_basis_
digest`/`dispatch_decision_digest` may legitimately be set — and that a
`suppression_reason`/`terminal_failure_category` value on the wrong
status, or a missing one on the right status, was previously
unenforceable at the database level. **Corrected: one `CHECK`
enumerating all seven reachable shapes exhaustively, using only
same-row columns:**

```sql
CHECK (
  -- assembling: nothing sealed yet, nothing terminal
  (assembly_status = 'assembling'
    AND sealed_rendering_basis_digest IS NULL
    AND dispatch_decision_digest IS NULL
    AND terminal_at IS NULL
    AND dispatched_at IS NULL
    AND suppression_reason IS NULL
    AND terminal_failure_category IS NULL)

  -- ready, freshly sealed, before the generation-1 preflight has ever run
  OR (assembly_status = 'ready'
    AND sealed_rendering_basis_digest IS NOT NULL
    AND dispatch_decision_digest IS NULL
    AND terminal_at IS NULL
    AND dispatched_at IS NULL
    AND suppression_reason IS NULL
    AND terminal_failure_category IS NULL)

  -- ready, after a real retryable failure returned it here (generation 1
  -- already ran and froze dispatch_decision_digest)
  OR (assembly_status = 'ready'
    AND sealed_rendering_basis_digest IS NOT NULL
    AND dispatch_decision_digest IS NOT NULL
    AND terminal_at IS NULL
    AND dispatched_at IS NULL
    AND suppression_reason IS NULL
    AND terminal_failure_category IS NULL)

  -- dispatching: a claimed attempt currently owns this envelope
  OR (assembly_status = 'dispatching'
    AND sealed_rendering_basis_digest IS NOT NULL
    AND dispatch_decision_digest IS NOT NULL
    AND terminal_at IS NULL
    AND dispatched_at IS NULL
    AND suppression_reason IS NULL
    AND terminal_failure_category IS NULL)

  -- sent: terminal success
  OR (assembly_status = 'sent'
    AND sealed_rendering_basis_digest IS NOT NULL
    AND dispatch_decision_digest IS NOT NULL
    AND terminal_at IS NOT NULL
    AND dispatched_at IS NOT NULL
    AND suppression_reason IS NULL
    AND terminal_failure_category IS NULL)

  -- failed_permanent: terminal failure
  OR (assembly_status = 'failed_permanent'
    AND sealed_rendering_basis_digest IS NOT NULL
    AND dispatch_decision_digest IS NOT NULL
    AND terminal_at IS NOT NULL
    AND dispatched_at IS NULL
    AND suppression_reason IS NULL
    AND terminal_failure_category IS NOT NULL)

  -- suppressed: terminal, non-failure, non-delivery
  OR (assembly_status = 'suppressed'
    AND sealed_rendering_basis_digest IS NOT NULL
    AND dispatch_decision_digest IS NOT NULL
    AND terminal_at IS NOT NULL
    AND dispatched_at IS NULL
    AND suppression_reason IS NOT NULL
    AND terminal_failure_category IS NULL)
)
```

**How the two `ready` shapes are told apart by a same-row `CHECK`, honestly
explained, not just asserted**: `dispatch_decision_digest`'s own nullity
already is the distinguishing same-row fact — it is null only in the
window between sealing and the generation-1 claim, and non-null from the
moment the generation-1 preflight completes onward, for every subsequent
`ready` state a retryable failure ever returns the envelope to. No
separate lifecycle marker or trigger is required, **because these are
the only two `ready` sub-shapes this ADR's own state diagram produces**
(freshly sealed, or post-retryable-failure) — there is no third `ready`
shape a marker would need to distinguish. This `CHECK` does **not**, by
itself, prove that a genuine prior attempt actually exists for a
retryable `ready` row — that cross-row fact is guaranteed by
construction instead: the only write path that ever sets
`dispatch_decision_digest` is the generation-1 claim transaction, which
always creates attempt generation 1 in that same transaction (per the
corrected claim algorithm above), so a non-null `dispatch_decision_digest`
is never reachable without a corresponding attempt row having been
created atomically alongside it. Zero-deliverable `suppressed` also
carries a non-null `dispatch_decision_digest`, computed over its own
empty `included` set — see the claim algorithm's zero-member branch
above.

This closes every shape this pass's brief named as needing an explicit
allow/reject answer: `sent` requires both timestamps and both digests
together; `failed_permanent` and `suppressed` each require `terminal_at`
and both digests, never `dispatched_at`; `failed_permanent` additionally
requires `terminal_failure_category`, and `suppressed` additionally
requires `suppression_reason`, each exclusively (never both non-null on
the same row, since no branch above sets both); `assembling` requires
every one of these seven columns null; the two `ready` shapes and
`dispatching` each require both non-terminal reason columns null. No row
can claim a terminal status without `terminal_at`, no row can claim
`dispatched_at` without having genuinely reached `sent`, and no row can
claim `dispatching` or later without both digests already fixed.

**Immutability, declared explicitly for every field this `CHECK`
governs**: `terminal_at`, `suppression_reason`, `terminal_failure_
category`, `sealed_rendering_basis_digest`, and `dispatch_decision_
digest` are each written **at most once**, ever, on a given envelope
row. None of the five has a dedicated immutability trigger of its own;
each is protected the same way this ADR already protects every other
terminal or once-only field — every write path that sets any of them is
itself a same-transaction `UPDATE ... WHERE assembly_status IN (...)`
guarded to match only the specific prior status that write is allowed to
originate from (`assembling` for the two sealing-time digests, a
non-terminal `ready`/`dispatching` for the terminal triple), so once
`assembly_status` has moved past that guard, no further write path can
ever match the same `WHERE` clause again — the same idempotent-guard
discipline "Durable dispatch-attempt authority" already establishes for
attempt rows, applied here to the envelope's own once-only columns.

**`UNIQUE (id, workspace_id)`** on `DocumentGovernanceEmailEnvelope` —
**withdrawn: leaving this undeclared while the attempt table's own
composite FK, below, already assumed it.** Codex correctly identified
that `DocumentGovernanceEmailEnvelopeAttempt`'s composite FK `(envelope_id,
workspace_id)` has no valid target to reference without this exact
unique constraint declared on the parent, in this exact column order —
corrected here, closing the gap rather than leaving it implicit.

#### Composite-FK sweep and deletion rules, corrected

**Every composite FK this ADR claims, confirmed against an explicit,
matching unique constraint** — a full sweep, not merely the one gap
found: this ADR declares exactly **three** composite foreign keys in
total — `DocumentGovernanceEmailEnvelopeAttempt`'s `(envelope_id,
workspace_id)`, targeting `DocumentGovernanceEmailEnvelope (id,
workspace_id)` per the `UNIQUE` constraint immediately above;
`DocumentGovernanceNotificationProjectionReceipt`'s
`(event_projection_id, workspace_id)`, targeting `DocumentGovernance
EventProjection (id, workspace_id)` per that table's own matching
`UNIQUE` constraint ("Projection receipts" above); and — **added by a
later pass, correcting the withdrawn single-column
`target_document_family_id` FK** — `DocumentGovernanceCommand`'s
`(target_document_family_id, workspace_id)`, targeting `document_families
(id, workspace_id)` per the `UNIQUE` constraint "Composite workspace
binding, corrected" above adds to `document_families` itself. All three
are safe for the identical reason: no parent row (an envelope, an event
projection, or a family) is ever deleted independently of its own
terminal retention purge or governance lifecycle, so `ON DELETE`
behaviour is never exercised prematurely against any of them. Every other
reference in this ADR
(`DocumentGovernanceEmailEnvelopeMember.envelope_id`,
`.notification_id`; `DocumentGovernanceNotification.recipient_
workspace_membership_id`; each receipt's own `notification_public_id`)
is a deliberately **single-column** reference, per the same single-
column-to-avoid-composite-`SET NULL`-fan-out discipline established
throughout this ADR and ADR-0035 — none of them requires a composite
target, and none is left implying one it does not have.

**Deletion rules, stated once, for the whole envelope/member/attempt
family:**

- **No child row can cross workspaces**: enforced structurally by the
  composite FK above (an attempt's own `workspace_id` must match its
  envelope's), and by the fact that every other child reference resolves
  through a single parent (`envelope_id`) that itself carries exactly one
  `workspace_id` — there is no column on any child table through which a
  cross-workspace value could ever be written independently of its
  parent.
- **Deleting an active or retryable envelope is prohibited**: envelope
  rows in `assembling`/`ready`/`dispatching` are never subject to any
  delete path this ADR defines — retention (above) only ever considers
  **terminal** envelopes (`sent`/`failed_permanent`/`suppressed`) whose
  400-day window has elapsed; no operator or reconciliation action this
  ADR introduces deletes a non-terminal envelope.
- **Attempts and members are never deleted independently of their
  envelope while it remains active** — both are append-only for the
  entire lifetime of a non-terminal envelope; the only deletion path is
  the terminal retention purge below.
- **Purge-time deletion order, once an envelope is terminal and past
  retention**: attempts and members (and member decisions, per "Send-time
  eligibility and terminal suppression" above) are deleted **first**,
  then the envelope row itself — a plain, ordinary `DELETE` sequence
  within the same purge transaction, **never** a database `ON DELETE
  CASCADE` relied upon implicitly; the purge job explicitly deletes
  children before parent, so the exact deletion order is visible in the
  application code performing it, not left to an implicit cascade
  configuration someone could change without noticing this ordering
  dependency.
- **An expired terminal envelope is never blocked indefinitely by its own
  attempts/members**: because deletion is explicit and ordered (children
  first, by the purge job itself, never by relying on a
  `restrictOnDelete()` the children don't declare), there is no
  restrictive constraint anywhere in this family that could cause a
  terminal, past-retention envelope to become permanently undeletable.

**`DocumentGovernanceEmailEnvelopeMember`** — append-only membership,
never updated or deleted once written:

| Column | Constraint |
|---|---|
| `envelope_id`, `notification_id` | `UNIQUE (notification_id)` — **one notification belongs to at most one envelope, ever, for V1's single (email) channel** — the exact rule the brief requires ("one notification may belong to at most one applicable delivery envelope for the same channel/category occurrence"); a second attempt to assign the same notification to a different envelope is rejected by this constraint, not merely discouraged by application logic |
| `source_event_id`, `recipient_user_public_id` | **Immutable scalar copies**, captured at membership-creation time — durable delivery evidence that survives independently of the `notification_id` row's own eventual purge (see "Retention and deletion" below); never a second source of truth while the notification row still exists, only a survival guarantee for after it is purged |
| `ordinal` | Non-nullable once sealed — the member's stable position within the sealed, deterministic rendering order (below); assigned at sealing, never before |
| `added_at` | Immutable |

**No live, blocking foreign key from a membership row to the
notification it represents** — `notification_id` is a plain, `nullOnDelete()`-eligible reference (per "Retention and deletion" below), never
`restrictOnDelete()`; the scalar `source_event_id`/`recipient_user_public_id`
copies above are what make the membership row fully intelligible on its
own even after its notification has been purged.

#### Envelope assembly lifecycle, corrected

**Withdrawn: allowing membership to be appended to a digest envelope for
the entire day with no defined cut-off, leaving a genuine race between "a
late notification arrives" and "the envelope has already been sent."**
Codex correctly identified that nothing previously prevented a
notification from being appended to an envelope that had already been
dispatched. **Corrected: an explicit, closed assembly lifecycle, with
exactly these permitted transitions:**

```
assembling → ready        (the digest cut-off / immediate-creation seal)
ready → dispatching       (a dispatch attempt opens — see below)
dispatching → sent        (a dispatch attempt's provider call succeeds)
dispatching → ready       (a dispatch attempt fails, retryable, ceiling not reached;
                            includes the reclaim path, "Durable dispatch-attempt
                            authority," step 6)
dispatching → failed_permanent  (ceiling reached, ordinary failure or reclaim alike)
ready → suppressed        (the generation-1 or retry preflight/stop-check, run at
                            claim time before any attempt is created, finds no
                            deliverable member — "Send-time eligibility and
                            terminal suppression" below; reached without ever
                            entering `dispatching` and without ever calling the
                            provider)
```

**Withdrawn: a `dispatching → suppressed` transition, implying
suppression was discovered only after the envelope had already entered
`dispatching` (and, with it, after an attempt row had already been
created).** Corrected alongside "Durable dispatch-attempt authority"
below: the generation-1 preflight and every retry's own stop-check now
both run **inside the claim transaction, under the envelope's lock,
before either an attempt is created or the envelope ever leaves
`ready`** — so suppression is always `ready → suppressed` directly, and
`dispatching` is only ever entered once at least one deliverable member,
or a passing retry stop-check, is already confirmed.

No other transition exists — in particular, **there is no transition
back into `assembling` from any other state**, and **`sent`/
`failed_permanent`/`suppressed` are terminal**, never re-entered.

- **Membership may be appended only while `assembly_status =
  'assembling'`** — enforced both by a same-row trigger rejecting any
  `INSERT` into `DocumentGovernanceEmailEnvelopeMember` whose parent
  envelope is not currently `assembling`, **and** by the row-lock protocol
  immediately below, which is what actually closes the race a trigger
  alone cannot under `READ COMMITTED` (below).
- **Immediate envelopes are created with their one member and sealed
  atomically, in the single transaction that creates them** — an
  immediate envelope is never observed in `assembling` by any other
  transaction; it moves straight to `ready` before it is ever visible.
- **Dispatch may begin only from `ready`, or from the retry path
  (`dispatching → ready` on a retryable failure, then `ready →
  dispatching` again)** — never from `assembling`, which has no rendering
  snapshot to dispatch from yet.

#### Append/seal row-lock protocol, corrected

**Withdrawn: relying on a trigger that merely observes `assembly_status`
at `INSERT` time to prevent the append/seal race.** Codex correctly
identified that under PostgreSQL `READ COMMITTED`, a trigger checking
`assembly_status` and a concurrent sealing transaction changing it are
not, by themselves, serialised against each other — both the append and
the seal must lock the **same envelope row** for the check-then-act
sequence to be safe. **Corrected: both paths acquire the envelope row's
own lock before checking or changing anything.**

**Append path — one transaction:**

1. Resolve or create the effective digest envelope (`INSERT ... ON
   CONFLICT (workspace_id, envelope_key) DO NOTHING`, per the existing
   creation pattern, using the late-arrival rule below to pick the
   effective date).
2. **Lock that envelope row `FOR UPDATE`.**
3. Re-read and verify, under the lock, `assembly_status = 'assembling'`
   and that the effective digest date this append still targets is the
   one just locked (defence against a concurrent seal having just run).
4. Insert the membership row.
5. Commit.

**If the locked envelope is no longer `assembling`** (a concurrent seal
won the race and committed first): do not append; recompute the next
eligible digest date per the late-arrival rule; resolve/create **that**
envelope instead; retry steps 2–5 against it — a small, deterministic,
bounded retry loop, never an unbounded one, since each retry only ever
advances to the next digest date.

**Seal path — one transaction:**

1. **Lock the envelope row `FOR UPDATE`.**
2. Re-read, under the lock, `assembly_status = 'assembling'` — if it is
   already anything else, this seal attempt is a no-op (another process
   already sealed it, or it was never assembling).
3. Confirm at least one member exists — an envelope found with zero
   members at this point is left `assembling` (untouched) rather than
   sealed, so it is never dispatched empty.
4. Read and deterministically order the current membership **while the
   parent row lock is still held** — no member can be concurrently
   appended past this point, since the append path's own step 2 lock
   request would now block until this transaction commits.
5. Compute and persist `sealed_membership_digest` and the full sealed
   rendering-basis snapshot (`template_key`/`template_version`/
   `branding_configuration_identity`/`workspace_display_name_snapshot`/
   `resolved_accent_identity`/`sealed_rendering_basis_digest`).
   **`dispatch_decision_digest` is deliberately left null here** — it is
   not yet knowable at sealing, since it depends on the generation-1
   preflight, which has not yet run.
6. Set `sealed_at` and `assembly_status = 'ready'`.
7. Commit.

**One global lock order for every email-delivery path, stated once**:
envelope row lock, always acquired first and released only at that same
transaction's own commit, for **every** operation that touches an
envelope's own mutable state — creation/reuse, membership append,
sealing, and dispatch claim (per "Durable dispatch-attempt authority"
below, which claims the envelope row before opening an attempt) all
acquire the identical envelope-row lock as their first act, never a
second resource first. Because every one of these paths only ever locks
the single envelope row itself (never an attempt or membership row
first), no cross-resource ordering conflict is possible between them.

**Concurrent append/seal always resolves to exactly one of two honest
outcomes, never a third**: either the appending transaction's own lock
request is granted first, in which case its member commits into the
still-`assembling` envelope before any seal can proceed against it and is
included in whatever that seal subsequently captures; or the sealing
transaction's lock is granted first, in which case the append transaction
observes `assembly_status <> 'assembling'` once it acquires the lock and
is routed to the next eligible envelope instead. **A member can never
commit into an already-sealed envelope, and is never silently stranded**
— the retry-to-next-envelope path guarantees it always lands somewhere.
- **An empty digest is never dispatched**: the cut-off operation only
  seals and transitions envelopes that have at least one member at the
  moment of sealing; an envelope with zero members at cut-off time is
  simply never created (the `envelope_key`/`ON CONFLICT` creation only
  ever happens when the first qualifying notification arrives, per the
  existing digest-assembly bullet below) — there is no empty envelope row
  to accidentally seal or dispatch.
- **Retries always use the exact sealed membership**: because
  `sealed_at`/`sealed_membership_digest`/the rendering-input snapshot are
  all fixed at sealing and never rewritten, a retried dispatch attempt
  renders from, and re-verifies against, the identical inputs every time
  — see "Durable dispatch-attempt authority" below.
- **Digest rendering uses members in stable, deterministic order** — the
  `ordinal` assigned at sealing, never a re-sort at render time.

**The late-arrival rule, precise, and the corrected `envelope_key`
construction it feeds**:

- **Before the configured UTC digest cut-off**: a qualifying notification
  is assigned to **today's** digest date.
- **At or after the cut-off, or when today's digest envelope for this
  `(recipient, category_group)` has already been sealed** (whichever is
  true first): the notification is assigned to **the next digest date**
  instead — never appended to an already-sealed or already-`dispatching`/
  `sent` envelope.
- **`envelope_key` is always built from this *effective* digest date**,
  never from "today" literally — so a late arrival naturally creates or
  joins **tomorrow's** envelope (via the identical `INSERT ... ON
  CONFLICT (workspace_id, envelope_key) DO NOTHING` creation pattern
  already established), rather than being rejected, silently dropped, or
  stranded. No notification is ever lost merely because it arrived near
  or after the cut-off — it is simply, deterministically, delivered in
  the *next* digest instead of the current one.

#### Durable dispatch-attempt authority and stale-worker fencing

**Withdrawn: treating the envelope's own mutable `status`/`attempt_count`
as the complete dispatch-execution authority.** Codex correctly
identified that this leaves two real gaps: nothing previously prevented
two queue workers from both claiming the same `ready` envelope and both
calling the mail provider, and nothing durably fenced a worker whose
claim had already been reclaimed as stale from later reporting against a
generation it no longer owns. **Corrected: an append-only
`DocumentGovernanceEmailEnvelopeAttempt` table, following the exact
fencing discipline this decomposition already establishes for bulk-item
attempts (ADR-0035), applied here to email dispatch specifically —
proportionate to what an email-delivery attempt actually needs, not a
new general workflow engine:**

| Column | Constraint |
|---|---|
| `id` | Internal identity |
| `envelope_id`, `workspace_id` | Composite FK `(envelope_id, workspace_id)` — safe as a composite FK here because envelope rows are never deleted before their own retention window elapses (below) |
| `generation` | Non-nullable, `UNIQUE (envelope_id, generation)`, allocated as `COALESCE(MAX(previous generations for this envelope), 0) + 1` for every new attempt |
| `attempt_token` | Non-nullable, a randomly generated opaque value set once at creation — the unforgeable fencing credential, distinct from `id` |
| `status` | Bounded enum: `open`, `accepted`, `failed_retryable`, `failed_permanent`, `abandoned` |
| `lease_expires_at` | Non-nullable while `status = 'open'`, set once at creation to a fixed, configured lease duration |
| `opened_at`, `completed_at` | `completed_at` non-nullable if and only if `status` is terminal (anything other than `open`) |
| `failure_category` | Nullable, bounded typed vocabulary — populated if and only if `status` is `failed_retryable`, `failed_permanent`, or `abandoned` |
| `provider_idempotency_key_used` | Non-nullable — the exact identity passed to the provider for this attempt (per "Honest external-delivery guarantee" below), reused unchanged across every attempt generation for the same envelope |
| `provider_message_id` | Nullable — populated only on `status = 'accepted'` |
| `sealed_rendering_basis_digest_verified` | Non-nullable — a copy of the envelope's own `sealed_rendering_basis_digest` as re-verified by this specific attempt immediately before its provider call (below); retained so a later audit can confirm which exact rendering basis a given attempt actually used |
| `dispatch_decision_digest_verified` | Non-nullable — a copy of the envelope's own `dispatch_decision_digest` as re-verified by this specific attempt immediately before its provider call (below); retained so a later audit can confirm which exact dispatch decision a given attempt actually acted on |

**Database constraints**: `CREATE UNIQUE INDEX ON document_governance_
email_envelope_attempts (envelope_id) WHERE status = 'open'` — one open
attempt per envelope, ever, at a time, the same partial-unique-index
pattern ADR-0035 already establishes for bulk-item attempts.

**Withdrawn: locking the attempt row before the envelope row at some
points in this lifecycle while locking the envelope first at others.**
Codex correctly identified that the pre-provider verification step, the
provider-acceptance recording step, and the retryable/permanent-failure
recording step each locked the attempt row first, while the claim step
and the reclaimer both already locked the envelope row first — two
contradictory lock orders active over the same table pair are a genuine
deadlock hazard the moment two of these paths run concurrently against
the same envelope (for example, a reclaimer racing a slow worker's own
result-recording transaction, each acquiring one row in the opposite
order the other expects). **Corrected: exactly one global lock order for
every transaction in this lifecycle, without exception: `envelope →
attempt`.** A worker holding only its own attempt's `id`/`attempt_token`
derives the envelope's identity from that same held attempt row's own
immutable `envelope_id` foreign key — a plain, lock-free read, since
`envelope_id` is fixed once at attempt creation and never changes —
locks the envelope first using that derived identity, and only then
locks the attempt itself. **A stale or slow worker must never lock the
attempt row first merely because it already knows the token**; knowing
the token only tells it *which* attempt to lock second, never licenses
locking it before the envelope. This applies identically to first-attempt
claim, retry-attempt claim, pre-provider verification and lease renewal,
provider-acceptance recording, retryable-failure recording,
permanent-failure recording, expired-attempt reclamation, duplicate/stale
result reporting, and any future explicit reconciliation pass — every
one of the numbered steps below now follows it.

**Lifecycle, mirroring ADR-0035's own fencing model exactly:**

1. **Claim, under the envelope's own row lock — corrected to run the
   eligibility decision *inside* the claim transaction, before any
   attempt row is created, never after.** **Withdrawn: a claim step that
   unconditionally inserted an open attempt row and transitioned `ready →
   dispatching` first, deferring the generation-1 member preflight (or
   the retry stop-check) to a later, separate "immediately before calling
   the provider" step.** Codex correctly identified that this created an
   attempt row — consuming a real generation and, for a retry, a real
   position against the retry ceiling — *before* it was known whether
   there was anything deliverable at all, directly contradicting the
   stated rule that a zero-deliverable outcome creates no attempt row.
   **Corrected: one claim transaction, envelope-locked throughout, that
   decides eligibility first and only then branches into "create an
   attempt" or "suppress, no attempt ever created":**
   - **Lock the envelope**: `SELECT ... FOR UPDATE SKIP LOCKED` one
     envelope whose `assembly_status = 'ready'` (whether this is its
     first dispatch or a retry after a prior `dispatching → ready`);
     verify no other `open` attempt exists for it (the partial unique
     index makes a second concurrent claim structurally impossible).
   - **Generation 1 (no prior attempt exists for this envelope)**: run
     the full per-member preflight, under this same lock, per "Send-time
     eligibility and terminal suppression" below.
     - **Zero members remain `included`**: write every member's
       `suppressed` decision, compute and persist `dispatch_decision_
       digest` (over the ordered sealed membership identity, every
       member's `suppressed` decision and its reason, the empty ordered
       `included` set, and `sealed_rendering_basis_digest`'s own
       identity — well-defined even with zero included members, and set
       for exactly the same reason it is set on the deliverable branch:
       a `suppressed` envelope still carries a genuine, auditable
       dispatch decision, only one that included nobody), transition the
       envelope directly `ready → suppressed` with `suppression_reason =
       'no_deliverable_members'` and set `terminal_at` (below), all in
       this one transaction; **no `DocumentGovernanceEmailEnvelopeAttempt`
       row is ever created**; no retry ceiling is consumed (there was
       never an attempt to count); commit.
     - **At least one member remains `included`**: write every member's
       `included`/`suppressed` decision and compute and persist
       `dispatch_decision_digest` (over the ordered sealed membership
       identity, every member decision, its suppression reason, the
       final ordered `included` set, and `sealed_rendering_basis_digest`'s
       own identity — never recomputing `sealed_rendering_basis_digest`
       itself, which stays exactly as sealing fixed it), `INSERT` attempt
       `generation = 1` with `status = 'open'`, a freshly generated
       `attempt_token`, its lease, and the derived
       `provider_idempotency_key_used`, and transition the envelope
       `ready → dispatching`, all in this one transaction; commit.
   - **Retry (a prior attempt already exists for this envelope)**: run
     only the narrow, envelope-wide stop-condition re-check, under this
     same lock, per "Send-time eligibility and terminal suppression"
     below.
     - **Any stop condition fails**: transition the envelope directly
       `ready → suppressed` with the corresponding reason and set
       `terminal_at`, in this one transaction; **no new attempt
       generation is ever created for this outcome**; commit.
     - **Every stop condition passes**: `INSERT` a new attempt row with
       `status = 'open'`, the next `generation`
       (`COALESCE(MAX(previous generations), 0) + 1`), a freshly
       generated `attempt_token`, reusing the same
       `provider_idempotency_key_used` derived from the envelope's own
       `envelope_key`, and transition the envelope `ready → dispatching`,
       all in this one transaction; commit.
   - **An open, unexpired lease on an existing attempt blocks any other
     worker from claiming the same envelope** in either branch — the
     partial unique index and the row lock together guarantee this.
2. **Immediately before calling the provider, under the envelope→attempt
   lock order — a pure fencing re-verification, never a re-decision.**
   The generation-1 preflight decisions and the retry stop-check outcome
   were already frozen, atomically, in step 1's own claim transaction;
   this step never re-runs either one and never changes any member's
   `included`/`suppressed` decision. The worker locks the envelope row
   first — by the `envelope_id` its own already-held attempt row names,
   read without a lock — then locks its own attempt row by `(id,
   attempt_token, generation)`, and verifies: `status = 'open'`, the
   token/generation
   still match, the lease has not expired, and — the email-specific
   addition — **the envelope's `assembly_status` is still exactly what
   this attempt expects (`dispatching`, owned by this attempt) and
   *both* the envelope's own `sealed_rendering_basis_digest` and its own
   `dispatch_decision_digest` still match what this attempt captured at
   claim time**, persisting both as `sealed_rendering_basis_digest_
   verified` and `dispatch_decision_digest_verified` on the attempt row.
   Both digests and the `included`/`suppressed` decision set they cover
   were frozen once, in step 1's own claim transaction, whether this
   attempt is generation 1 or a retry — this step only confirms nothing
   has since been tampered with or superseded; it never runs the
   preflight or the stop-check itself, and never produces a new
   eligibility decision or recomputes either digest. **Two different
   mismatches here mean two different things, and are handled
   differently, never conflated under one "fenced/abandoned" label**: a
   token/generation/lease/`assembly_status` mismatch means *this worker*
   is stale (someone else already owns or has converged the envelope) —
   no provider call, no status write at all, a pure fencing no-op,
   consistent with the complete result-fencing predicate above; a
   `sealed_rendering_basis_digest`/`dispatch_decision_digest` mismatch
   means the envelope's own sealed data is corrupted or tampered even
   though this worker's own token/generation are still genuinely valid —
   no provider call, but the attempt **is** written, `failed_permanent`,
   per step 9 below, since this is a permanent data-integrity fault, not
   a stale-worker situation.
3. **Only once every check above passes does the worker call the mail
   provider**, using `provider_idempotency_key_used` (derived
   deterministically from the envelope's own `envelope_key`, so every
   attempt generation for the same logical envelope reuses the identical
   idempotency identity — never a fresh one per attempt). **No database
   lock is held across this external call** — both locks acquired in
   step 2 (envelope, then attempt) are released together when that
   transaction commits, before the network call begins; the attempt's own
   `status = 'open'` row is what other workers respect instead of a held
   lock.

**Complete result fencing, corrected to apply identically to every
result path.** **Withdrawn: recording a result against "the attempt,
still `open`" as the only guard, on the unstated assumption that
`status = 'open'` alone was sufficient.** Codex correctly identified
that this is a weaker shortcut than the envelope→attempt lock order
above actually requires: `status = 'open'` alone does not confirm the
report is even about the right envelope, the right workspace, the right
attempt identity, the right generation, the right token, or a result
whose provider idempotency identity actually matches this attempt —
each of those is independently spoofable, replayable, or misroutable in
a way `status = 'open'` alone cannot detect. **Corrected: one normative
result-fencing transaction, applied verbatim, with no per-path
exception, to every one of the six ways a result can arrive** — provider
acceptance, provider retryable failure, provider permanent failure, a
local hard timeout (`T` above, caught exactly like any other failed
provider call and routed through the same failure path), a duplicate or
redelivered provider callback, and a suspended worker reporting after
its own reclaim:

1. **Derive the envelope identity from the worker's own held attempt
   context** (`envelope_id`, read without a lock, exactly as the
   envelope→attempt lock-order rule above already establishes) — never
   from caller-supplied input alone.
2. **Lock the envelope row `FOR UPDATE`.**
3. **Verify, under that lock**: the envelope's own `id`/`workspace_id`
   match what the worker's held context expects; `assembly_status =
   'dispatching'` (this ADR does not maintain a separate
   current-attempt-generation pointer column on the envelope —
   `assembly_status = 'dispatching'` combined with the partial
   one-open-attempt-per-envelope unique index below already establishes
   that at most one attempt can legitimately own this envelope at a
   time, so the attempt-level checks in step 5 carry the identity
   precision a separate pointer would otherwise duplicate); and **both**
   `sealed_rendering_basis_digest` and `dispatch_decision_digest` still
   match the values the worker's held attempt context captured at claim
   time.
4. **Lock the exact attempt row `FOR UPDATE`**, addressed by its own
   `id`.
5. **Verify, under that lock, every one of**: the attempt's
   `envelope_id`/`workspace_id` match the envelope just locked; the
   attempt `id` is exactly the one this worker holds; `generation` is
   exactly the one this worker holds; `attempt_token` is exactly the one
   this worker holds; `status = 'open'`; and, where the result carries a
   provider-supplied identity (a callback's own reference, an
   acknowledgement's own message identity), **that identity corresponds
   to this attempt's own `provider_idempotency_key_used`** — a result
   whose provider-side identity does not match this attempt's own is
   never applied to it, regardless of how closely its other fields
   otherwise line up.
6. **Only once every check in steps 3 and 5 passes** does the transaction
   record the attempt's own outcome and converge the envelope, together,
   under the two locks already held.
7. **Commit atomically** — the attempt outcome and the envelope
   convergence are written together, or neither is.

**If any single check in step 3 or step 5 fails**: **write no result**
(neither the attempt nor the envelope is mutated); **do not converge the
envelope**; classify the report as stale or duplicate under the existing
bounded-observability rule (below) rather than as a new outcome; **never
convert a stale or mismatched report into a fabricated new failure** —
a report that fails fencing is evidence about the *reporting worker*
being stale or wrong, never evidence that the attempt itself newly
failed.

4. **On provider acceptance**: a fresh transaction runs the complete
   result-fencing predicate above in full — **never merely "the attempt
   is still `open`"** — and, only once every one of its checks passes,
   atomically records, under both locks already held, `status =
   'accepted'`, `provider_message_id`, `completed_at` on the attempt and
   the envelope's own `dispatching → sent` transition together with
   `provider_message_id`, `dispatched_at`, and `terminal_at` (both set
   together, once, to the same commit-time value) copied onto the
   envelope. A duplicate acceptance report (a redelivered provider
   callback, a retried acknowledgement) fails the predicate's own step 5
   (`status = 'open'` no longer holds, having already been consumed by
   the first, genuine acceptance) and is therefore already a safe,
   fencing-driven no-op — never a separate `UPDATE ... WHERE status =
   'open'` guard layered on top of a weaker check.
5. **On a caught failure calling the provider** — a genuine provider
   rejection, a local hard timeout (`T` above), or any other exception
   the dispatch code catches — **the identical result-fencing predicate
   above runs first**, and only once it passes does the same fresh
   transaction record the outcome, under both locks, against the
   attempt's own `failure_category`, and converge the envelope in one of
   three ways:
   - **The provider's own signal is inherently non-retryable** (a hard
     bounce, a permanently invalid recipient address, or any other
     provider-classified permanent rejection) — the attempt is recorded
     `failed_permanent` regardless of the retry ceiling, and the envelope
     transitions `dispatching → failed_permanent` with `terminal_at` set
     and `terminal_failure_category = 'provider_permanent_failure'`.
   - **The failure is retryable and the ceiling is not yet reached** —
     the attempt is recorded `failed_retryable`, and the envelope
     transitions `dispatching → ready` (no `terminal_at`, since `ready`
     is non-terminal).
   - **The failure is retryable in kind, but the ceiling is now
     exhausted** — the attempt is recorded `failed_retryable` at the
     attempt level (an honest record of what actually happened to this
     specific attempt), and the envelope transitions `dispatching →
     failed_permanent` with `terminal_at` set and
     `terminal_failure_category = 'retry_ceiling_exhausted'` (never
     `provider_permanent_failure`, which is reserved for a genuinely
     non-retryable provider signal, not ordinary ceiling exhaustion).

   Every branch above writes `dispatched_at = NULL` on the envelope, per
   the schema `CHECK` above, and happens in the same transaction as the
   attempt's own write — the identical bounded-retry-then-permanent shape
   this decomposition already establishes everywhere else. A failure
   report that fails the predicate (wrong attempt identity, stale
   generation/token, mismatched digest) is never allowed to manufacture
   any of the three writes above against whatever attempt happens to
   still be `open` — it writes nothing.
6. **Reclamation and envelope convergence — corrected to be atomic.**
   **Withdrawn: a reclaimer that abandons the expired attempt but leaves
   the envelope `dispatching`, with a new generation "becoming claimable"
   through no stated mechanism.** Codex correctly identified that nothing
   previously transitioned the envelope at all on reclaim, so it could
   remain `dispatching` indefinitely — no worker would ever claim it
   again, since claiming (step 1) only ever selects from `ready`. **A
   stale worker is fenced, and the envelope is converged, exactly as
   ADR-0035's own bulk-item attempts are — as one atomic sequence, under
   the same `envelope → attempt` lock order** every other step in this
   lifecycle now uses without exception (above):
   1. Lock the envelope row (the same `envelope → attempt` order every
      other step in this lifecycle uses).
   2. Lock and re-verify the specific expired-open attempt (still
      `status = 'open'`, still past `lease_expires_at`).
   3. Fence the expired generation: no further check against its own
      `(attempt_token, generation)` will ever pass (per step 2's own
      fencing check above, which already compares against the current,
      post-reclaim row state).
   4. Mark the attempt `abandoned`.
   5. **Derive the durable attempt count/ceiling** (`COUNT(*)` over this
      envelope's own terminal, non-`accepted` attempt rows, per step 8
      below — evaluated **after** step 4's own write, so the
      just-abandoned attempt is correctly included).
   6. **Transition the envelope, in the same transaction**: `dispatching
      → ready` if another bounded attempt is still permitted (no
      `terminal_at`, non-terminal), or `dispatching → failed_permanent`
      if the ceiling is now exhausted (`terminal_at` set in this same
      transaction, `dispatched_at` left null, and
      `terminal_failure_category = 'attempt_reclaim_ceiling_exhausted'`
      — deliberately **not** `retry_ceiling_exhausted`, since that value
      is reserved for the ordinary caught-failure path above; keeping
      them distinct lets a later audit tell whether the ceiling was
      reached because a worker actively reported failures or because
      attempts silently expired and were reclaimed) — the identical
      convergence step 5 above already performs for an ordinary caught
      failure, applied here for the reclaim path specifically.
   7. Commit — the abandonment and the envelope's own convergence happen
      together, or neither does; **no envelope can remain indefinitely
      `dispatching`**, since every path that can leave an attempt
      non-`open` (ordinary failure, provider acceptance, or reclaim) now
      also converges the envelope in the same transaction.
   **Only after this commits** does a new generation become claimable
   (the envelope is `ready` again, or terminally `failed_permanent` and
   no longer claimable at all). **A suspended worker that later wakes and
   reports against a now-reclaimed attempt is exactly the sixth named
   result path the complete result-fencing predicate above governs**: its
   report runs that same predicate, and step 5's own `attempt_token`/
   `generation`/`status = 'open'` checks now fail against the
   already-`abandoned` row, so it performs no provider call and writes
   no status, **even if it is unaware it has been reclaimed** — the
   predicate, not the worker's own awareness, is what prevents the stale
   report. The reclaimer's own abandonment write above is not itself a
   "result being reported" (it is the reclaimer independently observing
   lease expiry, never a delivery outcome), so it does not re-derive
   digests; it still follows the same `envelope → attempt` lock order and
   the same tenant-scoped, workspace-bound query discipline every other
   path in this lifecycle uses.
7. **Duplicate provider callback/result reporting** (a provider that
   redelivers its own webhook/acknowledgement) is the fourth named result
   path: it runs the identical result-fencing predicate above, and its
   step 5 finds `status <> 'open'` on the now-terminal attempt (or, on
   the rarer case of an in-flight redelivery racing the first report's
   own commit, is naturally serialised by the attempt row lock and then
   finds the same) — a safe no-op that writes nothing, never a bespoke,
   weaker check of its own.
8. **Retry-ceiling exhaustion** is computed from `COUNT(*)` over this
   envelope's own durable attempt rows with a terminal, non-`accepted`
   status — never a mutable counter capable of losing an increment to a
   rolled-back transaction, the same derivation discipline ADR-0035's own
   attempt ceiling already establishes.
9. **Membership/digest mismatch** (the envelope's own
   `sealed_rendering_basis_digest` or its own `dispatch_decision_digest`
   no longer matches what the attempt captured at claim time — which
   should be structurally impossible once each is set, since both are
   immutable once written, but is checked anyway as a genuine defensive
   verification, not a hypothetical one) fails the attempt closed at
   step 2: **withdrawn: describing this outcome as a "fenced/abandoned
   outcome"** (step 2's own earlier wording) while separately describing
   it as `failed_permanent` — the two are not the same attempt status,
   and this is a genuine, permanent, unrecoverable data-integrity fault
   (a further retry would simply repeat the same mismatch against the
   same corrupted/tampered inputs), never a merely-stale-worker
   situation. **Corrected**: the attempt is recorded `failed_permanent`
   with `failure_category` identifying which of the two digests
   mismatched, and the envelope converges `dispatching → failed_permanent`
   in the same transaction with `terminal_at` set and
   `terminal_failure_category = 'rendering_integrity_failure'` — never
   silently ignored, and never merely `abandoned` (that status remains
   reserved for the reclaimer's own lease-expiry path, step 6).
10. **The unavoidable provider-accepted-but-not-yet-recorded window
    remains honestly at-least-once** where the configured provider
    offers no idempotency guarantee of its own — restated precisely below.

#### Provider-call timeout bounded relative to the lease

**Withdrawn: leaving the relationship between the provider-call duration
and the attempt's own lease duration unstated.** Codex correctly
identified that an unbounded or under-specified provider call could run
past its own attempt's lease expiry, creating exactly the ambiguous
window a reclaimer and a still-working (merely slow) worker could both
reasonably believe they own. **Corrected — one normative invariant:**

- **`T`** — the provider client's own **hard** call timeout, enforced by
  the HTTP client itself (a connect/read timeout configured on the
  provider client, never an advisory queue-job timeout, which bounds the
  *job*, not the specific network call, and could fire at the wrong
  layer).
- **`L`** — the attempt's own `lease_expires_at` duration, set at claim
  time (and immediately **renewed/re-established to its full duration**
  the moment immediately before the provider call begins, per step 2's
  own re-verification above — never left to drift down from whatever
  remained since claim time).
- **`M`** — a configured minimum **result-reporting/recovery margin**:
  the time reserved, after the provider call could possibly finish (by
  hard timeout or genuine success), for step 4/5 above's own result-
  recording transaction to complete.
- **The required invariant, held configuration-wide**: `L >= T + M`. A
  normal provider call therefore always either finishes or is forcibly
  cancelled by its own hard timeout **before** the lease enters its
  reclaimable interval, and the result-recording transaction always has
  at least `M` of genuine margin to commit before a reclaimer could
  legitimately consider the same attempt expired.
- **Step 2's own re-verification (token/generation/envelope status/
  rendering-digest) happens immediately before the lease renewal and the
  provider call together** — renewing the lease is not a separate,
  later act that could itself race the verification; both happen inside
  the same short transaction, immediately preceding the (lock-free)
  provider call itself.
- **Reclaimer behaviour around clock skew and boundary equality**: the
  reclaimer's own query (`lease_expires_at < now()`) is a strict
  inequality — an attempt whose lease expires at exactly the reclaimer's
  own `now()` is **not** reclaimed on that pass (it is treated as still
  potentially valid until strictly past expiry), and the reclaimer itself
  runs on a bounded, configured poll interval that assumes ordinary,
  bounded clock skew between the process that set `lease_expires_at` and
  the process evaluating it — this ADR does not introduce a distributed
  clock-synchronisation mechanism of its own, consistent with the same
  assumption ADR-0025/0034's own existing lease-based mechanisms already
  make.
- **Stated honestly, not hidden**: abnormal process suspension (a paused
  container, a long GC pause exceeding `M`), a network-stack failure that
  delays the client's own timeout from firing, or a provider that accepts
  a request despite the local client believing it timed out, can each
  still fall within the **already-documented** at-least-once residual
  duplicate-email window ("Honest external-delivery guarantee" below) —
  the `L >= T + M` invariant makes this window **rare and bounded** under
  ordinary operation, it does not, and cannot, make it structurally
  impossible for every conceivable failure of the underlying network or
  operating system. **Stale-worker result reporting remains fenced and
  cannot corrupt envelope state regardless** — a suspended worker that
  wakes up after reclaim still fails step 2's own token/generation check,
  per the reclamation design above, whatever the provider itself did in
  the meantime. **A stable provider idempotency identity, reused
  unchanged across every attempt generation, mitigates this residual
  window specifically where the configured provider supports one** —
  restated from, and unchanged by, "Honest external-delivery guarantee"
  below.

#### Honest external-delivery guarantee, corrected

**Withdrawn: any claim that this design "never duplicates email," or an
equivalent exactly-once framing.** Codex correctly identified that a
database unique constraint governs only Laravel's own durable state — it
cannot, by itself, guarantee an external mail provider's own send is
exactly-once, because a crash can occur **after** the provider has
already accepted the message but **before** Laravel records that success
(`dispatched_at`/`status = 'sent'`). **Corrected, stated honestly:**

- **Laravel's own guarantee is durable at-least-once dispatch**, not
  exactly-once: an envelope is retried, via successive dispatch-attempt
  generations (per "Durable dispatch-attempt authority" above), until one
  attempt reaches `accepted` (converging the envelope to `sent`) or the
  envelope exhausts its bounded ceiling into `failed_permanent`, and a
  crash during an in-flight attempt is always safe to retry from
  Laravel's own side — the residual risk is confined entirely to the
  external provider-acceptance boundary, never to anything Laravel's own
  database state could itself duplicate.
- **Where the configured mail provider supports a caller-supplied
  idempotency identity**, each attempt's own `provider_idempotency_key_
  used` — derived deterministically from the envelope's own
  `envelope_key`, and therefore identical across every attempt generation
  for the same logical envelope — is passed as that identity, which
  eliminates the residual risk for that provider by construction (the
  provider itself refuses a second send for an identity it has already
  accepted). **This codebase's currently configured mail transport was
  not verified during this drafting to support such an identity** — this
  ADR states the mechanism to use if and when the underlying transport
  supports it, rather than asserting today's transport already does.
- **Where the provider offers no such guarantee**, a small, explicitly
  documented residual risk of a duplicate email remains, bounded to the
  narrow window, within a single attempt, between provider-acceptance and
  that attempt's own success-recording write (step 4 above) — an
  accepted, honestly stated V1 limitation, not a claimed impossibility.
- **Bounded retry, never unbounded**: identical ceiling/backoff shape to
  every other bounded-retry mechanism in this decomposition, now derived
  from durable attempt rows rather than a mutable counter (per "Durable
  dispatch-attempt authority," step 8).
- **The in-product notification itself is never lost or duplicated by
  any of the above** — its own idempotency identity (item 2 above) is
  fully database-enforced and exactly-once by construction, entirely
  independent of the email envelope's own external-boundary risk.
- **Provider failure never changes the originating governance result** —
  restated: by the time any envelope is even created, the domain
  transition and the notification it represents are already durably
  committed; an email provider's own permanent failure affects only
  that envelope's own terminal status, never the underlying document-
  governance fact.

#### Send-time eligibility and terminal suppression

**Withdrawn: two directly contradictory statements in the same
section — "eligibility is re-evaluated immediately before every dispatch
attempt" alongside "retries reuse the original member decisions without
re-evaluation."** Codex correctly identified these cannot both be true.
**Corrected — one consistent V1 rule, splitting what is checked, and
when, into two genuinely different moments: full preflight exactly once,
at generation-1 claim time, before generation 1 is created; a narrow,
bounded re-check at every retry's own claim time, before that retry's
attempt is created.** Both moments live inside "Durable dispatch-attempt
authority"'s own step 1 claim transaction (above) — never in a separate,
later step, and never after an attempt row already exists.

**At generation-1 claim, under the envelope lock, before generation 1 is
created — full preflight, frozen atomically:**

- **Every check runs exactly once, at this moment**: the recipient still
  exists and is active (not `disabled_at`); their current email is
  verified; their current workspace membership still exists; where the
  event/category carries a required authority (e.g. approval-capability-
  addressed events), they still hold it; the workspace's own `email_
  delivery_enabled` gate; their current personal category preference;
  and, absent one, the current workspace default — the identical
  precedence chain "Email policy and preferences" below already
  establishes.
- **Each sealed member independently receives an immutable delivery
  decision from this one preflight**: `included` or `suppressed`, each
  with its own typed, safe reason (the same closed `suppression_reason`
  vocabulary below, evaluated per member).
- **These per-member decisions are persisted atomically, together with
  `dispatch_decision_digest`, inside the claim transaction itself** — a
  new, append-only `DocumentGovernanceEmailEnvelopeMemberDecision` row
  per member, written in the same transaction that either opens attempt
  generation 1 or, on a zero-deliverable outcome, never opens one at all
  (below) — never in a step that runs after generation 1 already exists.
  `dispatch_decision_digest`, computed at this moment, covers exactly the
  final `included`, ordered member set, an immutable digest over the full
  suppression-decision set, and `sealed_rendering_basis_digest`'s own
  identity — **never the original sealed membership unconditionally, and
  never a recomputation of `sealed_rendering_basis_digest` itself**,
  which remains exactly as sealing fixed it.
- **If no member remains `included` after this one preflight**: **no
  external provider-facing attempt is opened at all** — no
  `DocumentGovernanceEmailEnvelopeAttempt` row is created for this
  envelope, ever, for this outcome; the envelope transitions directly
  `ready → suppressed` (never through `dispatching`) with
  `suppression_reason = 'no_deliverable_members'`, `terminal_at` is set
  (per the envelope schema below), and every member decision becomes
  terminally `suppressed`, all in the same atomic claim transaction, and
  this **consumes no retry ceiling**, needs no open-attempt uniqueness
  row, and needs no lease — there was never anything to lease.

**`suppressed` — a terminal, non-failure envelope outcome**, part of
`assembly_status` above: reached with a closed `suppression_reason`
(`workspace_email_disabled`, `personal_opt_out`, `recipient_disabled`,
`recipient_unverified`, `membership_removed`, `authority_lost`, `no_
deliverable_members`). Suppression is **terminal** (no further retry is
ever attempted — there is nothing transient about "this workspace has
email off"); **never affects the in-product notification**, which remains
exactly as durable and readable as any other; is **content-free and
fully auditable** (the `suppression_reason` is a bounded, safe scalar,
never free text); and is **never presented as, logged as, or counted
alongside, a provider failure** — it is a correct, deliberate non-
delivery, not an error.

**At every retry's own claim, under the envelope lock, before that
retry's attempt generation is created — a narrow, bounded re-check of
envelope-wide stop conditions only, never the full preflight again:**

- **Rechecked**: the recipient still exists and is active; their current
  email remains verified; their active workspace membership still
  exists; the workspace's own `email_delivery_enabled` gate remains
  enabled; their personal category preference has not newly opted out.
- **Deliberately *not* rechecked on retry**: per-member authority (e.g.
  approval capability) and the workspace default-versus-personal-override
  precedence beyond the stop conditions above — these were already
  decided, once, at the first preflight, and are never re-litigated per
  retry.
- **If any stop condition now fails**: the envelope transitions directly
  `ready → suppressed`, in the same claim transaction, with the
  corresponding reason, and `terminal_at` is set; **no new attempt
  generation is ever created for this outcome** — the provider is
  **never called**, and no open-attempt row, token, or lease is ever
  allocated for it; it is **never automatically reopened later if the
  failed condition is later reversed** (e.g. the workspace re-enables
  email) — restated from, and consistent with, suppression's own
  terminal nature above; a reversed setting only ever affects a *future*,
  genuinely new envelope, never resurrects this one.
- **Otherwise, the retry reuses — verbatim, never recomputed —**: the
  exact originally-frozen `included` member set; the exact template and
  branding identity sealed at sealing time; `sealed_rendering_basis_
  digest` sealed at sealing time; `dispatch_decision_digest` frozen at
  the first preflight; the exact provider idempotency key. **A member
  previously `suppressed` at the first preflight is never re-included on
  a later retry**, and **neither digest's own content is ever silently
  changed across attempt generations** — a retry is a re-attempt of
  exactly the same logical delivery decided once, not a fresh evaluation
  wearing the same envelope identity.

**Event/member-specific authority changes occurring after the first
external attempt has already begun are an explicitly documented,
narrow delivery-boundary limitation, not silently absorbed into mutable
retry content**: the email itself contains only the already-approved,
safe summary decided at the first preflight, and clicking through to any
linked destination always performs its own, independent, live
authorization check regardless of anything decided at send time (per
"Reconciling stored target routes with live authorization" above) — **a
later authority change must never mutate an in-flight retry into a
different logical provider message under the same idempotency key**;
the only two lawful outcomes for an existing attempt sequence are
"deliver exactly what was originally decided" or "suppress it via one of
the five stop conditions above," never a third, silently-altered
variant.

**No member is ever silently removed without durable evidence** — every
exclusion, at either the whole-envelope or the per-member level, is a
  recorded, typed, auditable decision, never a quiet omission.

**Honestly new work, stated plainly**: the `DocumentGovernanceEvent`
table, the queued jobs, the `DocumentGovernanceNotification`,
`DocumentGovernanceEmailEnvelope`, `DocumentGovernanceEmailEnvelopeMember`,
`DocumentGovernanceEmailEnvelopeAttempt`, and
`DocumentGovernanceEmailEnvelopeMemberDecision` tables, and every
migration/model/Action backing them are new application code this ADR
requires — nothing above is a relabelling of existing infrastructure.

### 6. Email policy and preferences

**V1 model, deliberately simple — not a marketing-preference centre:**

- **Email-eligible categories** are exactly the "Email-eligible: Yes" rows
  in the event-vocabulary table above — a closed set, not a per-event
  toggle matrix.
- **Withdrawn: one `email_enabled_by_default` field asked to mean both
  a workspace-wide hard gate and an inheritable per-category default.**
  Codex correctly identified these as two different concepts that a
  single boolean cannot express without ambiguity. **Corrected: two
  explicit settings, on `workspace_notification_settings` (new, one row
  per workspace)**:
  - **`email_delivery_enabled`** (boolean) — the **hard workspace gate**.
    When `false`, no email is ever dispatched for this workspace, for any
    user, regardless of any personal preference.
  - **`default_email_enabled`** (boolean) — the **inherited default**
    applied to a user's own delivery **when `email_delivery_enabled` is
    `true` and that user has set no personal category preference of their
    own**.
  A **personal override**, set by any user for their own account (new:
  `user_notification_preferences`, one row per user per category-group),
  is the third input. **Precedence, stated as an ordered sequence:**
  1. If `email_delivery_enabled = false`, no email is sent — this check
     is evaluated first and short-circuits every other input.
  2. Otherwise, a personal category override, if the user has set one,
     wins.
  3. If no personal override exists, `default_email_enabled` applies.
  4. **In-product notifications are never disabled by either setting** —
     restated from the settled decision, and now stated as the final,
     unconditional step so no reading of the three-input precedence above
     could be misread as touching the in-product record at all.
  This is the "owner can disable email globally" case named in the
  brief, resolved as the hard `email_delivery_enabled` gate specifically
  — never expressed as an extreme value of the same field a personal
  override also reads, which is exactly what made the withdrawn single-
  field design ambiguous.
- **Who may manage these settings, stated consistently with the settled
  administration model**: active workspace `Owner`s and `Admin`s may
  manage `workspace_notification_settings` for their own workspace —
  the same ordinary workspace-administration capability ADR-0025 already
  grants them for other workspace-level configuration, **never** a grant
  of platform-level authority (ADR-0026's separate, workspace-independent
  platform-administrator plane is entirely untouched by this ADR).
- **Users cannot disable the corresponding in-product notification** —
  preference scope is delivery-channel only (email on/off); the
  authoritative in-product record (per the settled decision) is never
  gated by this preference.
- **Digest, not one-off, for review reminders specifically**:
  `governance.review.due_soon`/`overdue` are **grouped into one daily
  digest email per recipient per workspace** (never one email per
  document) when more than one is due for the same recipient on the same
  day — every other email-eligible event is sent immediately, since each
  already represents one batch/operation-level fact, not a per-document
  one.
- **No verified email**: Laravel's own existing `email_verified_at`
  gate (already present via Fortify, ADR-0005) is checked before
  dispatch; an unverified address never receives a notification email —
  a silent, logged no-op, never a retried failure.
- **Disabled user**: no email is dispatched to a `disabled_at`-set
  account, checked at send time (not merely at notification-creation
  time), consistent with "disabled/removed users do not receive new
  delivery" above.
- **Address changed between creation and send**: Laravel's mail dispatch
  always resolves the recipient's **current** email address at send time
  (the `Notifiable` contract's own `routeNotificationForMail()`,
  evaluated at dispatch, not captured earlier) — never a stale address
  frozen at notification-creation time.
- **Bounce/provider failure**: treated as a retryable delivery failure on
  the email envelope's own bounded ceiling (per "Email-delivery envelope
  model, corrected" above), exactly like any other transient failure in
  this decomposition (ADR-0007/0031/0034's own established bounded-
  retry-then-permanent-failure shape) — never retried forever, and a
  terminal email failure never retroactively affects the in-product
  notification's own already-durable existence.
- **Rate limiting/notification storms**: the digest grouping above is the
  primary defence for reminders; for everything else, this ADR's V1
  bound is structural, not a separate rate limiter — because every event
  is batch/operation-scoped (never per-document), a single large import
  or bulk operation already produces exactly one notification/email, not
  hundreds, by construction. A dedicated per-user email rate limiter is
  explicitly deferred (below) as unneeded for V1's own bounded event
  shapes.
- **Workspace-wide email disable**: yes, an Owner or Admin may set
  `email_delivery_enabled = false` for the entire workspace — the hard
  gate above, evaluated before any personal preference is even
  consulted, consistent with treating it as an organisational policy
  decision no individual member can unilaterally reverse.

### 7. Reminder scheduling

**One new daily scheduled command, `governance:scan-reminders-and-
authority-transitions`, registered in `routes/console.php` on the exact
existing `Schedule::command()->withoutOverlapping()` pattern, at a new
`dailyAt()` cadence** (this codebase's first daily-cadence scheduled
command, per "Context" above):

- **Configurable "due soon" lead time**: `REVIEW_DUE_SOON_LEAD_DAYS`
  (default **14**, a sensible V1 default consistent with the brief's own
  worked example), workspace-wide — one value per deployment for V1, not
  yet a per-workspace override (explicitly deferred below if a genuine
  need is demonstrated).
- **Timezone/date-boundary authority**: the scanner runs once daily, in
  UTC, comparing `review_due_date` (a plain date, per ADR-0030) against
  `CarbonImmutable::today('UTC')` — a document's review-due date is a
  calendar date, not a timestamp, so no per-workspace timezone
  configuration is required for V1; a family's `review_due_date` means
  the same calendar day everywhere.

#### Reminder and authority-transition occurrence identities, corrected

**Withdrawn: a separate `DocumentReviewReminderLog` table, `UNIQUE
(workspace_id, document_family_id, reminder_kind, due_date)`, as its own
idempotency authority alongside `DocumentGovernanceEvent`'s own
`occurrence_key`.** Codex correctly identified that maintaining two
independently-enforced uniqueness boundaries for the same underlying fact
creates exactly the "two fields that can disagree" hazard this
decomposition already rejects everywhere else — a partial failure could
leave the log written but the event missing, or the reverse, with nothing
to reconcile them against each other. **Corrected: `DocumentGovernance
Event.occurrence_key` (per "Event-occurrence identity, corrected" above)
is the one and only idempotency authority for every reminder and
authority-transition occurrence. No separate reminder-log table exists.**
The scanner's own idempotency step is simply
`INSERT INTO document_governance_events (..., occurrence_key, ...)
... ON CONFLICT (workspace_id, occurrence_key) DO NOTHING` — the same
statement shape every other event family in this ADR already uses, not a
second mechanism.

**Withdrawn, for the same reason: `governance.authority.attained`'s
identity depending on "newly satisfies... since the previous scan," with
no durable scan watermark to make that comparison meaningful.** A
scheduler-run-relative identity cannot be idempotent across a missed run
or an overlapping process, because "the previous scan" is not itself a
durable, queryable fact this design established anywhere. **Corrected:
every reminder/authority `occurrence_key` is derived from the qualifying
row's own current, authoritative condition — never from "what changed
since we last looked":**

- **`governance.review.due_soon`**: `(document_family_id, 'due_soon',
  review_due_date)` — the family's own identity, the reminder kind, and
  its **current** `review_due_date`.
- **`governance.review.overdue`**: `(document_family_id, 'overdue',
  review_due_date)`.
- **`governance.authority.approaching`**: `(document_id,
  'authority_approaching', effective_from)` — the version's own identity,
  the transition kind, and its current `effective_from`.
- **`governance.authority.attained`**: `(document_id, 'authority_attained',
  authority_start)` — keyed on the version's own computed `authority_start`
  (ADR-0017's `max(effective_from, approved_at)`), which is itself stable
  once approval has happened, per ADR-0017's own one-directional
  governance model.
- **`governance.authority.blocked`**: `(document_id, 'authority_blocked',
  blocking_successor_document_id)` — keyed on the version's own identity
  *and* the specific successor's identity that caused the block, per
  ADR-0017's own "blocked" condition; a different successor later
  producing the same block is a genuinely distinct occurrence, correctly
  notified again.

**The scanner queries every currently-qualifying row and inserts each
occurrence idempotently — it never depends on remembering the previous
scan to avoid a duplicate.** The `WHERE` clause is always a **current-
condition** predicate (`review_due_date <= :today + lead_days`,
`authority_start <= :today`, the "blocked" join condition itself), never
a delta against a remembered prior state; the `occurrence_key`'s own
uniqueness is what prevents re-notifying an unchanged fact, exactly as
review reminders already worked in the withdrawn design, now applied
uniformly to every reminder/transition kind.

**Specified scenarios, explicitly:**

- **A review date changes, then changes back to its original value**:
  the original `(family, due_soon, D1)` occurrence already exists; when
  the date returns to `D1`, the scanner recomputes the identical
  `occurrence_key` and the insert is a safe no-op — the recipient is
  **not** re-notified for a date they were already told about, even
  though the family's own date value visited an intermediate `D2` in
  between. This is a deliberate choice: the reminder communicates a fact
  about a due date, not about the history of edits to it.
- **An authority date (`effective_from`) changes before attainment**: the
  old `(document_id, authority_approaching, E1)` occurrence remains a
  harmless historical fact (dashboard-only, never individually notified);
  the new `effective_from = E2` produces its own new, distinct
  `occurrence_key`, correctly surfaced on the next scan. No retraction of
  the stale `E1` occurrence is needed, since the dashboard card itself
  always reads the version's **current** `effective_from` live, never the
  historical event log.
- **An approaching reminder was emitted, then the schedule changed**:
  identical to the point above — the emitted occurrence is a true
  historical fact ("this was approaching, as of that date"), and the
  live dashboard card, not the event log, is what the user actually sees
  reflecting the current schedule.
- **The scheduler misses multiple days**: every `WHERE` predicate above
  is a current-condition query (`<=`, not `=`), so a late-running scan
  simply finds every row that newly qualifies as of *today*, regardless
  of how many days were missed — there is no "catch-up mode" distinct
  from the ordinary scan, and no gap in which a qualifying row is
  silently skipped.
- **Two scheduler processes overlap despite `withoutOverlapping()`**:
  Laravel's own scheduler mutex is the first line of defence and is
  expected to prevent this; **if it were ever to fail** (e.g. a lock
  backend outage), `occurrence_key`'s own database `UNIQUE` constraint is
  the second, structural line of defence — both processes computing the
  identical deterministic key for the identical qualifying row, with the
  second `INSERT ... ON CONFLICT DO NOTHING` losing harmlessly. Overlap
  can never produce a duplicate event or duplicate notification, only,
  at worst, redundant read work.
- **An event exists but its notification projection has not yet run**:
  not a special case — this is the ordinary outbox-claim window "Reliable
  production and delivery" above already covers (`published_at IS NULL`
  until the projector claims the row); the reminder scanner's own job is
  finished the moment the event row is durably inserted, and projection
  proceeds on its own, independent cadence.

- **Cleared review date**: `review_due_date IS NULL` is simply excluded
  from the scan's own `WHERE` clause — no reminder, no error, no residual
  work-queue entry (the dashboard card's own live query already excludes
  it identically).
- **Owner changed after scheduling**: recipient resolution (above) always
  runs at projector time against the **current** `owner_user_id` — an
  owner change after a reminder event was inserted, but before its
  notification was projected, is resolved correctly without needing the
  scanner to care about ownership at all; ownership is a recipient-
  resolution concern, not a scheduling one.
- **Overdue, without daily spam**: `overdue` is its own occurrence kind
  under the same `occurrence_key` scheme, inserted **once** per distinct
  `review_due_date`, on the day it first qualifies — not re-inserted every
  subsequent day the document remains overdue, since the `WHERE
  review_due_date < :today` predicate keeps matching the same row but
  `occurrence_key` (which does not include "today's date") keeps
  colliding with the already-inserted occurrence. Ongoing visibility for
  a still-overdue document is the dashboard card's own live query (per
  "Notifications versus actionable work"), never a second, third, or
  infinitely-repeated notification.
- **A document/version deleted before its reminder fires**: the daily
  scan's own `WHERE` clause only ever selects currently-live rows — a
  deleted family/version is simply absent from the next scan; no error,
  and no orphaned reference this design ever needs to null out, since
  there is no separate reminder-log table left to hold one.

**Review date remains advisory, restated**: reaching a review-due date,
or even passing it, **never** withdraws a version, changes its
`authority_start`, or narrows retrieval eligibility — ADR-0017's own
authority model is untouched; this ADR only observes and reminds.

### 8. Dashboard and navigation projections

**Every card below is a live query against authoritative domain tables,
never a stored/cached count this ADR itself owns as independent
authority** — consistent with "Notifications versus actionable work"
above, applied uniformly.

**Withdrawn: presenting the illustrative predicates in the prior draft's
table (e.g. `technical_status = 'INDEXED' AND governance_status =
'DRAFT'` for "awaiting approval") as though they were a verified,
authoritative restatement of ADR-0031/ADR-0033's own governance
definitions.** Codex correctly flagged that a simplified predicate
sketched inside this ADR risks silently omitting a legitimately
actionable item, or including a technically-indexed-but-governance-
ineligible one, if it ever drifts from what ADR-0031/0033 actually
decided. **Corrected, as a standing rule for every row in this table:**

- **The domain state machine defined by ADR-0017/0030/0031/0033/0034/
  0035 is the sole authority for what counts as "awaiting approval,"
  "processing," "failed," "scheduled," "review due," or any other
  dashboard predicate this ADR presents.** This ADR does not redefine,
  narrow, or simplify any of them.
- **Every dashboard query in this ADR is implemented as a named
  repository query object/projection that calls into, or is reviewed
  directly against, the frozen ADR's own accepted eligibility definition**
  — never a bespoke predicate invented inside this ADR's own migration or
  controller layer. Any SQL fragment shown in this ADR's own table below
  is **illustrative of intent only** and must not be read as, or
  implemented as, a substitute for the frozen ADR's own definition; where
  the two could ever appear to differ, the frozen ADR governs and this
  ADR's own illustration is what must be corrected.
- **Capabilities are rechecked twice, independently of the dashboard
  count itself**: once when the drill-down route is opened (that route's
  own existing authorization check, unmodified by this ADR), and again
  when the underlying action is actually performed (that action's own
  existing authorization check) — a dashboard card's own count is a
  read-time convenience, never itself an authorization decision.

| Card | Illustrative query basis (see above — the frozen ADR's own definition governs) |
|---|---|
| Awaiting approval | Every version genuinely eligible for `ApproveDocumentVersion` under ADR-0017/ADR-0031's own accepted eligibility definition |
| Imports still processing | `ImportItem`/`ImportBatch` rows in a non-terminal technical state, per ADR-0034's own state machine |
| Failed/warning imports | `ImportItem` rows in a failure/needs-action state per ADR-0034, plus `bulk_operation.completed_with_exceptions`/`failed_before_execution` per ADR-0035's own terminal-mapping function |
| Scheduled changes | `Document` rows with `effective_from` in the future, or matching the current-condition `authority_attained`/`authority_blocked` predicates per "Reminder and authority-transition occurrence identities, corrected" above — never a "since the last scan" delta |
| Review due soon | `DocumentFamily.review_due_date` inside the configured lead time, per ADR-0030's own field definition |
| Overdue reviews | `DocumentFamily.review_due_date` in the past, per ADR-0030's own field definition |
| Recent completed imports | `ImportBatch` rows reaching a terminal outcome, most-recent-first, bounded page size |
| Recent bulk-operation outcomes | `BulkOperation` rows reaching a terminal outcome (ADR-0035's own terminal-mapping function), most-recent-first |
| Notification unread count | `COUNT(*)` on `DocumentGovernanceNotification`, per "In-product notification model" above |
| Recent-activity feed | A bounded, most-recent-first union of the above terminal events, **never** raw document content in its preview text — only the same safe scalar summary (count, title, outcome category) the notification `parameters` themselves are already restricted to |

**Every card drills into the relevant existing filtered route** (the
library's own filter/query parameters, ADR-0033's own route hierarchy,
ADR-0035's own `/documents/bulk/{bulkOperationPublicId}` detail route) —
this ADR introduces no new, parallel "dashboard-only" data model
duplicating what those routes already expose.

**Empty states teach, they do not merely say "nothing here"**: a
zero-document workspace's dashboard explicitly encourages uploading "a
small, useful starter set" and trying a real question against it as soon
as those documents are searchable — **it never states or implies a
minimum document count (not 100, not 300, not any number) before search
or onboarding is meaningful**, matching the settled decision precisely.
A large tenant's recent-activity feed is bounded (a fixed page size, most
recent first, "view all" linking to the full filtered route) — it never
grows into an unusably long, unpaginated list.

### 9. Browser UX and accessibility

**Placement**: a notification bell in the shell's account-controls
region (ADR-0027's stable primary region, alongside the theme toggle),
never a new third navigation region. **Unread indicator, settled V1
value**: a small, bounded badge, capped visually at **`99+`** — never an
unbounded literal count that could itself leak scale information — with
an `aria-label` stating the **exact** underlying count in words
regardless of the visual cap (e.g. `aria-label="147 unread notifications"`
even while the badge itself reads `99+`), satisfying "accessible text
equivalent" without relying on colour or a bare digit alone, and without
the accessible name itself being rounded down to the same visual ceiling
the badge uses. **Inbox**: a themed `Sheet`/panel (the same Radix-`Dialog`-backed pattern ADR-0027
already establishes for the mobile navigation drawer, reused rather than
a bespoke overlay), keyboard-operable (`Escape` to close, focus trapped
and returned to the trigger, arrow-key list navigation), with `role="log"`
or an equivalent live-region treatment for genuinely new arrivals —
**bounded**: a summary announcement per batch of new notifications, never
a per-notification screen-reader interruption stream.

**Actionable versus informational, visually distinct**: `severity =
'action_required'` rows carry a distinct accent/icon treatment (never
colour alone, per ADR-0027's own accessibility baseline), sit above
purely informational rows within the same list rather than only being
distinguishable by reading each row's text.

**Friendly wording, contextual tooltips**: every notification and every
dashboard card uses plain language ("Review due soon" rather than
"`governance.review.due_soon`"), with a tooltip beside genuinely
unfamiliar terms (e.g. what "applicability successor" means in plain
language) — the same tooltip discipline ADR-0033/0035 already establish,
applied here.

**Tenant-safe links**: every notification's effective route, where one is
returned at all, is resolved and supplied by Laravel fresh per request
(per "Reconciling stored target routes with live authorization" above),
already tenant-scoped and re-authorised; the browser never assembles a
route from a raw identifier embedded in a notification's own payload.

**Theme/responsive/loading/empty/partial-failure/stale-target states**:
light and dark themes throughout (ADR-0027's existing token
architecture, no new palette introduced); mobile renders the inbox as a
full-width sheet, matching the existing navigation-drawer pattern; a
loading skeleton, a genuine empty state (distinct from "zero unread,
but history exists"), a partial-failure state (some notifications loaded,
one page failed) that never silently drops the rest of the list, and the
"removed/deleted target" state (inert label, no dead link) from "In-
product notification model" above are each an explicit, designed state,
not an implicit fallthrough.

**Required staged visual checkpoints, in this order, each requiring a
direct browser URL or screenshot and David's explicit approval before the
next surface is built**: (1) notification bell and unread state; (2)
notification inbox/list; (3) empty state; (4) actionable-work
presentation; (5) awaiting-approval dashboard card and drill-down; (6)
review-due/overdue presentation; (7) processing-complete and processing-
warning notifications; (8) scheduled-activation notifications; (9)
bulk-operation outcome summary; (10) notification preferences; (11)
mobile layout; (12) light and dark themes; (13) keyboard/focus/
accessibility behaviour; (14) removed-target/no-destination state.

### 10. Security and privacy

- **Workspace-scoped composite integrity** wherever a live relational
  reference exists (`workspace_id` paired with every scoped FK, exactly
  as ADR-0035's own composite-FK/trigger discipline already establishes)
  — no notification, event, or reminder row is ever reachable or
  resolvable outside its own workspace.
- **Tenant-safe `404`, never `403`**, for any notification whose target
  has become cross-workspace-unreachable or was never accessible to the
  requester — the same concealment rule ADR-0006/0027 already establish,
  applied identically here.
- **Safe structured template parameters, never arbitrary HTML/prose** —
  `parameters` is a closed, allowlisted JSON shape per `template_key`,
  validated at write time; rendering escapes every interpolated value
  exactly as every other user-facing string in this decomposition already
  does (ADR-0027's own rendering rules).
- **No storage URLs, ever, in a durable notification** — a notification
  may reference a document by its safe scalar identity/label; it never
  embeds a signed or unsigned object-storage URL of any kind.
- **No document body, unreviewed/unsafe filename, extracted text,
  evidence excerpt, prompt, or provider output** in any email, log, or
  telemetry surface this ADR produces — the same allowlist-over-blocklist
  discipline ADR-0035's own audit design already applies, restated and
  enforced identically for every surface this ADR adds.
- **Log-safe, stable event keys** (`import.batch.completed_with_exceptions`,
  never a raw filename or free-text description) as the only thing
  written to structured logs/metrics describing what happened.
- **Recipient-enumeration protection**: notification-list/unread-count
  endpoints are scoped to `auth()->id()` exclusively — there is no
  endpoint that accepts an arbitrary user identifier to inspect another
  user's notifications, so there is no enumeration surface to close
  beyond ordinary authentication.
- **Bounded payload sizes**: `parameters`/`payload` JSON columns are
  schema-validated against a closed shape (above), which is itself a
  bound; an explicit byte-size ceiling is additionally enforced at write
  time as defence in depth.
- **Every notification's effective route re-authorises on every
  request** — a notification's own historical existence never
  substitutes for a live authorization check against its target's
  destination, since no route is ever stored, only resolved fresh per
  "Reconciling stored target routes with live authorization" above; **an
  old notification never grants access to a now-restricted target**,
  since clicking through still re-runs that destination's own,
  unmodified authorization logic (ADR-0031/0033/0035's own existing
  checks), unaffected by this ADR.
- **Safe historical display label, bounded**: `target_display_label`
  follows ADR-0035's own precedent exactly — captured once, at
  notification-creation time, bounded to 255 characters, escaped/safely
  rendered, never re-derived from a live join once the target is gone.

### 11. Retention and deletion

- **A notification never blocks deletion of its source domain object** —
  `source_event_id`/target references are scalar, following ADR-0025's
  content-free-correlation precedent, never a live, blocking FK.
- **Document/version/family deletion**: the notification and its
  originating outbox event remain, unmodified, readable via their own
  scalar identity and `target_display_label`; no route was ever stored to
  become stale, so there is no write-time cascade this ADR would
  otherwise need to perform against every historical notification on
  every deletion — the next render simply resolves no effective route,
  per "Reconciling stored target routes with live authorization" above.
- **Import staging expiry**: identical treatment — an expired
  `ImportBatch`/`ImportItem` never blocks or is blocked by any
  notification referencing it.
- **User removal/disablement**: `recipient_user_id` is `nullOnDelete()`
  for a genuine account deletion (rare) — the row's own durable meaning
  is unaffected, since `recipient_user_public_id` (never nulled) remains
  the identity every historical record and every uniqueness guarantee
  actually depends on, per "In-product notification model" above. For the
  far more common "disabled" or "removed from workspace" cases, the row
  is untouched entirely (the user account still exists; they simply stop
  receiving *new* delivery and their actionable-work projections stop
  counting them, per "Recipient resolution" above) — their own historical
  notification list, if they are later re-enabled, remains exactly as it
  was.
- **Workspace deletion**: out of scope for V1 (no accepted ADR currently
  defines whole-workspace deletion); `workspace_id` uses
  `restrictOnDelete()` consistent with every other workspace-scoped table
  in this decomposition, deferring the question to whichever future ADR
  defines workspace deletion, exactly as those other tables already do.

#### Settled V1 retention values, corrected

**Withdrawn: describing `DocumentGovernanceEvent`'s own 180-day
operational retention as "deliberately longer than either notification
retention window" while the notification windows are 90 and 365 days.**
Codex correctly identified the arithmetic error — 180 is shorter than
365, so the outbox event backing a `warning`/`action_required`
notification could expire and be purged **before** the notification it
produced, and before that notification's own longer retention window
even elapsed, defeating the entire point of "the event lineage does not
expire before the longest-lived notification projection." **Corrected,
closed V1 policy:**

- **Informational (`severity = 'info'`) notifications**: retained **90
  days**, unchanged.
- **`warning`/`action_required` notifications**: retained **365 days**,
  unchanged.
- **`DocumentGovernanceEvent`**: retained **400 days**, counted from
  successful full projection (every eligible notification created) or
  terminal poison-event failure — genuinely longer than the longest
  notification window (365 days), by construction, not merely by
  assertion.
- **Terminal email envelopes, their attempts, and their membership
  evidence** (`DocumentGovernanceEmailEnvelope`/`...Attempt`/`...Member`):
  retained **400 days**, counted from the envelope's own `terminal_at` —
  **the single common anchor for all three terminal states
  (`sent`/`failed_permanent`/`suppressed`) alike**, never `dispatched_at`
  (which is `sent`-specific provider-acceptance evidence only and is
  never set at all for `failed_permanent` or `suppressed`, per the
  envelope schema `CHECK` above). **Withdrawn: anchoring this window on
  `dispatched_at`.** Codex correctly identified that doing so would leave
  every `failed_permanent` and `suppressed` envelope permanently
  unpurgeable, since neither outcome ever sets `dispatched_at` — the same
  window as the originating event, for the same reason: delivery lineage
  must not expire before the notification projections it produced, and
  every terminal outcome, not only a successful send, must eventually
  age out.

Both tables' retention is enforced by the same daily scheduler this ADR
already introduces. **None of the above describes the notification,
event, or email tables as permanent audit storage** — every one of these
windows is finite, and none of them substitutes for, extends, or competes
with ADR-0030/0031/0034/0035's own accepted audit retention, which this
ADR does not modify and has no authority over.

#### Purge sequencing, stated explicitly

**One ordered sequence, closing the "which table may be purged before
which other one" question the brief raises:**

1. **Expired notification rows are purged first, and are never blocked
   by an email-envelope membership reference** — `DocumentGovernanceEmail
   EnvelopeMember.notification_id` is a plain, non-blocking reference
   (`nullOnDelete()`-eligible, never `restrictOnDelete()`), per "Email-
   delivery envelope model, corrected" above; a notification's own
   90/365-day expiry can proceed independently of whether any envelope
   referencing it has itself expired yet.
2. **Envelope membership retains its own immutable scalar evidence**
   (`source_event_id`, `recipient_user_public_id`, per the membership
   schema above) **specifically so it remains fully intelligible as
   delivery evidence after its notification row is purged** — a purged
   notification never leaves an unintelligible orphan membership row
   behind.
3. **Envelope members and attempts are purged together with their parent
   terminal envelope**, once 400 days have elapsed **since that
   envelope's own `terminal_at`** — never independently, never anchored
   on `dispatched_at` (unset for two of the three terminal outcomes), and
   never before the envelope itself is terminal
   (`sent`/`failed_permanent`/`suppressed`, each reachable and each
   equally eligible for purge once its own window elapses); a
   non-terminal envelope (`assembling`/`ready`/`dispatching`, still
   within its own assembly or retry lifetime, `terminal_at` still null)
   is never eligible for purge regardless of age, and children can never
   indefinitely block a purge that `terminal_at` itself already permits.
4. **The originating `DocumentGovernanceEvent` may be purged only once
   all three of the following hold**: (a) it has reached a terminal
   projection outcome — successfully projected (every eligible
   notification created) or terminally poison-failed; (b) its own 400-day
   retention window has elapsed; (c) **no non-terminal projection or
   delivery recovery still depends on it** — concretely, no notification
   this event produced is still within its own retention window with a
   still-non-terminal envelope attached, and no reconciliation/replay
   pass currently references this event's own `occurrence_key`. Because
   the event's own window (400 days) already exceeds every notification
   window it could have produced (at most 365 days) plus every envelope
   window it could have produced (400 days, but only starting once that
   envelope reaches its own terminal state, which is always reached
   before or alongside the event's own terminal projection outcome), this
   condition is satisfied by construction in the ordinary case; it is
   still checked explicitly, never assumed, as a genuine purge-time
   guard.
5. **None of the above affects the authoritative audit records owned by
   ADR-0030/0031/0034/0035** — restated, unchanged: this ADR's own tables
   are notification/delivery projections with their own finite,
   independent retention, never a second audit mechanism those ADRs'
   own audit tables need to be reconciled against.

#### Projection receipts — preserving replay idempotency after
#### notification expiry

**Withdrawn: a single receipt table using a nullable `recipient_user_
public_id` and an ordinary `UNIQUE` constraint to represent "one
completion row per event."** Codex correctly identified that PostgreSQL
does not treat two `NULL` values as equal for uniqueness purposes — a
`UNIQUE` constraint over a column that is `NULL` for every completion row
enforces **nothing** against a second completion row for the same event;
nothing actually guaranteed "exactly one" of them. **Also withdrawn: a
`projection_outcome` value (`suppressed_no_recipient`) that was never
declared in the enum it was supposed to belong to.** **Corrected: two
structurally separate tables, one per genuinely different concept.**

**`DocumentGovernanceEventProjection`** — the sole event-level completion
authority, exactly one row per source event, no nullable-recipient
sentinel of any kind:

| Column | Constraint |
|---|---|
| `id` | Internal identity |
| `workspace_id` | Scoping |
| `source_event_id` | The originating event's own immutable identity |
| `state` | Bounded enum: `resolving`, `projecting`, `completed`, `failed` |
| `resolved_recipient_set_digest` | Non-nullable once `state` leaves `resolving` — an immutable digest over the complete, frozen recipient set this projection resolved (below), including the deterministic empty-set digest when no recipient was found |
| `started_at`, `completed_at` | `completed_at` non-nullable if and only if `state ∈ {completed, failed}` |
| `attempt_count`, `last_error` | Bounded failure/retry evidence, the same discipline as every other bounded-retry mechanism in this ADR |

**`UNIQUE (workspace_id, source_event_id)`** — a genuine, unconditional
uniqueness guarantee, since every column in the tuple is always
non-null; this is what "exactly one event projection per source event"
actually means as a database-enforced fact, not merely an intended
convention.

**`DocumentGovernanceNotificationProjectionReceipt`** — one row per
resolved recipient, and **only** per resolved recipient; no event-level
row of this kind exists at all:

| Column | Constraint |
|---|---|
| `id` | Internal identity |
| `workspace_id` | Scoping |
| `event_projection_id` | FK to `DocumentGovernanceEventProjection (id)` — see the composite-target note below |
| `recipient_user_public_id` | **Non-nullable, immutable** — every row of this table describes exactly one real, resolved recipient; there is no shape in which this column is ever null |
| `outcome` | Bounded enum: `pending`, `notification_created`, `suppressed` |
| `suppression_reason` | Nullable, the same closed typed vocabulary "Send-time eligibility and terminal suppression" above already establishes, populated if and only if `outcome = 'suppressed'` |
| `notification_public_id` | Nullable — the resulting notification's own scalar public identity, populated if and only if `outcome = 'notification_created'`; **not** a live FK, so it survives the notification row's own later expiry/purge |
| `created_at`, `resolved_at` | `resolved_at` non-nullable if and only if `outcome <> 'pending'` |

**`UNIQUE (event_projection_id, recipient_user_public_id)`** — a genuine,
unconditional constraint, for the identical reason: both columns are
always non-null, on every row, by the table's own definition. **A
composite target is declared to bind this cleanly**: `DocumentGovernance
EventProjection` additionally declares `UNIQUE (id, workspace_id)`, and
this receipt table's own `(event_projection_id, workspace_id)` is a
composite FK against it — safe as a composite FK here for the identical
reason ADR-0035's own attempt-table pattern is safe: an event projection
row is never deleted independently of its own terminal retention purge
(below), so `ON DELETE` behaviour is never exercised prematurely.

**No content, route, email address, or rendered wording of any kind, on
either table** — restated, unconditional: these tables exist purely to
answer "was this recipient already resolved and notified for this
event," never to reconstruct or re-display what they were told.

#### Recipient-set freezing and crash recovery

**One transaction, before any recipient notification is produced:**

1. Lock (or create, via `INSERT ... ON CONFLICT (workspace_id,
   source_event_id) DO NOTHING` followed by a locking re-read) the event
   projection row.
2. **Resolve the complete recipient set exactly once, against one
   consistent database snapshot** — the same recipient-resolution rules
   "Recipient resolution" above already establishes, evaluated once, not
   once per member.
3. **Insert the non-null per-recipient receipt rows, in this same
   transaction**, each `outcome = 'pending'` initially — one row per
   resolved recipient, never a nullable placeholder for "no recipients
   yet decided."
4. Compute and persist `resolved_recipient_set_digest` over the now-frozen
   set (the deterministic empty-set digest specifically, if the resolved
   set is genuinely empty — a well-defined, stable value, never a null or
   absent digest).
5. Transition `resolving → projecting`.
6. Commit.

**If the resolved set is empty**: the event projection still reaches
`completed` (via `projecting → completed` once zero pending receipts
remain, trivially true immediately) with its own deterministic empty-set
digest recorded — **no nullable-recipient receipt row is ever fabricated**
to represent "no one was notified"; the *absence* of any receipt row
under a `completed` projection **is** that fact, honestly.

**Subsequent workers process only the frozen receipt rows** — every
per-recipient job claims a `pending` receipt (by the same `SELECT ... FOR
UPDATE SKIP LOCKED` pattern this ADR already uses elsewhere), **never
re-resolves current recipients for that historical occurrence**. For
every recipient: notification creation and the transition of its own
receipt to `notification_created` commit **atomically, in the same
transaction**; an intentional no-delivery result (the same send-time
eligibility rules "Send-time eligibility and terminal suppression" above
already define, applied at the notification-projection layer for
recipients who were never eligible even for an in-product notification —
e.g. removed before projection ran) transitions the receipt to
`suppressed` with its own typed reason; **duplicate processing of the
same receipt is idempotent**, guarded by `UPDATE ... WHERE outcome =
'pending'`. **The event projection reaches `state = 'completed'` only
once every one of its own frozen receipts has reached a terminal outcome**
(`notification_created` or `suppressed`) — never before, and this
convergence check is itself the same kind of read-time derivation this
ADR already uses for every other "am I done yet" question, never a
separately-maintained counter.

**Bounded batching, if a recipient set is large enough to warrant it**:
permitted **only** for step 3's own frozen `INSERT` (splitting a large
resolved set's own receipt-row creation into bounded batches, cursor-
paginated), **never** for step 2's own resolution — the snapshot in step
2 must be resolved once, completely, before any batch of step 3 begins;
**membership changes occurring between batches of step 3 can never alter
which recipients are frozen**, because the set to freeze was already
fully decided, as data, before the first batch's own `INSERT` ever runs.

**Replay, restated precisely**: reads the event projection's own `state`
and its frozen receipt rows; **never** resolves a new historical
recipient set; **cannot** recreate an expired notification (the receipt
correctly reports `notification_created`/`suppressed` regardless of
whether the notification row itself still exists); **cannot** email
newly-added administrators for an old event (they simply have no receipt
row under that event's own projection, and replay never creates one for
an already-`completed` projection).

**Projection semantics, precisely:**

- **Original projection resolves recipients exactly once**, per the
  freeze sequence above — never re-run for an event whose projection has
  already reached `completed`.
- **Notification expiry leaves both tables untouched** — their own
  retention (below) is independent of, and outlives, the notification
  row's own 90/365-day window.
- **Both tables expire only with, or after, the source event's own
  400-day purge** — bound to the same window "Settled V1 retention
  values, corrected" above already establishes for the event itself,
  swept together at the same purge step (per "Purge sequencing" point 4
  above, which already requires no non-terminal dependency remains before
  an event purges — these two tables are exactly such a dependency, and
  purge accordingly together, receipts before their own projection row,
  per the same children-before-parent discipline "Composite-FK sweep and
  deletion rules" above already establishes).
- **Resetting `published_at` on the outbox event cannot recreate an
  expired notification or its email** — resetting only permits the
  outbox row to be re-claimed for reprocessing; the event projection it
  reprocesses against, if already `completed`, still correctly reports
  every original outcome, so no new notification or email is fabricated
  from a reset alone.
- **A manually authorised "re-notify current recipients" operation, if
  ever introduced, is a genuinely new occurrence** — a fresh
  `occurrence_key` under a distinct cause, never a replay of the original
  event, and never something this projection mechanism itself performs
  automatically.

**Neither table is content-bearing audit storage**: each carries only
scalar identities, closed outcome enums, digests, and timestamps — the
same discipline that already makes every other table in this ADR safe to
retain past its source's own lifecycle, applied identically here.

- **Content-free historical activity (ADR-0025)**: this ADR's own
  recent-activity feed is a live query, not a competing historical-
  activity mechanism — it does not duplicate, replace, or need to
  reconcile with ADR-0025's Phase 19 aggregation, which remains the sole
  authority for historical usage/interval reporting.
- **Never an alternative permanent content archive**: notifications
  carry safe scalar parameters and bounded labels only, by the same
  design that makes them safe to retain past their source's deletion —
  there is no path by which this table could accumulate document content
  it would then need special deletion handling for, because it never
  stores any.

### 12. Laravel/Python ownership boundary — restated in full

**Laravel owns**: event interpretation; recipient resolution; authority
checks; notification templates and wording; persistence; read/dismiss
state; actionable-work projections; reminder scheduling; email
preferences; mail queueing and delivery; retries, reconciliation, and
audit correlation.

**Python owns only** its existing technical processing responsibilities
and the typed outcomes it already reports through ADR-0007/0009/0015/
0016/0032/0034's own accepted contracts.

**Python must never, and does not, under this ADR**: send an email;
create a user notification; choose a recipient; decide whether an event
is important; render browser or email wording; inspect a notification
preference; own a reminder schedule. **No new Python endpoint or cross-
language message is introduced.** No missing technical-outcome contract
was discovered during this drafting that would require one — every event
in the V1 vocabulary above is derivable entirely from facts Laravel
already owns or already receives through an existing contract.

### Settled V1 product values

Replacing every open numeric/behavioural decision the original draft
deferred, with these explicit V1 choices — each cross-referenced to its
own full treatment above, stated together here so no open value remains
scattered and unresolved:

- **Notification retention**: informational, 90 days; `warning`/
  `action_required`, 365 days; `DocumentGovernanceEvent` and terminal
  email envelopes/attempts/membership, 400 days each, genuinely longer
  than the longest notification window by construction (all four,
  "Retention and deletion" above).
- **Unread badge**: visual ceiling `99+`; the accessible label always
  announces the exact underlying count, never rounded to the visual
  ceiling ("Browser UX and accessibility" above).
- **Review-due-soon lead time**: deployment-configured
  (`REVIEW_DUE_SOON_LEAD_DAYS`), default 14 days; no per-workspace
  override in V1 ("Reminder scheduling" above).
- **Push and SMS**: explicitly deferred for V1. **No speculative channel
  abstraction is built to accommodate them** — the envelope/membership
  model above is shaped by, and named for, email specifically
  (`DocumentGovernanceEmailEnvelope`); a future ADR introducing a second
  channel is free to introduce its own parallel envelope design, or to
  generalise this one, as a deliberate decision made with a real second
  channel's actual requirements in hand, never as unused scaffolding
  carried in V1 on the assumption it will eventually be needed.

## Email templates and tenant-branding seam

**A controlled, versioned email-template system, owned entirely by
Laravel.** Every V1 email is template-based; **no arbitrary
administrator-authored HTML or free-form email content exists anywhere
in this design.**

### Template selection and Laravel's ownership

Each email is selected through exactly:

- a closed `template_key`;
- an immutable, versioned `template_version`;
- a closed, schema-validated set of safe scalar `parameters` — the same
  discipline "In-product notification model" above already establishes,
  reused rather than reinvented for email specifically;
- the delivery-envelope identity it is being rendered for (per "Email-
  delivery envelope model, corrected" above);
- the notification/event identities the envelope represents.

**Laravel owns, exclusively**: template selection; subject and body
wording; HTML rendering; plain-text rendering; escaping; action-link
generation (per "Reconciling stored target routes with live
authorization" above — an email's own call-to-action is resolved exactly
the same way, at send time, never a pre-baked stored URL); branding
resolution; accessibility; provider dispatch. **Python has no involvement
of any kind.**

### Presentation structure

Every V1 email uses one consistent layout, containing, where applicable:
workspace/tenant display name; a tenant logo, when a valid configured
logo exists; a tenant accent colour, when a valid configured accent
exists; **Dolved fallback branding when tenant branding is absent,
incomplete, invalid, or unavailable**; a plain-language heading; a short,
safe summary; one clear call-to-action linking to an authorised Dolved
route; a preference-management link; workspace context; a non-sensitive
Dolved service footer; a complete plain-text alternative.

**Never included, under any configuration**: document bodies; extracted
text; evidence excerpts; prompts or model output; signed or unsigned
object-storage URLs; arbitrary administrator-authored HTML; arbitrary
CSS; tracking pixels; unsafe uploaded filenames; uncontrolled user prose;
attachments (V1 has none, for any email).

**The call-to-action URL is generated by Laravel from an allowlisted
route kind and a scalar public identity — never a storage URL, and never
constructed by, or handed pre-built to, the browser/email payload.**
Opening the link performs ordinary live authentication, tenancy, and
authorization checks, exactly as any other route in this product already
does; **possession of the email itself grants no access whatsoever** —
restated from, and fully consistent with, "Reconciling stored target
routes with live authorization" above.

### Branding boundary — a narrow, provider-neutral input contract, not a
### feature

**This ADR reserves a seam; it does not design tenant-branding
administration.** The resolved branding input consumed by the template
renderer may contain only:

- a safe workspace display name;
- a validated tenant logo **reference** (never raw image bytes stored
  redundantly here — a reference into whatever storage/validation the
  later branding phase owns);
- a validated accent colour;
- the Dolved fallback identity;
- a branding-configuration version/public identity.

**A future tenant-branding phase — not this ADR — owns**: upload and
replacement of tenant logos; validation and storage; accent-colour
selection; branding preview; administration permissions; branding
lifecycle and deletion. **This ADR creates no administration screens for
any of the above, and does not reopen ADR-0027's design system** to
accommodate them.

**Until tenant branding is implemented, every email renders with the
Dolved fallback — a complete, fully supported V1 state, never a
temporary or broken placeholder.** Nothing in this ADR's own scope is
blocked on the later branding phase existing.

**Tenant branding is presentation only.** It never affects: notification
eligibility; recipients; severity; delivery channel; event identity;
template selection; authorization; or audit semantics — restated
explicitly because a presentation-layer input is exactly the kind of
thing that could otherwise be mistaken for carrying decision-making
weight it must never have.

### Delivery-time branding identity and historical evidence

**The rendering-snapshot columns on `DocumentGovernanceEmailEnvelope`
itself — `template_key`/`template_version`/`branding_configuration_
identity`/`workspace_display_name_snapshot`/`resolved_accent_identity`/
`sealed_rendering_basis_digest` — are the complete, closed model for
template/branding selection; nothing about it lives anywhere else.** All
six are resolved and fixed **no later than sealing** (the `assembling →
ready` transition, per "Envelope assembly lifecycle, corrected" above) —
never before every member is known, and never after.
`dispatch_decision_digest` is a separate, later-fixed fact (the
generation-1 preflight, per "Two distinct immutable digests, corrected"
above) and is not part of this template/branding model.

**Two template shapes, exactly**:

- **Immediate envelope**: the one versioned `template_key` selected for
  the notification's own `event_key`/category — one notification, one
  template, unconditionally.
- **Digest envelope**: exactly **one** versioned digest `template_key`,
  shared by the whole envelope, which renders its ordered member
  summaries from each member notification's own already-closed, safe
  `parameters` (per "In-product notification model" above) — never a
  per-member template selection inside one envelope.

**Category/template/membership compatibility, enforced at sealing, not
merely assumed**: the sealing operation verifies **every** member
notification's own `event_key`/category is compatible with the envelope's
own `category_group` and the digest template it is about to select —
a notification belonging to an incompatible category can never be sealed
into this envelope; **membership assignment itself (per "Envelope
assembly lifecycle" above) already only ever adds a notification to an
envelope whose `category_group` matches that notification's own
category, so a mixed, incompatible membership set is structurally
unreachable by the time sealing runs**, and sealing's own verification is
the final, defensive confirmation of that same invariant.

**Retries reuse the sealed snapshot and the frozen dispatch decision,
exactly**: retries of the same envelope (successive dispatch-attempt
generations, per "Durable dispatch-attempt authority" above) reuse the
identical `template_version`, `branding_configuration_identity`, and
`sealed_rendering_basis_digest` already sealed onto the envelope, and the
identical `dispatch_decision_digest` already frozen at the first
preflight — a retry is a re-attempt of the *same* delivery, never an
opportunity to silently pick up whatever branding or template
configuration happens to be current at retry time, nor a chance to
re-decide who is included. Each attempt's own
`sealed_rendering_basis_digest_verified` and `dispatch_decision_digest_
verified` (per the attempt table above) confirm both matches
immediately before every provider call.

**A newly created envelope may use current, newer branding/template
versions** — branding and template selection evolve prospectively, at
each envelope's own sealing moment, never retroactively applied to an
already-sealed envelope.

**Template-version lifetime guarantee, stated explicitly to close the
brief's own named gap**: **a template version must remain renderable for
at least the full retry/reconciliation lifetime of any envelope still
referring to it** — concretely, for at least this ADR's own 400-day
envelope/attempt retention window (per "Retention and deletion" below).
**Removing or replacing a template definition in a later deployment must
never make an older, still-pending or still-retained envelope
unrenderable**: template definitions are themselves versioned, additive
artifacts (a new `template_version` is added; an old one already
referenced by a sealed envelope is never deleted or overwritten while any
envelope could still reference it) — the same append-only discipline this
ADR already applies to every other immutable-identity column.

**Unavailable tenant logo at render/dispatch time**: falls back safely to
the Dolved fallback accent/mark for that specific render, **without
changing the envelope's own recorded `branding_configuration_identity`,
without failing, and without delaying dispatch** — a decorative asset's
own transient unavailability is never a delivery-blocking or authority-
changing condition. **If this fallback actually changes what the
recipient sees**, that fact is recorded as delivery evidence (a bounded,
typed `rendering_fallback_applied` marker on the relevant attempt row) —
**never** by storing the rendered content itself, only the fact that a
fallback occurred.

**Unsafe tenant accent**: falls back to the approved Dolved accent at the
same sealing-time contrast check "Email-client safety and accessibility"
below already establishes — `resolved_accent_identity` records whichever
one the check actually resolved to, so this fallback, too, is durable
evidence, not a silent runtime substitution invisible to any later
inspection.

**No raw image bytes, CSS, or fully-rendered HTML are retained on any
notification, envelope, or attempt row** — every row retains only safe,
versioned template inputs and delivery lineage; the actual HTML/text body
is regenerated fresh from those safe inputs on every render, including
every retry.

**Branding consistency is explicitly best-effort** where an external
logo asset disappears between envelope sealing and dispatch — correctness
and delivery **must not**, and do not, depend on a decorative asset's own
continued availability.

### Email-client safety and accessibility

Required: allowlisted, server-owned email markup only; conservative
inline styling suitable for common email clients (no reliance on
unsupported CSS features); semantic heading and link structure; meaningful
alternative text for a tenant logo (and for the Dolved fallback mark
alike); decorative Dolved elements hidden from assistive technology where
appropriate (`aria-hidden`/empty `alt`); sufficient contrast for tenant
accents; **automatic fallback to an approved Dolved accent when a
configured tenant colour cannot meet the required email contrast ratio**
— checked programmatically at render time, never left to administrator
judgement; no information conveyed by colour alone; a readable layout
with images disabled (the plain-text alternative, and alt text on any
retained image, both carry the actual meaning); a genuinely usable
plain-text alternative, not a token stub; responsive rendering at narrow
mail-client widths; no dependence on JavaScript, external fonts, or
remote stylesheets of any kind.

**A tenant accent may decorate borders, icons, or buttons only when its
specific foreground/background pairing passes the required contrast
check — administrators cannot override this safety rule.** This is a
rendering-time, programmatic gate, not a configuration option a
workspace administrator can bypass by insisting on a specific colour;
failing the check is precisely what triggers the Dolved-accent fallback
above, silently and automatically, never as a validation error the
administrator has to resolve before their branding "works."

### Preview and verification seam

**Reserved, not implemented**: a future authorised email-template preview
facility, driven by the same renderer and safe sample parameters this
ADR already establishes. This ADR does not implement or route such a
facility unless the later tenant-branding phase already requires it as
part of its own scope — it is named here only so that phase does not need
to invent a second, incompatible rendering path.

### Required staged visual checkpoints — representative notification
### emails

In addition to, and following, the fourteen product-surface checkpoints
in "Browser UX and accessibility" above, each requiring a rendered
browser preview or screenshots shown directly to David, with feedback
incorporated before the surface is considered visually accepted:

1. Dolved fallback — HTML and plain text.
2. Tenant-branded — HTML and plain text.
3. Images-disabled state.
4. Narrow/mobile mail-client rendering.
5. Unsafe-tenant-accent-colour fallback, actually triggered.
6. Immediate email versus grouped digest email, side by side.

## Alternatives considered

### Email only, no in-product record

Rejected — the settled decision fixes in-product history as the
authoritative user-facing record; email-only would make a provider outage
or a spam-filtered message a silent information loss with no fallback.

### In-product only, no email in V1

Rejected — explicitly settled as included in V1; users who do not have
the product open constantly still need to learn about failures/approvals
in a reasonable time.

### One notification per document in every batch

Rejected outright, per the brief's own settled decision — this is
precisely the "turning every internal event into an email" failure mode
this ADR exists to prevent; batch/operation-level notifications with
full item-level drill-down are the selected design throughout.

### Mutable dashboard counters

Rejected — the exact "two fields that can disagree" hazard this
decomposition has rejected repeatedly (ADR-0007, ADR-0017, ADR-0034,
ADR-0035); every dashboard/work-queue projection in this ADR is a live
query instead.

### Direct email sending inside the domain transaction

Rejected — an external mail-provider call inside the same transaction as
`ApproveDocumentVersion`/an import batch's own commit would make document
governance's own correctness depend on a third-party service's
availability and latency; the transactional-outbox-then-separate-
projector design decouples them completely, exactly as ADR-0008 already
established for ingestion.

### Python-owned notifications

Rejected outright — orchestration, recipient resolution, wording, and
delivery are Laravel's exclusively; Python's role is unchanged from every
other ADR in this decomposition.

### Unrestricted free-text notification payloads

Rejected — a `parameters`/`payload` field that accepted arbitrary prose
would reopen exactly the "no document content in an email/log" hazard
this ADR's own security section closes; a closed, allowlisted, versioned
template-parameter shape is selected instead.

### Polling domain tables directly, without a durable event record

Considered, and rejected as the primary mechanism (though dashboard cards
themselves are exactly this, deliberately, per "Notifications versus
actionable work"): polling alone cannot durably record "this specific
recipient was already told about this specific event" without
reinventing the outbox's own idempotency identity by another name, and
cannot survive a crash between "observed the state" and "recorded having
notified" without the same durability guarantee a transactional outbox
already provides.

### Mandatory email, no preferences at all

Rejected — the settled decision requires email behaviour to be
configurable, not permanently hard-coded; a workspace/personal
preference model is required.

### User preferences overriding every critical event, with no floor

Rejected — the settled decision (and this ADR's own precedence rule)
keeps the authoritative in-product record un-gateable by any preference;
only the *email channel* is preference-controlled, never the
notification's own durable existence.

### Permanent live foreign keys from notifications to their source domain
### rows, blocking deletion

Rejected — this would make ADR-0025/0031's own already-accepted deletion
paths responsible for cascading into, or being blocked by, a table those
ADRs have no reason to know exists; the content-free, scalar-correlation
design (reused directly from ADR-0025) avoids this entirely.

### Introducing push notifications or SMS in V1

Rejected for V1 — not named in the settled decisions, adds a second
external delivery provider and its own preference/retry/bounce surface
for no demonstrated V1 need; in-product plus email is the complete V1
channel set. A future ADR may add either if genuinely justified.

### A pseudo-event row (`import.approval.awaiting`) inside the closed
### event vocabulary

This was an earlier design in this ADR and is withdrawn as internally
contradictory — Codex correctly identified that a table describing
persistable events cannot coherently contain a row whose own description
reads "not an event at all." "Awaiting approval" is a derived,
live-queried fact, defined exclusively in "Notifications versus
actionable work" and "Dashboard and navigation projections," never as an
`event_key`.

### Target/operation correlation identity alone as event idempotency
### authority

This was an earlier design in this ADR and is withdrawn as insufficient
for recurring facts — Codex correctly identified that a document family
can legitimately receive several distinct, equally valid occurrences of
the same `event_key` over time (successive review reminders across
changed due dates and review cycles), which a `(event_key,
correlation_id)` uniqueness tuple would silently collapse into one. The
corrected design introduces `occurrence_key`, computed deterministically
before insertion from the target's own identity, the occurrence kind, and
the authoritative domain date/outcome-identity actually in question —
never the operation's bare target identity alone, and never the date the
scheduler happened to run.

### A nullable live recipient foreign key as the sole per-recipient
### uniqueness authority

This was an earlier design in this ADR and is withdrawn because
PostgreSQL's own "`NULL` never equals `NULL`" rule means a hard-deleted
user's nulled `recipient_user_id` could no longer meaningfully prevent a
duplicate notification for that same departed recipient. The corrected
design adds `recipient_user_public_id`, an immutable scalar captured once
at projection time, as the actual uniqueness and historical-intelligibility
authority; the live FK is retained only as a current-query convenience.

### A single `email_enabled_by_default` field expressing both a workspace
### hard gate and an inheritable personal default

This was an earlier design in this ADR and is withdrawn as ambiguous —
Codex correctly identified these as two different concepts a single
boolean cannot express without conflating them. The corrected design
introduces two explicit settings (`email_delivery_enabled`,
`default_email_enabled`) with an ordered, four-step precedence.

### Claiming exactly-once email delivery from a database unique
### constraint alone

This was an earlier design in this ADR and is withdrawn as overclaiming
— a database constraint governs only Laravel's own durable state, not an
external mail provider's own send, and cannot by itself close the crash
window between provider-acceptance and Laravel recording that success.
The corrected design states an honest at-least-once guarantee, uses a
provider-supplied idempotency identity where the configured transport
supports one, and documents a small residual duplicate-email risk where
it does not — never a false exactly-once claim.

### One delivery row per notification as the complete email model

This was an earlier design in this ADR and is withdrawn as unable to
represent a single digest email containing several notifications at
once. The corrected design introduces a durable envelope
(`DocumentGovernanceEmailEnvelope`) with append-only membership
(`DocumentGovernanceEmailEnvelopeMember`), where immediate and digest
delivery differ only in member count, never in a separately-coded path.

### A separate reminder-log table alongside the event table's own
### idempotency identity

This was an earlier design in this ADR and is withdrawn as introducing
exactly the "two fields that can disagree" hazard this decomposition
already rejects elsewhere — two independently-enforced uniqueness
boundaries for the same underlying fact could drift apart under a
partial failure. The corrected design uses `DocumentGovernanceEvent
.occurrence_key` as the sole idempotency authority for reminders and
authority transitions alike; no separate log table exists.

### A scheduler-run-relative ("since the previous scan") identity for
### authority-transition events

This was an earlier design in this ADR and is withdrawn because no
durable scan watermark was ever established to make "since the previous
scan" a meaningful, checkable comparison — a missed run or an overlapping
process could not be reasoned about safely against it. The corrected
design derives every reminder/transition `occurrence_key` from the
qualifying row's own current, authoritative condition, never from a
delta against remembered prior scanner state.

### Storing a resolved `target_route` string on the notification row

This was an earlier design in this ADR and is withdrawn as internally
inconsistent — the prior draft simultaneously claimed the stored route
"becomes null" on deletion, that no deletion-time update ever touches
historical notifications, and that availability is "checked lazily"
at render time, which cannot all be true of one stored column at once.
The corrected design stores only a safe target kind and immutable scalar
target identity/label, and resolves an effective route fresh, at render
time, on every request, re-running the destination's own live
authorization check.

### Treating an illustrative dashboard-query predicate as an authoritative
### restatement of a frozen ADR's own eligibility rule

This was an earlier design in this ADR and is withdrawn as a genuine risk
— a simplified predicate sketched inside this ADR could silently drift
from ADR-0031/0033's own actual accepted definition, omitting a
legitimately actionable item or including an ineligible one. The
corrected design treats every dashboard predicate shown in this ADR as
illustrative only, with the frozen ADR's own state machine as the sole
authority and dashboard queries implemented as named projections of it.

### Building a speculative multi-channel delivery abstraction ahead of a
### second real channel

Rejected — push/SMS are explicitly deferred for V1 (per "Settled V1
product values" above), and the envelope/membership model is deliberately
shaped and named for email specifically rather than generalised ahead of
a genuine second-channel requirement that does not yet exist.

### Allowing workspace administrators to override the email-contrast
### safety check for a tenant accent colour

Rejected — a decorative branding choice must never be permitted to
produce an inaccessible email; the contrast check and its automatic
Dolved-accent fallback are unconditional and cannot be configured away by
any administration permission.

### A retention arithmetic error — describing `DocumentGovernanceEvent`'s
### 180-day window as longer than the 365-day notification window

This was an earlier design in this ADR and is withdrawn as an
arithmetic contradiction — 180 is shorter than 365, so the outbox event
backing a `warning`/`action_required` notification could have expired
before the notification's own longer window even elapsed. The corrected
design sets `DocumentGovernanceEvent` and terminal email-envelope/
attempt/membership retention to 400 days each, genuinely exceeding the
longest notification window by construction.

### Unbounded digest-membership assembly with no cut-off or seal

This was an earlier design in this ADR and is withdrawn as leaving a
genuine race — nothing previously prevented a late-arriving notification
from being appended to a digest envelope that had already been
dispatched. The corrected design introduces an explicit assembly
lifecycle (`assembling → ready → dispatching → sent`/`failed_*`), an
atomic sealing operation that locks membership and computes a durable
rendering snapshot, and a deterministic late-arrival rule that routes a
post-cut-off notification to the next digest date instead.

### Mutable envelope `status`/`attempt_count` as the complete dispatch-
### execution authority

This was an earlier design in this ADR and is withdrawn as insufficient
to prevent two workers from concurrently dispatching the same envelope,
or a stale worker from reporting against a reclaimed delivery. The
corrected design introduces a durable, append-only
`DocumentGovernanceEmailEnvelopeAttempt` table with token/generation
fencing, directly mirroring the fencing discipline ADR-0035 already
establishes for bulk-item attempts.

### Describing template/branding identity as recorded on the envelope in
### prose, without closing the actual envelope schema

This was an earlier design in this ADR and is withdrawn as an
unresolved gap between narrative and schema — the prose claimed each
envelope records its rendering identity, but the displayed schema had no
columns for it. The corrected design adds the complete rendering-snapshot
column set directly to `DocumentGovernanceEmailEnvelope`, fixed no later
than sealing.

### Leaving four event families without a defined occurrence identity

This was an earlier design in this ADR and is withdrawn as incomplete —
`import.item.processing_failed`/`requires_user_action`/`match_ambiguous`
and `governance.ownership.reassignment_required` were left without any
stated occurrence-key construction, risking the same silent-collision
hazard already corrected for reminders. The corrected design binds each
to a real, immutable source-domain identity (ADR-0034's own
`ImportPreflightAttempt.event_id`/`ImportDecisionSnapshot.id`; the
specific `WorkspaceMembership` row plus its own eligibility-affecting
timestamp) rather than the item/family identity or event key alone.

### Assuming ownership-loss and deletion-stuck events have an obvious,
### unstated producer

This was an earlier design in this ADR and is withdrawn as unspecified —
neither event's actual Laravel-owned source was ever named. The corrected
design defines a bounded, transactional fan-out-then-reconciler producer
for ownership loss (never an unbounded fan-out inside one user-facing
disable/removal request) and a dedicated read-model-observing scheduler
for deletion stuckness, explicitly never mutating ADR-0025's own deletion
state machine.

### A workspace-scoped query alone as the only tenancy guard on a
### notification row

This was an earlier design in this ADR and is withdrawn as an
application-layer guard mistaken for a database-enforced invariant. The
corrected design adds `recipient_workspace_membership_id` and a
before-write trigger structurally binding it to the notification's own
`workspace_id`/`recipient_user_id`, following this decomposition's own
established tenancy-trigger pattern.

### A trigger observing `assembly_status`, with no shared row lock, as
### the append/seal race guard

This was an earlier design in this ADR and is withdrawn as insufficient
under `READ COMMITTED` — a trigger's own read and a concurrent sealing
transaction's own write are not serialised against each other without
both acquiring the same row lock first. The corrected design requires
both the append and seal paths to lock the envelope row before checking
or changing anything, with a deterministic retry-to-next-envelope path
for a late arrival that loses the race.

### A reclaimer that abandons the stale attempt without converging the
### envelope

This was an earlier design in this ADR and is withdrawn as capable of
leaving an envelope permanently `dispatching` with no worker ever able to
claim it again. The corrected design makes reclamation and envelope
convergence one atomic sequence, deriving the durable ceiling and
transitioning to `ready` or `failed_permanent` in the same transaction as
the abandonment itself.

### Leaving the provider-call timeout unrelated to the attempt's own
### lease duration

This was an earlier design in this ADR and is withdrawn as leaving an
avoidable ambiguous window in which both a reclaimer and a genuinely
still-working worker could reasonably believe they own an attempt. The
corrected design states a normative `L >= T + M` invariant, binding lease
duration to the provider's own hard call timeout plus a reserved
result-recording margin.

### Trusting eligibility resolved at notification-creation time all the
### way through to actual dispatch

This was an earlier design in this ADR and is withdrawn as stale by
construction for anything that sits `assembling` for a meaningful period
— a digest envelope in particular. The corrected design re-evaluates
eligibility immediately before every dispatch attempt, introduces a
terminal, non-failure `suppressed` outcome, and resolves partial digest
eligibility as immutable, typed, per-member decisions rather than a
silent removal.

### The notification row as the only uniqueness evidence for replay

This was an earlier design in this ADR and is withdrawn because a
notification's own 90/365-day expiry is shorter than its source event's
400-day replayability, risking a replay re-resolving an entirely new
historical recipient set against current membership. The corrected
design adds a compact, content-free projection receipt, retained through
the event's own 400-day window, recording exactly who was resolved and
notified the first time.

### An undeclared composite-FK target

This was an earlier design in this ADR and is withdrawn as an
unimplementable reference — the attempt table's own composite FK assumed
a unique `(id, workspace_id)` target on the envelope table that was never
declared. The corrected design declares it explicitly and sweeps every
other claimed reference in this ADR to confirm none of them silently
assumes one.

### An `eligibility_loss_cause_identity` built from membership columns
### that do not exist

This was an earlier design in this ADR and is withdrawn as unverified —
direct inspection of the actual `workspace_memberships` migrations shows
no dedicated removal or role-change timestamp column exists; removal
deletes the row, and role changes are ordinary updates with no
change-specific timestamp of their own. The corrected design binds the
identity to the real, verified `workspace_administration_audit_events.
event_id` the existing `RemoveWorkspaceMember`/`ChangeWorkspaceMemberRole`
Actions already produce, and to a new, dedicated fan-out source identity
for the (currently unimplemented) user-disablement path — never to a
column this codebase does not have.

### A nullable-recipient completion sentinel row for event-level
### projection completion

This was an earlier design in this ADR and is withdrawn as
non-functional — PostgreSQL does not treat two `NULL` values as equal for
uniqueness, so a `UNIQUE` constraint over an always-null completion
column enforced nothing. The corrected design separates event-level
completion (`DocumentGovernanceEventProjection`, one row per event,
every column always non-null) from per-recipient outcomes
(`DocumentGovernanceNotificationProjectionReceipt`, one row per resolved
recipient, never a placeholder row for "no recipients").

### Re-evaluating full recipient eligibility on every retry, while also
### claiming retries reuse the original decision unchanged

This was an earlier design in this ADR and is withdrawn as directly
self-contradictory. The corrected design performs full per-member
preflight exactly once, before the first provider attempt, freezing the
included/suppressed decision atomically with the first attempt's own
creation; every subsequent retry rechecks only a narrow, bounded set of
envelope-wide stop conditions and otherwise reuses the frozen decision,
template, branding, and rendering digest verbatim.

### Opening a provider-facing dispatch attempt for an envelope already
### known to have zero deliverable members

This was an earlier design in this ADR and is withdrawn as wasteful and
ambiguous — it would have consumed retry ceiling and lease/open-attempt
bookkeeping for a delivery that could never succeed. The corrected design
converges such an envelope directly to terminal `suppressed` in the same
transaction as the first preflight, opening no attempt row at all.

### A legacy ownership-eligibility digest keyed on `(document_family_id,
### owner_user_id)` alone

This was an earlier design in this ADR and is withdrawn as collision-
prone — the same person can be legitimately reassigned as owner twice,
with a different owner in between, and the resulting value-based digest
is identical both times even though the two assignment *acts* are
genuinely different occurrences, silently suppressing the second,
legitimate reconciliation. The corrected design adds
`DocumentFamily.owner_assignment_generation`, an immutable,
monotonically-incrementing integer extending ADR-0030 without changing
its settled owner-authority semantics, and keys the legacy identity on
`(document_family_public_id, owner_assignment_generation,
affected_owner_user_public_id)` instead — disambiguating the assignment
act itself, never merely its resulting value.

### A deletion occurrence identity of `(operation, generation)` alone

This was an earlier design in this ADR and is withdrawn as collision-
prone — a `stuck` occurrence and a later `failed_permanent` occurrence
recorded under the same still-current reclaim generation (generation
only advances on an actual reclaim, not on a mere reclassification) would
collide under this tuple, letting the permanent-failure occurrence be
silently suppressed as a duplicate of the earlier stuck one. The
corrected design adds `condition_kind` (`stuck`/`failed_permanent`) as a
third, closed-enum component of the tuple, so the two conditions can
coexist as genuinely distinct occurrences within the same generation.

### Locking the dispatch-attempt row before the envelope row at some
### points in the dispatch lifecycle

This was an earlier design in this ADR and is withdrawn as a genuine
deadlock hazard — the claim step and the reclaimer already locked the
envelope first, while pre-provider verification, provider-acceptance
recording, and failure recording each locked the attempt first; two
contradictory lock orders active over the same table pair can deadlock
the moment two such transactions run concurrently against the same
envelope. The corrected design adopts exactly one global lock order,
`envelope → attempt`, without exception, across every transaction in the
dispatch lifecycle, with a stale worker deriving the envelope's identity
from its own held attempt row's immutable `envelope_id` rather than
locking the attempt first.

### Creating dispatch-attempt generation 1 unconditionally, before
### running the generation-1 member-eligibility preflight

This was an earlier design in this ADR and is withdrawn as inconsistent
with its own stated zero-deliverable rule — the claim step inserted an
open attempt row and transitioned the envelope to `dispatching`
*before* the preflight (deferred to a later "immediately before calling
the provider" step) had ever run, so a zero-deliverable outcome would
still have consumed a real attempt generation and a retry-ceiling
position, contradicting the rule that no attempt row is ever created for
that outcome. The corrected design runs the full preflight (or, on
retry, the narrow stop-check) *inside* the same envelope-locked claim
transaction, before any attempt row is written, and branches only
afterward: zero deliverable members converge `ready → suppressed` with
no attempt row ever created; at least one deliverable member creates
attempt generation 1 (or the next retry generation) and transitions
`ready → dispatching` in that same transaction.

### Anchoring the 400-day terminal-envelope retention window on
### `dispatched_at`

This was an earlier design in this ADR and is withdrawn as
unenforceable for two of the three terminal outcomes — `dispatched_at`
is set only when the provider actually accepts a send, so a
`failed_permanent` or `suppressed` envelope, having no successful
provider acceptance, would never set it and could therefore never be
purged, retained forever by omission. The corrected design adds
`DocumentGovernanceEmailEnvelope.terminal_at`, a common immutable
timestamp set exactly once by whichever of the three terminal
transitions actually occurs, as the sole retention anchor for all three
terminal states; `dispatched_at` remains `sent`-specific
provider-acceptance evidence and is never repurposed as a retention
anchor.

### Claiming ADR-0030 already supplies an owner-reassignment Action and
### idempotency contract

This was an earlier design in this ADR and is withdrawn as unverified —
direct inspection of ADR-0030's own text finds no owner-reassignment
Action named anywhere, and a repository search finds no such Action,
migration, or idempotency table anywhere in `apps/api` today. The
corrected design defines a new, this-ADR-owned `ChangeDocumentFamilyOwner`
Action and `DocumentGovernanceCommand` idempotency table, reusing
ADR-0031's own governance-idempotency *pattern* (`UNIQUE (workspace_id,
purpose, idempotency_key)` and its matching/conflict/independence rules)
rather than inventing a second, competing idempotency shape or claiming
a table that does not exist.

### One field, `rendering_input_digest`, immutable at sealing and
### recomputed at the generation-1 preflight

This was an earlier design in this ADR and is withdrawn as internally
contradictory — the same column cannot honestly be both "fixed forever
at sealing" and "recomputed once eligibility is decided." The corrected
design splits it into two distinct, separately immutable fields:
`sealed_rendering_basis_digest` (fixed at sealing) and
`dispatch_decision_digest` (fixed once, at the generation-1 preflight,
chaining onto the sealed basis's own identity).

### Recording a delivery result against "the attempt is still `open`"
### as the only fencing check

This was an earlier design in this ADR and is withdrawn as a weaker
shortcut than the envelope→attempt lock order it sits inside actually
supports — `status = 'open'` alone cannot detect a wrong envelope, wrong
workspace, wrong attempt identity, wrong generation, wrong token, or a
result whose provider-side identity does not match the attempt it is
being applied to, each independently spoofable or misroutable. The
corrected design applies one normative, fully-specified result-fencing
predicate, verbatim, to every one of the six ways a result can arrive
(provider acceptance, retryable failure, permanent failure, local hard
timeout, duplicate callback, and a suspended worker reporting after
reclaim), writing nothing and converging nothing whenever any single
check fails.

### Using `rendering_input_digest`/`suppression_reason` in this ADR's own
### text without ever declaring them as envelope columns

This was an earlier design in this ADR and is withdrawn as an
undeclared-schema gap — `suppression_reason` was referenced throughout
the envelope's own terminal-suppression narrative and `CHECK` without
ever appearing in the envelope's own column table, and no
`terminal_failure_category` existed at all despite `failed_permanent`
needing one. The corrected design declares both columns explicitly, with
closed V1 vocabularies narrowed to only the values this ADR's own
preflight/retry/failure model actually reaches (`authority_lost` and
`configuration_failure` are each excluded, with the reachability
argument stated directly), and closes the envelope's own same-row
`CHECK` to enumerate all seven reachable `assembly_status` shapes
exhaustively.

### A consistency trigger described as the authority boundary for
### `owner_user_id`/`owner_assignment_generation`

This was an earlier design in this ADR and is withdrawn as
insufficient — a trigger that only checks "did the generation advance by
exactly one alongside an owner change" proves the pair is internally
consistent, never that the write came from `ChangeDocumentFamilyOwner`
at all; the restricted runtime role could issue the identical paired
`UPDATE` directly and satisfy the trigger while bypassing the command,
its idempotency record, and its audit write entirely. The corrected
design reuses ADR-0035's own three-role PostgreSQL foundation: table-level
`UPDATE` (and, for `owner_assignment_generation`, `INSERT`) is revoked
from `rag_platform_app` on both protected columns, and a new, directly-
invoked, purpose-controlled `SECURITY DEFINER` function —
`apply_document_family_owner_change`, owned by `rag_platform_owner`,
`EXECUTE`-granted only to `rag_platform_app` — is the only path capable
of writing either column, binding every mutation to a real, already-
committed `DocumentGovernanceCommand` row. The consistency trigger is
retained only as defence in depth, no longer described as the authority.

### A "look up, then insert if absent" idempotency-acquisition algorithm

This was an earlier design in this ADR and is withdrawn as non-atomic —
a plain `SELECT` followed by a conditional `INSERT` leaves a genuine race
between two concurrent requests for the same, brand-new idempotency key,
both of which can find nothing and both attempt to insert, with no
stated resolution. The corrected design uses one atomic
`INSERT ... ON CONFLICT (workspace_id, purpose, client_idempotency_key)
DO NOTHING RETURNING id`, followed unconditionally by a `SELECT ... FOR
UPDATE` on the exact unique identity, routing the row through four
closed cases (newly inserted; existing completed with a matching digest;
existing with a differing digest; existing, matching, but durably
incomplete — treated as structurally exceptional and failed closed,
never silently re-executed).

### Describing a table-level `GRANT UPDATE` as harmless because two
### columns were "excluded from the column-level grant list"

This was an earlier design in this ADR and is withdrawn as factually
false about PostgreSQL's own privilege model — privileges are additive,
never subtractive by omission; a table-level `UPDATE` grant conveys
`UPDATE` on every column regardless of what any earlier, narrower grant
did or did not name. The corrected design makes the standing state a
table-level `REVOKE UPDATE ON document_families FROM rag_platform_app`,
never merely a narrower `GRANT`, and explicitly reconciles this against
ADR-0035's own general runtime-grant baseline (which applies broad DML
to every existing application table and re-applies idempotently),
requiring `document_families`' own table-specific revoke to run last,
idempotently, in every reconciliation pass — plus a dedicated,
catalogue-level verification step that fails deployment closed if the
broad grant is ever rediscovered.

### A bare, single-column `target_document_family_id` FK, with no
### workspace binding

This was an earlier design in this ADR and is withdrawn as tenancy-
incomplete — nothing in a single-column FK to `document_families (id)`
prevents a command row from naming a family that belongs to a different
workspace than the command's own `workspace_id`. The corrected design
adds `UNIQUE (id, workspace_id)` to `document_families` and replaces the
single-column FK with a composite `FOREIGN KEY (target_document_family_id,
workspace_id) REFERENCES document_families (id, workspace_id)`, the same
pattern this ADR already uses for its email-envelope tables, plus an
independent runtime re-check inside `apply_document_family_owner_change`
itself (`commands.workspace_id = family.workspace_id`, checked before
both the no-op and genuine-mutation branches) as defence in depth against
a command row whose invariants were somehow no longer intact by the time
the function actually ran.

### An ordinary composite `ON DELETE SET NULL` for the family FK

This was an earlier design in this ADR and is withdrawn as destructive —
PostgreSQL's composite `ON DELETE SET NULL` (the same shape Laravel's
`nullOnDelete()` schema-builder shorthand generates for a multi-column
FK) nulls every referencing column together, which would null
`workspace_id` alongside `target_document_family_id` on an ordinary
family deletion, destroying the command row's own required non-null
tenancy identity. The corrected design uses PostgreSQL's column-targeted
referential action (`ON DELETE SET NULL (target_document_family_id)`,
available from PostgreSQL 15 onward; this repository runs
`postgres:18.4-alpine`), applied via raw DDL rather than the
schema-builder shorthand, so only the one column that may legitimately
go stale is ever nulled; a new `target_document_family_public_id` scalar
column preserves historical intelligibility independently of the live
FK.

### An `information_schema.role_table_grants` check keyed on a
### nonexistent `column_name`

This was an earlier design in this ADR and is withdrawn as invalid SQL
against a real catalogue view — `role_table_grants` has no `column_name`
column; it reports only genuinely table-level grants by its own
definition, making any such disambiguation both impossible to write and
unnecessary. The corrected design uses `role_table_grants` (or an
equivalent `pg_catalog` ACL inspection) purely to detect the *presence*
of a table-level grant, `has_column_privilege()` to verify each specific
column's own effective privilege (correctly folding in a table-level
grant's own effect on every column), `pg_auth_members`/`pg_roles` for
role-membership/attribute verification, and `pg_proc`/`pg_namespace`/
`aclexplode()` — keyed on exact schema-qualified name and argument
signature, never name alone — for complete `SECURITY DEFINER` function
verification.

### A same-row `CHECK` required to enforce `target_document_family_id`
### non-null only for the owner-change purpose

This was an earlier design in this ADR (implicitly, by omission) and is
withdrawn as unable to express the actual requirement — the column must
be non-null at creation yet must legitimately become null later, when
the column-targeted `ON DELETE SET NULL` referential action retires it
on family deletion; a same-row `CHECK` evaluates identically at both
moments and cannot distinguish "required now" from "legitimately absent
later." The corrected design uses a `BEFORE INSERT`-only trigger for the
creation-time requirement (structurally incapable of firing on the later
retirement `UPDATE`), combined with revoking `rag_platform_app`'s
`UPDATE` privilege on `document_governance_commands` entirely — never a
forgeable session-context flag — so no runtime path can fabricate a
retirement, and no fresh row can ever satisfy the owner-change purpose
with a null target.

### An `aclexplode(proacl)` check asserting a fixed one-row result

This was an earlier design in this ADR and is withdrawn as inconsistent
with genuine PostgreSQL ACL behaviour — an object's ACL ordinarily
contains an explicit owner entry alongside any runtime grant, `PUBLIC`
is represented by grantee OID `0` rather than by absence, and a `NULL`
`proacl` means the implicit default applies, never "no privileges." The
corrected design resolves the function by exact schema-qualified
signature via `to_regprocedure(...)` (failing closed if it resolves to
`NULL`), normalises the ACL via `COALESCE(proacl, acldefault('f',
proowner))`, classifies every exploded row as `PUBLIC`/`OWNER`/
`RUNTIME`/`OTHER`, and asserts against the classified set — never a
fixed row count — while a separate, name-only query independently
proves no broader overload exists under the same name in the intended
schema.

### An Action-level `SELECT ... FOR UPDATE` on the idempotency command
### table, issued by the restricted runtime role

This was an earlier design in this ADR and is withdrawn as
unexecutable — it directly contradicts the privilege model this same
ADR establishes: PostgreSQL requires `UPDATE` privilege on at least one
column of a table to acquire a row lock against it, and `rag_platform_app`
was, by this ADR's own correct design, already granted none. The
corrected design never has the runtime lock a command row at all: it
relies on `INSERT ... ON CONFLICT ... RETURNING id` and PostgreSQL's own
conflict-wait semantics (a competing uncommitted insert is waited on,
resolved to "no row" on the competitor's commit or "proceed and insert"
on its rollback) to determine ownership, falls through to an ordinary,
non-locking `SELECT` only when it did not create the row, and delegates
the sole row lock in the entire algorithm to
`apply_document_family_owner_change`, which genuinely holds the
necessary privilege as `rag_platform_owner`. No dummy or narrowly-scoped
`UPDATE` grant was introduced to paper over the contradiction.

### A role-membership check scoped to `rag_platform_app`'s own direct
### membership row alone

This was an earlier design in this ADR and is withdrawn as an
incomplete audit — a single one-level `pg_auth_members` join proves only
that `rag_platform_app` itself holds no direct membership row in either
privileged role; it cannot detect another login role directly or
transitively reaching `rag_platform_app`, `rag_platform_migrator`, or
`rag_platform_owner`; inherited function execution flowing through a
membership chain; `SET ROLE` authority reachable transitively; or an
unrelated login role holding superuser/role-creation authority that
would make the boundary moot regardless of any grant this ADR controls.
The corrected design enumerates every login role in the instance and
evaluates each one's `MEMBER`/`USAGE`/`SET` relationship to all three
protected roles via `pg_has_role()` (PostgreSQL's own authoritative
privilege function, supported on this repository's pinned PostgreSQL 18
version), supplemented by a cycle-safe recursive `pg_auth_members`
traversal used only for diagnostic path output, classified against a
closed, named, environment-versioned allowlist matrix rather than a
single hard-coded assertion.

### One combined "deployment/bootstrap" matrix row permitting
### `rolsuper` to vary while expecting no reachability

This was an earlier design in this ADR and is withdrawn as internally
impossible — a genuine PostgreSQL superuser bypasses every catalog ACL
and role-membership boundary this ADR establishes, so a row simultaneously
allowing `rolsuper = true` while requiring every membership/`USAGE`/
`SET`/`effective_execute` cell `false` describes a state that cannot
exist. The corrected design splits this into two named categories — a
non-superuser bootstrap/deployment identity (`rolsuper = false`,
genuinely no reach) and a named cluster superuser (`rolsuper = true`,
every reachability cell honestly `true`, isolated by credential
configuration rather than by catalog privilege) — plus an explicit
managed-service variant for environments with no customer-visible
superuser at all, and restates the isolation invariant accurately: no
*ordinary* login has privileged reachability; a named, accepted
superuser is inherently privileged but operationally isolated, never
"no login of any kind can reach protected authority."

### Claiming PostgreSQL permits constructing a circular role-membership
### grant, as the justification for a cycle guard

This was an earlier design in this ADR and is withdrawn as factually
false — PostgreSQL's own `GRANT role TO role` rejects a circular
membership grant at grant time; a genuine cyclic `pg_auth_members` graph
is not a reachable state on a normally operating cluster. The corrected
design retains the recursive diagnostic query's cycle guard as defensive
programming (against malformed catalogue data, future reuse against a
less-constrained edge source, or implementation error), states this
honestly rather than as a response to a real reachable state, and
requires two separate tests: a genuine PostgreSQL integration test
proving the database itself rejects a circular `GRANT`, and a synthetic
traversal test exercising the guard's own termination against a
fixture edge relation, never presented as evidence about real
`pg_auth_members` state.

### Omitting `rolcreatedb` from the executable login-role enumeration

This was an earlier design in this ADR and is withdrawn as
unenforceable — the allowlist matrix stated an exact expected
`rolcreatedb` value for more than one row, while neither of the two
normative enumeration queries actually selected that column, leaving the
comparison with no data behind it. The corrected design adds
`rolcreatedb` to both Step 1's and Step 2's own projections, alongside
every other attribute the matrix compares, and requires it in fail-closed
classification, mismatch diagnostics, and the provider-free tests.

### Hard-coding `rolcreaterole = true`/`rolcreatedb = true` for the
### named cluster-superuser matrix row

This was an earlier design in this ADR and is withdrawn as factually
inaccurate — PostgreSQL represents `rolsuper`, `rolcreaterole`, and
`rolcreatedb` as independent role attributes; a genuine superuser may
legitimately hold either or both creation flags `false`. The corrected
design makes both an explicit, environment-manifest-declared exact value
for this row, compared against the database's own observed value
independently for each attribute, while keeping the row's own protected-
role reachability and `effective_execute` truthfully `true` regardless
of what `rolcreaterole`/`rolcreatedb` happen to be — never inferring
either creation flag from `rolsuper` itself.

## Consequences

### Positive

- ADR-0030–0035's event-producing lifecycles finally have a real,
  bounded, non-spammy way to reach the people who need to know, without
  any of those ADRs' own state machines being reopened.
- The batch/operation-level notification shape, combined with always-
  live dashboard projections, structurally prevents the two most common
  notification-system failure modes (per-item spam, and drifted mutable
  counters) rather than merely discouraging them by convention.
- Reusing ADR-0025's content-free-correlation pattern and ADR-0008's
  outbox discipline (as a new, analogous instance, not a shared table)
  means this ADR introduces no genuinely new architectural pattern this
  codebase hasn't already proven — only new tables and jobs following
  patterns already in production shape.

### Negative

- Two entirely new outbox-style tables, two new queued jobs, a new daily
  scheduled command, a purpose-built notification schema, and the durable
  envelope/attempt/fencing model for email delivery are real, non-trivial
  new application surface — not a thin wrapper over existing
  infrastructure, despite reusing established patterns (including the
  fencing discipline ADR-0035 already proves for a different table).
- The hybrid recipient-resolution model (snapshotted notification
  identity, dynamically-resolved actionable work) means the two
  projections can legitimately show different things for the same user
  at the same moment (a notification history entry for someone who has
  since lost authority, absent from their now-empty actionable queue) —
  an accepted, deliberate divergence, not a bug, but one that must be
  explained clearly in the product UI so it does not read as
  inconsistent.
- Digest grouping for review reminders means a recipient with many due
  documents on the same day receives one email, not several — a
  deliberate trade-off against per-document granularity that a future
  ADR could revisit if a genuine need for finer-grained digest control
  emerges.

## Scope boundaries

This ADR does not define: ADR-0030–0035's own governance, import,
promotion, or bulk-operation rules; ADR-0037's export-specific event
vocabulary (though this ADR's namespace is chosen to accommodate it);
push/SMS delivery (explicitly deferred, per "Settled V1 product values"
below, with no speculative channel-abstraction layer built for a channel
V1 does not implement); per-workspace timezone-aware reminder scheduling
(V1 is UTC-date-based); a per-workspace-configurable "due soon" lead time
(V1 is one deployment-wide value, default 14 days); a dedicated per-user
email rate limiter beyond the structural batch-level bound already in
place; whole-workspace deletion's interaction with this ADR's own tables
(deferred to whichever future ADR defines workspace deletion); a future
tenant-branding administration surface (upload, validation, preview,
lifecycle — reserved as a narrow input-contract seam only, per "Email
templates and tenant-branding seam" below, never designed here); an
authorised email-template preview facility (reserved as a seam, not
implemented). Every other previously-open V1 numeric value (retention
windows, unread-badge ceiling) is now settled — see "Settled V1 product
values" below — and is no longer a scope boundary.

## Testing

Mapped to ADR-0029's fifteen-category taxonomy. Provider-free coverage
required for: event-to-notification mapping for every vocabulary entry
(Laravel feature/API); the notification-versus-work-queue classification,
asserting dashboard cards never read from the notification table and
notifications never double as a mutable counter (Laravel feature/API);
recipient resolution for every event family, including multiple-role
deduplication, cross-workspace exclusion, and disabled/removed users
(Laravel feature/API, security regression); authority revoked between
event occurrence and projector processing, asserting fail-closed
exclusion (Laravel feature/API); tenant isolation and `404`-not-`403`
concealment for notification routes and targets (security regression);
atomic domain-event/outbox creation, asserting no notification exists
without its originating domain transition having actually committed, and
vice versa (Laravel feature/API); projector replay/duplicate suppression
against every one of the three idempotency identities (Laravel feature/
API); email retry without duplicate send, including address-changed-at-
send-time and bounce/provider-failure handling (Laravel feature/API);
poison-event terminal-failure visibility (Laravel feature/API,
infrastructure/configuration); preference precedence (workspace default,
personal override, workspace-off-overrides-all) (Laravel feature/API);
digest grouping for multiple same-day reminders (Laravel feature/API);
reminder-date-changed, cleared, and owner-changed-after-scheduling
scenarios (Laravel feature/API); a missed scheduler run correctly
catching up without duplication (Laravel feature/API); review-date
advisory semantics — reaching or passing it never alters authority
(Laravel feature/API, shared event-contract if a contract fixture is
warranted); every named scheduled-activation outcome (approaching,
attained, blocked), including ADR-0017's own "blocked" condition (Laravel
feature/API); deleted/expired targets rendering the inert
label-only state, never a dead link (Frontend component, Laravel
feature/API); safe template parameters, asserting the closed
allowlisted shape rejects an out-of-shape or oversized payload (Laravel
unit, shared event-contract); unsafe-content rejection — a deliberately
crafted attempt to place document content, a storage URL, or unescaped
HTML into a notification/email is rejected at write time (security
regression); pagination and unread-count correctness under concurrent
read/write (Laravel feature/API); dashboard-count derivation matching
authoritative live queries exactly, including the zero-eligible and
large-tenant-bounded-feed cases (Laravel feature/API); accessibility
(bell/unread `aria-label`, keyboard-operable inbox, bounded live-region
announcements, actionable-versus-informational non-colour distinction)
(Frontend component); and a confirmation sweep that no Python contract,
test, or fixture in this ADR's scope grants Python any notification/
email/recipient/preference authority (shared event-contract, Python
integration).

**Additional coverage required for this pass's corrections (event-
occurrence identity, recipient identity, email settings/envelope/
delivery honesty, reminder identities, target-route resolution, dashboard
authority, and the email template/branding system)**:

- **Occurrence identity**: two legitimate occurrences sharing the same
  `event_key` and target/correlation identity but different authoritative
  dates (e.g. two distinct `review_due_soon` reminders across a changed
  due date) both persist, never suppressing one another; replaying the
  same occurrence produces no duplicate (Laravel feature/API).
- **Recipient identity survives deletion**: a recipient's `recipient_
  user_id` is nulled on account deletion while `recipient_user_public_id`
  and the row's own uniqueness remain intact; a second notification
  attempted for the same already-hard-deleted recipient/event still
  correctly collides against the existing row rather than silently
  succeeding as a duplicate (Laravel feature/API).
- **Email settings precedence**: the workspace master switch, the
  workspace default, and a personal override are each tested
  independently and in combination, including the master-switch-
  overrides-all case (Laravel feature/API).
- **Delivery envelopes**: an immediate envelope containing exactly one
  notification; a digest envelope accumulating several notifications
  across a day and dispatching once; a simulated crash after the mail
  provider accepts a send but before Laravel records `sent`, asserting
  the envelope's own retry behaviour is honest about the residual risk
  window rather than silently masking it; a stable provider-supplied
  idempotency identity being passed through where the configured
  transport supports one; the documented residual-duplicate behaviour
  where it does not (Laravel feature/API).
- **Reminder/authority occurrence correctness**: a reminder-scan overlap
  (simulated concurrent invocation) producing no duplicate event via the
  `occurrence_key` unique constraint alone; a missed-run catch-up
  correctly emitting every date it should have covered in one pass; a
  changed-then-changed-back review date correctly producing no duplicate
  notification; every named authority occurrence identity
  (`approaching`/`attained`/`blocked`) computed from current condition,
  never from a remembered prior scan (Laravel feature/API).
- **Notification expiry without audit loss**: an expired, purged
  notification/envelope row leaves ADR-0030/0031/0034/0035's own audit
  tables completely unaffected (Laravel feature/API).
- **Target-route resolution**: a deleted or newly-unauthorised target
  returns no effective route to the requesting browser, for that
  requester specifically, while a still-authorised different user
  reading the same historical notification still receives a valid route
  (Laravel feature/API, security regression).
- **Vocabulary closure**: a schema/enum-level test asserting
  `DocumentGovernanceEvent.event_key` accepts only the twenty real
  entries and no pseudo-event (shared event-contract).
- **Email templates and branding**: Dolved fallback rendering with no
  tenant branding configured; a valid tenant logo/accent correctly
  applied; an invalid or inaccessible logo falling back to Dolved
  branding without failing or delaying dispatch; an accent colour failing
  the contrast check falling back to the approved Dolved accent
  automatically; `template_key`/`template_version` validation rejecting
  an unknown or mismatched pair; parameter-schema rejection of an
  out-of-shape or oversized payload; HTML escaping; plain-text rendering
  correctness; action-route allowlisting (a crafted non-allowlisted route
  kind is rejected); a positive assertion that no sensitive content or
  storage URL ever appears in rendered output; a retried envelope reusing
  its own already-recorded template/branding identity rather than
  picking up a since-changed one; a genuinely new envelope correctly
  using current branding; images-disabled readability; narrow/mobile
  rendering; representative light/dark mail-client rendering where
  practically testable (Laravel unit, Laravel feature/API, Frontend
  component where applicable).

**Additional coverage required for this pass's corrections (retention/
purge sequencing, digest sealing, dispatch-attempt fencing, and the
closed template/branding envelope schema)**:

- **Retention and purge sequencing**: a notification past its 90/365-day
  window purges independently of its envelope membership row's own
  survival; a purged notification leaves its envelope membership row
  fully intelligible via its own retained scalar `source_event_id`/
  `recipient_user_public_id`; a terminal envelope and its attempts/
  membership purge together, only after 400 days; a non-terminal
  envelope is never purged regardless of age; a `DocumentGovernanceEvent`
  purges only after terminal projection, its own 400-day window, and no
  remaining non-terminal dependency; audit tables owned by ADR-0030/
  0031/0034/0035 are unaffected by any of the above (Laravel feature/API).
- **Closed envelope-state `CHECK`, all seven branches**: each of
  `assembling`, freshly-sealed `ready`, retryable `ready`, `dispatching`,
  `sent`, `failed_permanent`, and `suppressed` is accepted at the
  database level in exactly its one permitted shape (per the `CHECK`
  above); and every disallowed shape is rejected directly against
  PostgreSQL, not merely against application code, including: a
  `suppression_reason` populated on any non-`suppressed` status; a
  `suppressed` row with a null `suppression_reason`; a
  `terminal_failure_category` populated on any non-`failed_permanent`
  status; a `failed_permanent` row with a null `terminal_failure_
  category`; a fabricated `dispatched_at` on `failed_permanent` or
  `suppressed`; a `sent` row missing `dispatched_at`; `terminal_at` (or
  either digest, or either reason column) populated on `assembling`;
  `dispatch_decision_digest` populated on freshly-sealed `ready` (before
  generation 1 ever claimed it) and, separately, missing on `dispatching`
  or later; `sealed_rendering_basis_digest` missing on anything other
  than `assembling`; and a terminal status (`sent`/`failed_permanent`/
  `suppressed`) with `terminal_at` null. A second write attempt against
  an already-terminal envelope, or a second write attempt to
  `sealed_rendering_basis_digest`/`dispatch_decision_digest` once each is
  already set, is separately asserted to be a structural no-op for each
  of the five once-only fields independently — every transition that
  sets any of them is already gated behind `UPDATE ... WHERE
  assembly_status IN (...)` matching only the specific prior status that
  write may originate from, so once that status has passed, no further
  write path can match and re-set it — and the 400-day purge scheduler
  is asserted to compute its own age strictly from `terminal_at`, never
  from `dispatched_at`, for a `failed_permanent` and a `suppressed`
  fixture alike (Laravel unit, Laravel feature/API).
- **Digest sealing and late arrival**: a notification arriving
  immediately before cut-off joins the current day's envelope; one
  arriving during the sealing transaction itself is deterministically
  resolved to either the sealed envelope (if appended just before the
  lock) or the next digest date (if the lock already closed membership),
  never lost or duplicated; one arriving immediately after cut-off, or
  after the current envelope is already `sent`, is assigned to the next
  digest date; concurrent append-and-seal produces exactly one outcome
  per notification, never a lost or double-counted member; a retried
  dispatch renders from the unchanged sealed membership; an envelope with
  zero members at cut-off is never created or dispatched (Laravel
  feature/API).
- **Dispatch-attempt fencing**: two workers claiming the same `ready`
  envelope — only one succeeds, per the partial unique open-attempt
  index; a lease expiry followed by reclaim, and a stale worker's own
  subsequent report against the reclaimed generation, fails closed; a
  crash before the provider call leaves no attempt-status write; a crash
  after provider acceptance but before Laravel's own recording is
  recovered correctly and idempotently on retry; a duplicate provider
  callback is a no-op; retry-ceiling exhaustion is derived correctly from
  durable attempt rows; a rendering-input-digest mismatch at claim-
  verification time fails the attempt closed; provider-idempotency-
  supported and -unsupported behaviour are each tested explicitly,
  including the documented residual-risk case (Laravel feature/API).
- **`envelope → attempt` lock-order enforcement**: a concurrent
  result-recording transaction and a reclaimer racing over the same
  envelope/attempt pair never deadlock, asserted directly against
  PostgreSQL (each is observed to block on the envelope lock rather than
  raise a deadlock error, regardless of which one starts first); a
  worker holding only a stale `attempt_token` correctly derives the
  parent envelope from its own held attempt row's `envelope_id` without
  taking any lock to do so; a new-attempt-claim transaction running
  concurrently with an in-flight result-recording or reclaim transaction
  on the same envelope serialises correctly rather than deadlocking or
  double-claiming; every lock-acquisition order actually issued by
  claim, pre-provider verification, provider-acceptance recording,
  failure recording, and reclaim is asserted, directly against captured
  SQL or `pg_locks`, to be envelope-then-attempt with no exception
  (Laravel feature/API).
- **Complete result-fencing predicate**: for each of provider acceptance,
  provider retryable failure, and provider permanent failure, a result
  reported against a mismatched value for exactly one identity component
  at a time is rejected and writes nothing — wrong `envelope_id`; wrong
  `workspace_id`; wrong attempt `id`; wrong `generation`; wrong
  `attempt_token`; a mismatched `sealed_rendering_basis_digest`; a
  mismatched `dispatch_decision_digest`; and a result carrying a
  provider-side identity that does not match the attempt's own
  `provider_idempotency_key_used`. A correct, well-formed duplicate
  report arriving *after* the attempt has already reached a terminal
  status is confirmed to be a safe no-op distinct from the above (it
  fails only the `status = 'open'` check, every identity check having
  genuinely matched). A suspended worker's report arriving after its own
  attempt has been reclaimed and `abandoned` is confirmed to fail the
  same predicate for the same reason. Every one of the above is exercised
  against all three concrete recording paths (acceptance, retryable
  failure, permanent failure) plus the duplicate-callback and
  reclaimed-stale-worker paths, asserting in each case that neither the
  attempt nor the envelope is mutated (Laravel feature/API).
- **Template/branding envelope schema**: an immediate envelope's template
  matches its event category; a digest envelope's single template is
  compatible with every sealed member's own category, with a mixed-
  incompatible-member scenario asserted unreachable at membership-
  assignment time and rejected defensively at sealing if ever
  encountered; a template version scheduled for removal remains
  renderable for the full retention lifetime of any envelope still
  referencing it; a branding change after sealing never affects an
  already-sealed envelope's own recorded identity; a logo unavailable at
  retry time falls back safely without changing recorded branding
  identity; tampering with a sealed membership, `sealed_rendering_basis_
  digest`, or `dispatch_decision_digest` is detected and rejected for
  each digest independently; rendering from the same sealed inputs is
  fully deterministic across repeated attempts (Laravel unit, Laravel
  feature/API).

**Additional coverage required for this pass's corrections (occurrence
identities for every event, ownership-loss and deletion-stuck producers,
recipient-membership tenancy, append/seal locking, reclaim convergence,
provider-timeout/lease margin, send-time suppression, replay-after-
expiry, and composite-FK targets)**:

- **Occurrence identities for all twenty events**: every row of the
  complete occurrence-key matrix exercised directly — a genuinely new
  `ImportPreflightAttempt` produces a distinct `processing_failed`/
  `requires_user_action` occurrence from a prior attempt on the same
  item; a replayed callback for the *same* attempt collides; a corrected
  decision cycle (a new `ImportDecisionSnapshot`) produces a distinct
  `match_ambiguous` occurrence from the prior one; re-observing the same
  still-current snapshot collides (Laravel feature/API, shared event-
  contract).
- **Membership removal using a real administration event/work-item
  identity**: `RemoveWorkspaceMember` writes its verified
  `workspace_administration_audit_events` row and the corresponding
  `OwnershipEligibilityReconciliation` work item in the same transaction;
  the resulting `governance.ownership.reassignment_required` occurrence's
  `eligibility_loss_cause_identity` equals that specific audit event's own
  `event_id`, not a digest over any nonexistent timestamp (Laravel
  feature/API).
- **Role demotion using a real immutable cause**: `ChangeWorkspaceMemberRole`
  produces its own distinct audit event and reconciliation work item,
  correctly independent from a removal's own identity for the same
  membership (Laravel feature/API).
- **Repeated owner-loss cycles**: the brief's own worked example (Alex
  loses eligibility via one `RemoveWorkspaceMember`/`ChangeWorkspaceMember
  Role` invocation, is reassigned, a later owner loses eligibility via a
  second, independent invocation — even reappointing Alex) produces two
  distinct, both-recordable occurrences (two distinct audit-event
  `event_id`s), never one suppressing the other (Laravel feature/API).
- **User disablement fan-out**: a simulated disablement source record
  (`UserDisablementReconciliationSource`) correctly fans out to every
  workspace the affected user holds membership in, each resulting family
  occurrence sharing the **one** disablement's own source identity across
  workspaces; the disabling operation itself completes in bounded time
  independent of fan-out size; the reconciler's own batched, cursor-
  paginated pass resumes correctly after a simulated crash mid-fan-out
  without duplication or loss (Laravel feature/API).
- **Repeated owner-assignment/loss cycles under the legacy sweep
  specifically**: the legacy digest (`document_family_id`, `owner_
  user_id`) correctly collides on repeated scans of the same unchanged
  assignment, and correctly differs once `owner_user_id` changes and a
  later loss is observed against the new value — verified as a distinct
  code path from the primary, real-audit-event-identity producer above,
  never interchangeable with it (Laravel feature/API).
- **Repeated deletion-stuck episodes**: a recovered deletion followed by a
  genuinely new stuck episode (a new reclaim generation) produces a
  distinct occurrence; repeated scans of the same still-stuck generation
  no-op; a deletion that resolves in the narrow window between the
  read-model query and event emission is never falsely reported (Laravel
  feature/API).
- **Recipient membership tenancy — database-level rejection**: a direct
  attempt to insert a notification pairing one workspace's `workspace_id`
  with another workspace's membership is rejected; pairing a membership
  with a different user's `recipient_user_id` is rejected; an `UPDATE`
  attempting to rewrite `recipient_user_public_id` after creation is
  rejected; deleting the referenced membership nulls only `recipient_
  workspace_membership_id`, never `workspace_id` or the immutable
  recipient identity (Laravel feature/API, security regression).
- **Append/seal interleavings under `READ COMMITTED`**: arrival
  immediately before cut-off; arrival during the sealing transaction
  itself, resolved deterministically to either inclusion or the next
  envelope; arrival immediately after cut-off; arrival after the current
  envelope is already `sent`; concurrent append-and-seal stress producing
  exactly one outcome per notification across many iterations, never a
  lost or double-counted member; a retry rendering from unchanged sealed
  membership; no empty-envelope dispatch (Laravel feature/API).
- **Reclaimer envelope convergence**: reclaim below ceiling converges to
  `ready`; reclaim at ceiling converges to `failed_permanent`; two
  reclaimers racing the same expired attempt produce exactly one
  abandonment and one convergence; a stale worker's result report after
  reclaim is fenced and rejected; no envelope remains indefinitely
  `dispatching` across repeated crash-and-reclaim cycles (Laravel
  feature/API).
- **Provider timeout/lease margin, using a controllable fake clock/
  provider**: completion within timeout; a hard timeout firing and being
  correctly treated as a failure; the reserved reporting margin being
  sufficient in ordinary operation; the reclaim boundary (`lease_expires_
  at` exactly equal to `now()` is not reclaimed); a stale, suspended
  worker resuming after reclaim and being fenced; a provider accepting a
  request after the local client's own timeout fired, exercised as the
  documented residual-risk scenario, not asserted impossible (Laravel
  feature/API).
- **First-attempt per-member suppression**: the full preflight, run
  exactly once before attempt generation 1, correctly classifies each
  sealed member `included`/`suppressed` with its own typed reason,
  persisted atomically with `dispatch_decision_digest` and the first
  attempt's own creation, without recomputing `sealed_rendering_basis_
  digest`; each named `suppression_reason` reachable and correctly
  classified (Laravel feature/API).
- **Zero-deliverable envelope without a provider attempt**: an envelope
  whose first preflight includes zero members converges directly `ready →
  suppressed` (`no_deliverable_members`), never through `dispatching`,
  with `terminal_at` set and **no** `DocumentGovernanceEmailEnvelopeAttempt`
  row ever created for it — asserted directly against the attempts table
  (a `COUNT(*)` of zero for that envelope, not merely against the
  envelope's own final state), and against the exact sequence of writes
  inside the single claim transaction (Laravel feature/API).
- **Generation-1 preflight runs before, not after, attempt creation**: a
  captured/mocked ordering assertion that the full per-member preflight
  and its branch decision execute before the `INSERT` into
  `DocumentGovernanceEmailEnvelopeAttempt` (or, on the zero-deliverable
  branch, before confirming no such `INSERT` ever runs) within the same
  claim transaction — directly exercising the bug this pass closed, not
  just its final-state symptom (Laravel feature/API).
- **Retry after workspace email disable**: a workspace disabling
  `email_delivery_enabled` between attempt generations causes the next
  retry's own claim to terminalise `ready → suppressed`
  (`workspace_email_disabled`) with `terminal_at` set, **without ever
  creating a new attempt generation row** (asserted directly against the
  attempts table — the durable generation count for this envelope is
  unchanged by the suppressed retry) and without calling the provider;
  never automatically reopens even if the workspace later re-enables it
  (Laravel feature/API).
- **Retry after personal opt-out**: an individual recipient opting out
  between attempt generations causes the same terminal, non-retried,
  no-new-attempt-row outcome for that specific envelope (Laravel
  feature/API).
- **Retry preserving identical member set/both digests/idempotency
  key**: a retry that passes every stop condition reuses the exact
  originally-frozen `included` member set, template, branding identity,
  `sealed_rendering_basis_digest`, `dispatch_decision_digest`, and
  provider idempotency key, byte-for-byte — never re-including a member
  suppressed at the first preflight, and never silently altering either
  digest's content across generations (Laravel
  feature/API).
- **Exactly one event projection per source event**: concurrent attempts
  to create/resolve the same event's projection converge to exactly one
  `DocumentGovernanceEventProjection` row, enforced by `UNIQUE
  (workspace_id, source_event_id)` (Laravel feature/API).
- **No nullable-recipient completion sentinel**: a schema-level assertion
  that `DocumentGovernanceNotificationProjectionReceipt.recipient_user_
  public_id` is `NOT NULL` in every row, and that no code path ever
  attempts to insert an event-level completion row into that table
  (Laravel unit, infrastructure/configuration).
- **Recipient-set freezing before batched projection**: a large resolved
  recipient set split across multiple bounded `INSERT` batches for step
  3's own receipt creation still reflects exactly the set resolved once,
  in step 2, before the first batch began (Laravel feature/API).
- **Crash midway through recipient notification creation**: a simulated
  crash after some, but not all, `pending` receipts have been processed
  leaves the event projection correctly `projecting` (not prematurely
  `completed`), and a resumed worker correctly finishes only the
  remaining `pending` receipts without reprocessing already-terminal ones
  (Laravel feature/API).
- **Membership changes after recipient-set freeze**: a membership change
  occurring after step 2's own snapshot, but before all batches of step 3
  complete, never alters which recipients are frozen — the originally
  resolved set is exactly what receives receipt rows (Laravel feature/
  API).
- **Zero-recipient projection**: an event with a genuinely empty resolved
  recipient set reaches `completed` with its own deterministic empty-set
  digest and zero receipt rows — never a fabricated placeholder row
  (Laravel feature/API).
- **Replay after notification expiry, and after recipient roles change**:
  an event's notification and email fully expire and purge, then the
  source event is replayed — the event projection and its frozen receipts
  (not fresh recipient resolution) determine the outcome, reproducing the
  original recipient set even though current workspace membership has
  since changed; a `completed` projection with zero receipt rows is
  itself replayed correctly as a no-op; resetting `published_at` alone
  never recreates an expired notification or email (Laravel feature/API).
- **Composite-FK creation and deletion behaviour**: creating an attempt
  row with a `(envelope_id, workspace_id)` pair that does not match any
  envelope's own `(id, workspace_id)` is rejected; deleting an active
  (`assembling`/`ready`/`dispatching`) envelope is prevented by
  application logic and asserted never attempted; a terminal, past-
  retention envelope purges completely (children then parent) without
  being blocked indefinitely by any remaining child row (Laravel feature/
  API, infrastructure/configuration).

**Additional coverage required for this (fifth) pass's corrections
(owner-assignment generation, deletion `condition_kind`, the global
`envelope → attempt` lock order, generation-1-preflight-before-attempt
ordering, and the common `terminal_at` retention anchor)** — cross-
referenced to where each is already detailed above rather than restated:
the `envelope → attempt` lock-order enforcement tests ("`envelope →
attempt` lock-order enforcement," above); the generation-1-ordering and
zero-new-attempt-on-suppressed-retry tests ("Generation-1 preflight runs
before, not after, attempt creation," "Zero-deliverable envelope without
a provider attempt," and the updated "Retry after workspace email
disable"/"Retry after personal opt-out," above); the `terminal_at`/
`dispatched_at` schema `CHECK` and purge-anchor tests ("`terminal_at`/
`dispatched_at` schema `CHECK`," above). New for this pass and not
already covered above: repeated owner-reassignment cycles (`A → B → A`)
correctly produce two distinct, non-colliding legacy-sweep occurrences,
keyed apart by `owner_assignment_generation` alone, even though the
resulting owner value is identical both times; a concurrent legacy-sweep
write racing an ordinary reassignment is serialised correctly by the
same atomic-increment discipline "Legacy ownership-eligibility sweep"
above establishes; a `stuck` and a subsequent `failed_permanent`
occurrence recorded under the same still-current reclaim generation
coexist as two distinct, non-colliding rows, keyed apart by
`condition_kind`; a reclassification from `stuck` to `failed_permanent`
within one generation is asserted to consume no new generation of its
own (Laravel unit, Laravel feature/API).

**Additional coverage required for this (sixth) pass's corrections
(the owner-change command, the sealed-basis/dispatch-decision digest
split, complete result fencing, and the closed terminal-evidence
`CHECK`)** — cross-referenced to where each is already detailed above
rather than restated: the digest-tampering and lifecycle tests for both
`sealed_rendering_basis_digest` and `dispatch_decision_digest`
("Template/branding envelope schema," above); the complete
result-fencing predicate tests ("Complete result-fencing predicate,"
above); the closed envelope-state `CHECK` tests across all seven
branches ("Closed envelope-state `CHECK`, all seven branches," above).
New for this pass: the full `ChangeDocumentFamilyOwner`/
`DocumentGovernanceCommand` test list — backfill determinism and
no-event-on-backfill; family creation with an owner starting at
generation `1`; the without-owner and clear-owner cases rejected at the
database/Action level; the full `A → B → A` cycle producing three
strictly increasing generations; same-command replay with zero mutation;
same-key/different-digest failing closed as `idempotency_key_conflict`;
a redundant new command against the current owner producing an honest
no-op result distinct from a replay; an unrelated metadata edit leaving
both owner columns untouched, with the generation-guard trigger
separately rejecting a direct attempt to change the generation without a
corresponding owner change; and full atomicity of the generation/owner/
audit/idempotency-result write across a simulated mid-transaction crash
(Laravel unit, Laravel feature/API). **Corrected by the seventh pass,
immediately below: "two genuinely concurrent distinct owner commands
against the same family each completing with a correctly increasing
generation" is no longer accurate** — concurrent, differently-preconditioned
commands are now precondition-based, not both-always-succeed; see below.

**Additional coverage required for this (seventh) pass's corrections
(privilege-controlled owner/generation mutation and atomic idempotency
acquisition)** — cross-referenced to where each is already detailed
above rather than restated: the full protected-column privilege test
list ("Protected-column privilege model and the purpose-controlled
mutation function," above) — direct owner-only, generation-only, and
paired owner/`+1` `UPDATE` attempts by `rag_platform_app` all rejected;
session-setting/GUC fabrication having no effect; the privileged
function rejecting a missing command, wrong purpose, and a stale
expected precondition; a legitimate command succeeding with atomic
owner/generation/audit/result commit; migrator-authority backfill; and
`rag_platform_app` unable to assume owner/migrator authority. **Corrected
by the eighth pass, below: this description's own "wrong workspace/
family" rejection was not, in fact, implemented by the function at this
point** — the seventh-pass function took no workspace parameter and
performed no workspace-versus-family comparison at all; the eighth pass
closes this exact gap. The full concurrency-specific test list ("Concurrency-
specific tests," above) — concurrent same-key/same-digest producing
exactly one mutation; concurrent same-key/different-digest producing one
winner and one typed conflict; a forced rollback leaving no row behind;
two distinct keys serialising on the family lock; the redundant-command
no-op exercised under concurrency; a manually constructed incomplete
command row failing closed; and no raw unique-constraint violation ever
reaching the caller. **Superseding the sixth pass's own test description
immediately above**: two genuinely concurrent, differently-preconditioned
owner commands against the same family now produce one success and one
`owner_change_precondition_stale` failure, asserted under real
concurrent execution (Laravel unit, Laravel feature/API, infrastructure/
configuration).

**Additional coverage required for this (eighth) pass's corrections
(prohibiting broad runtime `UPDATE` on `document_families`, and binding
owner-change commands to the family's own workspace)** — cross-referenced
to where each is already detailed above rather than restated: the full
"Required tests, privilege reconciliation" list, above — no table-level
`UPDATE` after the complete migration sequence including the general
baseline; every allowlisted metadata column remains updatable; the
regression test that deliberately issues a table-level `GRANT` in an
isolated transaction and proves the reconciliation query detects it,
followed by proof that re-running the revoke-then-grant sequence restores
the intended state; and a new column remaining non-writable until an
explicit, reviewed grant is added. The full "Required tests, workspace
binding" list, above — a cross-workspace `INSERT` rejected by the
composite FK; target/workspace immutability on an existing command row;
the function-level defence test against a deliberately fabricated
mismatched row (composite FK bypassed only inside the controlled test
fixture); the workspace check proven to run, and reject, before both the
no-op and the genuine-mutation branches; a same-workspace legitimate
command still succeeding; and the external API surfacing only generic,
tenant-safe concealment for a cross-workspace attempt, never a raw
constraint violation (Laravel unit, Laravel feature/API, security
regression, infrastructure/configuration).

**Additional coverage required for this (ninth) pass's corrections
(column-targeted family-deletion referential action, and corrected
PostgreSQL catalogue verification)** — cross-referenced to where each is
already detailed above rather than restated: the full "Required tests,
column-targeted family-deletion behaviour" list, above — only
`target_document_family_id` nulled on family deletion; `workspace_id`
and `target_document_family_public_id` both retained; family deletion
never blocked; a pending command against a deleted target failing
`owner_change_target_family_missing`; a completed command remaining
readable; the cross-workspace `INSERT` rejection unaffected; and the raw
DDL applying successfully against `postgres:18.4-alpine`. The full
"Required tests, `SECURITY DEFINER` function verification" list, above —
wrong owner, `prosecdef = false`, unsafe `search_path`, restored `PUBLIC
EXECUTE`, missing runtime `EXECUTE`, an unexpected grantee, and an
unexpected overload, each independently detected, plus the correct final
state passing cleanly. The corrected table-level/column-level/role-
membership catalogue queries (`role_table_grants` for presence-of-a-
table-level-grant only, `has_column_privilege()` for effective per-column
access, `pg_auth_members`/`pg_roles` for membership/attributes) are
exercised by the "Required tests, privilege reconciliation" list from
the eighth pass, above, now run against valid, executable SQL rather
than the withdrawn, invalid `column_name` predicate (Laravel unit,
Laravel feature/API, security regression, infrastructure/configuration,
database integration test using genuine PostgreSQL).

**Additional coverage required for this (tenth) pass's corrections
(the `BEFORE INSERT` target-shape guard for owner-change commands, and
executable ACL normalisation/overload verification)** — cross-referenced
to where each is already detailed above rather than restated: the full
"Required tests, insert-time guard and post-insert immutability" list,
above — a fresh null-target `INSERT` rejected before the row is ever
visible; a genuine, fully populated `INSERT` succeeding; a direct
post-insert `UPDATE` attempting to null or rewrite
`target_document_family_id`/`target_document_family_public_id`/
`workspace_id`/`purpose` rejected at the privilege level, never reaching
the (structurally incapable) trigger; a genuine family deletion still
nulling exactly `target_document_family_id`, confirming the trigger
neither blocks nor is invoked by the referential action; a retired
pending command failing to execute; and a retired completed command
remaining readable. The full "Required tests, ACL normalisation and
overload resolution" list, above — an ACL containing both an owner entry
and the runtime entry passing correctly; `PUBLIC` detected by grantee
OID `0` specifically; restored `PUBLIC EXECUTE` detected; an unexpected
third-party grantee detected while the owner's own entry is correctly
never flagged; a `GRANT OPTION` on the runtime grant detected; a `NULL`
`to_regprocedure` resolution detected as a distinct missing-signature
failure; an extra overload detected independently of exact-signature
resolution; a same-named function in an unrelated schema neither
counted nor reachable through an unqualified call; and the correct,
unmodified final state passing every check (Laravel unit, Laravel
feature/API, security regression, infrastructure/configuration, database
integration test using genuine PostgreSQL).

**Additional coverage required for this (eleventh) pass's correction
(removing the unexecutable Action-level `SELECT ... FOR UPDATE` on
`document_governance_commands`, and correcting the acquisition algorithm
to never require runtime `UPDATE` privilege)** — cross-referenced to
where each is already detailed above rather than restated: the full
"Required tests, corrected acquisition privilege and concurrency" list,
above — an ordinary `rag_platform_app` `SELECT` succeeding while a
`rag_platform_app` `SELECT ... FOR UPDATE` is rejected outright; the
privileged function itself successfully locking a command row;
concurrent same-key/same-digest and same-key/different-digest pairs each
resolved via the `INSERT`'s own conflict-wait, never a runtime lock; a
forced rollback letting a waiting `INSERT` become the genuine inserter;
the fabricated "no-return-then-missing-row" condition failing closed as
`owner_change_acquisition_inconsistency`, distinct from the existing
`owner_change_command_incomplete` case; no raw unique-constraint
violation ever escaping; and the `command → family` lock order confirmed
unchanged — every lock in the algorithm taken inside
`apply_document_family_owner_change` alone (Laravel feature/API,
database integration test using genuine PostgreSQL with real concurrent
connections).

**Additional coverage required for this (twelfth) pass's correction
(the complete effective login-role membership/access audit, replacing
the withdrawn single-role, one-level membership check)** —
cross-referenced to where each is already detailed above rather than
restated: the full "Required tests, effective login-role membership and
access audit" list, above — the intended `rag_platform_app`/
`rag_platform_migrator` rows verified correct (including the
`NOINHERIT`-with-`SET`-capability signature); direct and transitive
login→app and login→owner membership each detected independently;
unexpected `rag_platform_migrator` membership detected; a `NOINHERIT`
role that can still `SET ROLE` correctly reported as such, never
conflated with automatic inheritance; inherited function `EXECUTE`
detected both directly and through a multi-hop chain; unexpected
superuser and `CREATEROLE` logins each detected; the environment's own
declared bootstrap/break-glass role confirmed present and correctly
excluded from the "unexpected powerful login" failure. **Corrected by
the thirteenth pass, below: the recursive query's own cycle guard was
described as protecting against a constructible real
`pg_auth_members` cycle — PostgreSQL itself rejects such a grant, so
this pass's own cyclic-membership test description is superseded.** A
missing expected runtime permission detected as a
failure, never silently tolerated; and the correct, complete, unmodified
role graph passing the entire audit cleanly (infrastructure/
configuration, security regression, database integration test using
genuine PostgreSQL with real role/membership fixtures).

**Additional coverage required for this (thirteenth) pass's correction
(splitting the impossible combined bootstrap/superuser matrix row into
two factually accurate categories, and correcting the circular-role-
membership claim behind the diagnostic query's own cycle guard)** —
cross-referenced to where each is already detailed above rather than
restated: the named non-superuser bootstrap/deployment identity
confirmed `rolsuper = false` with genuinely no reach, failing the gate
if ever found `rolsuper = true` or holding authority beyond its own
bounded task; the named cluster superuser confirmed `rolsuper = true`
with every reachability cell honestly `true`, correctly excluded from
the "unexpected powerful login" failure as its own distinct, accepted
category; a fixture asserting the named superuser's own credentials
reach any long-running service failing a separate, deployment-
configuration check; an environment-manifest mismatch (expected named
superuser absent, or an undeclared one present) failing the gate; an
unnamed superuser or `CREATEROLE` login still failing exactly as before;
the managed-service variant correctly classifying whatever provider-
administered role is actually visible, with no invented superuser row;
the genuine PostgreSQL integration test proving a circular `GRANT` is
rejected by the database itself, with the role audit remaining
functional afterward; and the separate synthetic traversal test proving
the diagnostic query's own cycle guard terminates safely against a
fixture edge relation, never against genuine `pg_auth_members`
(infrastructure/configuration, security regression, database integration
test using genuine PostgreSQL).

**Additional coverage required for this (fourteenth) pass's correction
(adding `rolcreatedb` to the executable enumeration, and making the
named cluster superuser's own `rolcreaterole`/`rolcreatedb` values
environment-exact rather than hard-coded)** — cross-referenced to where
each is already detailed above rather than restated: `rolcreatedb` now
present in both Step 1's and Step 2's own query projections and exercised
by every combination test above; a named superuser with both creation
flags `true`, both `false`, and each mixed combination all passing when
matching their own manifest; a manifest mismatch on `rolcreaterole` alone
and on `rolcreatedb` alone each independently failing the gate; the
superuser's own effective protected-role reachability and
`effective_execute` confirmed truthfully `true` regardless of those two
independent attributes' own values; and an unexpected additional
superuser still failing closed (Laravel unit, security regression,
database integration test using genuine PostgreSQL). The circular-
membership wording and its two required tests are unchanged by this pass.

**Required Playwright journey**: a document import completes with one
exception; the uploader receives and opens the resulting notification;
the notification's link opens the correct, already-authorised detail
page; the same batch's exception also appears on the failed/warning-
imports dashboard card with correct drill-down; a review-due-soon
reminder is visible on both the notification inbox and the review-due
dashboard card; dismissing the notification leaves the dashboard card's
own count unaffected; light and dark themes; mobile layout; keyboard-only
navigation through the inbox.

No live-provider run is required to prove this ADR's own orchestration;
actual email deliverability against a real provider remains governed by
whatever existing provider-smoke-test boundary this codebase's mail
configuration already uses, unmodified by this ADR.

## Phase/session allocation

Consuming ADR-0030–0035's own event-producing lifecycles, this ADR's
implementation is sequenced **after** those six ADRs' own primitives
exist, within the same Import, Staging and Bulk Governance / Document
Governance phase this decomposition has used throughout. **This ADR does
not invent or renumber final session identifiers**, since the phase's own
session renumbering has not yet been reconciled in `tasks.json`; the
allocation below states ownership and dependency order only.

- **Session A — Schema, event vocabulary, occurrence identities,
  producers, outbox/projector foundation, provider-free API tests.**
  `DocumentGovernanceEvent` (including `occurrence_key`),
  `DocumentGovernanceNotification` (including `recipient_user_public_id`,
  `recipient_workspace_membership_id`, and its tenancy trigger),
  `DocumentGovernanceEventProjection` and
  `DocumentGovernanceNotificationProjectionReceipt` schema and
  constraints; the closed twenty-entry V1 event vocabulary, wired to each
  source ADR's own domain transitions and to the complete occurrence-key
  matrix, attributed to its actual owning ADR; extending the verified
  existing `RemoveWorkspaceMember`/`ChangeWorkspaceMemberRole` Actions to
  write their own `OwnershipEligibilityReconciliation` work item; the
  (not-yet-existing) user-disablement Action's own required
  `UserDisablementReconciliationSource` contract; the bounded ownership-
  loss fan-out reconciler and the separate legacy-condition sweep,
  including `DocumentFamily.owner_assignment_generation` (new column,
  extending ADR-0030, backfilled to 1 under migrator/owner authority,
  with its `BEFORE UPDATE` consistency trigger retained as defence in
  depth only) and its corrected legacy identity; the new, entirely
  ADR-0036-owned `ChangeDocumentFamilyOwner` Action, its atomic
  `INSERT ... ON CONFLICT ... RETURNING id`-then-plain-`SELECT`
  idempotency-acquisition sequence (the runtime never issues a locking
  `SELECT ... FOR UPDATE`, relying instead on PostgreSQL's own
  conflict-wait semantics and on the privileged function for the one and
  only row lock), and its `DocumentGovernanceCommand` table
  (reusing ADR-0031's own governance-idempotency pattern, never a
  competing shape) — this is new work, not an extension of any
  pre-existing owner-reassignment mechanism, none of which exists in the
  repository today; the standing table-level `REVOKE UPDATE ON
  document_families FROM rag_platform_app` (never a narrower `GRANT`
  alone) plus its explicit column-level allowlist, reconciled against
  ADR-0035's own general runtime-grant baseline with a table-specific
  revoke that must run last and idempotently in every pass; the
  catalogue-level privilege-reconciliation/verification step (CI/
  deployment-owned, fails closed on a rediscovered table-level grant, a
  reachable protected column, an unreviewed column grant, or a role-model
  deviation, using `role_table_grants`/`has_column_privilege()` correctly
  rather than a nonexistent catalogue column) and the protected-table
  allowlist supporting it; the complete effective login-role membership
  and access audit (every `rolcanlogin` role enumerated, each evaluated
  against all three protected roles via `pg_has_role('MEMBER'/'USAGE'/
  'SET')`, a cycle-safe recursive `pg_auth_members` traversal for
  diagnostic path only — its own cycle guard correctly stated as
  defensive programming, never as protection against a database state
  PostgreSQL's own `GRANT` already makes unreachable —
  `has_function_privilege()` for effective `EXECUTE`, classified against
  a closed, named, environment-versioned allowlist matrix with separate,
  factually accurate categories for a non-superuser bootstrap identity
  and a named cluster superuser (or its managed-service equivalent) —
  replacing the withdrawn single-role, one-level membership join); `UNIQUE (id,
  workspace_id)` on `document_families` and `DocumentGovernanceCommand`'s
  own composite `FOREIGN KEY (target_document_family_id, workspace_id)`
  replacing its withdrawn single-column FK, with a column-targeted
  `ON DELETE SET NULL (target_document_family_id)` raw-DDL referential
  action (never Laravel's composite `nullOnDelete()` shorthand) and a new
  `target_document_family_public_id` scalar-lineage column; the new,
  purpose-scoped `BEFORE INSERT` trigger,
  `enforce_document_governance_command_target_shape` (owner-owned,
  requiring every owner-change creation-time field non-null, structurally
  incapable of firing on the later retirement `UPDATE`), paired with a
  standing `REVOKE UPDATE ON document_governance_commands FROM
  rag_platform_app` (no column-level exception at all — no runtime path
  ever needs one); the new, directly-invoked `SECURITY DEFINER` function,
  `apply_document_family_owner_change` (owned by `rag_platform_owner`,
  `EXECUTE`-granted to `rag_platform_app`, resolved by exact schema/name/
  signature via `to_regprocedure(...)`, its ACL verified by classified
  `aclexplode()` rather than a fixed row count, its overload-freedom
  verified by an independent name-only query, its own internal target-
  existence and `commands.workspace_id = family.workspace_id` re-checks
  run before both the no-op and mutation branches), reusing ADR-0035's
  own three-role foundation and protected-column pattern, extended by
  this ADR's first non-trigger `SECURITY DEFINER` invocation; the
  deletion-stuck-detection scheduler, including `condition_kind` as
  the third occurrence-identity component disambiguating `stuck` from
  `failed_permanent` within one reclaim generation;
  the `ProjectDocumentGovernanceNotifications` job and its idempotent
  recipient-resolution/receipt-writing logic.
- **Session B — In-product inbox, actionable-work/dashboard projections.**
  The notification bell/inbox browser surface; every dashboard card
  implemented as a named query object against its frozen ADR's own
  eligibility definition; the notification-versus-work-queue boundary
  implemented and tested end to end. Depends on Session A.
- **Session C — Scheduler/reminders, email preferences, envelopes and
  delivery.** The new daily `governance:scan-reminders-and-authority-
  transitions` command, using condition-derived occurrence identities
  throughout; `workspace_notification_settings` (`email_delivery_
  enabled`/`default_email_enabled`)/`user_notification_preferences`; the
  `DocumentGovernanceEmailEnvelope`/`...EnvelopeMember`/
  `...EnvelopeAttempt`/`...EnvelopeMemberDecision` schema, including the
  assembly lifecycle, `terminal_at` and its schema `CHECK` against
  `dispatched_at`/`assembly_status`/`suppression_reason`, the append/seal
  row-lock protocol, the digest sealing/late-arrival rule, the single
  global `envelope → attempt` lock order enforced across every claim,
  verification, result-recording, and reclaim transaction, attempt
  fencing with the provider-timeout/lease-margin invariant, atomic
  reclaim convergence, the generation-1-preflight/retry-stop-check split
  run inside the claim transaction before any attempt row is created, and
  send-time suppression; the `DispatchDocumentGovernanceEmail` job and
  digest grouping; the retention/purge sequencing sweep, anchored on
  `terminal_at`. Depends on Session A; independent of Session B.
- **Session D — Email templates and tenant-branding seam.** The
  versioned template-selection/rendering system; the narrow branding
  input contract and Dolved fallback; email-client-safe markup and
  contrast-fallback logic; the reserved (not implemented) preview seam.
  Depends on Session C.
- **Session E — Visual/accessibility checkpoints and Playwright
  verification.** Every named staged visual checkpoint (product surface
  and email alike), in order, with David's direct browser review at
  each; the required Playwright journey. Depends on Sessions B, C, and D
  all being functionally complete.

## Required final report

This is the thirteenth implementation-readiness correction pass on
ADR-0036. It resolved two factual contradictions in the effective
login-role membership/access audit introduced by the twelfth pass: an
allowlist matrix row permitting a "bootstrap" role to be `rolsuper =
true` while simultaneously expecting zero reachability (a genuinely
impossible combination), and a claim that PostgreSQL permits
constructing a circular role-membership grant, used to justify the
diagnostic query's own cycle guard.

1. **Exact sections changed**: "Exact allowlist matrix,
   environment-versioned" (table split into separate non-superuser-
   bootstrap and cluster-superuser rows, with new `rolcreatedb` column;
   the combined "Deployment/bootstrap/break-glass roles" prose paragraph
   replaced by four distinct subsections — named non-superuser
   bootstrap/deployment identity, named cluster superuser, managed-
   service environments, and the corrected isolation invariant); "Final
   deployment-gate ordering" (fail-closed list extended with the five
   named superuser/bootstrap conditions); the recursive diagnostic-path
   query's own explanatory text (cycle-guard justification corrected);
   the effective-audit test paragraph (superuser/bootstrap test
   descriptions split and corrected; cyclic-membership test replaced
   with the real-PostgreSQL-rejection test plus the synthetic traversal
   test); Alternatives considered (two entries added); Testing
   (twelfth-pass cross-reference corrected; thirteenth-pass coverage
   block added); Phase/session allocation (Session A updated); this
   report.

2. **Restricted bootstrap matrix row**: `rolsuper = false` (exact,
   never environment-varying); `rolcreaterole`/`rolcreatedb` stated as
   the environment's own exact, reviewed values for its bounded
   provisioning task; every `MEMBER`/`USAGE`/`SET` path to all three
   protected roles `false`; effective `EXECUTE` `false`. A label
   containing "bootstrap" never, by itself, justifies broader authority
   than this row states; the role provisions the boundary, it does not
   sit inside it.

3. **Cluster-superuser matrix row**: `rolsuper = true` (the expected,
   truthful value, not a violation); every `MEMBER`/`USAGE`/`SET`/
   `effective_execute` cell honestly `true`, since a genuine superuser
   bypasses every catalog ACL and role-membership boundary this ADR
   establishes — `pg_has_role()`/`has_function_privilege()` report this
   correctly on their own, with no special-casing needed in the audit
   query itself. Accepted only as a named, infrastructure-only exception;
   credential absence from every long-running service is verified
   separately, by deployment-configuration review, since the SQL audit
   cannot itself observe secrets placement. An additional or differently
   named superuser still fails the gate.

4. **Managed-environment behaviour**: where the environment manifest
   states no customer-visible `rolsuper` role exists, this matrix does
   not invent one — the provider-managed administrative role(s) actually
   visible are classified explicitly by name, with their real observed
   catalogue attributes recorded truthfully; unexpected powerful roles
   still fail closed in this environment exactly as in any other.

5. **Final isolation invariant**: "No ordinary application or service
   login has privileged reachability; the explicitly named
   infrastructure superuser (or provider-managed administrative role) is
   inherently privileged but operationally isolated" — corrected from,
   and explicitly distinguished against, the false-by-construction "no
   login of any kind can ever reach protected authority."

6. **Circular-grant factual correction**: withdrawn the claim that
   "PostgreSQL itself permits granting role A to role B and role B to
   role A" — PostgreSQL's own `GRANT role TO role` rejects a circular
   membership grant at grant time; a cyclic `pg_auth_members` graph is
   not a reachable state on a normally operating cluster. The recursive
   diagnostic query's own cycle guard is retained, restated honestly as
   defensive programming against malformed catalogue data, future reuse
   against a less-constrained edge source, or implementation error —
   never as protection against a state PostgreSQL itself already
   forbids.

7. **Real PostgreSQL circular-grant test**: create role A, create role
   B, `GRANT A TO B`, attempt `GRANT B TO A`, assert the second grant is
   rejected against the actual supported error behaviour (not brittle
   exact wording), and assert the role audit remains fully functional
   after the rejected statement's own transaction is cleaned up.

8. **Synthetic traversal-cycle test**: the recursive path-building
   query's own cycle guard is exercised directly against a synthetic
   edge relation or an isolated query fixture deliberately containing a
   cycle — never genuine `pg_auth_members`, which cannot hold one —
   asserting the guard terminates and reports the path safely, as
   defensive-diagnostics coverage only, never presented as evidence
   about real database state. The authoritative live-role checks remain
   `pg_has_role()`, real catalogue membership data, and effective
   function-privilege evaluation.

9. **Confirmation no settled decision changed**: the login-role
   enumeration model, the `pg_has_role()`-based membership/usage/`SET`
   evaluation, the acquisition algorithm, the command schema, the insert
   guard, the composite workspace binding, the column-targeted family-
   deletion referential action, the function's own ACL/overload
   verification, the generation algorithm, the two-digest lifecycle,
   complete result fencing, envelope/digest/result state, branding/
   templates, retention values, visual decisions, the Laravel/Python
   boundary, and every ADR-0030–0035 decision are all unchanged by this
   pass — both corrections make the matrix and the cycle-guard
   explanation factually accurate about the identical, already-agreed
   audit mechanism, never redesign it.

10. **Remaining blockers**: none identified in this pass.

11. **Codex-audit readiness**: ready — both identified factual
    contradictions are resolved with PostgreSQL-behaviour-accurate
    matrix categories and an honestly justified cycle guard, consistent
    with every closed area from every prior pass.

12. **New SHA-256**: computed and reported alongside this message from
    the file's current on-disk state.

13. **Exact files touched**:
    `docs/adr/0036-define-document-governance-notifications-and-reminders.md`
    only.

**Confirmed**: `Status: Proposed` unchanged throughout. ADR-0030–0035
were not modified. Nothing else was modified, committed, tagged, pushed,
accepted, or sent to any provider.
