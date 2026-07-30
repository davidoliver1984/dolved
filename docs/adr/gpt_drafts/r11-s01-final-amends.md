Please make one final focused amendment pass to ADR-0011.

1. Resolve the remaining semantic-result/telemetry inconsistency.

The current ChunkingResult section says the result may contain model token usage, call count, latency and estimated cost, but the following paragraph correctly states that execution telemetry remains separate.

Revise this so:

- ChunkingResult contains the semantic outcome:
  - strategy identity and version;
  - consequential configuration snapshot and fingerprint;
  - chunks;
  - provenance;
  - semantic warnings;
  - and, for model-assisted strategies, concrete model identity/version and consequential parameters because those can affect the chunk set.

- Operational usage and execution information remains separate:
  - wall-clock duration;
  - model call count;
  - input/output token usage;
  - estimated cost;
  - runtime/host details;
  - tracing and processing-attempt data.

These may be recorded by surrounding orchestration or instrumentation, not as part of the semantic ChunkingResult.

Review the Alternatives and Consequences sections for wording that must change to remain consistent with this distinction.

2. Clarify “given nothing but a NormalisedDocument.”

The ChunkingStrategy invocation accepts only a NormalisedDocument, but a strategy may still receive typed configuration, a tokenizer and other implementation dependencies through construction or dependency injection.

Replace wording suggesting the strategy literally has nothing except the document with wording such as:

“No dependency on a persistent extraction or chunking run record: the invocation contract accepts only a NormalisedDocument; strategy configuration and implementation dependencies are supplied independently and must not require resolving a persisted pipeline-run entity.”

Similarly, state that the invocation operates directly on the supplied NormalisedDocument without requiring a reference to or lookup of a persisted extraction or chunking-run entity.

3. Tighten the completeness language.

Avoid “every part of the input a chunker can represent,” because that could let an implementation define away ordinary content.

State instead that a successful ChunkingResult accounts for all content classified by the canonical normalised model as chunkable. Any intentionally non-chunkable structural or metadata fields must be excluded through an explicit, documented rule rather than silently ignored.

Preserve the rest of the ADR. Do not rewrite sections that do not need these amendments.