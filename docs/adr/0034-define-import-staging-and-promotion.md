# ADR 0034: Define Import Staging and Promotion

## Status

Accepted

## Date

2026-08-28

## Relationship to prior ADRs

This ADR sits entirely upstream of the existing ingestion outbox/claim
pipeline (ADR-0008/0015/0016): no `document.ingestion.requested` event is
published before promotion commits. It consumes ADR-0030 (metadata
classification, checksum state), ADR-0031 (`CreateDocumentVersion`,
`CreateApplicabilityOnlySuccessor`, governance actions, family-lineage
locking), and ADR-0032 (canonical artefact, conditional-create/write-once
object protection) as frozen Tier 1 (`TIER_1_READY_TO_FREEZE`) without
reopening any of their decisions. It supplies the `ImportItem`/
`PromotionAttempt` primitives ADR-0035's bulk operations, ADR-0036's
notification triggers, and ADR-0037's export will consume.

## Context

No staging, matching, or promotion mechanism exists anywhere in this
codebase today. Verified directly: three distinct browser-facing
mutation stages currently exist for a single upload — `InitializeDocumentUpload`
(presigned URL), `CompleteDocumentUpload` (`UPLOADING → UPLOADED`), and
`RequestDocumentIngestion` (`UPLOADED → QUEUED` plus outbox publication,
a separate Action and route from completion) — each with no family-
targeting parameter and no batching. `DocumentObjectStorage`
(`apps/api/app/Services/Documents/DocumentObjectStorage.php`), read in
full, wraps Laravel's generic filesystem abstraction (`temporaryUploadUrl`)
with no conditional-create, write-once, or object-versioning support
today. `WorkspaceMembership` carries no location field. ADR-0007's
technical lifecycle has no valid transition from `UPLOADING`/`UPLOADED`
directly to a terminal failure or expiry state — "an interrupted upload
simply does not progress" is explicitly *not* itself a terminal condition.

This revision responds to Codex's `TIER_2_BLOCKED_PENDING_CORRECTION`
finding that earlier drafts left the legacy-upload cutover incomplete
(omitting `RequestDocumentIngestion` and any real drain-completion
condition), left several relational and concurrency primitives
unspecified (decision-snapshot identity, idempotency scoping, matching
determinism, live-authorization-at-commit), left the preflight transport
direction unselected, and retained an unsafe checksum-immutability
fallback (rehashing an object immediately before commit does not close
the gap between that rehash and the commit itself, since object storage
sits outside the database transaction and bytes could still be replaced
in between).

## Decision

### Unified upload model

Every new, user-facing document upload — one file or many — uses
`ImportBatch`/`ImportItem` in V1; there is no permanent second path. The
existing `InitializeDocumentUpload`/`CompleteDocumentUpload`/
`RequestDocumentIngestion` browser-facing entry points exist only as a
bounded, temporary migration adapter, drained per "Legacy cutover and
drain" below, never as a permanent bypass of staging, matching, metadata
review, checksum verification, promotion, or audit.

### Relational invariants

**`ImportBatch`**

| Column | Constraint |
|---|---|
| `id`, `public_id` | Internal/public identity |
| `workspace_id` | `restrictOnDelete()`, composite-scoped with every child table below |
| `initiated_by_user_id` | Human actor provenance (see "Actor/system provenance" below) |
| `status` | Bounded enum: `open`, `resolved`, `expired` |
| `retention_expires_at` | Non-nullable, set at creation from the configurable seven-day default |
| `created_at` / `updated_at` | |

`open`/`resolved` are non-terminal in the sense that items within the
batch may still be individually acted on; `expired` is reached only by
the bounded retention sweep (below) and is immutable once set — no batch
transitions out of `expired`.

**`ImportItem`**

| Column | Constraint |
|---|---|
| `id`, `public_id` | Stable public identity |
| `import_batch_id`, `workspace_id` | Composite FK: `(import_batch_id, workspace_id)` references `(id, workspace_id)` on `import_batches` — **an item's `workspace_id` must match its batch's workspace through this composite identity, never independently settable** |
| `staged_object_key` | Exact, unique per item |
| `source_checksum_sha256`, `media_type`, `size_bytes` | Nullable until preflight verification completes; **immutable once verification succeeds** — no later write path may alter them |
| `preflight_status` | Bounded enum: `pending`, `verified`, `rejected` (with a bounded rejection-reason code) |
| `match_status` | Bounded enum: `pending`, `resolved` |
| `current_decision_snapshot_id`, `id` | **Composite FK** `(current_decision_snapshot_id, id)` references `ImportDecisionSnapshot (id, import_item_id)` — see "Binding the current decision snapshot" below |
| `replaced_by_import_item_id`, `import_batch_id`, `workspace_id` | **Composite, self-referencing FK** `(replaced_by_import_item_id, import_batch_id, workspace_id)` references `ImportItem (id, import_batch_id, workspace_id)` — see "Binding replacement lineage" below |

**Composite unique target required for the self-referencing FK above**:
`UNIQUE (id, import_item.import_batch_id, import_item.workspace_id)` on
`ImportItem` itself — i.e. `UNIQUE (id, import_batch_id, workspace_id)` —
already implied by `id` alone being unique, but declared explicitly here
because a composite foreign key must reference an explicitly-declared
composite unique (or primary key) target, not merely infer one from a
single-column uniqueness.

**Binding the current decision snapshot to its `ImportItem` —
structurally, not by application validation alone:**

- The unique target this composite FK relies on,
  `UNIQUE (id, import_item_id)` on `ImportDecisionSnapshot`, is already
  established above (see "Composite uniqueness required for relational
  integrity").
- `ImportItem`'s own composite FK, `(current_decision_snapshot_id, id)`
  referencing `ImportDecisionSnapshot (id, import_item_id)`, means the
  database itself rejects any attempt to set
  `current_decision_snapshot_id` to a snapshot whose `import_item_id`
  does not equal this exact `ImportItem`'s own `id` — including a
  snapshot belonging to a different item in the **same** batch, which a
  bare, non-composite foreign key to `ImportDecisionSnapshot.id` alone
  would not have caught.
- **Nullable**: `current_decision_snapshot_id` is `NULL` until the item's
  first decision review produces its first snapshot — a genuinely
  unresolved-decision state, not an error.
- **Deletion**: `restrictOnDelete()` — a decision snapshot can never be
  removed while any `ImportItem` still names it as current, consistent
  with decision snapshots being immutable, retained lineage records, never
  transient rows.
- **Update**: `current_decision_snapshot_id` is reassigned, forward only,
  exactly when a corrected decision produces a new snapshot (per
  "Promotion decisions, revisions, and `PromotionAttempt` identity"
  above) — the FK itself is never updated by cascade, since a decision
  snapshot's own `id`/`import_item_id` pair is immutable and never
  changes once written.

**Binding replacement lineage to the same batch and workspace —
structurally:**

- `ImportItem`'s composite FK, `(replaced_by_import_item_id,
  import_batch_id, workspace_id)` referencing
  `ImportItem (id, import_batch_id, workspace_id)`, means the database
  rejects any replacement reference whose target does not share **both**
  the referencing row's own `import_batch_id` **and** `workspace_id` —
  cross-batch and cross-workspace replacement lineage are both
  structurally impossible, not merely disallowed by convention.
- **Self-replacement is explicitly rejected** by an additional `CHECK
  (replaced_by_import_item_id IS NULL OR replaced_by_import_item_id <> id)`
  constraint — the composite FK alone does not prevent an item from
  naming itself, since it would trivially satisfy
  `(id, import_batch_id, workspace_id) = (id, import_batch_id,
  workspace_id)`.
- **Nullable**: `NULL` for every item that has not been replaced — the
  ordinary case.
- **Deletion**: `restrictOnDelete()` — a replaced-by target can never be
  removed while the original, superseded item still references it,
  preserving immutable import lineage rather than silently detaching it
  into an unresolvable dangling reference.
- **Update**: `replaced_by_import_item_id` is set exactly once, at the
  moment a replacement item is created for a failed/rejected original —
  it is never reassigned afterward, and this ADR introduces no
  restoration path that would need to clear or change it.

**Required tests for both composite bindings**: a current-snapshot
assignment from the correct `ImportItem` succeeds; an assignment
attempting to bind a snapshot belonging to a **different** item in the
**same** batch is rejected by the database; an assignment attempting to
bind a snapshot from another batch or workspace is rejected; a
replacement reference to an item in the same batch and workspace
succeeds; a cross-batch replacement reference is rejected; a
cross-workspace replacement reference is rejected; a self-replacement
attempt is rejected by the `CHECK` constraint; the nullable pre-decision
state (`current_decision_snapshot_id IS NULL`) and the nullable
unreplaced state (`replaced_by_import_item_id IS NULL`) both remain valid,
unconstrained states; and every existing retry, adoption, and idempotency
flow (per "Promotion decisions, revisions, and `PromotionAttempt`
identity" and "Live authorization at commit" above) continues to pass
unaffected, since neither composite binding changes when or how an
attempt, snapshot, or replacement item is created — only what the
database now structurally proves about the references between them.

**No promotion may begin before `preflight_status = 'verified'` and
`match_status = 'resolved'`, plus the full readiness criteria already
established** (checksum/size/media verified; exactly one family/successor
decision; every required ADR-0030 field present; applicability resolved;
no unresolved duplicate; no unresolved matching ambiguity).

**Decision snapshot** — a dedicated, immutable row, not a bare JSON blob
with no identity of its own:

| Column | Constraint |
|---|---|
| `id`, `public_id` | |
| `import_item_id` | FK, `restrictOnDelete()` |
| `schema_version` | Versioned, so a later field-set change is detectable |
| `canonical_definition` | Canonical JSON (the same RFC 8785 rule ADR-0032 already establishes, reused rather than reinvented, since this is exactly the same cross-language-determinism problem) |
| `digest_sha256` | SHA-256 over `canonical_definition` |
| `actor_user_id`, `created_at` | |

**Composite uniqueness required for relational integrity**:
`UNIQUE (id, import_item_id)` on the decision-snapshot table — this is
what lets `PromotionAttempt` bind to a snapshot **and** structurally
guarantee it belongs to the same item, below.

**Exact included fields**: the family/successor decision (new family, or a
named existing family's `public_id` as predecessor target), every ADR-0030
field under review (title, description, category, tags, owner, review
date, publisher/source label, source URL), and the applicability
selection. **No uncontrolled or raw source content of any kind** — never
extracted text, never file bytes, never anything beyond these bounded,
already-validated fields. **Immutable once the `PromotionAttempt`
referencing it is created** — a decision snapshot is never edited in
place; a changed decision produces a new snapshot row entirely.

**`PromotionAttempt`**

| Column | Constraint |
|---|---|
| `id`, `public_id` | |
| `import_item_id`, `workspace_id` | Composite FK, mirroring `ImportItem`'s own workspace binding |
| `decision_snapshot_id`, `import_item_id` | **Composite FK** `(decision_snapshot_id, import_item_id)` references `(id, import_item_id)` on the decision-snapshot table — **structurally**, not merely by convention, prevents a `PromotionAttempt` from ever referencing a snapshot belonging to a different `ImportItem`, the same composite-FK pattern ADR-0017 already uses for `documents_predecessor_workspace_family_foreign` |
| `attempt_ordinal` | **`UNIQUE (import_item_id, attempt_ordinal)`** — unique within its item, monotonically increasing, so attempts have a stable, human-legible sequence independent of timing |
| `status` | Bounded enum, exactly the state-machine vocabulary below |
| `reserved_object_key` | The content-addressed permanent key (below) |
| `checksum_evidence` | The bound checksum/size/media-type/write-once-or-version-identity proof, per "Immutable object proof" below |
| `lease_token`, `lease_generation`, `lease_expires_at` | Mirroring `IngestionEventClaim`'s existing shape |
| `failure_count` | Derived from distinct recorded failure rows (below), never a bare incrementable integer |
| `cancellation_requested_at` | Nullable |
| `actor_type`, `actor_user_id`, `system_actor_code` | See "Actor/system provenance" below |
| `actor_identity` | **Non-nullable canonical actor identity**, database-derived from the XOR provenance columns; see "Idempotency" below |
| `operation_kind`, `client_idempotency_key` | Bounded operation enum plus a non-null, length-bounded client key |
| `request_digest_sha256` | Non-null SHA-256 over the canonical, validated idempotent request payload |

**Exactly one open (non-terminal) `PromotionAttempt` per `import_item_id`**,
enforced by a partial unique index scoped to non-terminal `status` values.

**Idempotency — a non-null canonical actor identity, not a nullable
column**

**Corrected**: a `UNIQUE` constraint containing a nullable `actor_id`
column cannot deduplicate system-initiated operations in PostgreSQL —
two distinct `NULL` values are never considered equal by a unique index,
so two system-actor rows that should be treated as the same idempotent
operation would silently fail to collide. **Selected fix: a single,
non-nullable `actor_identity` column**, formatted as an explicitly typed
namespace — `user:{actor_user_id}` for a human actor, `system:{system_actor_code}`
for a system actor — always present, always comparable, and incapable of
colliding between the two namespaces by construction (a two-partial-
unique-index alternative was considered and rejected as more complex for
no additional safety, since a single non-null column achieves the same
guarantee with one ordinary index rather than two conditional ones). This
preserves the existing `actor_type`/`actor_user_id`/`system_actor_code`
XOR provenance columns unchanged.

This identity is database-enforced, not trusted from application input:
PostgreSQL defines it as a `GENERATED ALWAYS AS (...) STORED` value whose
expression selects the `user:` or `system:` namespace from `actor_type`
and the corresponding non-null provenance column. The actor XOR `CHECK`
below makes every other shape invalid. The application never supplies or
updates `actor_identity` directly. The provenance columns remain the
authoritative actor fact; the generated value exists specifically to give
the unique index a safe, non-null identity that cannot disagree with them.

Scoped unique identity:

```
UNIQUE (workspace_id, import_item_id, actor_identity, operation_kind, client_idempotency_key)
```

stored alongside a request/payload digest (the same canonical-digest
approach the decision snapshot itself uses).

The exact database rules are therefore: the actor XOR `CHECK`; `NOT NULL`
on the generated `actor_identity`, `operation_kind`,
`client_idempotency_key`, and `request_digest_sha256`; and the ordinary
unique constraint above. Tamper tests attempt both actor identifiers,
neither actor identifier, and an application-supplied generated identity;
all fail at the database boundary. Concurrent inserts with the same human
or system identity and digest converge on one attempt; the same identity
with a different digest returns the typed conflict.

- Same key, same digest → returns the prior attempt/result — a safe,
  repeatable no-op.
- Same key, different digest → a typed conflict, rejected outright, never
  silently applied.
- **No cross-user or cross-workspace suppression** — the identity includes
  both, so a coincidentally identical key from a different actor or
  workspace shares no state.
- **A same-decision technical retry** (after `FAILED`) **submits a new,
  distinct `client_idempotency_key`, bound to the same, unchanged decision
  snapshot** — it is never mistaken for a replay of the original
  submission, because the idempotency identity's `operation_kind`/key
  differs even though the snapshot does not.
- **A corrected-decision retry** (after `CONFLICT`) creates a new decision
  snapshot row entirely, which a new `PromotionAttempt` then binds to — the
  idempotency identity and the decision identity change together.

**Counts** — `ImportBatch`'s aggregate counts (ready/warning/failed/
processing/complete) are **always derived, read-model values computed from
current `ImportItem`/`PromotionAttempt` state** — never a second,
independently mutable counter capable of disagreeing with the rows it
summarises. Any materialised projection or cache used for display
performance is rebuildable from the underlying rows at any time and is
never itself authoritative.

**Actor/system provenance** — the same explicit XOR pattern ADR-0030
already establishes for its audit table, reused here rather than
reinvented: `actor_type` is `human` or `system`; a human row requires
`actor_user_id IS NOT NULL` and `system_actor_code IS NULL`; a system row
requires the reverse, with `system_actor_code` drawn from a bounded
vocabulary (illustrative: `promotion_reconciler`, `retention_sweep`,
`legacy_drain_reconciler`); a database `CHECK` constraint enforces exactly
one form; only safe, allowlisted audit data is ever recorded in either
form.

### Staging storage — acceptance requirement

The existing `s3_uploads` disk may be reused only if implementation-time
inspection proves: a private, non-public policy; workspace/item-scoped
exact keys with no listing or prefix authority ever granted to a client;
purpose- and time-bounded presigned operations; no reachability from the
retrieval/search pipeline; exact-key cleanup capability; protection
against cross-workspace key substitution; and encryption at rest wherever
the deployed policy already requires it elsewhere. If any criterion fails,
R25-S01 extends or replaces the configuration before staging ships — a
verification task, not an open decision.

### Preflight — selected transport: asynchronous, worker-topology-consistent

**Selected, after inspecting the actual worker topology**: verified that
Python's role throughout ADR-0008/0015/0016 is exclusively as an
SQS-consuming worker claiming outbox-published events — there is no
existing precedent for Laravel calling Python synchronously in the
ingestion-adjacent pipeline (the one synchronous Laravel-to-Python
protocol in this codebase, ADR-0018's `rc1`, belongs to retrieval/
generation, a materially different, latency-sensitive subsystem preflight
has no reason to imitate). **Asynchronous, outbox/worker-topology-
consistent preflight is selected accordingly:**

1. Laravel, in one transaction, creates a durable `ImportPreflightAttempt`
   (workspace/import-item identity, an `event_id`, lease fields mirroring
   `IngestionEventClaim`'s shape, an authorised exact staged-object
   reference) and a dispatch/outbox record, published through the
   existing outbox mechanism (ADR-0008, unmodified) under a new event
   type, `import.preflight.requested`.
2. Python receives the work through the existing SQS-consumption topology
   — no new delivery mechanism, only a new event type and consumer
   registration.
3. **Python reads only the exact staged object Laravel named** — a
   short-lived, purpose-scoped read capability (the same mechanism
   ADR-0032 already establishes for its own artefact fetches), never a
   browser-supplied or self-chosen location. The exact key appears in the
   dispatched event payload, itself only reachable by the authorised
   worker process, never by the browser.
4. Python reports success or failure through a **new, purpose-scoped,
   HMAC-authenticated Python→Laravel callback** — `import.preflight.complete`
   / `import.preflight.fail` — the inbound direction only.
5. **`AuthenticateIngestionWorker` applies exclusively to this inbound
   callback direction** — it authenticates Python calling Laravel, never
   the outbound dispatch, which is Laravel's own outbox publication and
   requires no HMAC of its own (it is not a request Python must prove
   anything about; it is Laravel's own durably-committed intent).
6. Laravel reconciles the callback against the current
   `ImportPreflightAttempt`, its `event_id`, its lease generation, and the
   staged-object identity before trusting anything reported.

**Dispatch/request schema** (Laravel-owned, published via outbox, not
HMAC-signed — it is Laravel's own durable event, not a request Python must
authenticate): `event_id`, `contract_version`, `workspace_id`,
`import_batch_id`, `import_item_id`, the exact staged object reference,
declared media type, **`lease_token` and `lease_generation` together** —
corrected here to include the generation, not the token alone, since the
generation is what every other lease-gated check in this decomposition
actually treats as authoritative.

**Result/callback schema** (`import.preflight.complete` /
`import.preflight.fail`, HMAC-authenticated, `contract_version`-carrying):
`event_id`, `workspace_id`, `import_item_id`, **`lease_token` and
`lease_generation`, both echoed back from the dispatch and both covered
by the HMAC signature** (never appended outside the signed payload), a
**bounded result vocabulary** — `readable`, `password_protected`,
`encrypted`, `corrupt_structure`, `mime_mismatch` — plus a typed
diagnostic code, never a raw parser exception message.

**Laravel accepts a reported result only when both the token and the
generation match the currently authoritative lease** for that
`ImportPreflightAttempt` — checked together, under the same row lock
every other lease-gated transition in this decomposition already uses. A
callback presenting a stale generation, a mismatched token, or a replay
of an already-processed `event_id` **fails closed and mutates nothing**
— `preflight_status` is left exactly as it was, and the mismatch is
logged (allowlisted identities only) as a rejected callback, never
silently accepted or silently ignored without a trace.

**Both directions, fully specified**: route names and purpose strings are
implementation detail (illustrative: `import.preflight.requested`
outbound, `import.preflight.complete`/`import.preflight.fail` inbound);
Laravel signs nothing outbound (it is an internal outbox publish, not a
request requiring proof); Python signs the inbound callback using the same
HMAC scheme every other worker callback in this codebase already uses;
replay/event identity is `event_id`, matching the existing convention;
timeout and reclaim mirror `IngestionEventClaim`'s existing lease-renewal/
expiry discipline exactly; a duplicate matching callback (same `event_id`,
same reported result) is a safe no-op; a conflicting callback (same
`event_id`, a different reported result) fails closed; a stale-lease-
generation callback is rejected outright; a callback arriving after the
`ImportItem`'s own cancellation or the batch's expiry is accepted for
logging but never changes `preflight_status`, since the item it concerns
is already beyond acting on. **No provider calls, no extraction artefact,
no chunks, no vectors are produced during preflight.** Logging is
allowlisted to stable identities and typed outcomes only — never source
bytes or raw filenames. **Python reports technical facts only; Laravel
alone maps the result to `ImportItem.preflight_status` and the recovery
action offered.**

**Honest boundary, stated plainly**: Laravel alone determines declared
extension/MIME plausibility, configured size limits, and zero-byte
rejection — no Python call needed. Only password-protection/encryption
detection and structural-corruption detection require Python's existing
parsing libraries; any parseability failure beyond these two specific,
provably-detectable conditions surfaces only during downstream extraction,
after promotion, as ADR-0007's existing `FAILED` outcome.

### Matching and reconciliation — deterministic, concurrency-safe

**Exact duplicates**: a checksum match against any `Document` in the
**same workspace** whose `checksum_verification_status = 'verified'` and
whose technical status is one of `UPLOADED`, `QUEUED`, `PROCESSING`,
`INDEXED`, or `FAILED` — governance state (current, draft, withdrawn) does
not affect eligibility to be matched, since re-uploading identical bytes
against any of these is a genuine duplicate regardless of the matched
version's governance standing.

- **A `DELETING`/`DELETED` (tombstoned) version's retained checksum is
  never a blocking duplicate** — a no-restoration deletion must not
  permanently prevent re-importing the same bytes. If a checksum matches
  only tombstoned versions, the UI shows a **non-blocking, informational
  warning** ("similar to a previously deleted document") and the import
  proceeds as an ordinary new-family or new-version decision — explicitly
  distinguished from a blocking duplicate against live content.
- **If identical bytes match a live version and the intended change is
  applicability-only**, the UI directs the user to ADR-0031's
  `CreateApplicabilityOnlySuccessor` action instead of re-uploading
  identical content through this ADR's promotion path — the two are
  different operations, and this ADR's duplicate guard exists to prevent
  the wrong one being used, not to block the right one.
- Otherwise: the user is told an identical document already exists, with
  safe actions to open it or remove the import item. No second version is
  ever created for identical bytes merely because the filename differs.

**Possible family matches**, using exactly these V1 fields, each
normalised (Unicode NFC, case-fold, whitespace-collapse, punctuation
stripped, extension removed from filenames, control characters rejected,
length-capped): the cleaned filename stem; the existing family's human
title. **Original source filename is used only where it is itself
already normalised** identically. **Publisher/source label, category, tag,
owner, and applicability are never used as similarity signals** — none of
these are semantic-title facts, and using them would conflate
organisational metadata with content identity.

- **Bounded candidate count**: at most five suggested families.
- **Deterministic ordering**: by normalised-string match score descending,
  tie-broken by the candidate family's own `public_id` ascending — never
  an unordered or timing-dependent result.
- **No automatic selection at any score** — every possible match is a
  suggestion the user must explicitly accept or reject, regardless of how
  close the score is.
- **The match-score threshold is a versioned configuration value** —
  numeric tuning is legitimate R25 implementation measurement, but the
  threshold itself is bound to a tracked configuration version, never a
  bare magic number, so a later change is itself a visible, testable
  change.
- **Unsupported or empty titles** (a family with no title, or a staged
  file whose cleaned stem is empty after normalisation) produce **no**
  suggested candidates from that side of the comparison — never a match
  against an empty string.
- **No LLM or embedding-provider call of any kind.**

### Shared workspace/checksum serialization primitive

**Corrected: an earlier design excluded ADR-0031's applicability-only
clone from this ADR's checksum-reservation mechanism entirely, on the
reasoning that a clone's identical checksum is intentional, not a
duplicate.** That reasoning is correct about *duplicate rejection*, but
it does not follow that clone should skip *serialization* — without a
shared lock, an import promotion and a concurrent clone for the same
underlying bytes could each read the current `Document` set before the
other commits, producing exactly the phantom-duplicate/phantom-absence
race this mechanism exists to prevent. **Verified against ADR-0031's own
text: it defines no workspace-checksum lock, reservation, or serialization
mechanism of any kind, and states nothing that would forbid one — there is
no Tier-1 contradiction here, and this correction is resolved entirely
within this ADR, as the consumer-side specification both this ADR's own
promotion path and ADR-0031's clone operation must follow.**

**`WorkspaceChecksumReservation`** — one durable row per
`(workspace_id, source_checksum_sha256)`:

| Column | Constraint |
|---|---|
| `workspace_id`, `source_checksum_sha256` | `UNIQUE (workspace_id, source_checksum_sha256)` — the row's whole purpose is to exist as a stable lock target for this pair |

- **Creation**: `INSERT ... ON CONFLICT (workspace_id, source_checksum_sha256) DO NOTHING`,
  issued by whichever operation (promotion or clone) first needs to
  serialise on that pair — race-free by construction, since Postgres
  resolves concurrent first-insertion attempts deterministically, with
  exactly one succeeding and every other observing the conflict and doing
  nothing.
- **Persists permanently, reused indefinitely** — it is never deleted
  after use. The row itself blocks nothing merely by existing; only a
  transaction actually holding its lock does, and that lock is released
  the instant the holding transaction ends. A future operation against
  the same checksum (another clone, a later re-upload of tombstoned
  content) reuses the same row rather than needing to recreate it.
- **Acquisition**: the idempotent insert above followed immediately by
  `SELECT ... FOR UPDATE` against the row is the **first lock acquisition
  inside the already-short, already-bounded final transaction** each
  consuming operation already performs — promotion's
  own final revalidation-and-commit transaction (step 6–7 below), and
  ADR-0031's clone operation's own analogous final transaction (its
  `SOURCE_VERIFIED → COMMITTED`-equivalent step, where it verifies
  six-layer completeness and transitions the target to `INDEXED`). The
  checksum lock is acquired before the existing item/family/version locks;
  once it is held, each consumer retains ADR-0031's family-first
  deterministic order for every remaining family/version lock. **No
  lease is required for this lock**: because it is acquired and released
  entirely within one ordinary, already-bounded database transaction —
  never held across the out-of-transaction object-copying work either
  operation also performs — ordinary PostgreSQL transaction semantics
  already provide everything a lease would otherwise exist to provide.
  Crash or disconnection during the holding transaction causes PostgreSQL
  to roll back that transaction and release the lock immediately, with no
  application-level reclaim logic needed.
- **Concurrent first-creation**: resolved by the `ON CONFLICT DO NOTHING`
  insert above — never a race between two competing row-creation
  statements.
- **Rollback**: if the transaction that inserted the row (when it did not
  already exist) subsequently rolls back for unrelated reasons, the
  row's insertion rolls back with it — harmless, since the row carries no
  state beyond its own existence as a lock target; the very next operation
  on that checksum simply recreates it via the same idempotent insert.
- **Why it cannot indefinitely block subsequent operations**: the lock is
  never held longer than the single short transaction that already bounds
  every other terminal decision in this ADR's state machine — the same
  transaction that would otherwise be taken regardless of this
  correction, not a new, separately-timed critical section.

**Required concurrency ordering, followed identically by both consumers:**

1. Both promotion and clone acquire the shared lock on
   `(workspace_id, source_checksum_sha256)` as the first step of their
   respective final transactions.
2. **Only after acquiring it** do they query or re-query the current live
   `Document` rows for that workspace and checksum — never before.
3. **Ordinary import promotion** applies its exact-duplicate detection
   (above) to this freshly-locked result.
4. **An applicability-only clone may bypass duplicate *rejection*** —
   because its matching-checksum content is intentional — **but never
   bypasses this lock**. It still acquires the same lock, still performs
   the same fresh re-query, and then proceeds to satisfy ADR-0031's own
   clone invariants (the compatibility proof, the predecessor's current
   claim state) against that fresh, lock-protected view. **"Clone bypasses
   duplicate detection" never means "clone bypasses serialization"** —
   these are two independent decisions this correction keeps carefully
   separate.
5. The transaction commits its authoritative result (a promoted
   `Document`, or a cloned target version) **before** releasing the lock.
6. Any operation that was waiting on the lock now acquires it and
   re-queries, correctly observing the just-committed result.

**No cross-workspace contention**: the row is keyed by `workspace_id`
first, so identical bytes in two different workspaces never share a row
or a lock.

**Required tests**: two import promotions racing the same checksum (only
one may commit as the "first" live document; the other observes it on
re-query and reacts per exact-duplicate handling); an import promotion
racing a clone in each starting order (both orderings must serialise
correctly, never interleave); two clones racing the same checksum;
transaction rollback immediately after lock acquisition (the lock
releases, the row's own insertion may or may not persist depending on
whether it was pre-existing, and a subsequent attempt proceeds normally);
retry after a simulated crash mid-transaction (PostgreSQL's own rollback
already handles this; the test proves no stuck lock results); an already-
existing, previously-used reservation row being reacquired cleanly by a
later, unrelated operation; and confirmation that two workspaces with
byte-identical content never contend with each other.

### Metadata review and promotion readiness

Unchanged: exactly ADR-0030's classified fields reviewed with sensible
defaults, clearly-optional fields, collapsible advanced detail, no forced
setup. Promotion readiness requires: checksum/size/media verified; exactly
one family/successor decision; every required field present; applicability
resolved; no unresolved duplicate decision; no unresolved matching
ambiguity.

### `PromotionAttempt` state machine

```
RESERVED → COPYING → SOURCE_VERIFIED → COMMITTED
                                     ↘ CONFLICT
                    ↘ FAILED (technical exhaustion, from COPYING or SOURCE_VERIFIED)
        (cancellation_requested_at set, from any non-terminal state) → ABANDONED
        (retention elapsed, no valid lease) → EXPIRED
```

| From | To | Initiator | Lease required | Terminal? |
|---|---|---|---|---|
| — | `RESERVED` | User submits a promotion request for the current decision snapshot | No (short txn only) | No |
| `RESERVED` | `COPYING` | System claims a copying lease | Yes | No |
| `COPYING` | `COPYING` | Lease expired, `cancellation_requested_at IS NULL`, ceiling not exhausted — reclaim | Yes (renewed) | No |
| `COPYING` | `SOURCE_VERIFIED` | Copy + verification succeeds, `cancellation_requested_at IS NULL` | Yes (re-verified immediately before transition) | No |
| `COPYING` | `FAILED` | Either the current lease holder recording ceiling exhaustion, or a reconciler after confirming no valid lease remains | Yes, one of two authoritative forms — never absent | Yes |
| `SOURCE_VERIFIED` | `SOURCE_VERIFIED` | Lease expired, not cancelled, ceiling not exhausted — reclaim | Yes (renewed) | No |
| `SOURCE_VERIFIED` | `FAILED` | Either form, as above | Yes, one of two forms | Yes |
| `SOURCE_VERIFIED` | `COMMITTED` | Final transaction succeeds under freshly re-verified lease/decision/authorization state | Yes, re-verified under lock immediately before transition | Yes |
| `SOURCE_VERIFIED` | `CONFLICT` | Final revalidation finds a duplicate, invalidated predecessor, or **the initiating actor's authorization has changed** | Yes, re-verified under lock immediately before transition | Yes |
| any non-terminal | *(same state, `cancellation_requested_at` set)* | User requests cancellation | No | No |
| `COPYING`/`SOURCE_VERIFIED` (cancellation set) | `ABANDONED` | System, only once the worker acknowledges or its lease genuinely expires | No | Yes |
| `RESERVED` (cancellation set) | `ABANDONED` | System, immediately — no external work was in flight | No | Yes |
| `RESERVED`/`COPYING`/`SOURCE_VERIFIED` | `EXPIRED` | Retention elapsed, only when no currently-valid lease protects the attempt | No | Yes |
| `FAILED` | *(new attempt, same decision snapshot, new idempotency key)* | User or bounded automated retry | — | — |
| `CONFLICT` | *(new attempt, next decision snapshot)* | User corrects the decision, or a differently-authorized actor adopts the batch (below) | — | — |

**Terminal technical-failure authority**: `FAILED` is recorded only by
the current lease holder presenting the current generation, or by a
Laravel-owned reconciler that has independently confirmed no valid lease
remains, the stored failure count has genuinely reached the ceiling, and
no cancellation/expiry/commit has superseded the attempt. A stale lease
generation can neither increment the failure count nor terminalise the
attempt. Each recorded failure is keyed by `(attempt_id, lease_generation)`
under a uniqueness constraint, so a repeated callback never consumes the
ceiling twice. `COPYING → FAILED` cleanup of any partial object begins
only once worker quiescence is established (the lease has genuinely
lapsed, or the worker has itself acknowledged termination under its
still-current lease); `SOURCE_VERIFIED → FAILED` retains its verified
object only after that same quiescence and the ownership check below both
pass. Every terminal transition in this table shares one attempt-row lock
and terminal-state revalidation, so `FAILED`, `COMMITTED`, `CONFLICT`,
`ABANDONED`, and `EXPIRED` can never race one another — whichever writer
locks the row first while it is still non-terminal is the one outcome
recorded. A lost terminal-failure acknowledgement is idempotent.

**Cancellation quiescence**: a cancellation request sets
`cancellation_requested_at` durably; new claims/renewals are refused from
that moment; a currently-executing worker observes this only at its next
lease-gated boundary, never interrupted mid-flight; Laravel neither marks
`ABANDONED` nor cleans up while a currently-valid lease could still write;
convergence happens only on acknowledgement or genuine lease expiry;
cleanup then proceeds by exact-key checks; a cancellation can never change
an already-`COMMITTED` attempt; a lost cancellation-acknowledgement
response is idempotent. Applied identically from `SOURCE_VERIFIED`.

**Lease and finalizer authority**: `SOURCE_VERIFIED → COMMITTED` and
`SOURCE_VERIFIED → CONFLICT` are decided only under an attempt-row lock
that re-verifies, immediately before the transition: the attempt is still
open; the decision snapshot being evaluated is the one currently bound to
the attempt; the presented lease generation is the current one (a stale
generation is rejected outright — the lease generation is the sole
authoritative claim in V1, with no separate carve-out); and
`cancellation_requested_at` is still null and retention has not expired —
a cancellation or expiry recorded at any point before this check runs
takes priority over it. A stale finalizer failing any of these checks
writes nothing.

**Live authorization at commit**: immediately before `COMMITTED`, under the
same lock, Laravel re-resolves the initiating user's **current** workspace
membership and required capability — never trusting the membership/role
that was true when the attempt was first created. Loss of role or
membership prevents commit and produces `CONFLICT` with the typed reason
`authorization_changed`, recorded without leaking role details in any
user-facing surface. **Python or preflight completion never grants
continuation authority of any kind.** **Another currently-authorized
owner/admin may adopt the `ImportItem`** only through an explicit,
authorized `AdoptImportItem` action, which produces a **new decision
snapshot and a new attempt bound to that adopting actor's own identity** —
never a resumption that impersonates the original actor. This same live-
authorization check applies at **every** browser-facing mutation on the
batch/item, not only at initial creation or final commit.

**Source ownership transfer and cleanup interlock**: the `COMMITTED`
transaction sets the new `Document.storage_key` to the reserved key in the
same transaction that marks the attempt `COMMITTED` — the exact instant
ownership transfers. The reserved-key cleanup sweep must lock the
`PromotionAttempt`/`ImportItem` row and confirm no committed `Document`
references the exact key before ever deleting it; a key is deleted only
when no committed `Document` owns it and no open attempt or valid lease
may still write to it. Deletion of the resulting `Document` later is
exclusively ADR-0025/ADR-0031's concern; this ADR's sweep never touches
that key again once ownership has transferred. Cleanup and commit are
serialised by this same lock, never two independently-timed checks.

**Object reuse across attempts**: the reserved permanent object key is
**content-addressed** — derived from `(import_item_id,
source_checksum_sha256)` — so a corrected or retried attempt whose bytes
are unchanged resolves to the same key automatically (no re-copy, only
re-verification), while a genuinely replaced file resolves to a different
key.

### Immutable object proof — no unproven fallback

**The generic rehash-immediately-before-commit fallback is withdrawn.**
Object storage sits outside the database transaction; rehashing shortly
before commit does not close the window in which bytes could still be
replaced between that rehash and the commit itself. **One of the
following is required, and only one is required per backend
configuration; if neither can be proven, that backend cannot support
staging and promotion fails closed on it:**

1. **Conditional-create / write-once destination**, enforced by the
   storage abstraction — the same mechanism ADR-0032 already establishes
   for its own artefact upload: once written, the object at the exact
   reserved key cannot be overwritten, proven by the storage backend
   itself, not assumed.
2. **An immutable object-version or generation identity**, where the
   backend can independently prove one exists and is stable — persisted
   on the `PromotionAttempt` and then on the `Document`, with every later
   read or deletion addressing that exact version identity, never merely
   "whatever is currently at this key."
3. **Copying verified bytes into a fresh immutable destination and
   committing only that resulting immutable identity** — a concrete way of
   achieving option 1 or 2 rather than a third independent mechanism.

**A generic ETag is never treated as a SHA-256 checksum or as proof of
either option above unless the storage contract independently proves its
exact semantics** — verified that this repository's storage contract does
not currently prove this for any backend in use.

**The final `Document` binds to the exact bytes that produced its recorded
SHA-256**, satisfied only by option 1 or 2, never by a bare existence
check. **`DocumentObjectStorage` is extended, at implementation time, with
whichever primitive (conditional-create or version-identity capture) the
deployed backend actually supports** — this ADR requires the extension,
not a specific vendor API. Commit binds both the storage key and the
proof identity together; cleanup and deletion address that same identity;
no modifiable key is ever committed merely because it was hashed moments
earlier. A retry or corrected attempt reusing an already-verified key
re-verifies the same proof (existence, and the write-once/version binding
itself) before recording its own evidence, rather than inheriting a
possibly-stale record.

### Full sequence

1. **Short transaction**: lock the `ImportItem`, authorise (current
   membership/capability, checked fresh), create the deterministic
   `PromotionAttempt` for the current decision snapshot in `RESERVED`, or
   observe an existing open one per the idempotency rule. Commit; lock
   released.
2. **Short transaction**: claim a copying lease, moving to `COPYING`.
3. **Outside any transaction**: if no object exists at the content-
   addressed key, copy under a conditional-create/write-once (or
   equivalent version-bound) destination; otherwise re-verify the existing
   object and its proof.
4. **Outside any transaction**: verify checksum, size, and media type.
5. **Short transaction, lease- and cancellation-gated**: persist
   `SOURCE_VERIFIED` with the bound evidence.
6. **A fresh, final short transaction**: idempotently ensure and lock the
   workspace-checksum reservation row **first**; then lock the
   `ImportItem` and relevant family/version rows in ADR-0031's existing
   family-first deterministic order; re-verify lease/decision/
   cancellation/authorization currency (above); re-run the exact-duplicate
   query; re-run the conflicting-successor check; re-verify metadata and
   applicability.
7. **Atomically**: create the `Document` via `CreateDocumentVersion` (with
   checksum evidence and `storage_key` populated together, transferring
   object ownership); persist metadata/applicability; publish
   `document.ingestion.requested`; mark the attempt `COMMITTED`.
8. Staged content becomes cleanup-eligible only after `COMMITTED` is
   durably confirmed and only for keys no committed `Document` now owns.

### Legacy cutover and drain — complete, including `RequestDocumentIngestion`

**All three existing browser-facing mutation stages are accounted for**:
`InitializeDocumentUpload` (presigned URL issuance), `CompleteDocumentUpload`
(`UPLOADING → UPLOADED`), and **`RequestDocumentIngestion`**
(`UPLOADED → QUEUED` plus outbox publication — a distinct browser-facing
Action and route from completion, verified directly).

**At cutover:**

1. The browser uses only the new `ImportBatch` path — no legacy route is
   offered as a user choice at any point.
2. **The legacy initialisation endpoint refuses new uploads from the
   cutover moment**, gated server-side (not merely by the client no
   longer calling it), so a cached old client build or a direct API call
   cannot start a new legacy upload after cutover.
3. **The legacy completion endpoint remains available only for uploads
   durably identified as pre-cutover** — a `legacy_upload_initiated_
   before_cutover` marker recorded on the `Document` row at the moment of
   its (still-permitted) initialisation, **never a client-supplied
   claim**.
4. **`RequestDocumentIngestion`'s browser-facing invocation remains
   available only for eligible pre-cutover `Document`s already in
   `UPLOADED` status**, checked against the same durable marker — its
   shared, internal role in the ingestion pipeline (below) is unaffected.
5. **Eligibility is always determined from this durable, server-recorded
   identity — never inferred from timestamps, filenames, or any other
   mutable state, and never asserted by the client.**

**Trustworthy bootstrap of the marker itself** — this repository's current
`UPLOADING`/`UPLOADED` rows all predate the marker's existence, so the
marker cannot simply be "added" without a concrete, safe sequence for
retroactively and atomically deciding which rows it applies to:

1. **Deploy marker and gate support first, while legacy initialisation
   remains temporarily open.** The
   `legacy_upload_initiated_before_cutover` column ships as nullable for
   existing rows, and a singleton durable gate row ships in the open
   state, already bound to one durable `cutover_operation_id`. From that
   deployment onward, every still-permitted
   `InitializeDocumentUpload` transaction first locks and reads that gate
   row and creates its `Document` with the marker set to `true` in the
   same transaction, binding its creation audit to that operation id. The
   marker is never client-supplied. A row created during this transition
   window is therefore marked at creation; the later inventory covers only
   rows that predate marker support.
2. **A resumable bounded inventory** visits pre-marker
   `UPLOADING`/`UPLOADED` rows in stable primary-key batches and marks each
   with the gate row's `cutover_operation_id`, writing its per-row audit in
   the same transaction. Re-running a batch is an idempotent no-op for rows
   already bound to that operation; a different operation id is a typed
   conflict. The gate stays open during these batches, but every newly
   initialized row is already marked by step 1, so the inventory cannot
   chase an unbounded moving set of unmarked rows.
3. **One final bounded gate-close transaction prevents the inventory/
   closure gap**: it first locks the singleton gate row (the same lock
   every initializer must take), rechecks for eligible unmarked rows,
   marks a final remainder only when it is within the configured bounded
   batch ceiling, verifies zero remain, and only then sets
   `legacy_upload_initialization_gate.closed = true` before commit. If the
   remainder exceeds the ceiling, the transaction rolls back without
   closing the gate and the bounded inventory resumes. A concurrent
   initializer either commits before the final transaction (already
   marked at creation) or waits on the gate row and then observes it
   closed; it can never create an accepted unmarked row between inventory
   completion and closure.
4. **Verification**: after the gate-close transaction commits, a read-only
   count confirms every `UPLOADING`/`UPLOADED` row not yet `QUEUED` now
   carries the marker, and that the gate is closed — a simple assertion,
   not a separate mutating step.
5. **Only marked rows are ever accepted by the legacy completion and
   `RequestDocumentIngestion` routes** from this point forward — an
   unmarked row (which, after step 3, can no longer be newly created) is
   rejected outright.
6. **Idempotent restart**: if deployment halts during inventory, committed
   batches and their audits remain valid and the stable cursor resumes;
   an interrupted batch rolls back entirely. If it halts during the final
   gate-close transaction, both the final marks and closure commit
   together or neither does. If it halts after gate closure, steps 4–5 are
   read-only checks, safely repeatable. There is no state in which the gate
   is closed while an eligible unmarked row remains.
7. **Audit evidence**: every row marked by backfill receives a bounded,
   append-only system-actor audit record
   (`system_actor_code = 'legacy_upload_cutover'`) binding that document's
   safe identity to the gate's cutover-operation identity and reason. Rows
   marked at transition-window creation record the same marker reason in
   their creation audit. One bounded cutover summary event records the
   operation identity, total marked count, gate transition, and transaction
   timestamp. The exact set is therefore reconstructable by joining
   bounded per-row audit records on the cutover identity — never embedded
   as an unbounded identity array in one event and never inferred after
   the fact from row timestamps.

**Required tests**: pre-existing rows are marked by the bounded inventory;
a transition-window row is marked in its creation
transaction; a row whose creation is attempted concurrently with the
cutover transaction is deterministically either marked (if it committed
first) or rejected by the now-closed gate (if it did not) — never both
unmarked and accepted; retrying the cutover transaction after a halted
deployment is safe and produces the same marked set and no duplicate
audit facts; an unmarked row is rejected by both the legacy completion
and `RequestDocumentIngestion` routes.

**Bounded drain reconciler, using only real, permitted technical
transitions**: before the legacy completion/ingestion-request routes
close, every pre-cutover legacy `Document` must be classified into
exactly one of:

- **Completed and queued** — reached `QUEUED` through
  `RequestDocumentIngestion`; from this point, shared ordinary ingestion
  owns it, and the legacy browser routes are no longer needed for it.
- **Already terminal** under its existing lifecycle (`INDEXED`, `FAILED`,
  or `DELETED`).
- **Expired/abandoned**, transitioned into a valid, visible terminal state
  through an **explicitly authorised new extension**, described next —
  never left silently "not progressing" and treated as though that were
  terminal.

**New, narrowly-scoped extension, authorised here because ADR-0007's
existing lifecycle has no valid expiry transition for a stalled
`UPLOADING`/`UPLOADED` document**: a new domain-level transition,
`UPLOADING`/`UPLOADED` → `FAILED`, **writing both fields ADR-0007's
existing `FAILED` invariant already requires together** —
`failure_category = 'legacy_upload_drain_expired'` and a human-readable
`failure_message` explaining the drain-expiry cause — reachable **only**
through a new,
narrowly-scoped `ExpireLegacyDrainUpload` Action, invoked **only** by the
bounded drain reconciler against a legacy `Document` whose durable
pre-cutover marker is set and whose drain window has elapsed with no
further progress. **This is not a general new capability** — it does not
change ADR-0007's ordinary rule that `FAILED` is otherwise reached only
from `PROCESSING`; it is a bounded, explicitly-scoped migration extension,
requiring its own migration (the new failure category value, already
representable in the existing `failure_category`/`failure_message`
columns — no new column required), its own Action, and its own tests.

**Route-removal condition is browser-upload drain completion, not
downstream ingestion completion**: the legacy completion and ingestion-
request routes close once every pre-cutover legacy `Document` has reached
`QUEUED` — terminal **for the browser-upload drain's own purposes**, even
though `QUEUED` remains a genuinely non-terminal, intermediate state in
ADR-0007's own technical lifecycle (ordinary ingestion still carries it
onward to `PROCESSING`/`INDEXED`/`FAILED`) — or the new `FAILED`-via-
drain-expiry state. **Not** once every such document reaches `INDEXED`.
Once `QUEUED`, a document is already fully owned by the shared,
unmodified ingestion pipeline, and the legacy browser routes have nothing
further to do for it.

**Shared ingestion-worker callbacks are explicitly distinguished from
legacy browser upload routes and are never retired**: `IngestionEventClaim`'s
claim/lease-renewal/completion/failure/cancellation callbacks remain
permanently, because every document promoted through the new
`ImportBatch` path — not only legacy documents — uses this same shared
ordinary ingestion pipeline after `COMMITTED`. **Only the three legacy
browser-facing entry points (initialise, complete, request-ingestion) are
drained and eventually retired; the internal worker protocol they publish
into is untouched.**

**Required tests**: initialisation immediately before and after the
cutover gate; legacy completion for a document in each pre-cutover state
at cutoff (`UPLOADING`, `UPLOADED`) and after the drain window
(`QUEUED`/terminal/drain-expired); `RequestDocumentIngestion` invoked for
an eligible pre-cutover `UPLOADED` document, and rejected for an
ineligible (post-cutover) one; the drain reconciler's classification of
every pre-cutover state; idempotent replay of a completion call within the
window.

## Alternatives considered

### Leaving the existing direct-upload routes as a permanent, independent
### second path

Rejected — a permanent second path would let content bypass staging,
matching, checksum verification, and audit indefinitely.

### Omitting `RequestDocumentIngestion` from the cutover/drain design

This was an earlier draft's gap and is corrected here: verified directly
that it is a distinct browser-facing Action and route from completion,
performing a real state transition (`UPLOADED → QUEUED` plus outbox
publication) that the drain reconciler must account for independently.

### Treating "not progressing" as a terminal state for drain purposes

Rejected explicitly — ADR-0007 is clear that an interrupted upload simply
not progressing is not itself a terminal condition; pretending otherwise
would leave a real, non-terminal row unaccounted for when the legacy
routes close. The narrowly-scoped `ExpireLegacyDrainUpload` extension is
authorised instead.

### An unspecified, optional Python preflight call

Rejected — the complete asynchronous, outbox-topology-consistent contract
above replaces it, chosen because it matches the only precedent this
codebase's ingestion-adjacent pipeline actually has, rather than
introducing a synchronous call style borrowed from a materially different
subsystem (retrieval/generation).

### A synchronous Laravel-to-Python preflight call

Considered, and rejected in favour of the asynchronous design: verified
that Python's only existing role in the ingestion-adjacent pipeline is as
an SQS-consuming worker, never a service Laravel calls synchronously
outside the retrieval/generation subsystem, which preflight is not part
of.

### Deriving the reserved permanent key from the attempt's own identity
### rather than content-addressing it

Rejected — content-addressing lets safe reuse fall out automatically from
whether bytes actually changed, with no special-case logic required.

### Rehashing the object immediately before commit as a sufficient
### immutability proof

Withdrawn — object storage sits outside the database transaction; a
rehash moments before commit does not prove nothing changed in between.
Conditional-create/write-once or an independently-provable version
identity is required instead; a backend able to prove neither cannot
support staging.

### Treating a generic storage-backend ETag as a SHA-256 checksum

Rejected — no verified proof exists that this repository's storage
contract guarantees an ETag is a content hash on every backend in use.

### A single, workspace-wide `SELECT`-then-`INSERT` duplicate check with
### no reservation primitive

Rejected — this admits a genuine concurrent duplicate in the gap between
the two statements. A dedicated, uniquely-constrained reservation row
closes it.

### Excluding ADR-0031's applicability-only clone from the checksum
### reservation/lock entirely

This was an earlier design in this ADR and is withdrawn: it correctly
recognised that a clone's matching checksum must never be *rejected* as a
duplicate, but incorrectly concluded from that, that the clone should
also skip *serialization* — leaving a real phantom-duplicate/phantom-
absence race between a concurrent import and clone against the same
bytes. The corrected design has both operations acquire the identical
lock and re-query before proceeding, while only import promotion applies
duplicate rejection to what it finds.

### A lease-based (rather than plain transaction-scoped) checksum lock

Considered, and rejected as unnecessary complexity: the lock is acquired
and released entirely within one already-short, already-bounded final
transaction on both the promotion and clone sides, never across the
out-of-transaction object-copying work either performs — ordinary
PostgreSQL transaction/rollback semantics already provide everything a
lease and its expiry/reclaim logic would otherwise need to provide.

### Letting the original attempt's authorization stand unchecked through
### to commit

Rejected — a role or membership change between attempt creation and final
commit must not silently let a now-unauthorised actor's work proceed;
live re-verification at commit, and an explicit adoption action for a
different authorised actor, are required instead.

## Consequences

### Positive

- Exactly one upload architecture exists in V1, with all three legacy
  browser-facing stages — including the previously-omitted
  `RequestDocumentIngestion` — accounted for in the cutover and drain.
- The complete relational-invariant tables give Codex an unambiguous
  implementation target for every entity this ADR introduces.
- The asynchronous preflight design matches this codebase's actual worker
  topology rather than inventing an inconsistent synchronous call.
- Matching is fully deterministic, provider-free, and safe under
  concurrency.
- Removing the unproven rehash fallback closes a real TOCTOU risk before
  implementation could introduce it.

### Negative

- The narrowly-scoped `ExpireLegacyDrainUpload` extension is real,
  if bounded, new surface on ADR-0007's technical lifecycle, requiring its
  own care to avoid becoming a general-purpose escape hatch.
- The asynchronous preflight design adds a second outbox event type and
  its own worker-consumption registration — genuine new infrastructure,
  not a thin wrapper.
- The dedicated checksum-reservation row and the live-authorization-at-
  commit check both add real write and lock surface to the final
  promotion transaction beyond what a simpler design would have needed.
- Backends unable to prove either write-once or version-identity semantics
  cannot support staging at all — a real deployment constraint, not merely
  a documentation note.

## Scope boundaries

This ADR does not define: the ingestion outbox/claim/lease protocol
itself; ADR-0031's governance actions or family-deletion flow; ADR-0032's
artefact/projection mechanics; ADR-0035's bulk-operation mechanics beyond
the primitives it consumes; ADR-0036's notification triggers; ADR-0037's
export; the exact numeric values for staging retention windows beyond the
seven-day default, drain-window duration, failure-ceiling counts, or
matching-score thresholds (bounded, tested, R25 implementation
measurement).

## Testing

Provider-free coverage: tenancy/concealment; exact-duplicate detection
including the tombstoned-warning-versus-blocking distinction and the
applicability-only-clone redirect; possible-match normalisation, ordering,
and no-automatic-selection; **the shared workspace/checksum
serialization primitive's complete concurrency test set** (two imports,
import-versus-clone in each order, two clones, rollback-after-acquisition,
crash/retry, a pre-existing reservation row's reuse, no cross-workspace
contention); every relational invariant table's constraints **including
the composite decision-snapshot/`PromotionAttempt` foreign key and the
`attempt_ordinal` uniqueness**; decision-snapshot canonicalisation and
digest determinism; idempotency conflict/independence cases **using the
non-nullable `actor_identity` column, with an explicit test proving two
concurrent requests from the same system actor with the same operation,
key, and digest correctly deduplicate, while different system actors do
not**; the complete
preflight contract in both directions **including `lease_generation`
round-tripping and rejection of a stale-generation, mismatched-token, or
replayed callback**; the complete state-machine transition table including
`FAILED`'s dual authority paths, cancellation quiescence, lease/finalizer
authority, and live-authorization-at-commit with adoption; source
ownership transfer and cleanup-versus-commit races; the immutable-object
proof and its backend-unsupported fail-closed path; **the complete legacy-
marker bootstrap sequence** (pre-existing rows, rows racing the cutover
transaction, idempotent restart) and the legacy cutover/drain sequence for
every pre-cutover state; and the projection lifecycle tests specified in
ADR-0033 for `document_family_activity_summary`, since this ADR's own
promotion commit is one of that projection's allowlisted producers.

Required Playwright journeys: one-file import; mixed multi-file import;
resuming an unfinished batch; a duplicate-then-corrected-retry cycle; a
`CONFLICT`-then-adoption-by-a-different-authorised-actor cycle; promoting,
approving/indexing, and reaching genuine searchable readiness (the same
terminal condition ADR-0033's onboarding journey asserts).

## Implementation and session allocation (R25)

- **R25-S01 — Domain, schema, and staging-privacy verification.** The
  complete relational-invariant tables above; the staging-disk acceptance
  verification.
- **R25-S02 — Preflight contract.** The asynchronous dispatch/callback
  schemas, HMAC authentication for the inbound direction, the staging-
  specific lease record, the bounded result vocabulary.
- **R25-S03 — Matching.** Exact-duplicate and possible-match resolution,
  the reservation-row concurrency primitive, the applicability-only-clone
  redirect.
- **R25-S04 — Promotion.** The complete state machine, live-authorization-
  at-commit and adoption, the immutable-object proof, ownership transfer
  and cleanup interlock.
- **R25-S05 — Legacy cutover and drain.** The three-stage gate, the
  `ExpireLegacyDrainUpload` extension, the drain reconciler.
- **R25-S06 — Import workflow and progress UI.** The seven-step visual
  flow; the honest coarse-progress model
  (`Promoted/queued → Processing → Indexed`, with finer sub-stage
  granularity named as an explicitly deferred future seam requiring
  ingestion-worker-protocol changes outside this ADR's scope).
- **R25-S07 — Tests and Playwright journeys.** The complete list above.

Not allocated here: ADR-0035's bulk-execution UI, ADR-0036's notification
UI, ADR-0037's export UI.
