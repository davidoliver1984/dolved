# ADR 0030: Define Document Family and Version Metadata

## Status

Accepted

## Date

2026-08-28

## Relationship to prior ADRs

### Extends ADR-0017, does not reopen it

ADR-0017 established `DocumentFamily`, linear version lineage, temporal
authority, and per-version applicability snapshots. That domain shape is
unchanged here. Nothing in this document touches `authority_start`, the
governance state machine, or applicability resolution.

### Extends ADR-0007, and introduces the checksum it did not

ADR-0007's three-layer separation and technical processing lifecycle are
unchanged. This ADR introduces `source_checksum_sha256` — verified absent
from `documents` today — because ADR-0031's clone compatibility proof and
ADR-0037's export manifest both require a real, verified content identity.
Because this repository already contains documents whose retained source
bytes may be unavailable, this ADR defines a **phased, honestly-stateful**
introduction rather than assuming every row can trivially receive one.

### Consumed by ADR-0031, ADR-0033, ADR-0034, ADR-0037

ADR-0031's clone compatibility proof and ADR-0037's source-inclusive export
both **require** a document's checksum state to be `verified` before
proceeding — a `pending` or `unavailable` document is not eligible for
either, exactly as reflected below. ADR-0033 consumes the category domain.
ADR-0034 requires this ADR's metadata as "required metadata" before
promotion.

### Does not touch ADR-0032

`DocumentExtractionArtifact`, its relational projection, and source
delivery remain ADR-0032's exclusive concern.

## Context

The Knowledge Library passes preceding this revision established the
metadata classification, and two prior corrections fixed the checksum's
non-existence and the audit table's inability to record family-scoped
events. **This revision applies Codex's second Tier-1 audit**, which found
three further gaps: the checksum introduction did not honestly account for
legacy documents whose source bytes may already be gone; the audit table's
new nullable `document_id` needed an explicit relational constraint, not
only a nullability change; and the 20-tag limit had no defined concurrency
protection, meaning two simultaneous requests could each observe 19 tags
and each add one, producing 21.

## Decision

### Metadata classification

Unchanged: family-level editable, version-level governed/editable,
immutable source metadata, derived technical data.

### Family-level editable metadata

Unchanged: title (backing `document_families.name`), description, category,
tags, owner (recorded identity), review-due date. Publisher/source label
and the source URL remain version-scoped — see "Source metadata" below.

### Title behaviour, Metadata semantics, Document owner, Review date

Unchanged from the prior revision: cleaned-title derivation and
inheritance; family rename as a separate audited action; the
recorded-identity/current-eligibility distinction for owner (eligibility
requiring both active membership **and** `disabled_at IS NULL`, checked
live, never cached); mandatory owner assignment at creation via a
`restrictOnDelete()` foreign key to `users.id`; owner backfill from the
lineage-root version's `created_by_user_id` regardless of current
eligibility, falling back to the workspace creator only when no
lineage-root identity resolves, itself an audited system-migration event;
review-due date as advisory-only, driving ADR-0036 reminders, never
authority.

### Source checksum — a phased, honestly-stateful introduction

**Final terminology, used consistently: `checksum_verification_status`,
with values `pending`, `verified`, `unavailable`.** This is not a cosmetic
naming choice — it is the mechanism that lets this ADR be honest about
legacy documents whose retained source bytes may already be deleted or
unreadable, rather than assuming every row can trivially receive a
checksum.

**Schema, added in one migration, safe from day one:**

- `source_checksum_sha256` — nullable, SHA-256 hex, **never given a global
  `NOT NULL` constraint** (a document honestly `unavailable` has no
  checksum to record, and forcing one would fabricate a value for content
  that provably cannot be verified).
- `checksum_verification_status` — non-nullable, defaulted to `pending`.
- A bounded failure reason/code, nullable, populated only when the status
  is `unavailable`.
- **A `CHECK` constraint added in the same migration, not deferred until
  backfill completes**: `status = 'verified'` requires
  `source_checksum_sha256 IS NOT NULL`; `status IN ('pending',
  'unavailable')` requires it to be `NULL`. This is a **shape** constraint
  (internal consistency of one row), not a **completeness** constraint
  (every row eventually verified) — it is safe to add immediately because
  every row starts `pending` with a null checksum, which trivially
  satisfies it, and every subsequent transition (below) maintains it.

**Phased sequence:**

1. Schema above, deployed with every existing row defaulted to `pending`.
2. **New uploads begin `pending`** at `UPLOADING`.
3. **Upload completion requires streamed source verification, and
   transitions to `verified` with the computed checksum in the same
   authoritative state change** — the `UPLOADING → UPLOADED` transition
   already gates on verified existence and size (ADR-0007); this ADR adds
   the streamed SHA-256 computation to that same gate, so a newly created
   document is never observably `UPLOADED` while still `pending`. A
   missing or unreadable object at this point fails the upload outright
   (it cannot yet be `unavailable` — that status is reserved for content
   that was once retained and later became unavailable, not for a upload
   that never genuinely completed).
4. **Legacy backfill** reads each pre-existing document's retained object
   through a bounded streamed read, computing the same SHA-256.
5. **Successful backfill transitions to `verified`** with the computed
   checksum.
6. **A missing, unreadable, or already-deleted legacy source becomes
   honestly `unavailable`**, with a bounded failure reason recorded — never
   a fabricated checksum, and never silently left `pending` forever once
   the cause is actually known.
7. **A backfill attempt that fails for a possibly-recoverable reason
   (transient read error, temporarily unavailable storage) remains
   `pending`, visible, and retryable** — only a confirmed, non-transient
   absence (the object genuinely does not exist) is recorded as
   `unavailable`.
8. **`verified` is checksum's terminal, immutable state** — once set,
   neither the status nor the checksum value is ever edited again, added
   to the same immutable-source-field guard as filename/size/media type.
9. **Existing conversations, citations, and tombstones remain fully valid
   regardless of a document's checksum state** — `EvidenceSnapshot`'s own
   durability (ADR-0023/0025) never depended on, and does not begin
   depending on, the source document's checksum.
10. **ADR-0031's clone and ADR-0037's source-inclusive export both require
    `status = 'verified'`** — a `pending` or `unavailable` document is
    simply ineligible for either, never treated as trivially compatible or
    incompatible by the absence of a value.

**Deployment safety, stated exactly**: the schema and its `CHECK`
constraint ship together, on day one, before any backfill runs — they
constrain shape, not completeness, so there is no unsafe window. The
backfill itself runs as an independent, resumable, bounded job afterward,
with no deploy depending on its completion; a document that has not yet
been backfilled is simply `pending` and correctly excluded from anything
requiring `verified`, exactly as intended, not a broken intermediate state.

**Audit/checksum lineage** records the algorithm identifier, the resulting
hex value or the `unavailable` reason code, and stable public identities —
never source bytes or the storage key.

### Source metadata

Unchanged from the prior revision: publisher/source label (bounded plain
text) and a separately validated, absolute-HTTPS-only source URL — no
userinfo, query, fragment, or control characters; not matching internal
storage-key formats; length-bounded; never fetched, resolved, or inspected;
rendered only with safe external-link handling; never entering retrieval,
logs, notifications, or provider calls. Both default from the immediate
predecessor at version creation.

**Immutable, version-scoped, never edited:** original source filename,
`source_checksum_sha256`/`checksum_verification_status` (above), file
size, media type, uploader, upload timestamp.

### Audit-event target model

**Corrected further, per the second Tier-1 audit**, which found the first
correction's nullable `document_id` needed an explicit relational
constraint, not only a nullability change, and needed exact actor
provenance rules.

**Relational structure:**

- `document_family_id` mandatory on every row.
- `document_id` nullable.
- `target_scope`, checked to exactly `family` or `version`.
- **`target_scope = 'family'` requires `document_id IS NULL`** — a `CHECK`
  constraint, not merely a documented convention.
- **`target_scope = 'version'` requires `document_id IS NOT NULL`** —
  likewise a `CHECK` constraint.
- **A version-scoped row's `(document_id, workspace_id,
  document_family_id)` triple is bound by a composite foreign key against
  the corresponding unique `Document` identity** — verified directly, this
  reuses the composite uniqueness ADR-0017 already establishes
  (`documents_id_workspace_family_unique`, on
  `(id, workspace_id, document_family_id)`) rather than inventing a new
  one. This is what actually prevents a version-scoped audit row from
  truthfully claiming a document belongs to a family it does not — a bare
  nullable `document_id` without this composite reference would allow
  exactly that inconsistency to be recorded.
- Composite `(workspace_id, document_family_id)` and
  `(workspace_id, document_id)` constraints remain as previously
  established.

**Actor provenance, defined exactly:**

- `actor_type`: `human` or `system`.
- **Human event**: `actor_user_id IS NOT NULL` and `system_actor_code IS
  NULL`.
- **System event**: `actor_user_id IS NULL` and `system_actor_code IS NOT
  NULL`, drawn from a **bounded vocabulary** — illustrative values:
  `owner_backfill_lineage_root`, `owner_backfill_workspace_creator_
  fallback`, `checksum_backfill`, `audit_target_scope_backfill` — never
  free text, and never a value capable of carrying sensitive content.
- **A database `CHECK` constraint enforces exactly one provenance form per
  row** — the XOR between the human and system shapes above, structurally,
  not only by application discipline.
- **Existing rows backfill as `human`** — every pre-existing row already
  has a non-nullable `actor_user_id` under the schema this ADR extends, so
  this backfill is value-preserving, not inferred.
- The checksum backfill, owner backfill, and audit-target-scope backfill
  (this ADR's own migration events) all use explicit, bounded system codes
  from the vocabulary above — never left as unattributed rows.
- **Only allowed metadata fields and stable public identities may appear
  in a recorded value, for either provenance form** — never source bytes,
  storage keys, extracted text, embeddings, or uncontrolled prose.

**Existing version-governance audit rows migrate without losing truthful
lineage**, exactly as previously established: `document_family_id`
backfilled from each row's existing document's family, `target_scope`
set to `version`.

### Categories

Unchanged: workspace-scoped `DocumentCategory` with normalized-name
uniqueness (NFC, trim, whitespace-collapse, case-fold), 100-character
display cap, active/archived status, archive-not-hard-delete safe-deletion
rule reused from `OrganisationalLocation`, no setup step required, never
consulted by retrieval/eligibility/authority/access.

### Tags

Unchanged domain shape (workspace-scoped identity, normalized uniqueness,
64-character cap, 20-tag-per-family cap, unique assignment per family), **now
with an explicit concurrency-safe enforcement procedure**, closing a real
race the prior revision left unaddressed: two simultaneous requests, each
observing 19 existing tags and each adding one distinct new tag, could
otherwise both succeed and leave a family with 21.

**The tag-assignment Action, in one transaction:**

1. Locks the `DocumentFamily` row (`lockForUpdate()`).
2. Re-reads current tag membership under that lock — never trusting a
   count observed before the lock was acquired.
3. Normalizes and deduplicates the requested final tag set (the same
   normalization rule used for uniqueness comparison).
4. Enforces the maximum of 20 against the re-read, locked count plus the
   normalized requested change — rejecting outright, never silently
   truncating, if the result would exceed it.
5. Performs every attach/detach in the same transaction as the lock and
   the count check.
6. Preserves the existing unique-normalized-tag-per-workspace and
   unique-tag-assignment-per-family constraints throughout.

Locking the family row for every tag change serializes concurrent tag
mutations against the same family — exactly the same family-level locking
discipline ADR-0031 independently establishes for governance mutations,
applied here to a metadata concern rather than a governance one.

## Alternatives considered

### A single free-text "safe source reference" field

Withdrawn — see the prior revision's reasoning, unchanged.

### Assuming every legacy document can receive a checksum immediately

Considered — it would have simplified the migration to a single backfill
pass with no honest failure state — and rejected: this repository already
contains, or could contain, documents whose source bytes have been deleted
or become unreadable independently of this ADR. Claiming every row
"eventually gets verified" would either block indefinitely on unrecoverable
rows or silently fabricate a value for content that cannot actually be
checked. The three-state model represents reality; a two-state
(`pending`/`verified`) model would not have had anywhere honest to put a
row that will never resolve.

### A global `NOT NULL` constraint on `source_checksum_sha256`

Rejected outright — see "Deployment safety" above; a truthfully
`unavailable` row has no checksum, by definition, and a `NOT NULL`
constraint would either be impossible to satisfy for such a row or force a
fabricated value into it.

### A bare nullable `document_id` on the audit table, with no composite
### relational constraint

This was the first correction's shape, and is completed here: nullability
alone does not prevent a version-scoped row from naming a document that
does not actually belong to the family the row also names. The composite
foreign key against `documents_id_workspace_family_unique` closes that gap
structurally.

### Enforcing the tag limit with an application-level check only, no row
### lock

Rejected — verified as a genuine race: without locking the family row for
the duration of the read-then-write, two concurrent requests can each pass
an application-level "is this under 20" check against a stale read and
both commit, producing more than 20. Locking the family row, already this
ADR's tenant-scoping primitive, closes it with no new lock type.

## Consequences

### Positive

- The checksum introduction is now honest about legacy content that may
  genuinely be unavailable, rather than assuming universal recoverability.
- The audit table's version-scoped rows are now structurally, not merely
  conventionally, prevented from recording an inconsistent
  document/family pairing.
- Actor provenance is now a checked database invariant, not a documented
  convention a future write path could silently violate.
- The tag limit is now genuinely race-free under concurrent requests.

### Negative

- The three-state checksum model is real additional complexity (a status
  column, a bounded reason vocabulary, and consumers that must check
  `verified` explicitly) compared to a naive "every document has a
  checksum" assumption.
- The composite foreign key on the audit table constrains any future
  schema change to `documents_id_workspace_family_unique` to also consider
  its effect on this dependent constraint.
- The actor-provenance `CHECK` constraint and bounded system-code
  vocabulary must be extended deliberately whenever a new automated
  migration or system action is introduced — an ongoing discipline, not a
  one-time cost.
- Family-row locking for every tag change serializes concurrent tag edits
  to the same family, a small, accepted throughput cost for correctness.

## Scope boundaries

Unchanged from the prior revision, plus: this ADR does not define the
exact bounded window after which a `pending` legacy document's backfill is
considered stuck and surfaced for operator attention — an R23
implementation detail, not fixed here.

## Implementation and session allocation (R23)

- **R23-S01a — Migration and domain model.** Categories, tags, family
  metadata columns; `source_checksum_sha256` +
  `checksum_verification_status` + bounded failure reason, with the
  `CHECK` constraint added in the same migration; the audit-table
  extension (`document_family_id` mandatory, `document_id` nullable with
  its composite FK, `target_scope` with its two `CHECK` constraints,
  `actor_type` with its XOR `CHECK` constraint and bounded system-code
  vocabulary).
- **R23-S01b — Policies, actions, API resources.** Authorised mutation
  actions; the streamed-checksum upload-completion gate; the lock-based
  tag-assignment Action.
- **R23-S01c — Backfill.** Title reinterpretation; streamed legacy
  checksum backfill (bounded, retryable, honestly `unavailable` where
  recoverable); owner backfill; audit-table `document_family_id`/
  `target_scope`/`actor_type` backfill — all recorded as bounded, named
  system-actor events.
- **R23-S01d — Tests.** Tenancy scoping; organisation-metadata-never-
  affects-retrieval; owner-authority-is-not-a-thing; owner-eligibility
  (membership-only, disabled-only, both); category/tag normalization and
  uniqueness; concurrent-tag-assignment race test (two simultaneous
  19-plus-1 additions, asserting exactly one succeeds or both succeed only
  if the combined result stays within 20); checksum state-machine tests
  covering `pending`→`verified`, `pending`→`unavailable`, and the
  `CHECK` constraint's rejection of an inconsistent row.

**Category-management UI is allocated to R24**, under a dedicated Library
settings surface.
