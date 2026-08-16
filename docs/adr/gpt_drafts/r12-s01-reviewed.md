I've reviewed ADR-0013 carefully.

Overall assessment: approved in principle.

I think this is one of the strongest ADRs in the repository so far. It feels like the natural successor to ADR-0011: it defines a clear architectural boundary, records the engineering reasoning rather than simply selecting a provider, and stays consistent with the style established by ADR-0010, ADR-0011 and ADR-0012.

Before I accept it as final, I'd like a few small refinements.

## 1. Strengthen the Voyage rationale by explicitly connecting it to the evaluation philosophy

The current rationale correctly explains why Voyage is the preferred V1 provider.

I'd like one additional paragraph making it explicit that this decision is evidence-based rather than permanent.

Something along the lines of:

- Voyage is selected because it currently best satisfies the project's priorities around retrieval quality and operational simplicity.
- This is not treated as an irreversible architectural commitment.
- The repository-owned evaluation harness planned for Phase 15 exists specifically so future embedding providers can be compared objectively against the active provider before any migration decision is made.
- Provider replacement therefore remains an engineering decision supported by measurable evidence rather than opinion.

The goal is to connect Phase 13 directly to the evaluation philosophy we're introducing later.

---

## 2. Expand the batching rationale slightly

The current Batch Contract explains the mechanics.

I'd like one additional sentence explaining *why* batching exists.

For example:

- batching reduces request overhead and improves throughput;
- while preserving the architectural guarantee that every returned vector remains attributable to exactly one originating chunk.

The section should explain both the operational and architectural purpose.

---

## 3. Make the nature of embeddings explicit

One concept I'd like called out explicitly because this is the first place it appears in the architecture:

Embedding is **not** another representation of the document.

It is a semantic representation.

I'd like a short paragraph near the beginning explaining something like:

- embedding does not preserve the document itself;
- it produces a numerical representation whose purpose is semantic similarity rather than reconstruction;
- once embeddings exist, the platform has crossed from document processing into semantic search.

This is an important conceptual transition in the overall pipeline and deserves to be stated explicitly.

---

## 4. Add one small illustrative EmbeddingProfile example

The ADR already discusses EmbeddingProfile in depth.

I'd like a tiny illustrative example—not implementation code, simply a conceptual example—similar to the occasional examples used in ADR-0011.

For example:

EmbeddingProfile

- provider
- model
- model revision (where exposed)
- dimensions
- task mode
- normalisation
- adapter version
- fingerprint

The purpose is simply to make the concept immediately concrete for a future reader.

---

## 5. Preserve the existing architectural philosophy

Please keep the ADR's strongest characteristics unchanged.

In particular, I think these sections are excellent and should remain substantially as they are:

- the ADR-0006 historical clarification rather than rewriting history;
- the provider-neutral Embedder boundary;
- the "minimum necessary input" privacy boundary;
- the compatibility invariant;
- controlled re-embedding;
- deterministic lineage where honestly achievable;
- the distinction between semantic results and operational telemetry;
- the statement that the Embedder abstraction makes switching possible without making switching free.

Those are all exactly the kind of engineering reasoning I want this repository to demonstrate.

No implementation changes.

ADR only.