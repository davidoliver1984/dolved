# Session Journal: R10-S05 — Normalise Extracted Content

## Date

2026-07-29

## Session mode

Bounded implementation under accepted ADR-0010. This session introduced the
immutable structural-normalisation boundary. It did not connect extraction or
normalisation to the worker, object storage, lifecycle transitions or
persistence, and it did not implement chunking, embeddings or retrieval.

## What was implemented

The Python service gained a separate `app.normalisation` package containing
the immutable `NormalisedDocument` contract, typed derived elements,
normalisation change records and the pure `StructuralNormaliser`.

The normaliser consumes only `ExtractedDocument`. It preserves tenant and
Document identity, extractor provenance, source size, metadata, warnings,
semantic structure, confidence and source locations while producing new
complete text, normalized offsets and deterministic derived UUIDs.

Version-one known-text rules perform NFC composition, CR/LF normalization,
Unicode space-separator replacement, horizontal whitespace cleanup, line-edge
trimming and deterministic empty-line reconciliation. Tables are rebuilt from
independently normalized cells.

Repeated PDF headers and footers are removed only after exact text,
three-page, content-count, semantic-type, page-boundary and geometric
evidence. Every suppression is recorded against the source UUIDs. PDF page
gaps, including blank pages, remain represented in complete text.

Unknown future element types use an immutable fallback that preserves their
kind, source identity, location, conservative text and canonical JSON payload.

## Important implementation decisions

NFC was chosen instead of compatibility normalization. NFC makes canonically
equivalent Unicode deterministic without folding compatibility characters
whose distinctions may be meaningful.

Whitespace policy applies only to known paragraph, heading and table-cell
semantics. Unknown elements receive only NFC and CR/LF handling so future
code, list or other whitespace-sensitive structures are not accidentally
flattened.

Derived UUIDv5 values make repeated normalisation of the same extraction
representation deterministic. They incorporate the source element UUID, so
they do not imply stable identity across separate extraction runs.

Normalised offsets belong to `NormalisedDocument.text`. Original source
locations remain untouched and are explicitly paired with source element
UUIDs, avoiding ambiguity between source and derived coordinate systems.

Repeated header/footer detection uses PDF source-location capability rather
than source media type. This keeps normalisation dependent on canonical
structure instead of dispatching to format-specific implementations.

## Problems and corrections

The first Unicode table fixture contained a composed `é` plus an additional
combining acute accent. NFC correctly preserved both accents, and the failed
assertion exposed the fixture error. The input was corrected to decomposed
`e` plus combining acute, after which it composed to exactly one `é`.

No production defect or dependency change was required after focused review.

## Verification

Focused verification passed:

```text
Structural normalisation: 12 tests
Extraction plus normalisation: 61 tests
Ruff formatting and linting: passed
MyPy focused files: passed
```

Repository-wide verification also passed:

```text
Laravel: 118 tests, 491 assertions; Pint passed across 105 files
Python: 103 tests; MyPy checked 41 source files
Web: 10 tests; ESLint and TypeScript checks passed
Ruff: 42 files formatted and linted
Compose: all 8 processes running; health-checked services healthy
LocalStack: bucket, CORS, queues and redrive policy verified
```

## Important takeaways

* Normalisation is a new immutable representation, not a cleanup mutation of
  extraction output.
* Canonical Unicode equivalence can be made deterministic without using more
  destructive compatibility folding.
* Whitespace rules are semantic policy and should apply only where the
  element type makes their safety explicit.
* Derived offsets and source provenance describe different representations
  and both are needed for traceable citations.
* Conservative structural-noise removal needs strong evidence and an
  auditable record of exactly what was suppressed.
* A safe unknown fallback lets the canonical model evolve without forcing an
  old consumer either to crash or to discard unrecognised content.

## Next step

R10-S05 and the Phase 10 implementation passed human review. Commit the
approved boundary, create annotated tags `phase-10-s05` and `phase-10`, then
begin the R11-S01 chunk-contract architecture session without implementing a
chunker prematurely.
