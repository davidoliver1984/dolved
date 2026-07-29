# Session Journal: R10-S02 — Implement Plain Text Extraction

## Date

2026-07-29

## Session mode

Bounded implementation under accepted ADR-0010. This session did not connect
extraction to S3, SQS, the ingestion worker, Laravel lifecycle transitions,
persistence, normalisation or chunking.

## What was implemented

The Python service gained its first canonical extraction boundary. Frozen
Pydantic models represent trusted Workspace and Document context, extractor
identity, warnings, source locations, semantic elements and the complete
`ExtractedDocument`. Unexpected fields are rejected and collections are
immutable tuples.

The first concrete semantic type is `ParagraphElement`. `UnknownElement`
provides the deliberate safe fallback required by ADR-0010 while preserving
the original type name, provenance and any available text.

`PlainTextExtractor` accepts bytes and trusted context. It enforces the 25 MiB
default limit before decoding, accepts strict UTF-8 with an optional BOM,
normalises CRLF and CR to LF, preserves the complete resulting text, emits
ordered blank-line-delimited paragraphs, records character and line
provenance, and gives every element a fresh UUIDv4.

Typed extraction failures identify permanent oversized, invalidly encoded,
empty and whitespace-only inputs using stable machine codes and readable
messages.

## Important implementation decisions

The extractor splits only on LF after the explicitly approved CR/LF
normalisation. Python's `splitlines()` was considered and rejected because it
also treats several Unicode characters as line boundaries. That would move
general whitespace normalisation forward from Stage 10.5 and could alter
meaningful source content.

`source_byte_size` describes the original input, including a UTF-8 BOM.
Character offsets describe the retained decoded and newline-normalised text.
This makes each offset directly sliceable against `ExtractedDocument.text`
while preserving the authoritative input size separately.

Specialised heading, list, table and other element types were not invented for
plain text. They remain for extractor stages that can establish and test their
real source-specific invariants.

No dependency was added because the service already uses Pydantic.

## Problems and corrections

The initial focused check found one Ruff formatting preference and then static
typing exposed that the extensible `ExtractedDocument.elements` collection is
correctly typed at the common `Element` boundary. The tests were adjusted to
narrow paragraph elements explicitly rather than weakening the production
contract.

The first paragraph scanner used Python's broad `splitlines()` helper. Review
identified that it would recognise Unicode separators beyond the agreed CR/LF
rules. It was replaced with LF-only scanning and a regression test now proves
that a Unicode line separator remains unchanged for Stage 10.5.

## Verification

Focused verification passed:

```text
Ruff formatting: 5 files formatted
Ruff lint: passed
Mypy: 5 focused source files, no issues
Pytest: 14 focused tests passed
```

Repository-wide verification also passed:

```text
Laravel: 118 tests, 491 assertions
Python: 56 tests
Web: 10 tests
Formatting, linting and type checking: passed
Compose: all 8 processes running; health-checked services healthy
LocalStack: bucket, CORS, queues and redrive policy verified
```

## Important takeaways

* Byte provenance and character provenance describe different representations
  and should be named and tested accordingly.
* A safe unknown-type fallback can preserve forward compatibility without
  turning the main model into an untyped attributes bag.
* Standard-library convenience functions can contain policy; their exact
  Unicode behaviour matters at a stage boundary.
* Fresh UUIDs make element identity stable within one immutable extraction,
  while deterministic content remains testable independently.

## Next step

R10-S02 passed human review. Commit and tag the approved boundary, then prepare
the bounded R10-S03 PDF extraction brief before changing application code.
