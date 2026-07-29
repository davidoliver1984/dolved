# Session Journal: R10-S01 — Define Extracted Document Contract

## Date

2026-07-29

## Session mode

Architecture and documentation only. No Python models, extractors, parser
dependencies, schemas, storage integration or pipeline behaviour were added.

## What was decided

ADR-0010 establishes extraction as a loss-minimisation stage. Semantic
structure, provenance, reading order, hierarchy, page context, metadata and
confidence are preserved wherever the source can provide them. Lossy choices
are deferred until a later stage has enough context to make them deliberately.

The canonical model is composed of typed semantic elements rather than one
flat string or an untyped attributes bag. It is intentionally extensible,
immutable and provenance-aware.

Extraction failures are distinguished as transient or permanent. Permanent
failures carry machine-readable and human-readable diagnostics; all failures
are audited. Non-fatal extraction warnings remain available without blocking
otherwise usable content.

## Boundary clarification

Final review found contradictory ownership in the initial accepted wording:
it said every extractor produced canonical `ExtractedDocument`, while also
saying normalisation converted parser-specific output into that same
representation.

The human developer approved a precise boundary:

* parser-specific models remain private inside their extractor;
* each extractor maps those private objects to immutable
  `ExtractedDocument`;
* deterministic structural normalisation consumes `ExtractedDocument` and
  produces immutable `NormalisedDocument`; and
* chunking consumes only `NormalisedDocument`.

Source format remains available as provenance but must not control chunking
behaviour. Workspace and Document public identities remain document-level
context.

Every semantic element receives a UUID that remains stable for its immutable
representation. A new extraction run creates new UUIDs; deterministic
identity across re-extraction is deliberately not required yet.

## Alternatives rejected

The session rejected parser-specific downstream models, extraction-time
flattening, a third-party parser model as the platform contract, mutable
in-place pipeline objects, an untyped generic block, deferred provenance and
format-specific chunking.

The final clarification also rejected allowing Stage 10.5 to receive parser
library objects. That would move parser coupling out of an extractor rather
than eliminating it.

## Verification

The accepted ADR was checked against CONTRIBUTING.md, the current
Implementation Guide, tasks.json, ADR-0002, ADR-0006, ADR-0007, ADR-0008 and
ADR-0009. The ADR index points to the accepted record.

The Stage 10.1 acceptance criteria were checked explicitly: typed semantic
representation, citation-capable provenance, retained warnings, explicit
failure handling, Workspace context and extractor identity/version are
architectural requirements.

Repository documentation consistency, tracker JSON validity, guide line
references and whitespace were verified before the commit boundary.

The repository-wide gate also passed: formatting, linting, type checking,
Laravel 118 tests with 491 assertions, Python 42 tests, web 10 tests, process
state and LocalStack resource verification.

## Important takeaways

* A parser adapter is not an architectural contract.
* Immutability needs a named output at every stage boundary.
* Format independence means consumers do not branch on format; it does not
  require deleting useful source-format provenance.
* Stable identity within one immutable artefact does not imply identity
  matching across re-extraction.
* Warnings and failures carry different operational meanings and should not
  be conflated.

## Next step

Commit and tag the architecture-only R10-S01 session, then prepare the bounded
R10-S02 plain-text extraction implementation under ADR-0010.
