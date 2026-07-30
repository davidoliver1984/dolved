# Session Journal: R11-S01 — Define Chunk Contract

## Date

2026-07-30

## Session mode

Architecture and documentation only. No chunk model, chunking strategy or
pipeline code was introduced.

## What happened

Before drafting an ADR, the actual current implementation was inspected
directly (`apps/ai/app/extraction/models.py`, `apps/ai/app/normalisation/models.py`,
the plain-text/PDF/DOCX extractor protocols) to answer a specific
architectural question first: does `NormalisedDocument` already carry the
business identity (`workspace_id`, `document_id`) a `ChunkingStrategy` would
need, or would a separate `ChunkingContext`/`ChunkingInput` wrapper be
required? Confirmed directly from the code that `NormalisedDocument` already
has both fields as required, non-optional attributes, that no
`extraction_id` or other persistent extraction identifier exists anywhere in
the pipeline, and that the codebase's existing `ExtractionContext` pattern
exists specifically because raw byte streams have no identity of their own —
a condition that does not apply to `NormalisedDocument`. This inspection,
plus a pre-existing "Deferred R11-S01 architecture direction" note already
sitting in `IMPLEMENTATION_GUIDE.md`'s Phase 11 section, settled the
boundary question before any ADR text was written: `ChunkingStrategy.chunk(document:
NormalisedDocument) -> ChunkingResult`, no wrapper.

`docs/adr/0011-define-the-chunking-architecture-and-contract.md` was then
drafted and went through two rounds of review before acceptance:

* **Round one** tightened several claims that were either too permissive or
  internally inconsistent: strengthened "no silent text loss" into a real
  completeness requirement (a successful result must account for all
  chunkable content; unrepresentable content is a typed failure, not a
  warning); reconciled determinism with future model-assisted strategies
  (same request to a model does not guarantee the same response; a
  strategy is conformant only if it can actually reproduce its result);
  required both a configuration snapshot and a fingerprint on
  `ChunkingResult`, not "snapshot or fingerprint"; tightened tokenizer
  identity to require enough precision to resolve exact tokenisation
  behaviour; narrowed the rejection of persistent run records to reject
  only making one a *dependency of the pure contract*, not persistence of
  chunk sets/processing attempts/embedding lineage in general; clarified
  that `ChunkingStrategy` produces the semantic result while orchestration
  may own execution telemetry; strengthened the chunk-identity derivation
  material to include final content, not only inputs; defined what an
  "identical" `NormalisedDocument` means (equivalent immutable value, not
  object-instance identity); and fixed a self-referential "Stage 11.1"
  citation, since this ADR *is* Stage 11.1.
* **Round two** was a focused tightening pass: resolved a remaining
  inconsistency where `ChunkingResult` was said to contain model token
  usage/call count/latency/cost even though the surrounding text said
  execution telemetry stays separate — model identity/version and
  consequential parameters are now explicitly semantic (they can change the
  chunk set), while usage/cost/timing are explicitly operational and may be
  captured by orchestration instead; clarified that "no dependency on a
  persistent run record" describes the *invocation* only — a strategy may
  still receive typed configuration, a tokenizer, or other dependencies via
  construction/DI; and tightened the completeness wording from "every part
  of the input a chunker can represent" (which could let an implementation
  define ordinary content out of scope) to "all content the canonical
  normalised model classifies as chunkable," with non-chunkable fields
  requiring an explicit, documented exclusion rule rather than silent
  omission.

The ADR was approved after these rounds with no further changes requested.

## Decisions recorded

`docs/adr/0011-define-the-chunking-architecture-and-contract.md` records, in
its final accepted form:

* Chunking is a deterministic, versioned transformation from one immutable
  `NormalisedDocument` to one immutable `ChunkingResult`, via
  `ChunkingStrategy.chunk(document: NormalisedDocument) -> ChunkingResult`,
  with no context wrapper — grounded directly in the existing
  `ExtractionContext` precedent rather than an assumed pattern. The
  invocation accepts only the document; configuration and implementation
  dependencies are supplied independently, without requiring resolution of
  a persisted pipeline-run entity.
* A pluggable `ChunkingStrategy` abstraction; Phase 11 v1 is one
  deterministic, structure-aware baseline strategy.
* Determinism given identical document/strategy/version/consequential
  configuration, with an explicit reconciliation for model-assisted
  strategies (conformant only if they can actually reproduce their result)
  and an explicit definition of what "identical input" means.
* Immutability of `NormalisedDocument`, `ChunkingResult` and `Chunk`.
* Completeness over silent loss: a successful result accounts for all
  content the canonical normalised model classifies as chunkable; anything
  it cannot safely represent is a typed failure, not a warning; warnings
  remain valid only for genuinely recoverable compromises.
* Chunk provenance tracing back to source `NormalisedElement`(s), and
  deterministically derived (not random) chunk identity from document
  identity, strategy identity/version, configuration fingerprint, ordinal,
  provenance spans and final content — deliberately unlike
  `ExtractedElement`'s fresh-per-run identity, for reasons specific to why
  each stage needs what it needs.
* No dependency on a persistent extraction/chunking "run" record as part of
  the pure contract — narrower than rejecting persistence of chunk sets,
  processing attempts, generations, embedding lineage or audit records
  outright, which remains a legitimate concern for later phases.
* Tokenizer identity recorded once, on the configuration, precise enough to
  resolve exact tokenisation behaviour; each chunk records its own token
  count.
* `ChunkingResult` retains the consequential configuration as both a
  canonical snapshot and a derived fingerprint, and keeps its semantic
  outcome conceptually separate from operational/execution information.

Seven alternatives are recorded with rejection reasoning, including two
added during review to reflect what was actually reconsidered: treating
unplaceable content as a warning-only best-effort result, and making a
persistent run record a dependency of the pure contract.

## Verification performed

* Read `apps/ai/app/extraction/models.py`, `apps/ai/app/normalisation/models.py`,
  and the plain-text/PDF/DOCX extractor protocols directly before answering
  the boundary question or drafting the ADR, rather than assuming the
  answer.
* Read every existing ADR (0001–0010) and the pre-existing "Deferred
  R11-S01 architecture direction" note in `IMPLEMENTATION_GUIDE.md` to
  confirm the `NormalisedDocument`-only signature was already the agreed
  direction, not a new decision being made from scratch.
* Checked the ADR's final form against each Stage 11.1 acceptance criterion
  in `IMPLEMENTATION_GUIDE.md`; all are met, with "tenant identity" recorded
  as superseded by workspace identity per ADR 0006, consistent with how
  prior stages have handled the same stale terminology.
* Re-synced `guide_start_line`/`guide_end_line` references in `tasks.json`
  for Stage 11.1 and its sibling stages after `IMPLEMENTATION_GUIDE.md`
  grew, verifying the new values against the actual file.
* Did not run `make lint` / `make test` / etc. — no application code changed
  in this session, so those checks do not apply.

## Problems or corrections

None from the initial architecture question — inspecting the actual code
before drafting avoided assuming a `ChunkingContext` wrapper was needed when
it was not. The two review rounds were refinements to strengthen precision
and resolve internal inconsistencies (notably the `ChunkingResult`
semantic-versus-telemetry split), not corrections of an error in the
underlying architectural direction.

## Next steps / important takeaways

* Stage 11.2 (Implement Baseline Chunker) can now proceed against a settled
  contract: a deterministic, structure-aware baseline strategy producing
  `Chunk`/`ChunkingResult` values conforming to ADR 0011, with a typed
  failure path for unrepresentable content and a deterministic identity
  scheme still to be concretely designed (namespace, hashing approach,
  field ordering) within that contract's constraints.
* Stage 11.2 will need to decide the concrete "consequential" configuration
  fields (what actually feeds the fingerprint) and the exact tokenizer
  identity representation — both deliberately left open by ADR 0011.
* Stage 11.3's evaluation work depends directly on the determinism and
  derived-identity guarantees just accepted; it should be able to assume
  re-running the baseline chunker against the same document is fully
  comparable.
