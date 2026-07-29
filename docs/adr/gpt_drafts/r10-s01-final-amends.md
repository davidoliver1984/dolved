# R10-S01 — Final Amendments

ADR-0010 was accepted subject to one boundary clarification discovered before
Phase 10 implementation began.

## Agreed clarification

The architecture distinguishes three immutable boundaries:

1. Each extractor owns its parser-specific models privately and maps them to
   the canonical `ExtractedDocument`. Parser-library objects never escape the
   extractor.
2. Deterministic structural normalisation consumes `ExtractedDocument` and
   produces a new immutable `NormalisedDocument`.
3. Chunking consumes only `NormalisedDocument`.

Source format may remain available as provenance for citations, auditing and
debugging, but chunking must not branch on it.

Public Workspace and Document identities remain document-level context rather
than being repeated on every semantic element.

Every element has a UUID that remains stable for the lifetime of its immutable
representation. Each new extraction run creates new element UUIDs.
Deterministic identity across separate extraction runs is not required at this
stage, and the exact generation and derived-element linkage strategy remains
an implementation decision.

The accepted Stage 10.1 requirements also make non-fatal extraction warnings
explicit: warnings remain diagnostic evidence on the immutable extraction
output and are not silently discarded or automatically treated as failures.

These amendments resolve the ambiguity between “every extractor produces the
canonical representation” and “normalisation creates the canonical
representation” without changing ADR-0010's loss-minimisation principle.

## Final wording review

Normalisation must not discard meaningful semantic information, but it may
remove or reconcile semantically empty, duplicated or parser-generated
structural noise under explicit deterministic rules while preserving
provenance and traceability. Blank paragraphs and meaningless formatting noise
therefore do not become permanent artefacts merely because the architecture
protects semantic information from premature loss.

Immutability prevents later stages from altering earlier representations
through normal application behaviour and reduces accidental or unauthorised
pipeline mutation. It complements rather than replaces storage access
controls, integrity checks and auditing.

Every consumer must provide a deliberate safe fallback for future or currently
unrecognised `Element` types instead of failing unexpectedly.
