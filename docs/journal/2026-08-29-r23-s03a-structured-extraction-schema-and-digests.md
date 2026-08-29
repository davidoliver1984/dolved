# R23-S03a — Structured extraction schema and digests

## Outcome

The provider-free ADR-0032 canonical structured-extraction identity foundation
is complete.

Implemented:

- a language-neutral `DocumentExtractionArtifact` V1 JSON Schema covering the
  real normalised-document field set;
- explicit exclusion of workspace, document, family and storage ownership from
  canonical bytes;
- Python assembly from `NormalisedDocument` without changing the object
  chunking consumes;
- RFC 8785 canonicalisation in Python and PHP, including lowercase hyphenated
  UUIDs, unchanged Unicode code points, valid UTF-8, finite-number enforcement
  and the ECMAScript number-format boundary;
- SHA-256 identities for the complete artefact, the complete ordered element
  projection manifest and the extraction-warning manifest;
- shared cross-language vectors covering composed and decomposed Unicode,
  emoji, every source-location variant, multi-row/multi-column tables, unknown
  elements, null versus absence, confidence boundaries, warnings and changes;
- full-precision numeric conformance vectors added after two candidate PHP
  packages were rejected for rounding or notation drift.

This stage did not add upload authorisation, object persistence, worker
acknowledgement, orphan sweeping, relational projection publication or source
delivery. Those remain owned by R23-S03b–S03d.

## Verification

- Python structured-artifact tests: 5 passed.
- PHP structured-artifact tests: 5 passed, 18 assertions.
- Full Python suite: 634 passed, 4 skipped.
- Ruff lint and formatting: passed.
- Mypy: passed for 227 source files.
- Full Laravel suite in the configured Compose runtime: 391 passed, 3 skipped,
  2,081 assertions.
- Laravel Pint: passed for 498 files.
- JSON Schema validation passed independently in both Python and PHP.
- `git diff --check`: passed.

A host-local Laravel invocation was non-authoritative and failed because it did
not have the Compose contract mounts and configured test storage. The same full
suite passed in the repository's configured API container.

No provider calls were made. Retrieval, planner, generation, threshold,
calibration, benchmark, held-out, chunking and existing ingestion behavior were
unchanged.

## Next

R23-S03b may implement the lease-bound upload authorisation record, typed worker
acknowledgement and bounded orphan recovery against these frozen identities.
