# EXP-0007 V3 engineering regression confirmation

`EXP-0007-v3-engineering-regression-confirmation` is the immutable,
engineering-only end-to-end confirmation boundary for the ten reviewed
Benchmark V3 regression cases and their 31 variants.

The definition binds Benchmark V3 authoring/population identity, the final
MATERIALISED provisioning record, organisation and catalogue digests, the
active 100-point dense+sparse corpus generation, ADR-0022-v4 planner lineage,
and the unchanged experimental retrieval configuration.

This population is development/regression evidence. It is not calibration,
held-out acceptance, or independent generalisation evidence.

## Frozen retrieval configuration

- Dense: Voyage `voyage-4-large`, 1024 dimensions, candidate K 40.
- Sparse: `prithivida/Splade_PP_en_v1`, candidate K 40.
- Fusion: equal-weight application-owned RRF, `rrf_k` 5, fusion K 15.
- Reranker: Voyage `rerank-2.5`, candidate K 15.
- Observational evidence threshold: `0.337890625`.
- Final evidence K: 5.

## Runtime boundary

The dedicated runtime mounts only the V3 engineering corpus, expectations,
population manifest, independence evidence, organisation/catalogue inputs,
the verified provisioning record, contracts, and exact application source.
Calibration, held-out, V2 engineering, and broad evaluation paths are
prohibited.

The current boundary provides definition and provider-free preflight only.
No provider-backed EXP-0007 execution is performed by the runtime script until
that execution is deliberately approved.
