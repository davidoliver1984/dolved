# ADR 0017: Define Document Versioning and Temporal Authority

## Status

Accepted

## Date

2026-08-06

## Relationship to prior ADRs

### Supersession of ADR-0007, in part

ADR 0007 deliberately deferred versioning rather than rejecting it outright,
and named the shape it expected a future decision to take: *"a future ADR
could add an explicit relationship between documents (for example, a
'supersedes' link) once a genuine requirement exists, without requiring
this ADR's lifecycle or storage separation to change."* Phase 16 is that
requirement. Retrieval cannot honestly answer *"which evidence is eligible
to answer this question"* without a settled definition of which version of
a document is authoritative at a given moment, so this document resolves
the deferral rather than reopening it speculatively.

**Superseded**: ADR-0007's *"no versioning for now"* position specifically —
the decision that every upload is an independent, unrelated Document with no
notion of supersession.

**Carried forward unchanged**: the per-Document technical processing
lifecycle (`UPLOADING → UPLOADED → QUEUED → PROCESSING → INDEXED`, `FAILED`
from `PROCESSING`, `DELETING → DELETED` from any non-deleted state); the
three-layer separation of relational metadata, object storage and the
vector projection; deletion as an asynchronous, auditable lifecycle rather
than an immediate row removal; and the principle that a Document is an
identity and a lifecycle, not a file. Every Document that exists today, and
every Document Phase 8 through Phase 15 already built against, continues to
go through exactly that same lifecycle, independently, whether or not it
belongs to a family with other versions.

This document does not rename or replace the `Document` model ADR-0007
established. A **version**, in this ADR's vocabulary, is a Document
understood in the context of the family it belongs to — the terms
`Document` and `DocumentVersion` are used interchangeably where the version
context matters, without implying a schema rename. Whether the eventual
implementation renames the model, or adds family/temporal/governance
columns and relationships to the existing one, is Stage 16.1 implementation
work, not fixed here.

## Context

Section 1 of the Phase 16 architectural review that preceded this document
named the concrete, current requirements driving this decision: `CURRENT`,
`VALID_AT_DATE` and `COMPARE` retrieval; scheduled future versions;
preserving historical source-of-truth behaviour; explicit document lineage;
and ensuring future capabilities can build on recorded history without
reinterpreting existing customer data. None of these are speculative — they
are what *"determine which evidence is eligible to answer an authorised
user's question"* actually requires once a document (a policy, a procedure,
a standard) can change over time and still needs its history to remain
honestly queryable.

This document owns the **domain model and derivation rules** — what a
version is, what lineage means, what "authoritative at time T" means, and
the invariants that keep that meaning consistent. It does not own how
retrieval consumes that model. That split mirrors how ADR-0007 (the Document
domain model) relates to ADR-0008 (the publication mechanics that consume
it), and how ADR-0014 (the storage domain model) relates to ADR-0015/0016
(the orchestration that consumes it) — Stage 16.2 (ADR-0018) is the
consumer here, not this document.

## What this ADR decides and does not decide

This ADR defines: `DocumentFamily` as the stable identity that persists
across versions; the rule that every Document belongs to exactly one
family; explicit, linear version lineage; the temporal-authority derivation
rule (`CURRENT` as a special case of `VALID_AT_DATE`); the structural
invariant preventing overlapping authoritative periods; scheduled future
versions and their cancellation/rescheduling semantics; a minimum governance
(approval) state model, orthogonal to the existing technical lifecycle; and
the domain shape for optional, hierarchical location applicability. It does
not decide `RetrievalPlanner`, `EligibilityResolver`, or the Retriever
contract (ADR-0018); it does not decide evaluation or reranking (Stage 16.4,
Stage 16.6); it does not decide the Laravel-to-Python synchronous call
contract retrieval will eventually need (recorded as required Stage 16.2
work, not designed here); and it does not decide administrative CRUD,
hierarchy-management UI, or approval-workflow UX for locations or governance
(Phase 19). It also does not decide exact table names, migrations, or
constraint syntax — Stage 16.1 implementation work, constrained by the
invariants fixed here.

## Decision

### `DocumentFamily`: the stable identity across time

A **`DocumentFamily`** is the durable identity a user, an administrator, or
a future generation/citation feature refers to when they mean "the absence
policy," independent of which specific version currently answers to that
name. It is workspace-owned, exactly as `Document` already is (ADR 0006,
ADR 0007) — a family never crosses a workspace boundary, and every
family-scoped query is tenant-scoped exactly as every other workspace-owned
query already is.

### Every Document belongs to exactly one family

Confirmed without qualification: every Document belongs to exactly one
`DocumentFamily`, from the first Document this platform ever ingested
onward. A family containing a single Document is the **normal, common
case** — most documents will never acquire a second version — not a
degenerate or special-cased one. `DocumentFamily` is not optional
scaffolding bolted onto "versioned documents" as a distinct category from
"ordinary documents"; there is one uniform model, and it applies uniformly.
The practical consequence, stated honestly rather than left implicit: this
requires a backfilling migration creating one single-Document family for
every Document already ingested by the time Stage 16.1 is implemented — real
implementation work, not a free architectural choice, but the one that keeps
this platform's data model uniform from that point forward rather than
carrying two shapes of Document indefinitely.

A new upload is always either the first Document in a new family, or an
explicitly targeted new version within an existing family — there is no
third, family-less case.

### Explicit, linear version lineage

Each Document, except a family's first, carries an explicit, immutable
reference to the single version it supersedes within the same family.
Lineage is a chain, not a graph: this ADR does not support branching
version history (multiple concurrent successors to one version) — nothing
in the stated V1 requirements needs it, and a linear chain is sufficient to
answer every `CURRENT`/`VALID_AT_DATE`/`COMPARE` question named in Context.
A version's successor is derived by querying which later version names it
as predecessor; it is not a separately stored, independently-updatable
field — this platform has repeatedly preferred one authoritative direction
for a fact over two fields that could disagree (ADR-0007's
`created_by_user_id` as provenance-only being the clearest precedent), and
lineage is no exception. A version's declared predecessor must have an
earlier effective date than the version itself; this is a structural
integrity requirement, not a business rule, and is expected to be enforced
at the same layer as the effective-date uniqueness constraint in
"Unambiguous temporal succession" below.

### The temporal authority model: `CURRENT` as a special case of `VALID_AT_DATE`

Rather than defining `CURRENT` and `VALID_AT_DATE` as two independently
implemented rules that must be kept in agreement, this ADR defines one rule
and treats `CURRENT` as `VALID_AT_DATE` evaluated at the present moment.
Getting this rule right requires two corrections to a naive version of it,
both worth stating precisely because neither failure is obvious from a
casual reading of "the latest eligible version."

**Failure 1: predecessor resurrection.** A rule that re-evaluates "the
governance-eligible version with the latest effective date not later than
T" fresh at every T, with no memory of history, lets a withdrawn version's
predecessor be silently resurrected. Consider a family with `v1` (effective
2024-01-01, never withdrawn) and `v2` (effective 2025-01-01, later withdrawn
in 2026). Evaluated at a date in 2026, the naive rule excludes `v2` because
it is withdrawn, finds nothing else in its way, and returns `v1`. That is
wrong: `v1`'s authority ended in 2025 the moment `v2` genuinely took over,
and nothing about `v2`'s later withdrawal un-happens that. The correct
answer for 2026 is **no current version**.

**Failure 2: approval cannot retroactively authorise the past.** A rule
that treats `effective_from` alone as the moment a version could become
authoritative ignores that a version is not the organisation's source of
truth merely because its scheduled date arrived — it also has to have
actually been approved. Consider `v2` scheduled `effective_from:
2027-01-01`, still sitting in `DRAFT`/awaiting approval through
2027-01-14, and finally approved on 2027-01-15. A rule keyed on
`effective_from` alone would claim `v2` was authoritative from 2027-01-01,
including for a `VALID_AT_DATE` query asked about 2027-01-05 — a date on
which `v2` had not, in fact, been approved yet. Approval happening on the
15th must not retroactively rewrite what was true on the 5th.

**Failure 3: `authority_start` alone can disagree with lineage order.**
Folding `approved_at` into `authority_start` fixes Failure 2, but opens a
narrower third gap: nothing about `max(effective_from, approved_at)`
guarantees that a predecessor's `authority_start` comes before its own
successor's. Consider a family where `v2` supersedes `v1`: `v1` has
`effective_from: 2027-01-01` but sits unapproved for two months, finally
approved on `2027-03-01` (`authority_start: 2027-03-01`); `v2`, created to
supersede `v1`, has `effective_from: 2027-02-01` and is approved promptly on
`2027-02-15` (`authority_start: 2027-02-15`). Both versions' `effective_from`
values are distinct; both `authority_start` values are distinct — neither of
the uniqueness constraints in "Unambiguous temporal succession" below is
violated. Yet `v2`, the successor, would attain authority on 2027-02-15,
*before* `v1`, its own predecessor, attains authority on 2027-03-01 — and if
`v1` were allowed to attain authority at that point, an older predecessor
would become the organisation's source of truth *after* its own named
successor already was. That is exactly as wrong as Failure 1, arrived at by
a different route: authority history would have moved backward. This is not
closed by either uniqueness constraint; it requires a third, distinct rule,
stated in "Unambiguous temporal succession" below.

**The two authoritative timestamps.** Every version that is ever approved
records two explicit facts, neither derived from a generic row-modification
timestamp: `effective_from` (when the version is *intended* to take effect
— set at scheduling time, and reschedulable while not yet attained) and
`approved_at` (when governance actually authorised it — set once, the
moment the `DRAFT → APPROVED` transition genuinely occurs). Neither one
alone is sufficient; a version needs both conditions true before it can be
authoritative for any instant.

**`authority_start`, and attaining authority.** A version's authority can
only begin once it is *both* effective and approved:

```text
authority_start = max(effective_from, approved_at)
```

- `approved_at: 2026-12-20`, `effective_from: 2027-01-01` → `authority_start
  = 2027-01-01` (approval came early; the scheduled date is the binding
  constraint).
- `approved_at: 2027-01-15`, `effective_from: 2027-01-01` → `authority_start
  = 2027-01-15` (approval was late; actual authorisation is the binding
  constraint, and the version was not authoritative for 2027-01-01 through
  2027-01-14 regardless of its scheduled date).

A version **attains authority** if and only if all three hold: (a) it was
approved, (b) should it later be withdrawn, the withdrawal timestamp is not
earlier than its own `authority_start`, and (c) — closing Failure 3 — no
successor it is superseded by (per "Explicit, linear version lineage" above)
has already attained authority at or before this version's own
`authority_start`. Conditions (a) and (b) directly generalise the corrected
rule from the first amendment round: previously "attained authority" was
tested against `effective_from`; it is now tested against `authority_start`,
which folds approval timing into the same test rather than treating it as a
separate concern layered on top. Condition (c) is new in this round: it is
the one place this derivation *does* need to consult the explicit lineage
chain, precisely because `authority_start` alone — being built from
independently-set `effective_from`/`approved_at` facts — cannot be trusted
to already agree with lineage order (see Failure 3 above and "Unambiguous
temporal succession" below for how this is prevented from arising at all,
making condition (c) a safety net rather than the primary defence).

**The corrected rule, in full.** For each version that attains authority,
its authority window is `[authority_start, end)`, where `end` is the
earlier of: its own withdrawal timestamp (`withdrawn_at`, if withdrawn after
attaining authority), or the `authority_start` of the family's *next version
that also attains authority* — skipping over any version that never attained
authority at all, whether because it was cancelled before reaching its own
`authority_start`, never approved, or (condition (c) above) blocked by a
successor that already attained authority first. Where neither applies, the
window remains open. The authoritative version at time **T** is whichever
attained-authority version's window contains T — and if no window contains
T, there is correctly **no** authoritative version at T, never a fallback to
whichever version happens to still exist.

Re-checked against all three failures above: for Failure 1, `v1`'s window
closes permanently at `v2`'s `authority_start` the moment `v2` attains
authority, regardless of `v2`'s later withdrawal — a query for 2026
correctly finds no window covering it. For Failure 2, `v2`'s window does not
begin until 2027-01-15 regardless of its `effective_from`, so a query for
2027-01-05 correctly does not return `v2` — it returns whatever was
authoritative before it (a predecessor's still-open window, or no version at
all if this is the family's first). For Failure 3, `v1` never attains
authority at all — condition (c) excludes it the moment `v2` (its named
successor) attains authority on 2027-02-15, which is before `v1`'s own
would-be `authority_start` of 2027-03-01 — so `v1` is skipped entirely by
the derivation, exactly like a cancelled version, and authority history
never moves backward.

This remains a **derived fact, computed at query time, never a stored,
flipped boolean, and requiring no scheduled job.** `authority_start` and
`end` are computed from `effective_from`, `approved_at` and `withdrawn_at`
every time, not cached or maintained — recording *when governance acted* is
not the same thing as storing *what is currently true*, and only the former
is persisted here. Unlike the first amendment round's version of this
section, the derivation is no longer fully independent of the explicit
lineage chain: conditions (a) and (b) still need only a version's own three
timestamps, but condition (c) needs the lineage-declared successor
relationship too, because — as Failure 3 shows — `authority_start` values
alone are not guaranteed to already agree with lineage order. The two are
expected to agree in practice, because "Unambiguous temporal succession"
below enforces it structurally at approval time; condition (c) exists so
that the derivation itself is correct even if that structural enforcement
were ever bypassed, not as the primary mechanism relied upon.

### Unambiguous temporal succession: the constraints this depends on

The corrected derivation is only well-defined if, for any two versions that
both attain authority, one's `authority_start` is strictly earlier than the
other's — "the next version that also attains authority" has to be
unambiguous. **Uniqueness of `authority_start` values is necessary but not
sufficient for that**: two versions can each have a perfectly unique
`authority_start` and the derivation still be ambiguous in the sense that
matters, if the *order* those values fall in disagrees with which version
the family's own lineage says supersedes which (see Failure 3, "The
temporal authority model" above). Unambiguous succession therefore depends
on three constraints, not two — two about uniqueness, one about ordering:

- **No two ever-approved versions in one family may share an
  `effective_from`.** This is checkable, and enforceable, at the moment a
  version is scheduled or rescheduled — the same pattern ADR-0014 already
  established for its own "at most one active generation per workspace"
  invariant (*"a partial unique PostgreSQL index permits at most one
  `ACTIVE` corpus generation per workspace"*).
- **No two versions in one family may share an `authority_start`.**
  Distinct `effective_from` values are not, by themselves, sufficient to
  guarantee this: because `authority_start` also depends on `approved_at`,
  a late approval of one version can coincide with another version's
  `effective_from` (or another's late approval), producing two versions
  with the same derived `authority_start` despite having been scheduled for
  different dates. This is a real, if narrow, case the effective-date
  constraint alone does not close. Enforcing it requires checking
  `authority_start` uniqueness both when a version is scheduled and again
  at the moment it is actually approved (since approval is what can make a
  previously-fine schedule collide) — Stage 16.1 implementation decides the
  exact mechanism (a check performed at approval time, a deferred
  constraint, or an equivalent), but both facts — that scheduling alone
  does not guarantee non-collision, and that the invariant must hold at
  approval time regardless — are fixed here.
- **Temporal authority must be monotonic with explicit lineage.** For any
  two versions in a family that both attain authority, where one supersedes
  the other (directly or transitively, per "Explicit, linear version
  lineage" above), the successor's `authority_start` must be strictly later
  than the predecessor's: `authority_start(successor) >
  authority_start(predecessor)`. Distinct, even unique, `authority_start`
  values do not guarantee this on their own — Failure 3 above is exactly
  two unique `authority_start` values in the wrong order relative to
  lineage, arising from nothing more unusual than a predecessor's approval
  being delayed past its successor's. This constraint does **not** require
  every version in a lineage chain to attain authority — a version that
  never attains authority (cancelled, never approved, or itself blocked by
  this same rule) is simply skipped when walking the chain, exactly as
  cancelled versions already are elsewhere in this document; it only
  constrains the relative order of versions that *do* attain authority.

  **Enforcement.** The cleanest mechanism is a validation performed at
  approval time, not a database constraint alone (unlike the two uniqueness
  constraints above, "later than every transitively-linked successor's
  `authority_start`" is not expressible as a simple index). When a version
  is approved — the `DRAFT → APPROVED` transition that sets `approved_at`
  — the system computes the resulting `authority_start` and checks whether
  any successor reachable from this version through the lineage chain has
  already attained authority at or before it. If so, the transition is
  rejected outright, with a clear domain error identifying the conflicting
  successor, rather than silently succeeding into a version that can never
  attain authority. This keeps the governance action itself honest — an
  approver is told immediately that this version's own successor already
  holds authority, rather than discovering later that the approval was a
  no-op — and it is checked once, at the one moment (`approved_at` being
  set) capable of producing the violation, exactly paralleling why
  `authority_start` uniqueness above is re-checked specifically at approval
  time. Condition (c) of "attains authority" in "The temporal authority
  model" above is the derivation's own, independent enforcement of this
  same rule, and remains correct even in the (expected to be unreachable,
  given this validation) case where the approval-time check was somehow
  bypassed — the same defence-in-depth relationship the two uniqueness
  constraints already have with the query-time derivation.

None of these three constraints amount to a general "no overlapping
periods" rule enforced directly — the windows themselves are derived, not
stored, so there is nothing resembling a stored interval to check. What is
enforced is narrower and precise: uniqueness of the two facts
`authority_start` is built from, plus agreement between `authority_start`
order and lineage order for any pair that both attain authority. The
impossibility of simultaneous or backward-moving authority is a
*consequence* of these three constraints plus the derivation's construction,
not a fourth, independently enforced fact.

### Scheduled future versions, cancellation and rescheduling

A version with a future effective date, or an already-`APPROVED` one whose
`authority_start` has not yet been reached, is not a distinct governance
state — it is simply a version that has not yet reached its
`authority_start`, exactly as "not yet current" is a computed fact rather
than a stored one. This gives cancellation and rescheduling their required
behaviour without special-case logic, precisely because "attaining
authority" is defined the way it is. Note throughout that `WITHDRAWN`
remains reachable only from `APPROVED` (see "Governance state" below) — an
unapproved `DRAFT` is a separate case, handled in its own bullet:

- **Cancelling** a scheduled or not-yet-attained version — `APPROVED →
  WITHDRAWN` before the version attains authority, whether because its
  `effective_from` has not arrived or because it was approved but hasn't
  yet — means it never attains authority in the first place. It is
  excluded from the derivation entirely, as if it had never existed for
  temporal-authority purposes. Whatever was authoritative before it was
  scheduled remains authoritative, for every date including those after the
  cancelled version's original effective date, because the derivation never
  depended on it to begin with. No gap is possible, and no predecessor is
  ever displaced by a version that never took effect.
- **A `DRAFT` that is never approved** is not "cancelled" in this sense at
  all, and abandoning one is not a governance-state transition this ADR
  defines. `WITHDRAWN` is reachable only from `APPROVED` (see "Governance
  state" below); a `DRAFT` was never governance-eligible and never attains
  authority regardless of what becomes of it, so there is no authority for
  a withdrawal to close and no transition to name. Whether and how an
  unapproved `DRAFT` may be deleted or left inert is ordinary Document
  lifecycle handling (ADR-0006/ADR-0007), not something this ADR needs to
  define.
- **Rescheduling** a not-yet-attained, `APPROVED` version changes its
  `effective_from`, constrained by the same effective-date uniqueness
  constraint as any other version in the family. It is an ordinary update to
  a version that has not yet attained authority; it requires no different
  mechanism from initial scheduling. Approval timing (`approved_at`) is a
  separate fact and is not itself "rescheduled" — approving a version is a
  one-time governance action, not a movable date.
- **Withdrawing an already-attained version** is different in kind, not
  degree: the version attained authority, its predecessor's window is
  already permanently closed, and withdrawal only closes the withdrawn
  version's own window — it does not reopen anything earlier. See "The
  temporal authority model" above.

### Governance state, orthogonal to technical processing

Two questions about a version are genuinely independent and must not be
collapsed into one state machine or a loose boolean:

- **Technical processing state** (ADR 0007, unchanged): *"Is this version
  successfully processed and indexed?"*
- **Governance state** (new, this ADR): *"Is this version authorised to
  become authoritative knowledge?"*

A version can be `INDEXED` — technically complete, its vectors published —
while still awaiting governance approval, exactly as ADR-0014's
embedding-space and workspace-corpus-generation lifecycles are separate,
coordinated state machines rather than one collapsed machine. This mirrors
that precedent rather than inventing a new one.

The minimum governance model needed to support authoritative retrieval —
deliberately bounded, not a workflow engine:

```text
DRAFT → APPROVED → WITHDRAWN
```

- **`DRAFT`** — the default state for every new version. A `DRAFT` version
  is never governance-eligible, regardless of its technical processing
  state or effective date; it is invisible to `CURRENT`, `VALID_AT_DATE`
  and `COMPARE` resolution entirely.
- **`APPROVED`** — authorised to become authoritative once `authority_start`
  (`max(effective_from, approved_at)`, see "The temporal authority model"
  above) is reached. The `DRAFT → APPROVED` transition records `approved_at`
  — the moment approval genuinely happened — as an explicit, required,
  non-derived timestamp, distinct from `effective_from`. Approval and
  effectiveness are independent facts — *"future versions may be uploaded,
  indexed and approved before their effective date"* is exactly this:
  `APPROVED` (with `approved_at` set) now, `effective_from` later, and
  `authority_start` resolving to whichever is later at query time.
- **`WITHDRAWN`** — a terminal state, reachable only from `APPROVED`. The
  `APPROVED → WITHDRAWN` transition records `withdrawn_at` — the moment
  withdrawal genuinely happened — as an explicit, required, non-derived
  timestamp, distinct from both `effective_from` and `approved_at`. Its
  effect on temporal authority depends on whether the version had already
  attained authority when withdrawn (see "The temporal authority model"
  above): withdrawing a version that already attained authority closes only
  that version's own window, permanently, and never reopens a predecessor's;
  withdrawing an `APPROVED` version before it attains authority (cancelling
  a scheduled or not-yet-attained version — see "Scheduled future versions,
  cancellation and rescheduling" above) removes it from consideration
  entirely, as though it never existed for temporal-authority purposes,
  leaving whatever was already authoritative untouched. `WITHDRAWN` is never
  reached from `DRAFT`: an unapproved version was never governance-eligible
  in the first place, so there is nothing for a withdrawal to close — see
  "Scheduled future versions" above for how an unapproved `DRAFT` is
  abandoned instead. In neither withdrawal case is it
  retroactive: `VALID_AT_DATE` queries for a date before `withdrawn_at` are
  unaffected by it, preserving the historical source-of-truth behaviour
  named in Context. Withdrawing the only, or the currently latest,
  attained-authority version in a family is a valid outcome that can
  legitimately leave a family with no currently-authoritative version — this
  is a real state ("there is currently no current policy"), not a bug the
  model needs to prevent; how retrieval represents that outcome is Stage
  16.2's concern.

`REJECTED`, richer approval workflows (multi-step sign-off, delegated
approval authority, notification), and any administrative UI for managing
these transitions are deliberately out of scope — a genuine future need for
Administration (Phase 19) to design, not something this ADR should
anticipate speculatively. *Who* is authorised to approve or withdraw a
version is an authorisation question belonging to Laravel's existing
role/permission model (ADR 0006); this ADR does not decide it.

Governance transitions are business-audit-worthy events under ADR-0006's
existing audit layer, extending the same treatment already given to
workspace and document lifecycle events — not telemetry alone. Every
ordinary `DRAFT → APPROVED` or `APPROVED → WITHDRAWN` transition records
`approved_at`/`withdrawn_at` as the actual current time at the moment the
transition is performed — never a caller-supplied value — exactly as any
other audited event timestamp already works under ADR-0006.

#### Backdated governance corrections

Governance timestamps are, by construction, load-bearing for what
`VALID_AT_DATE` returns for past dates — `approved_at` and `withdrawn_at`
are not administrative metadata on the side, they are direct inputs to the
temporal-authority derivation. That makes an ordinary "just edit the
timestamp" correction path dangerous in a way editing most fields is not:
silently changing a recorded `approved_at` or `withdrawn_at` silently
changes what a historical query would return, with no trace that history
was rewritten.

This ADR does not design a correction workflow — the administration UX
(who requests a correction, what review it goes through, how it is
surfaced) is left to Administration (Phase 19), exactly as the rest of this
governance model's UI is. What it does fix, because the temporal model
would otherwise have a silent gap, is the constraint any such correction
must obey:

- A correction to an already-recorded `approved_at` or `withdrawn_at`
  requires an elevated permission distinct from ordinary approve/withdraw
  authority — correcting history is not the same action as performing it,
  and must not be reachable by the same role check.
- A correction requires an explicit, recorded reason — never a bare
  timestamp overwrite with no accompanying explanation.
- A correction produces its own distinct business-audit record (old value,
  new value, who, when, why), under the same ADR-0006 audit layer as the
  original transition — it is recorded as a correction event in its own
  right, not merged into or replacing the original transition's audit
  entry.
- Ordinary approval and withdrawal, per above, always record the actual
  current time and are never backdated by a caller-supplied value; backdating
  is only ever reachable through the correction path just described, never
  as an option on the ordinary transition.

The intent is narrow: the temporal model must not leave room for someone to
silently alter what `VALID_AT_DATE` would have returned yesterday. It is not
an attempt to specify the correction feature itself.

### Region/Site applicability: one generic organisational-location hierarchy

The stated requirement is a hierarchy where a document's applicability to a
region extends automatically to that region's sites. Hard-coding exactly
two typed levels — `Region` and `Site` — would work for that requirement,
but would also bake in an assumption about hierarchy depth this platform has
no evidence is permanent. Instead: a single, generic, self-referencing
**`OrganisationalLocation`** entity, workspace-scoped, with a nullable
parent reference to another `OrganisationalLocation` in the same workspace.
Depth is not fixed by the schema — a two-level `Region → Site` tree is what
V1 populates and is what the applicability rule below is written against,
but a future three-level tree, or a flat one-level list, requires no schema
change, only different data. A `kind`/`level` label may be attached for
display purposes, but carries no structural or authorisation meaning of its
own — the parent/child relationship is the only thing that determines
hierarchy.

- Every location is a stable, structured entity with a durable identifier —
  never free text.
- Applicability is **optional**. Absent any applicability record for the
  version in question, the document is **`UNIVERSAL`** within its authorised
  scope — applicable everywhere the requesting user is otherwise permitted
  to see.
- Applicability to a location extends to every descendant location,
  computed by walking the parent chain — a document applicable to "North
  West" is applicable to every site beneath it, without those sites being
  individually recorded.
- Location applicability and user access are independent checks, evaluated
  separately: applicability is eligibility metadata (a business rule about
  where a document is relevant), never security metadata (a hard access
  boundary). A user's authorisation to see content associated with a given
  location is a separate fact, resolved by `AuthorisedKnowledgeScope`
  (Laravel's existing authorisation model, ADR 0006), not by this model.

**Applicability is recorded per version, as an immutable snapshot — not as
mutable state on the family.** A version's own content, structure and
provenance are already immutable once created (ADR 0007, ADR 0010, ADR
0011); which locations it applied to is exactly the same kind of fact, and
treating it any less permanently would let a later, unrelated change to
"the family's applicability" silently rewrite the context every past version
was actually created and approved under. Concretely: *"which policy applied
to Preston in 2024," "was the 2025 version applicable to this site,"* and
*"when did this policy become company-wide"* are all questions about a
specific version's own recorded applicability at the time, and must remain
answerable exactly as they were when that version was approved, regardless
of what any later version — or the family itself — subsequently records.

`DocumentFamily` may separately hold a **default applicability** — a
convenience used only to pre-populate a new version's applicability at
creation time (including a family's very first version, which has no prior
version to inherit from) or, when a family already has versions, to seed the
new version from its most recent one. This default is ordinary mutable data:
an administrator may change it at any time, and doing so affects only what
gets proposed the next time a new version is created — it never touches any
already-persisted version's own recorded applicability, and it is never
itself consulted when resolving eligibility for an existing version. Once a
version is created, whatever applicability was recorded for it (inherited
from the default and accepted, or explicitly overridden by the
administrator at creation time) is that version's own durable fact from
then on.

Administrative CRUD for `OrganisationalLocation`, hierarchy-management UI,
and configuration UX are Phase 19 (Administration) concerns. This ADR
defines only the domain shape retrieval eligibility and durable historical
data require.

### Interaction with ADR-0016's dual retrieval gate

Unchanged, restated for clarity because it is easy to get this wrong once a
temporal dimension exists: ADR-0016's dual gate — a published Qdrant point
**and** PostgreSQL `INDEXED` — remains the unconditional structural
prerequisite for a chunk to be retrievable at all. `INDEXED` continues to
mean exactly what ADR-0007 and ADR-0016 already say it means: the complete,
verified searchable representation exists. It is never redefined, here or
anywhere, to mean `CURRENT` or "temporally authoritative." This ADR's
governance and temporal-authority model is a **third, additive gate**,
applied on top of the existing two by `EligibilityResolver` (ADR-0018) — a
version can be fully `INDEXED` and structurally retrievable while still
failing this third gate (not yet approved, not yet effective, or withdrawn),
and that is the expected, common case for a scheduled future version.

### Ownership and workspace scoping

`DocumentFamily`, version lineage, governance state and
`OrganisationalLocation` are all Laravel/PostgreSQL-owned, exactly as
`Document` itself already is — nothing here changes which service owns
which data. Every family and every location is mandatorily workspace-scoped,
consistent with ADR-0006's entity-classification requirement that
workspace-owned data always carries a non-nullable workspace foreign key.

## Alternatives considered

### Branching (non-linear) version lineage

Considered and rejected for V1. Nothing in the stated requirements —
`CURRENT`, `VALID_AT_DATE`, `COMPARE`, scheduled future versions — needs
more than one concurrent successor to a given version. A linear chain
answers all of them; a branching model would add real complexity (which
branch is "current"?) with no demonstrated need. Left as a possible future
extension, not designed against here.

### A stored, scheduler-flipped `is_current` flag

Rejected, per the explicit requirement this ADR is built around. A stored
flag makes correctness depend on a background job having actually run at
the right moment — exactly the kind of fragile, silently-stuck-until-noticed
failure mode this platform has repeatedly designed against elsewhere (ADR
0008's entire justification for the outbox pattern is avoiding exactly this
shape of hazard). Deriving the fact at query time removes the dependency
entirely.

### Deriving `CURRENT` as "the latest eligible version not later than T," re-evaluated fresh at every query

This was the original form of the derivation rule in this document, and it
is wrong — not merely simplistic. Re-evaluating "latest eligible, not later
than T" independently at every T, with no memory of what happened between
the earliest date and T, lets a withdrawn version's predecessor be silently
resurrected: withdrawing `v2` removes it from "eligible," and the query
falls back to `v1` as though `v2` had never superseded it, even though `v2`
genuinely held authority for a real period after `v1`'s authority ended.
Rejected in favour of the "attained authority" derivation in "The temporal
authority model" above, which distinguishes a version that genuinely
succeeded its predecessor (permanently closing the predecessor's window)
from one that was cancelled before ever taking effect (which closes
nothing). The failure mode this correction closes is not hypothetical — it
is the literal behaviour the original wording in this document produced.

### Treating `effective_from` alone as sufficient for `authority_start`

This was the temporal-authority model's state after the first amendment
round, and was rejected on the second review: keying authority purely to
`effective_from` lets approval retroactively authorise dates before approval
genuinely happened. A version scheduled for the 1st but not actually
approved until the 15th would, under an `effective_from`-only rule, be
treated as authoritative for the 1st through the 14th — dates on which it
had not, in fact, been authorised by anyone. Rejected in favour of
`authority_start = max(effective_from, approved_at)`, which requires both
the scheduled date to have arrived *and* genuine approval to have happened
before a version can begin closing anything or becoming authoritative
itself.

### Relying on the derived query alone, with no structural uniqueness constraint

Considered, and specifically rejected on review: a correctly-written query
happens to be well-defined even over inconsistent data (it still returns
one row), which is precisely the risk — the query's own determinism could
mask that the underlying data was never supposed to allow two versions to
attain authority at the same instant, or in the wrong order. Structural
constraints close the real ways ambiguous or backward-moving succession
could arise rather than trusting every future query to be written carefully
enough to notice. Three constraints are needed, not one: `effective_from`
uniqueness alone is insufficient once `authority_start` also depends on
`approved_at` — two versions with distinct scheduled dates can still
collide on `authority_start` if a late approval coincides with another
version's approval or effective date; and uniqueness of `authority_start`
values is itself insufficient, since two distinct, unique values can still
fall in the wrong order relative to lineage (Failure 3). See "Unambiguous
temporal succession" above for why all three constraints, specifically, are
what the correctness of the derivation actually depends on — not a general,
separately-enforced non-overlap rule.

### Treating unique `authority_start` values as sufficient for unambiguous succession

This was the state of "Unambiguous temporal succession" after the second
amendment round, and was rejected on the third review: it correctly
prevented two versions from sharing an `authority_start`, but said nothing
about the *order* those distinct values fall in relative to lineage. A
predecessor whose approval is delayed can end up with an `authority_start`
later than its own successor's — both values unique, both individually
valid, and the derivation would still let the older predecessor attain
authority after its successor already had, moving authority history
backward (Failure 3, "The temporal authority model" above). Rejected in
favour of adding lineage-monotonic ordering as a third, distinct constraint,
enforced by rejecting the offending approval outright rather than allowing
it to produce a version that can never validly attain authority.

### A single, hard-coded `Region → Site` two-level model

Considered and rejected in favour of a generic, self-referencing
`OrganisationalLocation` entity. The two-level model satisfies every stated
V1 requirement, but bakes a specific hierarchy depth into the schema for no
demonstrated reason — a generic parent/child model produces identical V1
behaviour (as long as V1 only ever populates two levels of data) while
leaving room for a different depth later without a migration.

### Applicability recorded only on `DocumentFamily`, with no per-version snapshot

This was also the original form of this document's applicability model, and
was rejected on review for a reason directly analogous to the `CURRENT`
correction above: mutable, family-level-only applicability would let a
later change silently rewrite the context every historical version was
actually approved under, making *"which policy applied to Preston in
2024"* unanswerable once the family's applicability had since changed. A
version's applicability must be exactly as durable as the version itself —
recorded per version, at creation time, with the family holding only a
mutable convenience default that seeds new versions and is never itself
consulted when resolving an existing version's eligibility.

### Retroactive withdrawal (removing a version from historical authority, not just future authority)

Considered and rejected as the default. Making withdrawal retroactive would
mean a `VALID_AT_DATE` query for a past date could return a different answer
after a later withdrawal than it would have before — directly undermining
the *"preserving historical source-of-truth behaviour"* requirement this ADR
exists partly to satisfy. Withdrawal takes effect from its own timestamp
forward only.

### Designing a full approval-workflow engine now

Considered and rejected as premature, consistent with this platform's
repeated preference to defer scope until a demonstrated need exists (ADR
0006's deferred permission engine, ADR 0010's loss-minimisation principle,
ADR 0013's decision not to self-host embeddings). A three-state governance
model is the minimum that makes `CURRENT`/`VALID_AT_DATE`/`COMPARE`
resolvable honestly; multi-step sign-off, delegation and notification are
real product concerns for Phase 19, not architecture this ADR should
anticipate speculatively.

## Consequences

### Positive

- Retrieval (ADR-0018) inherits one settled, honestly-derived definition of
  "authoritative," rather than having to invent one under implementation
  pressure.
- `CURRENT` and `VALID_AT_DATE` sharing one derivation rule means they
  cannot silently drift into disagreeing about what "authoritative" means.
- No scheduled job's correctness is load-bearing for temporal authority.
- Cancelling or rescheduling a future version requires no special-case
  logic — the derived query already produces the correct answer, and a
  successor that never attained authority never displaces anything.
- A version that genuinely attains authority can never be silently
  re-authorised by a later, unrelated event — its predecessor's window
  closed permanently the moment it took effect, regardless of what happens
  to it afterward.
- A version cannot be treated as authoritative for a date before it was
  actually approved, even if its scheduled effective date had already
  passed — approval timing is a real input to the derivation, not a
  formality layered on top of scheduling.
- Governance timestamps that feed the derivation (`approved_at`,
  `withdrawn_at`) are protected from silent retroactive change: correcting
  them requires elevated permission, an explicit reason, and its own audit
  record, so historical `VALID_AT_DATE` answers cannot be quietly rewritten.
- Authority history can never move backward: an older predecessor can never
  attain authority after its own named successor already has, even when a
  delayed approval would otherwise have produced exactly that — the
  approval that would cause it is rejected outright, with a clear reason,
  rather than silently accepted into a version that can never validly
  become authoritative.
- A single uniform Document/family model from the first customer document
  onward avoids ever needing a "migrate ordinary documents into versioned
  documents" project later.
- The generic location hierarchy accommodates a deeper or differently-shaped
  organisational structure later without a schema migration.
- Per-version applicability snapshots mean historical eligibility questions
  ("what applied to Preston in 2024") remain answerable regardless of any
  later change to the family's default.
- Governance state and technical processing state stay independently
  reasoned-about and independently testable, rather than one obscuring the
  other.

### Negative

- Every existing Document requires a backfilling migration into a
  single-Document family — real implementation work, not free.
- A version can be `INDEXED` yet still ineligible for retrieval (unapproved,
  not yet effective, or withdrawn) — a real distinction every future reader
  of "is this document indexed" must now hold in mind, where previously
  `INDEXED` alone was a sufficient answer.
- The temporal-authority derivation is genuinely more complex than a simple
  "latest row" query — it must distinguish a version that attained authority
  from one that was merely approved, which is real logic to implement and
  test correctly, not a trivial `MAX()`.
- The structural uniqueness constraints — on effective dates, and separately
  on derived `authority_start` (checked again at approval time, since a late
  approval can create a collision scheduling alone did not have) — are
  additional migration and constraint-design work beyond what a pure
  application-level check would have required.
- Approving a version now requires an additional check — walking the
  lineage chain for already-attained-authority successors — before the
  `DRAFT → APPROVED` transition can be allowed to succeed; this is real
  logic on the write path, not merely a read-time concern, and a delayed
  approval can now be rejected outright rather than always succeeding.
- Approval and withdrawal now require an explicit, non-derived timestamp
  each, plus a distinct, permission-gated correction path for the rare
  backdated fix — more write and audit surface than treating governance
  timestamps as ordinary editable fields would have needed.
- Per-version applicability snapshots mean every new version's creation flow
  must explicitly persist an applicability value (even when it is simply
  inherited unchanged from the family default or the previous version),
  rather than implicitly deferring to family-level state — more write
  surface than a family-only model would have needed.
- Three new governance states, plus lineage, plus a generic location
  hierarchy, is real new domain-model surface for Stage 16.1 to implement,
  test and migrate — accepted because retrieval cannot honestly resolve
  eligibility without it, not because it is small.

## Architectural invariants

- Every Document belongs to exactly one `DocumentFamily`; there is no
  family-less Document and no later migration path between "ordinary" and
  "versioned" documents.
- Version lineage is linear and immutable; a version's predecessor, once
  set, does not change, and must have an earlier effective date than the
  version itself.
- `CURRENT` is `VALID_AT_DATE` evaluated at the present moment — one
  derivation rule, never two independently maintained ones.
- Temporal authority is always computed at evaluation time; no stored,
  scheduler-flipped flag determines it.
- A version's `authority_start` is `max(effective_from, approved_at)`; a
  version cannot attain authority, or be treated as authoritative for any
  date, before both its scheduled effective date has arrived and it has
  genuinely been approved.
- A version's predecessor's authority window closes permanently the moment
  the version itself attains authority (`authority_start` is reached while
  it is still governance-eligible); a later withdrawal of that version never
  reopens the predecessor's window.
- A version withdrawn before it attains authority never attained authority,
  is excluded from the temporal-authority derivation entirely, and never
  displaces or gaps whatever was already authoritative.
- `approved_at` and `withdrawn_at` are explicit, required, non-derived
  timestamps recorded at the moment each transition genuinely happens; they
  are never inferred from a generic row-modification timestamp.
- No two ever-approved versions in one family may share an `effective_from`,
  and no two versions in one family may share an `authority_start`; both are
  enforced structurally (the latter re-checked at approval time).
- For any two versions in one family that both attain authority, where one
  supersedes the other per explicit lineage, the successor's
  `authority_start` must be strictly later than the predecessor's; this is
  enforced by rejecting, at approval time, any `DRAFT → APPROVED` transition
  that would produce a predecessor attaining authority at or after an
  already-attained-authority successor, and independently re-asserted by the
  derivation itself as condition (c) of "attains authority." Together with
  the two uniqueness constraints above, this is what makes temporal
  succession unambiguous and authority history incapable of moving backward,
  by construction of the derivation.
- `WITHDRAWN` is reachable only from `APPROVED`, never from `DRAFT`; an
  unapproved version was never governance-eligible, so abandoning it is not
  a withdrawal and closes no authority window.
- Governance state (`DRAFT`/`APPROVED`/`WITHDRAWN`) is independent of
  technical processing state (ADR 0007); neither is collapsed into the
  other.
- Withdrawal is forward-only; historical `VALID_AT_DATE` queries before a
  withdrawal timestamp are unaffected by it.
- Correcting an already-recorded `approved_at` or `withdrawn_at` requires a
  permission distinct from ordinary approve/withdraw authority, an explicit
  recorded reason, and its own business-audit record; ordinary transitions
  always record the actual current time and are never backdated directly.
- `OrganisationalLocation` is a single, generic, self-referencing,
  workspace-scoped hierarchy — never hard-coded to a fixed number of levels.
- Applicability is recorded per version, as an immutable snapshot at
  creation time; `DocumentFamily` may hold only a mutable default used to
  seed new versions, never consulted when resolving an existing version's
  eligibility.
- Location applicability defaults to `UNIVERSAL` when unset, extends to
  descendant locations, and is always evaluated independently of user
  access.
- ADR-0016's dual gate (published Qdrant point plus PostgreSQL `INDEXED`)
  remains the unconditional prerequisite for retrievability; this ADR's
  governance/temporal gate is additive, never a redefinition of `INDEXED`.
- `DocumentFamily`, version lineage, governance state and
  `OrganisationalLocation` are Laravel/PostgreSQL-owned and mandatorily
  workspace-scoped.

## Scope boundaries

This document does not define:

- `RetrievalPlanner`, `EligibilityResolver`, or the Retriever contract,
  including how `COMPARE`'s two sides are resolved and returned — Stage
  16.2 (ADR-0018);
- the authenticated, bounded, synchronous Laravel-to-Python call contract
  retrieval will need — required Stage 16.2 (ADR-0018) work, explicitly not
  designed here;
- evaluation corpus design, metrics, or quality gates — Stage 16.4;
- hybrid retrieval or reranking — Stage 16.6;
- who is authorised to approve, withdraw, or reschedule a version, beyond
  noting it is an existing-authorisation-model (ADR 0006) question;
- richer approval workflows, delegation, notification, or any
  administrative UI for governance or location management — Phase 19;
- exact table names, migrations, or constraint syntax — Stage 16.1
  implementation, constrained by the invariants fixed here.

These remain open for the phases and stages named above to decide with the
context this ADR establishes.
