# Session Journal: R10-S04 — Implement DOCX Extraction

## Date

2026-07-29

## Session mode

Bounded implementation under accepted ADR-0010. The session implemented the
DOCX-to-canonical-extraction boundary only. It did not connect extraction to
the ingestion worker, S3, SQS, Laravel lifecycle transitions or persistence,
and it did not perform image extraction, OCR, normalisation or chunking.

## What was implemented

The Python service gained an implementation-neutral `DocxExtractor` protocol,
a minimal composition function and a concrete `PythonDocxExtractor`.
Parser-specific objects and exceptions remain inside that adapter.

The canonical immutable model was extended with `HeadingElement` and
`DocxSourceLocation`. The latter records body-block and optional table-cell
indexes without inventing unstable rendered page numbers.

The extractor preserves top-level paragraphs and tables in package body order,
retains explicit Word heading levels, creates structured table rows and cells,
maps available core metadata, assembles deterministic complete text and
produces directly sliceable element offsets. Nested table content is flattened
deliberately with a warning. Embedded images also produce an explicit warning
rather than being silently treated as extracted.

The shared 25 MiB input limit and an injectable 5,000,000-character output
limit bound the first implementation. Typed permanent failures cover empty,
invalid, encrypted-or-legacy and excessive inputs.

## Important implementation decisions

`python-docx` 1.2 was selected as a reversible adapter implementation because
its public `iter_inner_content()` API preserves top-level paragraphs and
tables in document order. Downstream consumers depend only on the canonical
model and `DocxExtractor` protocol.

Heading meaning comes only from explicit Heading 1 through Heading 9 styles or
style identifiers. Font size, weight and other visual formatting do not create
semantic headings.

DOCX pagination is a property of a rendering environment, not a stable
property of the source package. Provenance therefore records body and table
structure rather than fabricated page numbers.

Tables use the same escaped TSV complete-text representation as PDF.
Omitted grid positions are represented by empty cells. Merged cells follow
python-docx's documented layout-grid approximation and consequently repeat
the merged value for occupied grid positions.

Nested tables cannot be represented recursively by the current canonical
`TableCell` contract. Their text is preserved deterministically inside the
cell and a warning makes that structural reduction explicit.

## Problems and corrections

The first MyPy run identified inaccurate fixture typing, a missing lxml stub,
an optional heading-level narrowing and optional test-offset arithmetic. Each
was corrected without weakening the production contract or suppressing type
checking.

A later MyPy invocation encountered a SQLite disk I/O error in generated cache
shards on the Docker bind mount. An isolated cache passed immediately. After
removing only the generated Python 3.14 MyPy cache, the normal command passed,
as did the complete repository gate.

Review also identified that a mixed text-and-image document could otherwise
appear fully extracted. The adapter now emits `images_not_extracted` while
retaining its usable text, and a regression test proves that behaviour.

## Verification

Focused verification passed:

```text
Ruff formatting and linting: passed
MyPy: 15 focused source files, no issues
Pytest: 49 extraction tests passed
  plain text: 14
  PDF: 19
  DOCX: 16
```

Repository-wide verification also passed:

```text
Laravel: 118 tests, 491 assertions; Pint passed across 105 files
Python: 91 tests; MyPy checked 37 source files
Web: 10 tests; ESLint and TypeScript checks passed
Ruff: 38 files formatted and linted
Compose: all 8 processes running; health-checked services healthy
LocalStack: bucket, CORS, queues and redrive policy verified
```

## Important takeaways

* Source provenance must follow the source format's real structure; page
  numbers would be misleading for DOCX without a defined renderer.
* Style meaning is stronger evidence than visual appearance for deterministic
  structural extraction.
* A canonical table can preserve useful data while still documenting where a
  source feature, such as nesting or merged spans, was reduced.
* Unsupported non-text content should be visible through warnings rather than
  disappear behind an overly broad claim of successful extraction.
* Generated tool caches can fail independently of the code and should be
  isolated diagnostically before production contracts are weakened.

## Next step

R10-S04 passed human review. Commit and tag the approved boundary, then
prepare the bounded R10-S05 deterministic normalisation implementation.
