# R11-S03 Baseline Chunking Evaluation

## Purpose

This evaluation checks whether the ADR-0011 baseline structural strategy is a
safe, inspectable starting point for retrieval. It evaluates deterministic
boundaries, size, completeness and provenance. It does not claim to measure
answer relevance, embedding quality or retrieval recall, which require later
phases.

The source material in
`apps/ai/tests/fixtures/chunking/corpus.json` is repository-authored synthetic
content released as CC0-1.0. PDF and DOCX containers are generated during
tests, avoiding third-party binary fixtures.

## Expected structural behaviour

| Case | Expected behaviour |
|---|---|
| Short plain text | Remains one chunk; both paragraphs and locations survive |
| Long plain text | Produces multiple bounded chunks with deterministic recorded overlap and no primary loss |
| Awkward plain text | Preserves valid Unicode and all content through normalisation and tokenizer-safe fallback boundaries |
| Prose-heavy PDF | Removes repeated header/footer furniture, retains page reading order and PDF locations |
| Multi-section DOCX | Retains heading/body order and association plus DOCX locations |
| Table | Produces multiple bounded chunks, preferring complete row boundaries and preserving table provenance |

## Observed distribution

The approved default profile is 400 target tokens, 512 maximum, 64 overlap
tokens and a preferred 100-token minimum.

| Case | Chunks | Minimum | Median | Mean | Maximum |
|---|---:|---:|---:|---:|---:|
| Short plain text | 1 | 25 | 25 | 25 | 25 |
| Long plain text | 4 | 337 | 428.5 | 414 | 462 |
| Awkward plain text | 1 | 48 | 48 | 48 | 48 |
| Prose-heavy PDF | 2 | 316 | 356 | 356 | 396 |
| Multi-section DOCX | 1 | 87 | 87 | 87 | 87 |
| Table | 3 | 224 | 400 | 362.67 | 464 |

All measured chunks remain below the 512-token hard bound. The short and DOCX
cases appropriately remain intact rather than being padded or split merely to
reach the preferred minimum.

## Integrity checks

The automated evaluation:

* repeats each chunking operation and compares the complete result;
* reconstructs each element solely from ordered primary contribution spans;
* compares reconstructed text byte-for-byte with normalised element text;
* verifies each contribution slices identical text from its chunk and source
  element;
* verifies source-element UUIDs and immutable source locations are unchanged;
* checks concrete PDF and DOCX location types survive into chunk provenance;
* checks headings remain immediately adjacent to their following paragraphs;
* checks oversized table primary fragments end at row boundaries; and
* exposes chunk count, minimum, median, mean and maximum token counts as JSON.

## Success criteria

Stage 11.3 is considered successful when:

* the baseline strategy satisfies the structural guarantees defined by
  ADR-0011;
* repeated executions produce identical chunk sets and identical chunk
  identifiers;
* all completeness, provenance, ordering and determinism checks pass;
* no chunkable content is silently lost;
* the observed distribution provides a stable baseline against which future
  strategy or configuration changes can be compared.

The purpose of this stage is not to prove that the baseline strategy is
optimal. It is to establish a deterministic, reproducible reference
implementation and evaluation baseline for future chunking strategies.

## Known limitations

* This is a synthetic engineering corpus, not a statistically representative
  benchmark of customer documents.
* Structural integrity is measured; semantic answer quality, retrieval recall,
  ranking quality and citation presentation are not yet measurable.
* The canonical model does not yet expose dedicated list, code, quote,
  footnote or image-caption element types, so this evaluation cannot assess
  specialised policies for them.
* The PDF sample contains extractable text and does not evaluate OCR, complex
  multi-column reconstruction or scanned documents.
* The DOCX sample evaluates explicit heading styles and ordered body content,
  not every Word layout feature.
* Tables are serialised text with row-aware splitting; repeated header-row
  enrichment and more sophisticated table semantics are deferred.
* The corpus establishes a reproducible baseline, not evidence that the
  current 400/512/64/100 profile is optimal. Later retrieval evaluation may
  justify a new versioned configuration or strategy.

## Reproduction

```bash
docker compose exec -T ai uv run pytest tests/test_chunking_evaluation.py -q
```

The inspectable JSON distribution command is recorded in Stage 11.3 of
`IMPLEMENTATION_GUIDE.md`.
