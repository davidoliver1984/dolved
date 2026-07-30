Please revise the proposed ADR-0011 using the following review findings. Preserve the overall structure and the strong existing reasoning; this is a focused amendment pass, not a rewrite from scratch.

1. Strengthen the no-text-loss invariant.

The current wording says content that cannot be placed may be surfaced as a warning. A warning must not make incomplete successful output acceptable.

State that a successful ChunkingResult must account for all chunkable normalised content. Content may be repeated only through deliberate, recorded overlap or contextual enrichment. If content cannot be represented safely without loss, chunking must fail with a typed error rather than return an incomplete result.

Warnings remain valid for recoverable compromises such as splitting an oversized element, missing a preferred target size, table-specific compromises or explicit fallback handling.

2. Reconcile determinism with future model-assisted strategies.

Keep reproducibility as a strong architectural requirement, but explain that merely sending the same request to an external model does not guarantee determinism.

A future model-assisted strategy satisfies the contract only if the concrete model identity/version, consequential parameters and model-produced decisions are sufficiently fixed, retained or cached to reproduce the semantic chunk set. A strategy that cannot provide this must not claim conformance to the deterministic ChunkingStrategy contract.

3. Require both configuration snapshot and fingerprint.

ChunkingResult should conceptually retain:
- the typed, canonical consequential configuration snapshot; and
- a deterministic fingerprint derived from it.

The snapshot supports inspection and replay. The fingerprint supports comparison and deterministic identity derivation.

Replace any “snapshot or fingerprint” wording with “snapshot and fingerprint.”

4. Tighten tokenizer identity.

Retain tokenizer identity once in the chunking configuration, not per chunk.

Add that tokenizer identity must be precise enough to resolve the exact tokenisation behaviour used, including vocabulary/model revision or implementation version where required. Do not prescribe the exact representation in this ADR.

5. Narrow the rejection of persistent run records.

Do not permanently reject all persisted chunking-run or chunk-set records.

Reject only making a persistent extraction/chunking run entity or identifier a dependency of the pure ChunkingStrategy contract.

Extraction remains a transient transformation with no persistent extraction identifier.

Persistence of chunk sets, processing attempts, active/historical generations, embedding lineage or operational audit records remains deferred to later orchestration, vector-storage and operations phases.

6. Clarify semantic result versus telemetry ownership.

ChunkingStrategy produces the semantic ChunkingResult.

Wall-clock duration, machine/runtime details, tracing data and processing-attempt telemetry may be captured by orchestration or instrumentation around the strategy rather than necessarily constructed by the strategy itself.

Keep semantic identity/version/configuration facts separate from incidental execution telemetry.

7. Strengthen deterministic chunk identity material.

State that chunk identity is derived from:
- normalised-document identity material;
- strategy identity and version;
- consequential configuration fingerprint;
- ordinal;
- ordered provenance spans; and
- final semantic chunk content or its canonical digest.

The exact hashing/UUID namespace/field-ordering scheme remains an implementation detail constrained by the ADR.

8. Clarify what identical input means.

State that an identical NormalisedDocument means an equivalent immutable input value, including identity-bearing fields, ordered elements, provenance and content—not merely the same in-memory object instance.

9. Correct the Stage 11.1 implementation reference.

The exact identity derivation scheme is not an implementation detail “for Stage 11.1's model definition,” because this ADR is Stage 11.1. Refer to it as an implementation detail for Stage 11.2, or simply as an implementation detail constrained but not fixed by this ADR.

10. Review all related sections for consistency.

Update as needed:
- Decision
- Determinism
- Chunk identity
- Token counting
- ChunkingResult
- Architectural invariants
- Alternatives considered
- Consequences
- Scope boundaries

Do not add exact chunk sizes, overlap rules, class schemas, database persistence design, citation lifecycle or re-extraction lifecycle. Those remain deferred.