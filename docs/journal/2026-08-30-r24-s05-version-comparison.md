# R24-S05 — Version comparison

Date: 2026-08-30

## Functional boundary

R24-S05 added a tenant-authorised, family-bound comparison route and the first
production comparison presentation. Both explicit version identifiers must
belong to the route family. Identical identifiers fail validation. Missing
identifiers resolve only through the accepted current/predecessor or immediate
predecessor/successor rules. A family with no comparable pair returns a truthful
unavailable state, while a deleted version retains safe metadata and reports
its extracted content unavailable.

The content response consumes only ADR-0032's active published extraction
projection. It is bounded to 500 ordered elements per side and 100 warnings,
surfaces truncation and extraction warnings, and never rebuilds content from
retrieval chunks. A development-only representative fixture exercises the same
production component without representing workspace evidence.

## Bounded structural-dependency review

The requested final comparison experience is not purely an S09 styling task.
The current payload/data boundary was assessed as follows:

| Capability | Current support | Required handover |
| --- | --- | --- |
| Section and paragraph alignment | Projection headings, levels, paragraphs, tables and ordering are sufficient inputs, but equal-ordinal pairing is not reliable alignment | Add deterministic section-aware alignment to the bounded comparison adapter |
| Word-level additions/removals | Both aligned texts are present | Derive accessible word spans in the web layer only after backend alignment |
| Added, removed and modified content | Present for equal ordinals | Recompute from aligned pairs rather than positional coincidence |
| Moved content | Not represented | Add a `moved` classification with before/after ordinals and stable pair identity |
| Unchanged-context collapsing | Unchanged rows exist | Add group/count metadata and implement accessible expand/collapse in the final UI |
| Formatting-only changes | ADR-0032 deliberately retains normalised search structure, not inline formatting | Report formatting comparison explicitly unavailable; actual detection requires a separately reviewed ADR-0032 extraction-contract extension and re-extraction strategy |
| Unreliable/unavailable comparison | Per-side content availability, warnings and truncation exist, but alignment reliability is implicit | Add an explicit `reliable`, `partial` or `unavailable` alignment state and typed reason |

The smallest bounded extension needs no persistence migration: extend the
comparison adapter and its web type with an algorithm version, explicit
alignment state/reason, section-aware paired units, before/after ordinals,
`added`/`removed`/`modified`/`moved`/`unchanged` status and summary counts.
Formatting signals are outside that bounded extension and must not be invented.

This work is allocated to the start of R24-S09, before the consolidated
comparison design/flow review. That review also owns the document-friendly
side-by-side desktop and inline/mobile modes, change filters and navigation,
collapsed unchanged context, restrained word highlighting, accessible
labels/tooltips, and consolidated button, spacing and card treatment. The item
is duplicated in the R24-S09 `tasks.json` design handover so it cannot disappear
between sessions.

## Visual checkpoint

David recorded the following acceptance boundary:

> Functional checkpoint passed. Current visual treatment is a strong
> provisional foundation. Final comparison-page visual and end-to-end flow
> sign-off is deferred to the consolidated S09 review.

## Verification

- focused Laravel version-governance/comparison suite: 9 tests, 68 assertions;
- Laravel Pint: passed for the comparison controller, route and focused test;
- web ESLint and TypeScript: passed;
- web suite: 30 files, 129 tests;
- representative comparison route: HTTP 200 with changed, added and removed
  states rendered through the production component;
- `git diff --check`: passed;
- no providers called.
