# Session Journal: R11-S03 — Evaluate Chunking Quality

## Date

2026-07-30

## Session mode

Bounded evaluation of the accepted ADR-0011 baseline. This session added no
new chunking strategy, model, embedding, persistence or retrieval behaviour.

## What was implemented

A CC0 repository-authored corpus now covers short and long plain text, awkward
Unicode and formatting, three pages of prose-heavy PDF content with repeated
page furniture, a multi-section DOCX, and a large structured table.

The evaluation passes PDF and DOCX material through the real extractors and
structural normaliser before chunking. It checks deterministic results, the
hard token bound, complete primary-text reconstruction, exact contribution
spans, inherited source identities and locations, format-specific provenance,
heading adjacency, repeated-page-furniture removal and table row boundaries.

A reusable metric function exposes chunk count, minimum, median, mean and
maximum token counts. The measured results and known limitations are recorded
in `docs/evaluation/r11-s03-baseline-chunking.md`.

## Problems and corrections

The first DOCX expectation accidentally equated heading association with each
heading/body pair occupying a separate chunk. The valid baseline placed all
three short sections in one bounded chunk while keeping each pair adjacent.
The test now checks the meaningful invariant: ordered adjacency.

The first PDF source was structurally representative but too small to be
called prose-heavy. It was expanded to 18 body paragraphs across three pages.
After repeated header/footer removal, it produces two bounded chunks.

## Verification

Focused checks passed for all six evaluation tests.

Repository-wide verification also passed:

```text
Laravel: 118 tests, 491 assertions; Pint passed across 105 files
Python: 123 tests; MyPy checked 49 source files
Web: 10 tests; ESLint and TypeScript checks passed
Ruff: 50 files formatted and linted
Compose: all 8 processes running; health-checked services healthy
```

## Important takeaways

* Structural quality can be tested independently from retrieval relevance.
* Exact primary-span reconstruction is stronger evidence than searching for
  source phrases somewhere in combined chunk text.
* A preferred minimum should not force unnecessary splits or padding.
* Heading association means ordered structural context, not necessarily one
  section per chunk.
* A useful evaluation record includes limitations as prominently as passing
  metrics.

## Next step

R11-S03 and Phase 11 passed human review. Commit and tag the approved boundary,
then begin the R12-S01 embedding-provider architecture session.
