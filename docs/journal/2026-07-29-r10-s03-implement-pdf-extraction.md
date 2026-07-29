# Session Journal: R10-S03 — Implement PDF Extraction

## Date

2026-07-29

## Session mode

Bounded implementation under accepted ADR-0010. The session implemented only
the PDF-to-canonical-extraction boundary. It did not connect extraction to the
worker, S3, SQS, Laravel lifecycle transitions or persistence, and it did not
perform OCR, normalisation or chunking.

## What was implemented

The Python service gained an implementation-neutral `PdfExtractor` protocol,
a minimal composition function and the first concrete
`PdfPlumberExtractor`. Parser-specific objects and exceptions remain private
to that adapter.

The canonical immutable model was extended with PDF source locations,
document metadata, parser identity, source-aware warnings and structured
table, row and cell representations. Existing plain-text extraction continues
to use the shared model.

The extractor supports text-bearing PDFs, deterministic best-effort geometric
ordering, multi-page complete-text assembly, PDF metadata, line-bounded
tables, blank pages and typed permanent failures. Default injectable bounds
limit source size to 25 MiB, page count to 500 and complete extracted text to
5,000,000 Unicode characters.

ReportLab generates controlled PDF fixtures in memory. Nineteen focused tests
cover context and metadata, direct-slice character offsets, page and cell
provenance, tables, ambiguous overlaps, headers and footers, multi-column
ordering, rotation and CropBox provenance, blank and image pages, typed
failures, limits, model validation, immutability and fresh element UUIDs.

## Important implementation decisions

`pdfplumber` is isolated behind a small stable protocol rather than exposed to
downstream code. The initial factory selects it directly; configurable parser
selection and fallback were deliberately not invented.

PDFs do not carry dependable paragraph semantics. Version 1 therefore retains
conservative parser text boxes and orders them by top position, left position
and parser order. This is deterministic and testable, but intentionally not
described as universally correct semantic reading order.

The canonical complete text uses two line feeds between elements and two line
feeds, a form-feed and two line feeds between pages. Table values are
serialised as escaped tab-separated rows. Element offsets are zero-based,
end-exclusive Unicode indexes that directly slice the complete text.

Table-associated text is suppressed only when both geometry and normalized
cell content make the association confident. Ambiguous overlap retains the
text and emits a warning. This favours recoverable duplication over silent
information loss.

Source locations use PDF points in pdfplumber's displayed-page,
top-left-origin coordinate system. Rotation is explicit. A distinct CropBox
is recorded, but version 1 does not discard content outside it.

## Problems and corrections

Current pdfplumber wraps underlying pdfminer failures. The encrypted-file
handler was corrected to inspect the wrapped cause so password-protected PDFs
receive the specific `encrypted_pdf` result while malformed PDFs retain
`invalid_pdf`.

A rendered rotated/cropped fixture showed that the CropBox is metadata
pdfplumber exposes but not necessarily a destructive content boundary. The
model was renamed from an inaccurate “crop applied” flag to the factual
`has_distinct_crop_box`.

Pydantic's `model_copy()` bypasses validation of updated values. A source
location invariant test was corrected to reconstruct through
`model_validate()` so it tests the production validator.

ReportLab fixture typing was resolved with its development-only stubs rather
than suppressing type errors.

## Verification

Focused verification passed:

```text
Ruff formatting and linting: passed
Mypy: focused extraction and fixture files, no issues
Pytest: 33 extraction tests passed (14 plain text, 19 PDF)
```

Repository-wide verification also passed:

```text
Laravel: 118 tests, 491 assertions
Python: 75 tests
Web: 10 tests
Mypy: 31 source files
Ruff: 32 files formatted and linted
Compose: all 8 processes running; health-checked services healthy
LocalStack: bucket, CORS, queues and redrive policy verified
```

The controlled table fixture was inspected with `pdfinfo`, rendered through
Poppler and visually checked. It showed the expected three rows and two
columns. This was supporting diagnostic evidence; programmatic extraction
assertions remain the automated oracle. Temporary render files were removed.

## Important takeaways

* A parser boundary prevents one library's page and geometry objects from
  becoming an accidental platform contract.
* Determinism and semantic correctness are different claims; PDF reading
  order can provide the former without guaranteeing the latter.
* Provenance must say what the parser actually reports. Recording CropBox
  presence is more accurate than claiming it removed content.
* Loss-minimising extraction should preserve ambiguous content and report the
  uncertainty for later stages.
* File, page and text bounds are useful first controls, but untrusted PDF
  parsing still needs process-level time and memory isolation.

## Next step

R10-S03 passed human review. Commit and tag the approved boundary, then
prepare the bounded R10-S04 DOCX extraction implementation without expanding
into normalisation or worker integration.
