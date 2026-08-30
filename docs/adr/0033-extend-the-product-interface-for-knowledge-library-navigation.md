# ADR 0033: Extend the Product Interface for Knowledge Library Navigation

## Status

Accepted

## Date

2026-08-28

## Relationship to prior ADRs

ADR-0027 establishes the `AppShell`'s stable/contextual navigation model,
the semantic-token/theme architecture, the chat evidence/citation
presentation contract (including `source_route` as the canonical field
naming `/app/workspaces/{workspace}/documents/{document}` as a citation's
authorised source destination), the shadcn component system, and the WCAG
2.2 AA baseline. ADR-0028 establishes the precedent this ADR follows: a
growing product section gains its own route-backed contextual region
inside the shell's existing contextual slot, verified directly against
`apps/web/src/components/AppShell.tsx`, which already implements this for
Administration and Platform Operations but not yet for Documents.

**This ADR preserves `/app/workspaces/{workspace}/documents/{document}`
exactly as ADR-0027 already fixed it** — no reinterpretation of that
route segment as a family identity is made anywhere in this document.
Every existing citation `source_route`, bookmark, and live route continues
to resolve unchanged.

This ADR consumes ADR-0030 (metadata classification, checksum state),
ADR-0031 (governance actions, clone mechanism, family deletion), and
ADR-0032 (canonical extraction artefact, projection, source delivery) as
frozen Tier 1 (`TIER_1_READY_TO_FREEZE`) without reopening any of their
decisions. It does not redecide ADR-0017's authority/applicability rules,
ADR-0035's bulk-operation semantics, ADR-0036's notification/activity-feed
architecture, or ADR-0037's export design.

## Context

Verified directly against the current repository: `DocumentAdministration.tsx`
is a 40-line card list (filename as title, one search/status filter,
retry/delete buttons, no selection, no version/family concept); no
`DataTable`-style component exists in `apps/web/src/components/ui`; no
`SavedView` or `DocumentCategory` domain exists in `apps/api`; `EmptyState`
already exists and is reusable; `GetWorkspaceUsage.php`, read in full,
counts every technical-status-active document (including `DRAFT`-
governance and `failed`) with no governance or applicability filtering and
also returns cost/token data, so it cannot back a member-safe searchable
count; `WorkspaceMembership` carries no location field, and ADR-0018's own
resolution flow states that an undirected query applies no location
narrowing at all — there is no per-member location-assignment concept
anywhere in the accepted architecture. This revision responds to Codex's
`TIER_2_BLOCKED_PENDING_CORRECTION` finding that the previous drafts left
several load-bearing contracts (the library row's exact field sources, the
`SavedView` schema, comparison's family-membership validation, and the
onboarding/visual-acceptance rules) as abbreviated references to earlier,
now-unavailable drafting history rather than complete, self-contained
statements.

## Decision

### Route hierarchy

```
/documents                                          — library (family rows)
/documents/families/{familyPublicId}                — family detail
/documents/families/{familyPublicId}/versions        — version history
/documents/families/{familyPublicId}/compare
    ?from={documentPublicId}&to={documentPublicId}   — comparison
/documents/{documentPublicId}                        — version detail/source,
                                                        exactly as ADR-0027
                                                        already fixes it
/documents/scheduled
/documents/attention
/documents/saved/{savedViewPublicId}
/documents/settings/categories
/documents/imports        — appears once ADR-0034's read model exists
/documents/deleted        — appears once ADR-0031's family-deletion
                             tombstone data exists (R23-S02c)
/documents/export         — appears once ADR-0037 exists
```

Every route is authorised inside the workspace boundary, refresh-safe,
deep-linkable, back/forward-safe, and consistently concealed for an
invalid, deleted, or cross-workspace identifier of either kind (family or
version), with its own concealment test per identifier kind. Navigation
activation is segment-aware and route-prefix-safe (matching on parsed
route segments, whatever mechanism Next.js's routing APIs provide at
implementation time) — a path merely containing the substring `documents`
never activates the Documents contextual region, at most one contextual
destination is ever active, and every dynamic route above resolves to the
correct active item.

### Library table — complete read-model contract

**The canonical row is exactly one row per `DocumentFamily`.**

**Family-owned fields**, sourced from `DocumentFamily`/ADR-0030 and
**never** from any individual version row:

| Field | Source | Notes |
|---|---|---|
| Human title | `DocumentFamily` (backing `document_families.name`, ADR-0030) | Primary display identity |
| Description | `DocumentFamily` | Shown in expanded/detail context only |
| Category | `DocumentFamily.category_id` | Omitted, not blank, when none |
| Tags | Family-tag assignment (ADR-0030) | Up to 20, omitted when none |
| Owner | `DocumentFamily.owner_user_id` | Displays "Needs reassignment" using ADR-0030's derived eligibility, never a blank |
| Review-due date | `DocumentFamily` | Omitted when unset |

**These six fields are read from the family row exactly once per row** —
never derived by reading them off whichever version happens to be current,
which would silently break the moment a family's editable metadata and its
current version's identity diverge (for example, immediately after an
applicability-only clone creates a new current version while the family's
own title/description/category/tags/owner/review-date are untouched).

**Version-derived fields**, sourced from the family's **currently
authoritative version**, resolved by ADR-0017's own `CURRENT` derivation —
never a bare "most recently created version" query, and never a stored
flag:

| Field | Source | Notes |
|---|---|---|
| Technical status | `Document.status` | ADR-0007's lifecycle |
| Source filename | `Document.source_filename` | Immutable |
| Media type / size | `Document` | Immutable |
| Checksum / verification status | `Document.source_checksum_sha256` / `checksum_verification_status` | ADR-0030 |
| Governance status / effective dates | `Document.governance_status`, `effective_from`, `approved_at`, `withdrawn_at` | ADR-0017 |
| Current-authority badge | Derived from ADR-0017's `CURRENT` derivation applied at read time | Never a stored boolean |
| Scheduled-state summary | A bounded existence check against approved, not-yet-attained versions in the family | See below |
| Extraction warning/failure state | `Document`'s existing warning/failure fields (ADR-0007), plus ADR-0032's extraction-warning count for the current version | |

**Deterministic display behaviour, exhaustively enumerated:**

1. **A family with a current authoritative version**: the version-derived
   fields above are populated from that version; the current-authority
   badge reads "Current."
2. **A family with no current version but one or more scheduled, approved,
   not-yet-attained versions**: the row shows "No current version —
   scheduled [date]" (naming the earliest not-yet-attained scheduled
   version's effective date); version-derived technical/source fields are
   **left absent, never borrowed from the scheduled version** — a
   scheduled version must never silently present its own technical/source
   facts as though they already describe the family's current state before
   it genuinely attains authority.
3. **A family containing drafts but no current version**: the row shows
   "Draft awaiting approval" — a `DRAFT` version is never presented as
   current merely because it is the newest row; version-derived fields
   remain absent.
4. **A family with withdrawn/historical versions only** (every version has
   attained and then lost authority, none currently holds it): the row
   shows "No current version" with the most recent withdrawal date, per
   ADR-0017's own "this is a real state, not a bug" language — version-
   derived fields remain absent.
5. **A newly created family/version still processing** (technical status
   before `INDEXED`): the row shows the real technical status
   (`UPLOADING`/`UPLOADED`/`QUEUED`/`PROCESSING`) and no current-authority
   badge, since a version cannot attain governance authority before it is
   indexed and approved.
6. **A deleted/tombstoned family**: **does not appear in the ordinary
   library table at all** — it is excluded by the base query's own
   predicate (below), never filtered client-side.

**Last meaningful update — exact, closed definition.** Derived from an
explicit allowlist of event types, the **latest** of:

- A family metadata mutation (title, description, category, tags, owner,
  review date — ADR-0030's audit mechanism).
- A version creation.
- A governance transition (approve, withdraw, reschedule, timestamp
  correction — ADR-0017/0031).
- A technical-state transition reaching `INDEXED` or `FAILED`.
- An applicability-only successor (clone) creation — ADR-0031.
- A new extraction warning recorded against the current version.

**Explicitly excluded**: viewing, searching, pagination, any audit event
unrelated to the allowlist above, and notification delivery (ADR-0036) —
none of these are "the document changed."

**`document_family_activity_summary` — complete operational lifecycle**,
one row per family, storing `family_id` (unique) and
`last_meaningful_update` (the single latest allowlisted-event timestamp):

- **Authoritative durable inputs**: the projection is always rebuildable,
  from scratch, purely from the timestamps on the authoritative rows and
  append-only events that prove each allowlisted change:
  family-scoped `document_governance_audit_events.occurred_at` rows with
  ADR-0030's allowlisted metadata-mutation actions for family metadata;
  `documents.created_at` for version creation;
  `document_governance_audit_events.occurred_at` for governance changes;
  `ingestion_audit_events.occurred_at` for the allowlisted terminal
  `publication_completed → INDEXED` and `processing_failed → FAILED`
  transitions; the durable ADR-0031 clone-operation/audit timestamp for
  clone creation; and the durable ADR-0032 artefact/publication timestamp
  that records a persisted extraction warning. For an extraction warning,
  the allowlist predicate is
  evaluated against the warned version's authority window at the
  warning's timestamp, so a later change of current version cannot alter
  historical rebuild output. No generic `documents.updated_at`, mutable
  current-state timestamp, view/search event, or notification record is
  an input. **The projection itself never becomes an independent source
  of truth for governance or technical state** — it stores one derived
  timestamp, nothing else.
- **Deployment and backfill for existing families**: schema and live
  same-transaction maintenance deploy first. A resumable, bounded
  backfill then visits every existing family in stable primary-key order,
  locks that family row, and upserts its initial summary from the latest
  qualifying timestamp across **all of the family's versions and durable
  events**, not merely whichever version is current when the backfill
  runs. A family with no qualifying event beyond its own creation receives
  `document_families.created_at`. Families created while the backfill is
  running already receive live-maintained rows; revisiting one is safe.
- **Missing-row behaviour**: the library query joins to this projection
  with a **`LEFT JOIN`**, never an inner join — a family with no summary
  row (a backfill gap, or a family created between deploy and backfill
  completion) still appears in the library, never hidden by a missing
  projection row.
- **Deterministic fallback ordering** when a summary row is absent: the
  family's own `created_at` is used in its place for sort purposes — a
  real, already-durable timestamp, never `NULL` (which would sort
  unpredictably depending on the database's null-ordering default) and
  never a fabricated "now."
- **Reconciliation**: a standalone, idempotent rebuild job recomputes a
  family's exact value using the same backfill computation above and is
  safe to run at any time, for any subset of families, as a defence-in-
  depth path for a damaged, missing, lagging, or erroneously-ahead row.
  Under the family-row lock shared with every live producer, it replaces
  the projection value with the exact recomputed value (or inserts the
  missing row); it does **not** use the live path's monotonic `GREATEST`
  update, because that could never repair an incorrectly-future value.
  This is not the primary maintenance mechanism, since the primary
  mechanism (below) makes drift structurally unlikely.
- **Primary maintenance discipline, verified against how every allowlisted
  event actually reaches Laravel**: every one of the six allowlisted event
  types — including the technical `INDEXED`/`FAILED` transition and a new
  extraction warning — is, in this platform's existing architecture,
  processed inside a **Laravel-owned database transaction**: metadata and
  governance events are Laravel-initiated directly; a technical status
  transition and an extraction warning are recorded by Laravel while
  processing Python's ingestion-worker completion/failure callback, which
  Laravel already handles transactionally (ADR-0015/0016). **Because every
  producer already commits inside a Laravel transaction, same-transaction
  projection maintenance is used uniformly for all six event types** — an
  `UPSERT` (`INSERT ... ON CONFLICT (family_id) DO UPDATE`) is issued in
  the same transaction as the authoritative event it summarises. **No
  outbox or asynchronous reconciliation path is required for correctness**
  — the standalone rebuild job above exists only as defence-in-depth, not
  because same-transaction maintenance is insufficient for any producer.
  Each producer first locks the affected `DocumentFamily` row and then
  writes its authoritative event and projection update in the same
  transaction. This common family-row lock is also taken by backfill and
  reconciliation, preventing an exact rebuild from racing and overwriting
  a newly committed live event.
- **Concurrent-update safety, without losing or regressing a timestamp**:
  the `UPSERT`'s conflict clause sets
  `last_meaningful_update = GREATEST(document_family_activity_summary.last_meaningful_update, EXCLUDED.last_meaningful_update)`
  — two concurrent or out-of-order writes for the same family can never
  regress the stored value backward; the row always reflects the latest
  genuinely-recorded event, regardless of commit order.
- **Rebuild idempotency**: because both the backfill and reconciliation
  job compute the same exact maximum over the closed input mapping above,
  running either any number of times against unchanged underlying data
  always produces the same result. A crash commits either one complete
  family repair or none; the stable cursor resumes safely at that family.

**Required tests**: backfill correctness against a family with real,
pre-existing audit history; the `LEFT JOIN`/fallback-ordering behaviour for
a family with no summary row; rebuild idempotency (run twice, assert
identical output); concurrent/out-of-order `UPSERT`s never regressing the
stored timestamp; and one test per allowlisted producer (metadata
mutation, version creation, governance transition, technical `INDEXED`/
`FAILED`, clone creation, extraction warning) confirming it updates the
projection in the same transaction as its own authoritative write; an
exact rebuild correcting both a missing/lagging value and an erroneously-
future value; and a rebuild racing a live producer, proving the shared
family-row lock preserves the newer authoritative event.

This is genuine new Laravel work, allocated to R24-S02 below.

**Pagination and ordering:**

- **Page-based**, matching this repository's established API style
  (verified: `DocumentPage`/`workspaceDocuments` already use
  `page`/`per_page` request parameters and `meta.current_page`/
  `last_page` response shape — this ADR's library table reuses that exact
  convention, not cursor pagination).
- **Sort keys**: title, last-meaningful-update, review-due date — each
  ascending or descending.
- **Stable tie-breaker**: the family's own `public_id`, appended to every
  sort as a final ascending key, so two families sharing an identical
  sort-key value never produce nondeterministic ordering across pages.
- **Page sizes**: 25, 50, 100.
- **An item changing while paging**: because pages are offset-based
  (matching the existing convention) rather than cursor-based, a row
  inserted or resorted between page loads may shift another row across a
  page boundary — the existing, accepted behaviour this repository's
  pagination convention already has everywhere else, not a new risk this
  ADR introduces.
- **URL filter parameters are bounded and versioned**: an explicit,
  enumerated query-parameter set (`search`, `status`, `category`,
  `applicability`, `owner`, `review_status`, `sort`, `direction`, `page`,
  `per_page`, `historical`), each validated against a known value set on
  read.
- **Invalid or unsupported parameter values are dropped with the affected
  filter reset to its default, never a 500 or a silently wrong result** —
  the same honest-degradation principle this ADR already applies to saved
  views.

**Query shape — a bounded plan, not an N-query loop:**

1. One base query against `document_families`, filtered to the workspace,
   **excluding** any family with an open family-deletion tombstone
   (ADR-0031), **`LEFT JOIN`ed** to `document_family_activity_summary`
   (falling back to the family's own `created_at` where the join finds no
   row, per "Last meaningful update" above) for sorting.
2. **Current-authority selection** through one deterministic subquery/join
   per page (not per row) resolving each returned family's current
   version, expressed as a single SQL construct (a lateral join or
   equivalent) evaluated once for the whole page's family set — never one
   query per family row.
3. **Aggregate/existence subqueries** for scheduled-version presence,
   draft-only presence, and warning/failure counts — each a single,
   page-scoped aggregate, not per-row.
4. **Eager loading** for category, owner, and tags — a single additional
   query per relation for the whole page (Laravel's standard eager-load
   shape), never per row.
5. **No per-row lineage, warning, tag, owner, or location query of any
   kind.**
6. **Required acceptance evidence**: an explicit query-count assertion at
   the 100-row page size, proving the total query count for a fully
   rendered page is bounded and independent of row count — allocated to
   R24-S02's own test suite, not left unverified.

**Historical inclusion** is a filter/presentation mode, never a row-
expansion: with it enabled, the base query's current-authority-only
predicate is relaxed to also match families with no current version but
retained historical versions, **still returning exactly one row per
family** (annotated with a "historical" indicator and a version count),
routing into the family's own version-history page for the actual list of
versions — never one top-level row per version.

### Search and organisational filtering

Category, tags, and title/filename search exist only for library
organisation and filtering — never altering embeddings, retrieval ranking,
or evidence eligibility, and never silently entering a chat query.
Applicability remains a separate, location-based ADR-0017 eligibility
fact, resolved independently of anything this filtering surface does. V1
has organisational locations only, no departments or teams; the future
second-applicability-axis seam ADR-0017 already reserves is preserved,
not built. "Applicability is not confidentiality" appears verbatim in
every user-facing explanation this ADR specifies.

### Saved views — complete Laravel-owned domain

**`SavedView`** schema:

| Field | Type/constraint | Notes |
|---|---|---|
| `public_id` | UUID, unique | Browser-facing identity — `savedViewPublicId` in every route/API reference, matching this repository's established public-identity convention |
| `workspace_id` | FK, `restrictOnDelete()` | Composite-scoped with every other field below |
| `user_id` | FK to `users.id`, `restrictOnDelete()`, reconciled directly against the real schema — a saved view has no `WorkspaceMembership`-row reference, since (per ADR-0030's own owner-identity precedent) the durable identity is the `User`, not the membership row, so a view survives a membership's later end exactly long enough for its own cleanup rule (below) to act on it, never orphaned by a dangling membership FK | |
| `name` | Bounded string, max 100 characters, plus a normalized form (NFC, trim, whitespace-collapse, case-fold) for uniqueness comparison, mirroring ADR-0030's category/tag normalization rule exactly | **Unique on `(workspace_id, user_id, normalized_name)`** — a user cannot have two saved views that are the "same" name modulo case/whitespace |
| `definition_schema_version` | Integer | Versioned, so a later schema change can be detected and degraded honestly (below) |
| `definition` | JSON, canonical/versioned, max bounded size (a configured cap, R24 implementation measurement) | See allowed keys below |
| `created_at` / `updated_at` | Timestamps | |

**`definition`'s allowed V1 keys**: `search` (bounded string), `filters`
(an object restricted to the same enumerated filter keys the library
table's own URL parameters use — category, applicability, owner,
review_status, status), `sort` (one of the library table's supported sort
keys) with `direction`, `page_size` (25/50/100), and `historical`
(boolean). **An unknown key or an operator outside this enumerated set is
rejected outright on write** — never silently stored and silently ignored
on read.

**Behaviour when a previously valid field/value becomes unsupported
later** (a removed filter key, a sort key no longer offered): **dropped on
open, with a visible notice** naming what was removed — never silently
reinterpreted, never a crash.

**Archived category / retired location referenced by a saved filter**:
continues to filter correctly — both remain valid historical values under
ADR-0030/ADR-0017 — never treated as "unsupported."

**Rename and delete**: authorised to the owning user only — no
`owner`/`admin` override in V1, since a saved view carries no shared or
governance significance for anyone but its owner.

**Concealment**: a saved view belonging to another user in the same
workspace, or to another workspace entirely, or an invalid identifier,
resolves through the same not-found response every other route in this
codebase already uses — one concealment test for cross-user, one for
cross-workspace.

**Cleanup**: removed automatically when the owning membership ends (a
saved view has no provenance value worth preserving once its owner can no
longer use it — unlike ADR-0030's document-owner field, which is
preserved for provenance/audit reasons a saved view has none of). If the
underlying `User` account is disabled rather than removed from the
workspace, the saved view is retained but inert (not resolvable through
the user's own now-inaccessible session) until membership formally ends.

**Audit**: create/rename/delete are recorded under the same audited-
action discipline as every other user-initiated mutation in this
decomposition, using safe identities only.

**No governance weight of any kind** — a saved view cannot grant, imply,
or substitute for any authorization, and **stores no frozen result set** —
it is re-evaluated against live data every time it is opened, the
deliberate opposite of ADR-0035's frozen bulk-operation membership.

**Future ADR-0037 seam**: exportable as user-owned portable configuration,
keyed by the owner's stable public identity — not designed further here.

### Category settings

A route-backed Library settings surface (`/documents/settings/categories`)
supporting ADR-0030's full lifecycle: create, rename (audited), archive
(never hard-delete while referenced, reusing ADR-0030's
`OrganisationalLocation`-derived safe-deletion rule), archived categories
remaining visible on existing families while excluded from new-assignment
pickers. Not placed in Platform Operations. Tags remain freeform with no
equivalent settings surface.

### Truthful searchable-document count

A compact searchable-readiness indicator shows **consistently**, never
hidden once a tenant has "enough." Zero searchable documents: an honest
explanation that documents must be approved and prepared before Dolved can
answer from them. One or more: the exact, truthful count, always.

**Verified, decisive finding this count's definition is built on**: no
accepted ADR or existing schema defines a per-user or per-membership
assignment to one or more organisational locations, and ADR-0018's own
resolution flow states that an undirected query applies **no** location
narrowing at all. The count therefore mirrors that exactly: distinct
`DocumentFamily` rows (never one row per applicability-location join —
proven by `COUNT(DISTINCT family)`, never a join-row count) whose current
version (ADR-0017's `CURRENT` derivation) is technically `INDEXED`,
governance-`APPROVED`-and-current, and not `DELETING`/`DELETED`/`FAILED` —
with **no applicability-based exclusion**, because that is exactly what
undirected eligibility already means under ADR-0018.

This count is named "currently searchable within your workspace's
knowledge base," never a per-question promise: a question naming one
specific location narrows the actually-retrieved set to a real subset of
this baseline, resolved at query time by ADR-0018's unmodified
`EligibilityResolver`, independent of this count.

A new, member-safe Laravel query answers this directly over the same
accepted `DocumentFamily`/`Document`/`OrganisationalLocation`/
applicability-snapshot tables and the same `CURRENT`-derivation rule
ADR-0017 fixes — never a call to `EligibilityResolver` itself (which
answers a query-plan-shaped question, not a bare aggregate), never a
retrieval call, never Python, never a provider call. The endpoint is
available to any authenticated workspace member and returns only the
count — no cost, token, byte, or administration-only figure.

**Required tests**: a `UNIVERSAL` family is counted; a family applicable
to one specific location is counted; a family whose current version names
several locations at once is counted exactly once; a family applicable to
a parent region is counted the same way; cross-workspace concealment; a
`DRAFT`/`WITHDRAWN`-only/`FAILED`/`DELETED` family is excluded.

### Starter questions

Optional, deterministic, template-based suggestions drawn only from the
titles of currently searchable families (the same definition above) — e.g.
"What are the key points in {title}?" — filled from a real title, never
invented. No LLM or provider call is made to produce one. No factual
assertion about a document's contents. No prompt is shown for a document
that is not currently searchable. If no safe title-based suggestion can be
produced, starter questions are omitted entirely.

### Deleted/history — ownership

ADR-0031 owns family-deletion tombstone semantics and persistence (its
own R23-S02c session). This ADR owns the `/documents/deleted` route and
presentation contract, once that tombstone data exists — an owner/admin-
only view listing family tombstones with recorded deletion reason/date and
audit reference. ADR-0036 may contribute activity-feed events referencing
a deletion, but owns neither the tombstone data nor this route.

### Family/version detail — complete behaviour

**Family detail** (`/documents/families/{family}`): family metadata
(title, description, category, tags, owner, review date), editable inline
per ADR-0031's API where authorised; the current authoritative version,
visually distinct from every other listed version by governance state, not
a cosmetic highlight; every historical, draft, scheduled, and withdrawn
version with its own truthful status and dates and no added general prose
explanation for non-current status; applicability, source metadata
(publisher/label/validated URL, ADR-0030), extraction warnings (ADR-0032);
every governance action ADR-0031's API actually authorises for the
viewer's role and the version's current state, never an action rendered
available that the API would reject; entry points to source viewing,
extracted-text inspection, and version comparison; audit/history entry
points where ADR-0031's audit read model exists. **Preserved citations to
older versions are explicitly not exposed on this page in V1**, matching
ADR-0025's existing no-reverse-citation-listing invariant.

**Source viewing** (from any version-specific context): opens ADR-0032's
authorised source route, `/app/workspaces/{workspace}/documents/{document}`,
in a new tab/window. The browser never constructs a storage URL of any
kind — it only ever follows this application route, which itself proxies
the object server-side per ADR-0032. An unavailable, deleted, or
unsupported source shows a truthful unavailable state with **no dead
"View source" control at all** — its absence is the honest signal.
Historical (non-current) sources that remain retrievable show the same
safe status treatment; a source whose bytes have been deleted under
ADR-0025/0031 shows the existing "source removed" treatment, matching
citation display exactly.

**Extracted text**: presents ADR-0032's ordered projection only — **never**
a reconstruction from retrieval chunks, which can overlap and would
misrepresent the document. Rendered in bounded, paginated, or virtualised
structured sections (headings, paragraphs, tables) using ADR-0032's
deterministic `(ordinal, id)` ordering, with a maximum response/page size
matching ADR-0032's own bounded-transfer discipline — never a single
unbounded payload. Missing page/heading/structural metadata is shown as
genuinely unavailable, never fabricated. Extraction warnings are visible,
not hidden. Plainly states, verbatim: "Text Dolved extracted for search,"
and that it may not reproduce the source's visual layout exactly. Never a
raw JSON dump.

**Version comparison** (`/documents/families/{family}/compare`):

- **Both `from` and `to` version identifiers must resolve inside the same
  workspace and belong to the family named in the route** — a
  `documentPublicId` from another family, or another workspace, or an
  invalid identifier, is rejected with the same tenant-safe concealment
  treatment as any other invalid identifier, never a partial or
  best-effort comparison.
- **Same-workspace cross-family comparison is never permitted**, even
  though both identifiers might otherwise resolve to real, existing
  documents.
- `from` and `to` must be **distinct** — identical values are rejected as
  invalid, not silently rendered as a no-op comparison.
- **One query parameter missing**: defaults the missing side to the
  present side's immediate predecessor (if `to` is given, `from` defaults
  to `to`'s predecessor) or successor context as appropriate — if no such
  version exists, falls through to the "only one version" case below.
- **Both missing**: defaults to the family's current version against its
  immediate predecessor.
- **Only one version exists in the family**: comparison is unavailable,
  shown as a truthful empty state ("This family has only one version"),
  never an error.
- **The current version has no predecessor** (a family's first version is
  also its current one): same truthful empty state.
- **A named version is deleted/tombstoned or lacks extraction data**: the
  comparison shows a truthful partial/unavailable state for that side
  specifically (its metadata may still be shown; its content diff is
  marked unavailable), never a crash or a silently empty diff.
- Content diff surfaces added/removed/changed extracted content, element-
  by-element, from ADR-0032's ordered projection; unchanged sections
  collapse by default for large documents; extraction warnings and
  partial-extraction conditions are shown within the comparison.
- **Family-editable metadata (ADR-0030) and version-governed/immutable
  source fields are shown in clearly separated sections** — a comparison
  never implies that a title, description, category, tags, owner, or
  **review-due date** "changed between versions," because ADR-0030
  classifies every one of these as family-owned, not version-owned — they
  cannot differ per version at all, by construction. **Only true
  version-level facts — publisher/source label, source URL, applicability,
  and governance/effective dates (`effective_from`, `approved_at`,
  `withdrawn_at`)** — are diffed as "changed between these two versions."
  Family-owned metadata may still appear near the comparison, clearly
  labelled as the family's current metadata (not a per-version fact being
  diffed), for orientation only.
- Change indicators use icon/text/pattern in addition to colour, never
  colour alone; every interactive comparison control (version selectors,
  expand/collapse) is keyboard-operable with correct ARIA roles and
  states for screen-reader use.
- **No preserved reverse-citation history is exposed on the comparison
  page in V1**, matching the family detail page's own rule.

### Human-readable guidance and tooltips

Every concept a non-technical user could reasonably not already know —
title vs. filename, description, category, tags, owner, review date,
publisher/source, applicability, current authority, scheduled versions,
extracted text, warnings — gets a concise, plain-language explanation
behind an accessible, keyboard-operable information control. No critical
information is hover-only. Internal terms (claims, lineage digests,
projection generations, Qdrant, `attempt_origin`, `PromotionAttempt`)
never appear in user-facing copy.

### Small-corpus onboarding — complete state model

**Ten distinguished states, never collapsed into one another:**

1. Selected/uploading.
2. Safely staged (ADR-0034).
3. Matching/details unresolved.
4. Promoted/queued (ADR-0034's `COMMITTED`, ADR-0007's `UPLOADED`/`QUEUED`).
5. Processing (ADR-0007's technical `PROCESSING`).
6. Awaiting approval (technically `INDEXED`, governance `DRAFT`).
7. Approved but not yet technically ready, where this ordering is possible
   (a version approved ahead of completing indexing).
8. Indexed but not (yet) authoritative, where possible (a future-scheduled
   approved version).
9. **Approved, current, indexed, and genuinely searchable** — the only
   state in which the version-derived fields above and the searchable
   count both include it.
10. Warning or failed.

"Uploaded" is never presented as equivalent to "ready"; none of states 1–8
is ever shown as though it were state 9.

**Five understandable user-facing stages**, presented via tooltips/
accordions/progressive disclosure:

1. Upload documents.
2. Match to an existing document or create new.
3. Review details and applicability.
4. Approve.
5. Ask grounded questions.

**First-readiness success state**: shown once a tenant's first batch
reaches state 9 for at least one document — a plain, truthful message
using the corrected count above, with two real actions, **Ask Dolved** and
**View searchable documents**.

**Required Playwright journey** — must prove genuine readiness, not upload
alone:

1. Start with a newly provisioned workspace.
2. Import approximately ten representative documents through ADR-0034's
   `ImportBatch` flow.
3. Resolve every matching/details decision.
4. Promote (reach `COMMITTED`).
5. Reach real technical processing completion (`INDEXED`).
6. Approve/current-authorise as required by ADR-0017/0031.
7. **Assert the readiness count reflects genuinely searchable documents**
   using this ADR's corrected count definition — not a raw upload or
   promotion count.
8. Ask a question genuinely supported by the imported corpus.
9. Receive a grounded answer with valid evidence/citation behaviour
   (ADR-0023/0024/0027's existing contract).

**The journey must not pass on upload or indexing alone** — step 9's
grounded-answer assertion is the journey's actual pass condition.

### Visual acceptance — complete requirement per session

Every user-facing session (allocation below) requires, before it is
considered complete: a direct development URL; representative fixtures;
deliberately awkward fixtures — empty state, high-volume table (hundreds
of rows), a family with a very long title (overflow behaviour), a family
with missing optional metadata, a warning state, a failure state, a
deleted/unavailable source, and partial-extraction content where relevant;
dark and light review; desktop, tablet, and mobile review; keyboard/focus
review; screen-reader and live-region review (for any dynamically-updating
region — progress, notices); and David's explicit approval before the
pattern is replicated across further screens. Backend completion alone
never closes a user-facing session.

## Alternatives considered

### Reinterpreting the existing document-version route segment as a
### family identity

Rejected as unsafe — it would silently break every existing citation's
`source_route`, ADR-0027's already-fixed route, and any bookmark.

### A single navigation-matching code snippet prescribed in the ADR

Withdrawn — a fragile literal expression would canonise an implementation
detail; the behavioural requirement (segment-aware, prefix-safe, single
active destination) plus acceptance tests is the correct level.

### Sourcing family-editable metadata (title, description, category, tags,
### owner, review date) from whichever version happens to be current

Rejected on inspection: this would silently misrepresent family-level
facts as version-level ones, and would break the moment a family's own
metadata and its current version's identity diverge — exactly the
scenario an applicability-only clone (ADR-0031) creates routinely. Family-
owned fields are read from `DocumentFamily` directly, always.

### Recomputing "last meaningful update" from a live scan of every
### underlying event table at read time

Rejected as a query-shape violation of this ADR's own bounded-query
requirement — a per-row scan across metadata-audit, governance-audit, and
warning tables would reintroduce the N-query problem this ADR otherwise
closes. A maintained, write-time-updated summary projection is required
instead.

### Cursor-based pagination for the library table

Considered, and rejected in favour of matching this repository's already-
established page-based convention (`page`/`per_page`,
`meta.current_page`/`last_page`) — introducing a second pagination style
for one table would be inconsistent with every other paginated surface in
this codebase for no identified benefit.

### Allowing cross-family version comparison

Rejected outright — a comparison spanning two unrelated families would not
be "comparing versions of a document," and risks leaking one family's
existence/content into a UI surface scoped to another.

### Narrowing the searchable-document count to a per-member "assigned
### location" scope

Rejected on verification: no accepted ADR or schema defines a per-user
location assignment, and ADR-0018 itself applies no location narrowing to
an undirected question. The corrected count mirrors that exactly.

### Generating starter questions with an LLM call

Rejected — the non-negotiable no-provider-calls boundary and the risk of
implying an unsupported fact.

## Consequences

### Positive

- The library table's field-source model closes a real correctness risk
  (family metadata silently sourced from the wrong row) before
  implementation could introduce it.
- The bounded query plan and its required query-count test prevent an
  N-query regression from ever shipping unnoticed.
- `SavedView` is now a complete, implementable schema, not a partial
  sketch.
- Comparison's family-membership validation closes a real cross-family
  data-exposure risk.
- The onboarding journey's grounded-answer assertion proves the whole
  small-corpus promise end-to-end, not merely that upload succeeded.

### Negative

- The `document_family_activity_summary` projection is new, real
  write-path work touching every existing audited-mutation Action across
  ADR-0030/0031 — a genuine cross-cutting change, not confined to one
  session.
- The library table's bounded query plan is more complex to implement
  correctly than a naive per-row loop, and requires its own performance
  test to prove.
- `SavedView`'s complete schema, including normalization and degradation
  rules, is real new Laravel domain work beyond a simple CRUD table.

## Scope boundaries

This ADR does not define: ADR-0027's design system or shell mechanics;
ADR-0031's governance action semantics or family-deletion flow; ADR-0032's
source-delivery security mechanics; ADR-0035's bulk-operation mechanics
beyond the row-contract seam it will consume; ADR-0036's notification
centre or activity feed; ADR-0037's export UI; the exact Next.js API used
for segment-aware navigation matching; the exact numeric caps for saved-
view definition size or starter-question title-suitability rules.

## Incremental destination activation map

| Destination | R24 | R25 | R26 | R27 |
|---|---|---|---|---|
| Library, family detail, version history/comparison, saved views, category settings | ✅ | | | |
| Deleted/history (route + presentation; data from ADR-0031 R23-S02c) | ✅ | | | |
| Needs attention (failed/warning, awaiting approval only) | ✅ | expands | expands | |
| Imports | | ✅ | | |
| Needs attention (adds review-due, scheduled-change conflicts) | | | ✅ | |
| Notification centre/preferences | | | ✅ | |
| Export | | | | ✅ |

## Implementation and session allocation (R24)

- **R24-S01 — Contextual navigation shell and route scaffolding.**
  Segment-aware contextual activation; the full route tree; tenant-safe
  concealment tests for both identifier kinds independently; truthful
  empty states.
- **R24-S02 — Library table.** Full row contract; `document_family_activity_summary`
  projection and its write-path integration across ADR-0030/0031's
  audited actions, the ingestion completion/failure callbacks, and
  ADR-0032 warning publication; bounded query plan with its required
  query-count test; URL-backed search/filter/sort/page-size; responsive
  column reduction; no selection column (deferred to ADR-0035).
- **R24-S03 — Family detail.** Metadata, version list, current-authority
  distinction, governance actions gated by the API's own authorisation.
- **R24-S04 — Source viewing and extracted text.** Consuming ADR-0032's
  routes directly.
- **R24-S05 — Version comparison.** Family-membership validation; default/
  missing-parameter/single-version/no-predecessor/deleted-side handling.
- **R24-S06 — Saved views and category settings.** The complete `SavedView`
  schema and lifecycle; category-settings CRUD UI.
- **R24-S07 — Small-corpus onboarding and searchable-count read model.**
  The new member-safe Laravel query; deterministic starter-question
  templating; the ten-state onboarding model.
- **R24-S08 — Playwright: small-corpus readiness journey.** The complete
  import-through-grounded-answer journey specified above.
- **R24-S09 — Deleted/history route.** The presentation surface for
  ADR-0031's family-deletion tombstone data.

Not allocated here: ADR-0035's bulk-execution UI, ADR-0036's notification
UI, ADR-0037's export UI.

### Post-acceptance implementation-sequencing clarification — 2026-08-30

The required Playwright journey above remains normative and unchanged, but its
original R24-S08 execution allocation created an impossible dependency cycle:
steps 2–4 require ADR-0034's real `ImportBatch`, `ImportItem`, matching, review
and promotion implementation, while that implementation is owned by Phase 25
and Phase 25 originally followed the Phase 24 gate.

R24-S08 contains no independently executable remainder once R24-S07's count,
starter-question and onboarding-model work is complete. The complete
import-through-grounded-answer journey therefore executes as a mandatory part
of R25-S07, after R25-S01 through R25-S06 have implemented and visually
reviewed the real ADR-0034 workflow. It is not completed, skipped or weakened
by this clarification. The legacy direct-upload path is explicitly not an
acceptable substitute.

R24-S09 may proceed after R24-S07 because it depends only on ADR-0031's existing
family-deletion tombstone data. The Phase 24 gate accepts only the independently
implemented ADR-0033 product surfaces through R24-S09; it does not claim final
import-flow acceptance. The Phase 25 gate must not pass until R25-S07 has run
the complete nine-step journey above, including the genuinely searchable count
and grounded-answer-with-valid-evidence assertion.
