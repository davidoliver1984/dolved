Excellent. I think ADR-0013 is now very close to acceptance.

I have only two small refinements before approving it.

---

## 1. Slight wording improvement in "What an embedding represents"

Current wording:

> "...a coordinate in a space defined entirely by the embedding model."

Please consider revising "defined entirely" to something slightly more precise, for example:

> "...a coordinate in a semantic space defined by the embedding model's learned representation."

or

> "...a coordinate in a semantic space whose geometry is defined by the embedding model."

Reasoning:

The model defines the geometry of the embedding space, but the document content determines where an individual embedding sits within that space. "Entirely" slightly overstates the model's role. This is purely a wording refinement rather than an architectural change.

---

## 2. Add a small illustrative EmbeddingProfile example

The EmbeddingProfile section is excellent, but I think it would benefit from a tiny example showing what is actually being fingerprinted.

Something like:

```text
EmbeddingProfile

provider: voyage
model: voyage-3-large
dimensions: 1024
task_mode: document
adapter_version: 1
fingerprint: 5c2d...
```

This example is illustrative only, not normative.

The goal is simply to make the concept immediately obvious to someone reading the ADR six months from now without needing to infer what an EmbeddingProfile actually contains.

---

Other than those two very minor refinements, I'm happy to approve ADR-0013.

Please make only these documentation improvements.

Do not change any architectural decisions, implementation requirements, scope boundaries, or roadmap references.