# Session Journal: R11-S02 — Implement Baseline Chunker

## Date

2026-07-30

## Session mode

Bounded implementation under accepted ADR-0011. This session implemented the
chunk contract and one deterministic structural strategy. It did not connect
chunking to the ingestion worker, persist chunks, generate embeddings, select
a model or vector database, or evaluate retrieval quality.

## What was implemented

The Python service gained an `app.chunking` package containing immutable
configuration, chunk, provenance, warning and result models; the
`ChunkingStrategy` and `Tokenizer` protocols; a pinned tiktoken adapter; typed
chunking failures; and the baseline structural strategy.

The default semantic profile targets 400 tokens, enforces a 512-token maximum,
uses up to 64 tokens of recorded overlap and treats 100 tokens as a preferred,
not mandatory, minimum. The tokenizer is tiktoken 0.13.0 with the explicit
`o200k_base` encoding. The result records this identity, the loaded vocabulary
fingerprint, the typed configuration snapshot and its canonical fingerprint.
The hash-verified vocabulary is cached during the container build so worker
execution does not introduce a hidden runtime network dependency.

Whole normalised elements are grouped while they fit. Oversized paragraphs
prefer sentence, line and word boundaries; oversized tables prefer complete
rows. Heading association is preserved when possible. Stable UUIDv5 chunk
identities incorporate the complete semantic identity material required by
ADR-0011.

## Important implementation decisions

Primary content and overlap have separate provenance roles. Completeness is
defined only by primary spans, so repeated overlap can never conceal a missing
part of the source.

Provenance carries both the normalised-element identity and its inherited
source-element identities and locations. Element-local and chunk-local
character spans make every contribution directly inspectable and prepare the
data for later citation work without designing citations in this phase.

The implementation checks completeness as a postcondition. Every non-empty
chunkable element must be covered from character zero to its end by contiguous
primary spans. Failure produces `UnrepresentableContentError` instead of a
partial successful result.

Unknown elements with text use the conservative baseline path. Unknown
elements containing only a preserved structural payload are explicitly
non-chunkable and produce a warning. This is the deliberate safe fallback
carried forward from Phase 10.

No model identity is recorded because no model participates. Runtime duration,
call counts, execution token usage and cost remain operational telemetry
outside the semantic result.

## Problems and corrections

The first focused MyPy run reported that the private piece role was typed as
an unrestricted string while the public contribution contract accepts only
`primary` and `overlap`. The private type was narrowed to the same literal
union and all focused checks then passed.

An explicit small-final-chunk test showed that the preferred-minimum warning
initially counted overlap as though it were new primary content. It now
measures primary content only, ensuring contextual repetition cannot hide an
undersized new retrieval unit.

The Implementation Guide's pre-ADR note still described operational duration
and model usage as fields of `ChunkingResult`. It was corrected to reflect
ADR-0011 before the implementation record was written.

## Verification

Focused verification:

```text
Baseline chunking: 14 tests passed
Offline tokenizer load from rebuilt AI image: passed
Ruff formatting and linting: passed
MyPy focused files: passed
```

Repository-wide verification:

```text
Laravel: 118 tests, 491 assertions; Pint passed across 105 files
Python: 117 tests; MyPy checked 48 source files
Web: 10 tests; ESLint and TypeScript checks passed
Ruff: 49 files formatted and linted
Compose: all 8 processes running; health-checked services healthy
```

## Important takeaways

* A token limit is reproducible only when the exact tokenizer behaviour is
  attributable, not merely when an encoding has a familiar name.
* Primary-versus-overlap provenance turns “no text loss” into a property that
  can be verified rather than inferred from chunk text.
* A preferred minimum is a quality signal, while the maximum is a correctness
  boundary.
* Structural chunking benefits from semantic elements even before more
  sophisticated strategies exist.
* Deterministic identifiers make changes to content or consequential
  configuration visible and comparable.
* Model identity belongs only to strategies whose output a model actually
  influences.

## Next step

R11-S02 passed human review. Commit and tag the approved boundary, then begin
R11-S03 evaluation of chunking quality against representative documents.
