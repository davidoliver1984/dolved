# Session Journal: R16-S01 — Define Document Versioning and Temporal Authority

## Date

2026-08-07

## Session mode

Architecture and documentation only. No application code, migrations,
models, HTTP endpoints, or retrieval code were introduced.

## What happened

Before any Phase 16 ADR was drafted, an independent architectural review of
the proposed Phase 16 direction was carried out — covering document
versioning, `RetrievalPlanner`, `EligibilityResolver`, the retrieval
contract, metadata, outcomes, evaluation and reranking. That review found
that Phase 16's originally-scoped first stage, "Define Document Freshness
and Archival Policy," understated what retrieval actually needs: `CURRENT`,
`VALID_AT_DATE` and `COMPARE` retrieval require a genuine versioning
decision, not an archival flag — and ADR-0007 had already named this exact
shape (*"a future ADR could add an explicit relationship between documents…
once a genuine requirement exists"*) as the anticipated future decision it
was deliberately deferring, not rejecting. Stage 16.1 was rescoped
accordingly, and the Phase 16 stage sequence was revised to seven stages
(R16-S01 through R16-S07), matching ADR-0013's already-committed promise
that evaluation and reranking each receive their own ADR.

A first full draft of ADR-0017 followed a detailed, fully-specified brief:
`DocumentFamily` as the stable identity every Document belongs to (exactly
one, no optionality); explicit, linear, immutable version lineage (a chain,
not a branching graph); `CURRENT` defined as `VALID_AT_DATE` evaluated at
the present moment; a minimal, orthogonal governance state model
(`DRAFT → APPROVED → WITHDRAWN`); per-version location applicability via a
generic, self-referencing `OrganisationalLocation` hierarchy; and explicit
confirmation that ADR-0016's dual retrieval-visibility gate is unchanged,
with this ADR's model applying as a third, additive gate.

Three rounds of bounded amendment followed, each closing one specific
correctness gap found on review rather than reopening already-agreed
decisions:

- **Round 1** corrected a predecessor-resurrection bug in the first draft's
  temporal-authority derivation: a naive "latest eligible version not later
  than T" rule, re-evaluated fresh at every query, could silently resurrect
  a withdrawn version's predecessor. Fixed by introducing "attained
  authority" — a version only closes its predecessor's authority window if
  its effective date was actually reached while it was still
  governance-eligible — and deriving authority as a window,
  `[effective_from, end)`, rather than a fresh "latest row" query. This
  round also moved location applicability from family-level mutable state
  to an immutable per-version snapshot, and retitled a section from
  "no overlapping periods" to "unambiguous temporal succession" after review
  found the original claim did not logically follow from the stated
  constraint.
- **Round 2** closed a second gap: the round-1 derivation keyed authority
  purely to `effective_from`, which let a late approval retroactively
  authorise dates before approval genuinely happened. Fixed by introducing
  `approved_at` as an explicit, required, non-derived timestamp alongside
  `effective_from`, and redefining `authority_start = max(effective_from,
  approved_at)`. This round also made `withdrawn_at` explicit, and added
  backdated-governance-correction guardrails — elevated permission, an
  explicit reason, and a distinct business-audit record — so historical
  `VALID_AT_DATE` answers can never be silently rewritten.
- **Round 3** closed a third, narrower gap exposed by round 2's own change:
  unique `authority_start` values do not guarantee they fall in the same
  order as explicit lineage. A predecessor whose approval is delayed can end
  up with a later `authority_start` than its own successor's, which would
  let an older version attain authority after its successor already had —
  moving authority history backward. Fixed by adding lineage-monotonic
  ordering as a third structural constraint (`authority_start(successor) >
  authority_start(predecessor)` for any lineage-related pair that both
  attain authority), enforced by rejecting the offending `DRAFT → APPROVED`
  transition outright at approval time, with the derivation itself
  independently re-asserting the same rule as a defence-in-depth backstop.
  This round also corrected several passages that described withdrawing a
  "not-yet-approved" version — inconsistent with the stated
  `DRAFT → APPROVED → WITHDRAWN` state machine, since `WITHDRAWN` is
  reachable only from `APPROVED` — by making explicit that an abandoned,
  unapproved `DRAFT` is not a withdrawal at all.

The ADR was accepted after the third round with no further changes
requested.

## Decisions recorded

`docs/adr/0017-define-document-versioning-and-temporal-authority.md`
records, in its final accepted form, everything summarised in
`IMPLEMENTATION_GUIDE.md` Stage 16.1's Decision section — `DocumentFamily`
and linear version lineage; the `CURRENT`/`VALID_AT_DATE` derivation and its
`authority_start`/`authority_end` formulas; the three structural constraints
unambiguous succession depends on; the minimal governance state model and
backdated-correction guardrails; per-version applicability snapshots over
a generic `OrganisationalLocation` hierarchy; and the unchanged interaction
with ADR-0016's dual retrieval-visibility gate — not duplicated here.

Stage 16.1's title and scope in `IMPLEMENTATION_GUIDE.md`, `tasks.json` and
`PROJECT_ROADMAP.md` are corrected from "Define Document Freshness and
Archival Policy" to "Define Document Versioning and Temporal Authority" to
match what was actually decided and accepted.

## Verification performed

* An independent architectural review of the proposed Phase 16 direction
  was conducted before any ADR drafting began, covering document
  versioning, `RetrievalPlanner`, `EligibilityResolver`, the retrieval
  contract, evaluation and reranking, resulting in the revised
  R16-S01–R16-S07 sequence.
* Each worked temporal-authority example (predecessor resurrection,
  retroactive authorisation via late approval, and lineage-order violation
  via a delayed predecessor approval) was traced by hand to confirm each
  correction actually closes the failure mode it targets.
* Checked the accepted ADR against each Stage 16.1 acceptance criterion in
  `IMPLEMENTATION_GUIDE.md`; all are met.
* Confirmed, after each amendment round and again before acceptance, that
  only the ADR file itself had changed and that no other accepted ADR or
  application code was modified.
* Resynchronised `tasks.json`'s `guide_start_line`/`guide_end_line`
  references for every stage from R16-S02 through R23-S03 against the
  actual, larger Stage 16.1 record, and verified structurally: unique
  phase/session identifiers, every session's recorded start line matching
  its actual heading text in `IMPLEMENTATION_GUIDE.md`, and no inverted or
  overlapping line ranges. Pre-existing header/line mismatches in phases
  R00–R13, unrelated to this session, were confirmed present before this
  change and left untouched as out of scope.
* Did not run `make lint` / `make test` / etc. — no application code
  changed in this session, so those checks do not apply.

## Problems or corrections

Three rounds of bounded amendment were required before acceptance, each
addressing a genuine correctness gap rather than a stylistic preference:
predecessor resurrection (round 1), retroactive authorisation through late
approval (round 2), and lineage-order violation through delayed predecessor
approval (round 3). Each was found on independent review, not during
implementation, and each amendment was scoped narrowly to the specific gap
identified — no unrelated decision already agreed in an earlier round was
reopened.

## Next steps / important takeaways

* Stage 16.2 (Define Retrieval Contract, ADR-0018) now has a settled,
  honestly-derived definition of "authoritative" to build
  `RetrievalPlanner`, `EligibilityResolver` and the Retriever contract
  against, including the confirmed query-decomposition boundary, `COMPARE`
  contract, and the Laravel-owned `EligibilityResolver` call sequence
  agreed during the pre-drafting review.
* Stage 16.1 implementation (deferred to whichever session actually builds
  the schema — this ADR fixes the domain model and invariants, not table
  names or migrations) inherits explicit, tracked obligations: a backfill
  migration for every existing Document into a single-Document family; the
  `effective_from` and `authority_start` uniqueness constraints (the latter
  re-checked at approval time); the lineage-monotonic approval-time
  validation; and the backdated-correction permission/audit path.
* Two remaining pre-existing `IMPLEMENTATION_GUIDE.md` cross-reference
  errors (Stage 16.5's acceptance criteria and Stage 16.6's objective, both
  referencing a stale "15.x" stage number from before the Phase 15
  restructuring) were corrected as part of this update; no other stage
  content was altered.
* Document deletion, evaluation corpus design, and hybrid retrieval/
  reranking remain out of this ADR's scope, as recorded in its own
  "Scope boundaries" section — nothing here should be read as having
  decided them.
