# Design Corrections Register

Canonical register for Phase 29 (Product-wide Integration and Visual
Acceptance). This is the single place David's evolving design-corrections
list is recorded, classified and tracked through to resolution. It is not a
place to record new functionality ideas or architectural proposals — those
are reported and allocated to their own future session or ADR instead (see
"Classification" below).

This register does not reopen or invalidate any completed, approved session
or gate. A prior visual approval recorded in a session journal or in
`tasks.json` was a valid, final approval **for the surface it reviewed at the
time**. An entry here is a genuine, additional, later-discovered or
later-arising item — never a claim that an earlier checkpoint failed.

**On historical browser evidence, stated explicitly**: Phase 24 and Phase 25
browser reviews were actually performed, and their approvals are fully
documented in their own session journals and in `tasks.json`'s completion
evidence. Screenshots from those live reviews were not retained as
repository assets — only Phase 21 has persisted reference images under
`docs/assets/design/`. The absence of retained screenshots for Phase 24/25
does not invalidate, weaken or reopen those completed reviews or
approvals; it is a gap in what was archived, not a gap in what was
reviewed. The same applies to Phase 26 (`docs/journal/2026-09-01-r26-s04-phase-closure.md`):
David explicitly approved the complete ADR-0035 bulk-operations surface on
1 September 2026 against the development-only fixture route, and the
real ADR-0034 `ImportBatch`-to-bulk-approval Playwright journey passed
against genuine staged/promoted documents — no Phase 26 screenshots were
retained either, for the same reason Phase 24/25's were not. That approval
was a valid, final approval for the surface as reviewed at the time; it was
a staged, fixture-backed checkpoint, not yet a review of the surface as
part of the connected product, which is exactly the review layer Phase 29
adds — this is not a claim that the Phase 26 checkpoint failed or needs to
be repeated as a correction. Phase 29 must collect and retain its own
staged browser evidence for its new, connected-product review — that
requirement is about Phase 29's own evidence standard going forward, not a
retroactive judgement on Phase 24/25/26's already-valid approvals.

## How to use this register

1. Add an entry for every item David raises, using the schema below, with
   `status: proposed` and no classification yet.
2. At the start of Phase 29 (Stage 29.1), classify every entry using the
   scheme below, and record which Phase 29 stage (or which future
   session/ADR) owns it.
3. Update `status` as work proceeds. Only mark `resolved` once David has
   approved the correction with visual evidence where the entry is
   visual/interaction in nature.
4. Never delete a resolved entry — leave it in the table as a record of what
   was corrected and when.

## Classification

| Classification | Meaning | Where it is handled |
|---|---|---|
| `cosmetic` | Visual/interaction inconsistency (spacing, colour, copy, button style, tooltip, empty/error-state wording, etc.) | Corrected inside Phase 29 |
| `defect` | The implementation does not do what its own accepted ADR/session already specified | Corrected inside Phase 29 (or escalated if it is large enough to need its own session) |
| `new_capability` | Something Dolved does not do today and no accepted ADR specifies | Reported and allocated to its own future session — never corrected as "polish" |
| `architectural` | Requires a durable, ADR-level decision (a new contract, a schema change with real alternatives, a cross-service boundary change) | Reported and allocated to its own future ADR — never corrected as "polish" |

## Register

| ID | Surface/journey | Issue | Classification | Priority | Dependency | Expected result | Status | Visual evidence | David approval |
|---|---|---|---|---|---|---|---|---|---|
| DC-001 | Version comparison — formatting-only change detection | The comparison page correctly and honestly reports formatting-only changes (bold/italic/etc.) as explicitly unavailable, because the accepted ADR-0032 extraction projection does not retain inline-formatting signals (verified: `docs/journal/2026-08-30-r24-s05-version-comparison.md`, confirmed still unavailable by design at closure in `docs/journal/2026-08-30-r24-s09-phase-closure.md`). | `architectural` | — (not Phase 29 scope) | Requires a separately reviewed ADR-0032 extraction-contract extension and a re-extraction strategy before formatting-only detection can exist at all. | Formatting-only detection remains truthfully unavailable under the current accepted ADR-0032 projection. If a future ADR explicitly extends the extraction contract and provides a re-extraction strategy, the comparison may then identify supported formatting-only changes. Until that separate work is approved and implemented, the existing unavailable state remains the correct result. | not_started — reported, not allocated to a session | none (no visual defect; current behaviour is the correct, honest state) | not required for Phase 29 because it is outside Phase 29 scope |

No other evidence-backed, still-open item was found in the Phases 24–25
review that seeded this register (see the Phase 24/25 review summary in the
session that created this file), and the subsequent Phase 26 review found no
evidence-backed, unresolved cosmetic issue, functional defect, or new
architectural/capability item either — every R26 session's completion
evidence, journal and Playwright result was verified directly against the
implemented PostgreSQL role foundation, bulk domain/execution code and web
surfaces, with no discrepancy found. DC-001 is recorded here for visibility
and
continuity only — it is explicitly **not** a Phase 29 correction, since
Phase 29 corrects cosmetic issues and defects, never architectural gaps. It
should be re-triaged into its own future ADR-backed session rather than
picked up inside Phase 29.

---

## David's design-corrections list (pending)

*This section is intentionally empty pending David's own list. Do not add
entries on his behalf. When he provides his list, add each item to the
Register above with `status: proposed`, then classify it at the start of
Phase 29 (Stage 29.1) using the table above — do not classify it in advance
of that review.*
