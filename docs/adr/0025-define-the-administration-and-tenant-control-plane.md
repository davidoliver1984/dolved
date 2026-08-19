# ADR 0025: Define the Administration and Tenant Control Plane

## Status

Accepted

## Date

2026-08-19

## Relationship to prior ADRs

### Consumes ADR-0006's tenancy model; resolves its deferred invitation domain; does not reopen its role set or enforcement layers

ADR-0006 fixes the role set (*"Initial membership roles are a fixed set:
`owner`, `admin`, `member`. Custom roles and a granular permission engine
are explicitly deferred."*), the single-active-owner invariant (*"Every
workspace has exactly one active owner membership at all times — a
workspace may never be left ownerless."*), the seven-layer defence-in-depth
enforcement stack, and the `404`-not-`403` concealment rule. This document
treats all of that as settled and unchanged. ADR-0006 also explicitly
deferred invitations (*"Invitations (pending, not-yet-joined state) are
deliberately out of scope here and will be modelled in a later session"*)
and named a three-layer audit model (business / Search-RAG / database)
without building the business-audit layer beyond a narrow document-
governance slice. This document is the deferred invitation session and
extends the business-audit layer to cover membership/ownership/invitation
events it did not yet cover. Verified directly against the schema: a
partial unique index, `workspace_memberships_one_owner_per_workspace`,
already enforces *at most one* owner row per workspace at the database
layer (`apps/api/database/migrations/2026_07_28_000002_create_workspace_memberships_table.php`)
— it does not, and cannot by itself, enforce *at least one*; that half of
"never ownerless" is application-transaction-enforced, exactly as
ADR-0006's own Consequences section already flags as unresolved cost
(*"The single-active-owner and role invariants require careful
transactional enforcement (e.g. transferring ownership, removing the last
admin)"*). This document is where that transactional enforcement is
finally designed.

### Narrows ADR-0007's document-deletion authorization statement for the administration surface specifically; does not reopen its lifecycle model

ADR-0007's exact words: *"a workspace member may delete a document that is
still uploading, queued, processing, or has failed"* (unqualified by
role). Verified: this was never implemented — no delete action, route, or
policy ability exists anywhere in the codebase today; `DocumentPolicy`
defines only `requestIngestion` and `completeUpload`, both open to any
active member regardless of role. This document is not correcting a
deployed permissiveness; it is making an explicit, first-instance product
decision, on a feature that has never shipped, to restrict the
*administrative* delete and retry commands to `OWNER`/`ADMIN`. **ADR-0025
supersedes ADR-0007 in part**: ADR-0007's technical lifecycle model — the
`UPLOADING → UPLOADED → QUEUED → PROCESSING → INDEXED` chain, the
`FAILED` branch, the `<any non-DELETED state> → DELETING → DELETED`
cancellation-barrier pattern, asynchronous multi-system cleanup, and the
row-retained-after-`DELETED` reasoning — is entirely unchanged and is
reused, not redesigned. Only the *authorization* clause — who may invoke
deletion — is narrowed. Ordinary product use is unaffected: any active
member retains ADR-0007's and ADR-0006's existing ability to use the
workspace and inspect documents they're already authorised to see; they
simply cannot invoke the new administrative retry/delete commands this
document defines.

### Consumes ADR-0008/ADR-0015/ADR-0016's outbox, worker-lease, and HMAC completion-report pattern; reuses it for retry and deletion, does not redesign it

Document retry (`FAILED → QUEUED`) does not exist in any form today —
verified by exhaustive grep of `apps/api/app` and `routes/api.php`. The
one existing action that transitions a Document to `QUEUED`,
`RequestDocumentIngestion`, explicitly rejects a `FAILED` document
(throws `DocumentIngestionException::invalidState()`). This document
defines retry as a new, owner/admin-only action that reuses the same
outbox mechanics `RequestDocumentIngestion` already uses (a fresh
`event_id`, a validated ingestion-request payload, one `OutboxEvent` row
written in the same transaction as the status change) rather than
inventing a parallel publication path — see "Retry semantics" below for
why this is a distinct action rather than an extended one. Document
deletion's Laravel/Python orchestration reuses a second, distinct piece
of this same family: the `ingestion.complete`/`ingestion.fail`
purpose-scoped, HMAC-authenticated request shape that lets Python report
a typed, durably-verified completion or failure back to Laravel — the
only existing precedent in this codebase for that direction of call. See
"Document deletion and `EvidenceSnapshot` retention" below for how a new,
analogous purpose family is defined for deletion without redesigning the
underlying claim/lease/HMAC mechanics.

### Consumes ADR-0017's version-lineage immutability; deletion respects it, does not reopen it

`Document.predecessor_document_id` is immutable and a version's successor
is derived by the later version pointing backward, never stored forward.
Hard-removing a Document row that another version's row points to as
predecessor would break that chain. ADR-0007 already commits to the row
being retained after `DELETED` rather than physically removed, which is
exactly what keeps this chain intact — this document does not change that
commitment, and treats it as the reason a hard `DELETE` of a `Document`
row must never be performed, only the soft `DELETING → DELETED` status
transition ADR-0007 already defines.

### Consumes ADR-0023's `EvidenceSnapshot` durability; extends its durability principle to document deletion, a scenario ADR-0023 itself did not name

ADR-0023 stores `cited_text_verbatim` in `EvidenceSnapshot` specifically
so a persisted answer's citations never depend on a live dependency
surviving — but the scenario ADR-0023 itself discusses is re-extraction
and garbage collection of superseded extraction rows, not source-document
deletion. **This document does not attribute document-deletion wording or
scope to ADR-0023 that it does not contain** — it extends the same
durability *principle* ADR-0023 established, consistently, to a scenario
ADR-0023 never named: an owner/admin deleting the source Document
entirely.

ADR-0007's *"derived artefacts"* deletion language and that extended
principle could read as being in tension. Verified directly against the
migration
(`apps/api/database/migrations/2026_08_16_000014_add_grounded_generation_foundation.php`,
lines 47-49): `evidence_snapshots.document_chunk_id`, `.document_id`, and
`.ingestion_event_claim_id` are each declared `->constrained()->restrictOnDelete()`
— **`RESTRICT`, not `CASCADE`**. This confirms one thing precisely and no
more: the database already refuses to hard-delete a `Document` row that
any `EvidenceSnapshot` references, so `EvidenceSnapshot` could never be
silently cascade-destroyed or orphaned by a `Document` hard-delete — and
since ADR-0007 never performs a hard `Document` row delete in the first
place (only the soft `DELETING → DELETED` transition), that specific risk
was already structurally closed before this document existed.

**That conclusion does not extend to `document_chunk_id`.** Verified
directly against the schema
(`apps/api/database/migrations/2026_08_05_000010_create_document_chunks_and_corpus_assignments_tables.php`):
`document_chunks.text` is a non-nullable, content-bearing column — the
actual chunk text — and `evidence_snapshots.document_chunk_id` is
likewise `restrictOnDelete()` against it. Ordinary document deletion is
committed, in this same document, to removing content-bearing chunk
artefacts. Those two commitments cannot both be satisfied by a literal
`DELETE` of a cited `DocumentChunk` row — the database would refuse it,
for exactly the same reason it refuses a `Document` hard-delete. This is
a genuine, load-bearing implementation prerequisite, not a wording
question, and it is resolved by a concrete persistence design in
"Document deletion and `EvidenceSnapshot` retention" below — not by this
relationship note alone.

### Consumes ADR-0024's conversation deletion and streaming/reconnect model; extends its permission-revocation question, does not redesign its persistence split

ADR-0024 already defines `Conversation`'s own `DELETING → DELETED`
lifecycle, tenant-scoped SSE, and connection-independent `GenerationRun`
execution via a Laravel `ShouldQueue` job (`ExecuteGenerationRun`, on a
dedicated `conversation` queue connection). Verified: this queue
mechanism is a plain Laravel database/Redis-backed job queue, entirely
separate from the outbox/SQS/Python-worker-lease pattern ingestion uses —
a style precedent for "how this codebase runs Laravel-only background
work," not a mechanism document retry can call into, since actual
ingestion processing happens in Python via the outbox path. This document
extends ADR-0024's connection/session model to answer a question ADR-0024
didn't need to ask: what happens to an open SSE connection when the
connected user's *membership* (not their generation run) is revoked mid-
stream. See "Permission revocation and in-flight work" below.

### Extends ADR-0012's allowlist-first observability posture; does not redefine it

Business-audit persistence exists today only for document-governance
actions (`DocumentGovernanceAuditEvent`) and ingestion actions
(`IngestionAuditEvent`) — both narrow, document-scoped tables. No
membership, role, ownership-transfer, or invitation audit trail exists
anywhere. This document extends the business-audit layer ADR-0006 named
to actually cover those events, using the same shape (actor, action,
before/after values, timestamp) `DocumentGovernanceAuditEvent` already
established, not a new audit architecture.

## Context

Phases 16 through 18 are complete: retrieval, grounded generation, and
conversation/streaming all work end to end. Phase 19 is the first phase
that builds a genuinely new *kind* of surface — not a step in the RAG
pipeline, but a tenant-facing control plane over state that pipeline
already produces and Laravel already owns authoritatively. Verified
directly against the running application: **no document listing endpoint,
no document retry, no document deletion, no invitation domain, no
ownership-transfer code, no membership-removal code, and no usage-
aggregation code exist anywhere in this codebase today.** Every one of
`tasks.json`'s three Phase 19 sessions — R19-S01 Document Administration,
R19-S02 Tenant and Membership Administration, R19-S03 Usage Visibility —
is genuinely greenfield business logic, though each has real,
already-built lower-level primitives to build on (the outbox pattern for
retry; Qdrant's already-implemented `delete()`/`remove_vector_space()` for
vector cleanup; proven SMTP transport via Mailpit for invitation email;
per-answer token/cost columns on `generated_answers` for generation usage;
the workspace `role` value already resolved and rendered server-side in
the existing frontend for capability gating).

`tasks.json` currently marks all three R19 sessions `session_mode:
implementation` with `requires_adr: false`. The product owner has
explicitly chosen to document Phase 19's durable decisions in an ADR
anyway, consistent with this project's architecture-first workflow for
any decision that establishes a long-lived boundary, selects among
meaningful alternatives, or would be expensive to reverse — administrative
authority, deletion semantics, and audit obligations are exactly that
category of decision, even though `tasks.json`'s session metadata predates
this choice.

**The governing principle**, stated once and applied throughout: *administration
observes authoritative domain state and requests authorised domain
transitions; it does not directly edit derived state.*

```text
Administration UI
    |
    +-- reads tenant-scoped authoritative state
    |
    +-- requests existing-shaped domain actions
    |     +-- retry failed ingestion
    |     +-- request document deletion
    |     +-- manage invitations/memberships
    |     +-- transfer ownership
    |
    +-- reads bounded usage projections
          +-- current resource totals
          +-- historical activity totals
          +-- provider-reported usage
          +-- explicitly labelled estimates
```

Phase 19 must not become a second ingestion engine, a second document-
state authority, a direct editor of chunks/embeddings/vectors/ingestion
claims, an operational-telemetry replacement for Phase 20, a bypass around
existing tenant authorization, or a place where the browser manufactures
or repairs authoritative state. Existing Python workers/adapters continue
their established work unchanged; Phase 19 moves no provider-native
processing into Laravel and no tenant authority into Python.

## What this ADR decides and does not decide

This ADR defines: the owner/admin/member capability matrix for
administrative actions and its explicit narrowing of ADR-0007's
deletion-authorization wording; ownership-transfer invariants and
transaction shape; voluntary-departure rules; permission-revocation
behaviour for already-open sessions and streams; the invitation domain
(persistence, authority rules, expiry, acceptance, and its explicit
separation from email delivery); the document-administration read model
and its filters; retry semantics reusing the existing outbox pattern;
document deletion semantics and their reconciliation with `EvidenceSnapshot`
retention; membership-removal content-ownership rules; the usage-
visibility metric taxonomy (current gauges versus historical intervals);
the distinction between provider-reported usage, calculated usage, and
estimated cost, and the pricing-lineage model; aggregation/freshness
policy; tenancy/security rules and required negative tests; the business-
audit boundary against Phase 20's future operational telemetry;
failure/concurrency behaviour; explicit deferrals; and the R19-S01/S02/S03
implementation allocation.

It does not decide: exact Laravel controller/action/migration class names
beyond what's structurally required to state the decision; exact frontend
component structure or visual design; exact SQL for aggregation queries;
calibrated numeric thresholds (page sizes, reauthorization intervals)
without operational evidence, beyond naming that a bound must exist; a
generation/LLM pricing table's exact rate values; custom roles or a
granular permission engine (explicitly deferred, per ADR-0006); billing,
invoicing, or payment collection; and Phase 20's operational-telemetry
architecture. It does not redecide anything ADR-0006, ADR-0007, ADR-0008,
ADR-0012, ADR-0015, ADR-0016, ADR-0017, ADR-0023, or ADR-0024 already
settled.

## Decision

### Roles and administrative authority

The existing fixed role set — `OWNER`, `ADMIN`, `MEMBER`
(`app/Enums/WorkspaceRole.php`) — is retained unchanged. No custom
permissions, no new role, and no `USER` alias are introduced for this
phase; the frontend may render friendlier copy for an ordinary member, but
persisted domain terminology stays exactly this enum.

**Capability matrix:**

| Capability | Owner | Admin | Member |
|---|:---:|:---:|:---:|
| List and inspect workspace documents | Yes | Yes | Yes |
| Retry failed document ingestion | Yes | Yes | No |
| Delete workspace documents | Yes | Yes | No |
| View membership administration | Yes | Yes | No |
| Invite an ordinary member | Yes | Yes | No |
| Revoke an ordinary-member invitation | Yes | Yes | No |
| Remove an ordinary member | Yes | Yes | No |
| Promote a member to admin | Yes | No | No |
| Invite someone directly as admin | Yes | No | No |
| Demote or remove an admin | Yes | No | No |
| Transfer ownership | Yes | No | No |
| View workspace usage and cost estimates | Yes | Yes | No |

An admin must not alter the owner, promote another member to admin,
invite someone as admin, demote or remove another admin, transfer
ownership, or construct a second route to administrative elevation (for
example, removing an admin and re-inviting the same email as admin —
invitation issuance is itself owner-only for the `ADMIN` role, closing
that path structurally, not by convention). Only the owner may create or
remove admins.

**Why "any member" is rejected for retry/delete (Alternative 1),
evaluated rather than merely asserted:** upload and ingestion-request
remain open to any active member — that pattern is correct and unchanged,
because uploading is *adding* to the shared knowledge base, a
collaborative act with limited individual blast radius. Retry and delete
are different in kind: delete removes content the *whole team* may depend
on, not just what the acting member contributed, and is the one command
in this document that is deliberately hard to fully undo (see "Document
deletion" below); retry re-consumes real provider cost and queue capacity
on a re-triggerable basis. Restricting both to owner/admin is the smallest
rule that keeps ordinary collaborative use (upload, ask questions,
inspect documents already authorised) exactly as unrestricted as it is
today, while keeping the two genuinely consequential, hard-to-reverse
administrative actions behind the same authority tier this document
already requires for membership and ownership changes.

### Ownership transfer

The workspace must never become ownerless. Transfer is owner-only,
atomic, restricted to an existing active member of the same workspace,
audited (actor, target, old role, new role), protected against
stale/concurrent requests, and structurally incapable of producing two
owners or zero owners.

**Result:** the selected active member becomes `OWNER`; the former owner
becomes `ADMIN` — never demoted to an ordinary member automatically
(Alternative 5, rejected: a former owner who just handed over
responsibility retains administrative capability rather than being
silently downgraded to no capability at all, which would be a surprising,
punitive side effect of an otherwise routine operation with no
demonstrated need for it).

**Transaction shape**, stated at the invariant level, not as a migration:
both the outgoing owner's and incoming owner's `WorkspaceMembership` rows
are locked for update within one database transaction. Verified directly
against the migration
(`apps/api/database/migrations/2026_07_28_000002_create_workspace_memberships_table.php`,
lines 35-39): `workspace_memberships_one_owner_per_workspace` is a plain
`CREATE UNIQUE INDEX ... WHERE role = 'owner'`, issued via raw
`DB::statement`, not a `DEFERRABLE` constraint — Postgres enforces it at
the end of each statement, not at transaction commit. This has a direct
consequence for statement *order*: the transaction must update the
outgoing owner's row to `ADMIN` **before** updating the incoming member's
row to `OWNER`, never the reverse and never as a single set-based
statement that would momentarily hold two `owner` rows — promoting the
incoming member first, while the outgoing owner row still reads `owner`,
would violate the index mid-transaction and abort. Demote-then-promote,
in that order, is therefore not a stylistic preference but the specific
sequencing this non-deferrable index requires.

That index guarantees *at most one* owner row can ever exist, a real,
already-existing structural backstop against the two-owners failure mode
even if application logic were ever buggy — but, as a partial unique
index restricting duplicates rather than absence, it does nothing to
prevent the *zero*-owners failure mode. **The "never ownerless" half of
the invariant is not a free consequence of transaction atomicity alone —
correcting the first draft's overstatement here.** It holds only because
every ownership-changing code path is required to go through this same
guarded transfer action: an owner is blocked from leaving voluntarily
without transferring first (see "Voluntary departure" below), no role-
change or removal action is permitted to touch the `owner` role directly,
and a concurrent membership-removal request against either party is
serialized behind the same row lock and re-validated inside it. Atomicity
guarantees the demote-and-promote pair is all-or-nothing *once a transfer
begins*; it is the combination of that atomicity with these
application-level preconditions — restricting every path that could
otherwise touch the `owner` assignment to this one guarded action — that
together keep the workspace from ever observing zero owners. A stale or
concurrent transfer request (the target member no longer active, or a
second transfer already in flight) is detected by the row lock and the
precondition check re-run inside it, and fails as a typed, safe conflict
(see "Failure and concurrency behaviour" below), never as a silent no-op
or a partial state.

**Voluntary departure**, a genuine open question this document settles
with the smallest safe V1 rule, flagged explicitly for review since no
existing ADR or code addresses it: an owner cannot leave a workspace
without transferring ownership first — structurally, "leaving" is
self-removal of one's own membership, and a sole owner leaving would
violate "never ownerless," so it is blocked with a clear message directing
the user to transfer ownership first. Admins and ordinary members **may**
leave voluntarily at any time — the smallest safe rule, since nothing
about their departure risks an invariant violation, and forcing a member
to be administratively removed rather than simply leaving would be an
unnecessary restriction with no protective purpose. Voluntary departure
and administrative removal end at the same underlying state (membership
row removed, content ownership rules below apply identically) but differ
in *who initiates* it: leaving requires no owner/admin authorization
(you may always remove yourself, except a sole owner); removing someone
else always requires owner/admin authority per the capability matrix.

### Permission revocation and in-flight work

Revocation must take effect consistently, but this document does not
overclaim what the existing transport can enforce instantly. The
recommended, honestly-scoped distinction:

- **New authenticated workspace requests** — including new SSE
  connections and reconnects — are checked against current authoritative
  membership state on every request, unchanged from ADR-0006's existing
  enforcement stack. Revocation is therefore immediate for anything not
  already an open connection: the removed user cannot open a new request,
  cannot reconnect, and cannot resume via `Last-Event-ID` replay.
- **An already-open SSE connection is a genuine gap this document names
  rather than hides.** ADR-0024's streaming architecture does not give
  Laravel an instant, push-based way to sever one specific open connection
  the moment a membership row changes elsewhere. This document requires a
  **bounded reauthorization interval**: the SSE delivery loop re-checks
  the connected user's current membership at least once per bounded
  interval (an upper bound stated architecturally here; the exact value is
  R19-S02 implementation detail, calibrated against ADR-0024's existing
  delivery-loop cadence rather than invented fresh) and terminates the
  connection with an explicit, safe terminal event the moment membership
  no longer holds — never an indefinite continuation. This is a strictly
  weaker guarantee than instant revocation, and this document states that
  plainly rather than claiming otherwise.
- **Workspace-owned durable processing already in flight does not need to
  be corrupted or rolled back solely because its initiating user was
  removed.** A `GenerationRun` a removed user started may complete
  normally for the workspace — the content belongs to the workspace, not
  personally to the user who triggered it (see "Membership removal and
  content ownership" below) — but the removed user specifically can no
  longer read the result, because reading it requires a request or stream
  connection, both already gated by current membership.
- Removing a user never deletes workspace-owned documents, conversations,
  or audit history they created.

Role and membership authority is always checked from current authoritative
membership state, never trusted from a stale browser claim, token payload,
or cached value beyond the bounded interval named above.

### Invitation domain

Verified: no invitation persistence, delivery, or UI exists in any form
today — this is genuinely greenfield, exactly as ADR-0006 anticipated.
Verified separately: real SMTP transport is already wired and proven
(Mailpit locally, `MAIL_MAILER=smtp` in `compose.yaml`, actively used
today for Fortify's own verification/password-reset notifications) — but
no custom `Mailable`/`Notification` class exists anywhere in this
codebase; the app relies entirely on framework-default notification
classes for its existing mail. An invitation email is therefore small,
additive work reusing a proven transport, not a new delivery architecture
— stated honestly in both directions, since claiming either "email
already works for invitations" or "email infrastructure must be built
from scratch" would misrepresent what actually exists.

**Durable V1 invitation domain**, covering exactly what a token-based
invite needs and nothing more:

```text
Invitation
  workspace_id
  invited_email          (normalized — case-folded, trimmed)
  intended_role           MEMBER | ADMIN            (never OWNER)
  invited_by_user_id
  token_digest            (a digest of the token; the raw token is never
                           durably stored, mirroring the same discipline
                           this platform already applies to HMAC secrets
                           and processing-lease tokens elsewhere)
  status                   PENDING | ACCEPTED | REVOKED | EXPIRED
  created_at
  expires_at
  accepted_at              (nullable)
  revoked_at               (nullable)
  accepted_by_user_id      (nullable, set only on acceptance)
```

**Authority rules**, matching the capability matrix exactly: the owner may
invite as `MEMBER` or `ADMIN`; an admin may invite only as `MEMBER`;
invitations can never grant `OWNER` — ownership is acquired only through
the explicit transfer described above; an admin cannot use invitation
issuance as a second route to creating another admin, since issuing an
`ADMIN`-role invitation is itself owner-only, closing that path
structurally.

**Acceptance rule, verified implementable rather than assumed:** the
authenticated user's *verified* email must match the invitation's
normalized email. This is implementable today — `User implements
MustVerifyEmail`, and Fortify's verification flow is live and wired
(`FortifyServiceProvider`, real SMTP delivery confirmed) — so this rule
does not depend on a capability this platform lacks.

**Expiry:** configurable, seven-day default — a product default, not an
architecture invariant, the same framing ADR-0024 already applies to its
own configurable history-window and retention parameters; R19-S02 may
tune the number without this document being reopened.

**Invitation validity is explicitly separate from delivery.** Creating an
`Invitation` row is not the same as proving an email was sent. In the
absence of confirmed delivery (or where an operator simply hasn't
configured outbound mail for a given deployment), the platform must not
claim "email sent" when it cannot confirm that. The honest V1 seam: the
invitation is valid and acceptable via its secure link the moment it's
created, regardless of whether email delivery succeeds, is retried, or is
never attempted; sending the email is an additive, best-effort adapter
around that already-valid invitation, not a precondition for its
validity. This also means: an invitation's UI copy must never assert
email delivery succeeded unless a delivery mechanism actually confirms
it, and this document does not require one.

**Raw-token recoverability, corrected from the first draft.** Because
only `token_digest` is durably stored — the raw token is never persisted
reversibly, by design, mirroring the discipline already applied to HMAC
secrets and processing-lease tokens elsewhere in this platform — the
first draft's claim that the admin UI "must always be able to present the
secure invitation link" cannot hold indefinitely: nothing durable would
let the platform reconstruct that link after the creation response has
been rendered and discarded. The corrected rule: the raw token/link is
returned exactly once, in the creation or reissue response, and may be
forwarded through the delivery adapter during that same operation; after
that response, the raw link cannot be recovered by any code path,
including the admin UI itself. If an administrator needs the link again —
because it was lost, the recipient says they never received it, or
delivery failed silently — they reissue the invitation, which atomically
revokes the previous token (see "Concurrency" below) and returns a fresh
one. The UI must never claim it can redisplay an old raw token; it can
only offer to reissue.

**Effective expiry, defined precisely.** A persisted `PENDING` invitation
whose `expires_at` has passed must not remain acceptable merely because no
scheduled process has yet flipped its `status` to `EXPIRED` — expiry is
checked as a computed condition (`status = PENDING AND expires_at <=
now()`), evaluated fresh at the moment of acceptance, never trusted from a
possibly-stale `status` value alone. A background process may later
materialise the durable `EXPIRED` state for bookkeeping (so expired
invitations are visible in an admin's PENDING list without querying every
row's `expires_at` client-side), and an acceptance attempt against an
already-past-`expires_at` row may itself trigger that materialisation as
a side effect — but neither is required for the *safety* property, which
holds purely from the effective check at acceptance time. This also
resolves the constraint interaction the first draft left open: the
partial unique index enforcing at most one active invitation is scoped to
`WHERE status = 'pending'`, not to `expires_at`, because Postgres partial
indexes cannot key on a volatile expression like `now()`. An
"effectively expired but not yet materialised" row therefore still
occupies that uniqueness slot. Reissue handles this without special-
casing: reissuing to an email that already has a `PENDING` row — whether
that row is still genuinely valid, already past `expires_at`, or
otherwise stale — always closes the existing row transactionally first
(materialising it to `EXPIRED` if `expires_at` has passed, `REVOKED`
otherwise) before inserting the fresh `PENDING` row in the same
transaction, so the partial unique index is never violated and an
expired-but-not-yet-materialised row never blocks a legitimate reissue.

**Concurrency:** at most one active (`PENDING`) invitation exists per
`(workspace_id, invited_email)` pair, enforced by a partial unique index —
the same `WHERE`-scoped unique-index pattern this platform already uses
for "at most one owner per workspace." Reissuing an invitation to the same
email revokes the still-pending prior invitation (its `status` becomes
`REVOKED`, its token digest can never be matched again) and creates a
fresh row with a fresh token and expiry — never leaving two simultaneously
valid tokens for the same invitee.

**Safe public errors:** accepting, revoking, or resending an invitation
must not disclose whether an account already exists for that email, or
leak internal workspace details to an unauthenticated party holding a
guessed or expired token — the same 404-not-403-style non-disclosure
discipline ADR-0006 already requires everywhere else, applied to this
domain's own failure modes (expired, already-accepted, revoked, wrong
account) via safe, generic messaging rather than precise internal-state
disclosure.

### Document administration read model

All active workspace members may list and inspect documents in their
current workspace, unchanged — this is a read capability, not an
administrative one, and the capability matrix already reflects that.

The read surface exposes authoritative, useful state, deterministically
projected from fields already confirmed to exist — never a synthetic
status that could disagree with the real lifecycle: document identity;
immutable source metadata safe for display (`source_filename`,
`media_type`, `size_bytes` — all confirmed real, immutable columns);
lifecycle `status`; governance/version status where applicable
(`governance_status`, confirmed real); processing timestamps; the latest
relevant ingestion attempt/claim; sanitised `failure_category` and
`failure_message` (both confirmed real, durably persisted, DB-constrained
to be present whenever `status = FAILED`); creator identity where
authorised (`created_by_user_id`, confirmed real); and whether retry or
deletion is currently available (a deterministic function of `status` —
retry only for `FAILED`, delete for any non-`DELETING`/`DELETED` state,
per "Retry semantics" and "Document deletion" below).

**Extraction warnings, now verified rather than deferred as an open
question.** `failure_category`/`failure_message` are confirmed durably
persisted and safe to expose, unchanged from the first draft. Extraction
and chunking already produce a typed warning concept in Python
(`ExtractionWarning`/`ChunkingWarning`, each a `code` + `message` +
optional locator — e.g. `images_not_extracted`, `oversized_element_split`)
with template-based, developer-oriented-but-non-sensitive message text —
no raw source text or vendor/provider names are interpolated into them.
The wire shape already exists on both sides of the ingestion-worker HMAC
boundary: `IngestionOperationRequest` already validates an incoming
`warnings[].code`/`warnings[].message` array on the `ingestion.complete`
and `ingestion.publication.authorise` purposes. **But today the data
never actually flows**: Python's orchestrator hardcodes the transported
`warnings` field to an empty array rather than populating it from the
real `ExtractionWarning`/`ChunkingWarning` values it already computed,
and even where Laravel validates an incoming `warnings` array, the
`AuthoriseIngestionPublication` evidence-persistence whitelist silently
drops the key before it reaches `publication_evidence` — nothing is
durably stored today.

This document's decision: extraction warnings **are included** in the
V1 read model, using the wire shape that already exists rather than
inventing a new one. This requires two small, precisely-scoped fixes,
not new architecture: Python's ingestion orchestrator must populate the
`warnings` field it already sends from the `ExtractionWarning`/
`ChunkingWarning` values it already computes instead of hardcoding `[]`;
Laravel's evidence-persistence whitelist must stop dropping the
`warnings` key and instead persist it. Warning codes and messages remain
developer-authored, non-interpolated template strings — the read model
must not accept or display free-form provider/extraction prose beyond
this typed set. This is genuinely small additive work for R19-S01 — the
scaffolding on both sides already exists and is already validated — not
an unresolved blocker.

**Filters**, chosen against columns confirmed to exist: source filename;
document status; governance/family status where applicable; creator;
date range; failure category. All filtering and search is server-side,
tenant-scoped, and bounded; pagination is deterministic and page sizes are
bounded — the same discipline this platform already applies to retrieval
candidate bounds and delivery-event retention windows elsewhere.

Source metadata is inspectable but not editable in this phase — document
administration is a read/retry/delete surface in V1, not a source-
metadata editor, and does not bypass ADR-0017's governance commands
(approve/withdraw/reschedule), which remain their own, separate authority
path, unchanged.

### Retry semantics

Only an eligible `FAILED` document may be retried through this
administration action — confirmed: `FAILED` is reachable only from
`PROCESSING` via the existing `FailIngestionAttempt` action, and no
existing code path moves a document out of `FAILED` today. Retry is an
explicit owner/admin command.

**A new, distinct action — not an extension of `RequestDocumentIngestion`
— deliberately, because the two operations have different authorization
tiers.** `RequestDocumentIngestion` (any active member, `UPLOADED →
QUEUED`) and the new retry action (owner/admin only, `FAILED → QUEUED`)
reuse the same low-level mechanics — a fresh `event_id`, a validated
`DocumentIngestionRequestedPayload`, one `OutboxEvent` row written inside
the same transaction as the status change, under the same row-locked,
recheck-status-inside-the-lock pattern `RequestDocumentIngestion` already
uses — but are kept as two separate callable actions specifically so their
authorization never has to be parameterised or conditionally branched
inside one action. This is the smallest design that reuses the proven
outbox mechanism without building a parallel publication pipeline
(Alternative outcome: reusing the pattern, not the callable, given the
brief's own instruction not to build a parallel retry pipeline "merely
because Phase 19 introduces an admin button" — the pipeline is the same;
only the entry point and its authorization differ).

The same durable `Document` identity is retained; a fresh ingestion
attempt/event/claim and fresh lineage are created, exactly as the existing
`IngestionEventClaim` model already requires for any new attempt.
Previous failure/audit lineage is not overwritten into invisibility:
`Document.failure_category`/`.failure_message` reflect the *current*
attempt's outcome (cleared or replaced only once the new attempt itself
completes or fails), while the historical `IngestionAuditEvent` trail for
the prior attempt remains, append-only, unchanged.

**Idempotency, corrected from the first draft's overstatement.** A row
lock plus an inside-the-lock status recheck prevents two concurrent retry
requests from racing each other into a corrupt double-queue — that is
concurrency *safety*. It is not request *idempotency*: once the first
retry has already moved the document `FAILED → QUEUED`, a repeated retry
request (a duplicate click, a transport-level HTTP retry of the same
command) arrives to find `status === QUEUED`, and the row-lock/recheck
pattern alone gives no way to distinguish "this is a replay of the
command that already succeeded" from "this is a new, illegitimate retry
attempt against a document that is no longer `FAILED`" — both would
currently surface as the same typed conflict, which is wrong for the
first case. The corrected mechanism: the retry command carries a
client-supplied idempotency key, generated fresh by the browser each time
the user issues a new retry intent. Laravel persists that key together
with the `event_id`/attempt it produced, in a durable, uniquely-indexed
record scoped to `(document_id, idempotency_key)`. Repeating the same
key returns the original accepted operation's outcome unchanged — no
second `IngestionEventClaim`, no second `OutboxEvent` — even if the
document's status has since moved on. A *different* key submitted against
a document that is no longer `FAILED` is correctly rejected as a typed
state conflict, exactly as the first draft described. Concurrent
submissions carrying the same key are collapsed safely by the same unique
index that backs the ledger — a duplicate insert fails the uniqueness
check rather than creating a second attempt, and the losing request reads
back the winning attempt's outcome. Only one new
`IngestionEventClaim`/ingestion event is ever created per distinct
(document, key) pair.

**Deletion does not use this pattern — stated once, precisely, to remove
the contradiction the second revision left between this section and
"Document deletion" below.** Retry needs a client idempotency key because
the *same* document can legitimately fail again later and require a
distinct new retry attempt — the key is what lets Laravel tell "a replay
of the retry I already accepted" apart from "a new, later retry after a
new failure." Deletion has no equivalent scenario: it is a terminal
intent, not a repeatable one, so it uses one durable
`DocumentDeletionOperation` per document instead. A repeated delete
request returns that existing operation's status rather than creating a
second one; re-driving a stuck cleanup creates a new cleanup *attempt*
beneath the same deletion operation, not a second, competing deletion
intent; concurrency is protected by the document row lock plus the
one-open-operation-per-document constraint, not by a client-supplied key.
See "Document deletion and `EvidenceSnapshot` retention" below for that
model in full.

No unbounded automatic retry loop is introduced — retry remains an
explicit, human-triggered, single-shot command per idempotency key,
reaffirming ADR-0007's own existing *"automatic, uncontrolled retry loops
are rejected regardless of layer"* rather than reopening it. Retry never
directly edits chunks, vectors, claims, or status fields from the
controller/browser — it only requests the same domain transition
`RequestDocumentIngestion` already models, through the same authoritative
action layer. Because retry's precondition is `status === FAILED`,
checked and re-checked inside a row lock, it structurally cannot ever fire
against a `DELETING` or `DELETED` document — no additional guard is needed
beyond the existing precondition.

### Document deletion and `EvidenceSnapshot` retention

*"Delete document"* means removing the document from the active workspace
knowledge base and preventing any future retrieval or generation use of
it — not erasing every trace it ever existed.

Deletion is owner/admin only, an explicit command, and enters the
established asynchronous `DELETING → DELETED` lifecycle ADR-0007 already
defines — this document does not redesign that state machine, only adds
the authorization gate and the concrete cleanup obligations it triggers.

#### Chunk tombstones, withdrawn — `EvidenceSnapshot` is made independent of disposable chunks instead

The second revision chose to retain every `DocumentChunk` row and mutate
its content into a sanitised tombstone. **That decision is withdrawn.**
On inspection it does not hold together: it requires weakening
`DocumentChunk`'s unconditional immutability guard and the non-blank-text
`CHECK` constraint; it retains every deleted document's chunk rows and
corpus-assignment pivot rows indefinitely; and — the decisive problem —
it leaves `content_digest` attached to text that no longer matches it and
`token_count` attached to sentinel text it no longer describes, giving a
canonical `DocumentChunk` two incompatible meanings (immutable source
snapshot before deletion, mutable tombstone after). "The foreign key can
keep resolving" does not justify that incoherence.

**Corrected design: make `EvidenceSnapshot` genuinely independent of the
chunk surviving — the purpose it was already built for — and hard-delete
disposable `DocumentChunk` rows on document deletion, leaving canonical
chunk immutability completely untouched.**

- `Document` remains the existing minimal lifecycle/version tombstone —
  unchanged, not re-decided here.
- `IngestionEventClaim` is retained unchanged. Its durable retention is
  explicitly intended and safe: verified, every column is attempt/
  lineage/orchestration metadata (event and correlation identifiers,
  lease state, manifest **digests** — hashes, not content — status, and
  failure metadata) with no content-bearing column at all, so retaining
  it raises no content-removal obligation, exactly like `Document`.
  `evidence_snapshots.ingestion_event_claim_id` stays a live, unchanged
  `restrictOnDelete()` foreign key.
- `EvidenceSnapshot`'s own `cited_text_verbatim`, `content_digest`, and
  `source_provenance` remain exactly as ADR-0023 defined them — this was
  already the citation's authoritative content, independent of any live
  chunk, and nothing here changes that.
- **New, immutable, snapshot-owned lineage fields are added to
  `EvidenceSnapshot`**, captured at snapshot-creation time from the still-
  live chunk, so citation inspection never needs the chunk row to exist:
  `source_chunk_public_id` (the `DocumentChunk.public_id` cited),
  `source_chunk_ordinal` (its `ordinal` at citation time), and
  `source_ingestion_event_id` (the owning claim's `event_id`, duplicated
  onto the snapshot even though `ingestion_event_claim_id` also remains a
  live FK — redundant by design, so chunk-position and attempt identity
  stay directly inspectable without a join, and stay correct even if a
  future decision ever changed claim-retention policy). These are scalar,
  immutable, written once at creation — not another opaque JSON blob,
  consistent with the normalised-over-JSON lesson already applied
  elsewhere in this document.
- `EvidenceSnapshot.document_chunk_id` becomes **nullable**, with its
  foreign-key action changed from `restrictOnDelete()` to `nullOnDelete()`
  — the only foreign-key behaviour change this document makes. While the
  chunk row exists, the FK still resolves and is useful for direct joins;
  once the chunk is hard-deleted, it becomes `NULL` automatically, and the
  three new scalar fields above are what citation inspection actually
  relies on from that point forward — never the live FK.
- `EvidenceSnapshot.document_id` stays a live, unchanged `restrictOnDelete()`
  foreign key to the retained `Document` tombstone.
- Corpus-generation pivot rows (`workspace_corpus_generation_chunks`) for
  the deleted document's chunks are explicitly removed, and the
  content-bearing `DocumentChunk` rows themselves are physically deleted
  — in that order, since the pivot's own `document_chunk_id` foreign key
  is `restrictOnDelete()` against `document_chunks` and would otherwise
  block the chunk deletion.
- `DocumentChunk`'s unconditional immutability guard and the
  `document_chunks_text_not_blank` `CHECK` constraint are left **entirely
  untouched** — a canonical chunk that exists is exactly as immutable as
  it always was; a chunk belonging to a deleted document simply no longer
  exists as a row. No dual meaning is introduced.

**Historical citation inspection uses only `EvidenceSnapshot`-owned data —
never a live `DocumentChunk` lookup — by design, not by accident:**
`cited_text_verbatim`/`content_digest` for the citation's content, and
`source_chunk_public_id`/`source_chunk_ordinal`/`source_ingestion_event_id`
for its structural lineage, are all snapshot-owned and immutable from the
moment the snapshot is created, regardless of whether the source chunk
row still exists at read time.

#### Existing-data migration and backfill ordering

Because `EvidenceSnapshot` rows may already exist (created against live
chunks under the prior schema) before this design's schema change ships,
the migration sequence matters and is stated here at the architectural
level:

1. Add the three new lineage columns to `evidence_snapshots` as
   **nullable** (they cannot be `NOT NULL` yet, since existing rows don't
   have them).
2. **Backfill** every existing `EvidenceSnapshot` row's new columns from
   its still-live `document_chunk_id`/`ingestion_event_claim_id`
   foreign keys — at this point in the sequence, no chunk has been
   hard-deleted yet under the new regime, so every existing snapshot's
   live FKs still resolve and the backfill is a straightforward join, not
   a data-recovery problem.
3. Once backfill is verified complete, the three new columns may be
   tightened to `NOT NULL` (optional hardening, not required for
   correctness, since the deletion action can also just guarantee it
   populates them going forward).
4. Only after steps 1-3 are deployed does the foreign-key behaviour
   change land: `document_chunk_id` becomes nullable with `nullOnDelete()`
   replacing `restrictOnDelete()`.
5. Only after step 4 is deployed does the document-deletion action that
   performs hard `DocumentChunk` deletes go live.

This is a standard expand → backfill → contract sequence; skipping the
ordering (for instance, shipping hard chunk deletion before the backfill
completes) would strand pre-existing snapshots without the lineage this
design depends on for citation display after their source chunk is later
deleted.

#### Deletion orchestration: the Laravel/Python boundary

Resolved by extending the same shape this codebase already uses for
Python-initiated completion reporting — the `ingestion.complete`/
`ingestion.fail` purpose-scoped, HMAC-authenticated request family — the
only existing precedent in this codebase for Python calling back into
Laravel with a typed, durably-verified completion report (the rc1
retrieval/generation protocol is exclusively Laravel-initiated
synchronous request/response and has no equivalent callback shape; it is
not reused here).

**A new durable `DocumentDeletionOperation` record** (name illustrative,
not mandated), created by Laravel in the same transaction as the
`Document`'s `DELETING` transition, carries at creation: an
`event_id`/operation identity; `document_id`/`workspace_id` scope; the
requesting actor and a correlation id; **a snapshot of the active-attempt
identities to wait on** — the `IngestionEventClaim` ids that were
non-terminal (`open`/`sealed`/`publication_authorised`) at the instant
`DELETING` was entered, captured atomically with the transition itself;
lease/claim fields mirroring `IngestionEventClaim`'s existing claim/
renew/lease-expiry shape (reusing a proven pattern, not inventing a new
one); a typed cleanup-evidence field once Python reports it; typed
failure fields; and `completed_at`.

**The operation does *not* carry a finalised vector-scope set at
creation — corrected timing, removing a real tension the prior revision
left unresolved.** The prior revision described the scope set as
something "enumerated" and included on the operation from the moment it
is created, while separately requiring enumeration to happen only *after*
quiescence — those two statements cannot both be true. The corrected
sequence: only the active-attempt snapshot above is captured at creation;
quiescence is established against that snapshot (see "Ingestion
quiescence" below); **only once quiescence holds does Laravel enumerate
and durably record the final, deduplicated vector-scope set** (the union
described in "Vector scope enumeration" below, which by then safely
includes every attempt's generation lineage, since no attempt can still
be writing); cleanup is dispatched against that now-final, durably
recorded set. Laravel publishes a new outbox event type (e.g.
`document.deletion.requested`, versioned exactly like `document.
ingestion.requested`) through the existing outbox/SQS publication
pipeline unchanged, once quiescence and the final scope set are both
established.

**Idempotency and concurrency for deletion, stated in full here (the
single consistent rule; see also the correction in "Retry semantics"
above):** only one `DocumentDeletionOperation` may be open per document
at a time, enforced by a partial unique index — the same pattern ADR-0024
already uses for single-active-run-per-conversation. A repeated delete
request against a document that already has an open operation observes
that operation and returns its current status rather than creating a
second one; this is a structural, document-lock-backed no-op, not a
client-idempotency-key mechanism. Re-driving a stuck operation (stuck
waiting for quiescence, or stuck mid vector-cleanup) is requesting the
same operation's cleanup be reclaimed and retried — a new cleanup
*attempt* under the same operation, never a second, competing deletion
intent for the same document. This reuses the existing outbox/claim/lease
redelivery shape unchanged: an unclaimed or lease-expired deletion
operation is reclaimed exactly as an unclaimed ingestion event already
is, and a `DELETING` document whose operation has exceeded a bounded
threshold without completing is surfaced as visibly stuck in the read
model, not silently stuck.

**Ownership split:** Laravel owns authorization; resolving the document
and its full scope; the atomic `DELETING` transition; the ingestion
quiescence barrier described below; enumerating every vector scope
requiring cleanup; object-storage cleanup (`DocumentObjectStorage`, a new
delete method); removing corpus-assignment pivot rows and hard-deleting
`DocumentChunk` rows once quiescence and verified vector cleanup are both
established; validating Python's typed cleanup report; the final
`DELETING → DELETED` transition; and audit/browser-safe status. Python
owns only: claiming the deletion operation over the same HMAC-
authenticated channel as ingestion; calling `QdrantVectorStore.
delete(scope)` once per scope Laravel enumerated; verifying points per
scope; and reporting a typed completion or failure back. Python never
decides deletion is authorised, never changes `Document.status` directly,
never receives an unrestricted document/workspace/vector selector from
the browser, and never decides the document is finally `DELETED`.

**Publication barrier, now confirmed rather than flagged for
verification.** Directly verified against `AuthoriseIngestionPublication`
and `CompleteIngestionAttempt`: both already require
`DocumentStatus::Processing` before proceeding — `DELETING` is never
`Processing`, so both are already, structurally, rejected once `DELETING`
is entered. The second revision's open item ("R19-S01 must verify those
actions already check `Document.status`") is resolved: they do. **The
genuine gap is not publication — it's lease renewal, addressed next.**

#### Ingestion quiescence: the real gap, and the barrier that closes it

Verified: `RenewIngestionLease` validates only the lease itself — it does
not check `Document.status`. A worker that claimed an ingestion attempt
before deletion began can therefore keep renewing its lease, keep
extracting and embedding, and keep writing **provisional** vector points
indefinitely after `DELETING` begins, even though `AuthoriseIngestionPublication`/
`CompleteIngestionAttempt` will always refuse it. Those provisional
points would never reach publication, but they also would never be
cleaned up by a design that only enumerates completed corpus-generation
assignments (the exact race the brief describes: enumerate → find
nothing → the still-active worker writes points afterward → they're
refused publication and orphaned in Qdrant indefinitely). The dual
retrieval-visibility gate keeps orphaned provisional points from ever
being *served*, but that is not the same as deletion cleanup actually
removing them — this document requires the latter.

**The barrier, stated as a sequence:**

1. **Lease renewal is gated on Document status.** `RenewIngestionLease`
   must reject renewal once `Document.status` is `DELETING` or `DELETED`
   — a new check alongside its existing lease validation, returning a
   typed rejection distinct from ordinary lease expiry, so Python can
   tell "your lease genuinely expired" from "this document is being
   deleted, stop."
2. **New claims are refused once `DELETING`.** `ClaimDocumentIngestion`
   must reject a claim attempt against a `DELETING`/`DELETED` document —
   closing the case where an already-published `document.ingestion.
   requested` outbox event is claimed for the first time, or redelivered
   by SQS, after deletion has begun.
3. **Bounded convergence, not instant interruption.** A worker mid-
   provider-call (an embedding batch, extraction) cannot be interrupted
   mid-flight, and this document does not require that. What it requires
   is that the worker's *next* state-changing step — persisting a batch,
   sealing, requesting lease renewal — is gated behind a fresh
   lease/status validity check, and a worker whose renewal is refused (or
   whose lease has simply expired) must not perform another write. The
   orchestrator already re-validates lease state around these steps for
   ordinary expiry handling; R19-S01 must verify (and, if needed, extend)
   every such check to also cover the new `Document.status` condition —
   this is an extension of existing control flow, not a new shape.
4. **A worker cannot report deletion-cancellation as an ingestion
   failure, and this document does not ask it to — corrected from a real
   error in the second revision.** `FailIngestionAttempt` accepts only a
   *permanent processing failure* and itself requires
   `Document.status === Processing`; once `DELETING`, the Document is no
   longer `Processing`, so a worker attempting to self-report `failed`
   after observing lease rejection would simply be refused by the same
   barrier this document already relies on elsewhere. Worse, deletion
   cancellation is not semantically a processing failure at all, and must
   never increment the ingestion-failure metric (see "One authoritative
   ingestion-failure counter" below) — conflating the two would make
   routine administrative deletion look like a spike in ingestion defects.
   **A new terminal `IngestionEventClaim` status, `Cancelled`, is added**
   (alongside the existing `Open`/`Sealed`/`PublicationAuthorised`/
   `Completed`/`Failed`), reached only through a new purpose-scoped,
   HMAC-authenticated acknowledgement request — e.g. `ingestion.attempt.
   cancel`, modelled directly on `ingestion.fail`'s shape but distinct
   from it: it carries no `classification`/`failure_code`/
   `failure_message` (cancellation has no failure reason to record), only
   the claim/lease/document lineage needed for Laravel to validate the
   request and (per "Ingestion usage on cancelled attempts" below)
   whatever usage the attempt had already incurred before it observed
   cancellation. Laravel validates attempt/document/lease lineage and
   marks the claim `Cancelled` **without touching `Document.status`** —
   the Document stays `DELETING` throughout; only the claim's own status
   changes.
5. **The deletion operation waits for quiescence before requesting vector
   cleanup.** Laravel does not proceed to (or trust) vector cleanup until
   every `IngestionEventClaim` snapshotted as non-terminal at the moment
   `DELETING` was entered has since reached `Cancelled` (the worker
   acknowledged it), reached `Failed` (a genuine processing failure the
   worker hit for an unrelated reason before ever observing the
   cancellation signal — still a real failure, still counted), or had its
   `lease_expires_at` pass with no successful renewal since `DELETING`
   began (no acknowledgement ever arrives — lease expiry alone still
   establishes bounded quiescence, governed by the same lease duration
   already used elsewhere for stuck-claim reclaim, not a new timeout
   concept). An expired-but-still-non-terminal claim is treated as
   quiescent for deletion purposes on that basis alone — it does not need
   to reach `Cancelled` to unblock cleanup — while the barrier from points
   1-2 above (lease renewal and new claims both refused once `DELETING`)
   guarantees it can never be reclaimed or renewed afterward, which is
   the "state retained to prevent later reclaim" this needs. None of the
   non-terminal states can reach `completed` once `DELETING` blocks both
   publication actions, so this condition is exhaustive.
6. **Vector-scope enumeration and cleanup run only after quiescence is
   established** — guaranteeing no worker can still be mid-write when
   Python is asked to enumerate, delete, and verify.
7. **`DELETED` requires quiescence *plus* verified cleanup**, not cleanup
   alone — a deletion that reached apparent cleanliness while an attempt
   was still actively renewing would only be correct by timing
   coincidence, not by design.

**Other interactions, addressed explicitly:** an already-running
embedding batch is covered by bounded convergence (point 3) — the batch
completes under provider control, but the write that would follow it is
gated. Queue redelivery of an ingestion event after `DELETING` still
requires a successful claim, refused per point 2. The orchestrator's
existing `_cleanup_provisional` capability may be reused as a worker's
own best-effort self-cleanup on observing cancellation, but correctness
does not depend on it — Laravel's post-quiescence vector cleanup (below)
is authoritative and re-scans/re-deletes regardless of whether a worker
already tried. A stuck deletion operation — whether stuck waiting for
quiescence or stuck mid-cleanup — is reclaimed via the same bounded
redelivery already described for ingestion, and its wait/cleanup steps
are re-evaluated idempotently (re-checking claim terminal states,
re-issuing filter-based deletes, both naturally safe to repeat).

#### Vector scope enumeration: attempts, not only assignments

**This enumeration runs once, after quiescence is established** (per
"Ingestion quiescence" above), and its result is durably recorded on the
`DocumentDeletionOperation` as the final scope set before cleanup is
dispatched — not computed speculatively at operation-creation time, and
not re-derived from scratch on every retry once recorded, though a
re-drive of a stuck operation may re-run this enumeration if quiescence
had not yet been reached when it originally stalled.

`remove_vector_space()` drops an entire Qdrant *collection* — confirmed
unused anywhere in this codebase today — and **must never be invoked for
single-document deletion**; it is reserved, undesignated by this
document, for a genuinely different future operation (retiring an entire
embedding-space generation/collection once independently proven unused).

`delete(scope)` is filter-based within one collection, and a `VectorScope`
always requires both a `VectorSpace` (collection identity) and a
`workspace_corpus_generation_id`. Enumerating scopes from
`workspace_corpus_generation_chunks` pivot assignments alone is
**insufficient**, corrected from the prior revision: pivot rows are only
ever created inside `CompleteIngestionAttempt`, so an attempt that wrote
provisional points but never completed — precisely the attempt the
quiescence barrier above targets, which may already have written some
points before quiescence was reached — would be invisible to that
enumeration alone. **Laravel must enumerate the union of:**

- every `IngestionEventClaim` for the Document, regardless of terminal
  status, reading its `embedding_space_generation_id`,
  `sparse_space_generation_id`, and `workspace_corpus_generation_id`
  (all already present on the model, all nullable since not every claim
  reaches every stage) — covering attempts that wrote provisional points
  but never reached `CompleteIngestionAttempt`;
- existing `workspace_corpus_generation_chunks` pivot assignments —
  covering completed, published attempts;

deduplicated by `(collection, workspace_corpus_generation_id)` before
being sent to Python as the bounded typed cleanup command. For each
enumerated pair, Python issues one `delete()` call scoped to
`document_id`, with `publication_status` left unset so both `PROVISIONAL`
and `PUBLISHED` points are removed.

**Three distinct, correctly-separated outcomes per scope — corrected
from the prior revision's conflation:**
- **Authoritative collection-not-found** (`collection_exists()` returns a
  confirmed `False`): may be reported as already-clean — a collection
  that was never created, or was already fully retired, cannot hold
  points for this document.
- **Provider timeout or unavailability** (the check or delete call itself
  fails to complete): a **retryable cleanup failure**, never treated as
  evidence of cleanliness. The prior revision's wording — treating an
  "unavailable" historical collection as already-clean — conflated this
  with the first case; corrected here: *"could not contact the
  collection"* is not *"the collection doesn't exist,"* and deletion must
  not advance toward `DELETED` on the strength of an unreachable check.
- **Malformed or mismatched scope** (a scope Laravel sent that Python's
  contract validation rejects, or that references an inconsistent
  collection/generation identity): a rejected, non-retryable
  configuration/contract failure — surfaced loudly as a defect, not
  silently retried forever.

Laravel treats an unverified, non-zero-remaining, or retryable-failure
report as insufficient to proceed toward `DELETED`, and requires every
enumerated scope to resolve to either verified-clean or authoritative-
not-found before cleanup is considered complete.

#### Cleanup order

The complete deletion sequence, matching all of the above:

```text
Owner/admin delete command
    → Laravel authorizes and creates one deletion operation
    → Document enters DELETING
    → new ingestion claims and lease renewals are rejected
    → active ingestion attempts cancel or leases expire (quiescence)
    → Laravel enumerates all vector scopes from attempts + assignments
    → Python deletes and verifies provisional + published points
    → Laravel removes the source object
    → Laravel removes corpus assignments and content-bearing chunks
    → EvidenceSnapshots retain independent citation text and lineage
    → Laravel marks Document DELETED
```

Object-storage removal may overlap with safe portions of quiescence and
vector cleanup (it has no ordering dependency on either); `DELETED`
requires every mandatory step — quiescence, verified vector cleanup,
object-storage removal, and relational chunk/pivot removal — to be
verified complete before it is entered. Relational chunk/pivot removal
must not precede verified vector cleanup, since enumerating scopes relies
on the pivot/claim rows this step would otherwise have already destroyed.

**Disclosure commitment, not frozen copy:** the deletion confirmation
must accurately convey that the document is removed from the knowledge
base and future answers, while passages already cited in existing
conversations remain with those conversations. This document commits to
that meaning, not to specific UI wording.

**`EvidenceSnapshot` reconciliation, final:** source/ingestion/retrieval
artefacts — the object-storage file, vector-store entries, corpus-
assignment pivot rows, and content-bearing `DocumentChunk` rows
themselves — belong to the Document lifecycle and are physically removed
by document deletion. An accepted `EvidenceSnapshot` belongs to the
historical `GeneratedAnswer`/conversation record, not to the Document
lifecycle, and is governed entirely by that conversation's own deletion
lifecycle (ADR-0024) — never cascade-removed merely because its source
Document is later deleted, and never blocked from resolving because its
source chunk is gone, since its own lineage fields (verbatim text,
digest, provenance, and the new scalar chunk/attempt identifiers) are
immutable and snapshot-owned from creation. Citation presentation should
indicate the original source has since been removed, where appropriate (a
rendering-layer concern, not fixed here); citation inspection must never
attempt to read a live `DocumentChunk` after deletion — there may not be
one. Deleting the *conversation* that contains those citations — a
separate, explicit act — removes its `Message`s, `GeneratedAnswer`s,
`AnswerPart`s, citations, and `EvidenceSnapshot`s according to ADR-0024,
unchanged. A future compliance-grade "purge this content everywhere,
including historical citations" capability is a separate, explicitly
deferred concern (see "Explicitly deferred work" below) — this document
does not build it, and does not let ordinary document deletion silently
become it.

**Rejected alternatives, evaluated:** deleting `EvidenceSnapshot`s
whenever their source Document is deleted (Alternative 3) is rejected —
it would silently break every historical answer that cited the document,
directly contradicting the reason `EvidenceSnapshot` was designed to
store verbatim text in the first place; this is rejected on citation-
integrity grounds, not because any foreign-key constraint would prevent
it. Retaining deleted chunks indefinitely, whether as full content or as
sanitised tombstones, to preserve citations (Alternative 4, and the
chunk-tombstone design this revision itself withdraws) is also rejected
— unnecessary and, as demonstrated above, internally incoherent, since
`EvidenceSnapshot` — now carrying its own immutable structural lineage in
addition to its verbatim content — needs nothing from the chunk row
surviving. The chosen design is deliberately distinct from both: it
physically removes chunk content and rows (satisfying the concern
Alternative 4 would leave unaddressed) without touching `EvidenceSnapshot`
rows at all (satisfying the concern Alternative 3 would create), by
making the snapshot genuinely self-sufficient instead.

### Membership removal and content ownership

Workspace content is owned by the workspace, not personally by the member
who created it. Removing a member — whether voluntary departure or
administrative removal — never automatically deletes documents they
uploaded, ingestion attempts they initiated, conversations they created,
generated answers, workspace audit records, or historical usage.
References to a removed actor remain referentially safe: existing actor-
reference columns across this codebase (`created_by_user_id`,
`actor_user_id` on audit tables) are nullable-on-user-deletion or retained
as immutable descriptors already, per the patterns confirmed in
`DocumentGovernanceAuditEvent` and `Document` itself — Phase 19 follows
this same existing convention for any new membership/invitation audit
records rather than inventing a different one.

Membership removal, role changes, invitation actions, and ownership
transfer are all sensitive business-audit events (see "Audit and
observability boundary" below). Audit records for them must never contain
raw invitation tokens, document content, conversation content, provider
prompts/responses, or unnecessary personal data — the same allowlist-first
discipline ADR-0012 already requires of telemetry, applied here to
persisted audit records specifically.

### Usage visibility

Usage visibility is a tenant product/accounting projection — not Phase
20's future operational telemetry, and this document does not build that
system. Owner and admin may view usage; ordinary members may not,
unchanged from the capability matrix.

**Two fundamentally different metric shapes must never be presented as
one undifferentiated "usage" total (Alternative 9, rejected — collapsing
current state and historical activity into one number would make neither
number honestly interpretable):** current-state gauges (a snapshot of
what exists right now) and historical interval metrics (activity that
occurred within a defined window).

#### Query, run, and chargeable attempt — kept distinct, corrected from the first draft

The first draft implied a completed `GeneratedAnswer` count would stand
in for "queries." Verified this is wrong: `GenerationRun` has five
terminal outcomes (`GenerationRunStatus::isTerminal()`) — `completed`,
`retrieval_no_answer`, `clarification_required`, `failed`, `cancelled` —
and **only `completed` runs ever produce a `GeneratedAnswer` row**
(`PersistGeneratedAnswer` is invoked exclusively from the `completed`
path in `OrchestrateConversationRun`). A retrieval no-answer, a
clarification request, a failed provider call, or a cancelled run all
still represent real user activity — and in the failed case, real
provider token spend — that a `GeneratedAnswer`-only count would silently
omit.

This document defines separate metrics with separate authoritative
sources, rather than one overloaded "queries" number: **user
submissions**, **generation attempts/runs**, **successful grounded
answers** (equivalently, `GenerationRun`s with `status = completed`),
**controlled no-answer/clarification outcomes** (`status` in
`{retrieval_no_answer, clarification_required}`), **failed/cancelled
attempts** (`status` in `{failed, cancelled}`), and **token/cost usage**
covering every attempt that incurred provider spend, not only completed
ones (see the persistence gap below).

**These categories are defined against `Message`/`GenerationRun` at the
moment the events happen — they are not the query-time source once
history is being aggregated.** See "Content-free historical activity,
independent of conversation deletion" below for why, and for the durable
records that actually back Phase 19's historical counts.

**A genuine aggregation gap, not a cheap query, for anything beyond
completed-run token counts.** `generation_runs.usage` is a single
nullable `json` column, populated with contextualisation-stage usage for
*every* run regardless of terminal outcome, and merged with generation-
stage usage only for `completed` runs; failed provider calls get a thin,
non-indexed `{"generation": {"attempt_count", "latency_ms"}}` fragment
with no token counts. Only `generated_answers` unpacks a subset
(`input_tokens`, `output_tokens`, `latency_ms`, `cost_usd`) into
first-class, indexable columns — and only for `completed` runs. A "total
tokens used this interval, across all attempts including failures"
metric therefore cannot be produced by a cheap indexed query over
`generated_answers` alone, as the first draft implied; it would require
either expensive JSON aggregation over `generation_runs.usage` for every
run in range, or the normalised usage-event persistence this document
requires below for embedding usage, extended to cover generation usage
for every run (not just completed ones). This document requires the
latter — see "Embedding and generation usage reporting boundary" below —
rather than promising a query the current schema cannot cheaply support.

#### Current-state gauges, corrected definitions

**Active document count** — count of `Document` rows in an "active"
status set this document defines explicitly, since the first draft left
it implicit: every status except `DELETING` and `DELETED` (a document
mid-deletion is not part of the active knowledge base, even though its
row persists).

**Stored source bytes — relabelled from the first draft.**
`SUM(documents.size_bytes)` over active-status documents is **not**
verified physical object-storage consumption, and the first draft's
implication that it was is corrected here. `size_bytes` is client-
declared at upload-initialization time and only ever *verified* — not
independently measured — against the real object size once, at
`CompleteDocumentUpload` (a mismatch throws `DocumentUploadException`).
So for any document that has reached `Uploaded` or later, the value is
guaranteed equal to the actual stored object size at that one moment; it
says nothing about provider-side storage overhead, replication, or
subsequent changes, and a document still in `Uploading` has not yet been
verified at all. This gauge must be labelled as a **logical uploaded-
source-byte total**, verified-equal-to-object-size at upload completion,
explicitly not a "storage consumption" or billing figure — a genuinely
different quantity this document does not attempt to produce.

**Currently indexed/retrievable chunk count — corrected from a raw
`COUNT`.** `document_chunks` rows persist across every ingestion attempt
a document has ever had — the table has no `published`/`current` flag,
and a raw `COUNT(*) WHERE document_id = X` overcounts against historical,
superseded, and provisional attempts. The authoritative definition of
"currently indexed" is chunk rows that are (a) assigned, via
`workspace_corpus_generation_chunks`, to the workspace's currently
`Active`-status `WorkspaceCorpusGeneration`, and (b) belong to a
`Document` in an active status per above. This is a join through the
pivot and generation tables, not a bare chunk count — a materially
different (and more expensive, though still boundedly indexable) query
than the first draft implied.

**`DELETING` representation:** a document in `DELETING` is excluded from
every current-state gauge above (it is being removed from the active
knowledge base) but its historical activity (past ingestion attempts,
past queries that cited it) remains fully counted in historical interval
metrics, unaffected — this is the same current-vs-historical distinction
already established, applied consistently to the in-between `DELETING`
state.

#### Historical interval metrics

Activity that occurred within a defined window, and does not change
retroactively because a resource was later deleted, provided no content
is retained merely to preserve the metric: ingestion failures; generation
attempts, outcomes, and token/cost usage as defined above; embedding
usage (see below).

**One authoritative counting identity for ingestion failures, corrected
from the prior revision's ambiguity.** The prior revision named both
`IngestionEventClaim` and `IngestionAuditEvent` as sources, which risks
double-counting the same failure. The single authoritative counter is:
one failed ingestion attempt = one `IngestionEventClaim` entering the
`failed` terminal state, counted by `failed_at` falling in the interval —
never removed by later document deletion, so historical counts stay
stable. `IngestionAuditEvent` rows explain *why* a transition happened
(for the audit trail and read-model detail views) but are not the
numerical counter, and must never be summed alongside `IngestionEventClaim`
counts without explicit deduplication by attempt identity, which this
document does not require since `IngestionEventClaim` alone is
sufficient. **`Cancelled` is explicitly excluded from this count.** An
attempt cancelled because its document was deleted (per "Ingestion
quiescence" above) is counted, if anywhere, as deletion-cancellation
activity — never as an ingestion failure; conflating the two would make
routine administrative deletion register as an ingestion-quality
regression.

**Interval semantics, defined precisely rather than left implicit:**
UTC-backed, half-open intervals (`[start, end)`), so no instant is
double-counted or dropped at a boundary. Recommended initial display
periods — last 7 days, last 30 days, current calendar month — are stated
as product defaults, not an architecture invariant; the ADR's own
commitment is to the half-open-UTC-interval *shape*, which the web UI may
later expose additional ranges against without this document being
reopened. Every usage query is workspace-scoped, matching the same
tenant-isolation discipline as every other query in this system. Current
totals reflect current active/retained state and change when the
underlying resource changes; historical interval totals remain historical
even after the originating document, conversation, or member is later
deleted — recorded once, at the time the activity happened, never
retroactively recomputed because something downstream changed.

### Provider-reported usage and estimated cost

Provider-reported usage, application-calculated usage, estimated monetary
cost, and unavailable usage are four distinct states, never conflated.
**Unknown values are never rendered as zero** — a missing figure is
displayed as unavailable, not as evidence of zero activity, because the
two mean structurally different things and confusing them would
misrepresent real usage as free.

**Generation cost facts, corrected from the first draft's overstatement.**
The first draft treated `generated_answers.cost_usd` being a real,
populated, nullable column as evidence that generation cost is available
today. Verified directly against the OpenAI adapter
(`apps/ai/app/generation/openai_adapter.py`, `_to_result`): OpenAI's
Responses API returns only `input_tokens`/`output_tokens`/
`cached_tokens` — never a monetary figure — and the adapter itself
performs no local cost calculation; it unconditionally sets
`cost_usd: None`, `cost_basis: "unavailable"`, `pricing_snapshot: None`
in the `GenerationResult` it returns. `PersistGeneratedAnswer` writes
`$result->usage['cost_usd'] ?? null` straight onto the row — so
`generated_answers.cost_usd` is real, schema-correct, and **always
null in every production row today**, not populated. There is also no
`pricing_snapshot` column on `generated_answers` at all (only a key
inside the `usage` JSON blob, itself never populated for generation). By
contrast, `input_tokens`/`output_tokens`/`latency_ms` genuinely are
populated with real provider-reported numbers on every completed run.
Corrected distinction: **generation token usage is available and
provider-reported today; generation cost is not available in any form —
neither provider-reported nor locally estimated — until a pricing
mechanism is built.** The first draft's "provider-reported cost" language
is retracted; no provider in this codebase currently reports a monetary
cost figure for anything.

Estimated cost is labelled as an estimate, never as an invoice; it
derives from persisted provider/model/token lineage where available; it
uses an explicitly versioned pricing source. **Confirmed: this pattern
already exists for embeddings** — Python's `Settings
.embedding_estimated_cost_per_million_tokens_usd` (default `0.12`) and
`.embedding_pricing_snapshot` (`"voyage-pricing-2026-08-12"`), wired
end-to-end into `VoyageEmbedder`, computing a real `estimated_cost_usd`
per embed call. **No equivalent exists for generation** — confirmed
above, `cost_usd` is null with no local pricing table as a fallback.

**Pricing-ownership boundary, stated precisely — corrected from an
ambiguity in the prior revision, which said Laravel owns pricing policy
while also implying Python would gain the pricing computation without
reconciling the two.** The chosen boundary mirrors the embedding
adapter's own already-proven pattern exactly, rather than inventing a
different split for generation: Python's provider adapter (the same
class that already returns `cost_usd`/`cost_basis`/`pricing_snapshot`
fields on `GenerationResult`, today hardcoded to
null/`"unavailable"`/null) owns provider/model-specific token parsing and
computes a provider-specific cost estimate from an explicit, versioned
local pricing snapshot — the same shape `VoyageEmbedder._estimated_cost()`
already implements for embeddings, extended to a new
`generation_estimated_cost_per_million_tokens_usd`-style setting and a
`generation_pricing_snapshot` identifier. Python reports tokens,
`cost_basis`, estimated cost, and the pricing-snapshot identifier through
the existing typed `GenerationResult`/`usage` contract — no new channel,
just the same fields finally populated. Laravel validates and persists
that report with `GenerationRun`/`GeneratedAnswer` lineage, and owns
everything downstream of receiving it: tenant aggregation, authorization,
labelling, currency presentation, and the rule that an estimate is never
presented as an invoice. **No value is ever labelled provider-reported
monetary cost unless a provider actually supplied the monetary figure
itself** — since neither OpenAI nor Voyage does today, every cost figure
in this system is, and remains, an application-calculated estimate, never
provider-reported, until a provider that returns real billing data is
integrated. This is genuinely new work on the Python side — a local
pricing configuration plus the computation that currently doesn't exist
anywhere in the OpenAI adapter path — not "extend an existing fallback,"
since today there is no fallback to extend.

Cost calculation preserves enough lineage to explain which rates were
applied (the pricing-snapshot identifier travelling alongside the usage
figures it was computed from — the same idea `generation_fingerprint`
already applies to quality-affecting configuration, applied here to
pricing configuration). Historical totals are never silently recalculated
under today's prices; a "what would this have cost at current rates" view
is legitimate but must be clearly labelled as a current-price re-estimate,
never presented as the original historical figure (Alternative 8,
rejected: recomputing all historical cost using current prices,
unlabelled, would make a historical total lie about what actually
happened at the time). A cost estimate becomes explicitly unavailable,
never fabricated, when model/rate/usage data is insufficient — which, for
generation, is every row until the new pricing mechanism above exists.
Currency and numeric representation use a defined currency and a safe
fixed-precision numeric type — the existing `cost_usd` column's
`decimal(12,8)` type is the right shape to extend to any new pricing
computation, not floating-point.

**The Python/Laravel split, stated as an explicit distinction rather than
the ambiguous "pricing policy" phrasing an earlier revision used** (which
read as contradicting Python's own rate-configuration and calculation
ownership above — it did not intend to, and is corrected here):

- **Python owns provider/model-specific estimation inputs and
  calculation**: the local rate configuration, the effective/versioned
  pricing snapshot, and the calculation of the provider-specific
  estimate itself — unchanged from "Pricing-ownership boundary" above.
- **Laravel does not reinterpret or silently recalculate that historical
  estimate.** Once persisted, a `GeneratedAnswer`'s cost figure is the
  figure Python calculated at the time, under the pricing snapshot named
  alongside it; Laravel's role is to validate, store, and present that
  figure, never to substitute its own recomputation of it.
- **Laravel owns tenant-facing cost policy and presentation**: which
  authorised users may see it (per the capability matrix); tenant
  aggregation across documents/runs/intervals; labelling it as an
  estimate; currency formatting and numeric representation; unavailable/
  partial-data treatment; and ensuring it is never represented as
  billing-grade or provider-reported unless a provider genuinely
  supplied the monetary figure itself.
- **Neither side owns billing or invoicing in Phase 19** — this is not
  billing-grade accounting unless a future ADR explicitly makes it so,
  stated plainly so Phase 19's numbers are never mistaken for an invoice.

Laravel also owns the browser-facing usage calculation/projection and
tenant authorization generally (beyond cost specifically), and Python
reports provider-native usage through its existing typed contracts — the
same ownership boundary ADR-0023/ADR-0024 already establish for
generation, extended here to usage reporting.

### Embedding and generation usage reporting boundary

Verified: `EmbeddingResult` (`provider_input_tokens`, `estimated_cost_usd`,
`pricing_snapshot`) is computed on every embed call, but the document-
ingestion orchestrator (`_embed_persist_verify`) never reads these fields
before building the `ingestion.complete` evidence payload — they reach
only OpenTelemetry span attributes and logs, never Postgres, and are
**absent from the completion payload entirely**, not merely unread by
Laravel. Extending ingestion to carry them is additive to the existing,
already-validated `evidence`/`IngestionOperationRequest` shape (a new
purpose-scoped field set, following the same pattern as the existing
digest/count fields), not a new authenticated channel — Python must not,
and under this design does not, write directly to Laravel's database.

**The shape, and why it isn't invented from nothing:** a directly
analogous transport already works, proven, on the query/retrieval path —
`Retriever` already builds an `OperationUsage` from the same
`EmbeddingResult` shape and returns it inside the synchronous rc1
response; `RetrievalClient.php` already parses `provider_input_tokens`/
`pricing_snapshot` out of that response into `PlannerUsage`. This
document requires the ingestion path to carry the equivalent shape over
its own existing channel (the `ingestion.complete` HMAC-authenticated
request), not the retrieval path's — the two are separate protocols for
separate purposes, but the *usage-shape* precedent transfers directly:

```text
Python ingestion orchestrator
    → groups EmbeddingResult usage across the attempt's batches by
      (provider, model, pricing_snapshot, cost_basis)
    → includes one usage entry per group as a new typed field on the
      existing ingestion.complete payload: [{provider, model,
      input_tokens, cost_usd (nullable), cost_basis,
      pricing_snapshot (nullable)}, ...]
Laravel (IngestionOperationController::complete)
    → validates it via IngestionOperationRequest, same as every other
      field on that purpose
    → resolves and validates workspace/document/claim lineage from the
      already-authenticated request context (never browser-supplied)
    → persists one durable, normalised usage-event row per group,
      keyed to the IngestionEventClaim it came from
```

**Normalised persistence, not another JSON blob — for both embedding and
generation usage.** The "Query, run, and chargeable attempt" finding
above already established that JSON-blob usage storage cannot support
cheap interval aggregation.

**Stage-aware cardinality, corrected from the prior revision's "one row
per `GenerationRun`" oversimplification.** A single run can perform
multiple independently metered stages — contextualisation, retrieval
query embedding, reranking, answer generation, and provider retries
within any of those — and these stages can use different providers,
models, pricing snapshots, and cost bases from each other. Collapsing
them into one row per run would force an arbitrary choice of whose
provider/model identity "wins," misrepresenting every run that touches
more than one provider. **This document requires one normalised usage-
event row per run *and* operation kind**, keyed uniquely by
`(generation_run_public_id, operation_kind, ordinal)` — an immutable
scalar identifier, not a live foreign key (see "Content-free historical
activity, independent of conversation deletion" below for why) —
`operation_kind` distinguishing `contextualisation`/`retrieval_embedding`/
`rerank`/`generation`/etc., and `ordinal` distinguishing repeated attempts
of the same kind within one run (a provider retry within the generation
stage, for instance) so no two attempts collapse into one ambiguous row.
Aggregating multiple provider calls into a single row is permitted only
where every aggregated call shares an identical provider, model,
pricing snapshot, and cost basis — an explicit, checkable condition, not
a default; where that condition doesn't hold, separate rows are
required, never an averaged or last-write-wins figure.

The same rule applies to embedding usage on the ingestion side: one
ingestion-attempt embedding record may aggregate batches only when every
batch shares the same provider, model/profile, pricing snapshot, and cost
basis; otherwise it must produce separate records per distinct
`(provider, model, pricing_snapshot, cost_basis)` group within the
attempt.

**Illustrative row shape (not mandated as exact DDL), corrected lineage
reference for generation usage — see "Content-free historical activity"
below for why:** workspace_id, a scope reference — `ingestion_event_claim_id`
for embedding usage (a `restrictOnDelete()` FK never exercised, since
claims are retained unchanged, per "Document deletion" above — a live FK
is safe here because nothing ever removes the row it points to) or
`generation_run_public_id` for generation usage (an immutable **scalar**,
not a live foreign key — see below for why this must not be a live FK to
`generation_runs`) — `operation_kind`, `ordinal`, provider, model/space
identity, input/output token counts, `cost_usd` (nullable), `cost_basis`,
`pricing_snapshot` (nullable), `occurred_at`. Idempotency: unique per
`(scope reference, operation_kind, ordinal)` — a retried ingestion
attempt or generation run gets its own claim/run identity and therefore
its own set of rows, consistent with this document's retry model.

**Honest incompleteness, not silent omission.** Provider failures do not
always return token usage at all — a request that fails before the
provider returns any usage payload has no token count to record. A row
is still written when *some* usage is known (`cost_usd: null`,
`cost_basis: "unavailable"` when cost specifically can't be computed),
but where a provider call fails with no usage payload whatsoever, no
fabricated row is invented to stand in for it. The usage dashboard
reports **known, persisted usage** and must mark genuinely incomplete
coverage honestly (e.g. "N attempts recorded no usage data") rather than
implying it has captured every chargeable provider attempt when the
provider itself supplied nothing to record.

**This is genuinely new schema and write-path work on both the embedding
and generation sides** — extending the existing `ingestion.complete`
contract for embeddings, and adding equivalent normalised, stage-aware
writes at every `GenerationRun` terminal-outcome point (not only
`completed`) for generation — allocated to R19-S03 as a real
prerequisite, not incidental plumbing.

#### Ingestion usage on failed and cancelled attempts

The transport above, as first described, only carried embedding usage
through `ingestion.complete` — leaving open how usage already incurred
by an attempt that never reaches completion gets reported at all. An
attempt can incur real embedding usage and then still fail before
completion, be cancelled by document deletion, or lose its lease after
doing real provider work. Usage already incurred in any of those paths
is real historical activity and must not be silently dropped merely
because the attempt didn't end in `Completed`.

**Resolved: the same typed usage-entry shape is carried on every terminal
report, not only `.complete`.** `ingestion.fail`'s request body and the
new `ingestion.attempt.cancel` acknowledgement (introduced in "Ingestion
quiescence" above) may both carry the identical typed usage-entry array
already defined for `.complete`. Laravel deduplicates by
`(ingestion_event_claim_id, operation_kind, ordinal)` — the same unique
key already governing the usage-event table — so it does not matter
which terminal path (complete, fail, or cancel) ends up reporting a given
group's usage, or whether a redelivered request reports it more than
once; the row is written at most once per key regardless. Usage already
recorded before an attempt failed or was cancelled remains historical and
permanent — it is never retracted because the attempt itself didn't
succeed, exactly as "One authoritative ingestion-failure counter" above
already keeps `Cancelled` out of the failure count without touching
whatever usage rows that same attempt already produced. Where a provider
call failed or a lease was lost before any usage payload was ever
returned, the honest-incompleteness rule above applies unchanged — no
row is fabricated to fill the gap.

### Content-free historical activity, independent of conversation deletion

This document already commits, in "Historical interval metrics," to
historical activity remaining stable when the resource that produced it
is later deleted. Applied to conversations specifically, this creates a
genuine design gap the prior revisions left unaddressed: ADR-0024's
conversation deletion removes `Message`, `GenerationRun`, and
`GeneratedAnswer` rows entirely (content-bearing children, correctly
removed). Defining "user submissions" as a count of live `Message` rows
and "attempts" as a count of live `GenerationRun` rows — as the previous
section does for *production-time* categorisation — would mean, once a
conversation is deleted, one of three broken outcomes: historical counts
silently decrease (deleted messages stop being counted); a
`generation_run_id`-keyed usage-event table gets cascade-deleted along
with the run (historical cost silently decreases); or a restrictive
foreign key blocks ADR-0024's own deletion outright. **All three
contradict this document's own historical-usage rule, and this document
resolves the contradiction by making the historical record content-free
and structurally independent of conversation content, not by weakening
either commitment.**

**The model:** Phase 19's historical aggregation is backed by durable,
workspace-owned, content-free activity records — never by querying live
`Message`/`GenerationRun`/`Conversation` rows for anything beyond their
current, in-conversation display. Three record kinds:

- **User submission events** — one written by Laravel **atomically, in
  the same database transaction as accepting** the `USER` `Message`;
- **Run outcome events** — one written by Laravel **atomically, in the
  same database transaction as** the `GenerationRun`'s terminal-status
  transition (`completed`, `retrieval_no_answer`, `clarification_required`,
  `failed`, `cancelled`);
- **Stage-aware usage events** — as already defined above, one per run
  and operation kind, written **atomically, in the same database
  transaction as** Laravel's acceptance/persistence of the corresponding
  typed provider result, terminal report, or ingestion callback (the
  `GenerationRun` persistence transaction for generation usage; the
  `ingestion.complete`/`.fail`/`.cancel` request-handling transaction for
  embedding usage).

**Atomicity and recoverability, stated as an explicit reliability
invariant — corrected from an earlier, permissive "same transaction, or
immediately after" framing that left a real crash window.** Every write
above happens in the *same* database transaction as the domain event it
represents, never queued for a separate best-effort step afterward: a
crash between the domain event committing and the activity event being
written is structurally impossible, because either both commit together
or neither does. Where a single transaction is genuinely impossible
because a real external boundary sits between the domain event and
Laravel's own persistence — a case this document does not currently have
a concrete instance of, since every write named above already happens
inside a Laravel-owned request/transaction, but which this invariant
covers regardless, for any future path that does introduce one — the
source event's own transaction instead commits an outbox/reconciliation
record, reusing the same transactional-outbox discipline this document
already establishes for ingestion publication, so the activity event
remains durably recoverable and is materialised by a separate, later
step rather than lost if the process crashes in between. "Best-effort,
immediately afterward" is not an acceptable substitute for either
guarantee.

**Idempotency identity, so redelivery or reconciliation never inflates a
historical count.** Every activity event carries a deterministic
idempotency identity — workspace plus a source public identifier plus
event kind — enforced by a plain unique index, not a foreign key: user-
submission events by `(workspace_id, message_public_id)`; run-outcome
events by `(workspace_id, generation_run_public_id)` (a run reaches
exactly one terminal outcome, ever, so this is naturally unique); stage-
aware usage events by the `(scope reference, operation_kind, ordinal)`
key already defined above, itself workspace-scoped. A duplicate write —
a retried request, a redelivered outbox/reconciliation record, a
repeated terminal-report acknowledgement — collides against this unique
index and is rejected or safely ignored rather than producing a second
row, the same discipline this document already applies to every other
repeatable write (retry's idempotency key, deletion's single-operation
constraint). **The absence of a live foreign key to `Message`/
`GenerationRun` does not weaken this**: a unique index enforces the same
duplicate-prevention guarantee whether or not the column it's built on
also participates in a foreign key, and provenance — confirming a public
identifier genuinely belongs to the authenticated workspace/request
writing it — is validated at write time, inside the same transaction,
before the row commits; the foreign key was never what performed that
validation in the first place.

All three contain **no question text, answer text, citation text,
prompt, provider response, or other conversation content whatsoever** —
only workspace identity, timestamps, operation kind/outcome category,
provider/model/pricing lineage, and numeric usage. They may retain
immutable public operation/correlation identifiers where privacy policy
permits (for cross-referencing a still-live conversation's own detail
view, when one still exists), but never a live relational dependency on
the conversation surviving.

**The foreign-key rule, stated once, applied to every one of these
records:** none of them holds a live, constrained foreign key to
`Message`, `GenerationRun`, or `Conversation`. Where an identifier is
retained at all, it is an immutable **scalar** public identifier
(`message_public_id`, `generation_run_public_id`) captured at write time
— not a foreign key `ON DELETE` behaviour this document has to get
right, because there is no foreign key to configure in the first place.
This is a deliberately stronger choice than "nullable FK with
`NULL ON DELETE`" (the pattern used elsewhere in this document for
`EvidenceSnapshot.document_chunk_id`, where a live join was worth
retaining because the referenced row usually *isn't* deleted): here, the
referenced `Conversation` subtree is *routinely* deleted by ordinary
product use, so coupling historical accounting to its FK lifecycle at all
— even via `NULL ON DELETE` — would still mean conversation deletion has
to touch, lock, or update Phase 19's tables. Omitting the live FK
entirely means ADR-0024's conversation deletion **never needs to know
Phase 19's tables exist**: it is not blocked by them, does not cascade
into them, and does not have to be sequenced relative to them in any way.
`workspace_id` remains a live, ordinary foreign key throughout — `Workspace`
is never deleted the way conversation content is.

**Two distinct authoritative sources for two distinct questions, kept
explicit:** `Message`/`GenerationRun` rows are the authoritative source
*at production time* — the moment content is created, before anything
about it becomes historical — and remain the authoritative source for
*current-state, in-conversation* views (a conversation's own transcript,
for as long as that conversation still exists). The content-free activity
records above are the authoritative source for *Phase 19 historical
interval aggregation*, from the moment they're written onward,
permanently, regardless of what later happens to the conversation that
produced them. **The usage dashboard must not aggregate historical query,
run-outcome, or token/cost counts directly from live `Message`/
`GenerationRun` rows** — doing so would make deleted conversations
silently vanish from history, exactly the outcome this document commits
to preventing. This applies uniformly to every category defined in "Query,
run, and chargeable attempt" above: user submissions, successful answers,
controlled no-answer/clarification counts, failed/cancelled counts, and
stage-aware token/cost usage all read from these content-free records for
historical purposes. If a current-state "active conversation count" is
ever shown (a different question — "how many conversations exist right
now," not "how much history happened"), it may use live rows, exactly as
current-state document/chunk gauges elsewhere in this document already
do.

### Aggregation, performance and freshness

No usage-aggregation query, repository, or read model exists anywhere in
this codebase today — R19-S03's aggregation layer is genuinely greenfield.
The preferred V1 approach is the smallest design honest at this
repository's current scale: indexed, bounded, tenant-scoped queries (the
existing `(workspace_id, status)` index on `documents` already makes
failure counts cheap; a workspace-scoped generation-usage query over a
bounded interval is a straightforward indexed range scan, not a full
scan); explicit maximum time ranges on every historical query; no
unconstrained scans; no cross-workspace cache keys, ever; visible
freshness/as-of information on every usage view, so a user can tell how
current a figure is. Materialised rollups are introduced only where
current data volume or observed query plans actually justify them
(Alternative 10, rejected for V1: building a full materialised analytics
subsystem now, with zero evidence bounded relational aggregation is
insufficient at this repository's actual scale, would be exactly the kind
of premature infrastructure this platform's engineering philosophy argues
against elsewhere). If a future rollup is needed, it must define tenant
keying, time-bucket semantics, idempotent rebuild, late-arriving usage,
deletion effects, and reconciliation against source records — named here
as the bar a rollup would have to clear, not designed now, because nothing
yet demonstrates the need. Usage figures must never allow one tenant to
infer another tenant's activity, provider usage, or cost — the same
tenant-isolation invariant as every other view in this system, restated
because a poorly-scoped aggregate (a global average, a leaderboard, a
shared cache key) is a realistic way this could quietly slip in if not
named explicitly.

### Tenancy, security and browser behaviour

Every list, detail, mutation, and usage query in this document is scoped
through current workspace membership and server-side policy, unchanged
from ADR-0006's existing enforcement stack. The browser is never trusted
to supply an unrestricted `workspace_id`, role, ownership decision,
document status, or cost total — every one of those is server-resolved
from authoritative state on every request. The existing 404-not-403
concealment discipline applies wherever resource existence could otherwise
leak to an unauthorised party.

**Required negative tests**, all newly meaningful because this document
introduces the first role-gated document actions and the first
membership/invitation/ownership surface in this codebase: cross-workspace
document listing, detail, retry, and delete; guessed document IDs;
cross-workspace member and invitation operations; an admin attempting to
create/promote an admin, alter the owner, or act on another admin; a
member attempting to invoke any administration command; a stale or
concurrent ownership-transfer request; accepting an invitation intended
for a different email; cross-workspace usage access; and cache/projection
leakage across workspaces. Administrative mutation endpoints support safe
repeat submissions (the idempotency pattern already established for retry
above, applied consistently to invitation issuance, membership removal,
and ownership transfer) and return browser-safe typed outcomes, never a
raw internal exception.

### Audit and observability boundary

Phase 19 persists business-audit events for every sensitive administrative
action defined in this document: document retry requested and its result;
document deletion requested and completed/failed; invitation issued,
revoked, expired, and accepted; member removed; role changed; ownership
transferred; and relevant administrative failures. This extends the
existing `DocumentGovernanceAuditEvent` shape (actor, action, before/after
values, timestamp, workspace scope) to membership/ownership/invitation
events specifically — no comprehensive audit table for these currently
exists, confirmed by direct inspection, so this is genuinely new
persistence work, not a wiring exercise against something already built.

Business audit is not operational logging or tracing. **Phase 20 remains
responsible for queue health, service latency, worker throughput, trace
visualisation, infrastructure alerting, and service-level metrics — Phase
19's usage visibility must not rebuild any of that.** Correlation
identifiers follow existing observability conventions (ADR-0012)
unchanged; secrets, raw document text, conversation text, invitation
tokens, and provider payloads are never logged, extending the same
allowlist-first posture this platform already applies everywhere else
(Alternative 11, rejected: exposing operational telemetry inside the
tenant usage page would blur exactly this boundary and hand a tenant
visibility into platform internals that were never meant to be tenant-
facing).

### Failure and concurrency behaviour

Controlled conflicts are never collapsed into generic internal failures.
Named failure categories and their safe user-facing treatment: retry
rejected because state changed (the document is no longer `FAILED` by the
time the row lock is acquired — a safe, typed conflict, not a 500);
deletion already in progress (a second delete request against a document
that already has an open `DocumentDeletionOperation` returns that
operation's status — a structural no-op backed by the document lock and
one-open-operation constraint, not a client-idempotency-key mechanism and
not an error); deletion awaiting quiescence (surfaced as a distinct,
named in-progress state — an active ingestion attempt is still converging
toward cancellation/lease-expiry, not yet safe for vector cleanup, per
"Ingestion quiescence" above); vector cleanup outcomes kept distinct
(authoritative collection-not-found — treated as already-clean; provider
timeout/unavailability — a typed, retryable operational failure, never
treated as evidence of cleanliness; malformed/mismatched scope — a
rejected, non-retryable configuration failure, surfaced loudly); invitation
expired, revoked, or already accepted (three distinct, safely-worded
outcomes, never one generic "invalid invitation"); duplicate membership
(a safe no-op if the invited user already has the intended role, a typed
conflict otherwise); ownership-transfer race (caught by the row-locked
transaction described above); a role-change target being removed
concurrently (re-checked inside the same transactional pattern, fails
safely); usage partially unavailable or cost estimate unavailable (both
rendered as explicitly unavailable, never as zero, per "Provider-reported
usage" above); and stale pagination or read-model data (the document list
is a live, server-scoped read on every request — there is no separate
cached projection to go stale in V1, so this reduces to "read model reads
current state," not a distinct failure mode requiring new machinery).

Authoritative relational transitions — ownership transfer, role changes,
invitation state changes, the retry precondition-check-and-transition —
happen transactionally, with row-level locking where a race is possible.
External cleanup (object storage, vector store) is reconciled
asynchronously, exactly as ADR-0007's own deletion model already
establishes; this document does not weaken that distinction anywhere it
applies it to a new action.

### Explicitly deferred work

Deliberate V1 boundaries, not forgotten work: custom roles and a granular
permission builder (ADR-0006 already defers this); member-created admins;
multiple owners; branching administrative approval workflows; billing,
invoicing, and payment collection; billing-grade cost guarantees;
organisation-wide analytics across unrelated workspaces; Phase 20's
operational dashboards; arbitrary editing of source metadata; document
version editing through the admin list (ADR-0017's governance commands
remain the only path); synchronous multi-system deletion; automatic
unbounded ingestion retry; full cross-conversation document-content purge
(a genuine future compliance capability, distinct from and heavier than
ordinary document deletion — see "Document deletion" above); legal holds
and configurable compliance-retention policies; and invitation-provider-
specific email architecture beyond the proven SMTP seam already
identified.

### Session boundaries

**R19-S01 — Document Administration:** tenant-scoped document list/detail
(new — no listing endpoint exists today); authoritative status/read
projection including extraction warnings (Python populating the already-
validated `warnings` field it currently hardcodes empty; Laravel
persisting it instead of silently dropping it); filters and pagination;
owner/admin retry command with its idempotency-key ledger; owner/admin
asynchronous deletion command, including: `DocumentObjectStorage`'s new
delete method; the new `EvidenceSnapshot` lineage columns
(`source_chunk_public_id`/`source_chunk_ordinal`/
`source_ingestion_event_id`), their existing-data backfill, and the
`document_chunk_id` FK-behaviour migration (`restrictOnDelete()` →
nullable + `nullOnDelete()`) — all sequenced before hard chunk deletion
ships, per "Existing-data migration and backfill ordering"; the ingestion
quiescence barrier (`RenewIngestionLease`/`ClaimDocumentIngestion` status
checks; verifying every orchestrator write step re-validates
lease/status; the new `Cancelled` `IngestionEventClaim` terminal status
and its purpose-scoped `ingestion.attempt.cancel` acknowledgement
endpoint, distinct from `ingestion.fail` and excluded from the ingestion-
failure counter); the new purpose-scoped deletion-orchestration protocol
and `DocumentDeletionOperation` record, created with only an active-
attempt snapshot and finalising its vector-scope set only after
quiescence; Laravel-side vector-scope enumeration from the union of
ingestion-attempt lineage and corpus-generation assignments; Python-side
per-scope `delete()` calls (never `remove_vector_space()`) with
completeness verification distinguishing not-found/unavailable/malformed
outcomes; hard deletion of corpus-assignment pivot rows and content-
bearing `DocumentChunk` rows once quiescence and verified vector cleanup
are both established; deleted-source citation-presentation implications;
audit and negative tenancy tests.

**R19-S02 — Tenant and Membership Administration:** membership list;
the invitation domain (persistence, issue/revoke/accept, expiry,
concurrency) and its supported delivery seam; owner/admin/member
capability enforcement for every action in this document; member
removal; owner-only admin promotion/demotion; atomic ownership transfer;
the bounded-reauthorization-interval permission-revocation behaviour for
open SSE connections; audit and concurrency tests.

**R19-S03 — Usage Visibility:** current resource gauges using the
corrected definitions above (logical uploaded-source-byte totals,
active-corpus-generation-scoped chunk counts); the new content-free
historical activity records (user-submission events, run-outcome events)
written **atomically** at production time in the same transaction as
accepting the `Message`/terminal-transitioning the `GenerationRun` (never
a best-effort follow-up write), backed by no live foreign key to either
and keyed by the workspace-plus-public-identifier-plus-event-kind
idempotency identity defined above — the genuine prerequisite that makes
every query/run/outcome historical metric independent of ADR-0024
conversation deletion, durably crash-safe, and redelivery-safe; historical
usage intervals sourced from those records, not live rows; the new normalised, stage-aware usage-event table(s) and write
paths for both embedding usage (extending the existing `ingestion.complete`
payload, and now also `.fail`/the new `.cancel` acknowledgement, per
"Ingestion usage on failed and cancelled attempts") and generation usage
(covering every `GenerationRun` terminal outcome, keyed to
`generation_run_public_id` scalars, never a live FK); a wholly new
generation pricing mechanism (Python-side local rate computation plus a
versioned pricing-snapshot, mirroring but not extending the embedding
pattern, since generation currently has no cost figure in any form);
bounded aggregation; freshness and partial-data representation;
owner/admin access; tenant-isolation tests.

This allocation matches `tasks.json`'s existing R19-S01/S02/S03 titles and
scope exactly — no session responsibility is moved without explanation,
and none was needed here.

## Architectural invariants

- The workspace may never become ownerless; the existing at-most-one-owner
  database constraint (non-deferrable) is reinforced, not replaced, by
  every ownership-changing code path routing through one guarded,
  demote-then-promote transfer action.
- Only the owner may create, promote, demote, or remove an admin, or
  transfer ownership; an admin has no path — direct or via invitation — to
  perform any of these.
- Document retry and deletion are owner/admin-only, explicitly narrowing
  ADR-0007's unqualified "workspace member" wording for this
  administrative surface; ADR-0007's lifecycle model itself is unchanged
  and reused.
- `Document` and `IngestionEventClaim` rows are never hard-deleted —
  `Document` transitions via the existing soft `DELETING → DELETED`
  status change; `IngestionEventClaim` is content-free and simply
  retained. `DocumentChunk` rows belonging to a deleted document **are**
  physically deleted, and canonical `DocumentChunk` immutability is left
  completely untouched — no sanitise-in-place exception exists anywhere
  in this design.
- `EvidenceSnapshot.document_chunk_id` is nullable with `nullOnDelete()`;
  `.document_id` and `.ingestion_event_claim_id` remain live,
  `restrictOnDelete()`, always-resolving foreign keys, since neither
  referenced row is ever hard-deleted. An accepted `EvidenceSnapshot` is
  never cascade-deleted, orphaned, or otherwise affected by its source
  Document's or chunk's deletion — its own immutable `cited_text_verbatim`,
  `content_digest`, `source_provenance`, and the snapshot-owned
  `source_chunk_public_id`/`source_chunk_ordinal`/
  `source_ingestion_event_id` fields make citation inspection fully
  independent of any live `DocumentChunk` row.
- Document deletion never proceeds to vector cleanup, chunk removal, or
  `DELETED` while any ingestion attempt claimed before deletion began
  could still write a point — new claims and lease renewals are refused
  once `DELETING`, and cleanup waits for every such attempt to reach a
  terminal state (`Cancelled` or `Failed`) or lease expiry first.
- Deletion-cancellation is never reported through `FailIngestionAttempt`
  and never increments the ingestion-failure counter — a dedicated
  `Cancelled` terminal status, reached only through a purpose-scoped
  acknowledgement, keeps deletion-driven cancellation structurally
  distinct from a genuine processing failure. `Document.status` stays
  `DELETING` throughout an attempt's cancellation; only the claim's own
  status changes.
- A `DocumentDeletionOperation`'s final vector-scope set is enumerated
  and durably recorded only after quiescence is established, never at
  operation-creation time — the operation carries only a snapshot of the
  active-attempt identities to wait on until quiescence holds.
- Vector cleanup for a deleted document is enumerated by Laravel from the
  union of every `IngestionEventClaim`'s generation lineage and every
  corpus-generation pivot assignment for the document — not corpus
  assignments alone — covering both provisional and published points;
  `remove_vector_space()` is never used to delete a single document's
  points; an unreachable vector collection is never treated as proof of
  its own emptiness.
- Retry uses a client-supplied idempotency key, because the same document
  may legitimately fail and be retried again later. Deletion uses one
  durable operation per document instead, because deletion is a terminal
  intent, not a repeatable one; repeating either command safely returns
  the existing attempt/operation rather than creating a second one.
- Usage already incurred by an ingestion attempt is retained as historical
  activity even when that attempt subsequently fails or is cancelled by
  document deletion; only usage that was never reported by the provider
  in the first place is treated as unavailable, never as zero.
- Phase 19's historical activity/usage records hold no live, constrained
  foreign key to `Message`, `GenerationRun`, or `Conversation`, and carry
  no conversation content — ADR-0024 conversation deletion is never
  blocked by, and never cascades into, these records; historical counts
  and cost never decrease because a conversation was later deleted.
- Every content-free historical activity/usage event is written
  atomically with the domain event it represents — the same database
  transaction, never a best-effort step afterward — or, where a genuine
  external boundary makes that impossible, via a transactionally-committed
  outbox/reconciliation record; no crash window may silently lose an
  accepted user submission, run outcome, or usage report. Every such
  event carries a deterministic idempotency identity (workspace plus
  source public identifier plus event kind), enforced by a plain unique
  index independent of any foreign key, so redelivery or reconciliation
  never inflates a historical count.
- An invitation can never grant `OWNER`; ownership is acquired only
  through explicit transfer.
- At most one active invitation exists per workspace/normalized-email
  pair at any time, including effectively-expired-but-not-yet-materialised
  rows, which reissue always closes transactionally before creating a new
  one.
- A raw invitation token is returned at most once, in the creation or
  reissue response; it is never durably recoverable afterward, and
  obtaining a new link always means reissuing.
- Invitation validity is never contingent on confirmed email delivery.
- Current-state usage gauges and historical interval metrics are never
  presented as one undifferentiated total, and historical totals never
  change retroactively because a resource was later deleted.
- Unknown or unavailable usage/cost figures are never rendered as zero.
- A usage event never collapses two stages, providers, or models with
  different pricing/cost-basis identity into one ambiguous row; batches or
  calls are aggregated into one row only when their provider, model,
  pricing snapshot, and cost basis are identical.
- No monetary figure is labelled provider-reported cost unless a provider
  itself returned that figure; every cost value in this system today is,
  and remains until that changes, an application-calculated estimate.
- Estimated cost is always labelled as an estimate, tied to a versioned
  pricing snapshot, and never silently recalculated under different prices
  without that being labelled.
- No usage query may allow one tenant to infer another tenant's activity,
  usage, or cost.
- Business audit for administrative actions is persisted; it is not, and
  does not become, Phase 20's operational telemetry.
- Every administrative action is checked against current authoritative
  membership state; already-open sessions/streams are re-checked within a
  bounded interval, never trusted indefinitely.

## Alternatives considered

### Permitting all members to retry/delete documents

Rejected. Evaluated in full under "Roles and administrative authority"
above — delete and retry are meaningfully higher blast-radius than the
collaborative upload/query actions that remain open to all members.

### Permitting admins to create other admins

Rejected. Concentrating admin-creation authority in the owner alone keeps
exactly one accountable actor for the workspace's most consequential role
changes, with no second route to elevation — the smallest rule that
prevents an admin from unilaterally reshaping the workspace's own
governance.

### Deleting `EvidenceSnapshot`s whenever their source Document is deleted

Rejected. Evaluated in full under "Document deletion" above — would
silently break historical answers and directly contradicts why
`EvidenceSnapshot` stores verbatim text. (Corrected from an earlier
draft: this is rejected on citation-integrity grounds, not because a
`RESTRICT` constraint would prevent it — `RESTRICT` on `EvidenceSnapshot`'s
own foreign keys has no bearing on deleting `EvidenceSnapshot` rows
themselves.)

### Retaining deleted source/chunks indefinitely to preserve citations

Rejected — including the sanitise-in-place chunk-tombstone variant of this
alternative that an earlier revision of this document chose and this
revision withdraws (see "Document deletion" above for the full
reasoning): retaining chunk rows in any form, full content or
content-sanitised, is unnecessary duplication once `EvidenceSnapshot`
carries its own immutable citation text and structural lineage
independently, and the tombstone variant specifically introduced
incoherent semantics (`content_digest`/`token_count` attached to text
they no longer describe, a canonical row with two incompatible meanings)
without buying anything `EvidenceSnapshot` didn't already provide. The
chosen design physically deletes content-bearing `DocumentChunk` rows,
leaving canonical chunk immutability untouched.

### Allowing ownership transfer to demote the former owner to an ordinary member

Rejected. Evaluated under "Ownership transfer" above — a surprising,
punitive side effect with no demonstrated need; `ADMIN` is the correct,
unsurprising landing state for someone who just handed over ownership.

### Treating invitation issuance and email delivery as one operation

Rejected. Evaluated under "Invitation domain" above — would make invitation
validity falsely contingent on a delivery mechanism this platform cannot
always confirm, and would produce false "email sent" claims exactly where
delivery infrastructure is unconfigured or fails.

### Allowing multiple active invitation tokens for the same workspace/email

Rejected. Multiple simultaneously valid tokens for one invitee create
ambiguity about which link is authoritative and complicate revocation
(revoking one wouldn't invalidate the others) for no benefit over the
reissue-revokes-the-prior-one rule this document adopts instead.

### Recomputing all historical cost using current prices

Rejected. Evaluated under "Provider-reported usage and estimated cost"
above — would make a historical total misrepresent what actually
happened at the time it happened.

### One undifferentiated "usage" total

Rejected. Evaluated under "Usage visibility" above — collapses
current-state gauges and historical activity into a number that is
honestly interpretable as neither.

### Building a full materialised analytics subsystem immediately

Rejected for V1. Evaluated under "Aggregation, performance and freshness"
above — no evidence yet that bounded, indexed relational aggregation is
insufficient at this repository's current scale.

### Exposing operational telemetry inside the tenant usage page

Rejected. Evaluated under "Audit and observability boundary" above —
blurs the business-audit/operational-telemetry boundary and exposes
platform internals never meant to be tenant-facing.

### Performing document deletion synchronously

Rejected. Would require blocking a request on S3, Qdrant, and relational
cleanup all completing together, reintroducing exactly the partial-
failure-is-invisible problem ADR-0007 already rejected synchronous
deletion for; this document reuses ADR-0007's asynchronous model rather
than reopening that already-settled question.

## Consequences

### Positive

- Every genuinely new administrative capability (retry, delete,
  invitations, ownership transfer, usage visibility) reuses an existing,
  proven lower-level primitive — the outbox pattern, the already-built
  Qdrant cleanup methods, the proven SMTP transport, the embedding
  pricing-snapshot pattern, the partial-unique-index idiom, the ingestion-
  worker HMAC completion-report shape — rather than inventing parallel
  mechanisms.
- The ADR-0007/ADR-0023 tension around document deletion and historical
  citations is resolved with a concrete, schema-verified persistence
  design — `EvidenceSnapshot` made genuinely independent of its source
  chunk via new immutable snapshot-owned lineage fields, allowing
  content-bearing `DocumentChunk` rows to be physically deleted with
  canonical chunk immutability left completely untouched — not by
  assuming a `RESTRICT` constraint already solved it, and not by
  weakening an existing invariant to route around it.
- The capability matrix and business-audit extension give this platform
  its first real owner/admin/member authorization distinction anywhere in
  the codebase, on the two actions (retry, delete) that most need it.
- Naming genuine data-availability gaps precisely — extraction warnings
  (small, now-scoped wiring work), embedding and generation usage (real
  new normalised persistence), generation pricing (a wholly new local
  pricing mechanism, not an extension of an existing fallback) — means
  R19-S01/S03 inherit accurate scoping instead of discovering these gaps
  mid-implementation.

### Negative

- The permission-revocation guarantee for already-open SSE connections is
  honestly weaker than instant — a bounded-interval re-check, not a push-
  based termination — a real, named limitation of the current transport,
  not a solved problem.
- The at-least-one-owner invariant depends on every ownership-changing
  code path routing through the same guarded transfer action, plus
  transaction atomicity and a specific demote-then-promote statement
  order forced by the owner index being non-deferrable; the database only
  backstops the at-most-one half. A future defense-in-depth improvement
  (a deferred constraint or trigger) is not designed here because nothing
  yet demonstrates the current approach is insufficient.
- Document deletion now requires real new machinery this document
  specifies but does not implement: new `EvidenceSnapshot` lineage columns
  plus an expand-backfill-contract migration sequence for existing rows;
  an ingestion quiescence barrier spanning `RenewIngestionLease`,
  `ClaimDocumentIngestion`, and every orchestrator write step that must
  re-validate lease/status; a new `Cancelled` ingestion-attempt terminal
  status and its own purpose-scoped acknowledgement endpoint, kept
  distinct from `ingestion.fail` specifically so administrative deletion
  never pollutes the ingestion-failure metric; a new purpose-scoped
  Laravel/Python deletion-orchestration protocol whose scope-enumeration
  step is sequenced strictly after quiescence, not at operation creation;
  and Laravel-side relational enumeration of every vector scope from both
  ingestion-attempt lineage and corpus-generation assignments. This is
  substantially more implementation surface than any prior draft implied
  — deletion is, by a wide margin, the largest single piece of new
  machinery this document specifies, in exchange for actually being
  schema-consistent and race-free.
- Extending business audit beyond its current document-governance-only
  scope, building the invitation domain, and adding embedding/generation
  usage persistence are all real, non-trivial new schema and write-path
  work, not thin wrappers around existing tables — and the usage-event
  work in particular now requires normalised, stage-aware, indexable
  storage (one row per run-and-operation-kind, not one row per run,
  reported across complete/fail/cancel alike) rather than JSON, which is
  a larger commitment than any prior draft's framing suggested.
- Historical Phase 19 metrics now require a second, parallel set of
  content-free activity records (user-submission events, run-outcome
  events) written alongside `Message`/`GenerationRun` at production time,
  specifically so ADR-0024 conversation deletion never has to interact
  with Phase 19's tables at all. This is genuinely new write-path work on
  every message/run production path, not a reporting-side addition —
  R19-S03 cannot be built as a read-only layer over existing tables the
  way earlier framing implied.
- Retry and deletion both require a durable idempotency/operation-identity
  mechanism this document specifies but the first draft omitted — retry
  via a client-supplied key, deletion via a single durable operation per
  document — two different mechanisms for two different reasons, not one
  uniform pattern, which is itself a small additional concept to keep
  straight during implementation.

## Scope boundaries

This document does not define: exact Laravel controller/action/migration
class names beyond what's structurally required to state each decision;
exact frontend component structure, routing, or visual design (confirmed
fully greenfield — no admin/settings/members/usage page skeleton exists
anywhere in `apps/web` today); exact SQL for any aggregation query; exact
numeric values for page sizes, reauthorization intervals, or invitation
expiry beyond the stated seven-day default; the generation pricing
snapshot's actual rate values; a general permission/role engine beyond the
fixed three roles; billing, invoicing, or payment collection; Phase 20's
operational-telemetry architecture; or a full cross-conversation
compliance-purge capability. It does not redecide anything ADR-0006,
ADR-0007, ADR-0008, ADR-0012, ADR-0015, ADR-0016, ADR-0017, ADR-0023, or
ADR-0024 already settled. `tasks.json`, `PROJECT_ROADMAP.md`,
`IMPLEMENTATION_GUIDE.md`, and the ADR index are not architectural content
of this document; they are reconciled separately as part of its acceptance
review.
